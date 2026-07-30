<?php
/* ==========================================================================
   GET api/pulang.php   (return_url — pelayar pelanggan balik ke sini)
   --------------------------------------------------------------------------
   Halaman ini TIDAK dipercayai sebagai bukti pembayaran. Ia hanya:
     1. Sahkan checksum kalau ada (bonus)
     2. Kemas kini status dari data yang sah
     3. Redirect pelanggan balik ke laman kedai dengan ?order=...

   Status sebenar ditentukan oleh callback.php (server ke server) dan
   pemeriksaan langsung dalam status-order.php.
   ========================================================================== */

declare(strict_types=1);

require_once __DIR__ . '/lib/http.php';
require_once __DIR__ . '/lib/order.php';

$tetapan = new Tetapan();
$order   = new Order($tetapan);

$data   = $_GET ?: [];
$nombor = (string) ($data['order_number'] ?? '');

/* Kemas kini hanya bila checksum sah dan amaun sepadan */
if (Order::nomborSah($nombor) && $tetapan->secretKey() !== '') {
    $rekod = $order->ambil($nombor);

    if ($rekod !== null && $tetapan->klien()->sahkanCallback($data)) {
        $status = (int) ($data['status'] ?? Bayarcash::BARU);
        $amaunDibayar  = (float) ($data['amount'] ?? 0);
        $amaunDijangka = (float) ($rekod['jumlah'] ?? 0);

        if ($status === Bayarcash::BERJAYA && abs($amaunDibayar - $amaunDijangka) > 0.009) {
            $status = Bayarcash::GAGAL;
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
            'diterima'                  => gmdate('c'),
            'sumber'                    => 'return_url',
        ]);
    }
}

/* ------------------------- Balik ke laman kedai -------------------------- */

$url = $tetapan->urlAsas() . '/index.html';
if (Order::nomborSah($nombor)) {
    $url .= '?order=' . rawurlencode($nombor);
}

header('Location: ' . $url, true, 302);

/* Sandaran kalau header tidak berfungsi */
$selamat = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
echo '<!doctype html><meta charset="utf-8">'
   . '<meta http-equiv="refresh" content="0;url=' . $selamat . '">'
   . '<title>Kembali ke kedai…</title>'
   . '<p>Sedang kembali ke kedai… <a href="' . $selamat . '">Tekan di sini</a> kalau tidak berpindah.</p>';
