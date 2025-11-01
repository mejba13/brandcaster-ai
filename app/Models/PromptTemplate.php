<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PromptTemplate extends Model
{
    use HasFactory, HasUuids;

    /**
     * Template type constants.
     */
    public const TYPE_BRIEF = 'brief';
    public const TYPE_OUTLINE = 'outline';
    public const TYPE_DRAFT = 'draft';
    public const TYPE_VARIANT = 'variant';

    /**
     * Platform constants.
     */
    public const PLATFORM_WEBSITE = 'website';
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
        'name',
        'type',
        'platform',
        'template',
        'version',
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
            'version' => 'integer',
            'active' => 'boolean',
        ];
    }

    /**
     * Get the brand that owns this template (null for global templates).
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    /**
     * Scope a query to only include active templates.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    /**
     * Scope a query to only include global templates.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeGlobal($query)
    {
        return $query->whereNull('brand_id');
    }

    /**
     * Scope a query to filter by brand (including global).
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $brandId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForBrand($query, string $brandId)
    {
        return $query->where(function ($q) use ($brandId) {
            $q->where('brand_id', $brandId)
                ->orWhereNull('brand_id');
        });
    }

    /**
     * Scope a query to filter by type.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $type
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
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
     * Scope a query to get the latest version.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeLatestVersion($query)
    {
        return $query->orderBy('version', 'desc');
    }

    /**
     * Replace template variables with actual values.
     *
     * @param array $variables
     * @return string
     */
    public function render(array $variables = []): string
    {
        $template = $this->template;

        foreach ($variables as $key => $value) {
            $placeholder = '{' . $key . '}';
            $template = str_replace($placeholder, $value, $template);
        }

        return $template;
    }

    /**
     * Check if this is a global template.
     *
     * @return bool
     */
    public function isGlobal(): bool
    {
        return is_null($this->brand_id);
    }

    /**
     * Create a new version of this template.
     *
     * @param string $newTemplate
     * @return self
     */
    public function createNewVersion(string $newTemplate): self
    {
        return static::create([
            'brand_id' => $this->brand_id,
            'name' => $this->name,
            'type' => $this->type,
            'platform' => $this->platform,
            'template' => $newTemplate,
            'version' => $this->version + 1,
            'active' => true,
        ]);
    }
}
