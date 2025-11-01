<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContentVariant extends Model
{
    use HasFactory, HasUuids;

    /**
     * Platform constants.
     */
    public const PLATFORM_WEBSITE = 'website';
    public const PLATFORM_FACEBOOK = 'facebook';
    public const PLATFORM_TWITTER = 'twitter';
    public const PLATFORM_LINKEDIN = 'linkedin';
    public const PLATFORM_INSTAGRAM = 'instagram';

    /**
     * Status constants.
     */
    public const STATUS_PENDING = 'pending';
    public const STATUS_SCHEDULED = 'scheduled';
    public const STATUS_PUBLISHED = 'published';
    public const STATUS_FAILED = 'failed';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'content_draft_id',
        'platform',
        'title',
        'content',
        'formatting',
        'metadata',
        'scheduled_for',
        'status',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'formatting' => 'array',
            'metadata' => 'array',
            'scheduled_for' => 'datetime',
        ];
    }

    /**
     * Get the content draft this variant belongs to.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function contentDraft(): BelongsTo
    {
        return $this->belongsTo(ContentDraft::class);
    }

    /**
     * Get the publish jobs for this variant.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function publishJobs(): HasMany
    {
        return $this->hasMany(PublishJob::class);
    }

    /**
     * Scope a query to only include pending variants.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope a query to only include scheduled variants.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeScheduled($query)
    {
        return $query->where('status', self::STATUS_SCHEDULED);
    }

    /**
     * Scope a query to only include published variants.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopePublished($query)
    {
        return $query->where('status', self::STATUS_PUBLISHED);
    }

    /**
     * Scope a query to only include failed variants.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeFailed($query)
    {
        return $query->where('status', self::STATUS_FAILED);
    }

    /**
     * Scope a query to filter by platform.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $platform
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForPlatform($query, string $platform)
    {
        return $query->where('platform', $platform);
    }

    /**
     * Scope a query to filter variants scheduled before a certain time.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param \Carbon\Carbon|string $datetime
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeScheduledBefore($query, $datetime)
    {
        return $query->where('scheduled_for', '<=', $datetime);
    }

    /**
     * Scope a query to filter variants ready to publish.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeReadyToPublish($query)
    {
        return $query->where('status', self::STATUS_SCHEDULED)
            ->where('scheduled_for', '<=', now());
    }
}
