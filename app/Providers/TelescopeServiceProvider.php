<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Telescope\IncomingEntry;
use Laravel\Telescope\Telescope;
use Laravel\Telescope\TelescopeApplicationServiceProvider;

class TelescopeServiceProvider extends TelescopeApplicationServiceProvider
{
    public function register(): void
    {
        $this->hideSensitiveRequestDetails();

        $isLocal = $this->app->environment('local');

        Telescope::filter(function (IncomingEntry $entry) use ($isLocal) {
            return $isLocal ||
                   $entry->isReportableException() ||
                   $entry->isFailedRequest() ||
                   $entry->isFailedJob() ||
                   $entry->isScheduledTask() ||
                   $entry->hasMonitoredTag();
        });
    }

    protected function hideSensitiveRequestDetails(): void
    {
        Telescope::hideRequestParameters([
            '_token',
            'password',
            'password_confirmation',
            'token',
            'access_token',
            'refresh_token',
            'api_key',
            'otp',
            'code',
        ]);

        Telescope::hideRequestHeaders([
            'authorization',
            'cookie',
            'x-api-key',
            'x-hub-signature-256',
            'x-csrf-token',
            'x-xsrf-token',
        ]);
    }

    protected function gate(): void
    {
        Gate::define('viewTelescope', function (?User $user) {
            if ($user === null) {
                return false;
            }

            /** @var list<string> $allowedPhones */
            $allowedPhones = config('telescope.allowed_phones', []);
            if ($allowedPhones !== [] && in_array($user->phone, $allowedPhones, true)) {
                return true;
            }

            /** @var list<string> $allowedEmails */
            $allowedEmails = config('telescope.allowed_emails', []);
            if ($allowedEmails !== [] && $user->email !== null && in_array($user->email, $allowedEmails, true)) {
                return true;
            }

            return false;
        });
    }
}
