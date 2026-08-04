/* ==========================================================================
   OrderDyno — IKON APLIKASI
   --------------------------------------------------------------------------
   Bila pelanggan menekan "Pasang di telefon", ikon yang muncul pada skrin
   utama mereka mestilah ikon KEDAI itu — bukan ikon OrderDyno.

   Fail ini melukis ikon itu dalam pelayar menggunakan <canvas>, kerana di
   situlah emoji boleh dirender dengan betul: emoji berwarna datang dari font
   peranti, dan server tidak semestinya mempunyai font itu.

   Hasilnya PNG 512x512 sebagai data URI. Ia digunakan di dua tempat:

     1. app.js  — menetapkan favicon dan apple-touch-icon serta-merta
     2. editor.js — menghantarnya ke api/admin.php (aksi 'ikon') semasa
        menerbitkan menu, supaya api/ikon.php boleh menghidangkannya kepada
        manifest. Ia disimpan berasingan daripada menu kerana menu awam
        dimuat turun oleh setiap pelawat pada setiap lawatan.

   Ikon dilukis penuh sampai ke tepi dan kandungannya dikekalkan dalam 80%
   bahagian tengah. Itu syarat "maskable": Android memotong ikon mengikut
   bentuk pilihan pengguna (bulat, bulat-segi empat), jadi apa-apa yang
   penting mesti jauh dari tepi.
   ========================================================================== */

const Ikon = (() => {
  const SAIZ = 512;
  const SELAMAT = 0.62;          // pecahan lebar untuk kandungan (zon selamat)

  function muatGambar(src) {
    return new Promise((selesai) => {
      const img = new Image();
      img.onload = () => selesai(img);
      img.onerror = () => selesai(null);
      img.src = src;
    });
  }

  /* Latar sengaja RATA, bukan bergradien.
     Gradien licin menjadikan PNG 512px hampir 300KB kerana setiap piksel
     warnanya berbeza sedikit dan tiada apa untuk dimampatkan. Warna rata
     memberi rupa yang sama pada saiz ikon sebenar, dengan kira-kira 55KB. */
  function latarBelakang(ctx, tema) {
    ctx.fillStyle = tema.latar || '#0b0616';
    ctx.fillRect(0, 0, SAIZ, SAIZ);
  }

  /* Huruf pertama nama kedai, untuk kedai tanpa emoji dan tanpa logo */
  function lukisHuruf(ctx, nama, tema) {
    const huruf = (String(nama || '?').trim()[0] || '?').toUpperCase();
    const grad = ctx.createLinearGradient(0, SAIZ * 0.3, SAIZ, SAIZ * 0.7);
    grad.addColorStop(0, tema.warna1 || '#f5c542');
    grad.addColorStop(1, tema.warna3 || '#ffe9a8');

    ctx.fillStyle = grad;
    ctx.font = `800 ${Math.round(SAIZ * 0.42)}px 'Plus Jakarta Sans', system-ui, sans-serif`;
    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';
    ctx.fillText(huruf, SAIZ / 2, SAIZ / 2);
  }

  /**
   * Jana ikon aplikasi untuk sesebuah kedai.
   * @param {object} config konfigurasi kedai (C dalam app.js)
   * @returns {Promise<string>} PNG sebagai data URI, atau '' kalau gagal
   */
  async function jana(config) {
    try {
      const k = (config && config.kedai) || {};
      const tema = (config && config.tema) || {};

      const kanvas = document.createElement('canvas');
      kanvas.width = kanvas.height = SAIZ;
      const ctx = kanvas.getContext('2d');
      if (!ctx) return '';

      latarBelakang(ctx, tema);

      const kotak = SAIZ * SELAMAT;

      if (k.logo) {
        const img = await muatGambar(k.logo);
        if (img && img.width && img.height) {
          /* Muat dalam zon selamat tanpa memotong atau meregangkan */
          const skala = Math.min(kotak / img.width, kotak / img.height);
          const w = img.width * skala;
          const h = img.height * skala;
          ctx.drawImage(img, (SAIZ - w) / 2, (SAIZ - h) / 2, w, h);
          return kanvas.toDataURL('image/png');
        }
      }

      if (k.logoEmoji) {
        ctx.font = `${Math.round(kotak * 0.86)}px 'Apple Color Emoji', 'Segoe UI Emoji', 'Noto Color Emoji', sans-serif`;
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        /* Garis dasar emoji tidak sekata antara platform; sedikit anjakan ke
           bawah menjadikannya nampak betul-betul di tengah. */
        ctx.fillText(k.logoEmoji, SAIZ / 2, SAIZ / 2 + kotak * 0.03);
        return kanvas.toDataURL('image/png');
      }

      lukisHuruf(ctx, k.nama, tema);
      return kanvas.toDataURL('image/png');
    } catch (e) {
      return '';
    }
  }

  /* Pasang ikon pada tab pelayar dan skrin utama iOS. iOS tidak membaca
     manifest untuk ikon, jadi <link rel="apple-touch-icon"> ialah satu-satunya
     cara ikon kedai muncul bila pengguna iPhone menekan "Add to Home Screen". */
  function pasangPautan(dataUri) {
    if (!dataUri) return;
    [['icon', 'shortcut icon'], ['apple-touch-icon', 'apple-touch-icon']].forEach(([rel]) => {
      let el = document.querySelector(`link[rel="${rel}"]`);
      if (!el) {
        el = document.createElement('link');
        el.rel = rel;
        document.head.appendChild(el);
      }
      el.href = dataUri;
    });
  }

  return { jana, pasangPautan };
})();

window.Ikon = Ikon;
