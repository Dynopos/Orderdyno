/* ==========================================================================
   OrderDyno — Lapisan Data (Store)
   --------------------------------------------------------------------------
   Tugas fail ini:
     • Muat tetapan kedai + menu (localStorage > link kongsi > TEMPLATE)
     • Simpan balik ke localStorage
     • Export / Import fail JSON
     • Encode / decode link kongsi (#menu=...)
     • Simpan cart supaya tak hilang bila refresh
   Tiada server, tiada database — semua di dalam pelayar pelanggan.
   ========================================================================== */

const Store = (() => {
  const KUNCI = 'orderdyno:v2';
  const KUNCI_CART = 'orderdyno:cart:v2';

  /* Isnin dahulu — susunan yang biasa digunakan di Malaysia.
     `js` ialah nombor hari JavaScript (0 = Ahad). */
  const HARI = [
    { id: 'isnin',  nama: 'Isnin',  js: 1 },
    { id: 'selasa', nama: 'Selasa', js: 2 },
    { id: 'rabu',   nama: 'Rabu',   js: 3 },
    { id: 'khamis', nama: 'Khamis', js: 4 },
    { id: 'jumaat', nama: 'Jumaat', js: 5 },
    { id: 'sabtu',  nama: 'Sabtu',  js: 6 },
    { id: 'ahad',   nama: 'Ahad',   js: 0 },
  ];

  /* ------------------------------- Utiliti ------------------------------- */

  const klon = (o) => JSON.parse(JSON.stringify(o));

  function idBaru(awalan) {
    return awalan + '-' + Math.random().toString(36).slice(2, 8);
  }

  /* Gabung tetapan tersimpan dengan TEMPLATE supaya medan baru tak hilang
     bila template dikemas kini. */
  function gabung(asas, atas) {
    if (!atas || typeof atas !== 'object') return klon(asas);
    if (Array.isArray(asas)) return Array.isArray(atas) ? klon(atas) : klon(asas);
    const hasil = klon(asas);
    Object.keys(atas).forEach((k) => {
      const a = asas[k];
      const b = atas[k];
      if (a && typeof a === 'object' && !Array.isArray(a) && b && typeof b === 'object') {
        hasil[k] = gabung(a, b);
      } else if (b !== undefined) {
        hasil[k] = klon(b);
      }
    });
    return hasil;
  }

  /* Pastikan setiap item ada bentuk yang betul (elak crash bila import
     fail JSON yang tak lengkap). */
  function bersih(c) {
    const d = gabung(TEMPLATE, c);
    d.versi = TEMPLATE.versi;

    d.kategori = (Array.isArray(d.kategori) ? d.kategori : [])
      .filter((k) => k && (k.nama || k.id))
      .map((k) => ({ id: String(k.id || idBaru('kat')), nama: String(k.nama || 'Kategori') }));

    if (!d.kategori.length) d.kategori = klon(TEMPLATE.kategori);

    const idKategori = d.kategori.map((k) => k.id);

    /* Waktu operasi — pastikan setiap hari ada bentuk yang betul */
    const w = d.waktuBuka && typeof d.waktuBuka === 'object' ? d.waktuBuka : {};
    const jam = (v, lalai) => (/^\d{1,2}:\d{2}$/.test(String(v)) ? String(v).padStart(5, '0') : lalai);
    d.waktuBuka = {
      aktif: !!w.aktif,
      zon: Number.isFinite(Number(w.zon)) ? Number(w.zon) : 8,
      tutupSementara: !!w.tutupSementara,
      mesej: String(w.mesej || TEMPLATE.waktuBuka.mesej),
      hari: {},
    };
    HARI.forEach((h) => {
      const x = (w.hari && w.hari[h.id]) || {};
      d.waktuBuka.hari[h.id] = {
        tutupHariIni: !!x.tutupHariIni,
        buka: jam(x.buka, '10:00'),
        tutup: jam(x.tutup, '22:00'),
      };
    });

    d.menu = (Array.isArray(d.menu) ? d.menu : []).filter(Boolean).map((m) => ({
      id: String(m.id || idBaru('item')),
      kategori: idKategori.includes(m.kategori) ? m.kategori : idKategori[0],
      nama: String(m.nama || 'Item baru'),
      desc: String(m.desc || ''),
      gambar: String(m.gambar || ''),
      emoji: String(m.emoji || ''),
      harga: Number(m.harga) || 0,
      pilihan: (Array.isArray(m.pilihan) ? m.pilihan : [])
        .filter((p) => p && p.nama)
        .map((p) => ({ nama: String(p.nama), harga: Number(p.harga) || 0 })),
      tambahan: (Array.isArray(m.tambahan) ? m.tambahan : [])
        .filter((t) => t && t.nama)
        .map((t) => ({ nama: String(t.nama), harga: Number(t.harga) || 0 })),
      popular: !!m.popular,
      habis: !!m.habis,
    }));

    return d;
  }

  /* --------------------------- Link kongsi (URL) -------------------------- */

  function encode(obj) {
    const bait = new TextEncoder().encode(JSON.stringify(obj));
    let bin = '';
    bait.forEach((b) => (bin += String.fromCharCode(b)));
    return btoa(bin).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
  }

  function decode(teks) {
    const b64 = teks.replace(/-/g, '+').replace(/_/g, '/');
    const bin = atob(b64);
    const bait = Uint8Array.from(bin, (c) => c.charCodeAt(0));
    return JSON.parse(new TextDecoder().decode(bait));
  }

  function dariUrl() {
    const padan = location.hash.match(/menu=([A-Za-z0-9\-_]+)/);
    if (!padan) return null;
    try {
      return bersih(decode(padan[1]));
    } catch (e) {
      console.warn('Link menu tidak sah', e);
      return null;
    }
  }

  function jadiLink(config) {
    const asas = location.href.split('#')[0];
    return asas + '#menu=' + encode(config);
  }

  /* ------------------------------ Muat / Simpan --------------------------- */

  let dariLink = false;

  function muat() {
    const url = dariUrl();
    if (url) {
      dariLink = true;
      return url;
    }
    try {
      const mentah = localStorage.getItem(KUNCI);
      if (mentah) return bersih(JSON.parse(mentah));
    } catch (e) {
      console.warn('Gagal muat tetapan tersimpan', e);
    }
    return klon(TEMPLATE);
  }

  /* Ada tetapan tersimpan dalam pelayar ini? Digunakan untuk menentukan
     sama ada perlu muat menu yang diterbitkan dari server. */
  function adaTersimpan() {
    try {
      return !!localStorage.getItem(KUNCI);
    } catch (e) {
      return false;
    }
  }

  function simpan(config) {
    try {
      localStorage.setItem(KUNCI, JSON.stringify(config));
      dariLink = false;
      return true;
    } catch (e) {
      console.warn('Gagal simpan tetapan', e);
      return false;
    }
  }

  function padam() {
    try {
      localStorage.removeItem(KUNCI);
    } catch (e) {
      /* abaikan */
    }
  }

  /* -------------------------------- Cart --------------------------------- */

  function muatCart() {
    try {
      const mentah = localStorage.getItem(KUNCI_CART);
      const senarai = mentah ? JSON.parse(mentah) : [];
      return Array.isArray(senarai) ? senarai : [];
    } catch (e) {
      return [];
    }
  }

  function simpanCart(cart) {
    try {
      localStorage.setItem(KUNCI_CART, JSON.stringify(cart));
    } catch (e) {
      /* abaikan */
    }
  }

  /* ------------------------------ Export fail ---------------------------- */

  function turunJson(config, namaFail) {
    const blob = new Blob([JSON.stringify(config, null, 2)], { type: 'application/json' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = namaFail || 'menu-orderdyno.json';
    document.body.appendChild(a);
    a.click();
    a.remove();
    setTimeout(() => URL.revokeObjectURL(url), 1000);
  }

  /* ============================ WAKTU OPERASI ===========================
     Kira status buka/tutup mengikut waktu KEDAI, bukan waktu peranti
     pelawat. Logik ini disalin dalam api/lib/waktu.php — kalau anda
     mengubah satu, ubah yang satu lagi juga.
     ==================================================================== */

  const kepadaMinit = (jam) => {
    const b = String(jam).split(':');
    return (parseInt(b[0], 10) || 0) * 60 + (parseInt(b[1], 10) || 0);
  };

  /**
   * @returns {{buka:boolean, sebab:string, mesej:string, seterusnya:string}}
   */
  function statusBuka(config, masaUji) {
    const w = (config && config.waktuBuka) || {};
    if (!w.aktif) return { buka: true, sebab: 'tiada-waktu', mesej: '', seterusnya: '' };

    const mesej = w.mesej || 'Kedai sedang tutup.';
    if (w.tutupSementara) {
      return { buka: false, sebab: 'tutup-sementara', mesej, seterusnya: '' };
    }

    /* Masa kedai = UTC + zon. getTime() sentiasa UTC, jadi ini betul
       walaupun pelawat berada di zon waktu lain. */
    const asal = masaUji instanceof Date ? masaUji : new Date();
    const kedai = new Date(asal.getTime() + (Number(w.zon) || 0) * 3600000);
    const hariJs = kedai.getUTCDay();
    const kini = kedai.getUTCHours() * 60 + kedai.getUTCMinutes();

    const ikutJs = (js) => HARI.find((h) => h.js === js);
    const tetapan = (js) => (w.hari && w.hari[ikutJs(js).id]) || {};

    /* Buka kalau hari ini dalam julatnya, ATAU semalam masih berterusan
       melepasi tengah malam. */
    const dalamJulat = (js, minit) => {
      const t = tetapan(js);
      if (t.tutupHariIni) return false;
      const b = kepadaMinit(t.buka);
      const p = kepadaMinit(t.tutup);
      return p > b ? minit >= b && minit < p : minit >= b || minit < p;
    };

    const semalamJs = (hariJs + 6) % 7;
    const semalam = tetapan(semalamJs);
    const lanjut =
      !semalam.tutupHariIni &&
      kepadaMinit(semalam.tutup) <= kepadaMinit(semalam.buka) &&
      kini < kepadaMinit(semalam.tutup);

    if (dalamJulat(hariJs, kini) || lanjut) {
      return { buka: true, sebab: 'dalam-waktu', mesej: '', seterusnya: '' };
    }

    /* Cari waktu buka seterusnya — hari ini dahulu, kemudian 7 hari ke depan */
    let seterusnya = '';
    for (let i = 0; i < 8; i++) {
      const js = (hariJs + i) % 7;
      const t = tetapan(js);
      if (t.tutupHariIni) continue;
      const b = kepadaMinit(t.buka);
      if (i === 0 && kini >= b) continue;      // waktu hari ini sudah berlalu
      const label = i === 0 ? 'hari ini' : i === 1 ? 'esok' : ikutJs(js).nama;
      seterusnya = label + ' jam ' + t.buka;
      break;
    }

    return { buka: false, sebab: 'luar-waktu', mesej, seterusnya };
  }

  return {
    HARI,
    statusBuka,
    muat,
    simpan,
    padam,
    adaTersimpan,
    bersih,
    gabung,
    klon,
    idBaru,
    muatCart,
    simpanCart,
    jadiLink,
    turunJson,
    dariLink: () => dariLink,
  };
})();
