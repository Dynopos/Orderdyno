<?php
/* ==========================================================================
   Ikon — ikon aplikasi untuk skrin utama telefon
   --------------------------------------------------------------------------
   Setiap kedai pada pemasangan ini berkongsi ikon yang SAMA: lambang
   OrderDyno. Itu keputusan jenama, bukan had teknikal — bila pelanggan
   sesebuah kedai memasang laman ke telefon mereka, lambang itu hadir pada
   skrin utama mereka.

   Nama aplikasi tetap nama kedai (lihat api/manifest.php), supaya pelanggan
   yang memasang dua kedai masih dapat membezakannya.

   Logo dan emoji yang dipilih pemilik kedai masih digunakan pada laman itu
   sendiri — cuma bukan sebagai ikon aplikasi.
   ========================================================================== */

declare(strict_types=1);

require_once __DIR__ . '/tetapan.php';

final class Ikon
{
    public const SAIZ = [192, 512];

    public static function fail(int $saiz): string
    {
        $saiz = in_array($saiz, self::SAIZ, true) ? $saiz : 512;
        return dirname(__DIR__, 2) . '/assets/img/ikon-' . $saiz . '.png';
    }

    /**
     * Senarai ikon untuk manifest.
     *
     * Sentiasa PNG sebenar yang dibungkus bersama projek — tiada penjanaan,
     * jadi tiada kebergantungan pada sambungan GD dan rupanya sama pada
     * setiap pemasangan.
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
