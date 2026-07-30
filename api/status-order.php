<?php
/* ==========================================================================
   GET api/status-order.php?order=OD-...
   --------------------------------------------------------------------------
   Dipanggil oleh laman untuk papar keputusan pembayaran.

   Kalau status masih Baru/Menunggu, kita tanya Bayarcash secara langsung —
   ini menyelamatkan keadaan di mana callback lambat atau tersekat.

   Hanya maklumat resit dipulangkan. Emel, telefon dan alamat pelanggan
   TIDAK dipulangkan.
   ========================================================================== */

declare(strict_types=1);

require_once __DIR__ . '/lib/http.php';
require_once __DIR__ . '/lib/order.php';

wajib_kaedah('GET');

if (!had_kadar('status', 120, 600)) {
    json_silap('Terlalu banyak permintaan. Cuba lagi sebentar.', 429);
}

$tetapan = new Tetapan();
$order   = new Order($tetapan);

$nombor = (string) ($_GET['order'] ?? '');
if (!Order::nomborSah($nombor)) {
    json_silap('Nombor order tidak sah', 400);
}

$rekod = $order->ambil($nombor);
if ($rekod === null) {
    json_silap('Order tidak dijumpai', 404);
}

/* ------------------ Semak terus dengan Bayarcash bila perlu --------------- */

$status = (int) ($rekod['status'] ?? Bayarcash::BARU);
$belumPasti = in_array($status, [Bayarcash::BARU, Bayarcash::MENUNGGU], true);
$intentId = (string) ($rekod['payment_intent_id'] ?? '');

if ($belumPasti && $intentId !== '' && $tetapan->siap()) {
    try {
        $intent = $tetapan->klien()->paymentIntent($intentId);

        /* Cari percubaan terbaru yang berjaya, kalau tidak ambil yang pertama */
        $attempts = is_array($intent['attempts'] ?? null) ? $intent['attempts'] : [];
        $pilih = null;
        foreach ($attempts as $a) {
            if ((int) ($a['status'] ?? 0) === Bayarcash::BERJAYA) {
                $pilih = $a;
                break;
            }
        }
        if ($pilih === null && $attempts) {
            $pilih = $attempts[0];
        }

        if ($pilih !== null) {
            $statusBaru   = (int) ($pilih['status'] ?? Bayarcash::BARU);
            $amaunDibayar = (float) ($pilih['amount'] ?? 0);
            $amaunDijangka = (float) ($rekod['jumlah'] ?? 0);

            if ($statusBaru === Bayarcash::BERJAYA && abs($amaunDibayar - $amaunDijangka) > 0.009) {
                $statusBaru = Bayarcash::GAGAL;
            }

            $dikemas = $order->kemasStatus($nombor, $statusBaru, [
                'transaction_id'            => (string) ($pilih['transaction_id'] ?? ''),
                'exchange_reference_number' => (string) ($pilih['exchange_reference_number'] ?? ''),
                'exchange_transaction_id'   => (string) ($pilih['exchange_transaction_id'] ?? ''),
                'payer_bank_name'           => (string) ($pilih['payer_bank_name'] ?? ''),
                'amount'                    => (string) ($pilih['amount'] ?? ''),
                'currency'                  => (string) ($pilih['currency'] ?? ''),
                'status'                    => $statusBaru,
                'status_description'        => (string) ($pilih['status_description'] ?? Bayarcash::labelStatus($statusBaru)),
                'diterima'                  => gmdate('c'),
                'sumber'                    => 'semakan_langsung',
            ]);
            if ($dikemas !== null) {
                $rekod  = $dikemas;
                $status = (int) $rekod['status'];
            }
        }
    } catch (Throwable $e) {
        // Jangan gagalkan permintaan — pulangkan status tersimpan sahaja
        error_log('[OrderDyno] Semakan status gagal untuk ' . $nombor . ': ' . $e->getMessage());
    }
}

/* ------------------------------- Jawapan --------------------------------- */

$transaksi = $rekod['transaksi'] ?? [];
$terakhir  = is_array($transaksi) && $transaksi ? $transaksi[count($transaksi) - 1] : [];

json_keluar([
    'ok'           => true,
    'order_number' => $rekod['order_number'],
    'status'       => $status,
    'status_label' => Bayarcash::labelStatus($status),
    'dibayar'      => $status === Bayarcash::BERJAYA,
    'jumlah'       => $rekod['jumlah'],
    'mata_wang'    => $rekod['mata_wang'] ?? 'MYR',
    'cara'         => $rekod['cara'] ?? 'pickup',
    'saluran_nama' => $rekod['saluran_nama'] ?? '',
    'dibuat'       => $rekod['dibuat'] ?? '',
    'dibayar_pada' => $rekod['dibayar_pada'] ?? null,
    'nama'         => $rekod['pelanggan']['nama'] ?? '',
    'baris'        => array_map(static fn ($b) => [
        'nama'     => $b['nama'],
        'variasi'  => $b['variasi'],
        'tambahan' => array_column($b['tambahan'] ?? [], 'nama'),
        'nota'     => $b['nota'] ?? '',
        'kuantiti' => $b['kuantiti'],
        'jumlah'   => number_format((float) $b['jumlah'], 2, '.', ''),
    ], $rekod['baris'] ?? []),
    'rujukan' => [
        'transaction_id'            => $terakhir['transaction_id'] ?? '',
        'exchange_reference_number' => $terakhir['exchange_reference_number'] ?? '',
        'bank'                      => $terakhir['payer_bank_name'] ?? '',
    ],
]);
