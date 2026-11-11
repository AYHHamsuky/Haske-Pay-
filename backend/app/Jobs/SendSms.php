<?php

namespace App\Jobs;

use App\Services\Sms\SmsSender;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendSms implements ShouldQueue
{
    use Queueable;

    public int $tries = 4;

    public array $backoff = [10, 60, 300];

    public function __construct(public string $phone, public string $message)
    {
        $this->onQueue('sms');
    }

    public function handle(SmsSender $sms): void
    {
        $sms->send($this->phone, $this->message);
    }
}
