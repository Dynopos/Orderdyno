<?php
/* ==========================================================================
   Simpanan Selamat — fail data yang tidak boleh dibaca melalui web
   --------------------------------------------------------------------------
   MASALAH:
     Kredensial Bayarcash dan rekod order disimpan sebagai fail dalam
     api/data/. Pada Apache, .htaccess menghalang capaian web. Tetapi pada
     nginx (Laravel Forge, Ploi, dan hampir semua VPS moden) .htaccess
     DIABAIKAN SEPENUHNYA — jadi sesiapa boleh buka
     https://kedai.com/api/data/bayarcash.json dan terus dapat API Secret Key.

   PENYELESAIAN:
     Setiap fail data disimpan dengan sambungan .php dan bermula dengan
     baris pengawal:

         <?php exit; ?>
         {"pat":"...","secret_key":"..."}

     Kalau seseorang cuba buka fail itu melalui pelayar, PHP-FPM akan
     melaksanakannya, `exit` berjalan serta-merta, dan pelawat dapat halaman
     kosong. Data selepas pengawal tidak pernah dihantar.

     Bila kod kita membacanya, kita buang baris pengawal dan parse JSON.

   Ini berfungsi pada Apache, nginx, LiteSpeed, Caddy — apa sahaja yang
   melaksanakan PHP. Ia tidak bergantung pada konfigurasi server.
   ========================================================================== */

declare(strict_types=1);

final class SimpananSelamat
{
    /* Baris pengawal. Jangan ubah — fail lama bergantung padanya. */
    private const PENGAWAL = "<?php exit; ?>\n";

    /* ------------------------------- BACA -------------------------------- */

    /*
     * Baca kandungan JSON mentah dari fail terlindung.
     * Fail .json lama (tanpa pengawal) masih dibaca supaya pemasangan
     * yang sudah ada tidak rosak selepas kemas kini.
     */
    public static function bacaMentah(string $fail): ?string
    {
        if (!is_file($fail)) {
            return null;
        }

        $kandungan = file_get_contents($fail);
        if ($kandungan === false) {
            return null;
        }

        if (str_starts_with($kandungan, self::PENGAWAL)) {
            return substr($kandungan, strlen(self::PENGAWAL));
        }

        /* Fail tanpa pengawal — kemungkinan format lama. Buang apa-apa
           pembuka PHP yang ada, kalau tidak pulangkan seadanya. */
        if (str_starts_with($kandungan, '<?php')) {
            $hujung = strpos($kandungan, "?>\n");
            if ($hujung !== false) {
                return substr($kandungan, $hujung + 3);
            }
            return null;
        }

        return $kandungan;
    }

    /* Baca dan parse sebagai array */
    public static function baca(string $fail): ?array
    {
        $mentah = self::bacaMentah($fail);
        if ($mentah === null) {
            return null;
        }
        $data = json_decode($mentah, true);
        return is_array($data) ? $data : null;
    }

    /* ------------------------------ TULIS -------------------------------- */

    /* Tulis rentetan JSON mentah dengan baris pengawal di hadapan */
    public static function tulisMentah(string $fail, string $mentah): void
    {
        $bait = self::PENGAWAL . $mentah;

        /* Tulis ke fail sementara dahulu, kemudian rename — supaya pembaca
           tidak pernah nampak fail separuh siap. */
        $sementara = $fail . '.tmp' . bin2hex(random_bytes(4));

        if (file_put_contents($sementara, $bait, LOCK_EX) === false) {
            @unlink($sementara);
            throw new RuntimeException('Gagal tulis ' . basename($fail));
        }

        @chmod($sementara, 0640);

        if (!@rename($sementara, $fail)) {
            @unlink($sementara);
            throw new RuntimeException('Gagal simpan ' . basename($fail));
        }
    }

    /* Tulis array sebagai JSON terlindung */
    public static function tulis(string $fail, array $data, bool $cantik = true): void
    {
        $bendera = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | ($cantik ? JSON_PRETTY_PRINT : 0);
        $json = json_encode($data, $bendera);
        if ($json === false) {
            throw new RuntimeException('Gagal encode data untuk ' . basename($fail));
        }
        self::tulisMentah($fail, $json);
    }

    /* ------------------------------ UTILITI ------------------------------ */

    /*
     * Tukar laluan .json lama menjadi .php.
     * Digunakan untuk mencari fail dalam kedua-dua format.
     */
    public static function laluanLama(string $failPhp): string
    {
        return preg_replace('/\.php$/', '.json', $failPhp) ?? $failPhp;
    }

    /* Cari fail sama ada dalam format baru (.php) atau lama (.json) */
    public static function cari(string $failPhp): ?string
    {
        if (is_file($failPhp)) {
            return $failPhp;
        }
        $lama = self::laluanLama($failPhp);
        return is_file($lama) ? $lama : null;
    }

    /* Buang fail dalam kedua-dua format */
    public static function buang(string $failPhp): void
    {
        foreach ([$failPhp, self::laluanLama($failPhp)] as $f) {
            if (is_file($f)) {
                @unlink($f);
            }
        }
    }

    /* Senarai fail data dalam folder, kedua-dua format */
    public static function senarai(string $dir): array
    {
        if (!is_dir($dir)) {
            return [];
        }
        $fail = array_merge(glob($dir . '/*.php') ?: [], glob($dir . '/*.json') ?: []);
        return array_values(array_filter($fail, static fn ($f) => !str_contains(basename($f), '.tmp')));
    }
}
