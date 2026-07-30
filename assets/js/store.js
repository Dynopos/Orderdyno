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

  return {
    muat,
    simpan,
    padam,
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
