<?php
/* ==========================================================================
   GET api/ikon.php?s=192|512    (atau ?jenis=svg)
   --------------------------------------------------------------------------
   Ikon aplikasi bagi kedai pada subdomain ini, untuk manifest dan skrin
   utama telefon. Lihat api/lib/ikon.php untuk susunan sumbernya.

   Dihidangkan sebagai endpoint, bukan fail statik, kerana setiap kedai pada
   pemasangan ini mempunyai ikonnya sendiri.
   ========================================================================== */

declare(strict_types=1);

require_once __DIR__ . '/lib/http.php';
require_once __DIR__ . '/lib/ikon.php';

wajib_kaedah('GET');

if (!Kedai::wujud()) {
    json_keluar(['ok' => false, 'kedaiTiada' => true], 404);
}

/* Hanya saiz ikon yang diisytiharkan dalam manifest diterima — tanpa had,
   ?s=20000 menjadi cara mudah membakar CPU server. */
$saiz = (int) ($_GET['s'] ?? 512);
if (!in_array($saiz, [192, 512], true)) {
    $saiz = 512;
}

$hantar = static function (string $bait, string $jenis): never {
    $etag = '"' . substr(hash('sha256', $bait), 0, 32) . '"';

    if (($_SERVER['HTTP_IF_NONE_MATCH'] ?? '') === $etag) {
        header('ETag: ' . $etag);
        http_response_code(304);
        exit;
    }

    header('Content-Type: ' . $jenis);
    header('Content-Length: ' . strlen($bait));
    header('ETag: ' . $etag);
    header('Cache-Control: public, max-age=86400');
    header('X-Content-Type-Options: nosniff');
    echo $bait;
    exit;
};

if (($_GET['jenis'] ?? '') === 'svg') {
    $hantar(Ikon::lukisSvg(), 'image/svg+xml; charset=utf-8');
}

/* --------------------- 1. Ikon yang dijana pelayar ----------------------- */

$simpanan = Ikon::simpanan();

if ($simpanan !== null) {
    $ukuran = @getimagesizefromstring($simpanan);
    $lebar = is_array($ukuran) ? (int) ($ukuran[0] ?? 0) : 0;

    /* Kecilkan hanya bila perlu dan bila GD ada. Kalau tidak, hantar seadanya
       — pelayar mengecilkan sendiri, dan ikon 512px kekal betul. */
    if ($lebar === $saiz || !Ikon::adaGd() || $lebar < 1) {
        $hantar($simpanan, 'image/png');
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
                $hantar($png, 'image/png');
            }
        } else {
            imagedestroy($asal);
        }
    }

    $hantar($simpanan, 'image/png');
}

/* ---------------------- 2. Jubin warna tema (GD) ------------------------- */

$png = Ikon::lukisPng($saiz);
if ($png !== null) {
    $hantar($png, 'image/png');
}

/* ------------------------- 3. Sandaran SVG ------------------------------- */

$hantar(Ikon::lukisSvg(), 'image/svg+xml; charset=utf-8');
