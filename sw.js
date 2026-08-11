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

   Aset statik guna "hidang dahulu, kemas kini di belakang". Tiada langkah
   build dalam projek ini, jadi tiada nama fail bercap versi — cara ini
   memastikan perubahan sampai pada lawatan berikutnya tanpa memaksa
   pelanggan menunggu rangkaian setiap kali.
   ========================================================================== */

const VERSI = 'orderdyno-v1';
const RANGKA = VERSI + '-rangka';

/* Cukup untuk membuka laman dan memaparkan menu tanpa talian */
const PRACACHE = [
  './',
  './index.html',
  './assets/css/style.css',
  './assets/js/config.js',
  './assets/js/contoh-menu.js',
  './assets/js/store.js',
  './assets/js/bayar.js',
  './assets/js/app.js',
  './assets/js/editor.js',
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
    e.respondWith(cacheDahulu(perminta));
  }
});
