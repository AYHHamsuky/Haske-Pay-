<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\Phone;
use Illuminate\Console\Command;

class CreateAdmin extends Command
{
    protected $signature = 'haske:make-admin {phone} {--name=Admin}';

    protected $description = 'Create or promote an admin. They set up 2FA on first login.';

    public function handle(): int
    {
        $phone = Phone::normalize($this->argument('phone'));
        if (! $phone) {
            $this->error('Invalid Nigerian phone number.');

            return self::FAILURE;
        }
        $user = User::firstOrCreate(['phone' => $phone], ['name' => $this->option('name')]);
        $user->forceFill(['role' => 'admin', 'status' => 'active'])->save();
        $user->walletOrCreate();
        $this->info("{$phone} is now an admin. Log in at /login with an SMS code, then scan the 2FA QR code.");

        return self::SUCCESS;
    }
}
