/* ==========================================================================
   OrderDyno — Logik app
   ========================================================================== */
(function () {
  'use strict';

  const $ = (s, p = document) => p.querySelector(s);
  const $$ = (s, p = document) => Array.from(p.querySelectorAll(s));
  const KUNCI_SIMPAN = 'orderdyno.cart.v1';
  const KUNCI_PELANGGAN = 'orderdyno.pelanggan.v1';

  /* ------------------------------- Utiliti ------------------------------- */
  const wang = (n) => `${KEDAI.mataWang} ${Number(n).toFixed(2)}`;

  const selamat = (s) =>
    String(s == null ? '' : s).replace(/[&<>"']/g, (c) => ({
      '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
    }[c]));

  function toast(mesej, jenis = '') {
    const el = document.createElement('div');
    el.className = 'toast' + (jenis ? ` toast--${jenis}` : '');
    el.textContent = mesej;
    $('#toast').appendChild(el);
    setTimeout(() => {
      el.style.transition = 'opacity .3s';
      el.style.opacity = '0';
      setTimeout(() => el.remove(), 320);
    }, 2200);
  }

  const cariItem = (id) => MENU.find((m) => m.id === id);
  const hargaTerendah = (item) => Math.min(...item.pilihan.map((p) => p.harga));

  /* ------------------------------ Waktu buka ----------------------------- */
  function statusKedai() {
    if (!KEDAI.waktu || !KEDAI.waktu.buka || !KEDAI.waktu.tutup) {
      return { buka: true, teks: 'Buka 24 Jam' };
    }
    const keMinit = (t) => {
      const [j, m] = t.split(':').map(Number);
      return j * 60 + m;
    };
    const kini = new Date();
    const skrg = kini.getHours() * 60 + kini.getMinutes();
    const b = keMinit(KEDAI.waktu.buka);
    const t = keMinit(KEDAI.waktu.tutup);
    const buka = b <= t ? skrg >= b && skrg < t : skrg >= b || skrg < t;
    return {
      buka,
      teks: buka
        ? `Buka sekarang · sampai ${KEDAI.waktu.tutup}`
        : `Tutup · buka ${KEDAI.waktu.buka}`,
    };
  }

  /* --------------------------------- Cart -------------------------------- */
  let cart = muatCart();

  function muatCart() {
    try {
      const data = JSON.parse(localStorage.getItem(KUNCI_SIMPAN) || '[]');
      // Buang item yang sudah tiada dalam menu (menu penjaja mungkin berubah)
      return Array.isArray(data) ? data.filter((b) => cariItem(b.itemId)) : [];
    } catch {
      return [];
    }
  }

  function simpanCart() {
    try {
      localStorage.setItem(KUNCI_SIMPAN, JSON.stringify(cart));
    } catch {
      /* mod peribadi / storan penuh — abaikan, cart kekal dalam memori */
    }
  }

  const capBaris = (b) =>
    [b.itemId, b.pilihan, [...b.tambahan].sort().join('|'), (b.nota || '').trim()].join('::');

  function hargaBaris(b) {
    const item = cariItem(b.itemId);
    if (!item) return 0;
    const pilihan = item.pilihan.find((p) => p.nama === b.pilihan) || item.pilihan[0];
    const tambahan = (item.tambahan || [])
      .filter((t) => b.tambahan.includes(t.nama))
      .reduce((j, t) => j + t.harga, 0);
    return (pilihan.harga + tambahan) * b.kuantiti;
  }

  const subJumlah = () => cart.reduce((j, b) => j + hargaBaris(b), 0);
  const bilanganCart = () => cart.reduce((j, b) => j + b.kuantiti, 0);

  function tambahKeCart(baris) {
    const sedia = cart.find((b) => capBaris(b) === capBaris(baris));
    if (sedia) sedia.kuantiti += baris.kuantiti;
    else cart.push(baris);
    simpanCart();
    kemasCart();
  }

  function ubahKuantiti(cap, delta) {
    const b = cart.find((x) => capBaris(x) === cap);
    if (!b) return;
    b.kuantiti += delta;
    if (b.kuantiti <= 0) cart = cart.filter((x) => x !== b);
    simpanCart();
    kemasCart();
    lukisCart();
  }

  function buangBaris(cap) {
    cart = cart.filter((x) => capBaris(x) !== cap);
    simpanCart();
    kemasCart();
    lukisCart();
  }

  function kemasCart() {
    const n = bilanganCart();
    $('#kiraCart').textContent = n;
    const btn = $('#btnSemak');
    btn.classList.remove('berdenyut');
    void btn.offsetWidth;
    btn.classList.add('berdenyut');

    // Tanda kad yang ada dalam cart
    $$('.kad').forEach((kad) => {
      const jum = cart
        .filter((b) => b.itemId === kad.dataset.id)
        .reduce((j, b) => j + b.kuantiti, 0);
      kad.dataset.dalamCart = jum > 0 ? '1' : '0';
      $('.kad__bilangan', kad).textContent = jum;
    });
  }

  /* ------------------------------- Render UI ----------------------------- */
  function lukisKepala() {
    $('#logo').textContent = KEDAI.logoEmoji || '🍽️';
    $('#namaKedai').textContent = KEDAI.nama;
    $('#footerNama').textContent = KEDAI.nama;
    $('#tagline').textContent = KEDAI.tagline || '';
    $('#slogan').textContent = KEDAI.slogan || '';
    document.title = `${KEDAI.nama} — Order Online`;

    const s = statusKedai();
    const lencana = $('#statusBuka');
    lencana.className = 'lencana ' + (s.buka ? 'lencana--buka' : 'lencana--tutup');
    lencana.lastElementChild.textContent = s.teks;

    if (KEDAI.alamat) {
      $('#alamat').textContent = KEDAI.alamat;
      $('#pautanLokasi').href = KEDAI.waze || '#';
    } else {
      $('#pautanLokasi').hidden = true;
    }
  }

  function lukisMenu() {
    const menu = $('#menu');
    const chips = $('#chips');

    menu.innerHTML = KATEGORI.map((kat) => {
      const items = MENU.filter((m) => m.kategori === kat.id);
      if (!items.length) return '';
      return `
        <section class="kategori" id="kat-${selamat(kat.id)}">
          <h2 class="kategori__tajuk">${kat.emoji || ''} ${selamat(kat.nama)}</h2>
          <div class="grid">${items.map(kadItem).join('')}</div>
        </section>`;
    }).join('');

    chips.innerHTML = KATEGORI.filter((k) => MENU.some((m) => m.kategori === k.id))
      .map(
        (k) =>
          `<button class="chip" type="button" data-kat="${selamat(k.id)}">${k.emoji || ''} ${selamat(k.nama)}</button>`
      )
      .join('');

    $$('.kad__btn').forEach((b) =>
      b.addEventListener('click', () => bukaSheetItem(b.closest('.kad').dataset.id))
    );
    $$('.chip').forEach((c) =>
      c.addEventListener('click', () => {
        const sasar = $(`#kat-${CSS.escape(c.dataset.kat)}`);
        if (sasar) window.scrollTo({ top: sasar.offsetTop - 120, behavior: 'smooth' });
      })
    );

    pantauSkrol();
  }

  function gambarItem(item) {
    return item.gambar
      ? `<img src="${selamat(item.gambar)}" alt="${selamat(item.nama)}" loading="lazy">`
      : lukisItem(item);
  }

  function kadItem(item) {
    const banyakPilihan = item.pilihan.length > 1;
    return `
      <article class="kad" data-id="${selamat(item.id)}" data-dalam-cart="0">
        ${item.popular ? '<span class="kad__lencana">⭐ Paling Laris</span>' : ''}
        <span class="kad__bilangan">0</span>
        <div class="kad__gambar">${gambarItem(item)}</div>
        <h3 class="kad__nama">${selamat(item.nama)}</h3>
        ${item.desc ? `<p class="kad__desc">${selamat(item.desc)}</p>` : ''}
        <p class="kad__harga">${banyakPilihan ? '<small>dari </small>' : ''}${wang(hargaTerendah(item))}</p>
        <button class="kad__btn" type="button">+ Pilih</button>
      </article>`;
  }

  /* --------------------------- Sheet: pilih item ------------------------- */
  let draf = null;

  function bukaSheetItem(id) {
    const item = cariItem(id);
    if (!item) return;
    draf = { itemId: id, pilihan: item.pilihan[0].nama, tambahan: [], kuantiti: 1, nota: '' };

    $('#tajukItem').textContent = item.nama;
    $('#badanItem').innerHTML = `
      <div class="item-hero">
        <div class="item-hero__gambar">${gambarItem(item)}</div>
        <div>
          <strong>${selamat(item.nama)}</strong>
          <p>${selamat(item.desc || '')}</p>
        </div>
      </div>

      <span class="label">Pilih saiz / jenis</span>
      <div class="pilihan">
        ${item.pilihan
          .map(
            (p, i) => `
          <label>
            <input type="radio" name="pilihan" value="${selamat(p.nama)}" ${i === 0 ? 'checked' : ''}>
            <span>${selamat(p.nama)}</span>
            <span class="harga">${wang(p.harga)}</span>
          </label>`
          )
          .join('')}
      </div>

      ${
        item.tambahan && item.tambahan.length
          ? `<span class="label">Tambahan (pilihan)</span>
             <div class="pilihan">
               ${item.tambahan
                 .map(
                   (t) => `
                 <label>
                   <input type="checkbox" name="tambahan" value="${selamat(t.nama)}">
                   <span>${selamat(t.nama)}</span>
                   <span class="harga">+ ${wang(t.harga)}</span>
                 </label>`
                 )
                 .join('')}
             </div>`
          : ''
      }

      <span class="label">Nota untuk penjaja</span>
      <textarea class="nota" id="notaItem" placeholder="Cth: kurang pedas, tanpa bawang, cili asing..."></textarea>

      <span class="label">Kuantiti</span>
      <div class="stepper">
        <button type="button" id="kurang" aria-label="Kurang">−</button>
        <span id="paparKuantiti">1</span>
        <button type="button" id="tambahQty" aria-label="Tambah">+</button>
      </div>`;

    const kemasButang = () => {
      const item2 = cariItem(draf.itemId);
      const p = item2.pilihan.find((x) => x.nama === draf.pilihan) || item2.pilihan[0];
      const t = (item2.tambahan || [])
        .filter((x) => draf.tambahan.includes(x.nama))
        .reduce((j, x) => j + x.harga, 0);
      $('#btnTambah').textContent = `Tambah ke Order · ${wang((p.harga + t) * draf.kuantiti)}`;
      $('#paparKuantiti').textContent = draf.kuantiti;
      $('#kurang').disabled = draf.kuantiti <= 1;
    };

    $$('input[name="pilihan"]', $('#badanItem')).forEach((r) =>
      r.addEventListener('change', () => {
        draf.pilihan = r.value;
        kemasButang();
      })
    );
    $$('input[name="tambahan"]', $('#badanItem')).forEach((c) =>
      c.addEventListener('change', () => {
        draf.tambahan = $$('input[name="tambahan"]:checked', $('#badanItem')).map((x) => x.value);
        kemasButang();
      })
    );
    $('#kurang').addEventListener('click', () => {
      if (draf.kuantiti > 1) draf.kuantiti--;
      kemasButang();
    });
    $('#tambahQty').addEventListener('click', () => {
      if (draf.kuantiti < 99) draf.kuantiti++;
      kemasButang();
    });

    kemasButang();
    bukaSheet('#sheetItem');
  }

  $('#btnTambah').addEventListener('click', () => {
    if (!draf) return;
    draf.nota = ($('#notaItem') && $('#notaItem').value.trim().slice(0, 200)) || '';
    tambahKeCart({ ...draf });
    tutupSheet();
    toast(`${cariItem(draf.itemId).nama} masuk cart ✓`, 'baik');
  });

  /* ------------------------------ Sheet: cart ---------------------------- */
  let pelanggan = muatPelanggan();

  function muatPelanggan() {
    try {
      return Object.assign(
        { nama: '', telefon: '', alamat: '', cara: KEDAI.pickup.aktif ? 'pickup' : 'delivery' },
        JSON.parse(localStorage.getItem(KUNCI_PELANGGAN) || '{}')
      );
    } catch {
      return { nama: '', telefon: '', alamat: '', cara: 'pickup' };
    }
  }

  function simpanPelanggan() {
    try {
      localStorage.setItem(KUNCI_PELANGGAN, JSON.stringify(pelanggan));
    } catch {
      /* abaikan */
    }
  }

  const cajHantar = () =>
    pelanggan.cara === 'delivery' && KEDAI.delivery.aktif ? KEDAI.delivery.caj || 0 : 0;

  function lukisCart() {
    const badan = $('#badanCart');
    const kaki = $('#kakiCart');

    if (!cart.length) {
      badan.innerHTML = `
        <div class="cart-kosong">
          <span class="emo">🛒</span>
          <strong>Cart masih kosong</strong>
          <p>Pilih burger, fries atau air dari menu di bawah.</p>
        </div>`;
      kaki.innerHTML = `<button class="btn-utama" type="button" data-tutup>Lihat Menu</button>`;
      kaki.querySelector('[data-tutup]').addEventListener('click', tutupSheet);
      return;
    }

    badan.innerHTML = cart
      .map((b) => {
        const item = cariItem(b.itemId);
        const cap = capBaris(b);
        const meta = [b.pilihan, ...b.tambahan].filter(Boolean).join(' · ');
        return `
        <div class="cart-item" data-cap="${selamat(cap)}">
          <div class="cart-item__gambar">${gambarItem(item)}</div>
          <div class="cart-item__isi">
            <div class="cart-item__nama">${selamat(item.nama)}</div>
            <div class="cart-item__meta">${selamat(meta)}${b.nota ? `<br>📝 ${selamat(b.nota)}` : ''}</div>
            <div class="cart-item__bawah">
              <div class="stepper">
                <button type="button" data-aksi="kurang" aria-label="Kurang">−</button>
                <span>${b.kuantiti}</span>
                <button type="button" data-aksi="tambah" aria-label="Tambah">+</button>
              </div>
              <button class="buang" type="button" data-aksi="buang">Buang</button>
              <span class="cart-item__harga">${wang(hargaBaris(b))}</span>
            </div>
          </div>
        </div>`;
      })
      .join('');

    $$('.cart-item', badan).forEach((el) => {
      const cap = el.dataset.cap;
      el.addEventListener('click', (e) => {
        const aksi = e.target.dataset.aksi;
        if (aksi === 'kurang') ubahKuantiti(cap, -1);
        else if (aksi === 'tambah') ubahKuantiti(cap, 1);
        else if (aksi === 'buang') buangBaris(cap);
      });
    });

    const sub = subJumlah();
    const hantar = cajHantar();
    const kurangMin =
      pelanggan.cara === 'delivery' &&
      KEDAI.delivery.minOrder &&
      sub < KEDAI.delivery.minOrder;
    const s = statusKedai();

    // Borang diletak dalam badan yang boleh skrol; butang checkout kekal di kaki.
    badan.insertAdjacentHTML(
      'beforeend',
      `
      <span class="label">Cara terima</span>
      <div class="pilih-cara">
        ${
          KEDAI.pickup.aktif
            ? `<label>
                 <input type="radio" name="cara" value="pickup" ${pelanggan.cara === 'pickup' ? 'checked' : ''}>
                 🏃 ${selamat(KEDAI.pickup.label)}
                 <span class="kecil">${selamat(KEDAI.pickup.nota || '')}</span>
               </label>`
            : ''
        }
        ${
          KEDAI.delivery.aktif
            ? `<label>
                 <input type="radio" name="cara" value="delivery" ${pelanggan.cara === 'delivery' ? 'checked' : ''}>
                 🛵 ${selamat(KEDAI.delivery.label)}
                 <span class="kecil">+ ${wang(KEDAI.delivery.caj)} · ${selamat(KEDAI.delivery.nota || '')}</span>
               </label>`
            : ''
        }
      </div>

      <div class="medan"><input id="fNama" type="text" placeholder="Nama anda" value="${selamat(pelanggan.nama)}" autocomplete="name"></div>
      <div class="medan"><input id="fTel" type="tel" placeholder="No. telefon (cth 0123456789)" value="${selamat(pelanggan.telefon)}" autocomplete="tel"></div>
      <div class="medan" id="medanAlamat" ${pelanggan.cara === 'delivery' ? '' : 'hidden'}>
        <textarea id="fAlamat" rows="2" placeholder="Alamat penghantaran penuh">${selamat(pelanggan.alamat)}</textarea>
      </div>

      ${
        !s.buka
          ? `<div class="amaran" style="margin-top:14px">⏰ Kedai tutup sekarang. Anda masih boleh hantar order — penjaja akan sahkan bila buka (${selamat(KEDAI.waktu.buka)}).</div>`
          : ''
      }
      ${
        kurangMin
          ? `<div class="amaran" style="margin-top:14px">🛵 Order minimum untuk penghantaran ialah ${wang(KEDAI.delivery.minOrder)}. Tambah ${wang(KEDAI.delivery.minOrder - sub)} lagi.</div>`
          : ''
      }`
    );

    kaki.innerHTML = `
      <div class="jumlah-baris"><span>Subjumlah</span><span>${wang(sub)}</span></div>
      ${hantar ? `<div class="jumlah-baris"><span>Caj penghantaran</span><span>${wang(hantar)}</span></div>` : ''}
      <div class="jumlah-baris besar"><span>Jumlah</span><span>${wang(sub + hantar)}</span></div>
      <button class="btn-utama btn-wasap" id="btnHantar" type="button" ${kurangMin ? 'disabled' : ''}>
        📲 Hantar Order ke WhatsApp
      </button>`;

    $$('input[name="cara"]', badan).forEach((r) =>
      r.addEventListener('change', () => {
        pelanggan.cara = r.value;
        simpanPelanggan();
        lukisCart();
      })
    );
    const ikat = (sel, medan) => {
      const el = $(sel, badan);
      if (el) el.addEventListener('input', () => {
        pelanggan[medan] = el.value;
        simpanPelanggan();
      });
    };
    ikat('#fNama', 'nama');
    ikat('#fTel', 'telefon');
    ikat('#fAlamat', 'alamat');

    $('#btnHantar').addEventListener('click', hantarOrder);
  }

  /* ------------------------------ Checkout ------------------------------- */
  function hantarOrder() {
    if (!cart.length) return toast('Cart kosong', 'salah');

    const nama = (pelanggan.nama || '').trim();
    const tel = (pelanggan.telefon || '').trim();
    const alamat = (pelanggan.alamat || '').trim();

    if (nama.length < 2) return silaIsi('#fNama', 'Sila isi nama anda');
    if (tel.replace(/\D/g, '').length < 9) return silaIsi('#fTel', 'Sila isi no. telefon yang betul');
    if (pelanggan.cara === 'delivery' && alamat.length < 8)
      return silaIsi('#fAlamat', 'Sila isi alamat penghantaran');

    const sub = subJumlah();
    const hantar = cajHantar();
    const cara = pelanggan.cara === 'delivery' ? KEDAI.delivery.label : KEDAI.pickup.label;
    const rujukan = 'OD' + Date.now().toString(36).toUpperCase().slice(-6);

    const baris = cart.map((b, i) => {
      const item = cariItem(b.itemId);
      const extra = b.tambahan.length ? `\n   + ${b.tambahan.join(', ')}` : '';
      const nota = b.nota ? `\n   📝 ${b.nota}` : '';
      return `${i + 1}. ${item.nama} (${b.pilihan}) x${b.kuantiti}${extra}${nota}\n   ${wang(hargaBaris(b))}`;
    });

    const mesej = [
      `*ORDER BARU — ${KEDAI.nama}*`,
      `No. Rujukan: ${rujukan}`,
      '',
      '*Pesanan:*',
      ...baris,
      '',
      `Subjumlah: ${wang(sub)}`,
      hantar ? `Penghantaran: ${wang(hantar)}` : null,
      `*JUMLAH: ${wang(sub + hantar)}*`,
      '',
      `*Cara:* ${cara}`,
      `*Nama:* ${nama}`,
      `*Telefon:* ${tel}`,
      pelanggan.cara === 'delivery' ? `*Alamat:* ${alamat}` : null,
      '',
      'Terima kasih! 🙏',
    ]
      .filter((x) => x !== null)
      .join('\n');

    const url = `https://wa.me/${KEDAI.whatsapp}?text=${encodeURIComponent(mesej)}`;
    const tab = window.open(url, '_blank', 'noopener');
    if (!tab) window.location.href = url;

    toast(`Order ${rujukan} dihantar ke WhatsApp ✓`, 'baik');
  }

  function silaIsi(sel, mesej) {
    const el = $(sel);
    if (el) {
      el.setAttribute('aria-invalid', 'true');
      el.focus();
      el.addEventListener('input', () => el.removeAttribute('aria-invalid'), { once: true });
    }
    toast(mesej, 'salah');
  }

  /* -------------------------------- Sheets ------------------------------- */
  let sheetTerbuka = null;

  function bukaSheet(sel) {
    const sheet = $(sel);
    sheet.hidden = false;
    requestAnimationFrame(() => sheet.classList.add('buka'));
    $('#tirai').classList.add('buka');
    document.body.style.overflow = 'hidden';
    sheetTerbuka = sheet;
  }

  function tutupSheet() {
    if (!sheetTerbuka) return;
    const sheet = sheetTerbuka;
    sheet.classList.remove('buka');
    $('#tirai').classList.remove('buka');
    document.body.style.overflow = '';
    sheetTerbuka = null;
    setTimeout(() => {
      if (!sheet.classList.contains('buka')) sheet.hidden = true;
    }, 340);
  }

  $('#tirai').addEventListener('click', tutupSheet);
  $$('[data-tutup]').forEach((b) => b.addEventListener('click', tutupSheet));
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') tutupSheet();
  });

  $('#btnSemak').addEventListener('click', () => {
    lukisCart();
    bukaSheet('#sheetCart');
  });

  /* ------------------------------- Scrollspy ----------------------------- */
  function pantauSkrol() {
    const seksyen = $$('.kategori');
    const chips = $$('.chip');
    if (!seksyen.length || !('IntersectionObserver' in window)) return;

    const pemerhati = new IntersectionObserver(
      (entri) => {
        entri.forEach((e) => {
          if (!e.isIntersecting) return;
          const id = e.target.id.replace('kat-', '');
          chips.forEach((c) => c.classList.toggle('aktif', c.dataset.kat === id));
        });
      },
      { rootMargin: '-180px 0px -60% 0px', threshold: 0 }
    );

    seksyen.forEach((s) => pemerhati.observe(s));
    if (chips[0]) chips[0].classList.add('aktif');
  }

  /* --------------------------------- Mula -------------------------------- */
  lukisKepala();
  lukisMenu();
  kemasCart();
  setInterval(lukisKepala, 60000); // kemas status buka/tutup setiap minit
})();
