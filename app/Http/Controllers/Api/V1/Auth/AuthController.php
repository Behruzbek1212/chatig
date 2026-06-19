<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\VerifyLoginRequest;
use App\Http\Requests\Auth\VerifyRegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\Store;
use App\Models\User;
use App\Services\Auth\OtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AuthController extends ApiController
{
    public function __construct(private readonly OtpService $otp) {}

    /**
     * Single entry point for the unified auth screen: the SPA submits the phone
     * first and branches to login (existing) or registration (new) based on the
     * `exists` flag. Deliberately reveals existence — the product favours UX here.
     */
    public function checkPhone(LoginRequest $request): JsonResponse
    {
        return $this->ok([
            'exists' => User::where('phone', $request->string('phone'))->exists(),
        ]);
    }

    public function register(RegisterRequest $request): JsonResponse
    {
        DB::transaction(function () use ($request): void {
            $store = Store::create([
                'name' => $request->string('company_name'),
                'business_type' => $request->string('business_type'),
                'trial_ends_at' => now()->addDays(config('chatig.trial_days')),
            ]);

            User::create([
                'store_id' => $store->id,
                'phone' => $request->string('phone'),
            ]);
        });

        $this->otp->request($request->string('phone'), 'register');

        return $this->message('Tasdiqlash kodi yuborildi.', 201);
    }

    public function verifyRegister(VerifyRegisterRequest $request): JsonResponse
    {
        $phone = $request->string('phone');

        $this->otp->verify($phone, 'register', $request->string('code'));

        $user = User::where('phone', $phone)->firstOrFail();
        $user->forceFill(['phone_verified_at' => now()])->save();
        $user->load('store');

        return $this->tokenResponse($user);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $phone = $request->string('phone');

        // Avoid user enumeration: always return the same message; only send if exists.
        if (User::where('phone', $phone)->exists()) {
            $this->otp->request($phone, 'login');
        }

        return $this->message('Agar raqam ro\'yxatda bo\'lsa, tasdiqlash kodi yuborildi.');
    }

    public function verifyLogin(VerifyLoginRequest $request): JsonResponse
    {
        $phone = $request->string('phone');

        $this->otp->verify($phone, 'login', $request->string('code'));

        $user = User::where('phone', $phone)->firstOrFail();

        if (! $user->hasVerifiedPhone()) {
            $user->forceFill(['phone_verified_at' => now()])->save();
        }

        $user->load('store');

        return $this->tokenResponse($user);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return $this->message('Tizimdan chiqdingiz.');
    }

    public function me(Request $request): JsonResponse
    {
        return $this->ok(new UserResource($request->user()->load('store')));
    }

    private function tokenResponse(User $user): JsonResponse
    {
        $token = $user->createToken('spa')->plainTextToken;

        return $this->ok([
            'token' => $token,
            'user' => new UserResource($user),
        ]);
    }
}
