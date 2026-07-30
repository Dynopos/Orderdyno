# 🛒 OrderDyno — Template Menu Online + Order WhatsApp

Template website ordering untuk kedai kecil, kafe, gerai dan penjaja.
Pelanggan pilih menu → masuk cart → **order terus masuk ke WhatsApp anda**.

**Yang paling penting: pemilik kedai boleh isi menu sendiri tanpa sentuh code.**
Tekan butang **⚙ Edit Menu** di penjuru atas, isi semuanya dari borang.

Tiada backend, tiada database, tiada langganan bulanan — hanya HTML, CSS dan
JavaScript biasa. Boleh host percuma di GitHub Pages, Netlify, Vercel, Cloudflare
Pages atau mana-mana hosting statik.

![Hero](docs/preview-hero.png)
![Menu](docs/preview-menu.png)
![Panel Edit Menu](docs/preview-editor.png)

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

Tab **Simpan & Kongsi** → **📋 Salin link**. Link itu mengandungi seluruh menu
anda. Hantar dalam bio Instagram, status WhatsApp, atau jadikan QR code.
Sesiapa yang buka akan nampak menu anda.

Sesuai untuk: menu ringkas tanpa gambar upload. Kalau anda upload banyak gambar,
link jadi terlalu panjang — guna Cara 2 atau 3.

### Cara 2 — Jadikan kekal dalam kod (disyorkan untuk kedai serius)

1. Tab **Simpan & Kongsi** → **⬇ Export fail JSON**
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

## Susunan fail

```
index.html              struktur laman
assets/css/style.css    keseluruhan reka bentuk & animasi
assets/js/config.js     ⬅ TEMPLATE: data lalai (nama kedai, kategori, menu)
assets/js/store.js      simpan/muat, export/import JSON, link kongsi
assets/js/app.js        paparan menu, cart, checkout WhatsApp
assets/js/editor.js     panel Edit Menu
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
Template ini hantar order ke WhatsApp; pembayaran diuruskan antara anda dan
pelanggan (COD, transfer, QR). Untuk pembayaran automatik, integrasi gateway
Malaysia seperti toyyibPay, Billplz, CHIP, senangPay atau Bayarcash boleh
ditambah kemudian.

**Berapa banyak item boleh masuk?**
Tiada had teknikal. Untuk lebih 100 item dengan gambar upload, guna Cara 2
(simpan dalam `config.js`) dan letak gambar sebagai fail dalam `assets/`.

**Pelanggan lain nampak cart saya?**
Tidak. Cart disimpan dalam pelayar masing-masing.

---

Dibina dengan ❤️ untuk peniaga kecil Malaysia.
