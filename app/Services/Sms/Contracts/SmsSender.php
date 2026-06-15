<?php

namespace App\Services\Sms\Contracts;

interface SmsSender
{
    /**
     * Send an SMS to the given (normalized +998...) phone number.
     */
    public function send(string $phone, string $message): void;
}
