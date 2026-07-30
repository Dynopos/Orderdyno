<?php
/* ==========================================================================
   Pembantu HTTP — jawapan JSON, baca badan permintaan, had kadar
   ========================================================================== */

declare(strict_types=1);

require_once __DIR__ . '/tetapan.php';

function json_keluar(array $data, int $kod = 200): never
{
    http_response_code($kod);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function json_silap(string $mesej, int $kod = 400): never
{
    json_keluar(['ok' => false, 'mesej' => $mesej], $kod);
}

/* Baca badan JSON permintaan */
function badan_json(): array
{
    $mentah = file_get_contents('php://input');
    if ($mentah === false || $mentah === '') {
        return [];
    }
    if (strlen($mentah) > 512 * 1024) {
        json_silap('Permintaan terlalu besar', 413);
    }
    $data = json_decode($mentah, true);
    return is_array($data) ? $data : [];
}

/* Pastikan kaedah HTTP yang betul */
function wajib_kaedah(string $kaedah): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== $kaedah) {
        header('Allow: ' . $kaedah);
        json_silap('Kaedah tidak dibenarkan', 405);
    }
}

function ip_pelawat(): string
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
}

/**
 * Had kadar ringkas berasaskan fail — cukup untuk melindungi hosting kongsi
 * daripada penyalahgunaan mudah. Pulangkan false bila had dilanggar.
 */
function had_kadar(string $nama, int $maks, int $tempohDetik): bool
{
    Tetapan::sediakanDirData();
    $dir = Tetapan::dirData() . '/kadar';
    if (!is_dir($dir) && !@mkdir($dir, 0750, true) && !is_dir($dir)) {
        return true; // gagal buat folder — jangan halang pelanggan sah
    }

    $kunci = $dir . '/' . $nama . '-' . hash('sha256', ip_pelawat()) . '.php';
    $sekarang = time();

    $rekod = ['mula' => $sekarang, 'kira' => 0];
    $lama = SimpananSelamat::baca($kunci);
    if (is_array($lama) && ($sekarang - (int) ($lama['mula'] ?? 0)) < $tempohDetik) {
        $rekod = ['mula' => (int) $lama['mula'], 'kira' => (int) $lama['kira']];
    }

    $rekod['kira']++;
    try {
        SimpananSelamat::tulis($kunci, $rekod, false);
    } catch (Throwable $e) {
        return true; // gagal tulis — jangan halang pelanggan sah
    }

    // Buang fail lama sekali-sekala
    if (random_int(1, 50) === 1) {
        foreach (SimpananSelamat::senarai($dir) as $f) {
            if (filemtime($f) < $sekarang - 86400) {
                @unlink($f);
            }
        }
    }

    return $rekod['kira'] <= $maks;
}
