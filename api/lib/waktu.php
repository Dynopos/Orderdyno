<?php
/* ==========================================================================
   Waktu operasi — semakan di sisi server
   --------------------------------------------------------------------------
   Pelayar sudah menyembunyikan butang order bila kedai tutup, tetapi itu
   sahaja tidak memadai: sesiapa boleh menghantar permintaan terus ke
   api/buat-bayaran.php dan membayar untuk kedai yang sudah tutup. Wang yang
   masuk untuk order yang tidak akan disediakan adalah masalah sebenar bagi
   pemilik kedai, bukan sekadar isu paparan.

   Logik di sini mesti sepadan dengan statusBuka() dalam assets/js/store.js.
   Kalau anda mengubah satu, ubah yang satu lagi juga.
   ========================================================================== */

declare(strict_types=1);

final class Waktu
{
    /** id => nombor hari PHP (0 = Ahad), sama seperti JavaScript */
    private const HARI = [
        'isnin'  => 1,
        'selasa' => 2,
        'rabu'   => 3,
        'khamis' => 4,
        'jumaat' => 5,
        'sabtu'  => 6,
        'ahad'   => 0,
    ];

    private const NAMA = [
        0 => 'Ahad', 1 => 'Isnin', 2 => 'Selasa', 3 => 'Rabu',
        4 => 'Khamis', 5 => 'Jumaat', 6 => 'Sabtu',
    ];

    private static function minit(string $jam): int
    {
        $b = explode(':', $jam);
        return ((int) ($b[0] ?? 0)) * 60 + ((int) ($b[1] ?? 0));
    }

    /** Tetapan satu hari, dengan nilai lalai yang selamat */
    private static function hari(array $w, int $js): array
    {
        $id = array_search($js, self::HARI, true);
        $d  = $w['hari'][$id] ?? [];
        return [
            'tutupHariIni' => !empty($d['tutupHariIni']),
            'buka'         => (string) ($d['buka'] ?? '10:00'),
            'tutup'        => (string) ($d['tutup'] ?? '22:00'),
        ];
    }

    /**
     * @param array    $config  Konfigurasi kedai yang diterbitkan
     * @param int|null $masa    Cap masa UNIX (untuk ujian); null = sekarang
     * @return array{buka:bool, mesej:string, seterusnya:string}
     */
    public static function status(array $config, ?int $masa = null): array
    {
        $w = $config['waktuBuka'] ?? [];
        if (!is_array($w) || empty($w['aktif'])) {
            return ['buka' => true, 'mesej' => '', 'seterusnya' => ''];
        }

        $mesej = (string) ($w['mesej'] ?? 'Kedai sedang tutup.');

        if (!empty($w['tutupSementara'])) {
            return ['buka' => false, 'mesej' => $mesej, 'seterusnya' => ''];
        }

        /* Masa kedai = UTC + zon, supaya status dikira ikut waktu kedai
           dan bukan waktu server (yang selalunya UTC). */
        $zon   = (int) ($w['zon'] ?? 8);
        $cap   = ($masa ?? time()) + $zon * 3600;
        $hariJs = (int) gmdate('w', $cap);
        $kini   = (int) gmdate('G', $cap) * 60 + (int) gmdate('i', $cap);

        $dalamJulat = static function (array $d, int $minit): bool {
            if ($d['tutupHariIni']) {
                return false;
            }
            $b = self::minit($d['buka']);
            $p = self::minit($d['tutup']);
            return $p > $b ? ($minit >= $b && $minit < $p) : ($minit >= $b || $minit < $p);
        };

        /* Semalam boleh berterusan melepasi tengah malam */
        $semalam = self::hari($w, ($hariJs + 6) % 7);
        $lanjut  = !$semalam['tutupHariIni']
            && self::minit($semalam['tutup']) <= self::minit($semalam['buka'])
            && $kini < self::minit($semalam['tutup']);

        if ($dalamJulat(self::hari($w, $hariJs), $kini) || $lanjut) {
            return ['buka' => true, 'mesej' => '', 'seterusnya' => ''];
        }

        /* Waktu buka seterusnya */
        $seterusnya = '';
        for ($i = 0; $i < 8; $i++) {
            $js = ($hariJs + $i) % 7;
            $d  = self::hari($w, $js);
            if ($d['tutupHariIni']) {
                continue;
            }
            if ($i === 0 && $kini >= self::minit($d['buka'])) {
                continue;
            }
            $label = $i === 0 ? 'hari ini' : ($i === 1 ? 'esok' : self::NAMA[$js]);
            $seterusnya = $label . ' jam ' . $d['buka'];
            break;
        }

        return ['buka' => false, 'mesej' => $mesej, 'seterusnya' => $seterusnya];
    }
}
