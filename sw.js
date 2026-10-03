/* ==========================================================================
   OrderDyno — SERVICE WORKER
   --------------------------------------------------------------------------
   Membolehkan laman kedai dipasang pada skrin utama telefon dan dibuka
   walaupun talian putus.

   Peraturan cache di sini bukan sekadar prestasi — ia soal wang:

     · /api/  tidak pernah dicache, kecuali menu-awam.php. Order, pembayaran,
       status transaksi dan panel admin mesti sentiasa datang dari server.
       Resit yang dicache adalah resit yang menipu.

     · menu-awam.php guna "rangkaian dahulu". Pelanggan yang ada talian
       sentiasa nampak harga terkini. Salinan cache hanya digunakan bila
       talian putus — dan pada ketika itu storefront memaparkan jalur
       "anda sedang offline" serta menyembunyikan butang bayar.

     · Harga tetap dikira semula di server semasa bayaran, jadi menu lama
       dalam cache tidak boleh menjadi harga yang dibayar.

     · CSS dan JS guna "rangkaian dahulu" atas sebab yang sama. Dahulu ia
       "hidang dahulu, kemas kini di belakang", dan itu memecahkan laman
       selepas setiap deploy: halaman (navigasi) diambil segar dari rangkaian
       sementara CSS dihidangkan dari cache lama. Pelanggan mendapat HTML
       baharu dengan gaya lama — kotak carian putih tanpa gaya, susun atur
       yang pecah. Tiada langkah build di sini, jadi tiada nama fail bercap
       versi yang boleh membezakan keduanya; satu-satunya jalan ialah
       memastikan HTML dan asetnya datang dari tempat yang sama.

     · Imej masih "hidang dahulu" — ia tidak pernah berubah tanpa nama baharu,
       dan ia yang paling berat.

   Cache kekal sebagai sandaran offline untuk semuanya.
   ========================================================================== */

/* Naikkan nombor ini bila strategi cache berubah — 'activate' membuang semua
   cache versi lama, jadi salinan basi tidak boleh hidup melepasi deploy. */
const VERSI = 'orderdyno-v4';
const RANGKA = VERSI + '-rangka';

/* Cukup untuk membuka laman dan memaparkan menu tanpa talian */
/* Mesti sepadan dengan ?v=N pada index.html. Alamat yang berubah setiap kali
   fail berubah bermakna cache lama tidak mungkin dipadankan langsung — itu
   perlindungan yang tidak bergantung pada service worker berkelakuan betul. */
const ASET_VERSI = '?v=4';

const PRACACHE = [
  './',
  './index.html',
  './assets/css/style.css' + ASET_VERSI,
  './assets/js/config.js' + ASET_VERSI,
  './assets/js/contoh-menu.js' + ASET_VERSI,
  './assets/js/store.js' + ASET_VERSI,
  './assets/js/bayar.js' + ASET_VERSI,
  './assets/js/app.js' + ASET_VERSI,
  './assets/js/editor.js' + ASET_VERSI,
  /* Ikon aplikasi — mesti ada tanpa talian juga. */
  './assets/img/lambang.png',
  './assets/img/ikon-192.png',
];

self.addEventListener('install', (e) => {
  e.waitUntil(
    caches.open(RANGKA)
      /* Satu fail yang gagal tidak boleh menggagalkan keseluruhan pemasangan */
      .then((c) => Promise.allSettled(PRACACHE.map((u) => c.add(u))))
      .then(() => self.skipWaiting())
  );
});

self.addEventListener('activate', (e) => {
  e.waitUntil(
    caches.keys()
      .then((nama) => Promise.all(
        nama.filter((n) => n !== RANGKA).map((n) => caches.delete(n))
      ))
      .then(() => self.clients.claim())
  );
});

const simpan = (perminta, jawapan) => {
  if (jawapan && jawapan.ok && jawapan.type === 'basic') {
    const salinan = jawapan.clone();
    caches.open(RANGKA).then((c) => c.put(perminta, salinan));
  }
  return jawapan;
};

/* Rangkaian dahulu; cache hanya bila rangkaian gagal */
async function rangkaianDahulu(perminta) {
  try {
    return simpan(perminta, await fetch(perminta));
  } catch (e) {
    const cache = await caches.match(perminta);
    if (cache) return cache;
    throw e;
  }
}

/* Hidang dari cache serta-merta, kemas kini salinan di belakang */
async function cacheDahulu(perminta) {
  const cache = await caches.match(perminta);
  const rangkaian = fetch(perminta).then((r) => simpan(perminta, r)).catch(() => null);
  return cache || rangkaian.then((r) => r || Promise.reject(new Error('offline')));
}

self.addEventListener('fetch', (e) => {
  const perminta = e.request;

  /* POST order dan bayaran tidak boleh disentuh langsung */
  if (perminta.method !== 'GET') return;

  const url = new URL(perminta.url);
  if (url.origin !== self.location.origin) return;   // font luar dsb.

  if (url.pathname.includes('/api/')) {
    /* Satu-satunya API yang selamat dicache: menu awam, dan hanya sebagai
       sandaran bila offline. */
    if (url.pathname.endsWith('/menu-awam.php')) {
      e.respondWith(rangkaianDahulu(perminta));
    }
    return;                                          // selebihnya: rangkaian sahaja
  }

  if (perminta.mode === 'navigate') {
    e.respondWith(rangkaianDahulu(perminta));
    return;
  }

  if (url.pathname.includes('/assets/')) {
    /* Imej tidak berubah tanpa nama baharu, jadi ia selamat dihidangkan dari
       cache serta-merta. CSS dan JS mesti sepadan dengan HTML yang baru
       diambil dari rangkaian — kalau tidak, deploy memberi pelanggan halaman
       baharu dengan gaya lama. */
    const imej = /\.(png|jpe?g|webp|gif|svg|ico|woff2?)$/i.test(url.pathname);
    e.respondWith(imej ? cacheDahulu(perminta) : rangkaianDahulu(perminta));
  }
});
