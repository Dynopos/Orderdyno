<?php
/* ==========================================================================
   Kedai — sokongan banyak kedai pada satu server (subdomain)
   --------------------------------------------------------------------------
   Satu pemasangan OrderDyno boleh menghidangkan ramai pelanggan:

       orderdyno.my              → kedai utama (demo / laman jualan anda)
       nasilemakali.orderdyno.my → kedai pelanggan A
       kedaisiti.orderdyno.my    → kedai pelanggan B

   Setiap kedai mempunyai folder datanya sendiri, jadi menu, kredensial
   Bayarcash dan rekod order tidak pernah bercampur:

       api/data/                    ← kedai utama (domain akar)
       api/data/kedai/<slug>/       ← setiap kedai pelanggan

   Kedai utama sengaja kekal di lokasi lama supaya pemasangan sedia ada
   terus berfungsi tanpa perlu memindahkan apa-apa data.

   Mod ini hanya aktif kalau 'domain_asas' ditetapkan dalam api/config.php.
   Tanpa itu, semuanya berkelakuan seperti sebelum ini — satu kedai sahaja.
   ========================================================================== */

declare(strict_types=1);

require_once __DIR__ . '/simpanan.php';

final class Kedai
{
    /* Subdomain yang tidak boleh dijual — ia milik infrastruktur */
    public const TEMPAHAN = [
        'www', 'api', 'admin', 'pentadbir', 'mail', 'smtp', 'imap', 'pop',
        'ftp', 'ns1', 'ns2', 'dns', 'cpanel', 'webmail', 'cdn', 'static',
        'assets', 'app', 'staging', 'test', 'dev', 'blog', 'shop', 'my',
    ];

    private static ?array $config = null;
    private static bool $sudahKesan = false;
    private static ?string $slug = null;

    /* ------------------------------ CONFIG -------------------------------- */

    private static function config(): array
    {
        if (self::$config === null) {
            $fail = __DIR__ . '/../config.php';
            $d = is_file($fail) ? require $fail : [];
            self::$config = is_array($d) ? $d : [];
        }
        return self::$config;
    }

    /* Domain induk, contoh 'orderdyno.my'. Kosong = mod satu kedai. */
    public static function domainAsas(): string
    {
        $d = trim((string) (self::config()['domain_asas'] ?? ''));
        return strtolower(ltrim($d, '.'));
    }

    public static function banyakKedai(): bool
    {
        return self::domainAsas() !== '';
    }

    /* ---------------------------- KESAN SLUG ------------------------------ */

    /**
     * Slug kedai untuk permintaan semasa, atau null untuk kedai utama.
     *
     * Diambil dari Host. Host boleh dipalsukan oleh penyerang, tetapi ia
     * tidak memberi apa-apa faedah di sini: slug yang tidak terdaftar
     * ditolak, dan slug yang sah hanya membawa kepada data kedai itu —
     * yang memang dihidangkan secara awam pada subdomainnya sendiri.
     */
    public static function slug(): ?string
    {
        if (self::$sudahKesan) {
            return self::$slug;
        }
        self::$sudahKesan = true;
        self::$slug = null;

        $asas = self::domainAsas();
        if ($asas === '') {
            return null;   // mod satu kedai
        }

        $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
        $host = explode(':', $host)[0];            // buang port
        $host = rtrim($host, '.');

        if ($host === '' || $host === $asas || $host === 'www.' . $asas) {
            return null;   // domain akar → kedai utama
        }

        $hujung = '.' . $asas;
        if (!str_ends_with($host, $hujung)) {
            return null;   // domain lain sama sekali → layan sebagai utama
        }

        $depan = substr($host, 0, -strlen($hujung));
        if ($depan === '' || str_contains($depan, '.')) {
            return null;   // sub-sub-domain tidak disokong
        }

        self::$slug = self::slugSah($depan) ? $depan : '__tidaksah';
        return self::$slug;
    }

    /* Adakah permintaan ini untuk kedai pelanggan (bukan kedai utama)? */
    public static function adaSlug(): bool
    {
        return self::slug() !== null;
    }

    /* Kedai yang diminta wujud dan aktif? */
    public static function wujud(): bool
    {
        $slug = self::slug();
        if ($slug === null) {
            return true;   // kedai utama sentiasa ada
        }
        $rekod = self::ambil($slug);
        return $rekod !== null && !empty($rekod['aktif']);
    }

    /* ---------------------------- PENGESAHAN ------------------------------ */

    public static function slugSah(string $slug): bool
    {
        if (!preg_match('/^[a-z0-9](?:[a-z0-9-]{1,28}[a-z0-9])$/', $slug)) {
            return false;
        }
        if (str_contains($slug, '--')) {
            return false;
        }
        return !in_array($slug, self::TEMPAHAN, true);
    }

    /* Sebab penolakan, untuk dipaparkan dalam panel pentadbir */
    public static function sebabSlugTolak(string $slug): string
    {
        if ($slug === '') {
            return 'Alamat kedai belum diisi.';
        }
        if (in_array($slug, self::TEMPAHAN, true)) {
            return "'$slug' dikhaskan untuk sistem — pilih nama lain.";
        }
        if (!preg_match('/^[a-z0-9-]+$/', $slug)) {
            return 'Guna huruf kecil, nombor dan tanda sengkang sahaja.';
        }
        if (strlen($slug) < 3 || strlen($slug) > 30) {
            return 'Panjang mesti antara 3 hingga 30 aksara.';
        }
        if ($slug[0] === '-' || $slug[-1] === '-' || str_contains($slug, '--')) {
            return 'Tidak boleh bermula, berakhir, atau mempunyai dua sengkang berturut.';
        }
        return 'Alamat kedai tidak sah.';
    }

    /* ------------------------------ DAFTAR -------------------------------- */

    private static function failDaftar(): string
    {
        return __DIR__ . '/../data/kedai.php';
    }

    /** @return array<string,array> slug => rekod */
    public static function semua(): array
    {
        $fail = SimpananSelamat::cari(self::failDaftar());
        if ($fail === null) {
            return [];
        }
        $d = SimpananSelamat::baca($fail);
        return is_array($d['kedai'] ?? null) ? $d['kedai'] : [];
    }

    public static function ambil(string $slug): ?array
    {
        return self::semua()[$slug] ?? null;
    }

    private static function tulisDaftar(array $kedai): void
    {
        $dir = __DIR__ . '/../data';
        if (!is_dir($dir)) {
            @mkdir($dir, 0750, true);
        }
        SimpananSelamat::tulis(self::failDaftar(), ['kedai' => $kedai]);
    }

    /**
     * Cipta kedai baharu. Memulangkan rekodnya (termasuk kunci admin
     * dalam bentuk teks — ini satu-satunya masa ia boleh dibaca penuh).
     *
     * @throws InvalidArgumentException
     */
    public static function cipta(string $slug, string $nama): array
    {
        $slug = strtolower(trim($slug));
        $nama = trim($nama);

        if (!self::slugSah($slug)) {
            throw new InvalidArgumentException(self::sebabSlugTolak($slug));
        }
        if ($nama === '' || mb_strlen($nama) > 80) {
            throw new InvalidArgumentException('Nama kedai mesti antara 1 hingga 80 aksara.');
        }

        $semua = self::semua();
        if (isset($semua[$slug])) {
            throw new InvalidArgumentException("Alamat '$slug' sudah digunakan oleh kedai lain.");
        }

        $rekod = [
            'nama'    => $nama,
            'kunci'   => bin2hex(random_bytes(18)),
            'dicipta' => gmdate('c'),
            'aktif'   => true,
        ];

        $semua[$slug] = $rekod;
        self::tulisDaftar($semua);
        self::sediakanDir($slug);

        return ['slug' => $slug] + $rekod;
    }

    public static function kemas(string $slug, array $ubah): bool
    {
        $semua = self::semua();
        if (!isset($semua[$slug])) {
            return false;
        }
        if (isset($ubah['nama'])) {
            $nama = trim((string) $ubah['nama']);
            if ($nama !== '' && mb_strlen($nama) <= 80) {
                $semua[$slug]['nama'] = $nama;
            }
        }
        if (isset($ubah['aktif'])) {
            $semua[$slug]['aktif'] = (bool) $ubah['aktif'];
        }
        if (!empty($ubah['kunci_baru'])) {
            $semua[$slug]['kunci'] = bin2hex(random_bytes(18));
        }
        self::tulisDaftar($semua);
        return true;
    }

    /** Buang kedai berserta SEMUA datanya. Tidak boleh dibatalkan. */
    public static function padam(string $slug): bool
    {
        $semua = self::semua();
        if (!isset($semua[$slug])) {
            return false;
        }
        unset($semua[$slug]);
        self::tulisDaftar($semua);
        self::buangDir(self::dirUntuk($slug));
        return true;
    }

    /* ------------------------------ FOLDER -------------------------------- */

    public static function dirUntuk(?string $slug): string
    {
        $asas = __DIR__ . '/../data';
        if ($slug === null) {
            return $asas;                 // kedai utama — lokasi lama
        }
        return $asas . '/kedai/' . $slug;
    }

    /* Folder data untuk permintaan semasa */
    public static function dirSemasa(): string
    {
        return self::dirUntuk(self::slug());
    }

    public static function sediakanDir(string $slug): void
    {
        $dir = self::dirUntuk($slug);
        if (!is_dir($dir)) {
            @mkdir($dir, 0750, true);
        }
    }

    private static function buangDir(string $dir): void
    {
        if (!is_dir($dir) || !str_contains($dir, '/data/kedai/')) {
            return;   // jangan sesekali menyentuh folder di luar data/kedai
        }
        $item = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($item as $f) {
            $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
        }
        @rmdir($dir);
    }

    /* ------------------------------- URL ---------------------------------- */

    public static function urlUntuk(string $slug): string
    {
        $asas = self::domainAsas();
        if ($asas === '') {
            return '';
        }
        $skema = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $port = '';
        $hostPenuh = (string) ($_SERVER['HTTP_HOST'] ?? '');
        if (str_contains($hostPenuh, ':')) {
            $port = ':' . explode(':', $hostPenuh)[1];
        }
        return $skema . '://' . $slug . '.' . $asas . $port;
    }

    /* Kunci admin kedai semasa (null = guna kunci_admin dari config.php) */
    public static function kunciSemasa(): ?string
    {
        $slug = self::slug();
        if ($slug === null) {
            return null;
        }
        $rekod = self::ambil($slug);
        return $rekod === null ? '' : (string) ($rekod['kunci'] ?? '');
    }
}
