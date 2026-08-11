<?php
/* ==========================================================================
   GET api/ikon.php?s=192|512
   --------------------------------------------------------------------------
   Ikon aplikasi untuk manifest dan skrin utama telefon — lambang OrderDyno,
   sama untuk setiap kedai. Lihat api/lib/ikon.php.

   Ia kekal sebagai endpoint dan bukan pautan terus kepada fail supaya
   manifest boleh merujuk satu laluan tetap: kalau ikon ditukar kemudian,
   hanya satu tempat perlu berubah.
   ========================================================================== */

declare(strict_types=1);

require_once __DIR__ . '/lib/http.php';
require_once __DIR__ . '/lib/ikon.php';

wajib_kaedah('GET');

if (!Kedai::wujud()) {
    json_keluar(['ok' => false, 'kedaiTiada' => true], 404);
}

/* Hanya saiz yang diisytiharkan dalam manifest diterima */
$saiz = (int) ($_GET['s'] ?? 512);
if (!in_array($saiz, Ikon::SAIZ, true)) {
    $saiz = 512;
}

$fail = Ikon::fail($saiz);
$bait = is_file($fail) ? @file_get_contents($fail) : false;

if ($bait === false || $bait === '') {
    json_silap('Ikon tidak dijumpai.', 404);
}

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
