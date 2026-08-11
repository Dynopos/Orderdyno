<?php
/* ==========================================================================
   Ikon — ikon aplikasi bagi kedai pada subdomain semasa
   --------------------------------------------------------------------------
   Bila pelanggan memasang laman ke skrin utama telefon, ikon yang muncul
   mesti ikon KEDAI itu. Satu pemasangan menghidangkan ramai kedai, jadi
   ikon tidak boleh menjadi fail statik.

   Dua sumber sahaja:

     1. Ikon yang dijana pelayar semasa pemilik menerbitkan menu. Ini yang
        terbaik — emoji berwarna datang dari font peranti, dan server tidak
        semestinya mempunyai font emoji langsung.

     2. Lambang OrderDyno yang dibungkus bersama projek. Kedai yang belum
        menerbitkan dari panel mendapat ini.

   Sandaran ialah fail PNG sebenar, bukan lukisan GD. Itu bermakna ikon
   berfungsi walaupun sambungan GD tiada pada server, dan rupanya sama pada
   setiap pemasangan.

   Ikon disimpan berasingan daripada menu dengan sengaja: PNG 512px ialah
   puluhan kilobait base64, dan menu awam dimuat turun oleh setiap pelawat
   pada setiap lawatan.
   ========================================================================== */

declare(strict_types=1);

require_once __DIR__ . '/tetapan.php';

final class Ikon
{
    public const HAD_BAIT = 262144;      // 256KB — jauh lebih besar dari PNG 512px biasa
    public const SAIZ = [192, 512];

    public static function fail(): string
    {
        return Tetapan::dirData() . '/ikon.php';
    }

    /* Lambang OrderDyno yang dibungkus bersama projek */
    public static function failAsal(int $saiz): string
    {
        $saiz = in_array($saiz, self::SAIZ, true) ? $saiz : 512;
        return dirname(__DIR__, 2) . '/assets/img/ikon-' . $saiz . '.png';
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

    public static function adaGd(): bool
    {
        return function_exists('imagecreatetruecolor') && function_exists('imagepng');
    }

    /**
     * Senarai ikon untuk manifest. Sentiasa PNG — sandaran ialah fail sebenar
     * yang dibungkus bersama projek, jadi tiada keadaan di mana kita
     * mengisytiharkan image/png tetapi menghidangkan sesuatu yang lain.
     */
    public static function senarai(): array
    {
        return [
            ['src' => '/api/ikon.php?s=192', 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
            ['src' => '/api/ikon.php?s=512', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
            ['src' => '/api/ikon.php?s=512', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
        ];
    }
}
