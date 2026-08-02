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

   Untuk memasang ke dalam kedai tertentu (mod banyak kedai / subdomain):

       php tools/pasang-demo.php --kedai=demo

   Itu menerbitkan menu ke demo.orderdyno.my dan bukan ke domain utama —
   berguna bila domain utama digunakan sebagai halaman jualan.
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

/* --kedai=<slug> memasang ke dalam kedai pelanggan, bukan kedai utama. */
$slug = null;
foreach ($argv as $a) {
    if (str_starts_with((string) $a, '--kedai=')) {
        $slug = strtolower(trim(substr($a, 8)));
    }
}

if ($slug !== null) {
    if (Kedai::ambil($slug) === null) {
        fwrite(STDERR, "Kedai '$slug' tidak wujud. Ciptanya dahulu dalam api/pentadbir.php.\n");
        exit(1);
    }
    /* Tetapan membaca folder data melalui Kedai::dirSemasa(), yang biasanya
       ditentukan oleh Host. Dalam CLI tiada Host, jadi kita palsukannya. */
    $_SERVER['HTTP_HOST'] = $slug . '.' . Kedai::domainAsas();
    echo "Memasang ke kedai: $slug\n";
}

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

/* Pembayaran tiruan dihidupkan HANYA kalau tiada kredensial Bayarcash sebenar.
   Kedai yang sudah beroperasi tidak boleh bertukar menjadi tiruan secara tidak
   sengaja — dan sebaik sahaja kredensial diisi kemudian, Tetapan::modDemo()
   mematikannya sendiri. */
$adaKredensial = $tetapan->pat() !== ''
    || $tetapan->secretKey() !== ''
    || $tetapan->portalKey() !== '';

try {
    $tetapan->simpanKredensial([
        'persekitaran' => 'sandbox',
        'saluran'      => [1, 5],   // FPX + DuitNow QR
        'mod_demo'     => !$adaKredensial,
    ]);
    echo "Mod sandbox ditetapkan, saluran FPX + DuitNow QR diaktifkan.\n";
} catch (Throwable $e) {
    echo 'Amaran: gagal simpan tetapan saluran — ' . $e->getMessage() . "\n";
}

/* ------------------------------ RUMUSAN ---------------------------------- */

$segar = new Tetapan();

echo "\n";

if (!$segar->modDemo()) {
    echo "Siap. Kredensial Bayarcash sebenar dikesan, jadi pembayaran memproses\n";
    echo "transaksi sebenar seperti biasa — pembayaran tiruan TIDAK dihidupkan.\n";
    exit(0);
}

echo "Siap — aliran pembayaran penuh sudah boleh didemokan.\n\n";
echo "Butang \"Bayar Online\" muncul dalam cart. Pelanggan boleh pilih saluran,\n";
echo "isi maklumat, dan melihat skrin resit — tetapi pembayaran itu DITIRU:\n";
echo "tiada duit bergerak, tiada bank, dan tiada panggilan ke Bayarcash.\n\n";
echo "Halaman bayaran demo mempunyai dua butang, supaya anda boleh tunjukkan\n";
echo "kedua-dua keadaan: bayaran berjaya dan bayaran gagal.\n\n";
echo "Untuk beralih ke pembayaran SEBENAR: isi kredensial Bayarcash dalam\n";
echo "Edit Menu → tab Bayaran. Pembayaran tiruan mati sendiri sebaik sahaja\n";
echo "kredensial wujud — tiada langkah tambahan diperlukan.\n";
exit(0);
