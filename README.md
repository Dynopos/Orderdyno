# 🍔 OrderDyno — App Ordering Generik Untuk Penjaja Kecil

Landing page + cart untuk gerai kecil. Pelanggan pilih menu, masukkan dalam cart,
isi nama & alamat, kemudian **order terus masuk ke WhatsApp penjaja**.

Tiada backend, tiada database, tiada yuran platform — hanya HTML, CSS dan
JavaScript biasa. Boleh host percuma di GitHub Pages, Netlify, Vercel atau
mana-mana hosting statik.

![Menu](docs/preview-menu.png)

## Ciri-ciri

- 🎨 Latar belakang gradient warna-warni beranimasi
- 🍔 Gambar makanan auto-lukis (SVG) — tak perlu upload gambar untuk mula
- 🛒 Cart dengan butang **Semak Order** melekat di atas skrin
- ➕ Saiz/variasi (Single, Double, Regular, Large), add-on dan nota per item
- 💾 Cart & maklumat pelanggan disimpan dalam browser (tak hilang bila refresh)
- 🏃 Pilihan **Ambil Sendiri** atau **Penghantaran** (caj + order minimum)
- ⏰ Status **Buka / Tutup** ikut waktu operasi
- 📲 Checkout hantar mesej order kemas ke WhatsApp
- 📱 Mobile-first, elok juga atas desktop

## Guna dalam 3 langkah

1. **Buka `assets/js/config.js`** — ini satu-satunya fail yang perlu diubah.
2. **Tukar nombor WhatsApp** kepada nombor penjaja (format antarabangsa, tanpa
   `+` atau `-`):
   ```js
   whatsapp: '60123456789',   // 012-345 6789
   ```
3. **Tukar nama kedai dan menu**, kemudian upload semua fail ke hosting.

Untuk cuba secara lokal:

```bash
python3 -m http.server 8000
# buka http://localhost:8000
```

## Ubah menu

Semua item ada dalam array `MENU` di `assets/js/config.js`:

```js
{
  id: 'bgr-ayam',                       // mesti unik
  nama: 'Burger Ayam Special',
  desc: 'Patty ayam, telur, salad, mayo & sos istimewa',
  kategori: 'burger',                   // padan dengan id dalam KATEGORI
  popular: true,                        // lencana "Paling Laris"
  pilihan: [                            // saiz / variasi — sekurangnya satu
    { nama: 'Single', harga: 5.50 },
    { nama: 'Double', harga: 9.50 },
  ],
  tambahan: [                           // pilihan, boleh buang
    { nama: 'Extra Telur', harga: 1.50 },
  ],
  art: { jenis: 'burger', patty: '#c98a4b', topping: 'salad', bun: '#f2b45c' },
}
```

### Kategori

Tambah atau buang kategori dalam array `KATEGORI`. Kategori yang tiada item
tidak akan dipaparkan.

```js
const KATEGORI = [
  { id: 'burger',  nama: 'Burger',      emoji: '🍔' },
  { id: 'fries',   nama: 'Fries',       emoji: '🍟' },
  { id: 'minuman', nama: 'Air Minuman', emoji: '🥤' },
];
```

## Gambar makanan

Secara lalai setiap item dilukis automatik guna SVG (`assets/js/art.js`), jadi
menu nampak elok walaupun penjaja belum ada gambar.

| `art.jenis` | Pilihan warna / gaya |
|---|---|
| `burger` | `bun`, `patty` (kod warna hex) · `topping`: `salad`, `cheese`, `bawang`, `telur` |
| `fries` | `kotak` (warna kotak) · `taburan`: `cheese`, `pepper`, `saltedegg`, atau `null` |
| `minuman` | `warna`, `warna2` (kod warna hex) · `ais`: `true` / `false` |

Bila dah ada gambar sebenar, letak dalam `assets/img/` dan tambah `gambar:` pada
item — ia akan ganti lukisan SVG:

```js
{ id: 'bgr-ayam', nama: 'Burger Ayam', gambar: 'assets/img/burger-ayam.jpg', ... }
```

Guna gambar bersegi empat sama (cth 600×600px) supaya kad nampak kemas.

## Tetapan kedai

Semua di dalam objek `KEDAI`:

| Tetapan | Kegunaan |
|---|---|
| `nama`, `tagline`, `slogan`, `logoEmoji` | Paparan di bahagian atas |
| `whatsapp` | Nombor penerima order (wajib) |
| `mataWang` | Lalai `RM` |
| `waktu` | `{ buka: '11:00', tutup: '23:30' }`, atau `null` untuk buka 24 jam |
| `pickup` | Aktif/tidak, label, nota masa siap |
| `delivery` | Aktif/tidak, `caj` penghantaran, `minOrder` |
| `alamat`, `waze` | Lokasi gerai pada lencana atas |

Waktu tutup selepas tengah malam pun boleh, contoh `{ buka: '18:00', tutup: '02:00' }`.

Bila kedai tutup, pelanggan masih boleh hantar order — hanya dipaparkan amaran
bahawa penjaja akan sahkan bila buka.

## Struktur fail

```
index.html               Struktur halaman
assets/css/style.css     Tema warna-warni
assets/js/config.js      ← Penjaja edit fail ini sahaja
assets/js/art.js         Penjana lukisan makanan SVG
assets/js/app.js         Cart, sheet, checkout WhatsApp
```

## Nota

- Order dihantar sebagai mesej WhatsApp — tiada pembayaran online. Penjaja
  sahkan order dan terima bayaran (tunai / QR / transfer) seperti biasa.
- Nama, telefon dan alamat pelanggan disimpan dalam browser pelanggan sendiri
  (`localStorage`) supaya tak perlu taip semula. Tiada data dihantar ke
  mana-mana pelayan selain WhatsApp.
