<?php

namespace App\Services\Providers;

use App\Models\Service;
use App\Models\Transaction;

interface BillProvider
{
    public function name(): string;

    public function verifyMeter(Service $service, string $meter, string $meterType): VerifyResult;

    /** Send the purchase. Must be safe to call with the same request_id twice. */
    public function purchase(Transaction $tx): ProviderResult;

    /** Ask for the final state of a purchase we already sent. */
    public function requery(Transaction $tx): ProviderResult;

    /** @return list<array{code:string,name:string,amount:int}> amounts in kobo */
    public function dataPlans(Service $service): array;

    /** Our float with the provider, kobo. Null if unknown. */
    public function balance(): ?int;
}
