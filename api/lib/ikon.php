<?php
/* ==========================================================================
   Ikon — ikon aplikasi bagi kedai pada subdomain semasa
   --------------------------------------------------------------------------
   Bila pelanggan memasang laman ke skrin utama telefon, ikon yang muncul
   mesti ikon KEDAI itu. Satu pemasangan menghidangkan ramai kedai, jadi
   ikon tidak boleh menjadi fail statik.

   Tiga sumber, mengikut keutamaan:

     1. Ikon yang dijana pelayar semasa pemilik menerbitkan menu. Ini yang
        terbaik — emoji berwarna datang dari font peranti, dan server tidak
        semestinya mempunyai font emoji langsung.

     2. Jubin warna tema yang dilukis dengan GD. Tiada emoji, tetapi ia
        mengikut warna kedai dan berfungsi tanpa apa-apa font.

     3. SVG yang sama, untuk server tanpa GD.

   Ikon disimpan berasingan daripada menu dengan sengaja: PNG 512px ialah
   puluhan kilobait base64, dan menu awam dimuat turun oleh setiap pelawat
   pada setiap lawatan.
   ========================================================================== */

declare(strict_types=1);

require_once __DIR__ . '/tetapan.php';

final class Ikon
{
    public const HAD_BAIT = 262144;      // 256KB — jauh lebih besar dari PNG 512px biasa

    public static function fail(): string
    {
        return Tetapan::dirData() . '/ikon.php';
    }

    /* PNG mentah yang disimpan pemilik kedai, atau null */
    public static function simpanan(): ?string
    {
        $fail = SimpananSelamat::cari(self::fail());
        if ($fail === null) {
            return null;
        }
        $mentah = SimpananSelamat::bacaMentah($fail);
        if ($mentah === null || $mentah === '') {
            return null;
        }
        $bait = base64_decode(trim($mentah), true);
        return ($bait === false || $bait === '') ? null : $bait;
    }

    /**
     * Terima data URI dari panel dan simpan.
     * @throws InvalidArgumentException
     */
    public static function simpan(string $dataUri): int
    {
        if (!preg_match('#^data:image/png;base64,([A-Za-z0-9+/=]+)$#', trim($dataUri), $p)) {
            throw new InvalidArgumentException('Ikon mesti PNG dalam bentuk data URI.');
        }

        $bait = base64_decode($p[1], true);
        if ($bait === false || $bait === '') {
            throw new InvalidArgumentException('Ikon tidak boleh dibaca.');
        }
        if (strlen($bait) > self::HAD_BAIT) {
            throw new InvalidArgumentException('Ikon terlalu besar (had 256KB).');
        }

        /* Sahkan ia benar-benar PNG, bukan fail lain yang dinamakan PNG */
        $saiz = @getimagesizefromstring($bait);
        if ($saiz === false || ($saiz[2] ?? 0) !== IMAGETYPE_PNG) {
            throw new InvalidArgumentException('Ikon bukan imej PNG yang sah.');
        }

        Tetapan::sediakanDirData();
        SimpananSelamat::tulisMentah(self::fail(), base64_encode($bait));

        return strlen($bait);
    }

    public static function buang(): void
    {
        $fail = SimpananSelamat::cari(self::fail());
        if ($fail !== null) {
            @unlink($fail);
        }
    }

    /* ---------------------------- WARNA TEMA ------------------------------ */

    /** @return array{0:int,1:int,2:int} */
    private static function rgb(string $hex, array $ganti): array
    {
        $h = ltrim(trim($hex), '#');
        if (strlen($h) === 3) {
            $h = $h[0] . $h[0] . $h[1] . $h[1] . $h[2] . $h[2];
        }
        if (!preg_match('/^[0-9a-f]{6}$/i', $h)) {
            return $ganti;
        }
        $n = (int) hexdec($h);
        return [($n >> 16) & 255, ($n >> 8) & 255, $n & 255];
    }

    /** @return array{latar:array,warna:array} */
    public static function tema(): array
    {
        $menu = (new Tetapan())->menuTersimpan();
        $t = is_array($menu['tema'] ?? null) ? $menu['tema'] : [];

        return [
            'latar' => self::rgb((string) ($t['latar'] ?? ''), [11, 6, 22]),
            'warna' => self::rgb((string) ($t['warna1'] ?? ''), [168, 85, 247]),
        ];
    }

    /* ------------------------------ LUKISAN ------------------------------- */

    public static function adaGd(): bool
    {
        return function_exists('imagecreatetruecolor') && function_exists('imagepng');
    }

    /**
     * Jubin warna tema — tiada teks, jadi tiada font diperlukan.
     *
     * Warna rata dengan satu bulatan, bukan gradien: gradien licin
     * menjadikan PNG 512px ratusan kilobait kerana hampir setiap piksel
     * berbeza dan tiada apa untuk dimampatkan.
     */
    public static function lukisPng(int $saiz): ?string
    {
        if (!self::adaGd()) {
            return null;
        }

        $t = self::tema();
        $img = imagecreatetruecolor($saiz, $saiz);
        if ($img === false) {
            return null;
        }

        $latar = imagecolorallocate($img, ...$t['latar']);
        $warna = imagecolorallocate($img, ...$t['warna']);
        if ($latar === false || $warna === false) {
            imagedestroy($img);
            return null;
        }

        imagefilledrectangle($img, 0, 0, $saiz, $saiz, $latar);

        /* Bulatan dikekalkan dalam zon selamat supaya ia tidak dipotong bila
           Android menggunakan topeng bulat pada ikon. */
        $d = (int) round($saiz * 0.44);
        imagefilledellipse($img, intdiv($saiz, 2), intdiv($saiz, 2), $d, $d, $warna);

        ob_start();
        imagepng($img, null, 9);
        $png = (string) ob_get_clean();
        imagedestroy($img);

        return $png !== '' ? $png : null;
    }

    /** Sandaran untuk server tanpa GD */
    public static function lukisSvg(): string
    {
        $t = self::tema();
        $latar = sprintf('#%02x%02x%02x', ...$t['latar']);
        $warna = sprintf('#%02x%02x%02x', ...$t['warna']);

        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" width="512" height="512">'
            . '<rect width="512" height="512" fill="' . $latar . '"/>'
            . '<circle cx="256" cy="256" r="113" fill="' . $warna . '"/>'
            . '</svg>';
    }

    /* ----------------------------- MANIFEST ------------------------------- */

    /**
     * Senarai ikon untuk manifest.
     *
     * PNG diisytiharkan hanya bila kita benar-benar boleh menghasilkannya —
     * mengisytiharkan image/png yang kemudiannya menjadi SVG akan membuatkan
     * Chrome menolak ikon itu, dan laman menjadi tidak boleh dipasang.
     */
    public static function senarai(): array
    {
        $adaPng = self::simpanan() !== null || self::adaGd();

        if (!$adaPng) {
            return [[
                'src'     => '/api/ikon.php?jenis=svg',
                'sizes'   => 'any',
                'type'    => 'image/svg+xml',
                'purpose' => 'any maskable',
            ]];
        }

        return [
            [
                'src'     => '/api/ikon.php?s=192',
                'sizes'   => '192x192',
                'type'    => 'image/png',
                'purpose' => 'any',
            ],
            [
                'src'     => '/api/ikon.php?s=512',
                'sizes'   => '512x512',
                'type'    => 'image/png',
                'purpose' => 'any',
            ],
            [
                'src'     => '/api/ikon.php?s=512',
                'sizes'   => '512x512',
                'type'    => 'image/png',
                'purpose' => 'maskable',
            ],
        ];
    }
}
