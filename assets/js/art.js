/* ==========================================================================
   OrderDyno — Penjana gambar SVG
   --------------------------------------------------------------------------
   Setiap item menu dalam config.js boleh guna `art: { jenis: ... }` untuk
   dapatkan lukisan makanan tanpa perlu upload gambar. Kalau penjaja ada
   gambar sebenar, letak `gambar: 'assets/img/nasi.jpg'` dan ia akan ganti
   lukisan ini.
   ========================================================================== */

let _uid = 0;
const nextId = (p) => `${p}${++_uid}`;

/** Nombor "rawak" tapi tetap sama setiap kali render (0–1). */
const rawak = (n) => {
  const v = Math.sin(n * 12.9898 + 78.233) * 43758.5453;
  return v - Math.floor(v);
};

/* ------------------------------- BURGER ---------------------------------- */
function lukisBurger(o = {}) {
  const bun = o.bun || '#f0ad51';
  const bunGelap = teduh(bun, -18);
  const patty = o.patty || '#7a4326';
  const pattyGelap = teduh(patty, -18);
  const g = nextId('bg');

  // Lapisan hiasan: dilukis SELEPAS patty supaya nampak terjuntai di depan.
  const topping = {
    salad: `
      <path d="M22 124c10-10 19 4 28-4s17 6 27-3 18 7 28-2 18 7 28-2 18 6 27-3v20H22z"
            fill="#57bb52"/>
      <path d="M30 132c9 5 19-2 28 3s19-2 28 3 19-2 28 3 19-2 28 2"
            stroke="#9ce08d" stroke-width="3.5" fill="none" stroke-linecap="round"/>
      <ellipse cx="64" cy="122" rx="16" ry="7" fill="#e8544a"/>
      <ellipse cx="64" cy="120" rx="10" ry="3.5" fill="#ff8574" opacity=".75"/>
      <ellipse cx="134" cy="124" rx="15" ry="6.5" fill="#e8544a"/>
      <ellipse cx="134" cy="122" rx="9" ry="3" fill="#ff8574" opacity=".75"/>`,
    cheese: `
      <path d="M24 118h152v14l-13 22-13-16-14 20-13-16-14 20-13-16-14 18-13-18-16 14-14-22z"
            fill="#ffc32e"/>
      <path d="M24 118h152v13H24z" fill="#ffdf82"/>`,
    bawang: `
      <path d="M22 126c12-8 24 3 36-3s26 4 38-2 24 5 36-1 24 3 46-3v17H22z" fill="#57bb52"/>
      <g fill="none" stroke="#fdf1e3" stroke-width="4.5">
        <ellipse cx="58" cy="120" rx="18" ry="8"/>
        <ellipse cx="100" cy="116" rx="20" ry="9"/>
        <ellipse cx="143" cy="120" rx="17" ry="7.5"/>
      </g>`,
    telur: `
      <path d="M24 122c14-12 24 4 36-4s24 6 36-2 24 8 36 0 24 6 44-4v22H24z" fill="#fff8e6"/>
      <ellipse cx="106" cy="122" rx="16" ry="14" fill="#ffb703"/>
      <ellipse cx="100" cy="117" rx="6" ry="5" fill="#ffd873" opacity=".85"/>`,
  }[o.topping] || '';

  return `
<svg viewBox="0 0 200 200" role="img" aria-label="Burger">
  <defs>
    <linearGradient id="${g}bun" x1="0" y1="0" x2="0" y2="1">
      <stop offset="0" stop-color="${teduh(bun, 22)}"/><stop offset="1" stop-color="${bunGelap}"/>
    </linearGradient>
    <linearGradient id="${g}pat" x1="0" y1="0" x2="0" y2="1">
      <stop offset="0" stop-color="${teduh(patty, 12)}"/><stop offset="1" stop-color="${pattyGelap}"/>
    </linearGradient>
  </defs>
  <ellipse cx="100" cy="182" rx="70" ry="10" fill="#000" opacity=".18"/>

  <!-- roti bawah -->
  <path d="M24 156h152c0 12-11 20-26 20H50c-15 0-26-8-26-20z" fill="${bunGelap}"/>
  <path d="M24 156h152v5H24z" fill="${teduh(bun, 8)}"/>

  <!-- patty -->
  <rect x="20" y="134" width="160" height="26" rx="12" fill="url(#${g}pat)"/>
  <path d="M34 141h132" stroke="${teduh(patty, 22)}" stroke-width="3.5"
        stroke-linecap="round" opacity=".45"/>

  <!-- hiasan (salad / cheese / bawang / telur) -->
  ${topping}

  <!-- roti atas -->
  <path d="M26 120C26 68 58 42 100 42s74 26 74 78z" fill="url(#${g}bun)"/>
  <path d="M42 98c6-24 26-40 58-40" stroke="${teduh(bun, 38)}" stroke-width="7"
        stroke-linecap="round" fill="none" opacity=".5"/>
  <g fill="${teduh(bun, 48)}">
    <ellipse cx="72" cy="82" rx="7" ry="4" transform="rotate(-20 72 82)"/>
    <ellipse cx="104" cy="70" rx="7" ry="4" transform="rotate(6 104 70)"/>
    <ellipse cx="134" cy="88" rx="7" ry="4" transform="rotate(24 134 88)"/>
    <ellipse cx="88" cy="100" rx="6" ry="3.5" transform="rotate(-8 88 100)"/>
    <ellipse cx="120" cy="104" rx="6" ry="3.5" transform="rotate(14 120 104)"/>
  </g>
</svg>`;
}

/* -------------------------------- FRIES ---------------------------------- */
function lukisFries(o = {}) {
  const kotak = o.kotak || '#e2453b';
  const g = nextId('fr');

  const batang = [
    [56, 60, -16], [72, 44, -8], [90, 34, -2], [108, 38, 5],
    [124, 50, 13], [140, 66, 20], [82, 56, 3], [116, 60, -5],
  ]
    .map(
      ([x, y, r]) => `<rect x="${x}" y="${y}" width="15" height="70" rx="5"
        fill="url(#${g}f)" transform="rotate(${r} ${x + 7} ${y + 35})"/>`
    )
    .join('');

  // Bentuk kuah yang menitis atas kotak (tepi bawah bergelombang).
  const kuah = (warna, y, atas) =>
    `<path d="M50 ${atas}H150V${y}q-8 22-16 2q-8 20-16 0q-8 24-16 4q-8 18-16-2q-8 22-16 0q-8 16-16-4Z"
           fill="${warna}"/>`;

  // Taburan dilukis SELEPAS kotak supaya nampak melimpah di depan.
  const taburanAtas = {
    cheese: kuah('#ffc32e', 118, 96) + '<path d="M50 96h100v11H50z" fill="#ffdf82"/>',
    saltedegg:
      kuah('#ffd45e', 114, 96) +
      `<g fill="#3f7d34">${Array.from({ length: 10 }, (_, i) => {
        const x = 58 + i * 9;
        const y = 100 + ((i * 17) % 14);
        return `<ellipse cx="${x}" cy="${y}" rx="3.6" ry="2" transform="rotate(${i * 31} ${x} ${y})"/>`;
      }).join('')}</g>`,
  }[o.taburan] || '';

  // Lada hitam melekat pada batang fries, jadi ia atas fries (bukan atas kotak).
  const taburanBatang =
    o.taburan === 'pepper'
      ? `<g fill="#2b2b3d">${Array.from({ length: 26 }, (_, i) => {
          const x = 60 + rawak(i) * 80;
          const y = 44 + rawak(i + 99) * 58;
          return `<circle cx="${x.toFixed(1)}" cy="${y.toFixed(1)}" r="${(1.5 + rawak(i + 7) * 1.2).toFixed(1)}"/>`;
        }).join('')}</g>`
      : '';

  return `
<svg viewBox="0 0 200 200" role="img" aria-label="Fries">
  <defs>
    <linearGradient id="${g}f" x1="0" y1="0" x2="0" y2="1">
      <stop offset="0" stop-color="#ffe082"/><stop offset="1" stop-color="#f0a83c"/>
    </linearGradient>
    <linearGradient id="${g}k" x1="0" y1="0" x2="1" y2="1">
      <stop offset="0" stop-color="${teduh(kotak, 16)}"/><stop offset="1" stop-color="${teduh(kotak, -20)}"/>
    </linearGradient>
  </defs>
  <ellipse cx="100" cy="182" rx="60" ry="10" fill="#000" opacity=".18"/>
  ${batang}
  ${taburanBatang}
  <path d="M52 104h96l-11 74a8 8 0 0 1-8 7H71a8 8 0 0 1-8-7z" fill="url(#${g}k)"/>
  <path d="M52 104h96l-2 15H54z" fill="${teduh(kotak, 30)}" opacity=".75"/>
  <path d="M68 134h64l-6 38H74z" fill="#fff" opacity=".22"/>
  <path d="M86 144h28M82 158h36" stroke="#fff" stroke-width="6" stroke-linecap="round" opacity=".75"/>
  ${taburanAtas}
</svg>`;
}

/* ------------------------------- MINUMAN --------------------------------- */
function lukisMinuman(o = {}) {
  const warna = o.warna || '#c98b52';
  const warna2 = o.warna2 || teduh(warna, 30);
  const g = nextId('mn');

  const ais = o.ais
    ? `<g fill="#fff" opacity=".45">
         <rect x="74" y="86" width="24" height="24" rx="5" transform="rotate(-12 86 98)"/>
         <rect x="104" y="102" width="22" height="22" rx="5" transform="rotate(16 115 113)"/>
         <rect x="80" y="124" width="20" height="20" rx="5" transform="rotate(24 90 134)"/>
       </g>`
    : '';

  return `
<svg viewBox="0 0 200 200" role="img" aria-label="Minuman">
  <defs>
    <linearGradient id="${g}l" x1="0" y1="0" x2="0" y2="1">
      <stop offset="0" stop-color="${warna2}"/><stop offset="1" stop-color="${warna}"/>
    </linearGradient>
    <clipPath id="${g}c"><path d="M60 62h80l-10 116a8 8 0 0 1-8 7H78a8 8 0 0 1-8-7z"/></clipPath>
  </defs>
  <ellipse cx="100" cy="184" rx="52" ry="9" fill="#000" opacity=".18"/>
  <g transform="rotate(15 112 60)">
    <rect x="105" y="10" width="13" height="72" rx="6.5" fill="#ff5c8a"/>
    <rect x="108" y="16" width="4" height="58" rx="2" fill="#fff" opacity=".4"/>
  </g>
  <path d="M60 62h80l-10 116a8 8 0 0 1-8 7H78a8 8 0 0 1-8-7z" fill="#f3f6fb"/>
  <g clip-path="url(#${g}c)">
    <rect x="55" y="78" width="90" height="115" fill="url(#${g}l)"/>
    ${ais}
    <rect x="66" y="78" width="12" height="115" fill="#fff" opacity=".22"/>
  </g>
  <path d="M60 62h80l-10 116a8 8 0 0 1-8 7H78a8 8 0 0 1-8-7z" fill="none"
        stroke="#dfe6ef" stroke-width="3"/>
  <rect x="52" y="50" width="96" height="18" rx="9" fill="#fff"/>
  <rect x="52" y="50" width="96" height="8" rx="4" fill="#e6ecf4"/>
  <path d="M74 96l-4 78" stroke="#fff" stroke-width="5" stroke-linecap="round" opacity=".55"/>
</svg>`;
}

/* -------------------------------- HELPERS -------------------------------- */
/** Cerah/gelapkan warna hex. `amt` positif = lebih cerah. */
function teduh(hex, amt) {
  const n = parseInt(hex.replace('#', ''), 16);
  const c = [(n >> 16) & 255, (n >> 8) & 255, n & 255].map((v) =>
    Math.max(0, Math.min(255, v + Math.round((amt / 100) * 255)))
  );
  return `#${c.map((v) => v.toString(16).padStart(2, '0')).join('')}`;
}

const PELUKIS = { burger: lukisBurger, fries: lukisFries, minuman: lukisMinuman };

/** Pulangkan markup SVG untuk sesuatu item menu. */
function lukisItem(item) {
  const art = item.art || {};
  const fn = PELUKIS[art.jenis] || lukisBurger;
  return fn(art);
}
