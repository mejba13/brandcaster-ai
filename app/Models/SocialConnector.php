<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Crypt;

class SocialConnector extends Model
{
    use HasFactory, HasUuids;

    /**
     * Platform constants.
     */
    public const PLATFORM_FACEBOOK = 'facebook';
    public const PLATFORM_TWITTER = 'twitter';
    public const PLATFORM_LINKEDIN = 'linkedin';
    public const PLATFORM_INSTAGRAM = 'instagram';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'brand_id',
        'platform',
        'account_name',
        'account_id',
        'encrypted_token',
        'token_expires_at',
        'platform_settings',
        'rate_limits',
        'last_posted_at',
        'active',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'platform_settings' => 'array',
            'rate_limits' => 'array',
            'token_expires_at' => 'datetime',
            'last_posted_at' => 'datetime',
            'active' => 'boolean',
        ];
    }

    /**
     * Get the brand that owns this connector.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    /**
     * Get all publish jobs for this connector.
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphMany
     */
    public function publishJobs(): MorphMany
    {
        return $this->morphMany(PublishJob::class, 'connector');
    }

    /**
     * Interact with the encrypted_token attribute.
     *
     * @return \Illuminate\Database\Eloquent\Casts\Attribute
     */
    protected function encryptedToken(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $value ? Crypt::decryptString($value) : null,
            set: fn (?string $value) => $value ? Crypt::encryptString($value) : null,
        );
    }

    /**
     * Scope a query to only include active connectors.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    /**
     * Scope a query to filter by brand.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $brandId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForBrand($query, string $brandId)
    {
        return $query->where('brand_id', $brandId);
    }

    /**
     * Scope a query to filter by platform.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $platform
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopePlatform($query, string $platform)
    {
        return $query->where('platform', $platform);
    }

    /**
     * Check if the token is expired or about to expire.
     *
     * @return bool
     */
    public function isTokenExpired(): bool
    {
        if (!$this->token_expires_at) {
            return false;
        }

        return $this->token_expires_at->isPast();
    }

    /**
     * Check if the token will expire soon (within 7 days).
     *
     * @return bool
     */
    public function isTokenExpiringSoon(): bool
    {
        if (!$this->token_expires_at) {
            return false;
        }

        return $this->token_expires_at->isBetween(now(), now()->addDays(7));
    }
}
