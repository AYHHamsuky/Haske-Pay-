<?php

namespace Database\Seeders;

use App\Models\User;
use App\Services\Ledger;
use App\Services\Payments\Paystack;
use Illuminate\Database\Seeder;

/** Local-only accounts. Every demo PIN is 1234. */
class DemoSeeder extends Seeder
{
    public function run(Ledger $ledger, Paystack $paystack): void
    {
        $admin = User::firstOrCreate(['phone' => '08010000001'], ['name' => 'Abubakar Yahya', 'role' => 'admin']);

        $customer = User::firstOrCreate(['phone' => '08031234567'], ['name' => 'Aisha Bello', 'role' => 'customer']);
        $customer->setPin('1234');

        $agent = User::firstOrCreate(['phone' => '08061234567'], ['name' => 'Musa Ibrahim', 'role' => 'agent']);
        $agent->setPin('1234');
        $agent->agentProfile()->firstOrCreate([], ['business_name' => 'Musa POS & Recharge', 'location' => 'Barnawa, Kaduna', 'commission_tier' => 'silver']);

        foreach ([$admin, $customer, $agent] as $u) {
            $u->walletOrCreate();
            $paystack->ensureVirtualAccount($u);
        }
        $ledger->credit($customer, 30_000_00, 'SEED-CUSTOMER-1', 'funding', ['description' => 'Opening demo balance']);
        $ledger->credit($agent, 150_000_00, 'SEED-AGENT-1', 'funding', ['description' => 'Opening demo balance']);

        $customer->savedMeters()->firstOrCreate(
            ['service_code' => 'kaduna-electric', 'meter_number' => '04223456781'],
            ['meter_type' => 'prepaid', 'customer_name' => 'Aisha Bello', 'address' => 'Barnawa, Kaduna'],
        );
    }
}
