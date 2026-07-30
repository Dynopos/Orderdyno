/* ==========================================================================
   OrderDyno — Panel "Edit Menu"
   --------------------------------------------------------------------------
   Panel ini membolehkan pemilik kedai isi semua maklumat & menu sendiri
   tanpa buka code. Semua perubahan:
     • disimpan automatik ke localStorage pelayar
     • boleh di-Export jadi fail JSON (backup / pindah peranti)
     • boleh dikongsi sebagai satu link panjang (#menu=...)

   Tab: Kedai · Menu · Tema · Order · Simpan
   ========================================================================== */

const Editor = (() => {
  let C = null;              // salinan kerja tetapan
  let el = null;             // elemen panel
  let tab = 'kedai';
  let itemEdit = null;       // id item yang sedang dibuka dalam borang
  let masaSimpan = null;

  const esc = App.esc;

  /* ------------------------------ Utiliti --------------------------------- */

  const $p = (s) => el.querySelector(s);
  const $$p = (s) => Array.from(el.querySelectorAll(s));

  /* Set nilai ikut laluan "kedai.orderNow.label" */
  function set(laluan, nilai) {
    const bahagian = laluan.split('.');
    let sasaran = C;
    for (let i = 0; i < bahagian.length - 1; i++) sasaran = sasaran[bahagian[i]];
    sasaran[bahagian[bahagian.length - 1]] = nilai;
  }

  function ambil(laluan) {
    return laluan.split('.').reduce((o, k) => (o == null ? undefined : o[k]), C);
  }

  /* Kecilkan gambar sebelum simpan supaya localStorage tak penuh */
  function bacaGambar(fail, siap) {
    if (!fail) return;
    if (fail.size > 8 * 1024 * 1024) {
      App.toast('Gambar terlalu besar (maks 8MB)', 'silap');
      return;
    }
    const pembaca = new FileReader();
    pembaca.onload = () => {
      const img = new Image();
      img.onload = () => {
        const maks = 640;
        const skala = Math.min(1, maks / Math.max(img.width, img.height));
        const kanvas = document.createElement('canvas');
        kanvas.width = Math.round(img.width * skala);
        kanvas.height = Math.round(img.height * skala);
        kanvas.getContext('2d').drawImage(img, 0, 0, kanvas.width, kanvas.height);
        siap(kanvas.toDataURL('image/jpeg', 0.82));
      };
      img.onerror = () => App.toast('Fail gambar tak sah', 'silap');
      img.src = pembaca.result;
    };
    pembaca.readAsDataURL(fail);
  }

  function pilihFail(siap) {
    const inp = document.createElement('input');
    inp.type = 'file';
    inp.accept = 'image/*';
    inp.onchange = () => bacaGambar(inp.files[0], siap);
    inp.click();
  }

  function gerak(senarai, i, arah) {
    const j = i + arah;
    if (j < 0 || j >= senarai.length) return;
    const t = senarai[i];
    senarai[i] = senarai[j];
    senarai[j] = t;
  }

  /* Alih item naik/turun dalam kategorinya sendiri sahaja — bertukar tempat
     dengan jiran terdekat yang berada dalam kategori yang sama. */
  function gerakItem(idx, arah) {
    const item = C.menu[idx];
    if (!item) return;
    for (let j = idx + arah; j >= 0 && j < C.menu.length; j += arah) {
      if (C.menu[j].kategori === item.kategori) {
        C.menu[idx] = C.menu[j];
        C.menu[j] = item;
        return;
      }
    }
  }

  /* ---------------------- Simpan + segarkan paparan ----------------------- */

  function terap(renderPanel) {
    App.gunaConfig(Store.klon(C));
    tunjukStatus('Menyimpan…');
    clearTimeout(masaSimpan);
    masaSimpan = setTimeout(() => tunjukStatus('Semua perubahan disimpan ✓', true), 450);
    if (renderPanel !== false) renderBadan();
  }

  function tunjukStatus(teks, siap) {
    const s = $p('.ed__status');
    if (!s) return;
    s.textContent = teks;
    s.classList.toggle('simpan', !!siap);
  }

  /* ============================ TAB: KEDAI =============================== */

  function tabKedai() {
    const k = C.kedai;
    return `
      <div class="f">
        <label for="fNama">Nama kedai</label>
        <input class="medan" id="fNama" data-jalan="kedai.nama" value="${esc(k.nama)}" placeholder="Contoh: Warung Kak Ros">
      </div>

      <div class="f">
        <label for="fTagline">Tagline</label>
        <input class="medan" id="fTagline" data-jalan="kedai.tagline" value="${esc(k.tagline)}" placeholder="Contoh: Sedap panas-panas dari dapur">
      </div>

      <div class="f">
        <span class="f__label">Logo kedai</span>
        <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap">
          <div class="ed-item__gambar" style="width:56px;height:56px">
            ${
              k.logo
                ? `<img src="${esc(k.logo)}" alt="">`
                : k.logoEmoji
                ? esc(k.logoEmoji)
                : '<span style="opacity:.4">🖼</span>'
            }
          </div>
          <button class="btn-kecil" type="button" data-aksi="logo-upload">Upload gambar</button>
          ${k.logo ? '<button class="btn-kecil btn-kecil--bahaya" type="button" data-aksi="logo-buang">Buang</button>' : ''}
        </div>
        <div class="f" style="margin-top:12px">
          <input class="medan" data-jalan="kedai.logoEmoji" value="${esc(k.logoEmoji)}" placeholder="Atau guna emoji sahaja, contoh: 🍔" maxlength="4">
        </div>
      </div>

      <div class="f-baris">
        <div class="f">
          <label for="fWa">Nombor WhatsApp</label>
          <input class="medan" id="fWa" data-jalan="kedai.whatsapp" value="${esc(k.whatsapp)}" placeholder="60123456789" inputmode="numeric">
          <p class="f__nota">Format antarabangsa tanpa +, contoh <b>60123456789</b>. Order pelanggan akan masuk ke nombor ini.</p>
        </div>
        <div class="f">
          <label for="fMataWang">Mata wang</label>
          <input class="medan" id="fMataWang" data-jalan="kedai.mataWang" value="${esc(k.mataWang)}" placeholder="RM" maxlength="5">
        </div>
      </div>

      <div class="f">
        <label for="fTel">Nombor untuk dipaparkan</label>
        <input class="medan" id="fTel" data-jalan="kedai.telefon" value="${esc(k.telefon)}" placeholder="+60 12-345 6789">
      </div>

      <div class="f">
        <label for="fWaktu">Waktu operasi</label>
        <input class="medan" id="fWaktu" data-jalan="kedai.waktu" value="${esc(k.waktu)}" placeholder="Setiap hari · 10:00 AM – 10:00 PM">
      </div>

      <div class="f">
        <label for="fAlamat">Alamat</label>
        <textarea class="medan" id="fAlamat" data-jalan="kedai.alamat" placeholder="Alamat penuh kedai anda">${esc(k.alamat)}</textarea>
      </div>

      <div class="f">
        <label for="fMap">Pautan Google Maps / Waze</label>
        <input class="medan" id="fMap" data-jalan="kedai.pautanLokasi" value="${esc(k.pautanLokasi)}" placeholder="https://maps.app.goo.gl/...">
      </div>

      <div class="f-baris">
        <div class="f">
          <label for="fOnLabel">Label butang kedua</label>
          <input class="medan" id="fOnLabel" data-jalan="kedai.orderNow.label" value="${esc(k.orderNow.label)}" placeholder="Order Now">
        </div>
        <div class="f">
          <label for="fOnLink">Pautan butang kedua</label>
          <input class="medan" id="fOnLink" data-jalan="kedai.orderNow.pautan" value="${esc(k.orderNow.pautan)}" placeholder="Biar kosong = scroll ke menu">
        </div>
      </div>

      <div class="f">
        <label for="fNota">Nota dalam cart</label>
        <textarea class="medan" id="fNota" data-jalan="kedai.nota" placeholder="Contoh: Pembayaran secara COD atau QR sahaja">${esc(k.nota)}</textarea>
      </div>

      <label class="suis">
        <input type="checkbox" data-jalan="kedai.sembunyikanEdit" ${k.sembunyikanEdit ? 'checked' : ''}>
        <span>Sembunyikan butang "Edit Menu" dari pelanggan</span>
      </label>
      <p class="f__nota">Bila disembunyikan, anda masih boleh buka panel ini dengan tambah <b>#edit</b> di hujung URL.</p>`;
  }

  /* ============================ TAB: MENU ================================ */

  function tabMenu() {
    if (itemEdit) return borangItem();

    const kategori = C.kategori
      .map((kat, ki) => {
        const item = C.menu.filter((m) => m.kategori === kat.id);
        return `
        <div class="ed-blok">
          <div class="ed-blok__kepala">
            <input class="medan" data-kat-nama="${ki}" value="${esc(kat.nama)}" placeholder="Nama kategori" style="flex:1">
            <button class="mini" type="button" data-aksi="kat-naik" data-i="${ki}" title="Naik" ${ki === 0 ? 'disabled' : ''}>↑</button>
            <button class="mini" type="button" data-aksi="kat-turun" data-i="${ki}" title="Turun" ${ki === C.kategori.length - 1 ? 'disabled' : ''}>↓</button>
            <button class="mini mini--bahaya" type="button" data-aksi="kat-buang" data-i="${ki}" title="Buang kategori">✕</button>
          </div>

          ${
            item.length
              ? item
                  .map((m, mi) => {
                    const idx = C.menu.indexOf(m);
                    const gambar = m.gambar
                      ? `<img src="${esc(m.gambar)}" alt="">`
                      : m.emoji
                      ? esc(m.emoji)
                      : '<span style="opacity:.4">🖼</span>';
                    const harga =
                      m.pilihan.length > 1
                        ? `${App.wang(Math.min.apply(null, m.pilihan.map((p) => p.harga)))} – ${App.wang(Math.max.apply(null, m.pilihan.map((p) => p.harga)))}`
                        : App.wang(m.pilihan.length ? m.pilihan[0].harga : m.harga);
                    return `
                <div class="ed-item">
                  <div class="ed-item__gambar">${gambar}</div>
                  <div class="ed-item__isi">
                    <div class="ed-item__nama">${esc(m.nama)}${m.habis ? ' · <span style="color:#ff9db3">habis</span>' : ''}</div>
                    <div class="ed-item__harga">${harga}</div>
                  </div>
                  <button class="mini" type="button" data-aksi="item-naik" data-i="${idx}" title="Naik" ${mi === 0 ? 'disabled' : ''}>↑</button>
                  <button class="mini" type="button" data-aksi="item-turun" data-i="${idx}" title="Turun" ${mi === item.length - 1 ? 'disabled' : ''}>↓</button>
                  <button class="mini" type="button" data-aksi="item-edit" data-id="${esc(m.id)}" title="Edit">✎</button>
                  <button class="mini mini--bahaya" type="button" data-aksi="item-buang" data-i="${idx}" title="Buang">✕</button>
                </div>`;
                  })
                  .join('')
              : '<p class="f__nota" style="margin:0 0 10px">Belum ada item dalam kategori ini.</p>'
          }

          <button class="btn-tambah-baris" type="button" data-aksi="item-tambah" data-kat="${esc(kat.id)}">＋ Tambah item</button>
        </div>`;
      })
      .join('');

    return `
      ${kategori}
      <button class="btn-tambah-baris" type="button" data-aksi="kat-tambah">＋ Tambah kategori</button>`;
  }

  function borangItem() {
    const m = C.menu.find((x) => x.id === itemEdit);
    if (!m) {
      itemEdit = null;
      return tabMenu();
    }

    const pilihan = m.pilihan
      .map(
        (p, i) => `
      <div style="display:flex;gap:8px;margin-bottom:8px">
        <input class="medan" data-pil="nama" data-i="${i}" value="${esc(p.nama)}" placeholder="Contoh: Biasa / Besar" style="flex:2">
        <input class="medan" data-pil="harga" data-i="${i}" value="${p.harga}" placeholder="0.00" inputmode="decimal" style="flex:1">
        <button class="mini mini--bahaya" type="button" data-aksi="pil-buang" data-i="${i}" title="Buang">✕</button>
      </div>`
      )
      .join('');

    const tambahan = m.tambahan
      .map(
        (t, i) => `
      <div style="display:flex;gap:8px;margin-bottom:8px">
        <input class="medan" data-tam="nama" data-i="${i}" value="${esc(t.nama)}" placeholder="Contoh: Extra cheese" style="flex:2">
        <input class="medan" data-tam="harga" data-i="${i}" value="${t.harga}" placeholder="0.00" inputmode="decimal" style="flex:1">
        <button class="mini mini--bahaya" type="button" data-aksi="tam-buang" data-i="${i}" title="Buang">✕</button>
      </div>`
      )
      .join('');

    return `
      <button class="btn-kecil" type="button" data-aksi="item-tutup" style="margin-bottom:18px">← Kembali ke senarai menu</button>

      <div class="f">
        <label for="iNama">Nama item</label>
        <input class="medan" id="iNama" data-item="nama" value="${esc(m.nama)}" placeholder="Contoh: Nasi Lemak Ayam">
      </div>

      <div class="f">
        <label for="iDesc">Penerangan</label>
        <textarea class="medan" id="iDesc" data-item="desc" placeholder="Terangkan menu ini dengan ringkas">${esc(m.desc)}</textarea>
      </div>

      <div class="f">
        <label for="iKat">Kategori</label>
        <select class="medan" id="iKat" data-item="kategori">
          ${C.kategori.map((k) => `<option value="${esc(k.id)}"${k.id === m.kategori ? ' selected' : ''}>${esc(k.nama)}</option>`).join('')}
        </select>
      </div>

      <div class="f">
        <span class="f__label">Gambar item</span>
        <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap">
          <div class="ed-item__gambar" style="width:64px;height:64px">
            ${m.gambar ? `<img src="${esc(m.gambar)}" alt="">` : m.emoji ? esc(m.emoji) : '<span style="opacity:.4">🖼</span>'}
          </div>
          <button class="btn-kecil" type="button" data-aksi="item-gambar">Upload gambar</button>
          ${m.gambar ? '<button class="btn-kecil btn-kecil--bahaya" type="button" data-aksi="item-gambar-buang">Buang</button>' : ''}
        </div>
        <div class="f" style="margin-top:12px">
          <input class="medan" data-item="emoji" value="${esc(m.emoji)}" placeholder="Atau emoji sahaja, contoh: 🍜" maxlength="4">
        </div>
      </div>

      <div class="f">
        <label for="iHarga">Harga asas</label>
        <input class="medan" id="iHarga" data-item="harga" value="${m.harga}" placeholder="0.00" inputmode="decimal">
        <p class="f__nota">Digunakan bila tiada senarai pilihan/saiz di bawah.</p>
      </div>

      <div class="f">
        <span class="f__label">Pilihan / saiz (harga berbeza)</span>
        ${pilihan}
        <button class="btn-tambah-baris" type="button" data-aksi="pil-tambah">＋ Tambah pilihan</button>
        <p class="f__nota">Kalau ada 2 atau lebih, harga di kad menu akan jadi julat — contoh <b>RM 8.00 – RM 12.00</b>.</p>
      </div>

      <div class="f">
        <span class="f__label">Tambahan / add-on</span>
        ${tambahan}
        <button class="btn-tambah-baris" type="button" data-aksi="tam-tambah">＋ Tambah add-on</button>
      </div>

      <label class="suis">
        <input type="checkbox" data-item="popular" ${m.popular ? 'checked' : ''}>
        <span>Tandakan sebagai "Popular"</span>
      </label>
      <label class="suis">
        <input type="checkbox" data-item="habis" ${m.habis ? 'checked' : ''}>
        <span>Stok habis (pelanggan tak boleh order)</span>
      </label>

      <button class="btn-kecil btn-kecil--bahaya" type="button" data-aksi="item-buang-ini" style="margin-top:10px">Buang item ini</button>`;
  }

  /* ============================ TAB: TEMA ================================ */

  function tabTema() {
    const t = C.tema;
    return `
      <div class="f">
        <span class="f__label">Pilih tema siap pakai</span>
        <div class="tema-grid">
          ${PRESET_TEMA.map(
            (p) => `
            <button class="tema-pil${t.preset === p.id ? ' aktif' : ''}" type="button" data-aksi="tema-preset" data-id="${p.id}">
              <span class="tema-pil__contoh" style="background:linear-gradient(100deg,${p.warna1},${p.warna2} 55%,${p.warna3})"></span>
              ${p.nama}
            </button>`
          ).join('')}
        </div>
      </div>

      <div class="f-baris">
        <div class="f">
          <label for="tW1">Warna 1</label>
          <input class="medan" type="color" id="tW1" data-jalan="tema.warna1" value="${esc(t.warna1)}">
        </div>
        <div class="f">
          <label for="tW2">Warna 2</label>
          <input class="medan" type="color" id="tW2" data-jalan="tema.warna2" value="${esc(t.warna2)}">
        </div>
        <div class="f">
          <label for="tW3">Warna 3</label>
          <input class="medan" type="color" id="tW3" data-jalan="tema.warna3" value="${esc(t.warna3)}">
        </div>
      </div>

      <div class="f">
        <label for="tLatar">Warna latar</label>
        <input class="medan" type="color" id="tLatar" data-jalan="tema.latar" value="${esc(t.latar)}">
        <p class="f__nota">Pilih warna gelap untuk kekalkan rupa neon. Warna cerah akan buat teks sukar dibaca.</p>
      </div>`;
  }

  /* ============================ TAB: ORDER =============================== */

  function tabOrder() {
    const p = C.penghantaran.pickup;
    const d = C.penghantaran.delivery;
    return `
      <div class="ed-blok">
        <div class="ed-blok__kepala"><h4>Ambil sendiri</h4></div>
        <label class="suis">
          <input type="checkbox" data-jalan="penghantaran.pickup.aktif" ${p.aktif ? 'checked' : ''}>
          <span>Benarkan pelanggan ambil sendiri</span>
        </label>
        <div class="f">
          <label for="pLabel">Label</label>
          <input class="medan" id="pLabel" data-jalan="penghantaran.pickup.label" value="${esc(p.label)}" placeholder="Ambil Sendiri">
        </div>
        <div class="f" style="margin-bottom:0">
          <label for="pNota">Nota</label>
          <input class="medan" id="pNota" data-jalan="penghantaran.pickup.nota" value="${esc(p.nota)}" placeholder="Sedia dalam 15–20 minit">
        </div>
      </div>

      <div class="ed-blok">
        <div class="ed-blok__kepala"><h4>Penghantaran</h4></div>
        <label class="suis">
          <input type="checkbox" data-jalan="penghantaran.delivery.aktif" ${d.aktif ? 'checked' : ''}>
          <span>Tawarkan penghantaran</span>
        </label>
        <div class="f">
          <label for="dLabel">Label</label>
          <input class="medan" id="dLabel" data-jalan="penghantaran.delivery.label" value="${esc(d.label)}" placeholder="Penghantaran">
        </div>
        <div class="f-baris">
          <div class="f">
            <label for="dCaj">Caj penghantaran</label>
            <input class="medan" id="dCaj" data-jalan="penghantaran.delivery.caj" data-nombor value="${d.caj}" inputmode="decimal" placeholder="0.00">
          </div>
          <div class="f">
            <label for="dMin">Minimum order</label>
            <input class="medan" id="dMin" data-jalan="penghantaran.delivery.minOrder" data-nombor value="${d.minOrder}" inputmode="decimal" placeholder="0.00">
          </div>
        </div>
        <div class="f" style="margin-bottom:0">
          <label for="dNota">Nota</label>
          <input class="medan" id="dNota" data-jalan="penghantaran.delivery.nota" value="${esc(d.nota)}" placeholder="Dalam radius 5km sahaja">
        </div>
      </div>

      <p class="f__nota">Kalau kedua-duanya aktif, pelanggan akan nampak pilihan dalam cart. Kalau satu sahaja, ia digunakan automatik.</p>`;
  }

  /* =========================== TAB: SIMPAN =============================== */

  function tabSimpan() {
    const link = Store.jadiLink(C);
    const panjang = link.length;
    const beratGambar = JSON.stringify(C).indexOf('data:image') !== -1;

    return `
      <div class="ed-blok">
        <div class="ed-blok__kepala"><h4>Backup fail JSON</h4></div>
        <p class="f__nota" style="margin-top:0">Simpan menu anda sebagai fail. Boleh diimport balik bila tukar telefon, clear browser, atau nak bagi pada orang lain.</p>
        <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:12px">
          <button class="btn-kecil btn-kecil--utama" type="button" data-aksi="export">⬇ Export fail JSON</button>
          <button class="btn-kecil" type="button" data-aksi="import">⬆ Import fail JSON</button>
        </div>
      </div>

      <div class="ed-blok">
        <div class="ed-blok__kepala"><h4>Kongsi menu sebagai link</h4></div>
        <p class="f__nota" style="margin-top:0">Link ini mengandungi seluruh menu anda. Sesiapa yang buka akan nampak menu yang sama — tanpa server.</p>
        <div class="pautan-kotak" style="margin-top:12px">${esc(link)}</div>
        <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center">
          <button class="btn-kecil btn-kecil--utama" type="button" data-aksi="salin-link">📋 Salin link</button>
          <span class="f__nota" style="margin:0">${panjang.toLocaleString()} aksara</span>
        </div>
        ${
          beratGambar
            ? '<p class="f__nota" style="color:#ffd0a8;margin-top:10px">Anda ada gambar yang di-upload, jadi link ini panjang dan mungkin tak berfungsi pada semua platform. Untuk link pendek, guna URL gambar (hosting luar) atau emoji, dan simpan menu ke server sebagai fail JSON.</p>'
            : ''
        }
      </div>

      <div class="ed-blok">
        <div class="ed-blok__kepala"><h4>Jadikan menu ini kekal</h4></div>
        <p class="f__nota" style="margin-top:0">Untuk menu anda muncul terus kepada semua pelawat (tanpa mereka perlu buka link kongsi), Export fail JSON di atas, kemudian tampal isinya ke dalam <b>assets/js/config.js</b> sebagai nilai <b>TEMPLATE</b>, dan upload semula website.</p>
      </div>

      <div class="ed-blok">
        <div class="ed-blok__kepala"><h4>Set semula</h4></div>
        <p class="f__nota" style="margin-top:0">Kembali ke menu contoh asal. Semua isian anda akan hilang — export dahulu kalau perlu.</p>
        <button class="btn-kecil btn-kecil--bahaya" type="button" data-aksi="reset" style="margin-top:12px">Reset ke template asal</button>
      </div>`;
  }

  /* ============================== RENDER ================================= */

  const TAB = [
    { id: 'kedai', nama: 'Kedai', render: tabKedai },
    { id: 'menu', nama: 'Menu', render: tabMenu },
    { id: 'tema', nama: 'Tema', render: tabTema },
    { id: 'order', nama: 'Order', render: tabOrder },
    { id: 'simpan', nama: 'Simpan & Kongsi', render: tabSimpan },
  ];

  function bina() {
    el = document.createElement('div');
    el.className = 'ed';
    el.innerHTML = `
      <div class="ed__tirai" data-aksi="tutup"></div>
      <div class="ed__panel" role="dialog" aria-modal="true" aria-label="Edit menu dan tetapan kedai">
        <div class="ed__kepala">
          <div>
            <h2>Edit Menu Anda</h2>
            <p>Semua perubahan disimpan automatik dalam pelayar ini</p>
          </div>
          <button class="bulat-tutup" type="button" data-aksi="tutup" aria-label="Tutup">✕</button>
        </div>
        <div class="ed__tab">
          ${TAB.map((t) => `<button type="button" data-tab="${t.id}">${t.nama}</button>`).join('')}
        </div>
        <div class="ed__badan"></div>
        <div class="ed__kaki">
          <span class="ed__status">Sedia untuk diedit</span>
          <button class="btn-kecil btn-kecil--utama" type="button" data-aksi="tutup">Selesai</button>
        </div>
      </div>`;
    document.getElementById('editorTempat').appendChild(el);
    pasangPeristiwa();
  }

  function renderBadan() {
    const jumpa = TAB.find((t) => t.id === tab) || TAB[0];
    $p('.ed__badan').innerHTML = jumpa.render();
    $$p('.ed__tab button').forEach((b) => b.classList.toggle('aktif', b.dataset.tab === tab));
  }

  /* ============================ PERISTIWA ================================ */

  function pasangPeristiwa() {
    /* --- Input teks / checkbox / warna --- */
    el.addEventListener('input', (e) => {
      const t = e.target;

      // Medan tetapan biasa
      if (t.dataset.jalan) {
        const nilai =
          t.type === 'checkbox'
            ? t.checked
            : t.hasAttribute('data-nombor')
            ? Number(t.value) || 0
            : t.value;
        set(t.dataset.jalan, nilai);
        // Tak perlu render panel semula untuk medan teks (elak hilang fokus)
        terap(t.type === 'checkbox');
        return;
      }

      // Nama kategori
      if (t.dataset.katNama !== undefined) {
        C.kategori[Number(t.dataset.katNama)].nama = t.value;
        terap(false);
        return;
      }

      // Medan item
      const m = C.menu.find((x) => x.id === itemEdit);
      if (!m) return;

      if (t.dataset.item) {
        const medan = t.dataset.item;
        if (medan === 'harga') m.harga = Number(t.value) || 0;
        else if (medan === 'popular' || medan === 'habis') m[medan] = t.checked;
        else m[medan] = t.value;
        terap(medan === 'popular' || medan === 'habis' || medan === 'kategori');
        return;
      }

      if (t.dataset.pil) {
        const p = m.pilihan[Number(t.dataset.i)];
        if (t.dataset.pil === 'harga') p.harga = Number(t.value) || 0;
        else p.nama = t.value;
        terap(false);
        return;
      }

      if (t.dataset.tam) {
        const a = m.tambahan[Number(t.dataset.i)];
        if (t.dataset.tam === 'harga') a.harga = Number(t.value) || 0;
        else a.nama = t.value;
        terap(false);
      }
    });

    /* Dropdown kategori item guna peristiwa change */
    el.addEventListener('change', (e) => {
      if (e.target.tagName === 'SELECT' && e.target.dataset.item === 'kategori') {
        const m = C.menu.find((x) => x.id === itemEdit);
        if (m) {
          m.kategori = e.target.value;
          terap();
        }
      }
    });

    /* --- Klik butang --- */
    el.addEventListener('click', (e) => {
      const btnTab = e.target.closest('[data-tab]');
      if (btnTab) {
        tab = btnTab.dataset.tab;
        itemEdit = null;
        renderBadan();
        return;
      }

      const btn = e.target.closest('[data-aksi]');
      if (!btn) return;
      const aksi = btn.dataset.aksi;
      const i = Number(btn.dataset.i);
      const m = C.menu.find((x) => x.id === itemEdit);

      switch (aksi) {
        case 'tutup':
          tutup();
          break;

        /* --- Logo --- */
        case 'logo-upload':
          pilihFail((d) => {
            C.kedai.logo = d;
            terap();
          });
          break;
        case 'logo-buang':
          C.kedai.logo = '';
          terap();
          break;

        /* --- Kategori --- */
        case 'kat-tambah': {
          const id = Store.idBaru('kat');
          C.kategori.push({ id, nama: 'Kategori Baru' });
          terap();
          break;
        }
        case 'kat-naik':
          gerak(C.kategori, i, -1);
          terap();
          break;
        case 'kat-turun':
          gerak(C.kategori, i, 1);
          terap();
          break;
        case 'kat-buang': {
          const kat = C.kategori[i];
          const bil = C.menu.filter((x) => x.kategori === kat.id).length;
          if (C.kategori.length === 1) {
            App.toast('Perlu sekurang-kurangnya satu kategori', 'silap');
            break;
          }
          if (bil && !confirm(`Buang "${kat.nama}" bersama ${bil} item di dalamnya?`)) break;
          C.menu = C.menu.filter((x) => x.kategori !== kat.id);
          C.kategori.splice(i, 1);
          terap();
          break;
        }

        /* --- Item --- */
        case 'item-tambah': {
          const id = Store.idBaru('item');
          C.menu.push({
            id,
            kategori: btn.dataset.kat,
            nama: 'Item Baru',
            desc: '',
            gambar: '',
            emoji: '',
            harga: 0,
            pilihan: [],
            tambahan: [],
            popular: false,
            habis: false,
          });
          itemEdit = id;
          terap();
          break;
        }
        case 'item-edit':
          itemEdit = btn.dataset.id;
          renderBadan();
          break;
        case 'item-tutup':
          itemEdit = null;
          renderBadan();
          break;
        case 'item-naik':
          gerakItem(i, -1);
          terap();
          break;
        case 'item-turun':
          gerakItem(i, 1);
          terap();
          break;
        case 'item-buang':
          if (confirm(`Buang "${C.menu[i].nama}"?`)) {
            C.menu.splice(i, 1);
            terap();
          }
          break;
        case 'item-buang-ini':
          if (m && confirm(`Buang "${m.nama}"?`)) {
            C.menu = C.menu.filter((x) => x.id !== m.id);
            itemEdit = null;
            terap();
          }
          break;
        case 'item-gambar':
          pilihFail((d) => {
            if (!m) return;
            m.gambar = d;
            terap();
          });
          break;
        case 'item-gambar-buang':
          if (m) {
            m.gambar = '';
            terap();
          }
          break;

        /* --- Pilihan & tambahan --- */
        case 'pil-tambah':
          if (m) {
            m.pilihan.push({ nama: '', harga: m.harga || 0 });
            terap();
          }
          break;
        case 'pil-buang':
          if (m) {
            m.pilihan.splice(i, 1);
            terap();
          }
          break;
        case 'tam-tambah':
          if (m) {
            m.tambahan.push({ nama: '', harga: 0 });
            terap();
          }
          break;
        case 'tam-buang':
          if (m) {
            m.tambahan.splice(i, 1);
            terap();
          }
          break;

        /* --- Tema --- */
        case 'tema-preset': {
          const p = PRESET_TEMA.find((x) => x.id === btn.dataset.id);
          if (p) {
            C.tema = { preset: p.id, warna1: p.warna1, warna2: p.warna2, warna3: p.warna3, latar: p.latar };
            terap();
          }
          break;
        }

        /* --- Simpan & kongsi --- */
        case 'export':
          Store.turunJson(C, `menu-${(C.kedai.nama || 'kedai').toLowerCase().replace(/[^a-z0-9]+/g, '-')}.json`);
          App.toast('Fail JSON dimuat turun', 'baik');
          break;

        case 'import': {
          const inp = document.createElement('input');
          inp.type = 'file';
          inp.accept = 'application/json,.json';
          inp.onchange = () => {
            const fail = inp.files[0];
            if (!fail) return;
            const pembaca = new FileReader();
            pembaca.onload = () => {
              try {
                C = Store.bersih(JSON.parse(pembaca.result));
                itemEdit = null;
                terap();
                App.toast('Menu berjaya diimport ✓', 'baik');
              } catch (err) {
                App.toast('Fail JSON tak sah', 'silap');
              }
            };
            pembaca.readAsText(fail);
          };
          inp.click();
          break;
        }

        case 'salin-link': {
          const link = Store.jadiLink(C);
          const selesai = () => App.toast('Link disalin ✓', 'baik');
          if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(link).then(selesai, () => salinLama(link, selesai));
          } else {
            salinLama(link, selesai);
          }
          break;
        }

        case 'reset':
          if (confirm('Set semula ke menu contoh asal? Semua isian anda akan hilang.')) {
            Store.padam();
            C = Store.klon(TEMPLATE);
            itemEdit = null;
            tab = 'kedai';
            terap();
            App.toast('Kembali ke template asal');
          }
          break;
      }
    });

    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' && el.classList.contains('buka')) tutup();
    });
  }

  function salinLama(teks, siap) {
    const ta = document.createElement('textarea');
    ta.value = teks;
    ta.style.position = 'fixed';
    ta.style.opacity = '0';
    document.body.appendChild(ta);
    ta.select();
    try {
      document.execCommand('copy');
      siap();
    } catch (err) {
      App.toast('Salin manual dari kotak di atas', 'silap');
    }
    ta.remove();
  }

  /* ============================== BUKA/TUTUP ============================= */

  function buka(tabMula) {
    if (!el) bina();
    C = Store.klon(App.config());
    if (tabMula) tab = tabMula;
    itemEdit = null;
    renderBadan();
    tunjukStatus('Sedia untuk diedit');
    el.classList.add('buka');
    document.body.classList.add('beku');
  }

  function tutup() {
    el.classList.remove('buka');
    if (!document.querySelector('.sheet.buka')) document.body.classList.remove('beku');
    // Buang #menu= dari URL supaya tetapan tersimpan digunakan lepas ini
    if (location.hash.indexOf('menu=') !== -1) {
      history.replaceState(null, '', location.pathname + location.search);
    }
  }

  return { buka, tutup };
})();

document.addEventListener('DOMContentLoaded', () => {
  const btn = document.getElementById('btnEdit');
  if (btn) btn.addEventListener('click', () => Editor.buka());
});
