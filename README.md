# 🛒 OrderDyno — Template Menu Online + Order WhatsApp

Template website ordering untuk kedai kecil, kafe, gerai dan penjaja.
Pelanggan pilih menu → masuk cart → **order terus masuk ke WhatsApp anda**.

**Yang paling penting: pemilik kedai boleh isi menu sendiri tanpa sentuh code.**
Tekan butang **⚙ Edit Menu** di penjuru atas, isi semuanya dari borang.

Tiada database, tiada langganan bulanan — hanya HTML, CSS dan JavaScript biasa.
Boleh host percuma di GitHub Pages, Netlify, Vercel, Cloudflare Pages atau
mana-mana hosting statik.

Nak pelanggan bayar dahulu? Ada integrasi **Bayarcash** (FPX, DuitNow) yang
pilihan — pemilik kedai masukkan kredensial sendiri dari panel. Ia memerlukan
hosting PHP; tanpanya semua yang lain tetap berfungsi.

![Hero](docs/preview-hero.png)
![Menu](docs/preview-menu.png)
![Panel Edit Menu](docs/preview-editor.png)
![Cart dengan pembayaran online](docs/preview-bayar-cart.png)
![Tetapan Bayarcash](docs/preview-bayar-tetapan.png)
![Resit selepas bayar](docs/preview-bayar-resit.png)

---

## Ciri-ciri

**Untuk pelanggan**

- 🌌 Reka bentuk gelap "aurora neon" dengan gradien beranimasi
- 🛒 Butang **Semak Order** melekat di atas skrin, sentiasa nampak
- ➕ Saiz/variasi (harga papar automatik sebagai julat, contoh `RM 8.00 – RM 12.00`)
- 🧂 Add-on, kuantiti dan nota khas per item
- 🚗 Pilihan **Ambil Sendiri** atau **Penghantaran** (caj + order minimum)
- 💾 Cart tak hilang bila refresh
- 📱 Mobile-first — majoriti pelanggan order dari telefon
- 💬 Order dihantar sebagai mesej WhatsApp yang tersusun rapi

**Untuk pemilik kedai**

- ⚙️ Panel **Edit Menu** terbina dalam — nama kedai, logo, waktu, alamat, menu, harga
- 🖼️ Upload gambar item terus dari telefon (auto-kecilkan supaya tak berat)
- 🎨 6 tema warna siap pakai + pemilih warna sendiri
- 🏷️ Tanda item **Popular** atau **Habis** dengan satu klik
- 💾 Auto-simpan dalam pelayar + **Export/Import fail JSON** sebagai backup
- 🔗 **Kongsi menu sebagai satu link** — tanpa server, tanpa hosting menu
- 💳 **Pembayaran online Bayarcash** (pilihan) — FPX, DuitNow, DuitNow QR, BNPL —
  kredensial diisi sendiri dari panel, tiada coding

---

## Mula guna (3 minit)

1. Buka `index.html` dalam pelayar (atau upload folder ini ke hosting anda).
2. Tekan **⚙ Edit Menu** di penjuru atas kanan.
3. Tab **Kedai** — isi nama kedai, tagline, **nombor WhatsApp**, waktu, alamat.
4. Tab **Menu** — tambah kategori, tambah item, letak harga & gambar.
5. Tab **Tema** — pilih warna yang padan dengan kedai anda.
6. Tekan **Selesai**. Siap.

Semua perubahan disimpan automatik. Tiada butang "Save" untuk dilupakan.

> **Penting:** nombor WhatsApp perlu format antarabangsa tanpa `+`, ruang atau `-`.
> Contoh Malaysia: `60123456789`.

---

## Di mana menu disimpan?

Bila anda guna panel Edit Menu, menu disimpan dalam **`localStorage` pelayar
anda sahaja**. Ini bermakna:

- ✅ Cepat, peribadi, tak perlu server
- ⚠️ Pelanggan yang buka website anda **tak akan nampak** menu itu — mereka
  nampak menu lalai dalam kod
- ⚠️ Kalau anda clear browser data, menu itu hilang

Ada **tiga cara** untuk edarkan menu anda kepada pelanggan:

### Cara 1 — Link kongsi (paling cepat)

Tab **Kongsi** → **📋 Salin link**. Link itu mengandungi seluruh menu
anda. Hantar dalam bio Instagram, status WhatsApp, atau jadikan QR code.
Sesiapa yang buka akan nampak menu anda.

Sesuai untuk: menu ringkas tanpa gambar upload. Kalau anda upload banyak gambar,
link jadi terlalu panjang — guna Cara 2 atau 3.

### Cara 2 — Jadikan kekal dalam kod (disyorkan untuk kedai serius)

1. Tab **Kongsi** → **⬇ Export fail JSON**
2. Buka fail `assets/js/config.js`
3. Ganti objek `TEMPLATE` dengan isi fail JSON yang anda export
4. Upload semula folder ke hosting

Sekarang setiap pelawat nampak menu anda terus, tanpa link panjang.

Untuk sembunyikan butang Edit Menu dari pelanggan, set dalam `config.js`:

```js
sembunyikanEdit: true,
```

Anda masih boleh buka panel bila-bila masa dengan tambah `#edit` di hujung URL —
contoh `https://kedaisaya.com/#edit`.

### Cara 3 — Guna sebagai menu peribadi

Tak upload mana-mana. Buka `index.html` pada tablet di kaunter, biar pelanggan
pilih sendiri, dan order masuk ke WhatsApp anda.

---

## 💳 Pembayaran online dengan Bayarcash (pilihan)

Tanpa langkah ini, kedai tetap berfungsi penuh — order pergi ke WhatsApp dan
pelanggan bayar secara COD, transfer atau QR. Bahagian ini untuk anda yang nak
pelanggan **bayar dahulu** melalui FPX / DuitNow.

### Apa yang diperlukan

**Hosting yang menjalankan PHP 8.0 atau lebih baharu** (cPanel, Plesk, atau
mana-mana shared hosting biasa). Kredensial dan pengiraan checksum wajib berada
di server — kalau ia diletak dalam JavaScript, sesiapa boleh mencurinya dan
mengubah harga. Sebab itu folder `api/` diperlukan.

> **GitHub Pages, Netlify dan Vercel (static) tidak menjalankan PHP.**
> Kalau anda di sana, laman kekal berfungsi tetapi butang bayar tidak muncul.

### Langkah pemasangan

**1. Daftar akaun Bayarcash** di [bayarcash.com](https://bayarcash.com).
Ambil tiga nilai ini dari console mereka:

| Nilai | Lokasi dalam console |
|---|---|
| Personal Access Token | Developers → Personal Access Token |
| API Secret Key | halaman Profile |
| Portal Key | menu Portals |

Guna **Sandbox** dahulu (`console.bayarcash-sandbox.com`) untuk menguji tanpa
duit sebenar. Kredensial sandbox dan production adalah berbeza.

**2. Upload folder `api/`** bersama laman anda ke hosting PHP.

**3. Tetapkan kunci admin** — ini satu-satunya langkah manual:

```
Salin  api/config.sample.php  →  api/config.php
Dalam fail itu, tukar 'kunci_admin' kepada kata kunci rahsia anda sendiri.
```

Kunci ini yang melindungi tetapan pembayaran anda daripada dicapai orang lain.
Tiada nilai lain perlu diisi dalam fail itu.

**4. Isi kredensial dari pelayar** — buka website → **⚙ Edit Menu** → tab
**Bayaran** → masukkan kunci admin → tampal tiga nilai dari langkah 1 →
**Simpan kredensial** → **Uji sambungan**.

**5. Aktifkan saluran** yang anda sudah hidupkan dalam console Bayarcash.
Secara lalai hanya FPX aktif di sana; jangan tandakan saluran yang belum
diaktifkan kerana permintaan akan ditolak.

**6. Segerakkan menu ke server** — tekan **Segerakkan menu sekarang**.

Selesai. Butang **Bayar Online** akan muncul dalam cart pelanggan.

### Penting: segerakkan menu setiap kali harga berubah

Harga yang dicaj dikira di **server** dari snapshot menu (`api/data/menu.json`),
bukan dari data yang dihantar pelayar. Ini yang menghalang orang membuka
devtools dan membayar RM 0.01. Kalau anda tukar harga tetapi lupa segerakkan,
panel akan beri amaran bahawa menu di server berbeza.

### Aliran pembayaran

```
Pelanggan tekan "Bayar Online"
   → api/buat-bayaran.php  sahkan cart, kira jumlah dari menu server,
                           cipta Payment Intent (bertandatangan checksum)
   → pelanggan ke halaman Bayarcash, pilih bank, bayar
   → api/callback.php      Bayarcash beritahu server (checksum disahkan,
                           amaun dibandingkan) — ini sumber kebenaran
   → api/pulang.php        pelayar pelanggan balik ke kedai
   → sheet resit muncul, cart dikosongkan
```

Kalau callback lambat atau tersekat, `api/status-order.php` bertanya terus
kepada Bayarcash supaya status tidak tersangkut.

### Lihat order

Tab **Bayaran** → **Buka senarai order**, atau terus ke
`api/orders.php?key=KUNCI_ADMIN`. Jangan kongsi pautan itu — ia mengandungi
kunci admin anda.

### Nota keselamatan

- Personal Access Token dan API Secret Key **tidak pernah** dihantar ke pelayar.
  Panel hanya menunjukkan 4 aksara terakhir untuk pengesahan visual.
- Jumlah bayaran sentiasa dikira di server. Harga dari pelayar diabaikan.
- Checksum callback disahkan dengan `hash_equals` sebelum apa-apa dipercayai.
- Order hanya ditanda **dibayar** bila status `3` **dan** amaun sepadan tepat.
- Order yang sudah berjaya tidak boleh diturunkan statusnya oleh callback lewat.
- `api/data/` (kunci + rekod order) dan `api/config.php` dihalang oleh
  `.htaccess`. **Kalau hosting anda guna nginx**, `.htaccess` diabaikan — tambah
  ini dalam konfigurasi server anda:

  ```nginx
  location ~ ^/api/(data|lib)/  { deny all; return 404; }
  location ~ ^/api/config.*\.php$ { deny all; return 404; }
  ```

- Jangan upload folder `api/` ke hosting yang **tidak** menjalankan PHP — fail
  sumber (termasuk kredensial) boleh dihidangkan sebagai teks biasa.
- `api/config.php` dan `api/data/` sudah ada dalam `.gitignore` supaya kunci
  anda tidak masuk ke Git.

---

## Susunan fail

```
index.html                    struktur laman
assets/css/style.css          keseluruhan reka bentuk & animasi
assets/js/config.js           ⬅ TEMPLATE: data lalai (nama kedai, kategori, menu)
assets/js/store.js            simpan/muat, export/import JSON, link kongsi
assets/js/app.js              paparan menu, cart, checkout WhatsApp
assets/js/editor.js           panel Edit Menu (termasuk tab Bayaran)
assets/js/bayar.js            aliran pembayaran di sebelah pelanggan

api/                          backend pembayaran (pilihan — perlu PHP)
├── config.sample.php         ⬅ salin jadi config.php, set kunci_admin
├── status.php                pembayaran tersedia? (dipanggil oleh laman)
├── admin.php                 simpan kredensial & segerak menu (perlu kunci)
├── buat-bayaran.php          sahkan cart → cipta Payment Intent
├── callback.php              callback server-ke-server dari Bayarcash
├── pulang.php                return_url — bawa pelanggan balik ke kedai
├── status-order.php          status order untuk sheet resit
├── orders.php                senarai order untuk pemilik kedai
├── lib/bayarcash.php         klien API v3 + checksum HMAC SHA256
├── lib/tetapan.php           muat/simpan tetapan & menu dipercayai
├── lib/order.php             pengesahan cart + simpanan order
└── data/                     kunci, snapshot menu, rekod order (dilindungi)
```

Kalau anda selesa dengan code, `config.js` sahaja yang perlu diubah.
Kalau tidak, guna panel Edit Menu — hasilnya sama.

---

## Struktur data satu item menu

```js
{
  id: 'nasi-lemak',            // unik, jangan ulang
  kategori: 'kat1',            // mesti padan dengan id dalam `kategori`
  nama: 'Nasi Lemak Ayam',
  desc: 'Sambal pedas, ayam goreng berempah',
  gambar: '',                  // URL gambar, atau kosong
  emoji: '🍚',                 // ganti gambar dengan emoji (pilihan)
  harga: 9.50,                 // digunakan bila `pilihan` kosong
  pilihan: [                   // ada 2+ → harga papar sebagai julat
    { nama: 'Biasa', harga: 9.50 },
    { nama: 'Set Lengkap', harga: 14.00 },
  ],
  tambahan: [                  // add-on
    { nama: 'Extra Sambal', harga: 1.00 },
  ],
  popular: true,               // lencana "Popular"
  habis: false,                // tanda "Habis", tak boleh order
}
```

---

## Host percuma di GitHub Pages

1. Push folder ini ke repository GitHub anda
2. **Settings → Pages → Source: Deploy from a branch**
3. Pilih branch dan folder `/ (root)` → **Save**
4. Website anda hidup di `https://<username>.github.io/<repo>/`

---

## Nota teknikal

- Tiada framework, tiada langkah build, tiada `npm install`
- Font dari Google Fonts (Kaushan Script, Bebas Neue, Plus Jakarta Sans);
  kalau internet perlahan atau Google Fonts disekat, reka bentuk kekal berfungsi
  dengan font sistem
- Gambar yang di-upload dikecilkan ke maks 640px dan disimpan sebagai JPEG
  supaya `localStorage` tak penuh
- Semua teks yang dimasukkan pengguna di-escape sebelum dipaparkan
- Hormat `prefers-reduced-motion` — animasi dimatikan untuk pengguna yang
  memilih pergerakan minimum
- Diuji dengan Chromium (desktop 1366px + telefon 390px)

---

## Soalan lazim

**Boleh terima pembayaran online?**
Ya — melalui Bayarcash (FPX, DuitNow, DuitNow QR, BNPL). Lihat bahagian
[Pembayaran online dengan Bayarcash](#-pembayaran-online-dengan-bayarcash-pilihan)
di atas. Ia memerlukan hosting PHP. Tanpanya, order pergi ke WhatsApp dan
pembayaran diuruskan antara anda dan pelanggan (COD, transfer, QR).

**Perlu ke saya guna pembayaran online?**
Tidak. Ia pilihan sepenuhnya. Banyak gerai lebih suka COD — laman ini berfungsi
penuh tanpa folder `api/`.

**Pelanggan sudah bayar tetapi saya tak dapat notifikasi?**
Selepas bayar, skrin resit ada butang **Beritahu kedai via WhatsApp** yang
menghantar butiran order + rujukan bank kepada anda. Semua order juga direkod di
`api/orders.php?key=KUNCI_ADMIN` dan dalam console Bayarcash anda.

**Boleh guna gateway lain?**
Boleh — `api/lib/bayarcash.php` adalah satu-satunya fail yang tahu tentang
Bayarcash. toyyibPay, Billplz, CHIP dan senangPay mengikut pola yang sama
(cipta bil → redirect → callback bertandatangan).

**Berapa banyak item boleh masuk?**
Tiada had teknikal. Untuk lebih 100 item dengan gambar upload, guna Cara 2
(simpan dalam `config.js`) dan letak gambar sebagai fail dalam `assets/`.

**Pelanggan lain nampak cart saya?**
Tidak. Cart disimpan dalam pelayar masing-masing.

---

Dibina dengan ❤️ untuk peniaga kecil Malaysia.
