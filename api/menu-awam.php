<?php
/* ==========================================================================
   GET api/menu-awam.php
   --------------------------------------------------------------------------
   Menu yang diterbitkan, untuk dibaca oleh mana-mana pelawat.

   Ini yang membolehkan "Terbitkan menu" menghantar menu kepada SEMUA
   pelanggan. Tanpa endpoint ini, setiap pelawat hanya nampak menu lalai
   dalam config.js kerana menu yang diedit hanya wujud dalam localStorage
   pemilik kedai.

   Tiada kredensial dalam jawapan ini — hanya maklumat yang memang
   dipaparkan pada laman: nama kedai, waktu, alamat, kategori, menu, harga,
   tema. Fail sumbernya (api/data/menu.php) kekal tidak boleh dibaca terus.
   ========================================================================== */

declare(strict_types=1);

require_once __DIR__ . '/lib/http.php';

/* Kedai tidak wujud pada subdomain ini */
if (!Kedai::wujud()) {
    json_keluar(['ok' => false, 'kedaiTiada' => true], 404);
}

wajib_kaedah('GET');

$tetapan = new Tetapan();
$fail = SimpananSelamat::cari(Tetapan::failMenu());

if ($fail === null) {
    json_keluar(['ok' => false, 'mesej' => 'Tiada menu diterbitkan'], 404);
}

$mentah = SimpananSelamat::bacaMentah($fail);
if ($mentah === null) {
    json_keluar(['ok' => false, 'mesej' => 'Menu tidak boleh dibaca'], 500);
}

$data = json_decode($mentah, true);
if (!is_array($data) || empty($data['menu'])) {
    json_keluar(['ok' => false, 'mesej' => 'Menu tidak sah'], 500);
}

/*
 * Buang medan yang hanya untuk pemilik kedai. Sekarang tiada, tetapi
 * senarai ini menghalang kebocoran kalau medan sensitif ditambah kemudian.
 */
unset($data['kunci_admin'], $data['pat'], $data['secret_key'], $data['portal_key']);

$hash = hash('sha256', $mentah);

/* ETag supaya pelawat berulang tidak muat turun menu yang sama berkali-kali */
$etag = '"' . substr($hash, 0, 32) . '"';
if (($_SERVER['HTTP_IF_NONE_MATCH'] ?? '') === $etag) {
    header('ETag: ' . $etag);
    header('Cache-Control: no-cache, must-revalidate');
    http_response_code(304);
    exit;
}

header('ETag: ' . $etag);
/* no-cache = sentiasa semak dengan server, tetapi guna semula badan bila 304.
   Kemas kini menu muncul serta-merta, tanpa muat turun berulang. */
header('Cache-Control: no-cache, must-revalidate');
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

echo json_encode([
    'ok'      => true,
    'hash'    => $hash,
    'dikemas' => gmdate('c', (int) filemtime($fail)),
    'config'  => $data,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
