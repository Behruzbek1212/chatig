<?php

namespace Tests\Support;

use App\Services\Sms\Contracts\SmsSender;

class FakeSmsSender implements SmsSender
{
    /** @var array<int, array{phone:string,message:string}> */
    public array $sent = [];

    public function send(string $phone, string $message): void
    {
        $this->sent[] = ['phone' => $phone, 'message' => $message];
    }

    public function lastCodeFor(string $phone): ?string
    {
        foreach (array_reverse($this->sent) as $entry) {
            if ($entry['phone'] === $phone && preg_match('/(\d{4,8})/', $entry['message'], $m)) {
                return $m[1];
            }
        }

        return null;
    }
}
