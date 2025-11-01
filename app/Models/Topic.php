<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Topic extends Model
{
    use HasFactory, HasUuids;

    /**
     * Status constants.
     */
    public const STATUS_DISCOVERED = 'discovered';
    public const STATUS_QUEUED = 'queued';
    public const STATUS_USED = 'used';
    public const STATUS_EXPIRED = 'expired';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'brand_id',
        'category_id',
        'title',
        'description',
        'keywords',
        'source_urls',
        'confidence_score',
        'trending_at',
        'used_at',
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
            'keywords' => 'array',
            'source_urls' => 'array',
            'confidence_score' => 'decimal:4',
            'trending_at' => 'datetime',
            'used_at' => 'datetime',
        ];
    }

    /**
     * Get the brand that owns this topic.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    /**
     * Get the category this topic belongs to.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Get the content drafts created from this topic.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function contentDrafts(): HasMany
    {
        return $this->hasMany(ContentDraft::class);
    }

    /**
     * Scope a query to only include discovered topics.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeDiscovered($query)
    {
        return $query->where('status', self::STATUS_DISCOVERED);
    }

    /**
     * Scope a query to only include queued topics.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeQueued($query)
    {
        return $query->where('status', self::STATUS_QUEUED);
    }

    /**
     * Scope a query to only include used topics.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeUsed($query)
    {
        return $query->where('status', self::STATUS_USED);
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
     * Scope a query to filter by category.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $categoryId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForCategory($query, string $categoryId)
    {
        return $query->where('category_id', $categoryId);
    }

    /**
     * Scope a query to order by trending date.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $direction
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeTrending($query, string $direction = 'desc')
    {
        return $query->orderBy('trending_at', $direction);
    }

    /**
     * Scope a query to filter by minimum confidence score.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param float $minScore
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeHighConfidence($query, float $minScore = 0.7)
    {
        return $query->where('confidence_score', '>=', $minScore);
    }

    /**
     * Mark this topic as used.
     *
     * @return bool
     */
    public function markAsUsed(): bool
    {
        return $this->update([
            'status' => self::STATUS_USED,
            'used_at' => now(),
        ]);
    }
}
