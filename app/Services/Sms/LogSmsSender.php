<?php

namespace App\Services\Sms;

use App\Services\Sms\Contracts\SmsSender;
use Illuminate\Support\Facades\Log;

/**
 * Default driver for local/dev: writes the SMS to the log instead of sending.
 */
class LogSmsSender implements SmsSender
{
    public function send(string $phone, string $message): void
    {
        Log::info('SMS (log driver)', ['phone' => $phone, 'message' => $message]);
    }
}
