<?php
/* ==========================================================================
   Order — pengesahan cart & simpanan rekod order
   --------------------------------------------------------------------------
   PENTING: jumlah bayaran dikira SEMULA di sini daripada menu yang
   tersimpan di server (api/data/menu.php). Jumlah yang dihantar oleh
   pelayar tidak pernah dipercayai — kalau tidak, sesiapa boleh ubah harga
   dalam devtools dan bayar RM 0.01.
   ========================================================================== */

declare(strict_types=1);

require_once __DIR__ . '/tetapan.php';

final class Order
{
    public function __construct(private Tetapan $tetapan)
    {
    }

    /* ============================== LOKASI ============================== */

    private static function dir(): string
    {
        return Tetapan::dirData() . '/orders';
    }

    private static function sediakanDir(): void
    {
        Tetapan::sediakanDirData();
        $dir = self::dir();
        if (!is_dir($dir)) {
            @mkdir($dir, 0750, true);
        }
    }

    /* Hanya benarkan nombor order yang selamat jadi nama fail */
    public static function nomborSah(string $nombor): bool
    {
        return (bool) preg_match('/^[A-Z0-9\-]{6,40}$/', $nombor);
    }

    private static function fail(string $nombor): string
    {
        return self::dir() . '/' . $nombor . '.php';
    }

    public static function nomborBaru(): string
    {
        return 'OD-' . gmdate('ymd') . '-' . strtoupper(bin2hex(random_bytes(4)));
    }

    /* ========================== PENGESAHAN CART ========================= */

    /**
     * Sahkan cart terhadap menu di server dan kira jumlah sebenar.
     *
     * @param array $cart  Baris dari pelayar: id, variasi, tambahan[], kuantiti, nota
     * @return array{baris:array,subtotal:float,caj:float,jumlah:float,cara:string}
     * @throws InvalidArgumentException bila cart tidak sah
     */
    public function sahkanCart(array $cart, string $cara): array
    {
        $menu = $this->tetapan->menuTersimpan();
        if ($menu === null) {
            throw new InvalidArgumentException('Menu belum diterbitkan. Pemilik kedai perlu buka tab Bayaran dan tekan "Terbitkan menu".');
        }

        if (!$cart) {
            throw new InvalidArgumentException('Cart kosong');
        }
        if (count($cart) > 60) {
            throw new InvalidArgumentException('Terlalu banyak baris dalam satu order');
        }

        $ikutId = [];
        foreach ($menu['menu'] as $item) {
            if (!empty($item['id'])) {
                $ikutId[(string) $item['id']] = $item;
            }
        }

        $maksKuantiti = $this->tetapan->maksKuantiti();
        $baris = [];
        $subtotal = 0.0;

        foreach ($cart as $masuk) {
            if (!is_array($masuk)) {
                throw new InvalidArgumentException('Baris cart tidak sah');
            }

            $id = (string) ($masuk['id'] ?? '');
            if (!isset($ikutId[$id])) {
                throw new InvalidArgumentException('Item tidak dijumpai dalam menu: ' . $id);
            }
            $item = $ikutId[$id];

            if (!empty($item['habis'])) {
                throw new InvalidArgumentException('Item sudah habis: ' . ($item['nama'] ?? $id));
            }

            $kuantiti = (int) ($masuk['kuantiti'] ?? 0);
            if ($kuantiti < 1 || $kuantiti > $maksKuantiti) {
                throw new InvalidArgumentException('Kuantiti tidak sah untuk ' . ($item['nama'] ?? $id));
            }

            /* --- Harga asas: dari pilihan kalau ada, kalau tidak dari item --- */
            $pilihan = is_array($item['pilihan'] ?? null) ? $item['pilihan'] : [];
            $variasi = trim((string) ($masuk['variasi'] ?? ''));

            if ($pilihan) {
                $jumpa = null;
                foreach ($pilihan as $p) {
                    if ((string) ($p['nama'] ?? '') === $variasi) {
                        $jumpa = $p;
                        break;
                    }
                }
                if ($jumpa === null) {
                    throw new InvalidArgumentException('Pilihan tidak sah untuk ' . ($item['nama'] ?? $id));
                }
                $hargaUnit = (float) ($jumpa['harga'] ?? 0);
            } else {
                if ($variasi !== '') {
                    throw new InvalidArgumentException('Item ini tiada pilihan: ' . ($item['nama'] ?? $id));
                }
                $hargaUnit = (float) ($item['harga'] ?? 0);
            }

            /* --- Tambahan: setiap satu mesti ada dalam menu --- */
            $tambahanSah = [];
            $senaraiTambahan = is_array($item['tambahan'] ?? null) ? $item['tambahan'] : [];
            foreach ((array) ($masuk['tambahan'] ?? []) as $namaTambahan) {
                $nama = trim((string) (is_array($namaTambahan) ? ($namaTambahan['nama'] ?? '') : $namaTambahan));
                $jumpa = null;
                foreach ($senaraiTambahan as $t) {
                    if ((string) ($t['nama'] ?? '') === $nama) {
                        $jumpa = $t;
                        break;
                    }
                }
                if ($jumpa === null) {
                    throw new InvalidArgumentException('Tambahan tidak sah untuk ' . ($item['nama'] ?? $id) . ': ' . $nama);
                }
                $tambahanSah[] = ['nama' => $nama, 'harga' => (float) ($jumpa['harga'] ?? 0)];
                $hargaUnit += (float) ($jumpa['harga'] ?? 0);
            }

            if ($hargaUnit <= 0) {
                throw new InvalidArgumentException('Harga tidak sah untuk ' . ($item['nama'] ?? $id));
            }

            $jumlahBaris = round($hargaUnit * $kuantiti, 2);
            $subtotal += $jumlahBaris;

            $baris[] = [
                'nama'       => (string) ($item['nama'] ?? $id),
                'variasi'    => $variasi,
                'tambahan'   => $tambahanSah,
                'nota'       => mb_substr(trim((string) ($masuk['nota'] ?? '')), 0, 200),
                'kuantiti'   => $kuantiti,
                'harga_unit' => round($hargaUnit, 2),
                'jumlah'     => $jumlahBaris,
            ];
        }

        /* ----------------------- Cara terima order ----------------------- */
        $hantar = $menu['penghantaran'] ?? [];
        $pickupAktif   = !empty($hantar['pickup']['aktif']);
        $deliveryAktif = !empty($hantar['delivery']['aktif']);

        if ($cara !== 'delivery' && $cara !== 'pickup') {
            $cara = $pickupAktif ? 'pickup' : 'delivery';
        }
        if ($cara === 'delivery' && !$deliveryAktif) {
            $cara = 'pickup';
        }
        if ($cara === 'pickup' && !$pickupAktif) {
            $cara = $deliveryAktif ? 'delivery' : 'pickup';
        }

        $caj = 0.0;
        if ($cara === 'delivery' && $deliveryAktif) {
            $caj = round((float) ($hantar['delivery']['caj'] ?? 0), 2);
            $min = (float) ($hantar['delivery']['minOrder'] ?? 0);
            if ($min > 0 && $subtotal < $min) {
                throw new InvalidArgumentException('Minimum order untuk penghantaran ialah ' . number_format($min, 2));
            }
        }

        $subtotal = round($subtotal, 2);
        $jumlah   = round($subtotal + $caj, 2);

        if ($jumlah < 1.0) {
            throw new InvalidArgumentException('Jumlah order terlalu kecil untuk pembayaran online (minimum 1.00)');
        }
        if ($jumlah > $this->tetapan->maksJumlah()) {
            throw new InvalidArgumentException('Jumlah order melebihi had yang dibenarkan');
        }

        return [
            'baris'    => $baris,
            'subtotal' => $subtotal,
            'caj'      => $caj,
            'jumlah'   => $jumlah,
            'cara'     => $cara,
        ];
    }

    /* Mata wang: Bayarcash memproses MYR */
    public function mataWang(): string
    {
        $menu = $this->tetapan->menuTersimpan();
        $simbol = strtoupper(trim((string) ($menu['kedai']['mataWang'] ?? 'RM')));
        return ($simbol === 'RM' || $simbol === 'MYR') ? 'MYR' : $simbol;
    }

    /* ============================== SIMPAN ============================== */

    public function simpan(array $order): void
    {
        self::sediakanDir();
        $nombor = (string) $order['order_number'];
        if (!self::nomborSah($nombor)) {
            throw new InvalidArgumentException('Nombor order tidak sah');
        }
        SimpananSelamat::tulis(self::fail($nombor), $order);
    }

    public function ambil(string $nombor): ?array
    {
        if (!self::nomborSah($nombor)) {
            return null;
        }
        $fail = SimpananSelamat::cari(self::fail($nombor));
        if ($fail === null) {
            return null;
        }
        return SimpananSelamat::baca($fail);
    }

    /**
     * Kemas kini status order dari data transaksi Bayarcash.
     * Hanya status 3 (Berjaya) menandakan order sebagai dibayar.
     */
    public function kemasStatus(string $nombor, int $status, array $transaksi = []): ?array
    {
        $order = $this->ambil($nombor);
        if ($order === null) {
            return null;
        }

        // Order yang sudah berjaya tidak boleh diturunkan semula statusnya
        $sudahBayar = (int) ($order['status'] ?? 0) === Bayarcash::BERJAYA;
        if (!$sudahBayar) {
            $order['status']       = $status;
            $order['status_label'] = Bayarcash::labelStatus($status);
            if ($status === Bayarcash::BERJAYA) {
                $order['dibayar_pada'] = gmdate('c');
            }
        }

        if ($transaksi) {
            $order['transaksi'] = $order['transaksi'] ?? [];
            $idBaru = (string) ($transaksi['transaction_id'] ?? '');
            $ada = false;
            foreach ($order['transaksi'] as $i => $lama) {
                if ((string) ($lama['transaction_id'] ?? '') === $idBaru && $idBaru !== '') {
                    $order['transaksi'][$i] = $transaksi;
                    $ada = true;
                    break;
                }
            }
            if (!$ada) {
                $order['transaksi'][] = $transaksi;
            }
        }

        $order['dikemas'] = gmdate('c');
        $this->simpan($order);
        return $order;
    }

    /* Senarai order terbaru (untuk halaman orders.php) */
    public function senarai(int $had = 100): array
    {
        $dir = self::dir();
        if (!is_dir($dir)) {
            return [];
        }
        $fail = SimpananSelamat::senarai($dir);
        usort($fail, static fn ($a, $b) => filemtime($b) <=> filemtime($a));
        $keluar = [];
        foreach (array_slice($fail, 0, $had) as $f) {
            $data = SimpananSelamat::baca($f);
            if ($data !== null) {
                $keluar[] = $data;
            }
        }
        return $keluar;
    }

    /* Buang order tidak berbayar yang sudah luput, supaya folder tak membesar */
    public function bersihLuput(): void
    {
        $dir = self::dir();
        if (!is_dir($dir)) {
            return;
        }
        $hadMasa = time() - ($this->tetapan->minitLuput() * 60);
        foreach (SimpananSelamat::senarai($dir) as $f) {
            if (filemtime($f) > $hadMasa) {
                continue;
            }
            $data = SimpananSelamat::baca($f);
            if ($data !== null && (int) ($data['status'] ?? 0) === Bayarcash::BARU) {
                @unlink($f);
            }
        }
    }
}
