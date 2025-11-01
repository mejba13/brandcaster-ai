<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Metric extends Model
{
    use HasFactory, HasUuids;

    /**
     * Metric type constants.
     */
    public const TYPE_IMPRESSIONS = 'impressions';
    public const TYPE_CLICKS = 'clicks';
    public const TYPE_LIKES = 'likes';
    public const TYPE_SHARES = 'shares';
    public const TYPE_COMMENTS = 'comments';
    public const TYPE_CTR = 'ctr';
    public const TYPE_ENGAGEMENT = 'engagement';
    public const TYPE_REACH = 'reach';
    public const TYPE_VIEWS = 'views';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'publish_job_id',
        'metric_type',
        'value',
        'recorded_at',
        'metadata',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'value' => 'integer',
            'recorded_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    /**
     * Get the publish job this metric belongs to.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function publishJob(): BelongsTo
    {
        return $this->belongsTo(PublishJob::class);
    }

    /**
     * Scope a query to filter by metric type.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $type
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('metric_type', $type);
    }

    /**
     * Scope a query to filter metrics recorded after a date.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param \Carbon\Carbon|string $date
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeRecordedAfter($query, $date)
    {
        return $query->where('recorded_at', '>=', $date);
    }

    /**
     * Scope a query to filter metrics recorded before a date.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param \Carbon\Carbon|string $date
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeRecordedBefore($query, $date)
    {
        return $query->where('recorded_at', '<=', $date);
    }

    /**
     * Scope a query to filter metrics recorded between dates.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param \Carbon\Carbon|string $startDate
     * @param \Carbon\Carbon|string $endDate
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeRecordedBetween($query, $startDate, $endDate)
    {
        return $query->whereBetween('recorded_at', [$startDate, $endDate]);
    }

    /**
     * Scope a query to order by most recent.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeRecent($query)
    {
        return $query->orderBy('recorded_at', 'desc');
    }

    /**
     * Scope a query to only include engagement metrics.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeEngagement($query)
    {
        return $query->whereIn('metric_type', [
            self::TYPE_LIKES,
            self::TYPE_SHARES,
            self::TYPE_COMMENTS,
            self::TYPE_ENGAGEMENT,
        ]);
    }
}
