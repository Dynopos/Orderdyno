/* ==========================================================================
   OrderDyno — TEMPLATE ASAS
   --------------------------------------------------------------------------
   Ini data permulaan (placeholder) untuk template.

   Anda ADA DUA cara nak isi menu sendiri:

   1) CARA MUDAH (disyorkan) — buka website, tekan butang "Edit Menu"
      di penjuru atas. Semua tetapan & menu boleh diisi terus dari sana.
      Ia disimpan dalam pelayar anda, dan boleh di-Export jadi fail JSON.

   2) CARA KEKAL — ubah nilai dalam fail ini. Sesuai kalau anda nak
      menu anda jadi menu lalai untuk semua pelawat (selepas upload).
   ========================================================================== */

const TEMPLATE = {
  versi: 2,

  /* ---------------------------- MAKLUMAT KEDAI ---------------------------- */
  kedai: {
    nama: 'Nama Kedai Anda',
    tagline: 'Tagline kedai anda di sini',

    // Logo: biar kosong untuk tunjuk placeholder. Boleh letak URL gambar,
    // atau upload dari panel Edit Menu (jadi data URL).
    logo: '',
    logoEmoji: '',

    mataWang: 'RM',

    // Nombor WhatsApp — format antarabangsa TANPA '+', space atau '-'
    // Contoh Malaysia: 60123456789
    whatsapp: '60120000000',

    // Nombor untuk paparan sahaja (kad "Hubungi" di footer)
    telefon: '+60 12-000 0000',

    alamat: 'Alamat kedai anda',
    pautanLokasi: '', // contoh: https://maps.google.com/?q=...
    waktu: 'Setiap hari · 10:00 AM – 10:00 PM',

    // Butang kedua di hero. Kalau `pautan` kosong, ia scroll ke menu.
    orderNow: { label: 'Order Now', pautan: '' },

    // Nota ringkas yang muncul dalam cart (pilihan)
    nota: '',

    // Set `true` untuk sembunyikan butang "Edit Menu" dari pelanggan.
    // Anda masih boleh buka panel dengan tambah #edit pada hujung URL.
    sembunyikanEdit: false,
  },

  /* -------------------------------- TEMA --------------------------------- */
  tema: {
    preset: 'aurora',
    warna1: '#a855f7', // ungu
    warna2: '#ff4d94', // pink
    warna3: '#ff9a3c', // oren
    latar: '#0b0616',
  },

  /* ---------------------------- CARA TERIMA ORDER ------------------------ */
  penghantaran: {
    pickup: { aktif: true, label: 'Ambil Sendiri', nota: 'Sedia dalam 15–20 minit' },
    delivery: { aktif: false, label: 'Penghantaran', caj: 0, minOrder: 0, nota: '' },
  },

  /* ------------------------------ KATEGORI ------------------------------- */
  kategori: [
    { id: 'kat1', nama: 'Kategori 1' },
    { id: 'kat2', nama: 'Kategori 2' },
  ],

  /* -------------------------------- MENU ---------------------------------
     Setiap item:
       id       — unik
       kategori — mesti padan dengan id dalam `kategori`
       nama     — nama paparan
       desc     — penerangan pendek (boleh kosong)
       gambar   — URL atau data URL (boleh kosong -> placeholder)
       emoji    — ganti gambar dengan emoji besar (boleh kosong)
       harga    — harga tunggal, digunakan bila `pilihan` kosong
       pilihan  — variasi/saiz [{ nama, harga }]. Kalau >1, harga papar julat.
       tambahan — add-on [{ nama, harga }]
       popular  — true untuk lencana "Popular"
       habis    — true untuk tanda "Habis" (tak boleh order)
     ---------------------------------------------------------------------- */
  menu: [
    {
      id: 'item1',
      kategori: 'kat1',
      nama: 'Contoh Menu A',
      desc: 'Terangkan menu ini',
      gambar: '',
      emoji: '',
      harga: 5.0,
      pilihan: [],
      tambahan: [],
      popular: false,
      habis: false,
    },
    {
      id: 'item2',
      kategori: 'kat1',
      nama: 'Contoh Menu B',
      desc: '',
      gambar: '',
      emoji: '',
      harga: 8.0,
      pilihan: [
        { nama: 'Biasa', harga: 8.0 },
        { nama: 'Besar', harga: 12.0 },
      ],
      tambahan: [],
      popular: false,
      habis: false,
    },
    {
      id: 'item3',
      kategori: 'kat2',
      nama: 'Contoh Menu C',
      desc: '',
      gambar: '',
      emoji: '',
      harga: 4.5,
      pilihan: [],
      tambahan: [],
      popular: false,
      habis: false,
    },
  ],
};

/* --------------------------------------------------------------------------
   PRESET TEMA — pilihan warna siap pakai dalam panel Edit Menu
   -------------------------------------------------------------------------- */
const PRESET_TEMA = [
  { id: 'aurora',  nama: 'Aurora',   warna1: '#a855f7', warna2: '#ff4d94', warna3: '#ff9a3c', latar: '#0b0616' },
  { id: 'sunset',  nama: 'Sunset',   warna1: '#ff6a3d', warna2: '#ff2e63', warna3: '#ffc93c', latar: '#150a12' },
  { id: 'ocean',   nama: 'Ocean',    warna1: '#22d3ee', warna2: '#3b82f6', warna3: '#8b5cf6', latar: '#050f1a' },
  { id: 'matcha',  nama: 'Matcha',   warna1: '#4ade80', warna2: '#14b8a6', warna3: '#facc15', latar: '#04150f' },
  { id: 'emas',    nama: 'Emas',     warna1: '#f5c542', warna2: '#ff8a3d', warna3: '#ffe9a8', latar: '#120d05' },
  { id: 'sakura',  nama: 'Sakura',   warna1: '#ff8fb1', warna2: '#c084fc', warna3: '#ffd6e0', latar: '#160910' },
];
