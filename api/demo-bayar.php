<?php
/* ==========================================================================
   GET/POST api/demo-bayar.php
   --------------------------------------------------------------------------
   Halaman pembayaran TIRUAN untuk laman demo.

   Ia menggantikan halaman Bayarcash supaya aliran penuh — cart → pilih bank →
   skrin resit — boleh ditunjukkan kepada bakal pelanggan tanpa akaun
   Bayarcash, tanpa sandbox, dan tanpa duit.

   TIADA duit bergerak. TIADA panggilan ke Bayarcash. Tiada apa-apa di sini
   yang menyentuh transaksi sebenar.

   PERLINDUNGAN — halaman ini boleh menandakan order sebagai "dibayar", jadi ia
   menolak untuk berfungsi melainkan KESEMUA syarat ini benar:

     1. Tetapan::modDemo() benar — iaitu bendera demo dipasang DAN tiada
        kredensial Bayarcash sebenar dikonfigurasi.
     2. Rekod order itu sendiri ditanda 'demo' => true semasa ia dicipta.
        Order sebenar yang dicipta sebelum ini tidak boleh disentuh.
     3. Order belum lagi berstatus Berjaya.

   Kalau mana-mana gagal, ia memulangkan 403 tanpa mengubah apa-apa.
   ========================================================================== */

declare(strict_types=1);

require_once __DIR__ . '/lib/http.php';
require_once __DIR__ . '/lib/order.php';

$tetapan = new Tetapan();
$order   = new Order($tetapan);

/* ----------------------------- Perlindungan ------------------------------ */

if (!$tetapan->modDemo()) {
    http_response_code(403);
    exit('Mod demo tidak aktif.');
}

$nombor = (string) ($_GET['order'] ?? $_POST['order'] ?? '');
$rekod  = $order->ambil($nombor);

if ($rekod === null) {
    http_response_code(404);
    exit('Order tidak dijumpai.');
}

if (empty($rekod['demo'])) {
    // Order sebenar — jangan sentuh, walau apa pun keadaan tetapan sekarang.
    http_response_code(403);
    exit('Order ini bukan order demo.');
}

$asas = rtrim($tetapan->urlAsas(), '/');

/* --------------------------- Terima keputusan ---------------------------- */

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!had_kadar('demo-bayar', 60, 600)) {
        http_response_code(429);
        exit('Terlalu banyak cubaan. Cuba lagi sebentar.');
    }

    $jadi   = ($_POST['keputusan'] ?? '') === 'berjaya';
    $status = $jadi ? Bayarcash::BERJAYA : Bayarcash::GAGAL;

    $order->kemasStatus($nombor, $status, [
        'transaction_id'            => 'DEMO-' . substr(hash('sha256', $nombor), 0, 12),
        'exchange_reference_number' => 'DEMO-REF-' . substr(hash('sha256', $nombor . 'ref'), 0, 8),
        'payer_bank_name'           => 'Bank Demo (tiruan)',
        'status'                    => $status,
    ]);

    header('Location: ' . $asas . '/?order=' . rawurlencode($nombor));
    exit;
}

/* ------------------------------- Paparan --------------------------------- */

$jumlah  = number_format((float) ($rekod['jumlah'] ?? 0), 2, '.', '');
$saluran = (string) ($rekod['saluran_nama'] ?? 'FPX');
$nama    = (string) ($rekod['pelanggan']['nama'] ?? '');

header('Content-Type: text/html; charset=utf-8');
?>
<!doctype html>
<html lang="ms">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Pembayaran Demo — <?= h($nombor) ?></title>
<style>
  :root { color-scheme: dark; }
  * { box-sizing: border-box; }
  body {
    margin: 0; min-height: 100vh; display: grid; place-items: center; padding: 24px;
    background: #0b0f19; color: #e8ecf4;
    font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
  }
  .kad {
    width: 100%; max-width: 460px; background: #131a2a;
    border: 1px solid #26304a; border-radius: 18px; overflow: hidden;
  }
  .amaran {
    background: #4a3410; color: #ffd48a; padding: 14px 20px;
    font-size: .88rem; font-weight: 600; line-height: 1.5;
    border-bottom: 1px solid #6b4a15;
  }
  .isi { padding: 24px; }
  h1 { margin: 0 0 4px; font-size: 1.15rem; }
  .lemah { color: #93a0bd; font-size: .85rem; margin: 0 0 20px; }
  dl { margin: 0 0 24px; display: grid; grid-template-columns: auto 1fr; gap: 10px 16px; font-size: .92rem; }
  dt { color: #93a0bd; }
  dd { margin: 0; text-align: right; font-weight: 600; }
  .jumlah { font-size: 1.5rem; }
  button {
    width: 100%; padding: 14px; border: 0; border-radius: 12px; cursor: pointer;
    font-size: 1rem; font-weight: 700; font-family: inherit; margin-bottom: 10px;
  }
  .ya { background: #16a34a; color: #fff; }
  .tak { background: transparent; color: #ff9db3; border: 1px solid #5c2333; }
  .nota { margin: 16px 0 0; font-size: .78rem; color: #6f7c99; line-height: 1.6; }
</style>
</head>
<body>
  <div class="kad">
    <div class="amaran">
      ⚠️ HALAMAN DEMO — ini bukan pembayaran sebenar.<br>
      Tiada duit bergerak dan tiada bank terlibat.
    </div>
    <div class="isi">
      <h1>Sahkan Pembayaran</h1>
      <p class="lemah"><?= h($saluran) ?></p>

      <dl>
        <dt>Order</dt><dd><?= h($nombor) ?></dd>
        <?php if ($nama !== ''): ?>
        <dt>Nama</dt><dd><?= h($nama) ?></dd>
        <?php endif; ?>
        <dt>Jumlah</dt><dd class="jumlah">RM <?= h($jumlah) ?></dd>
      </dl>

      <form method="post">
        <input type="hidden" name="order" value="<?= h($nombor) ?>">
        <button class="ya"  type="submit" name="keputusan" value="berjaya">Bayar RM <?= h($jumlah) ?></button>
        <button class="tak" type="submit" name="keputusan" value="gagal">Batal / bayaran gagal</button>
      </form>

      <p class="nota">
        Kedua-dua butang berfungsi — guna "Batal" untuk menunjukkan bagaimana
        sistem mengendalikan pembayaran yang gagal.
      </p>
    </div>
  </div>
</body>
</html>
