<?php

namespace App\Support;

final class Phone
{
    /**
     * VTpass sandbox numbers that simulate pending (201…), anomalies (500…), no response (400…)
     * and timeouts (300…). https://vtpass.com/documentation/mtn-airtime-vtu-api/
     */
    public const VTPASS_SANDBOX_NUMBERS = ['201000000000', '500000000000', '400000000000', '300000000000'];

    /** Normalise +234 / 234 / 0 prefixed numbers to 0XXXXXXXXXX, or null if invalid. */
    public static function normalize(?string $raw): ?string
    {
        $d = preg_replace('/\D/', '', (string) $raw);
        if (in_array($d, self::VTPASS_SANDBOX_NUMBERS, true) && str_contains((string) config('haske.vtpass.base_url'), 'sandbox')
            && config('haske.drivers.bills') === 'live') {
            return $d;
        }
        if (str_starts_with($d, '234') && strlen($d) === 13) {
            $d = '0'.substr($d, 3);
        } elseif (strlen($d) === 10 && in_array($d[0], ['7', '8', '9'], true)) {
            $d = '0'.$d;
        }

        return preg_match('/^0[789][01]\d{8}$/', $d) ? $d : null;
    }

    public const PREFIXES = [
        'mtn' => ['0803', '0806', '0703', '0706', '0813', '0816', '0810', '0814', '0903', '0906', '0913', '0916', '0704', '0707'],
        'airtel' => ['0802', '0808', '0708', '0812', '0701', '0902', '0901', '0904', '0907', '0912', '0911'],
        'glo' => ['0805', '0807', '0705', '0815', '0811', '0905', '0915'],
        '9mobile' => ['0809', '0818', '0817', '0909', '0908'],
    ];

    public static function network(string $phone): ?string
    {
        $pre = substr($phone, 0, 4);
        foreach (self::PREFIXES as $net => $list) {
            if (in_array($pre, $list, true)) {
                return $net;
            }
        }

        return null;
    }

    public static function international(string $phone): string
    {
        return '234'.substr($phone, 1);
    }
}
