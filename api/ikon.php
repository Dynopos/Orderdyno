<?php
/* ==========================================================================
   GET api/ikon.php?s=192|512
   --------------------------------------------------------------------------
   Ikon aplikasi bagi kedai pada subdomain ini, untuk manifest dan skrin
   utama telefon.

   Dihidangkan sebagai endpoint, bukan fail statik, kerana setiap kedai pada
   pemasangan ini mempunyai ikonnya sendiri. Lihat api/lib/ikon.php.
   ========================================================================== */

declare(strict_types=1);

require_once __DIR__ . '/lib/http.php';
require_once __DIR__ . '/lib/ikon.php';

wajib_kaedah('GET');

if (!Kedai::wujud()) {
    json_keluar(['ok' => false, 'kedaiTiada' => true], 404);
}

/* Hanya saiz yang diisytiharkan dalam manifest diterima — tanpa had,
   ?s=20000 menjadi cara mudah membakar CPU server. */
$saiz = (int) ($_GET['s'] ?? 512);
if (!in_array($saiz, Ikon::SAIZ, true)) {
    $saiz = 512;
}

$hantar = static function (string $bait): never {
    $etag = '"' . substr(hash('sha256', $bait), 0, 32) . '"';

    if (($_SERVER['HTTP_IF_NONE_MATCH'] ?? '') === $etag) {
        header('ETag: ' . $etag);
        http_response_code(304);
        exit;
    }

    header('Content-Type: image/png');
    header('Content-Length: ' . strlen($bait));
    header('ETag: ' . $etag);
    header('Cache-Control: public, max-age=86400');
    header('X-Content-Type-Options: nosniff');
    echo $bait;
    exit;
};

/* --------------------- 1. Ikon yang dijana pelayar ----------------------- */

$simpanan = Ikon::simpanan();

if ($simpanan !== null) {
    $ukuran = @getimagesizefromstring($simpanan);
    $lebar = is_array($ukuran) ? (int) ($ukuran[0] ?? 0) : 0;

    /* Kecilkan hanya bila perlu dan bila GD ada. Kalau tidak, hantar seadanya
       — pelayar mengecilkan sendiri, dan ikon 512px kekal betul. */
    if ($lebar === $saiz || !Ikon::adaGd() || $lebar < 1) {
        $hantar($simpanan);
    }

    $asal = @imagecreatefromstring($simpanan);
    if ($asal !== false) {
        $kecil = imagecreatetruecolor($saiz, $saiz);
        if ($kecil !== false) {
            imagealphablending($kecil, false);
            imagesavealpha($kecil, true);
            imagecopyresampled(
                $kecil, $asal,
                0, 0, 0, 0,
                $saiz, $saiz,
                imagesx($asal), imagesy($asal)
            );
            ob_start();
            imagepng($kecil, null, 9);
            $png = (string) ob_get_clean();
            imagedestroy($kecil);
            imagedestroy($asal);
            if ($png !== '') {
                $hantar($png);
            }
        } else {
            imagedestroy($asal);
        }
    }

    $hantar($simpanan);
}

/* ------------------- 2. Lambang OrderDyno yang dibungkus ----------------- */

$asal = Ikon::failAsal($saiz);
$bait = is_file($asal) ? @file_get_contents($asal) : false;

if ($bait === false || $bait === '') {
    json_silap('Ikon tidak dijumpai.', 404);
}

$hantar($bait);
