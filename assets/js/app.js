/* ==========================================================================
   OrderDyno — Aplikasi Kedai (paparan pelanggan)
   --------------------------------------------------------------------------
   Tanggungjawab:
     • Papar maklumat kedai, kategori & menu dari tetapan
     • Sheet pilih item (saiz, add-on, kuantiti, nota)
     • Cart + kiraan jumlah + caj penghantaran
     • Hantar order sebagai mesej WhatsApp yang tersusun
   ========================================================================== */

const App = (() => {
  /* ----------------------------- Keadaan ---------------------------------- */

  let C = Store.muat();       // tetapan kedai + menu
  let CART = Store.muatCart();
  let draf = null;            // item yang sedang dipilih dalam sheet
  let caraOrder = 'pickup';
  const pelanggan = { nama: '', email: '', telefon: '', alamat: '', nota: '' };
  let saluranPilih = 0;   // saluran Bayarcash yang dipilih pelanggan

  /* ------------------------------ Pintasan -------------------------------- */

  const $ = (s) => document.querySelector(s);
  const $$ = (s) => Array.from(document.querySelectorAll(s));

  const IKON = {
    gambar: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M21 19V5a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2M8.5 11a1.5 1.5 0 1 1 1.5-1.5A1.5 1.5 0 0 1 8.5 11m10 7h-13l3.25-4.33 2.25 3 3.25-4.34z"/></svg>',
    campur: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6z"/></svg>',
    kad: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20 4H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2m0 14H4v-6h16zm0-10H4V6h16z"/></svg>',
    wa: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12.04 2C6.58 2 2.13 6.45 2.13 11.91a9.8 9.8 0 0 0 1.36 4.98L2 22l5.25-1.38a9.9 9.9 0 0 0 4.79 1.22h.01c5.46 0 9.91-4.45 9.91-9.91S17.5 2 12.04 2m5.8 14.02c-.24.68-1.42 1.31-1.96 1.36-.54.05-1.05.24-3.53-.73s-4.05-3.5-4.18-3.66c-.12-.17-.83-1.11-.83-2.12 0-1 .53-1.5.72-1.7.19-.22.41-.27.55-.27h.4c.12 0 .3-.05.46.36l.63 1.53c.05.1.09.22.02.36l-.27.4-.19.22c-.09.09-.18.19-.08.36.1.17.44.73.95 1.18.65.58 1.19.76 1.36.85.17.08.27.07.37-.05l.53-.61c.14-.17.25-.13.42-.07l1.2.57c.4.2.66.29.76.46.09.17.09.97-.15 1.65z"/></svg>',
  };

  const esc = (t) =>
    String(t == null ? '' : t).replace(/[&<>"']/g, (c) =>
      ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c])
    );

  const wang = (n) => `${C.kedai.mataWang} ${(Number(n) || 0).toFixed(2)}`;

  /* ------------------------------- Toast ---------------------------------- */

  function toast(mesej, jenis) {
    const el = document.createElement('div');
    el.className = 'toast' + (jenis ? ' ' + jenis : '');
    el.textContent = mesej;
    $('#toast').appendChild(el);
    setTimeout(() => {
      el.classList.add('keluar');
      setTimeout(() => el.remove(), 320);
    }, 2600);
  }

  /* ------------------------- Harga & pengiraan ---------------------------- */

  /* Harga paparan: julat kalau ada lebih satu pilihan */
  function hargaPapar(item) {
    if (item.pilihan && item.pilihan.length) {
      const senarai = item.pilihan.map((p) => Number(p.harga) || 0);
      const min = Math.min.apply(null, senarai);
      const max = Math.max.apply(null, senarai);
      return min === max ? wang(min) : `${wang(min)} – ${wang(max)}`;
    }
    return wang(item.harga);
  }

  function jumlahBaris(baris) {
    const tambahan = (baris.tambahan || []).reduce((j, t) => j + (Number(t.harga) || 0), 0);
    return (Number(baris.harga) + tambahan) * baris.kuantiti;
  }

  const subtotal = () => CART.reduce((j, b) => j + jumlahBaris(b), 0);

  function cajHantar() {
    const d = C.penghantaran.delivery;
    return caraOrder === 'delivery' && d.aktif ? Number(d.caj) || 0 : 0;
  }

  const bilangan = () => CART.reduce((j, b) => j + b.kuantiti, 0);

  /* ============================ PAPARAN ================================== */

  function pakaiTema() {
    const t = C.tema || {};
    const r = document.documentElement.style;
    r.setProperty('--c1', t.warna1 || '#a855f7');
    r.setProperty('--c2', t.warna2 || '#ff4d94');
    r.setProperty('--c3', t.warna3 || '#ff9a3c');
    r.setProperty('--latar', t.latar || '#0b0616');
    const meta = document.querySelector('meta[name=theme-color]');
    if (meta) meta.content = t.latar || '#0b0616';
  }

  function paparKedai() {
    const k = C.kedai;

    document.title = `${k.nama} — Menu Online`;
    $('#namaKedai').textContent = k.nama;
    $('#footerNama').textContent = k.nama;
    $('#tagline').textContent = k.tagline;
    $('#tagline').hidden = !k.tagline;
    $('#tahun').textContent = new Date().getFullYear();

    // Logo
    const logo = $('#logo');
    if (k.logo) {
      logo.classList.remove('kosong');
      logo.innerHTML = `<img src="${esc(k.logo)}" alt="${esc(k.nama)}">`;
    } else if (k.logoEmoji) {
      logo.classList.remove('kosong');
      logo.textContent = k.logoEmoji;
    } else {
      logo.classList.add('kosong');
      logo.innerHTML = IKON.gambar;
    }

    // Butang WhatsApp
    const wa = $('#btnWa');
    if (k.whatsapp) {
      wa.href = `https://wa.me/${String(k.whatsapp).replace(/\D/g, '')}`;
      wa.hidden = false;
    } else {
      wa.hidden = true;
    }

    // Butang Order Now — kalau tiada pautan, ia scroll ke menu
    const on = $('#btnOrderNow');
    $('#labelOrderNow').textContent = k.orderNow.label || 'Order Now';
    if (k.orderNow.pautan) {
      on.href = k.orderNow.pautan;
      on.target = '_blank';
      on.rel = 'noopener';
      on.querySelector('.btn__luar').hidden = false;
    } else {
      on.href = '#menu';
      on.removeAttribute('target');
      on.querySelector('.btn__luar').hidden = true;
    }

    paparStatusBuka();

    // Kad info footer
    $('#alamat').textContent = k.alamat || '—';
    $('#waktu').textContent = k.waktu || '—';
    $('#telefon').textContent = k.telefon || '—';

    const kadLokasi = $('#kadLokasi');
    if (k.pautanLokasi) {
      kadLokasi.href = k.pautanLokasi;
      kadLokasi.target = '_blank';
    } else {
      kadLokasi.removeAttribute('href');
      kadLokasi.removeAttribute('target');
    }

    const kadTel = $('#kadTelefon');
    if (k.telefon) kadTel.href = 'tel:' + String(k.telefon).replace(/[^\d+]/g, '');
    else kadTel.removeAttribute('href');

    // Butang Edit boleh disembunyikan dari pelanggan (#edit masih boleh buka)
    $('#btnEdit').hidden = !!k.sembunyikanEdit && !location.hash.includes('edit');
  }

  function paparChips() {
    const guna = C.kategori.filter((kat) => C.menu.some((m) => m.kategori === kat.id));
    $('#chips').innerHTML =
      guna.length > 1
        ? guna.map((kat) => `<a class="chip" href="#kat-${esc(kat.id)}">${esc(kat.nama)}</a>`).join('')
        : '';
  }

  function kadItem(item) {
    const gambar = item.gambar
      ? `<div class="kad__gambar ada"><img src="${esc(item.gambar)}" alt="${esc(item.nama)}" loading="lazy"></div>`
      : item.emoji
      ? `<div class="kad__gambar">${esc(item.emoji)}</div>`
      : `<div class="kad__gambar">${IKON.gambar}</div>`;

    const lencana = item.habis
      ? '<span class="lencana lencana--habis">Habis</span>'
      : item.popular
      ? '<span class="lencana">Popular</span>'
      : '';

    return `
      <article class="kad${item.habis ? ' habis' : ''}" data-id="${esc(item.id)}">
        ${lencana}
        ${gambar}
        <div class="kad__isi">
          <h3 class="kad__nama">${esc(item.nama)}</h3>
          ${item.desc ? `<p class="kad__desc">${esc(item.desc)}</p>` : ''}
          <div class="kad__harga">${hargaPapar(item)}</div>
        </div>
        <button class="kad__tambah" type="button" data-tambah="${esc(item.id)}"
                aria-label="Tambah ${esc(item.nama)}" ${item.habis ? 'disabled' : ''}>
          ${IKON.campur}
        </button>
      </article>`;
  }

  function paparMenu() {
    const wadah = $('#menu');

    if (!C.menu.length) {
      wadah.innerHTML = `
        <div class="kosong-nota">
          <h3>Menu masih kosong</h3>
          <p>Tekan butang <b>Edit Menu</b> di penjuru atas untuk masukkan kategori dan item menu kedai anda.</p>
          <button class="btn-kecil btn-kecil--utama" type="button" data-buka-editor="menu">Isi Menu Sekarang</button>
        </div>`;
      return;
    }

    wadah.innerHTML = C.kategori
      .map((kat) => {
        const item = C.menu.filter((m) => m.kategori === kat.id);
        if (!item.length) return '';
        return `
          <section class="kategori" id="kat-${esc(kat.id)}">
            <div class="kategori__kepala">
              <h2 class="kategori__nama">${esc(kat.nama)}</h2>
              <span class="kategori__garis"></span>
            </div>
            <div class="grid">${item.map(kadItem).join('')}</div>
          </section>`;
      })
      .join('');

    animasiMasuk();
    intaiKategori();
  }

  /* Kad muncul beransur bila di-scroll.
     Semasa panel editor terbuka, kad terus dipaparkan supaya menu tak
     berkelip setiap kali pemilik kedai menaip. */
  function animasiMasuk() {
    const kad = $$('.kad');
    const sedangEdit = !!document.querySelector('.ed.buka');
    if (sedangEdit || !('IntersectionObserver' in window)) {
      kad.forEach((k) => k.classList.add('masuk'));
      return;
    }
    const io = new IntersectionObserver(
      (masukan) => {
        masukan.forEach((m) => {
          if (!m.isIntersecting) return;
          const i = Array.from(m.target.parentElement.children).indexOf(m.target);
          setTimeout(() => m.target.classList.add('masuk'), Math.min(i, 6) * 70);
          io.unobserve(m.target);
        });
      },
      { rootMargin: '0px 0px -8% 0px', threshold: 0.05 }
    );
    kad.forEach((k) => io.observe(k));
  }

  /* Chip kategori aktif ikut kedudukan scroll */
  function intaiKategori() {
    if (!('IntersectionObserver' in window)) return;
    const io = new IntersectionObserver(
      (masukan) => {
        masukan.forEach((m) => {
          if (!m.isIntersecting) return;
          $$('.chip').forEach((c) =>
            c.classList.toggle('aktif', c.getAttribute('href') === '#' + m.target.id)
          );
        });
      },
      { rootMargin: '-30% 0px -60% 0px' }
    );
    $$('.kategori').forEach((s) => io.observe(s));
  }

  let kiraTerakhir = -1;

  function paparKira() {
    const n = bilangan();
    $('#kiraCart').textContent = n;
    // Animasi 'loncat' hanya bila bilangan benar-benar berubah
    if (n && n !== kiraTerakhir && kiraTerakhir !== -1) {
      const b = $('#btnSemak');
      b.classList.remove('loncat');
      void b.offsetWidth;
      b.classList.add('loncat');
    }
    kiraTerakhir = n;
  }

  /* ========================= SHEET (LAPISAN) ============================= */

  function bukaSheet(id) {
    const sheet = $(id);
    sheet.hidden = false;
    $('#tirai').classList.add('buka');
    document.body.classList.add('beku');
    requestAnimationFrame(() => sheet.classList.add('buka'));
  }

  function tutupSheet() {
    $$('.sheet').forEach((s) => {
      if (!s.classList.contains('buka')) return;
      s.classList.remove('buka');
      setTimeout(() => {
        s.hidden = true;
      }, 420);
    });
    $('#tirai').classList.remove('buka');
    if (!document.querySelector('.ed.buka')) document.body.classList.remove('beku');
  }

  /* ======================= SHEET: PILIH ITEM ============================= */

  function bukaItem(id) {
    const item = C.menu.find((m) => m.id === id);
    if (!item || item.habis) return;

    draf = {
      item,
      pilihan: item.pilihan && item.pilihan.length ? 0 : null,
      tambahan: [],
      kuantiti: 1,
    };

    $('#tajukItem').textContent = item.nama;

    const gambar = item.gambar
      ? `<div class="sh-gambar"><img src="${esc(item.gambar)}" alt="${esc(item.nama)}"></div>`
      : item.emoji
      ? `<div class="sh-gambar">${esc(item.emoji)}</div>`
      : `<div class="sh-gambar">${IKON.gambar}</div>`;

    const blokPilihan =
      item.pilihan && item.pilihan.length
        ? `<div class="blok">
             <p class="blok__tajuk">Pilihan <em>Wajib</em></p>
             ${item.pilihan
               .map(
                 (p, i) => `
               <label class="opsyen${i === 0 ? ' pilih' : ''}">
                 <input type="radio" name="pilihan" value="${i}"${i === 0 ? ' checked' : ''}>
                 <span class="opsyen__nama">${esc(p.nama)}</span>
                 <span class="opsyen__harga">${wang(p.harga)}</span>
               </label>`
               )
               .join('')}
           </div>`
        : '';

    const blokTambahan =
      item.tambahan && item.tambahan.length
        ? `<div class="blok">
             <p class="blok__tajuk">Tambahan <em>Pilihan</em></p>
             ${item.tambahan
               .map(
                 (t, i) => `
               <label class="opsyen">
                 <input type="checkbox" name="tambahan" value="${i}">
                 <span class="opsyen__nama">${esc(t.nama)}</span>
                 <span class="opsyen__harga">+ ${wang(t.harga)}</span>
               </label>`
               )
               .join('')}
           </div>`
        : '';

    $('#badanItem').innerHTML = `
      ${gambar}
      ${item.desc ? `<p class="sh-desc">${esc(item.desc)}</p>` : ''}
      ${blokPilihan}
      ${blokTambahan}
      <div class="blok">
        <p class="blok__tajuk">Nota untuk kedai</p>
        <textarea class="medan" id="notaItem" placeholder="Contoh: kurang manis, tanpa cili…"></textarea>
      </div>
      <div class="baris-jumlah">
        <div class="stepper">
          <button type="button" data-kuantiti="-1" aria-label="Kurang">−</button>
          <span id="paparKuantiti">1</span>
          <button type="button" data-kuantiti="1" aria-label="Tambah">+</button>
        </div>
        <b id="paparJumlah"></b>
      </div>`;

    kemasJumlahItem();
    bukaSheet('#sheetItem');
  }

  function hargaAsas() {
    if (!draf) return 0;
    const it = draf.item;
    return draf.pilihan !== null && it.pilihan[draf.pilihan]
      ? Number(it.pilihan[draf.pilihan].harga) || 0
      : Number(it.harga) || 0;
  }

  function kemasJumlahItem() {
    if (!draf) return;
    const tambahan = draf.tambahan.reduce(
      (j, i) => j + (Number(draf.item.tambahan[i].harga) || 0),
      0
    );
    const el = $('#paparJumlah');
    if (el) el.textContent = wang((hargaAsas() + tambahan) * draf.kuantiti);
    const kq = $('#paparKuantiti');
    if (kq) kq.textContent = draf.kuantiti;
  }

  function masukCart() {
    if (!draf) return;
    const it = draf.item;
    const nota = ($('#notaItem') && $('#notaItem').value.trim()) || '';

    const baris = {
      id: it.id,
      nama: it.nama,
      variasi:
        draf.pilihan !== null && it.pilihan[draf.pilihan] ? it.pilihan[draf.pilihan].nama : '',
      harga: hargaAsas(),
      tambahan: draf.tambahan.map((i) => ({
        nama: it.tambahan[i].nama,
        harga: Number(it.tambahan[i].harga) || 0,
      })),
      nota,
      kuantiti: draf.kuantiti,
    };

    // Gabung dengan baris serupa supaya cart kekal ringkas
    const tanda = (b) =>
      [b.id, b.variasi, b.nota, b.tambahan.map((t) => t.nama).sort().join('|')].join('§');
    const sama = CART.find((b) => tanda(b) === tanda(baris));
    if (sama) sama.kuantiti += baris.kuantiti;
    else CART.push(baris);

    Store.simpanCart(CART);
    paparKira();
    tutupSheet();
    toast(`${baris.nama} ditambah ✓`, 'baik');
  }

  /* ============================== CART =================================== */

  function bukaCart() {
    paparCart();
    bukaSheet('#sheetCart');
  }

  function paparCart() {
    const badan = $('#badanCart');
    const kaki = $('#kakiCart');

    if (!CART.length) {
      badan.innerHTML = `
        <div style="text-align:center;padding:38px 10px">
          <div style="font-size:2.6rem;margin-bottom:12px">🛒</div>
          <p style="margin:0;color:var(--lemah);font-size:.92rem">Order anda masih kosong.<br>Pilih menu dahulu ya.</p>
        </div>`;
      kaki.innerHTML = '';
      return;
    }

    const d = C.penghantaran.delivery;
    const p = C.penghantaran.pickup;
    const st = subtotal();
    const caj = cajHantar();
    const kurang = caraOrder === 'delivery' && Number(d.minOrder) > 0 && st < Number(d.minOrder);

    const blokCara =
      p.aktif && d.aktif
        ? `<div class="blok">
             <p class="blok__tajuk">Cara terima order</p>
             <div class="pil-baris">
               <button class="pil${caraOrder === 'pickup' ? ' aktif' : ''}" type="button" data-cara="pickup">${esc(p.label)}</button>
               <button class="pil${caraOrder === 'delivery' ? ' aktif' : ''}" type="button" data-cara="delivery">${esc(d.label)}</button>
             </div>
             ${
               (caraOrder === 'pickup' ? p.nota : d.nota)
                 ? `<p class="f__nota">${esc(caraOrder === 'pickup' ? p.nota : d.nota)}</p>`
                 : ''
             }
           </div>`
        : '';

    badan.innerHTML = `
      ${CART.map(
        (b, i) => `
        <div class="cart-baris">
          <div class="cart-baris__isi">
            <div class="cart-baris__nama">${esc(b.nama)}${
          b.variasi ? ` <span style="color:var(--lemah);font-weight:600">· ${esc(b.variasi)}</span>` : ''
        }</div>
            ${b.tambahan.length ? `<div class="cart-baris__nota">+ ${b.tambahan.map((t) => esc(t.nama)).join(', ')}</div>` : ''}
            ${b.nota ? `<div class="cart-baris__nota">📝 ${esc(b.nota)}</div>` : ''}
            <div class="cart-baris__bawah">
              <div class="stepper">
                <button type="button" data-cart="-1" data-i="${i}" aria-label="Kurang">−</button>
                <span>${b.kuantiti}</span>
                <button type="button" data-cart="1" data-i="${i}" aria-label="Tambah">+</button>
              </div>
              <span class="cart-baris__harga">${wang(jumlahBaris(b))}</span>
            </div>
          </div>
        </div>`
      ).join('')}

      <div class="jumlah-blok">
        <div class="jumlah-baris"><span>Subtotal</span><span>${wang(st)}</span></div>
        ${caj ? `<div class="jumlah-baris"><span>${esc(d.label)}</span><span>${wang(caj)}</span></div>` : ''}
        <div class="jumlah-baris besar"><span>Jumlah</span><span>${wang(st + caj)}</span></div>
      </div>

      ${blokCara}

      ${blokSaluran()}

      <div class="blok">
        <p class="blok__tajuk">Maklumat anda</p>
        <div class="f"><input class="medan" id="cNama" placeholder="Nama anda" value="${esc(pelanggan.nama)}"></div>
        <div class="f"><input class="medan" id="cTel" type="tel" placeholder="Nombor telefon" value="${esc(pelanggan.telefon)}"></div>
        ${
          bolehBayar()
            ? `<div class="f"><input class="medan" id="cEmail" type="email" placeholder="Alamat emel (untuk resit pembayaran)" value="${esc(pelanggan.email)}"></div>`
            : ''
        }
        ${
          caraOrder === 'delivery'
            ? `<div class="f"><textarea class="medan" id="cAlamat" placeholder="Alamat penghantaran penuh">${esc(pelanggan.alamat)}</textarea></div>`
            : ''
        }
        <div class="f"><textarea class="medan" id="cNota" placeholder="Nota keseluruhan order (pilihan)">${esc(pelanggan.nota)}</textarea></div>
      </div>

      ${C.kedai.nota ? `<div class="amaran">${esc(C.kedai.nota)}</div>` : ''}
      ${
        kurang
          ? `<div class="amaran">Minimum order untuk ${esc(d.label)} ialah ${wang(d.minOrder)}. Tambah ${wang(Number(d.minOrder) - st)} lagi.</div>`
          : ''
      }`;

    /* Kedai tutup — tunjuk sebabnya di tempat pelanggan akan menekan */
    const sb = status();
    if (!sb.buka) {
      kaki.innerHTML = `
        <div class="amaran" style="margin-bottom:12px">
          <b>🌙 ${esc(sb.mesej)}</b>
          ${sb.seterusnya ? `<br>Buka semula ${esc(sb.seterusnya)}.` : ''}
          <br>Order anda kekal dalam cart — hantar bila kedai buka.
        </div>
        <button class="cart-buang" type="button" data-kosongkan
                style="display:block;margin:0 auto">Kosongkan order</button>`;
      return;
    }

    const butangBayar = bolehBayar()
      ? `<button class="btn-blok" id="btnBayar" type="button"${kurang ? ' disabled' : ''}>
           ${IKON.kad} Bayar Online — ${wang(st + caj)}
         </button>`
      : '';

    kaki.innerHTML = `
      ${butangBayar}
      <button class="btn-blok btn-hantar${butangBayar ? ' btn-blok--kedua' : ''}" id="btnHantar" type="button"${kurang ? ' disabled' : ''}>
        ${IKON.wa} ${butangBayar ? 'Order dulu, bayar kemudian' : 'Hantar Order via WhatsApp'}
      </button>
      <button class="cart-buang" type="button" data-kosongkan
              style="display:block;margin:12px auto 0">Kosongkan order</button>`;
  }

  /* ============================ WAKTU OPERASI ===========================
     Bila kedai tutup, pelanggan masih boleh melihat menu dan mengisi cart —
     mereka cuma tidak boleh menghantar order. Itu sengaja: ramai pelanggan
     melihat menu malam dan order keesokan paginya.
     ==================================================================== */

  let statusTerakhir = null;

  function status() {
    statusTerakhir = Store.statusBuka(C);
    return statusTerakhir;
  }

  function paparStatusBuka() {
    const st = status();
    let jalur = document.getElementById('jalurTutup');

    if (st.buka) {
      if (jalur) jalur.remove();
      document.body.classList.remove('kedai-tutup');
      return;
    }

    document.body.classList.add('kedai-tutup');
    if (!jalur) {
      jalur = document.createElement('div');
      jalur.id = 'jalurTutup';
      jalur.className = 'jalur-tutup';
      jalur.setAttribute('role', 'status');
      document.body.insertBefore(jalur, document.body.firstChild);
    }
    jalur.innerHTML =
      '<b>🌙 ' + esc(st.mesej) + '</b>' +
      (st.seterusnya ? '<span>Buka semula ' + esc(st.seterusnya) + '</span>' : '');

    ukurJalur();
  }

  /* Butang "Edit Menu" ialah position:fixed, jadi ia tidak bergerak bila
     jalur muncul dan keduanya bertindih. Ukur tinggi jalur dan turunkan
     butang itu — tingginya berubah ikut panjang mesej, jadi ia mesti
     diukur, bukan diteka. */
  function ukurJalur() {
    const jalur = document.getElementById('jalurTutup');
    const tinggi = jalur ? jalur.offsetHeight : 0;
    document.documentElement.style.setProperty('--tinggi-jalur', tinggi + 'px');
  }

  /* Semak setiap minit supaya kedai "bangun" sendiri bila sampai waktunya,
     tanpa pelanggan perlu refresh. */
  function mulaPengawasWaktu() {
    setInterval(() => {
      const sebelum = statusTerakhir && statusTerakhir.buka;
      const kini = Store.statusBuka(C).buka;
      if (sebelum !== kini) {
        paparStatusBuka();
        if (document.getElementById('sheetCart') && !$('#sheetCart').hidden) lukisCart();
      }
    }, 60000);
  }

  /* Pembayaran online tersedia? */
  function bolehBayar() {
    return typeof Bayar !== 'undefined' && Bayar.sedia();
  }

  /* Pemilih saluran pembayaran (FPX, DuitNow, …) */
  function blokSaluran() {
    if (!bolehBayar()) return '';

    const senarai = Bayar.keadaan().saluran;
    if (!saluranPilih && senarai.length) saluranPilih = senarai[0].kod;

    /* Laman demo perlu amaran yang lebih tegas daripada sandbox: di sini
       tiada bank langsung, jadi jangan sesekali biar ia disangka sebenar. */
    const k = Bayar.keadaan();
    const amaranSandbox = k.demo
      ? '<div class="amaran">Laman demo — pembayaran ini ditiru sepenuhnya. Tiada duit bergerak dan tiada bank terlibat.</div>'
      : k.sandbox
      ? '<div class="amaran">Mod ujian (sandbox) — pembayaran tidak melibatkan duit sebenar.</div>'
      : '';

    if (senarai.length < 2) {
      return amaranSandbox
        ? `<div class="blok">${amaranSandbox}</div>`
        : '';
    }

    return `<div class="blok">
      <p class="blok__tajuk">Cara bayar</p>
      <div class="pil-baris">
        ${senarai
          .map(
            (s) => `<button class="pil${s.kod === saluranPilih ? ' aktif' : ''}" type="button" data-saluran="${s.kod}">${esc(s.nama)}</button>`
          )
          .join('')}
      </div>
      ${amaranSandbox}
    </div>`;
  }

  function ubahKuantiti(i, delta) {
    const b = CART[i];
    if (!b) return;
    b.kuantiti += delta;
    if (b.kuantiti < 1) CART.splice(i, 1);
    Store.simpanCart(CART);
    paparKira();
    paparCart();
  }

  function simpanMedanPelanggan() {
    if ($('#cNama')) pelanggan.nama = $('#cNama').value;
    if ($('#cEmail')) pelanggan.email = $('#cEmail').value;
    if ($('#cTel')) pelanggan.telefon = $('#cTel').value;
    if ($('#cAlamat')) pelanggan.alamat = $('#cAlamat').value;
    if ($('#cNota')) pelanggan.nota = $('#cNota').value;
  }

  /* ========================= HANTAR KE WHATSAPP ========================== */

  function binaMesej() {
    const d = C.penghantaran.delivery;
    const p = C.penghantaran.pickup;
    const st = subtotal();
    const caj = cajHantar();
    const g = [];

    g.push(`*ORDER BARU — ${C.kedai.nama}*`);
    g.push('');

    CART.forEach((b, i) => {
      g.push(`${i + 1}. *${b.nama}*${b.variasi ? ` (${b.variasi})` : ''} × ${b.kuantiti}`);
      if (b.tambahan.length) g.push(`    + ${b.tambahan.map((t) => t.nama).join(', ')}`);
      if (b.nota) g.push(`    _${b.nota}_`);
      g.push(`    ${wang(jumlahBaris(b))}`);
    });

    g.push('');
    g.push(`Subtotal: ${wang(st)}`);
    if (caj) g.push(`${d.label}: ${wang(caj)}`);
    g.push(`*JUMLAH: ${wang(st + caj)}*`);
    g.push('');
    g.push(`Cara: ${caraOrder === 'delivery' ? d.label : p.label}`);
    if (pelanggan.nama) g.push(`Nama: ${pelanggan.nama}`);
    if (pelanggan.telefon) g.push(`Telefon: ${pelanggan.telefon}`);
    if (caraOrder === 'delivery' && pelanggan.alamat) g.push(`Alamat: ${pelanggan.alamat}`);
    if (pelanggan.nota) g.push(`Nota: ${pelanggan.nota}`);

    return g.join('\n');
  }

  function hantarOrder() {
    simpanMedanPelanggan();

    if (!CART.length) return toast('Order masih kosong', 'silap');
    if (!pelanggan.nama.trim()) {
      toast('Isi nama anda dahulu', 'silap');
      if ($('#cNama')) $('#cNama').focus();
      return;
    }
    if (!pelanggan.telefon.trim()) {
      toast('Isi nombor telefon anda', 'silap');
      if ($('#cTel')) $('#cTel').focus();
      return;
    }
    if (caraOrder === 'delivery' && !pelanggan.alamat.trim()) {
      toast('Isi alamat penghantaran', 'silap');
      if ($('#cAlamat')) $('#cAlamat').focus();
      return;
    }

    const no = String(C.kedai.whatsapp || '').replace(/\D/g, '');
    if (!no) return toast('Kedai belum set nombor WhatsApp', 'silap');

    window.open(`https://wa.me/${no}?text=${encodeURIComponent(binaMesej())}`, '_blank', 'noopener');
    toast('WhatsApp dibuka — tekan hantar ya!', 'baik');
  }

  /* ========================== BAYAR ONLINE ============================== */

  async function bayarOnline() {
    simpanMedanPelanggan();

    if (!CART.length) return toast('Order masih kosong', 'silap');
    if (!pelanggan.nama.trim()) {
      toast('Isi nama anda dahulu', 'silap');
      if ($('#cNama')) $('#cNama').focus();
      return;
    }
    if (!pelanggan.telefon.trim()) {
      toast('Isi nombor telefon anda', 'silap');
      if ($('#cTel')) $('#cTel').focus();
      return;
    }
    // Bayarcash memerlukan emel yang sah untuk resit
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(pelanggan.email.trim())) {
      toast('Isi alamat emel yang sah untuk resit', 'silap');
      if ($('#cEmail')) $('#cEmail').focus();
      return;
    }
    if (caraOrder === 'delivery' && !pelanggan.alamat.trim()) {
      toast('Isi alamat penghantaran', 'silap');
      if ($('#cAlamat')) $('#cAlamat').focus();
      return;
    }

    const btn = $('#btnBayar');
    if (btn) {
      btn.disabled = true;
      btn.textContent = 'Menyediakan pembayaran…';
    }

    try {
      await Bayar.bayar({
        cart: CART,
        cara: caraOrder,
        saluran: saluranPilih,
        pelanggan: {
          nama: pelanggan.nama.trim(),
          email: pelanggan.email.trim(),
          telefon: pelanggan.telefon.trim(),
          alamat: pelanggan.alamat.trim(),
          nota: pelanggan.nota.trim(),
        },
      });
      // Berjaya → pelayar sedang redirect ke Bayarcash
    } catch (e) {
      toast(e.message || 'Gagal mulakan pembayaran', 'silap');
      paparCart();
    }
  }

  /* ============================== PERISTIWA ============================== */

  function pasangPeristiwa() {
    document.addEventListener('click', (e) => {
      const bukaEd = e.target.closest('[data-buka-editor]');
      if (bukaEd && window.Editor) {
        Editor.buka(bukaEd.dataset.bukaEditor);
        return;
      }
      const btnTambah = e.target.closest('[data-tambah]');
      if (btnTambah) {
        bukaItem(btnTambah.dataset.tambah);
        return;
      }
      const kad = e.target.closest('.kad');
      if (kad && !kad.classList.contains('habis')) {
        bukaItem(kad.dataset.id);
        return;
      }
      if (e.target.closest('[data-tutup]') || e.target.id === 'tirai') tutupSheet();
    });

    $('#btnSemak').addEventListener('click', bukaCart);
    $('#btnTambah').addEventListener('click', masukCart);

    // Sheet item: pilihan, tambahan, kuantiti
    $('#sheetItem').addEventListener('change', (e) => {
      if (!draf) return;
      if (e.target.name === 'pilihan') {
        draf.pilihan = Number(e.target.value);
        $$('#sheetItem .opsyen').forEach((o) => {
          const inp = o.querySelector('input[name=pilihan]');
          if (inp) o.classList.toggle('pilih', inp.checked);
        });
      }
      if (e.target.name === 'tambahan') {
        const i = Number(e.target.value);
        if (e.target.checked) draf.tambahan.push(i);
        else draf.tambahan = draf.tambahan.filter((x) => x !== i);
        e.target.closest('.opsyen').classList.toggle('pilih', e.target.checked);
      }
      kemasJumlahItem();
    });

    $('#sheetItem').addEventListener('click', (e) => {
      const b = e.target.closest('[data-kuantiti]');
      if (!b || !draf) return;
      draf.kuantiti = Math.max(1, draf.kuantiti + Number(b.dataset.kuantiti));
      kemasJumlahItem();
    });

    // Sheet cart
    $('#sheetCart').addEventListener('click', (e) => {
      const q = e.target.closest('[data-cart]');
      if (q) {
        simpanMedanPelanggan();
        ubahKuantiti(Number(q.dataset.i), Number(q.dataset.cart));
        return;
      }
      const cara = e.target.closest('[data-cara]');
      if (cara) {
        simpanMedanPelanggan();
        caraOrder = cara.dataset.cara;
        paparCart();
        return;
      }
      if (e.target.closest('[data-kosongkan]')) {
        CART = [];
        Store.simpanCart(CART);
        paparKira();
        paparCart();
        toast('Order dikosongkan');
        return;
      }
      const sal = e.target.closest('[data-saluran]');
      if (sal) {
        simpanMedanPelanggan();
        saluranPilih = Number(sal.dataset.saluran);
        paparCart();
        return;
      }
      if (e.target.closest('#btnBayar')) {
        bayarOnline();
        return;
      }
      if (e.target.closest('#btnHantar')) hantarOrder();
    });

    // Kekalkan input pelanggan walaupun cart di-render semula
    $('#sheetCart').addEventListener('input', (e) => {
      if (e.target.classList.contains('medan')) simpanMedanPelanggan();
    });

    $('#chips').addEventListener('click', (e) => {
      const chip = e.target.closest('.chip');
      if (!chip) return;
      $$('.chip').forEach((c) => c.classList.toggle('aktif', c === chip));
    });

    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') tutupSheet();
    });
  }

  /* ============================== API ==================================== */

  /* Dipanggil oleh editor.js setiap kali tetapan berubah */
  function gunaConfig(baru, simpan) {
    C = baru;
    if (simpan !== false) Store.simpan(C);

    // Buang item cart yang dah tak ada dalam menu
    const idSah = C.menu.map((m) => m.id);
    const asal = CART.length;
    CART = CART.filter((b) => idSah.indexOf(b.id) !== -1);
    if (CART.length !== asal) Store.simpanCart(CART);

    // Kalau cara order aktif dimatikan, jatuh balik ke yang aktif
    if (caraOrder === 'delivery' && !C.penghantaran.delivery.aktif) caraOrder = 'pickup';
    if (caraOrder === 'pickup' && !C.penghantaran.pickup.aktif && C.penghantaran.delivery.aktif) {
      caraOrder = 'delivery';
    }

    render();
  }

  function render() {
    pakaiTema();
    paparKedai();
    paparChips();
    paparMenu();
    paparKira();
  }

  function mula() {
    pasangPeristiwa();

    if (!C.penghantaran.pickup.aktif && C.penghantaran.delivery.aktif) caraOrder = 'delivery';

    render();

    if (Store.dariLink()) toast('Menu dikongsi berjaya dimuatkan 👋');
    if (location.hash.indexOf('edit') !== -1) {
      setTimeout(() => window.Editor && Editor.buka(), 400);
    }

    mulaPengawasWaktu();
    window.addEventListener('resize', ukurJalur);

    // Semak sama ada backend pembayaran tersedia (senyap kalau tiada)
    if (window.Bayar) Bayar.mula();
  }

  function kosongkanCart() {
    CART = [];
    Store.simpanCart(CART);
    paparKira();
  }

  return {
    mula,
    render,
    gunaConfig,
    toast,
    tutupSheet,
    bukaCart,
    kosongkanCart,
    paparCartSemula: paparCart,
    bukaSheetHasil: () => bukaSheet('#sheetHasil'),
    config: () => C,
    wang,
    esc,
  };
})();

window.App = App;

document.addEventListener('DOMContentLoaded', App.mula);
