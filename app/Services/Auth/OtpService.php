<?php

namespace App\Services\Auth;

use App\Models\OtpCode;
use App\Services\Auth\Exceptions\OtpException;
use App\Services\Sms\Contracts\SmsSender;
use Illuminate\Support\Facades\Hash;

class OtpService
{
    public function __construct(private readonly SmsSender $sms) {}

    /**
     * Generate, persist (hashed) and send an OTP for the given phone + purpose.
     * Enforces resend cooldown and hourly limit.
     */
    public function request(string $phone, string $purpose): void
    {
        $config = config('chatig.otp');

        $recent = OtpCode::where('phone', $phone)
            ->where('created_at', '>=', now()->subSeconds($config['resend_cooldown']))
            ->exists();

        if ($recent) {
            throw OtpException::throttled();
        }

        $hourlyCount = OtpCode::where('phone', $phone)
            ->where('created_at', '>=', now()->subHour())
            ->count();

        if ($hourlyCount >= $config['max_per_hour']) {
            throw OtpException::throttled('Soatlik limitdan oshdingiz. Keyinroq urinib ko\'ring.');
        }

        $code = $this->generateCode($config['length']);

        OtpCode::create([
            'phone' => $phone,
            'code_hash' => Hash::make($code),
            'purpose' => $purpose,
            'expires_at' => now()->addSeconds($config['ttl_seconds']),
        ]);

        $this->sms->send($phone, "ChatiG tasdiqlash kodi: {$code}");
    }

    /**
     * Verify a code for the given phone + purpose. Consumes it on success.
     */
    public function verify(string $phone, string $purpose, string $code): void
    {
        $config = config('chatig.otp');

        $otp = OtpCode::where('phone', $phone)
            ->where('purpose', $purpose)
            ->whereNull('consumed_at')
            ->latest('id')
            ->first();

        if (! $otp) {
            throw OtpException::invalid();
        }

        if ($otp->isExpired()) {
            throw OtpException::expired();
        }

        if ($otp->attempts >= $config['max_attempts']) {
            throw OtpException::throttled('Juda ko\'p noto\'g\'ri urinish. Yangi kod so\'rang.');
        }

        if (! Hash::check($code, $otp->code_hash)) {
            $otp->increment('attempts');
            throw OtpException::invalid();
        }

        $otp->update(['consumed_at' => now()]);
    }

    private function generateCode(int $length): string
    {
        $max = (10 ** $length) - 1;

        return str_pad((string) random_int(0, $max), $length, '0', STR_PAD_LEFT);
    }
}
