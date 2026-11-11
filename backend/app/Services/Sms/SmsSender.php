<?php

namespace App\Services\Sms;

use App\Support\Phone;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/** Termii in live mode; writes to the log in fake mode. */
class SmsSender
{
    public function send(string $phone, string $message): void
    {
        if (config('haske.drivers.sms') === 'fake') {
            Log::channel('single')->info("[SMS to {$phone}] {$message}");
            cache()->put('haske.sandbox.last_sms.'.$phone, $message, now()->addHour());

            return;
        }

        $cfg = config('haske.termii');
        $res = Http::baseUrl($cfg['base_url'])->acceptJson()->timeout(20)->post('/sms/send', [
            'api_key' => $cfg['api_key'],
            'to' => Phone::international($phone),
            'from' => $cfg['sender_id'],
            'sms' => $message,
            'type' => 'plain',
            'channel' => $cfg['channel'],
        ]);

        if ($res->failed()) {
            throw new RuntimeException('Termii send failed: '.$res->body());
        }
    }
}
