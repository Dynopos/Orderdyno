<?php
/* ==========================================================================
   tools/pasang-menu.php
   --------------------------------------------------------------------------
   Pasang menu SEBENAR seorang pelanggan terus ke kedainya di server.

   Ini untuk kes "pelanggan hantar poster menu, kita masukkan untuk mereka".
   Selepas ini pemilik kedai mengedit sendiri melalui panel Edit Menu — fail
   di sini hanyalah benih permulaan, bukan sumber kekal.

   GUNA (dari terminal atau Forge -> Commands):

       php tools/pasang-menu.php --kedai=ayenas --fail=tools/menu/ayenas.json

   Kalau kedai itu belum wujud, tambah --cipta supaya ia dicipta sekali gus.
   Kunci admin kedai akan dicetak SEKALI sahaja — salin dan hantar kepada
   pemilik kedai:

       php tools/pasang-menu.php --kedai=ayenas --fail=tools/menu/ayenas.json --cipta

   Untuk kedai utama (domain akar), tinggalkan --kedai.

   Kredensial Bayarcash TIDAK disentuh. Kedai sebenar mesti mengisi kunci
   mereka sendiri dalam Edit Menu -> tab Bayaran, kerana hanya pemilik akaun
   boleh menjananya. Kalau anda mahu menunjukkan aliran bayaran sebelum itu,
   tambah --demo untuk menghidupkan pembayaran tiruan (tiada duit bergerak).
   ========================================================================== */

declare(strict_types=1);

/* Skrip ini menulis terus ke storan tanpa kunci admin, jadi ia mesti tidak
   boleh dicapai melalui pelayar. */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Skrip ini hanya boleh dijalankan dari terminal.\n");
}

require_once __DIR__ . '/../api/lib/tetapan.php';

$akar = dirname(__DIR__);

/* ------------------------------- ARGUMEN --------------------------------- */

$slug  = null;
$fail  = null;
$cipta = in_array('--cipta', $argv, true);
$demo  = in_array('--demo', $argv, true);

foreach ($argv as $a) {
    $a = (string) $a;
    if (str_starts_with($a, '--kedai=')) {
        $slug = strtolower(trim(substr($a, 8)));
    } elseif (str_starts_with($a, '--fail=')) {
        $fail = trim(substr($a, 7));
    }
}

if ($fail === null || $fail === '') {
    fwrite(STDERR, "Guna: php tools/pasang-menu.php --kedai=<slug> --fail=<fail.json> [--cipta] [--demo]\n");
    exit(1);
}

/* Terima laluan relatif kepada akar projek supaya arahan Forge kekal pendek */
if ($fail[0] !== '/') {
    $fail = $akar . '/' . $fail;
}
if (!is_file($fail)) {
    fwrite(STDERR, "Tidak jumpa fail menu: $fail\n");
    exit(1);
}

/* ---------------------------- BACA & SAHKAN ------------------------------ */

$mentah = (string) file_get_contents($fail);
$config = json_decode($mentah, true);

if (!is_array($config)) {
    fwrite(STDERR, 'Fail menu bukan JSON yang sah: ' . json_last_error_msg() . "\n");
    exit(1);
}

$silap = [];

if (empty($config['menu']) || !is_array($config['menu'])) {
    $silap[] = 'Tiada senarai "menu".';
}
if (empty($config['kategori']) || !is_array($config['kategori'])) {
    $silap[] = 'Tiada senarai "kategori".';
}

if (!$silap) {
    $kategori = [];
    foreach ($config['kategori'] as $k) {
        $kategori[(string) ($k['id'] ?? '')] = true;
    }

    $idDilihat = [];
    foreach ($config['menu'] as $i => $m) {
        $id = (string) ($m['id'] ?? '');
        $nama = (string) ($m['nama'] ?? '');

        if ($id === '') {
            $silap[] = "Item #$i tiada 'id'.";
        } elseif (isset($idDilihat[$id])) {
            $silap[] = "Item '$id' berulang — setiap id mesti unik.";
        }
        $idDilihat[$id] = true;

        if ($nama === '') {
            $silap[] = "Item '$id' tiada nama.";
        }
        if (!isset($kategori[(string) ($m['kategori'] ?? '')])) {
            $silap[] = "Item '$id' merujuk kategori yang tidak wujud.";
        }
        if (!is_numeric($m['harga'] ?? null) || (float) $m['harga'] < 0) {
            $silap[] = "Item '$id' tiada harga yang sah.";
        }
        foreach (['pilihan', 'tambahan'] as $senarai) {
            foreach ((array) ($m[$senarai] ?? []) as $p) {
                if (!is_numeric($p['harga'] ?? null) || (float) $p['harga'] < 0) {
                    $silap[] = "Item '$id' ada $senarai tanpa harga yang sah.";
                }
            }
        }
    }
}

if ($silap) {
    fwrite(STDERR, "Menu ditolak — betulkan dahulu:\n");
    foreach (array_slice($silap, 0, 20) as $s) {
        fwrite(STDERR, "  · $s\n");
    }
    if (count($silap) > 20) {
        fwrite(STDERR, '  · … dan ' . (count($silap) - 20) . " lagi\n");
    }
    exit(1);
}

/* -------------------------- KEDAI (SUBDOMAIN) ---------------------------- */

$namaKedai = trim((string) ($config['kedai']['nama'] ?? '')) ?: ($slug ?? 'Kedai');

if ($slug !== null && $slug !== '') {
    if (Kedai::ambil($slug) === null) {
        if (!$cipta) {
            fwrite(STDERR, "Kedai '$slug' tidak wujud. Tambah --cipta, atau ciptanya dalam api/pentadbir.php.\n");
            exit(1);
        }
        try {
            $baru = Kedai::cipta($slug, $namaKedai);
        } catch (Throwable $e) {
            fwrite(STDERR, 'Gagal cipta kedai: ' . $e->getMessage() . "\n");
            exit(1);
        }
        echo "Kedai '$slug' dicipta — $namaKedai\n";
        echo '  KUNCI ADMIN: ' . ($baru['kunci'] ?? '—') . "\n";
        echo "  (Ini satu-satunya masa kunci ini boleh dibaca. Salin sekarang.)\n\n";
    }

    /* Tetapan membaca folder data melalui Kedai::dirSemasa(), yang biasanya
       ditentukan oleh Host. Dalam CLI tiada Host, jadi kita palsukannya. */
    $_SERVER['HTTP_HOST'] = $slug . '.' . Kedai::domainAsas();
    echo "Memasang ke kedai: $slug\n";
} else {
    echo "Memasang ke kedai utama (domain akar).\n";
}

$tetapan = new Tetapan();

/* ---------------------------- TERBITKAN MENU ----------------------------- */

try {
    $hasil = $tetapan->simpanMenu($mentah);
} catch (Throwable $e) {
    fwrite(STDERR, 'Gagal terbitkan menu: ' . $e->getMessage() . "\n");
    exit(1);
}

echo "\nMenu diterbitkan.\n";
echo '  Kedai    : ' . ($config['kedai']['nama'] ?? '—') . "\n";
echo '  Kategori : ' . count($config['kategori']) . "\n";
echo '  Item     : ' . $hasil['item'] . "\n";

/* ------------------------- AMARAN MEDAN KOSONG --------------------------- */

$wa = trim((string) ($config['kedai']['whatsapp'] ?? ''));
if ($wa === '') {
    echo "\nAMARAN: nombor WhatsApp kedai masih kosong.\n";
    echo "Butang \"Hantar Order ke WhatsApp\" tidak akan berfungsi sehingga ia diisi\n";
    echo "dalam Edit Menu -> tab Kedai.\n";
}


/* ------------------------- PEMBAYARAN TIRUAN ----------------------------- */

if ($demo) {
    $adaKredensial = $tetapan->pat() !== ''
        || $tetapan->secretKey() !== ''
        || $tetapan->portalKey() !== '';

    if ($adaKredensial) {
        echo "\nKredensial Bayarcash sebenar dikesan — pembayaran tiruan TIDAK dihidupkan.\n";
    } else {
        try {
            $tetapan->simpanKredensial([
                'persekitaran' => 'sandbox',
                'saluran'      => [1, 5],   // FPX + DuitNow QR
                'mod_demo'     => true,
            ]);
            echo "\nPembayaran tiruan dihidupkan (tiada duit bergerak).\n";
            echo "Ia mati sendiri sebaik sahaja kredensial Bayarcash sebenar diisi.\n";
        } catch (Throwable $e) {
            echo 'Amaran: gagal hidupkan pembayaran tiruan — ' . $e->getMessage() . "\n";
        }
    }
}

exit(0);
