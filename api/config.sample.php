<?php
/* ==========================================================================
   OrderDyno — Kunci Pentadbir
   --------------------------------------------------------------------------
   Ini SATU-SATUNYA fail yang anda perlu sentuh secara manual.

   LANGKAH:
     1. Salin fail ini jadi `config.php` (dalam folder `api/` yang sama)
     2. Tukar `kunci_admin` kepada kata kunci rahsia pilihan anda
        (panjang, campuran huruf & nombor — ini pelindung tetapan Bayarcash anda)
     3. Simpan. Selesai.

   Selepas itu, SEMUA kredensial Bayarcash diisi dari pelayar:
     buka website → ⚙ Edit Menu → tab "Bayaran" → masukkan kunci admin ini
     → isi Personal Access Token, API Secret Key & Portal Key → Simpan.

   Kredensial disimpan di server dalam `api/data/bayarcash.json` dan tidak
   sekali-kali dihantar semula ke pelayar.

   Belum ada akaun Bayarcash? Daftar di https://bayarcash.com
   ========================================================================== */

return [

    /* ---------------------------------------------------------------------
       WAJIB — kata kunci untuk buka tab "Bayaran" dan senarai order.
       Jangan biar kosong dan jangan guna kata kunci mudah.
       --------------------------------------------------------------------- */
    'kunci_admin' => 'TUKAR-KUNCI-INI-kepada-sesuatu-yang-panjang-dan-rahsia',

    /* =====================================================================
       Semua yang di bawah ini PILIHAN.
       Biar sahaja seperti asal kalau anda isi tetapan dari tab "Bayaran".
       ===================================================================== */

    /* ---------------------------------------------------------------------
       Kalau anda lebih suka letak kredensial di sini (atau sebagai
       environment variable) berbanding melalui pelayar, isi di bawah.
       Environment variable mengatasi fail ini; fail ini mengatasi tetapan
       yang disimpan dari pelayar.
         BAYARCASH_PAT · BAYARCASH_SECRET_KEY · BAYARCASH_PORTAL_KEY
         BAYARCASH_ENV  ('sandbox' atau 'production')
       --------------------------------------------------------------------- */
    'pat'          => '',
    'secret_key'   => '',
    'portal_key'   => '',
    'persekitaran' => '',   // 'sandbox' | 'production'

    /* ---------------------------------------------------------------------
       URL asas website anda tanpa '/' di hujung.
       Biar kosong untuk auto-detect. Isi manual hanya kalau website di
       belakang proxy/CDN dan return URL jadi salah.
       Contoh: 'https://kedaisaya.com'
       --------------------------------------------------------------------- */
    'url_asas' => '',

    /* ---------------------------------------------------------------------
       MENJUAL KEPADA RAMAI PELANGGAN (pilihan)

       Isi dua nilai ini kalau anda mahu satu pemasangan menghidangkan ramai
       kedai, setiap satu pada subdomainnya sendiri:

           orderdyno.my              → kedai utama (demo / laman jualan anda)
           nasilemakali.orderdyno.my → kedai pelanggan

       'domain_asas'     — domain induk anda, tanpa 'www'
       'kunci_pentadbir' — kunci untuk membuka api/pentadbir.php, tempat anda
                           mencipta kedai dan menjana kunci untuk pemiliknya

       Biar KOSONG untuk pemasangan satu kedai. Bila kunci_pentadbir kosong,
       api/pentadbir.php memulangkan 404 dan tidak mendedahkan apa-apa.

       Jana kunci pentadbir yang kuat:
           php -r "echo bin2hex(random_bytes(24));"
       --------------------------------------------------------------------- */
    'domain_asas'     => '',
    'kunci_pentadbir' => '',

    /* ---------------------------------------------------------------------
       Hidupkan HANYA kalau laman berada di belakang Cloudflare dengan proxy
       dihidupkan (awan oren).

       Tanpa ini, setiap pelawat kelihatan datang dari IP Cloudflare yang
       sama, jadi mereka berkongsi satu baldi had kadar — seorang penyalahguna
       boleh mengunci semua pelanggan sah.

       Jangan hidupkan kalau laman boleh dicapai terus melalui IP server,
       kerana header CF-Connecting-IP boleh dipalsukan dan had kadar akan
       dipintas sepenuhnya.
       --------------------------------------------------------------------- */
    'di_belakang_cloudflare' => false,

    /* ---------------------------------------------------------------------
       Had keselamatan
       --------------------------------------------------------------------- */
    'maks_kuantiti' => 99,      // kuantiti maksimum satu baris order
    'maks_jumlah'   => 10000,   // jumlah maksimum satu order
    'minit_luput'   => 60,      // order tidak berbayar dianggap luput
];
