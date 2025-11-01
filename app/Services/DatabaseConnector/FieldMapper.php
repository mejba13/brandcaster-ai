<?php

namespace App\Services\DatabaseConnector;

use App\Exceptions\DatabaseConnectorException;

/**
 * Field Mapper
 *
 * Maps application content fields to external database columns
 * based on configurable field mapping.
 */
class FieldMapper
{
    /**
     * Map content fields to database columns
     *
     * @param array $content Application content (title, body, slug, etc.)
     * @param array $fieldMapping Mapping configuration
     * @return array Mapped data ready for database insert
     * @throws DatabaseConnectorException
     */
    public function map(array $content, array $fieldMapping): array
    {
        $mapped = [];

        foreach ($content as $appField => $value) {
            // Skip if field is not in mapping
            if (!isset($fieldMapping[$appField])) {
                continue;
            }

            $dbColumn = $fieldMapping[$appField];

            // Handle nested field mapping (e.g., 'meta_description' => 'post_meta.description')
            if (str_contains($dbColumn, '.')) {
                $mapped = $this->mapNestedField($mapped, $dbColumn, $value);
            } else {
                $mapped[$dbColumn] = $value;
            }
        }

        return $mapped;
    }

    /**
     * Map a nested field (e.g., for JSON columns or related tables)
     *
     * @param array $mapped Current mapped data
     * @param string $path Dotted path (e.g., 'post_meta.description')
     * @param mixed $value Value to set
     * @return array Updated mapped data
     */
    protected function mapNestedField(array $mapped, string $path, $value): array
    {
        $parts = explode('.', $path);

        // For now, we'll treat nested paths as JSON encoding
        // In future, this could support actual relational writes
        $rootField = array_shift($parts);

        if (count($parts) > 0) {
            // Initialize as empty array if not exists
            if (!isset($mapped[$rootField])) {
                $mapped[$rootField] = [];
            }

            // Build nested array
            $current = &$mapped[$rootField];
            foreach ($parts as $i => $part) {
                if ($i === count($parts) - 1) {
                    $current[$part] = $value;
                } else {
                    if (!isset($current[$part])) {
                        $current[$part] = [];
                    }
                    $current = &$current[$part];
                }
            }

            // JSON encode for storage
            $mapped[$rootField] = json_encode($mapped[$rootField]);
        } else {
            $mapped[$rootField] = $value;
        }

        return $mapped;
    }

    /**
     * Reverse map: Convert database columns back to application fields
     *
     * @param array $dbData Database row data
     * @param array $fieldMapping Mapping configuration
     * @return array Application content structure
     */
    public function reverseMap(array $dbData, array $fieldMapping): array
    {
        $content = [];

        // Flip the mapping (app_field => db_column becomes db_column => app_field)
        $reversedMapping = array_flip($fieldMapping);

        foreach ($dbData as $dbColumn => $value) {
            if (isset($reversedMapping[$dbColumn])) {
                $appField = $reversedMapping[$dbColumn];
                $content[$appField] = $value;
            }
        }

        return $content;
    }

    /**
     * Validate field mapping configuration
     *
     * @param array $fieldMapping
     * @return array Validation errors (empty if valid)
     */
    public function validate(array $fieldMapping): array
    {
        $errors = [];
        $requiredFields = ['title', 'body'];

        // Check required fields are mapped
        foreach ($requiredFields as $required) {
            if (!isset($fieldMapping[$required]) || empty($fieldMapping[$required])) {
                $errors[] = "Required field '$required' must be mapped";
            }
        }

        // Check for duplicate column mappings
        $columns = array_values($fieldMapping);
        $duplicates = array_filter(array_count_values($columns), fn($count) => $count > 1);

        if (!empty($duplicates)) {
            foreach ($duplicates as $column => $count) {
                $errors[] = "Database column '$column' is mapped multiple times";
            }
        }

        return $errors;
    }

    /**
     * Get default field mapping for common CMS platforms
     *
     * @param string $platform (wordpress, drupal, joomla, custom)
     * @return array Default field mapping
     */
    public function getDefaultMapping(string $platform): array
    {
        return match(strtolower($platform)) {
            'wordpress' => [
                'title' => 'post_title',
                'body' => 'post_content',
                'excerpt' => 'post_excerpt',
                'slug' => 'post_name',
                'status' => 'post_status',
                'published_at' => 'post_date',
                'meta_description' => 'post_meta.description',
                'featured_image' => 'thumbnail_url',
            ],
            'drupal' => [
                'title' => 'title',
                'body' => 'body',
                'excerpt' => 'summary',
                'slug' => 'alias',
                'status' => 'status',
                'published_at' => 'created',
            ],
            'joomla' => [
                'title' => 'title',
                'body' => 'introtext',
                'excerpt' => 'metadesc',
                'slug' => 'alias',
                'status' => 'state',
                'published_at' => 'publish_up',
            ],
            default => [
                'title' => 'title',
                'body' => 'content',
                'excerpt' => 'excerpt',
                'slug' => 'slug',
                'status' => 'status',
                'published_at' => 'published_at',
                'meta_description' => 'meta_description',
                'featured_image' => 'featured_image_url',
            ],
        };
    }
}
