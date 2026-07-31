<?php
/* ==========================================================================
   GET api/status.php
   --------------------------------------------------------------------------
   Dipanggil oleh laman untuk tahu sama ada pembayaran online tersedia.
   Tidak memerlukan kunci admin, jadi ia HANYA memulangkan maklumat awam:
   sama ada sistem sedia, saluran yang aktif, dan mata wang.

   Dengan kunci admin (?key=...), ia juga memulangkan keadaan tetapan
   (bertopeng) untuk dipaparkan dalam tab "Bayaran".
   ========================================================================== */

declare(strict_types=1);

require_once __DIR__ . '/lib/http.php';
require_once __DIR__ . '/lib/order.php';

wajib_kaedah('GET');

$tetapan = new Tetapan();
$order   = new Order($tetapan);

$siap = $tetapan->siap();

$jawapan = [
    'ok'       => true,
    'siap'     => $siap,
    'saluran'  => $siap ? $tetapan->saluran() : [],
    'mataWang' => $siap ? $order->mataWang() : null,
    'sandbox'  => $tetapan->persekitaran() === 'sandbox',
    // Beritahu panel bahawa backend memang ada, walaupun belum dikonfigurasi
    'backend'  => true,
    'adaConfig'     => $tetapan->adaConfig(),
    'adaKunciAdmin' => $tetapan->adaKunciAdmin(),
];

// Bayarcash hanya memproses MYR
if ($siap && $order->mataWang() !== 'MYR') {
    $jawapan['siap'] = false;
    $jawapan['mesej'] = 'Bayarcash memproses MYR sahaja. Tukar mata wang kedai ke RM untuk guna pembayaran online.';
}

/* ------------------- Maklumat tambahan untuk pemilik --------------------- */
$kunci = isset($_GET['key']) ? (string) $_GET['key'] : null;
if ($kunci !== null && $tetapan->kunciSah($kunci)) {
    $menu = $tetapan->menuTersimpan();
    $jawapan['admin'] = [
        'persekitaran' => $tetapan->persekitaran(),
        'halangan'     => $tetapan->halangan(),
        'sumber'       => $tetapan->sumber(),
        'terisi'       => [
            'pat'        => $tetapan->pat() !== '' ? Tetapan::topeng($tetapan->pat()) : '',
            'secret_key' => $tetapan->secretKey() !== '' ? Tetapan::topeng($tetapan->secretKey()) : '',
            'portal_key' => $tetapan->portalKey() !== '' ? Tetapan::topeng($tetapan->portalKey()) : '',
        ],
        'saluranAktif' => array_column($tetapan->saluran(), 'kod'),
        'saluranAda'   => array_map(
            static fn ($kod, $nama) => ['kod' => $kod, 'nama' => $nama],
            array_keys(Tetapan::SALURAN),
            array_values(Tetapan::SALURAN)
        ),
        'menu' => $menu === null ? null : [
            'hash'    => $menu['__hash'],
            'dikemas' => $menu['__dikemas'],
            'item'    => count($menu['menu'] ?? []),
        ],
        'urlCallback' => $tetapan->urlApi('callback.php'),
        'urlReturn'   => $tetapan->urlApi('pulang.php'),
        'urlAsasDitetapkan' => $tetapan->urlAsasDitetapkan(),
    ];
} elseif ($kunci !== null) {
    // Jangan bocorkan sama ada kunci hampir betul
    $jawapan['adminSilap'] = true;
}

json_keluar($jawapan);
