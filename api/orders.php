<?php
/* ==========================================================================
   GET api/orders.php?key=KUNCI_ADMIN
   --------------------------------------------------------------------------
   Senarai order ringkas untuk pemilik kedai. Perlukan kunci admin dari
   api/config.php. Tambah &json=1 untuk dapatkan JSON.
   ========================================================================== */

declare(strict_types=1);

require_once __DIR__ . '/lib/http.php';
require_once __DIR__ . '/lib/order.php';

$tetapan = new Tetapan();

if (!had_kadar('orders', 30, 600)) {
    http_response_code(429);
    exit('Terlalu banyak cubaan. Cuba lagi dalam 10 minit.');
}

if (!$tetapan->kunciSah((string) ($_GET['key'] ?? ''))) {
    usleep(300000);
    http_response_code(401);
    header('Content-Type: text/html; charset=utf-8');
    exit('<!doctype html><meta charset="utf-8"><title>Akses ditolak</title>'
        . '<p style="font:16px system-ui;padding:40px">Kunci admin salah atau belum ditetapkan dalam <code>api/config.php</code>.</p>');
}

$order   = new Order($tetapan);
$senarai = $order->senarai(200);

/* ------------------------------- JSON ----------------------------------- */
if (!empty($_GET['json'])) {
    json_keluar(['ok' => true, 'bilangan' => count($senarai), 'orders' => $senarai]);
}

/* ------------------------------- HTML ----------------------------------- */

function h(?string $t): string
{
    return htmlspecialchars((string) $t, ENT_QUOTES, 'UTF-8');
}

$berjaya = array_filter($senarai, static fn ($o) => (int) ($o['status'] ?? 0) === Bayarcash::BERJAYA);
$jumlahTerkumpul = array_sum(array_map(static fn ($o) => (float) ($o['jumlah'] ?? 0), $berjaya));

$warnaStatus = [
    Bayarcash::BARU     => '#b3a4c9',
    Bayarcash::MENUNGGU => '#ffc93c',
    Bayarcash::GAGAL    => '#ff6b8a',
    Bayarcash::BERJAYA  => '#4ade80',
    Bayarcash::DIBATAL  => '#b3a4c9',
];

header('Content-Type: text/html; charset=utf-8');
header('X-Robots-Tag: noindex, nofollow');
?>
<!doctype html>
<html lang="ms">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Order — OrderDyno</title>
<style>
  :root { color-scheme: dark; }
  * { box-sizing: border-box; }
  body { margin:0; padding:24px 18px 60px; background:#0b0616; color:#f6f0ff;
         font:15px/1.55 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif; }
  .bekas { max-width:1080px; margin-inline:auto; }
  h1 { margin:0 0 4px; font-size:1.5rem; }
  .sub { margin:0 0 26px; color:#b3a4c9; font-size:.86rem; }
  .ringkas { display:grid; grid-template-columns:repeat(auto-fit,minmax(170px,1fr)); gap:12px; margin-bottom:26px; }
  .kotak { padding:16px 18px; border-radius:16px; background:rgba(255,255,255,.045); border:1px solid rgba(255,255,255,.09); }
  .kotak b { display:block; font-size:1.5rem; margin-bottom:2px; }
  .kotak span { font-size:.76rem; letter-spacing:.1em; text-transform:uppercase; color:#b3a4c9; }
  table { width:100%; border-collapse:collapse; font-size:.86rem; }
  th, td { text-align:left; padding:11px 12px; border-bottom:1px solid rgba(255,255,255,.08); vertical-align:top; }
  th { font-size:.72rem; letter-spacing:.1em; text-transform:uppercase; color:#b3a4c9; font-weight:700; }
  tr:hover td { background:rgba(255,255,255,.03); }
  .pil { display:inline-block; padding:3px 10px; border-radius:99px; font-size:.72rem; font-weight:700;
         border:1px solid currentColor; }
  .wang { font-weight:800; white-space:nowrap; }
  .kecil { color:#b3a4c9; font-size:.78rem; }
  .bungkus { overflow-x:auto; border-radius:16px; border:1px solid rgba(255,255,255,.09); background:rgba(255,255,255,.03); }
  code { font-size:.8rem; background:rgba(0,0,0,.35); padding:2px 6px; border-radius:5px; }
  .kosong { padding:50px 20px; text-align:center; color:#b3a4c9; }
</style>
</head>
<body>
<div class="bekas">

  <h1>Order</h1>
  <p class="sub">
    <?= h($tetapan->persekitaran() === 'sandbox' ? 'Mod SANDBOX — pembayaran ujian sahaja' : 'Mod PRODUCTION — pembayaran sebenar') ?>
    · 200 order terkini
  </p>

  <div class="ringkas">
    <div class="kotak"><b><?= count($senarai) ?></b><span>Jumlah order</span></div>
    <div class="kotak"><b style="color:#4ade80"><?= count($berjaya) ?></b><span>Berjaya dibayar</span></div>
    <div class="kotak"><b>RM <?= h(number_format($jumlahTerkumpul, 2)) ?></b><span>Terkumpul</span></div>
  </div>

  <?php if (!$senarai): ?>
    <div class="bungkus"><p class="kosong">Belum ada order lagi.</p></div>
  <?php else: ?>
  <div class="bungkus">
    <table>
      <thead>
        <tr>
          <th>Order</th><th>Masa</th><th>Pelanggan</th><th>Item</th>
          <th>Cara</th><th>Status</th><th style="text-align:right">Jumlah</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($senarai as $o):
        $status = (int) ($o['status'] ?? 0);
        $warna  = $warnaStatus[$status] ?? '#b3a4c9';
        $trx    = $o['transaksi'] ?? [];
        $akhir  = is_array($trx) && $trx ? $trx[count($trx) - 1] : [];
      ?>
        <tr>
          <td>
            <code><?= h($o['order_number'] ?? '') ?></code>
            <?php if (!empty($akhir['exchange_reference_number'])): ?>
              <div class="kecil">Ruj: <?= h($akhir['exchange_reference_number']) ?></div>
            <?php endif; ?>
          </td>
          <td class="kecil"><?= h(str_replace('T', ' ', substr((string) ($o['dibuat'] ?? ''), 0, 16))) ?> UTC</td>
          <td>
            <?= h($o['pelanggan']['nama'] ?? '') ?>
            <div class="kecil"><?= h($o['pelanggan']['telefon'] ?? '') ?></div>
            <div class="kecil"><?= h($o['pelanggan']['email'] ?? '') ?></div>
            <?php if (!empty($o['pelanggan']['alamat'])): ?>
              <div class="kecil"><?= h($o['pelanggan']['alamat']) ?></div>
            <?php endif; ?>
          </td>
          <td>
            <?php foreach ($o['baris'] ?? [] as $b): ?>
              <div>
                <?= (int) $b['kuantiti'] ?>× <?= h($b['nama']) ?><?= $b['variasi'] ? ' (' . h($b['variasi']) . ')' : '' ?>
                <?php if (!empty($b['tambahan'])): ?>
                  <div class="kecil">+ <?= h(implode(', ', array_column($b['tambahan'], 'nama'))) ?></div>
                <?php endif; ?>
                <?php if (!empty($b['nota'])): ?>
                  <div class="kecil">📝 <?= h($b['nota']) ?></div>
                <?php endif; ?>
              </div>
            <?php endforeach; ?>
            <?php if (!empty($o['pelanggan']['nota'])): ?>
              <div class="kecil">Nota order: <?= h($o['pelanggan']['nota']) ?></div>
            <?php endif; ?>
          </td>
          <td class="kecil">
            <?= h(($o['cara'] ?? '') === 'delivery' ? 'Penghantaran' : 'Ambil sendiri') ?>
            <div><?= h($o['saluran_nama'] ?? '') ?></div>
          </td>
          <td><span class="pil" style="color:<?= h($warna) ?>"><?= h($o['status_label'] ?? Bayarcash::labelStatus($status)) ?></span>
            <?php if (!empty($akhir['payer_bank_name'])): ?>
              <div class="kecil"><?= h($akhir['payer_bank_name']) ?></div>
            <?php endif; ?>
          </td>
          <td class="wang" style="text-align:right">
            <?= h($o['mata_wang'] ?? 'MYR') ?> <?= h($o['jumlah'] ?? '0.00') ?>
            <?php if (($o['caj_hantar'] ?? '0.00') !== '0.00'): ?>
              <div class="kecil">termasuk hantar <?= h($o['caj_hantar']) ?></div>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>

</div>
</body>
</html>
