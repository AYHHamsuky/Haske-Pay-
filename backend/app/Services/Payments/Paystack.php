<?php

namespace App\Services\Payments;

use App\Models\User;
use App\Models\VirtualAccount;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class Paystack
{
    private function http(): PendingRequest
    {
        return Http::baseUrl(config('haske.paystack.base_url'))
            ->withToken((string) config('haske.paystack.secret_key'))
            ->acceptJson()->asJson()->timeout(30);
    }

    private function fake(): bool
    {
        return config('haske.drivers.payments') === 'fake';
    }

    /** HMAC-SHA512 of the raw body with the secret key, compared in constant time. */
    public function validSignature(string $rawBody, ?string $signature): bool
    {
        if (! $signature) {
            return false;
        }

        return hash_equals(hash_hmac('sha512', $rawBody, (string) config('haske.paystack.secret_key')), $signature);
    }

    public function placeholderEmail(User $user): string
    {
        return $user->email ?: $user->phone.'@'.config('haske.paystack.email_domain');
    }

    /** One Paystack dedicated virtual account per user. */
    public function ensureVirtualAccount(User $user): VirtualAccount
    {
        if ($user->virtualAccount) {
            return $user->virtualAccount;
        }

        if ($this->fake()) {
            return $user->virtualAccount()->create([
                'bank' => 'Wema Bank (sandbox)',
                'account_number' => '78'.str_pad((string) ($user->id * 7919 % 100_000_000), 8, '0', STR_PAD_LEFT),
                'account_name' => 'HASKE / '.strtoupper($user->name ?: $user->phone),
                'provider_ref' => 'CUS_fake'.$user->id,
            ]);
        }

        [$first, $last] = array_pad(explode(' ', trim($user->name ?: 'Haske Customer'), 2), 2, 'Customer');
        $customer = $this->http()->post('/customer', [
            'email' => $this->placeholderEmail($user),
            'first_name' => $first,
            'last_name' => $last,
            'phone' => '+234'.substr($user->phone, 1),
        ])->throw()->json('data');

        $dva = $this->http()->post('/dedicated_account', [
            'customer' => $customer['customer_code'],
            'preferred_bank' => config('haske.paystack.preferred_bank'),
        ])->throw()->json('data');

        return $user->virtualAccount()->create([
            'bank' => $dva['bank']['name'],
            'account_number' => $dva['account_number'],
            'account_name' => $dva['account_name'],
            'provider_ref' => $customer['customer_code'],
        ]);
    }

    /** Card / transfer checkout for the web flow. Returns the URL to send the buyer to. */
    public function initialize(string $reference, int $amount, string $email, string $callbackUrl, array $metadata = []): string
    {
        if ($this->fake()) {
            return route('checkout.sandbox-pay', ['reference' => $reference]);
        }

        $data = $this->http()->post('/transaction/initialize', [
            'reference' => $reference,
            'amount' => $amount, // kobo
            'email' => $email,
            'callback_url' => $callbackUrl,
            'metadata' => $metadata,
            'channels' => ['card', 'bank_transfer', 'ussd'],
        ])->throw()->json('data');

        return $data['authorization_url'] ?? throw new RuntimeException('Paystack did not return a checkout URL.');
    }

    /** @return array{status:string, amount:int}|null */
    public function verify(string $reference): ?array
    {
        if ($this->fake()) {
            return null;
        }
        $data = $this->http()->get('/transaction/verify/'.rawurlencode($reference))->json('data');

        return $data ? ['status' => $data['status'], 'amount' => (int) $data['amount']] : null;
    }
}
