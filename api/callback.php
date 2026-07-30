<?php
/* ==========================================================================
   POST api/callback.php   (callback_url — server ke server)
   --------------------------------------------------------------------------
   Ini sumber kebenaran untuk status pembayaran. Aliran:
     1. Sahkan checksum dengan API secret key
     2. Pastikan amaun sepadan dengan order yang disimpan (elak bayar kurang)
     3. Kemas kini status order
     4. Balas HTTP 200 — kalau tidak, Bayarcash akan cuba semula 5 kali
        setiap 5 minit
   ========================================================================== */

declare(strict_types=1);

require_once __DIR__ . '/lib/http.php';
require_once __DIR__ . '/lib/order.php';

wajib_kaedah('POST');

$tetapan = new Tetapan();
$order   = new Order($tetapan);

/* Bayarcash boleh hantar sebagai JSON atau form-encoded — terima kedua-duanya */
$data = badan_json();
if (!$data) {
    $data = $_POST;
}

if (!$data) {
    error_log('[OrderDyno] Callback tanpa data');
    json_silap('Tiada data', 400);
}

if ($tetapan->secretKey() === '') {
    error_log('[OrderDyno] Callback diterima tetapi secret key belum dikonfigurasi');
    json_silap('Belum dikonfigurasi', 503);
}

/* ---------------------------- Sahkan checksum ---------------------------- */

if (!$tetapan->klien()->sahkanCallback($data)) {
    error_log('[OrderDyno] Checksum callback tidak sah untuk order ' . (string) ($data['order_number'] ?? '?'));
    json_silap('Checksum tidak sah', 400);
}

/* ------------------------------ Cari order ------------------------------- */

$nombor = (string) ($data['order_number'] ?? '');
if (!Order::nomborSah($nombor)) {
    // Bukan order dari sistem ini — akui terima supaya tiada cubaan semula
    json_keluar(['ok' => true, 'mesej' => 'Nombor order diabaikan']);
}

$rekod = $order->ambil($nombor);
if ($rekod === null) {
    error_log('[OrderDyno] Callback untuk order tidak dijumpai: ' . $nombor);
    json_keluar(['ok' => true, 'mesej' => 'Order tidak dijumpai']);
}

/* --------------------------- Sahkan amaun & status ----------------------- */

$status = (int) ($data['status'] ?? Bayarcash::BARU);

$amaunDibayar  = (float) ($data['amount'] ?? 0);
$amaunDijangka = (float) ($rekod['jumlah'] ?? 0);

/* Hanya tandakan berjaya bila amaun betul-betul sepadan */
if ($status === Bayarcash::BERJAYA && abs($amaunDibayar - $amaunDijangka) > 0.009) {
    error_log(sprintf(
        '[OrderDyno] Amaun tidak sepadan untuk %s — diterima %.2f, dijangka %.2f',
        $nombor,
        $amaunDibayar,
        $amaunDijangka
    ));
    $order->kemasStatus($nombor, Bayarcash::GAGAL, [
        'transaction_id'     => (string) ($data['transaction_id'] ?? ''),
        'status'             => Bayarcash::GAGAL,
        'status_description' => 'Amaun tidak sepadan',
        'amount'             => (string) ($data['amount'] ?? ''),
        'diterima'           => gmdate('c'),
    ]);
    json_keluar(['ok' => true, 'mesej' => 'Amaun tidak sepadan — order ditanda gagal']);
}

$order->kemasStatus($nombor, $status, [
    'transaction_id'            => (string) ($data['transaction_id'] ?? ''),
    'exchange_reference_number' => (string) ($data['exchange_reference_number'] ?? ''),
    'exchange_transaction_id'   => (string) ($data['exchange_transaction_id'] ?? ''),
    'payer_bank_name'           => (string) ($data['payer_bank_name'] ?? ''),
    'amount'                    => (string) ($data['amount'] ?? ''),
    'currency'                  => (string) ($data['currency'] ?? ''),
    'status'                    => $status,
    'status_description'        => (string) ($data['status_description'] ?? Bayarcash::labelStatus($status)),
    'datetime'                  => (string) ($data['datetime'] ?? ''),
    'diterima'                  => gmdate('c'),
]);

json_keluar(['ok' => true, 'mesej' => 'Diterima']);
