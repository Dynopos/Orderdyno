/* ==========================================================================
   OrderDyno — Pembayaran Online (Bayarcash)
   --------------------------------------------------------------------------
   Modul ini bercakap dengan backend PHP dalam folder `api/`. Ia tidak pernah
   memegang Personal Access Token atau secret key — semuanya di server.

   Kalau `api/status.php` tidak dijumpai (contoh: hosting statik seperti
   GitHub Pages), modul ini berdiam diri dan kedai terus berfungsi dengan
   checkout WhatsApp seperti biasa.
   ========================================================================== */

const Bayar = (() => {
  const API = 'api/';

  let keadaan = {
    backend: false,   // ada PHP backend?
    siap: false,      // kredensial + menu lengkap?
    saluran: [],
    sandbox: true,
    demo: false,      // laman demo — pembayaran ditiru, tiada Bayarcash
    mesej: '',
  };

  let tinjau = null;  // pemasa polling status

  const $ = (s) => document.querySelector(s);

  /* ============================== HTTP =================================== */

  async function dapat(laluan) {
    const r = await fetch(API + laluan, { headers: { Accept: 'application/json' } });
    const data = await r.json().catch(() => ({}));
    if (!r.ok) throw new Error(data.mesej || `HTTP ${r.status}`);
    return data;
  }

  async function hantar(laluan, badan) {
    const r = await fetch(API + laluan, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      body: JSON.stringify(badan),
    });
    const data = await r.json().catch(() => ({}));
    if (!r.ok) throw new Error(data.mesej || `HTTP ${r.status}`);
    return data;
  }

  /* ============================== MULA ================================== */

  async function mula() {
    try {
      const d = await dapat('status.php');

      /* Hosting statik boleh memulangkan fail PHP sebagai teks mentah dengan
         status 200. Kalau jawapan bukan JSON kita, anggap tiada backend. */
      if (!d || d.backend !== true) {
        keadaan.backend = false;
        keadaan.siap = false;
        return;
      }

      keadaan = {
        backend: true,
        siap: !!d.siap,
        saluran: Array.isArray(d.saluran) ? d.saluran : [],
        sandbox: !!d.sandbox,
        demo: !!d.demo,
        mesej: d.mesej || '',
        adaConfig: !!d.adaConfig,
        adaKunciAdmin: !!d.adaKunciAdmin,
      };
    } catch (e) {
      // Tiada PHP, atau api/ tidak diupload — bukan ralat, cuma tiada pembayaran
      keadaan.backend = false;
      keadaan.siap = false;
    }

    // Kalau cart sedang terbuka, papar semula supaya butang bayar muncul
    if (document.querySelector('#sheetCart.buka')) App.paparCartSemula();

    await muatMenuAwam();
    periksaPulangan();
  }

  /* ======================= MENU YANG DITERBITKAN ======================== */

  /*
   * Muat menu yang pemilik kedai terbitkan melalui "Terbitkan menu".
   *
   * Hanya digunakan bila pelayar ini TIADA tetapan tersimpan — iaitu
   * pelanggan biasa. Pelayar pemilik kedai menyimpan drafnya sendiri dalam
   * localStorage, jadi draf itu kekal menang; panel Bayaran yang memberitahu
   * pemilik bila draf berbeza dengan yang diterbitkan.
   *
   * Menu ini sengaja TIDAK disimpan ke localStorage. Kalau disimpan, salinan
   * itu akan menang selama-lamanya dan pelanggan tidak akan nampak kemas
   * kini menu yang seterusnya.
   */
  async function muatMenuAwam() {
    if (!keadaan.backend) return;          // tiada server — tiada apa nak muat
    if (Store.dariLink()) return;          // link kongsi menang
    if (Store.adaTersimpan()) return;      // pelayar pemilik — hormati drafnya

    try {
      const d = await dapat('menu-awam.php');
      if (!d || !d.ok || !d.config) return;
      App.gunaConfig(Store.bersih(d.config), false);   // false = jangan simpan
    } catch (e) {
      /* Tiada menu diterbitkan lagi (404) atau server tak dapat dihubungi.
         Laman terus guna menu lalai — bukan ralat. */
    }
  }

  /* ========================== BUAT PEMBAYARAN =========================== */

  /**
   * @param {object} d { cart, cara, pelanggan, saluran }
   * Redirect pelayar ke halaman pembayaran Bayarcash bila berjaya.
   */
  async function bayar(d) {
    const jawapan = await hantar('buat-bayaran.php', {
      cart: d.cart.map((b) => ({
        id: b.id,
        variasi: b.variasi || '',
        tambahan: (b.tambahan || []).map((t) => t.nama),
        kuantiti: b.kuantiti,
        nota: b.nota || '',
      })),
      cara: d.cara,
      saluran: d.saluran,
      pelanggan: d.pelanggan,
    });

    if (!jawapan.url) throw new Error('Bayarcash tidak memulangkan pautan pembayaran');

    // Simpan nombor order supaya boleh dirujuk selepas balik
    try {
      sessionStorage.setItem('orderdyno:order', jawapan.order_number);
    } catch (e) {
      /* abaikan */
    }

    location.href = jawapan.url;
  }

  /* ======================= KEPUTUSAN SELEPAS BALIK ====================== */

  function periksaPulangan() {
    const params = new URLSearchParams(location.search);
    const nombor = params.get('order');
    if (!nombor) return;

    // Bersihkan URL supaya refresh tidak buka semula sheet ini
    history.replaceState(null, '', location.pathname + location.hash);

    bukaHasil(nombor);
  }

  function bukaHasil(nombor) {
    App.bukaSheetHasil();
    paparHasil({ menunggu: true, order_number: nombor });
    tinjauStatus(nombor, 0);
  }

  function tinjauStatus(nombor, cubaan) {
    clearTimeout(tinjau);

    dapat('status-order.php?order=' + encodeURIComponent(nombor))
      .then((d) => {
        paparHasil(d);

        // Status 0 = Baru, 1 = Menunggu — terus tinjau sampai ~45 saat
        const belumPasti = d.status === 0 || d.status === 1;
        if (belumPasti && cubaan < 12) {
          tinjau = setTimeout(() => tinjauStatus(nombor, cubaan + 1), cubaan < 3 ? 2000 : 4000);
        } else if (d.dibayar) {
          App.kosongkanCart();
        }
      })
      .catch((e) => {
        paparHasil({ ralat: e.message, order_number: nombor });
      });
  }

  function hentiTinjau() {
    clearTimeout(tinjau);
  }

  /* ---------------------------- Papar keputusan --------------------------- */

  function paparHasil(d) {
    const badan = $('#badanHasil');
    const kaki = $('#kakiHasil');
    const esc = App.esc;
    if (!badan) return;

    /* --- Sedang menunggu --- */
    if (d.menunggu) {
      $('#tajukHasil').textContent = 'Menyemak pembayaran…';
      badan.innerHTML = `
        <div class="hasil">
          <div class="hasil__pusing" aria-hidden="true"></div>
          <h3 class="hasil__tajuk">Sedang menyemak pembayaran</h3>
          <p class="hasil__nota">Sebentar ya, kami sedang menunggu pengesahan dari bank.</p>
          <p class="hasil__ruj">Order <b>${esc(d.order_number)}</b></p>
        </div>`;
      kaki.innerHTML = '';
      return;
    }

    /* --- Ralat rangkaian --- */
    if (d.ralat) {
      $('#tajukHasil').textContent = 'Tidak dapat semak';
      badan.innerHTML = `
        <div class="hasil">
          <div class="hasil__ikon hasil__ikon--gagal">!</div>
          <h3 class="hasil__tajuk">Tidak dapat semak status</h3>
          <p class="hasil__nota">${esc(d.ralat)}</p>
          <p class="hasil__ruj">Order <b>${esc(d.order_number || '—')}</b> — simpan nombor ini dan tunjuk kepada kedai.</p>
        </div>`;
      kaki.innerHTML = `<button class="btn-blok" type="button" data-tutup>Tutup</button>`;
      return;
    }

    const wang = `${d.mata_wang === 'MYR' ? 'RM' : d.mata_wang} ${d.jumlah}`;
    const item = (d.baris || [])
      .map(
        (b) => `<div class="hasil__baris">
                  <span>${b.kuantiti}× ${esc(b.nama)}${b.variasi ? ` (${esc(b.variasi)})` : ''}</span>
                  <span>${esc(d.mata_wang === 'MYR' ? 'RM' : d.mata_wang)} ${esc(b.jumlah)}</span>
                </div>`
      )
      .join('');

    /* --- Berjaya --- */
    if (d.dibayar) {
      $('#tajukHasil').textContent = 'Pembayaran berjaya';
      badan.innerHTML = `
        <div class="hasil">
          <div class="hasil__ikon hasil__ikon--berjaya">✓</div>
          <h3 class="hasil__tajuk">Terima kasih${d.nama ? ', ' + esc(d.nama.split(' ')[0]) : ''}!</h3>
          <p class="hasil__nota">Pembayaran anda sebanyak <b>${esc(wang)}</b> telah diterima.</p>
        </div>
        <div class="hasil__resit">
          ${item}
          <div class="hasil__baris hasil__baris--jumlah"><span>Jumlah dibayar</span><span>${esc(wang)}</span></div>
        </div>
        <p class="hasil__ruj">
          Order <b>${esc(d.order_number)}</b>
          ${d.rujukan && d.rujukan.exchange_reference_number ? `<br>Rujukan bank: ${esc(d.rujukan.exchange_reference_number)}` : ''}
          ${d.rujukan && d.rujukan.bank ? `<br>${esc(d.rujukan.bank)}` : ''}
        </p>`;

      kaki.innerHTML = `
        <button class="btn-blok btn-hantar" type="button" data-beritahu="${esc(d.order_number)}">
          Beritahu kedai via WhatsApp
        </button>
        <button class="cart-buang" type="button" data-tutup style="display:block;margin:12px auto 0">Tutup</button>`;

      // Simpan data untuk butang WhatsApp
      kaki.dataset.hasil = JSON.stringify(d);
      return;
    }

    /* --- Gagal / dibatalkan / masih tergantung --- */
    const tajuk =
      d.status === 4 ? 'Pembayaran dibatalkan' : d.status === 2 ? 'Pembayaran tidak berjaya' : 'Pembayaran belum selesai';

    $('#tajukHasil').textContent = tajuk;
    badan.innerHTML = `
      <div class="hasil">
        <div class="hasil__ikon hasil__ikon--gagal">✕</div>
        <h3 class="hasil__tajuk">${esc(tajuk)}</h3>
        <p class="hasil__nota">
          ${
            d.status === 2 || d.status === 4
              ? 'Tiada duit ditolak. Anda boleh cuba lagi atau hantar order melalui WhatsApp.'
              : 'Bank masih memproses. Kalau duit sudah ditolak, tunjuk nombor order di bawah kepada kedai.'
          }
        </p>
      </div>
      <div class="hasil__resit">
        ${item}
        <div class="hasil__baris hasil__baris--jumlah"><span>Jumlah</span><span>${esc(wang)}</span></div>
      </div>
      <p class="hasil__ruj">Order <b>${esc(d.order_number)}</b> · ${esc(d.status_label)}</p>`;

    kaki.innerHTML = `
      <button class="btn-blok" type="button" data-cuba-lagi>Cuba bayar semula</button>
      <button class="cart-buang" type="button" data-tutup style="display:block;margin:12px auto 0">Tutup</button>`;
  }

  /* Mesej WhatsApp selepas bayar — supaya kedai tahu order sudah masuk */
  function mesejSelepasBayar(d) {
    const wangSimbol = d.mata_wang === 'MYR' ? 'RM' : d.mata_wang;
    const g = [];
    g.push(`*ORDER SUDAH DIBAYAR*`);
    g.push('');
    g.push(`Order: ${d.order_number}`);
    if (d.nama) g.push(`Nama: ${d.nama}`);
    g.push('');
    (d.baris || []).forEach((b, i) => {
      g.push(`${i + 1}. ${b.nama}${b.variasi ? ` (${b.variasi})` : ''} × ${b.kuantiti}`);
      if (b.tambahan && b.tambahan.length) g.push(`    + ${b.tambahan.join(', ')}`);
      if (b.nota) g.push(`    _${b.nota}_`);
    });
    g.push('');
    g.push(`*JUMLAH DIBAYAR: ${wangSimbol} ${d.jumlah}*`);
    g.push(`Cara: ${d.cara === 'delivery' ? 'Penghantaran' : 'Ambil sendiri'}`);
    if (d.saluran_nama) g.push(`Bayaran: ${d.saluran_nama}`);
    if (d.rujukan && d.rujukan.exchange_reference_number) {
      g.push(`Rujukan: ${d.rujukan.exchange_reference_number}`);
    }
    return g.join('\n');
  }

  /* =============================== ADMIN ================================ */

  /* Dipanggil oleh tab "Bayaran" dalam panel Edit Menu */
  function admin(aksi, data, kunci) {
    return hantar('admin.php', Object.assign({ aksi, kunci }, data || {}));
  }

  function statusAdmin(kunci) {
    return dapat('status.php?key=' + encodeURIComponent(kunci));
  }

  /* ============================= PERISTIWA ============================== */

  document.addEventListener('click', (e) => {
    const beritahu = e.target.closest('[data-beritahu]');
    if (beritahu) {
      const kaki = document.getElementById('kakiHasil');
      let d = {};
      try {
        d = JSON.parse(kaki.dataset.hasil || '{}');
      } catch (err) {
        /* abaikan */
      }
      const no = String(App.config().kedai.whatsapp || '').replace(/\D/g, '');
      if (!no) return App.toast('Kedai belum set nombor WhatsApp', 'silap');
      window.open(`https://wa.me/${no}?text=${encodeURIComponent(mesejSelepasBayar(d))}`, '_blank', 'noopener');
      return;
    }

    if (e.target.closest('[data-cuba-lagi]')) {
      hentiTinjau();
      App.tutupSheet();
      setTimeout(() => App.bukaCart(), 450);
    }
  });

  return {
    mula,
    bayar,
    admin,
    statusAdmin,
    hentiTinjau,
    bukaHasil,
    sedia: () => keadaan.backend && keadaan.siap,
    adaBackend: () => keadaan.backend,
    keadaan: () => keadaan,
  };
})();

/* `const` pada aras atas tidak mencipta sifat pada window, jadi kita
   dedahkan modul ini secara jelas untuk pemeriksaan `window.Bayar`. */
window.Bayar = Bayar;
