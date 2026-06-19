<?php

namespace App\Models;

use Database\Factories\StoreFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $public_id
 * @property string $name
 * @property string $business_type
 * @property string $status
 * @property Carbon|null $trial_ends_at
 */
class Store extends Model
{
    /** @use HasFactory<StoreFactory> */
    use HasFactory;

    protected $fillable = ['public_id', 'name', 'business_type', 'status', 'trial_ends_at'];

    protected static function booted(): void
    {
        static::creating(function (Store $store): void {
            $store->public_id ??= (string) Str::ulid();
        });
    }

    protected function casts(): array
    {
        return [
            'trial_ends_at' => 'datetime',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function channels(): HasMany
    {
        return $this->hasMany(Channel::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function isOnTrial(): bool
    {
        return $this->trial_ends_at !== null && $this->trial_ends_at->isFuture();
    }

    /**
     * True once the store has an Instagram channel in the `connected` state.
     * Drives the mandatory onboarding gate (the dashboard is locked until this
     * is true).
     */
    public function hasConnectedInstagram(): bool
    {
        return $this->channels()
            ->where('type', 'instagram')
            ->where('status', 'connected')
            ->exists();
    }

    /**
     * Status of the post-connect AI bootstrap (prompt generation from the IG
     * profile): pending | ready | failed, or null before any Instagram connect.
     * Drives the onboarding "analysing…" screen.
     */
    public function aiSetupStatus(): ?string
    {
        return $this->instagramMeta()['ai_setup_status'] ?? null;
    }

    /**
     * Granular step of the post-connect AI bootstrap:
     * reading_profile | analyzing_posts | generating_prompt | saving |
     * embedding_facts | ready | failed, or null before any connect. Drives the
     * live progress UI.
     */
    public function aiSetupStep(): ?string
    {
        return $this->instagramMeta()['ai_setup_step'] ?? null;
    }

    /**
     * @return array<string, mixed>
     */
    private function instagramMeta(): array
    {
        /** @var Channel|null $channel */
        $channel = $this->channels()
            ->where('type', 'instagram')
            ->where('status', 'connected')
            ->latest('id')
            ->first();

        return $channel?->meta ?: [];
    }
}
