<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Crypt;

class WebsiteConnector extends Model
{
    use HasFactory, HasUuids;

    /**
     * Database driver constants.
     */
    public const DRIVER_MYSQL = 'mysql';
    public const DRIVER_PGSQL = 'pgsql';
    public const DRIVER_SQLSRV = 'sqlsrv';

    /**
     * Slug policy constants.
     */
    public const SLUG_POLICY_AUTO = 'auto_generate';
    public const SLUG_POLICY_MANUAL = 'manual';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'brand_id',
        'name',
        'driver',
        'encrypted_credentials',
        'table_name',
        'field_mapping',
        'status_workflow',
        'slug_policy',
        'timezone',
        'last_tested_at',
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
            'field_mapping' => 'array',
            'status_workflow' => 'array',
            'last_tested_at' => 'datetime',
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
     * Interact with the encrypted_credentials attribute.
     *
     * @return \Illuminate\Database\Eloquent\Casts\Attribute
     */
    protected function encryptedCredentials(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $value ? json_decode(Crypt::decryptString($value), true) : null,
            set: fn (?array $value) => $value ? Crypt::encryptString(json_encode($value)) : null,
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
     * Scope a query to filter by driver.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $driver
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeDriver($query, string $driver)
    {
        return $query->where('driver', $driver);
    }
}
