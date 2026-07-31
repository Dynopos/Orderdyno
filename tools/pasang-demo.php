<?php
/* ==========================================================================
   tools/pasang-demo.php
   --------------------------------------------------------------------------
   Pasang kedai demo (Restoran Doa Ibu) terus ke server, supaya SESIAPA yang
   membuka laman itu nampak menu penuh — tanpa perlu menekan apa-apa dalam
   panel, dan tanpa bergantung pada localStorage pelayar pemilik.

   Ini untuk laman demo. Kedai sebenar sepatutnya menerbitkan menu mereka
   sendiri dari panel Edit Menu.

   GUNA (dari terminal atau Forge → Commands):

       php tools/pasang-demo.php

   Ia akan:
     · membaca assets/js/contoh-menu.js (sumber tunggal data demo)
     · menerbitkannya sebagai menu awam — inilah yang dilihat pelanggan,
       dan yang digunakan server untuk mengira harga bayaran
     · menetapkan mod sandbox supaya ujian tidak melibatkan duit sebenar
     · mengaktifkan saluran FPX + DuitNow QR untuk paparan cart

   Kredensial Bayarcash TIDAK disentuh — ia mesti diisi sendiri dari panel,
   kerana hanya pemilik akaun boleh menjananya.

   Untuk membuang demo dan kembali ke laman kosong:

       php tools/pasang-demo.php --buang
   ========================================================================== */

declare(strict_types=1);

/* Skrip ini menulis terus ke storan tanpa kunci admin, jadi ia mesti tidak
   boleh dicapai melalui pelayar. */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Skrip ini hanya boleh dijalankan dari terminal.\n");
}

require_once __DIR__ . '/../api/lib/tetapan.php';

$akar   = dirname(__DIR__);
$buang  = in_array('--buang', $argv, true);
$tetapan = new Tetapan();

/* ------------------------------- BUANG ---------------------------------- */

if ($buang) {
    $fail = SimpananSelamat::cari(Tetapan::failMenu());
    if ($fail === null) {
        echo "Tiada menu diterbitkan — tiada apa untuk dibuang.\n";
        exit(0);
    }
    @unlink($fail);
    echo "Menu demo dibuang. Pelawat kini nampak template kosong semula.\n";
    exit(0);
}

/* ------------------------- BACA SUMBER DEMO ------------------------------ */

$sumber = $akar . '/assets/js/contoh-menu.js';
if (!is_file($sumber)) {
    fwrite(STDERR, "Tidak jumpa $sumber\n");
    exit(1);
}

$teks = (string) file_get_contents($sumber);

/* Fail itu ialah komen + `const CONTOH_MENU = { ...JSON... };`
   Ambil objek antara '{' pertama dan '}' terakhir. */
$mula = strpos($teks, '{');
$tamat = strrpos($teks, '}');
if ($mula === false || $tamat === false || $tamat <= $mula) {
    fwrite(STDERR, "Tidak jumpa objek CONTOH_MENU dalam $sumber\n");
    exit(1);
}

$mentah = substr($teks, $mula, $tamat - $mula + 1);
$config = json_decode($mentah, true);

if (!is_array($config) || empty($config['menu'])) {
    fwrite(STDERR, "CONTOH_MENU bukan JSON yang sah: " . json_last_error_msg() . "\n");
    fwrite(STDERR, "Pastikan objek itu ditulis sebagai JSON (kunci berpetik, tiada komen di dalam).\n");
    exit(1);
}

/* ---------------------------- TERBITKAN MENU ----------------------------- */

try {
    $hasil = $tetapan->simpanMenu($mentah);
} catch (Throwable $e) {
    fwrite(STDERR, 'Gagal terbitkan menu: ' . $e->getMessage() . "\n");
    exit(1);
}

echo "Menu demo diterbitkan.\n";
echo '  Kedai    : ' . ($config['kedai']['nama'] ?? '—') . "\n";
echo '  Kategori : ' . count($config['kategori'] ?? []) . "\n";
echo '  Item     : ' . $hasil['item'] . "\n";

/* --------------------- MOD SANDBOX + SALURAN PAPARAN --------------------- */

try {
    $tetapan->simpanKredensial([
        'persekitaran' => 'sandbox',
        'saluran'      => [1, 5],   // FPX + DuitNow QR
    ]);
    echo "Mod sandbox ditetapkan, saluran FPX + DuitNow QR diaktifkan.\n";
} catch (Throwable $e) {
    echo 'Amaran: gagal simpan tetapan saluran — ' . $e->getMessage() . "\n";
}

/* ------------------------------ RUMUSAN ---------------------------------- */

$segar     = new Tetapan();
$halangan  = $segar->halangan();
$perluIsi  = array_values(array_intersect(
    $halangan,
    ['pat_kosong', 'secret_key_kosong', 'portal_key_kosong']
));

echo "\n";
if (!$perluIsi) {
    echo "Siap. Pembayaran online sudah aktif — buka laman anda untuk demo.\n";
    exit(0);
}

echo "Menu sudah siap dan kelihatan kepada semua pelawat.\n";
echo "Yang tinggal hanya kredensial Bayarcash — hanya anda boleh menjananya:\n\n";
echo "  1. Daftar/log masuk di https://console.bayarcash-sandbox.com\n";
echo "  2. Ambil Personal Access Token, API Secret Key, Portal Key\n";
echo "  3. Laman anda → Edit Menu → tab Bayaran → masukkan kunci admin,\n";
echo "     tampal ketiga-tiganya, tekan Simpan kredensial\n\n";
echo "Sehingga itu, cart menunjukkan butang WhatsApp sahaja (tanpa Bayar Online).\n";
exit(0);
