<?php
/* ==========================================================================
   Klien Bayarcash (API v3) + checksum HMAC SHA256
   --------------------------------------------------------------------------
   Rujukan: https://api.webimpian.support/bayarcash
   SDK rasmi: https://github.com/webimpian/bayarcash-php-sdk

   Semua fungsi di sini hanya dipanggil dari server. Personal Access Token
   dan API secret key tidak sekali-kali dihantar ke pelayar.
   ========================================================================== */

declare(strict_types=1);

final class Bayarcash
{
    private const ASAS = [
        'sandbox'    => 'https://api.console.bayarcash-sandbox.com/v3',
        'production' => 'https://api.console.bayar.cash/v3',
    ];

    public function __construct(
        private string $pat,
        private string $secretKey,
        private string $persekitaran = 'sandbox',
    ) {
    }

    private function asas(): string
    {
        return self::ASAS[$this->persekitaran] ?? self::ASAS['sandbox'];
    }

    /* ====================== CHECKSUM (HMAC SHA256) ======================= */

    /*
     * Algoritma Bayarcash:
     *   1. trim() setiap nilai
     *   2. ksort() — susun ikut kunci
     *   3. implode('|') — cantum nilai dengan '|'
     *   4. hash_hmac('sha256', $rentetan, $secretKey)
     *
     * Nota: dokumentasi rasmi menyuruh trim setiap nilai, tetapi SDK PHP
     * rasmi (webimpian/bayarcash-php-sdk) tidak melakukannya. Untuk
     * permintaan keluar kita ikut dokumentasi — nilai kita memang sudah
     * bersih, jadi kedua-duanya menghasilkan hasil sama. Untuk callback
     * masuk kita sahkan kedua-dua varian; lihat sahkanSalahSatu().
     */
    public function checksum(array $payload, bool $trim = true): string
    {
        $payload = array_map(
            static fn ($v) => $trim ? trim((string) $v) : (string) $v,
            $payload
        );
        ksort($payload);
        return hash_hmac('sha256', implode('|', $payload), $this->secretKey);
    }

    /*
     * Sahkan checksum terhadap varian trim DAN tanpa trim, supaya callback
     * sah tidak ditolak hanya kerana satu medan mengandungi ruang di hujung.
     * Penyerang masih memerlukan secret key untuk lulus mana-mana varian.
     */
    private function sahkanSalahSatu(array $payload, string $diberi): bool
    {
        if ($diberi === '') {
            return false;
        }
        return hash_equals($this->checksum($payload, true), $diberi)
            || hash_equals($this->checksum($payload, false), $diberi);
    }

    /* Checksum untuk payload Payment Intent */
    public function checksumIntent(array $data): string
    {
        return $this->checksum([
            'payment_channel' => $data['payment_channel'],
            'order_number'    => $data['order_number'],
            'amount'          => $data['amount'],      // rentetan 2 desimal, cth "24.50"
            'payer_name'      => $data['payer_name'],
            'payer_email'     => $data['payer_email'],
        ]);
    }

    /*
     * Sahkan callback v3 yang diterima di return_url (GET) atau
     * callback_url (POST). Guna hash_equals untuk elak timing attack.
     */
    public function sahkanCallbackV3(array $cb): bool
    {
        return $this->sahkanSalahSatu([
            'transaction_id'            => $cb['transaction_id'] ?? '',
            'exchange_reference_number' => $cb['exchange_reference_number'] ?? '',
            'exchange_transaction_id'   => $cb['exchange_transaction_id'] ?? '',
            'order_number'              => $cb['order_number'] ?? '',
            'currency'                  => $cb['currency'] ?? '',
            'amount'                    => $cb['amount'] ?? '',
            'payer_bank_name'           => $cb['payer_bank_name'] ?? '',
            'status'                    => $cb['status'] ?? '',
            'status_description'        => $cb['status_description'] ?? '',
        ], (string) ($cb['checksum'] ?? ''));
    }

    /*
     * Callback record_type=transaction (v2 dan juga POST callback_url v3)
     * menyertakan payer_name, payer_email, record_type dan datetime.
     */
    public function sahkanCallbackTransaksi(array $cb): bool
    {
        return $this->sahkanSalahSatu([
            'record_type'               => $cb['record_type'] ?? '',
            'transaction_id'            => $cb['transaction_id'] ?? '',
            'exchange_reference_number' => $cb['exchange_reference_number'] ?? '',
            'exchange_transaction_id'   => $cb['exchange_transaction_id'] ?? '',
            'order_number'              => $cb['order_number'] ?? '',
            'currency'                  => $cb['currency'] ?? '',
            'amount'                    => $cb['amount'] ?? '',
            'payer_name'                => $cb['payer_name'] ?? '',
            'payer_email'               => $cb['payer_email'] ?? '',
            'payer_bank_name'           => $cb['payer_bank_name'] ?? '',
            'status'                    => $cb['status'] ?? '',
            'status_description'        => $cb['status_description'] ?? '',
            'datetime'                  => $cb['datetime'] ?? '',
        ], (string) ($cb['checksum'] ?? ''));
    }

    /*
     * Terima kedua-dua bentuk checksum callback. Bayarcash menghantar set
     * medan yang berbeza untuk return_url (v3) dan callback_url, jadi kita
     * cuba kedua-duanya sebelum menolak.
     */
    public function sahkanCallback(array $cb): bool
    {
        return $this->sahkanCallbackV3($cb) || $this->sahkanCallbackTransaksi($cb);
    }

    /* ============================ ENDPOINT ============================== */

    /* POST /v3/payment-intents — pulangkan objek intent termasuk `url` */
    public function buatPaymentIntent(array $payload): array
    {
        return $this->minta('POST', '/payment-intents', $payload);
    }

    /* GET /v3/payment-intents/{id} — intent + semua `attempts` */
    public function paymentIntent(string $id): array
    {
        return $this->minta('GET', '/payment-intents/' . rawurlencode($id));
    }

    /* GET /v3/transactions/{id} — butiran satu transaksi */
    public function transaksi(string $id): array
    {
        return $this->minta('GET', '/transactions/' . rawurlencode($id));
    }

    /* GET /v3/transactions?order_number=... */
    public function transaksiIkutOrder(string $orderNumber): array
    {
        return $this->minta('GET', '/transactions?order_number=' . rawurlencode($orderNumber));
    }

    /* GET /v3/banks — senarai bank FPX (pulangkan array tulen, bukan {data}) */
    public function bankFpx(): array
    {
        return $this->minta('GET', '/banks');
    }

    /*
     * GET /v3/portals — senarai portal dalam akaun.
     * Setiap portal menyertakan `portal_key` dan `payment_channels`, jadi ini
     * cara terbaik untuk sahkan kredensial: ia membuktikan token berfungsi,
     * membuktikan portal key wujud, dan memberitahu saluran mana yang benar-
     * benar diaktifkan pada portal itu.
     */
    public function portals(int $maksHalaman = 5): array
    {
        $semua = [];
        $laluan = '/portals';

        for ($i = 0; $i < $maksHalaman; $i++) {
            $jawapan = $this->minta('GET', $laluan);
            $data = is_array($jawapan['data'] ?? null) ? $jawapan['data'] : [];
            foreach ($data as $portal) {
                $semua[] = $portal;
            }

            $seterusnya = $jawapan['links']['next'] ?? null;
            if (!is_string($seterusnya) || $seterusnya === '') {
                break;
            }
            // Ambil bahagian selepas '/v3' supaya laluan relatif kekal betul
            $bahagian = parse_url($seterusnya);
            $laluanPenuh = ($bahagian['path'] ?? '') . (isset($bahagian['query']) ? '?' . $bahagian['query'] : '');
            $potong = strpos($laluanPenuh, '/portals');
            if ($potong === false) {
                break;
            }
            $laluan = substr($laluanPenuh, $potong);
        }

        return $semua;
    }

    /* ============================== HTTP ================================ */

    /**
     * @throws RuntimeException bila panggilan gagal atau API pulangkan ralat
     */
    private function minta(string $kaedah, string $laluan, ?array $badan = null): array
    {
        $url = $this->asas() . $laluan;

        $ch = curl_init($url);
        $pilihan = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_CUSTOMREQUEST  => $kaedah,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $this->pat,
                'Content-Type: application/json',
                'Accept: application/json',
            ],
        ];

        if ($badan !== null) {
            $pilihan[CURLOPT_POSTFIELDS] = json_encode($badan, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        curl_setopt_array($ch, $pilihan);
        $jawapan = curl_exec($ch);
        $kod     = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $ralat   = curl_error($ch);
        curl_close($ch);

        if ($jawapan === false) {
            throw new RuntimeException('Gagal hubungi Bayarcash: ' . $ralat);
        }

        $data = json_decode((string) $jawapan, true);
        if (!is_array($data)) {
            throw new RuntimeException('Jawapan Bayarcash bukan JSON sah (HTTP ' . $kod . ')');
        }

        if ($kod < 200 || $kod >= 300) {
            $mesej = $data['message'] ?? ($data['error'] ?? 'Ralat tidak diketahui');
            if (!empty($data['errors']) && is_array($data['errors'])) {
                $baris = [];
                foreach ($data['errors'] as $medan => $senarai) {
                    $baris[] = $medan . ': ' . (is_array($senarai) ? implode(', ', $senarai) : $senarai);
                }
                $mesej .= ' (' . implode('; ', $baris) . ')';
            }
            throw new RuntimeException('Bayarcash HTTP ' . $kod . ' — ' . $mesej);
        }

        return $data;
    }

    /* ============================ STATUS =============================== */

    public const BARU      = 0;
    public const MENUNGGU  = 1;
    public const GAGAL     = 2;
    public const BERJAYA   = 3;
    public const DIBATAL   = 4;

    public static function labelStatus(int $status): string
    {
        return match ($status) {
            self::BARU     => 'Baru',
            self::MENUNGGU => 'Menunggu',
            self::GAGAL    => 'Gagal',
            self::BERJAYA  => 'Berjaya',
            self::DIBATAL  => 'Dibatalkan',
            default        => 'Tidak diketahui',
        };
    }
}
