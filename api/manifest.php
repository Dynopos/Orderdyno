<?php
/* ==========================================================================
   GET api/manifest.php
   --------------------------------------------------------------------------
   Manifest aplikasi web, dijana untuk kedai pada subdomain ini.

   Ia mesti dijana, bukan fail statik: satu pemasangan menghidangkan ramai
   kedai, jadi manifest yang sama akan menamakan setiap kedai "OrderDyno"
   dan memberi mereka ikon yang sama. Bila pelanggan memasang laman ke skrin
   utama, yang mesti muncul ialah nama dan ikon KEDAI itu.

   Tiada kredensial di sini — hanya apa yang memang dipaparkan pada laman.
   ========================================================================== */

declare(strict_types=1);

require_once __DIR__ . '/lib/http.php';
require_once __DIR__ . '/lib/ikon.php';

wajib_kaedah('GET');

if (!Kedai::wujud()) {
    json_keluar(['ok' => false, 'kedaiTiada' => true], 404);
}

$tetapan = new Tetapan();
$menu = $tetapan->menuTersimpan();

$kedai = is_array($menu['kedai'] ?? null) ? $menu['kedai'] : [];
$tema  = is_array($menu['tema'] ?? null) ? $menu['tema'] : [];

$nama = trim((string) ($kedai['nama'] ?? ''));
if ($nama === '' || $nama === 'Nama Kedai Anda') {
    $nama = 'Menu Online';
}

/* Nama pendek muncul di bawah ikon pada skrin utama — ruang di situ sempit,
   jadi ambil dua patah perkataan pertama dan hadkan panjangnya. */
$pendek = $nama;
if (mb_strlen($pendek) > 12) {
    $kata = preg_split('/\s+/', $nama) ?: [$nama];
    $pendek = mb_substr(trim(($kata[0] ?? '') . ' ' . ($kata[1] ?? '')), 0, 12);
}

$latar = (string) ($tema['latar'] ?? '#0b0616');
if (!preg_match('/^#[0-9a-f]{6}$/i', $latar)) {
    $latar = '#0b0616';
}

/* Kedai berada pada akar hostnya sendiri, jadi skop ialah keseluruhan laman.
   Itu penting: tanpanya, halaman bayaran terbuka di luar aplikasi dan
   pelanggan kehilangan konteks di tengah-tengah pembayaran. */
$manifest = [
    'id'               => '/',
    'name'             => $nama,
    'short_name'       => $pendek,
    'description'      => trim((string) ($kedai['tagline'] ?? '')) ?: 'Menu online — order terus dari telefon anda.',
    'start_url'        => '/',
    'scope'            => '/',
    'display'          => 'standalone',
    'orientation'      => 'portrait',
    'background_color' => $latar,
    'theme_color'      => $latar,
    'lang'             => 'ms',
    'dir'              => 'ltr',
    'categories'       => ['food', 'shopping'],
    'icons'            => Ikon::senarai(),
];

header('Content-Type: application/manifest+json; charset=utf-8');
header('Cache-Control: public, max-age=300');
header('X-Content-Type-Options: nosniff');
echo json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
