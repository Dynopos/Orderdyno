/* ==========================================================================
   OrderDyno — Tetapan Kedai
   --------------------------------------------------------------------------
   Ini SATU-SATUNYA fail yang perlu penjaja ubah untuk guna app ini.
   Tukar nama kedai, nombor WhatsApp, waktu operasi dan menu di bawah.
   ========================================================================== */

const KEDAI = {
  nama: 'Warung Jaja',
  tagline: 'Sedap Panas-Panas, Terus Dari Dapur',
  slogan: 'Dimasak bila anda order 🔥',
  logoEmoji: '🍔',

  // Nombor WhatsApp penjaja — format antarabangsa TANPA '+' atau '-'
  // Contoh Malaysia: 60123456789
  whatsapp: '60123456789',

  mataWang: 'RM',

  // Waktu operasi (24 jam). Guna null untuk buka 24 jam.
  waktu: { buka: '11:00', tutup: '23:30' },

  // Cara terima order
  pickup: { aktif: true, label: 'Ambil Sendiri', nota: 'Sedia dalam 15–20 minit' },
  delivery: {
    aktif: true,
    label: 'Penghantaran',
    caj: 4.0,
    nota: 'Dalam radius 5km sahaja',
    minOrder: 15.0,
  },

  alamat: 'Gerai Depan Masjid Taman Melati, Kuala Lumpur',
  waze: 'https://waze.com/ul?q=Taman%20Melati',
};

/* --------------------------------------------------------------------------
   KATEGORI
   -------------------------------------------------------------------------- */
const KATEGORI = [
  { id: 'burger', nama: 'Burger', emoji: '🍔' },
  { id: 'fries', nama: 'Fries', emoji: '🍟' },
  { id: 'minuman', nama: 'Air Minuman', emoji: '🥤' },
];

/* --------------------------------------------------------------------------
   MENU
   --------------------------------------------------------------------------
   Setiap item:
     id       — unik, jangan ulang
     nama     — nama paparan
     desc     — penerangan pendek
     kategori — mesti padan dengan id dalam KATEGORI
     pilihan  — senarai saiz/variasi { nama, harga }
     tambahan — (pilihan) add-on { nama, harga }
     art      — lukisan SVG auto-generate (lihat assets/js/art.js)
     gambar   — (pilihan) URL gambar sebenar; kalau ada, ia ganti `art`
     popular  — true untuk tunjuk lencana "Paling Laris"
   -------------------------------------------------------------------------- */
const MENU = [
  /* ---------------------------------- BURGER ---------------------------- */
  {
    id: 'bgr-ayam',
    nama: 'Burger Ayam Special',
    desc: 'Patty ayam, telur, salad, mayo & sos istimewa',
    kategori: 'burger',
    popular: true,
    pilihan: [
      { nama: 'Single', harga: 5.5 },
      { nama: 'Double', harga: 9.5 },
    ],
    tambahan: [
      { nama: 'Extra Telur', harga: 1.5 },
      { nama: 'Extra Cheese', harga: 1.5 },
    ],
    art: { jenis: 'burger', patty: '#c98a4b', topping: 'salad', bun: '#f2b45c' },
  },
  {
    id: 'bgr-daging',
    nama: 'Burger Daging Special',
    desc: 'Patty daging bakar, telur, bawang & sos BBQ',
    kategori: 'burger',
    popular: true,
    pilihan: [
      { nama: 'Single', harga: 6.0 },
      { nama: 'Double', harga: 10.5 },
    ],
    tambahan: [
      { nama: 'Extra Telur', harga: 1.5 },
      { nama: 'Extra Cheese', harga: 1.5 },
    ],
    art: { jenis: 'burger', patty: '#6b3a20', topping: 'bawang', bun: '#e8a54f' },
  },
  {
    id: 'bgr-cheese',
    nama: 'Burger Cheese Melt',
    desc: 'Dua keping cheese cair atas patty daging panas',
    kategori: 'burger',
    pilihan: [
      { nama: 'Single', harga: 7.0 },
      { nama: 'Double', harga: 12.0 },
    ],
    tambahan: [{ nama: 'Extra Cheese', harga: 1.5 }],
    art: { jenis: 'burger', patty: '#6b3a20', topping: 'cheese', bun: '#f0ad51' },
  },
  {
    id: 'bgr-kambing',
    nama: 'Burger Kambing',
    desc: 'Patty kambing berempah, sos mint & timun',
    kategori: 'burger',
    pilihan: [
      { nama: 'Single', harga: 8.5 },
      { nama: 'Double', harga: 15.0 },
    ],
    art: { jenis: 'burger', patty: '#4e2a18', topping: 'salad', bun: '#d99845' },
  },
  {
    id: 'bgr-benjo',
    nama: 'Burger Benjo Telur',
    desc: 'Benjo digoreng rangup, telur dadar & sos cili',
    kategori: 'burger',
    pilihan: [
      { nama: 'Biasa', harga: 4.5 },
      { nama: 'Double Benjo', harga: 7.5 },
    ],
    art: { jenis: 'burger', patty: '#d9762f', topping: 'telur', bun: '#f5bb63' },
  },

  /* ---------------------------------- FRIES ----------------------------- */
  {
    id: 'fry-original',
    nama: 'Fries Original',
    desc: 'Rangup luar, lembut dalam, taburan garam laut',
    kategori: 'fries',
    popular: true,
    pilihan: [
      { nama: 'Regular', harga: 4.0 },
      { nama: 'Large', harga: 6.5 },
    ],
    art: { jenis: 'fries', kotak: '#e2453b', taburan: null },
  },
  {
    id: 'fry-cheese',
    nama: 'Fries Cheese',
    desc: 'Dicurah sos cheese panas melimpah',
    kategori: 'fries',
    pilihan: [
      { nama: 'Regular', harga: 6.0 },
      { nama: 'Large', harga: 9.0 },
    ],
    art: { jenis: 'fries', kotak: '#f4a623', taburan: 'cheese' },
  },
  {
    id: 'fry-blackpepper',
    nama: 'Fries Black Pepper',
    desc: 'Perisa lada hitam pedas menyengat',
    kategori: 'fries',
    pilihan: [
      { nama: 'Regular', harga: 6.0 },
      { nama: 'Large', harga: 9.0 },
    ],
    art: { jenis: 'fries', kotak: '#3d3d5c', taburan: 'pepper' },
  },
  {
    id: 'fry-saltedegg',
    nama: 'Fries Salted Egg',
    desc: 'Sos telur masin creamy dengan daun kari',
    kategori: 'fries',
    pilihan: [
      { nama: 'Regular', harga: 7.0 },
      { nama: 'Large', harga: 10.0 },
    ],
    art: { jenis: 'fries', kotak: '#e8b53a', taburan: 'saltedegg' },
  },

  /* --------------------------------- MINUMAN ---------------------------- */
  {
    id: 'drk-teh',
    nama: 'Teh Ais',
    desc: 'Teh tarik sejuk berais, manis berkrim',
    kategori: 'minuman',
    popular: true,
    pilihan: [
      { nama: 'Regular', harga: 3.0 },
      { nama: 'Large', harga: 4.5 },
    ],
    art: { jenis: 'minuman', warna: '#c98b52', warna2: '#e8c9a0', ais: true },
  },
  {
    id: 'drk-milo',
    nama: 'Milo Ais',
    desc: 'Milo pekat, susu penuh & ais batu',
    kategori: 'minuman',
    pilihan: [
      { nama: 'Regular', harga: 4.0 },
      { nama: 'Large', harga: 5.5 },
    ],
    tambahan: [{ nama: 'Extra Milo Powder', harga: 1.0 }],
    art: { jenis: 'minuman', warna: '#5c3a21', warna2: '#8a5a33', ais: true },
  },
  {
    id: 'drk-bandung',
    nama: 'Sirap Bandung',
    desc: 'Sirap ros dengan susu, klasik kegemaran',
    kategori: 'minuman',
    pilihan: [
      { nama: 'Regular', harga: 3.5 },
      { nama: 'Large', harga: 5.0 },
    ],
    art: { jenis: 'minuman', warna: '#e8517f', warna2: '#f7a7c1', ais: true },
  },
  {
    id: 'drk-limau',
    nama: 'Limau Ais',
    desc: 'Limau nipis segar, masam manis menyegarkan',
    kategori: 'minuman',
    pilihan: [
      { nama: 'Regular', harga: 3.0 },
      { nama: 'Large', harga: 4.5 },
    ],
    art: { jenis: 'minuman', warna: '#8bc34a', warna2: '#d4e86a', ais: true },
  },
  {
    id: 'drk-kopi',
    nama: 'Kopi Ais',
    desc: 'Kopi kampung pekat, susu manis',
    kategori: 'minuman',
    pilihan: [
      { nama: 'Regular', harga: 3.5 },
      { nama: 'Large', harga: 5.0 },
    ],
    art: { jenis: 'minuman', warna: '#3e2416', warna2: '#7a4b2a', ais: true },
  },
  {
    id: 'drk-mineral',
    nama: 'Air Mineral',
    desc: 'Botol 500ml sejuk',
    kategori: 'minuman',
    pilihan: [{ nama: '500ml', harga: 2.0 }],
    art: { jenis: 'minuman', warna: '#7ed6f2', warna2: '#c9f0fb', ais: false },
  },
];
