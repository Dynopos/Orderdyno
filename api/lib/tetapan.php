<?php
/* ==========================================================================
   Tetapan — muat konfigurasi, kredensial Bayarcash & menu yang dipercayai
   --------------------------------------------------------------------------
   Keutamaan sumber kredensial (tinggi ke rendah):
     1. Environment variable  (BAYARCASH_PAT, ...)
     2. api/config.php
     3. api/data/bayarcash.json  — yang disimpan dari tab "Bayaran"

   `api/data/` mengandungi kunci rahsia dan rekod order, jadi ia dihalang
   dari capaian web oleh api/data/.htaccess.
   ========================================================================== */

declare(strict_types=1);

require_once __DIR__ . '/bayarcash.php';

final class Tetapan
{
    public const SALURAN = [
        1  => 'FPX Online Banking',
        2  => 'Manual Bank Transfer',
        3  => 'Direct Debit',
        4  => 'FPX Line of Credit',
        5  => 'DuitNow Online Banking / Wallets',
        6  => 'DuitNow QR',
        7  => 'SPayLater',
        8  => 'Boost PayFlex',
        9  => 'QRIS Indonesia — Online Banking',
        10 => 'QRIS Indonesia — eWallet',
        11 => 'NETS Singapore',
    ];

    private array $config;
    private array $simpanan;

    public function __construct()
    {
        $this->config   = $this->muatConfig();
        $this->simpanan = $this->muatSimpanan();
    }

    /* ============================= LOKASI =============================== */

    public static function dirData(): string
    {
        return __DIR__ . '/../data';
    }

    private static function failSimpanan(): string
    {
        return self::dirData() . '/bayarcash.json';
    }

    public static function failMenu(): string
    {
        return self::dirData() . '/menu.json';
    }

    /* Pastikan folder data ada dan dilindungi */
    public static function sediakanDirData(): void
    {
        $dir = self::dirData();
        if (!is_dir($dir)) {
            @mkdir($dir, 0750, true);
        }
        $htaccess = $dir . '/.htaccess';
        if (!is_file($htaccess)) {
            @file_put_contents($htaccess, "Require all denied\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n");
        }
    }

    /* ============================== MUAT =============================== */

    private function muatConfig(): array
    {
        $fail = __DIR__ . '/../config.php';
        if (!is_file($fail)) {
            return [];
        }
        $data = require $fail;
        return is_array($data) ? $data : [];
    }

    private function muatSimpanan(): array
    {
        $fail = self::failSimpanan();
        if (!is_file($fail)) {
            return [];
        }
        $data = json_decode((string) file_get_contents($fail), true);
        return is_array($data) ? $data : [];
    }

    /* ============================= NILAI =============================== */

    private function env(string $nama): string
    {
        $v = getenv($nama);
        return is_string($v) ? trim($v) : '';
    }

    /* Ambil nilai ikut keutamaan: env > config.php > simpanan */
    private function nilai(string $env, string $kunci): string
    {
        foreach ([$this->env($env), (string) ($this->config[$kunci] ?? ''), (string) ($this->simpanan[$kunci] ?? '')] as $v) {
            if (trim($v) !== '') {
                return trim($v);
            }
        }
        return '';
    }

    public function pat(): string
    {
        return $this->nilai('BAYARCASH_PAT', 'pat');
    }

    public function secretKey(): string
    {
        return $this->nilai('BAYARCASH_SECRET_KEY', 'secret_key');
    }

    public function portalKey(): string
    {
        return $this->nilai('BAYARCASH_PORTAL_KEY', 'portal_key');
    }

    public function persekitaran(): string
    {
        $v = $this->nilai('BAYARCASH_ENV', 'persekitaran');
        return $v === 'production' ? 'production' : 'sandbox';
    }

    public function kunciAdmin(): string
    {
        return trim((string) ($this->config['kunci_admin'] ?? ''));
    }

    public function maksKuantiti(): int
    {
        return max(1, (int) ($this->config['maks_kuantiti'] ?? 99));
    }

    public function maksJumlah(): float
    {
        return max(1.0, (float) ($this->config['maks_jumlah'] ?? 10000));
    }

    public function minitLuput(): int
    {
        return max(5, (int) ($this->config['minit_luput'] ?? 60));
    }

    /* Saluran pembayaran yang diaktifkan pemilik kedai */
    public function saluran(): array
    {
        $kod = $this->simpanan['saluran'] ?? $this->config['saluran'] ?? [1];
        $keluar = [];
        foreach ((array) $kod as $k) {
            // Terima kedua-dua bentuk: 1  atau  ['kod' => 1, 'nama' => '...']
            $nombor = is_array($k) ? (int) ($k['kod'] ?? 0) : (int) $k;
            if (isset(self::SALURAN[$nombor]) && !isset($keluar[$nombor])) {
                $keluar[$nombor] = ['kod' => $nombor, 'nama' => self::SALURAN[$nombor]];
            }
        }
        return $keluar ? array_values($keluar) : [['kod' => 1, 'nama' => self::SALURAN[1]]];
    }

    /* ============================= KEADAAN ============================== */

    public function adaConfig(): bool
    {
        return is_file(__DIR__ . '/../config.php');
    }

    public function adaKunciAdmin(): bool
    {
        $k = $this->kunciAdmin();
        return $k !== '' && !str_contains($k, 'TUKAR-KUNCI-INI');
    }

    /* Sedia terima bayaran? */
    public function siap(): bool
    {
        return $this->pat() !== ''
            && $this->secretKey() !== ''
            && $this->portalKey() !== ''
            && is_file(self::failMenu());
    }

    /* Kenapa belum siap — untuk dipaparkan dalam panel Bayaran */
    public function halangan(): array
    {
        $h = [];
        if (!$this->adaConfig()) {
            $h[] = 'config_hilang';
        } elseif (!$this->adaKunciAdmin()) {
            $h[] = 'kunci_admin_belum_ditukar';
        }
        if ($this->pat() === '')        { $h[] = 'pat_kosong'; }
        if ($this->secretKey() === '')  { $h[] = 'secret_key_kosong'; }
        if ($this->portalKey() === '')  { $h[] = 'portal_key_kosong'; }
        if (!is_file(self::failMenu())) { $h[] = 'menu_belum_segerak'; }
        return $h;
    }

    /* Sumber setiap kredensial — supaya pemilik tahu di mana ia diambil */
    public function sumber(): array
    {
        $satu = function (string $env, string $kunci): string {
            if ($this->env($env) !== '')                        { return 'env'; }
            if (trim((string) ($this->config[$kunci] ?? '')) !== '')   { return 'config'; }
            if (trim((string) ($this->simpanan[$kunci] ?? '')) !== '') { return 'panel'; }
            return 'kosong';
        };
        return [
            'pat'        => $satu('BAYARCASH_PAT', 'pat'),
            'secret_key' => $satu('BAYARCASH_SECRET_KEY', 'secret_key'),
            'portal_key' => $satu('BAYARCASH_PORTAL_KEY', 'portal_key'),
        ];
    }

    /* ============================ SAHKAN KUNCI ========================== */

    public function kunciSah(?string $diberi): bool
    {
        $betul = $this->kunciAdmin();
        if ($betul === '' || !$this->adaKunciAdmin() || !is_string($diberi) || $diberi === '') {
            return false;
        }
        return hash_equals($betul, $diberi);
    }

    /* ============================== SIMPAN ============================== */

    /**
     * Simpan kredensial dari panel. Medan yang dihantar kosong TIDAK menimpa
     * nilai lama — supaya pemilik boleh kemas kini satu medan sahaja.
     */
    public function simpanKredensial(array $masuk): void
    {
        self::sediakanDirData();
        $baru = $this->simpanan;

        foreach (['pat', 'secret_key', 'portal_key'] as $medan) {
            if (isset($masuk[$medan]) && trim((string) $masuk[$medan]) !== '') {
                $baru[$medan] = trim((string) $masuk[$medan]);
            }
        }

        if (isset($masuk['persekitaran'])) {
            $baru['persekitaran'] = $masuk['persekitaran'] === 'production' ? 'production' : 'sandbox';
        }

        if (isset($masuk['saluran']) && is_array($masuk['saluran'])) {
            $kod = [];
            foreach ($masuk['saluran'] as $k) {
                $n = (int) $k;
                if (isset(self::SALURAN[$n])) {
                    $kod[] = $n;
                }
            }
            $baru['saluran'] = $kod ?: [1];
        }

        $baru['dikemas'] = gmdate('c');

        $this->tulisJson(self::failSimpanan(), $baru);
        $this->simpanan = $baru;
    }

    /* Buang kredensial yang disimpan dari panel */
    public function lupakanKredensial(): void
    {
        $fail = self::failSimpanan();
        if (is_file($fail)) {
            @unlink($fail);
        }
        $this->simpanan = [];
    }

    /**
     * Simpan snapshot menu yang dipercayai. Harga untuk pembayaran dikira
     * dari fail INI, bukan dari data yang dihantar pelayar.
     * $mentah ialah rentetan JSON asal supaya hash sepadan dengan pelayar.
     */
    public function simpanMenu(string $mentah): array
    {
        $data = json_decode($mentah, true);
        if (!is_array($data) || empty($data['menu']) || !is_array($data['menu'])) {
            throw new InvalidArgumentException('Struktur menu tidak sah');
        }

        self::sediakanDirData();
        $fail = self::failMenu();
        if (file_put_contents($fail, $mentah, LOCK_EX) === false) {
            throw new RuntimeException('Gagal tulis menu ke server');
        }
        @chmod($fail, 0640);

        return [
            'hash'    => hash('sha256', $mentah),
            'item'    => count($data['menu']),
            'dikemas' => gmdate('c'),
        ];
    }

    public function menuTersimpan(): ?array
    {
        $fail = self::failMenu();
        if (!is_file($fail)) {
            return null;
        }
        $mentah = (string) file_get_contents($fail);
        $data = json_decode($mentah, true);
        if (!is_array($data)) {
            return null;
        }
        $data['__hash']    = hash('sha256', $mentah);
        $data['__dikemas'] = gmdate('c', (int) filemtime($fail));
        return $data;
    }

    /* ============================== KLIEN =============================== */

    public function klien(): Bayarcash
    {
        return new Bayarcash($this->pat(), $this->secretKey(), $this->persekitaran());
    }

    /* ============================= URL ASAS ============================= */

    public function urlAsas(): string
    {
        $dikonfig = trim((string) ($this->config['url_asas'] ?? ''));
        if ($dikonfig !== '') {
            return rtrim($dikonfig, '/');
        }

        $skema = 'http';
        if (
            (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || ($_SERVER['SERVER_PORT'] ?? '') === '443'
            || strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https'
        ) {
            $skema = 'https';
        }

        $hos = $_SERVER['HTTP_HOST'] ?? 'localhost';
        // Buang aksara yang tidak sah dalam nama hos
        $hos = preg_replace('/[^A-Za-z0-9\.\-:]/', '', $hos) ?? 'localhost';

        // Folder induk kepada /api/
        $laluan = rtrim(str_replace('\\', '/', dirname(dirname($_SERVER['SCRIPT_NAME'] ?? '/api/x.php'))), '/');

        return $skema . '://' . $hos . $laluan;
    }

    public function urlApi(string $fail): string
    {
        return $this->urlAsas() . '/api/' . $fail;
    }

    /* ============================== UTILITI ============================= */

    private function tulisJson(string $fail, array $data): void
    {
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false || file_put_contents($fail, $json, LOCK_EX) === false) {
            throw new RuntimeException('Gagal simpan tetapan');
        }
        @chmod($fail, 0600);
    }

    /* Papar hujung kunci sahaja — untuk pengesahan visual, bukan rahsia */
    public static function topeng(string $rahsia): string
    {
        $panjang = strlen($rahsia);
        if ($panjang === 0) {
            return '';
        }
        if ($panjang <= 4) {
            return str_repeat('•', $panjang);
        }
        return str_repeat('•', min(12, $panjang - 4)) . substr($rahsia, -4);
    }
}
