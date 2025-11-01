<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Brand extends Model
{
    use HasFactory, HasUuids;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'name',
        'slug',
        'domain',
        'brand_voice',
        'style_guide',
        'settings',
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
            'brand_voice' => 'array',
            'style_guide' => 'array',
            'settings' => 'array',
            'active' => 'boolean',
        ];
    }

    /**
     * Get the categories for this brand.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
    }

    /**
     * Get the website connectors for this brand.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function websiteConnectors(): HasMany
    {
        return $this->hasMany(WebsiteConnector::class);
    }

    /**
     * Get the social connectors for this brand.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function socialConnectors(): HasMany
    {
        return $this->hasMany(SocialConnector::class);
    }

    /**
     * Get the topics discovered for this brand.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function topics(): HasMany
    {
        return $this->hasMany(Topic::class);
    }

    /**
     * Get the content drafts for this brand.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function contentDrafts(): HasMany
    {
        return $this->hasMany(ContentDraft::class);
    }

    /**
     * Get the users that belong to this brand.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'brand_user')
            ->using(BrandUser::class)
            ->withPivot('role')
            ->withTimestamps();
    }

    /**
     * Get the prompt templates for this brand.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function promptTemplates(): HasMany
    {
        return $this->hasMany(PromptTemplate::class);
    }

    /**
     * Scope a query to only include active brands.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActive($query)
    {
        return $query->where('active', true);
    }
}
