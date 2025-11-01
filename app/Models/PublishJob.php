<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class PublishJob extends Model
{
    use HasFactory, HasUuids;

    /**
     * Status constants.
     */
    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_PUBLISHED = 'published';
    public const STATUS_FAILED = 'failed';

    /**
     * Maximum retry attempts.
     */
    public const MAX_ATTEMPTS = 3;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'content_variant_id',
        'connector_id',
        'connector_type',
        'idempotency_key',
        'scheduled_at',
        'published_at',
        'status',
        'result',
        'attempt_count',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'published_at' => 'datetime',
            'result' => 'array',
            'attempt_count' => 'integer',
        ];
    }

    /**
     * Get the content draft this job is for.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function contentDraft(): BelongsTo
    {
        return $this->belongsTo(ContentDraft::class);
    }

    /**
     * Get the content variant this job is for.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function contentVariant(): BelongsTo
    {
        return $this->belongsTo(ContentVariant::class);
    }

    /**
     * Get the connector (WebsiteConnector or SocialConnector).
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphTo
     */
    public function connector(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the metrics for this publish job.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function metrics(): HasMany
    {
        return $this->hasMany(Metric::class);
    }

    /**
     * Scope a query to only include pending jobs.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope a query to only include processing jobs.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeProcessing($query)
    {
        return $query->where('status', self::STATUS_PROCESSING);
    }

    /**
     * Scope a query to only include published jobs.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopePublished($query)
    {
        return $query->where('status', self::STATUS_PUBLISHED);
    }

    /**
     * Scope a query to only include failed jobs.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeFailed($query)
    {
        return $query->where('status', self::STATUS_FAILED);
    }

    /**
     * Scope a query to filter jobs ready to execute.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeReadyToExecute($query)
    {
        return $query->where('status', self::STATUS_PENDING)
            ->where('scheduled_at', '<=', now());
    }

    /**
     * Scope a query to filter jobs by connector type.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $connectorType
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForConnectorType($query, string $connectorType)
    {
        return $query->where('connector_type', $connectorType);
    }

    /**
     * Check if the job can be retried.
     *
     * @return bool
     */
    public function canRetry(): bool
    {
        return $this->attempt_count < self::MAX_ATTEMPTS;
    }

    /**
     * Increment the attempt count.
     *
     * @return bool
     */
    public function incrementAttempts(): bool
    {
        return $this->increment('attempt_count');
    }

    /**
     * Mark the job as processing.
     *
     * @return bool
     */
    public function markAsProcessing(): bool
    {
        return $this->update(['status' => self::STATUS_PROCESSING]);
    }

    /**
     * Mark the job as published.
     *
     * @param array $result
     * @return bool
     */
    public function markAsPublished(array $result = []): bool
    {
        return $this->update([
            'status' => self::STATUS_PUBLISHED,
            'published_at' => now(),
            'result' => $result,
        ]);
    }

    /**
     * Mark the job as failed.
     *
     * @param array $result
     * @return bool
     */
    public function markAsFailed(array $result = []): bool
    {
        return $this->update([
            'status' => self::STATUS_FAILED,
            'result' => $result,
        ]);
    }
}
