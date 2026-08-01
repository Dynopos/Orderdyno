<?php
/* ==========================================================================
   api/pentadbir.php — PANEL PENTADBIR (untuk anda, penjual)
   --------------------------------------------------------------------------
   Satu tempat untuk mencipta dan mengurus kedai pelanggan. Setiap kedai
   mendapat subdomainnya sendiri dan kunci pemiliknya sendiri.

   Ini BUKAN panel pemilik kedai. Pemilik kedai guna butang "Edit Menu" pada
   laman mereka, dengan kunci yang anda berikan dari sini.

   Akses: api/config.php mesti mengandungi 'kunci_pentadbir'. Kalau tiada,
   halaman ini mati sepenuhnya — jadi pemasangan yang tidak menjual apa-apa
   tidak mendedahkan apa-apa.
   ========================================================================== */

declare(strict_types=1);

require_once __DIR__ . '/lib/http.php';
require_once __DIR__ . '/lib/tetapan.php';
require_once __DIR__ . '/lib/order.php';

/* ------------------------------- Akses ----------------------------------- */

$configFail = __DIR__ . '/config.php';
$config     = is_file($configFail) ? (array) require $configFail : [];
$kunciBetul = trim((string) ($config['kunci_pentadbir'] ?? ''));

if ($kunciBetul === '') {
    http_response_code(404);
    exit('Panel pentadbir tidak diaktifkan.');
}

$kunciDiberi = (string) ($_POST['kunci'] ?? $_GET['kunci'] ?? '');
$masuk = $kunciDiberi !== '' && hash_equals($kunciBetul, $kunciDiberi);

if ($kunciDiberi !== '' && !$masuk) {
    // Perlahankan tekaan
    usleep(400000);
    if (!had_kadar('pentadbir', 12, 600)) {
        http_response_code(429);
        exit('Terlalu banyak cubaan. Cuba lagi dalam 10 minit.');
    }
}

/* ------------------------------- Aksi ------------------------------------ */

$mesej = '';
$jenis = 'ok';
$kunciBaharu = null;   // dipaparkan sekali sahaja selepas cipta/putar

if ($masuk && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $aksi = (string) ($_POST['aksi'] ?? '');

    try {
        switch ($aksi) {
            case 'cipta': {
                $rekod = Kedai::cipta(
                    (string) ($_POST['slug'] ?? ''),
                    (string) ($_POST['nama'] ?? '')
                );
                $kunciBaharu = ['slug' => $rekod['slug'], 'kunci' => $rekod['kunci']];
                $mesej = 'Kedai "' . $rekod['nama'] . '" dicipta.';
                break;
            }

            case 'nama': {
                $slug = (string) ($_POST['slug'] ?? '');
                Kedai::kemas($slug, ['nama' => (string) ($_POST['nama'] ?? '')]);
                $mesej = 'Nama kedai dikemas kini.';
                break;
            }

            case 'aktif': {
                $slug = (string) ($_POST['slug'] ?? '');
                $ke   = ($_POST['ke'] ?? '') === '1';
                Kedai::kemas($slug, ['aktif' => $ke]);
                $mesej = $ke ? 'Kedai diaktifkan semula.' : 'Kedai digantung — pelawat nampak mesej "belum dibuka".';
                break;
            }

            case 'kunci': {
                $slug = (string) ($_POST['slug'] ?? '');
                Kedai::kemas($slug, ['kunci_baru' => true]);
                $rekod = Kedai::ambil($slug);
                $kunciBaharu = ['slug' => $slug, 'kunci' => (string) ($rekod['kunci'] ?? '')];
                $mesej = 'Kunci baharu dijana. Kunci lama tidak boleh digunakan lagi.';
                break;
            }

            case 'padam': {
                $slug = (string) ($_POST['slug'] ?? '');
                if ((string) ($_POST['sahkan'] ?? '') !== $slug) {
                    throw new InvalidArgumentException('Taip alamat kedai dengan tepat untuk mengesahkan pemadaman.');
                }
                Kedai::padam($slug);
                $mesej = "Kedai '$slug' dan semua datanya dipadam.";
                break;
            }

            default:
                throw new InvalidArgumentException('Aksi tidak dikenali.');
        }
    } catch (Throwable $e) {
        $mesej = $e->getMessage();
        $jenis = 'silap';
    }
}

/* --------------------------- Kumpul maklumat ------------------------------ */

/** Ringkasan setiap kedai — menu diterbitkan? bayaran siap? berapa order? */
function ringkasan(string $slug): array
{
    $dir = Kedai::dirUntuk($slug);
    $menu = SimpananSelamat::cari($dir . '/menu.php');
    $bayar = SimpananSelamat::cari($dir . '/bayarcash.php');

    $bilItem = 0;
    if ($menu !== null) {
        $mentah = SimpananSelamat::bacaMentah($menu);
        $d = $mentah === null ? null : json_decode($mentah, true);
        $bilItem = is_array($d['menu'] ?? null) ? count($d['menu']) : 0;
    }

    $order = 0;
    $dirOrder = $dir . '/orders';
    if (is_dir($dirOrder)) {
        $order = count(SimpananSelamat::senarai($dirOrder));
    }

    $adaKredensial = false;
    if ($bayar !== null) {
        $b = SimpananSelamat::baca($bayar) ?? [];
        $adaKredensial = trim((string) ($b['pat'] ?? '')) !== ''
            && trim((string) ($b['secret_key'] ?? '')) !== ''
            && trim((string) ($b['portal_key'] ?? '')) !== '';
    }

    return ['item' => $bilItem, 'order' => $order, 'bayar' => $adaKredensial];
}

$senarai = Kedai::semua();
ksort($senarai);
$domainAsas = Kedai::domainAsas();

header('Content-Type: text/html; charset=utf-8');
header('X-Robots-Tag: noindex, nofollow');
?>
<!doctype html>
<html lang="ms">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Panel Pentadbir — OrderDyno</title>
<style>
  :root { color-scheme: dark; }
  * { box-sizing: border-box; }
  body {
    margin: 0; padding: 24px 16px 64px; background: #0a0e17; color: #e6ebf5;
    font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; line-height: 1.55;
  }
  .bekas { max-width: 940px; margin: 0 auto; }
  h1 { font-size: 1.4rem; margin: 0 0 4px; }
  .sub { color: #8b98b8; font-size: .9rem; margin: 0 0 28px; }
  .kotak { background: #121a2b; border: 1px solid #23304d; border-radius: 14px; padding: 20px; margin-bottom: 20px; }
  .kotak h2 { font-size: 1rem; margin: 0 0 14px; }
  label { display: block; font-size: .8rem; color: #8b98b8; margin: 0 0 6px; text-transform: uppercase; letter-spacing: .04em; }
  input[type=text], input[type=password] {
    width: 100%; padding: 11px 13px; border-radius: 10px; border: 1px solid #2c3a5c;
    background: #0d1422; color: #e6ebf5; font-size: .95rem; font-family: inherit;
  }
  input:focus { outline: 2px solid #4c7dff; outline-offset: 1px; }
  .baris { display: flex; gap: 12px; flex-wrap: wrap; margin-bottom: 14px; }
  .baris > div { flex: 1; min-width: 200px; }
  button {
    padding: 11px 18px; border: 0; border-radius: 10px; cursor: pointer;
    font-size: .92rem; font-weight: 650; font-family: inherit; background: #4c7dff; color: #fff;
  }
  button.halus { background: #22304d; color: #cfd9ee; }
  button.bahaya { background: #3a1620; color: #ff9db3; }
  .mesej { padding: 13px 16px; border-radius: 10px; margin-bottom: 20px; font-size: .92rem; }
  .mesej.ok { background: #10301d; color: #a9edc4; border: 1px solid #1d5c37; }
  .mesej.silap { background: #331520; color: #ffa9bd; border: 1px solid #6b2338; }

  .kunci { font-family: ui-monospace, Menlo, Consolas, monospace; font-size: .95rem;
           background: #0d1422; padding: 12px 14px; border-radius: 8px; word-break: break-all;
           border: 1px solid #2c3a5c; margin: 8px 0; user-select: all; }
  table { width: 100%; border-collapse: collapse; font-size: .9rem; }
  th, td { text-align: left; padding: 12px 10px; border-bottom: 1px solid #1d2740; vertical-align: top; }
  th { color: #8b98b8; font-weight: 600; font-size: .78rem; text-transform: uppercase; letter-spacing: .04em; }
  .pautan { color: #7fa4ff; text-decoration: none; }
  .pautan:hover { text-decoration: underline; }
  .lencana { display: inline-block; padding: 2px 9px; border-radius: 999px; font-size: .74rem; font-weight: 650; }
  .hijau { background: #10301d; color: #a9edc4; }
  .kelabu { background: #202a40; color: #8b98b8; }
  .kuning { background: #3a2f10; color: #f2d99b; }
  details { margin-top: 8px; }
  summary { cursor: pointer; color: #8b98b8; font-size: .85rem; }
  details form { margin-top: 10px; display: flex; gap: 8px; flex-wrap: wrap; align-items: center; }
  details input { max-width: 220px; }
  .nota { color: #6f7d9c; font-size: .82rem; }
  code { background: #0d1422; padding: 2px 6px; border-radius: 5px; font-size: .88em; }
</style>
</head>
<body>
<div class="bekas">

<?php if (!$masuk): ?>

  <h1>Panel Pentadbir</h1>
  <p class="sub">Masukkan kunci pentadbir untuk meneruskan.</p>

  <?php if ($kunciDiberi !== ''): ?>
    <div class="mesej silap">Kunci pentadbir salah.</div>
  <?php endif; ?>

  <form class="kotak" method="post">
    <label for="k">Kunci pentadbir</label>
    <input type="password" id="k" name="kunci" autocomplete="current-password" autofocus>
    <p></p>
    <button type="submit">Masuk</button>
  </form>

<?php else: ?>

  <h1>Panel Pentadbir</h1>
  <p class="sub">
    <?= count($senarai) ?> kedai
    <?php if ($domainAsas !== ''): ?>· <code>*.<?= h($domainAsas) ?></code><?php endif; ?>
  </p>

  <?php if ($domainAsas === ''): ?>
    <div class="mesej silap">
      <b>'domain_asas' belum ditetapkan dalam api/config.php.</b><br>
      Tanpa itu, subdomain tidak dikesan dan semua kedai akan berkongsi data yang sama.
      Tambah <code>'domain_asas' =&gt; 'orderdyno.my',</code> ke dalam fail itu.
    </div>
  <?php endif; ?>

  <?php if ($mesej !== ''): ?>
    <div class="mesej <?= h($jenis) ?>"><?= h($mesej) ?></div>
  <?php endif; ?>

  <?php if ($kunciBaharu !== null): ?>
    <div class="kotak" style="border-color:#3d5c8f">
      <h2>Kunci pemilik untuk <?= h($kunciBaharu['slug']) ?></h2>
      <p class="nota" style="margin-top:0">
        Berikan kunci ini kepada pemilik kedai. Ia dipaparkan <b>sekali sahaja</b> —
        salin sekarang. Kalau hilang, jana yang baharu dari senarai di bawah.
      </p>
      <div class="kunci"><?= h($kunciBaharu['kunci']) ?></div>
      <?php if ($domainAsas !== ''): ?>
        <p class="nota">Laman mereka: <a class="pautan" href="<?= h(Kedai::urlUntuk($kunciBaharu['slug'])) ?>" target="_blank" rel="noopener"><?= h(Kedai::urlUntuk($kunciBaharu['slug'])) ?></a></p>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <div class="kotak">
    <h2>Kedai baharu</h2>
    <form method="post">
      <input type="hidden" name="kunci" value="<?= h($kunciDiberi) ?>">
      <input type="hidden" name="aksi" value="cipta">
      <div class="baris">
        <div>
          <label for="nama">Nama kedai</label>
          <input type="text" id="nama" name="nama" placeholder="Restoran Nasi Lemak Ali" required>
        </div>
        <div>
          <label for="slug">Alamat (subdomain)</label>
          <input type="text" id="slug" name="slug" placeholder="nasilemakali" required
                 pattern="[a-z0-9-]+" title="Huruf kecil, nombor dan sengkang sahaja">
        </div>
      </div>
      <p class="nota" style="margin:0 0 14px">
        Laman mereka akan jadi
        <code><span id="pratonton">nama</span>.<?= h($domainAsas !== '' ? $domainAsas : 'domain-anda') ?></code>.
        Huruf kecil, nombor dan sengkang sahaja.
      </p>
      <button type="submit">Cipta kedai</button>
    </form>
  </div>

  <div class="kotak">
    <h2>Kedai pelanggan</h2>
    <?php if (!$senarai): ?>
      <p class="nota">Belum ada kedai. Cipta yang pertama di atas.</p>
    <?php else: ?>
      <table>
        <thead>
          <tr>
            <th>Kedai</th><th>Menu</th><th>Bayaran</th><th>Order</th><th>Status</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($senarai as $slug => $k):
            $r = ringkasan($slug);
            $url = Kedai::urlUntuk($slug);
            $aktif = !empty($k['aktif']);
        ?>
          <tr>
            <td>
              <b><?= h((string) $k['nama']) ?></b><br>
              <?php if ($url !== ''): ?>
                <a class="pautan" href="<?= h($url) ?>" target="_blank" rel="noopener"><?= h($slug) ?>.<?= h($domainAsas) ?> ↗</a>
              <?php else: ?>
                <span class="nota"><?= h($slug) ?></span>
              <?php endif; ?>

              <details>
                <summary>Urus</summary>

                <form method="post">
                  <input type="hidden" name="kunci" value="<?= h($kunciDiberi) ?>">
                  <input type="hidden" name="aksi" value="nama">
                  <input type="hidden" name="slug" value="<?= h($slug) ?>">
                  <input type="text" name="nama" value="<?= h((string) $k['nama']) ?>" required>
                  <button class="halus" type="submit">Tukar nama</button>
                </form>

                <form method="post">
                  <input type="hidden" name="kunci" value="<?= h($kunciDiberi) ?>">
                  <input type="hidden" name="aksi" value="kunci">
                  <input type="hidden" name="slug" value="<?= h($slug) ?>">
                  <button class="halus" type="submit">Jana kunci pemilik baharu</button>
                </form>

                <form method="post">
                  <input type="hidden" name="kunci" value="<?= h($kunciDiberi) ?>">
                  <input type="hidden" name="aksi" value="aktif">
                  <input type="hidden" name="slug" value="<?= h($slug) ?>">
                  <input type="hidden" name="ke" value="<?= $aktif ? '0' : '1' ?>">
                  <button class="halus" type="submit"><?= $aktif ? 'Gantung kedai' : 'Aktifkan semula' ?></button>
                </form>

                <form method="post" onsubmit="return confirm('Padam kedai ini dan SEMUA datanya? Tidak boleh dibatalkan.')">
                  <input type="hidden" name="kunci" value="<?= h($kunciDiberi) ?>">
                  <input type="hidden" name="aksi" value="padam">
                  <input type="hidden" name="slug" value="<?= h($slug) ?>">
                  <input type="text" name="sahkan" placeholder="Taip '<?= h($slug) ?>'" required>
                  <button class="bahaya" type="submit">Padam kekal</button>
                </form>
              </details>
            </td>
            <td><?= $r['item'] ? h((string) $r['item']) . ' item' : '<span class="nota">belum</span>' ?></td>
            <td>
              <?= $r['bayar']
                    ? '<span class="lencana hijau">aktif</span>'
                    : '<span class="lencana kelabu">belum</span>' ?>
            </td>
            <td><?= h((string) $r['order']) ?></td>
            <td>
              <?= $aktif
                    ? '<span class="lencana hijau">aktif</span>'
                    : '<span class="lencana kuning">digantung</span>' ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>

  <p class="nota">
    Pemilik kedai membuka laman mereka sendiri, tekan <b>Edit Menu</b>, dan
    masukkan kunci pemilik yang anda berikan. Mereka tidak boleh melihat kedai
    lain, dan tidak boleh membuka panel ini.
  </p>

  <script>
    var slug = document.getElementById('slug');
    var pratonton = document.getElementById('pratonton');
    if (slug && pratonton) {
      slug.addEventListener('input', function () {
        var v = slug.value.toLowerCase().replace(/[^a-z0-9-]/g, '');
        slug.value = v;
        pratonton.textContent = v || 'nama';
      });
    }
  </script>

<?php endif; ?>

</div>
</body>
</html>
