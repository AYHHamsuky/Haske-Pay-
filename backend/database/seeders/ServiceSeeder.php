<?php

namespace Database\Seeders;

use App\Models\Service;
use Illuminate\Database\Seeder;

/**
 * Codes are VTpass serviceIDs. Commission rates are what VTpass paid at planning time;
 * update them from the current VTpass rate card before going live.
 */
class ServiceSeeder extends Seeder
{
    public function run(): void
    {
        $discos = [
            // code, name, short, commission %, baxi service type
            ['kaduna-electric', 'Kaduna Electric', 'KAEDCO', 1.5, 'kaduna_electric'],
            ['kano-electric', 'Kano Electric', 'KEDCO', 1.0, 'kano_electric'],
            ['jos-electric', 'Jos Electric', 'JED', 0.9, 'jos_electric'],
            ['abuja-electric', 'Abuja Electric', 'AEDC', 1.2, 'abuja_electric'],
            ['yola-electric', 'Yola Electric', 'YEDC', 1.0, 'yola_electric'],
            ['ikeja-electric', 'Ikeja Electric', 'IKEDC', 1.5, 'ikeja_electric'],
            ['eko-electric', 'Eko Electric', 'EKEDC', 1.0, 'eko_electric'],
            ['ibadan-electric', 'Ibadan Electric', 'IBEDC', 1.1, 'ibadan_electric'],
            ['enugu-electric', 'Enugu Electric', 'EEDC', 1.4, 'enugu_electric'],
            ['benin-electric', 'Benin Electric', 'BEDC', 1.5, 'benin_electric'],
            ['portharcourt-electric', 'Port Harcourt Electric', 'PHED', 2.0, 'portharcourt_electric'],
            ['aba-electric', 'Aba Power', 'ABA', 1.0, 'aba_electric'],
        ];
        foreach ($discos as [$code, $name, $short, $rate, $baxi]) {
            Service::updateOrCreate(['code' => $code], [
                'name' => $name, 'short_name' => $short, 'category' => 'electricity',
                'provider' => 'vtpass', 'fallback_provider' => 'baxi',
                'commission_rate' => $rate, 'fee' => 100_00,
                'min_amount' => 1_000_00, 'max_amount' => 500_000_00,
                'meta' => ['baxi_service' => $baxi],
            ]);
        }

        $nets = [
            ['mtn', 'MTN', 3.0], ['airtel', 'Airtel', 3.4], ['glo', 'Glo', 4.0], ['etisalat', '9mobile', 4.0],
        ];
        foreach ($nets as [$code, $name, $rate]) {
            Service::updateOrCreate(['code' => $code], [
                'name' => $name, 'short_name' => $name, 'category' => 'airtime',
                'provider' => 'vtpass', 'fallback_provider' => 'baxi',
                'commission_rate' => $rate, 'fee' => 0,
                'min_amount' => 50_00, 'max_amount' => 50_000_00,
                'meta' => ['network' => $code === 'etisalat' ? '9mobile' : $code, 'baxi_service' => $code === 'etisalat' ? '9mobile' : $code],
            ]);
            Service::updateOrCreate(['code' => "{$code}-data"], [
                'name' => $name, 'short_name' => $name, 'category' => 'data',
                'provider' => 'vtpass', 'fallback_provider' => 'baxi',
                'commission_rate' => max(1.0, $rate - 1), 'fee' => 0,
                'min_amount' => 50_00, 'max_amount' => 100_000_00,
                'meta' => ['network' => $code === 'etisalat' ? '9mobile' : $code, 'baxi_service' => $code === 'etisalat' ? '9mobile' : $code],
            ]);
        }
    }
}
