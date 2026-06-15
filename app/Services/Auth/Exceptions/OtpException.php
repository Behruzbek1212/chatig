<?php

namespace App\Services\Auth\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OtpException extends Exception
{
    public function __construct(string $message, public readonly int $status = 422)
    {
        parent::__construct($message);
    }

    public static function throttled(string $message = 'Tez-tez urinmoqdasiz. Birozdan keyin qayta urinib ko\'ring.'): self
    {
        return new self($message, 429);
    }

    public static function invalid(string $message = 'Kod noto\'g\'ri.'): self
    {
        return new self($message, 422);
    }

    public static function expired(string $message = 'Kod muddati tugagan. Yangi kod so\'rang.'): self
    {
        return new self($message, 422);
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json(['message' => $this->getMessage()], $this->status);
    }
}
