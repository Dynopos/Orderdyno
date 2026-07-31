<?php
/* ==========================================================================
   POST api/buat-bayaran.php
   --------------------------------------------------------------------------
   Dipanggil oleh cart pelanggan. Aliran:
     1. Sahkan cart terhadap menu di server dan kira jumlah SEBENAR
     2. Cipta Payment Intent di Bayarcash (dengan checksum)
     3. Simpan rekod order di server
     4. Pulangkan `url` untuk pelayar redirect ke halaman pembayaran

   Jumlah yang dihantar pelayar tidak pernah digunakan.
   ========================================================================== */

declare(strict_types=1);

require_once __DIR__ . '/lib/http.php';
require_once __DIR__ . '/lib/order.php';

wajib_kaedah('POST');

if (!had_kadar('bayar', 20, 600)) {
    json_silap('Terlalu banyak permintaan pembayaran. Cuba lagi sebentar.', 429);
}

$tetapan = new Tetapan();
$order   = new Order($tetapan);

if (!$tetapan->siap()) {
    json_silap('Pembayaran online belum disediakan oleh kedai ini.', 503);
}
if ($order->mataWang() !== 'MYR') {
    json_silap('Bayarcash memproses MYR sahaja.', 503);
}

$masuk = badan_json();

/* --------------------------- Maklumat pelanggan -------------------------- */

$p = is_array($masuk['pelanggan'] ?? null) ? $masuk['pelanggan'] : [];

$nama = trim((string) ($p['nama'] ?? ''));
if (mb_strlen($nama) < 2 || mb_strlen($nama) > 80) {
    json_silap('Nama tidak sah — sila isi nama penuh anda.');
}

$emel = trim((string) ($p['email'] ?? ''));
if (!filter_var($emel, FILTER_VALIDATE_EMAIL) || mb_strlen($emel) > 120) {
    json_silap('Alamat emel tidak sah. Bayarcash memerlukan emel untuk resit pembayaran.');
}

/* Nombor telefon — Bayarcash menerima nombor Malaysia sahaja */
$telefonMentah = preg_replace('/\D/', '', (string) ($p['telefon'] ?? '')) ?? '';
$telefon = null;
if ($telefonMentah !== '') {
    if (str_starts_with($telefonMentah, '0')) {
        $telefonMentah = '60' . substr($telefonMentah, 1);
    } elseif (!str_starts_with($telefonMentah, '60')) {
        $telefonMentah = '60' . $telefonMentah;
    }
    if (strlen($telefonMentah) >= 11 && strlen($telefonMentah) <= 13) {
        $telefon = $telefonMentah;
    }
}

$alamat = mb_substr(trim((string) ($p['alamat'] ?? '')), 0, 300);
$nota   = mb_substr(trim((string) ($p['nota'] ?? '')), 0, 300);

/* ------------------------------ Sahkan cart ------------------------------ */

$cart = is_array($masuk['cart'] ?? null) ? $masuk['cart'] : [];

try {
    $dikira = $order->sahkanCart($cart, (string) ($masuk['cara'] ?? 'pickup'));
} catch (InvalidArgumentException $e) {
    json_silap($e->getMessage(), 422);
}

if ($dikira['cara'] === 'delivery' && $alamat === '') {
    json_silap('Sila isi alamat penghantaran.');
}

/* ------------------------------- Saluran -------------------------------- */

$saluranAktif = array_column($tetapan->saluran(), 'kod');
$saluran = (int) ($masuk['saluran'] ?? 0);
if (!in_array($saluran, $saluranAktif, true)) {
    $saluran = (int) $saluranAktif[0];
}

/* --------------------------- Cipta payment intent ------------------------ */

$order->bersihLuput();

$nombor = Order::nomborBaru();
$amaun  = number_format($dikira['jumlah'], 2, '.', '');   // "24.50" — nilai yang sama ditandatangani

$payload = [
    'payment_channel' => $saluran,
    'portal_key'      => $tetapan->portalKey(),
    'order_number'    => $nombor,
    'amount'          => $amaun,
    'payer_name'      => $nama,
    'payer_email'     => $emel,
    'return_url'      => $tetapan->urlApi('pulang.php'),
    'callback_url'    => $tetapan->urlApi('callback.php'),
];

if ($telefon !== null) {
    $payload['payer_telephone_number'] = (int) $telefon;
}

$demo = $tetapan->modDemo();

if ($demo) {
    /* Laman demo: langkau Bayarcash sepenuhnya dan hantar pelanggan ke
       halaman pembayaran tiruan kita sendiri. Tiada duit, tiada API. */
    $intent    = [];
    $urlBayar  = $tetapan->urlApi('demo-bayar.php') . '?order=' . rawurlencode($nombor);
} else {
    $payload['checksum'] = $tetapan->klien()->checksumIntent($payload);

    try {
        $intent = $tetapan->klien()->buatPaymentIntent($payload);
    } catch (Throwable $e) {
        error_log('[OrderDyno] Payment intent gagal: ' . $e->getMessage());
        json_silap('Gagal mulakan pembayaran. Sila cuba lagi atau hantar order melalui WhatsApp.', 502);
    }

    $urlBayar = (string) ($intent['url'] ?? '');
    if ($urlBayar === '') {
        error_log('[OrderDyno] Bayarcash tidak pulangkan URL: ' . json_encode($intent));
        json_silap('Bayarcash tidak memulangkan pautan pembayaran. Sila cuba lagi.', 502);
    }
}

/* ------------------------------ Simpan order ----------------------------- */

try {
    $order->simpan([
        'order_number'      => $nombor,
        'dibuat'            => gmdate('c'),
        'dikemas'           => gmdate('c'),
        'status'            => Bayarcash::BARU,
        'status_label'      => Bayarcash::labelStatus(Bayarcash::BARU),
        'mata_wang'         => 'MYR',
        'subtotal'          => number_format($dikira['subtotal'], 2, '.', ''),
        'caj_hantar'        => number_format($dikira['caj'], 2, '.', ''),
        'jumlah'            => $amaun,
        'cara'              => $dikira['cara'],
        'saluran'           => $saluran,
        'saluran_nama'      => Tetapan::SALURAN[$saluran] ?? (string) $saluran,
        'baris'             => $dikira['baris'],
        'pelanggan'         => [
            'nama'    => $nama,
            'email'   => $emel,
            'telefon' => $telefon ?? '',
            'alamat'  => $alamat,
            'nota'    => $nota,
        ],
        'payment_intent_id' => (string) ($intent['id'] ?? ''),
        'url_bayar'         => $urlBayar,
        'transaksi'         => [],
        'dibayar_pada'      => null,
        // Ditanda pada rekod itu sendiri: demo-bayar.php enggan menyentuh
        // order yang tidak mempunyai penanda ini.
        'demo'              => $demo,
    ]);
} catch (Throwable $e) {
    error_log('[OrderDyno] Gagal simpan order: ' . $e->getMessage());
    json_silap('Gagal simpan order di server. Sila hubungi kedai.', 500);
}

json_keluar([
    'ok'           => true,
    'url'          => $urlBayar,
    'order_number' => $nombor,
    'jumlah'       => $amaun,
    'demo'         => $demo,
]);
