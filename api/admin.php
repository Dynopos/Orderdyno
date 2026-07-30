<?php
/* ==========================================================================
   POST api/admin.php
   --------------------------------------------------------------------------
   Endpoint untuk pemilik kedai (tab "Bayaran" dalam panel Edit Menu).
   Setiap permintaan mesti sertakan `kunci` — kunci admin dari api/config.php.

   Aksi:
     uji       — uji kredensial dengan panggil senarai bank Bayarcash
     simpan    — simpan Personal Access Token / Secret Key / Portal Key / saluran
     menu      — segerakkan snapshot menu ke server (untuk pengesahan harga)
     lupakan   — buang kredensial yang disimpan dari panel

   Kredensial tidak pernah dipulangkan semula ke pelayar — hanya bentuk
   bertopeng (•••• 4 aksara terakhir) untuk pengesahan visual.
   ========================================================================== */

declare(strict_types=1);

require_once __DIR__ . '/lib/http.php';
require_once __DIR__ . '/lib/order.php';

wajib_kaedah('POST');

$masuk   = badan_json();
$tetapan = new Tetapan();

/* ---------------------------- Sahkan kunci ------------------------------- */

if (!$tetapan->adaConfig()) {
    json_silap('Fail api/config.php belum dibuat. Salin api/config.sample.php jadi api/config.php dan tetapkan kunci_admin.', 409);
}
if (!$tetapan->adaKunciAdmin()) {
    json_silap('kunci_admin dalam api/config.php masih nilai asal. Tukar kepada kata kunci rahsia anda dahulu.', 409);
}

// Hadkan tekaan kunci
if (!had_kadar('admin', 15, 600)) {
    json_silap('Terlalu banyak cubaan. Cuba lagi dalam 10 minit.', 429);
}

if (!$tetapan->kunciSah((string) ($masuk['kunci'] ?? ''))) {
    // Perlahankan sedikit untuk kurangkan kelajuan tekaan
    usleep(300000);
    json_silap('Kunci admin salah', 401);
}

/* ------------------------------- Aksi ----------------------------------- */

$aksi = (string) ($masuk['aksi'] ?? '');

switch ($aksi) {

    /* ---------------------------------------------------------------- uji */
    case 'uji': {
        // Uji dengan nilai yang dihantar kalau ada, kalau tidak nilai tersimpan
        $pat = trim((string) ($masuk['pat'] ?? '')) ?: $tetapan->pat();
        $env = ($masuk['persekitaran'] ?? $tetapan->persekitaran()) === 'production' ? 'production' : 'sandbox';

        if ($pat === '') {
            json_silap('Personal Access Token belum diisi');
        }

        try {
            $klien = new Bayarcash($pat, $tetapan->secretKey(), $env);
            $bank  = $klien->bankFpx();
            $bil   = is_array($bank['data'] ?? null) ? count($bank['data']) : (is_array($bank) ? count($bank) : 0);
            json_keluar([
                'ok'           => true,
                'mesej'        => 'Sambungan berjaya — Bayarcash menerima token anda.',
                'persekitaran' => $env,
                'bilanganBank' => $bil,
            ]);
        } catch (Throwable $e) {
            json_keluar([
                'ok'    => false,
                'mesej' => 'Sambungan gagal: ' . $e->getMessage(),
            ], 502);
        }
    }

    /* ------------------------------------------------------------- simpan */
    case 'simpan': {
        try {
            $tetapan->simpanKredensial([
                'pat'          => $masuk['pat'] ?? '',
                'secret_key'   => $masuk['secret_key'] ?? '',
                'portal_key'   => $masuk['portal_key'] ?? '',
                'persekitaran' => $masuk['persekitaran'] ?? null,
                'saluran'      => $masuk['saluran'] ?? null,
            ]);
        } catch (Throwable $e) {
            json_silap('Gagal simpan: ' . $e->getMessage(), 500);
        }

        // Muat semula supaya jawapan menunjukkan keadaan terkini
        $segar = new Tetapan();
        json_keluar([
            'ok'    => true,
            'mesej' => 'Tetapan Bayarcash disimpan',
            'siap'  => $segar->siap(),
            'halangan' => $segar->halangan(),
            'terisi'   => [
                'pat'        => $segar->pat() !== '' ? Tetapan::topeng($segar->pat()) : '',
                'secret_key' => $segar->secretKey() !== '' ? Tetapan::topeng($segar->secretKey()) : '',
                'portal_key' => $segar->portalKey() !== '' ? Tetapan::topeng($segar->portalKey()) : '',
            ],
        ]);
    }

    /* --------------------------------------------------------------- menu */
    case 'menu': {
        $mentah = $masuk['menu'] ?? null;
        if (!is_string($mentah) || $mentah === '') {
            json_silap('Data menu tidak dihantar');
        }
        if (strlen($mentah) > 4 * 1024 * 1024) {
            json_silap('Menu terlalu besar. Guna URL gambar berbanding upload untuk item yang banyak.', 413);
        }

        try {
            $hasil = $tetapan->simpanMenu($mentah);
        } catch (Throwable $e) {
            json_silap('Gagal segerakkan menu: ' . $e->getMessage(), 400);
        }

        $segar = new Tetapan();
        json_keluar([
            'ok'    => true,
            'mesej' => 'Menu disegerakkan ke server (' . $hasil['item'] . ' item)',
            'menu'  => $hasil,
            'siap'  => $segar->siap(),
            'halangan' => $segar->halangan(),
        ]);
    }

    /* ------------------------------------------------------------ lupakan */
    case 'lupakan': {
        $tetapan->lupakanKredensial();
        json_keluar(['ok' => true, 'mesej' => 'Kredensial yang disimpan dari panel telah dibuang']);
    }

    default:
        json_silap('Aksi tidak dikenali');
}
