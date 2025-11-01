<?php

namespace App\Services\DatabaseConnector;

use App\Exceptions\DatabaseConnectorException;
use App\Models\WebsiteConnector;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Crypt;

/**
 * Database Connector Service
 *
 * Manages dynamic database connections and writes content to external databases
 * with heterogeneous schemas using configurable field mapping.
 */
class DatabaseConnectorService
{
    protected FieldMapper $fieldMapper;
    protected ConnectionTester $connectionTester;

    public function __construct(
        FieldMapper $fieldMapper,
        ConnectionTester $connectionTester
    ) {
        $this->fieldMapper = $fieldMapper;
        $this->connectionTester = $connectionTester;
    }

    /**
     * Create a dynamic database connection for the given connector
     *
     * @param WebsiteConnector $connector
     * @return string Connection name
     * @throws DatabaseConnectorException
     */
    public function createConnection(WebsiteConnector $connector): string
    {
        $connectionName = "dynamic_connector_{$connector->id}";

        try {
            $credentials = $this->decryptCredentials($connector->encrypted_credentials);

            config([
                "database.connections.$connectionName" => [
                    'driver' => $connector->driver,
                    'host' => $credentials['host'],
                    'port' => $credentials['port'] ?? $this->getDefaultPort($connector->driver),
                    'database' => $credentials['database'],
                    'username' => $credentials['username'],
                    'password' => $credentials['password'],
                    'charset' => $credentials['charset'] ?? 'utf8mb4',
                    'collation' => $credentials['collation'] ?? $this->getDefaultCollation($connector->driver),
                    'prefix' => $credentials['prefix'] ?? '',
                    'strict' => false,
                    'engine' => null,
                ]
            ]);

            return $connectionName;
        } catch (\Exception $e) {
            Log::error('Failed to create database connection', [
                'connector_id' => $connector->id,
                'error' => $e->getMessage()
            ]);

            throw new DatabaseConnectorException(
                "Failed to create database connection: {$e->getMessage()}",
                0,
                $e
            );
        }
    }

    /**
     * Test connection to external database
     *
     * @param WebsiteConnector $connector
     * @return bool
     */
    public function testConnection(WebsiteConnector $connector): bool
    {
        return $this->connectionTester->test($connector);
    }

    /**
     * Publish content to external database
     *
     * @param WebsiteConnector $connector
     * @param array $content Content data (title, body, slug, etc.)
     * @param bool $dryRun If true, simulate without writing
     * @return array Result with inserted ID or error
     * @throws DatabaseConnectorException
     */
    public function publish(
        WebsiteConnector $connector,
        array $content,
        bool $dryRun = false
    ): array {
        $connectionName = $this->createConnection($connector);

        try {
            // Map content fields to database columns
            $mappedData = $this->fieldMapper->map($content, $connector->field_mapping);

            // Add status if configured
            if ($connector->status_workflow) {
                $mappedData = $this->addStatus($mappedData, $connector->status_workflow);
            }

            // Generate slug if needed
            if ($connector->slug_policy === WebsiteConnector::SLUG_POLICY_AUTO) {
                $mappedData = $this->ensureSlug($mappedData, $connector->field_mapping);
            }

            // Add timestamps if mapped
            $mappedData = $this->addTimestamps($mappedData, $connector);

            if ($dryRun) {
                Log::info('Dry run - would insert data', [
                    'connector_id' => $connector->id,
                    'table' => $connector->table_name,
                    'data' => $mappedData
                ]);

                return [
                    'success' => true,
                    'dry_run' => true,
                    'data' => $mappedData,
                ];
            }

            // Start transaction
            DB::connection($connectionName)->beginTransaction();

            try {
                // Insert into external database
                $insertedId = DB::connection($connectionName)
                    ->table($connector->table_name)
                    ->insertGetId($mappedData);

                DB::connection($connectionName)->commit();

                Log::info('Successfully published content', [
                    'connector_id' => $connector->id,
                    'table' => $connector->table_name,
                    'inserted_id' => $insertedId
                ]);

                return [
                    'success' => true,
                    'inserted_id' => $insertedId,
                    'table' => $connector->table_name,
                ];
            } catch (\Exception $e) {
                DB::connection($connectionName)->rollBack();
                throw $e;
            }
        } catch (\Exception $e) {
            Log::error('Failed to publish content', [
                'connector_id' => $connector->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw new DatabaseConnectorException(
                "Failed to publish content: {$e->getMessage()}",
                0,
                $e
            );
        } finally {
            // Purge dynamic connection
            DB::purge($connectionName);
        }
    }

    /**
     * Update existing content in external database
     *
     * @param WebsiteConnector $connector
     * @param mixed $identifier Record identifier (ID or slug)
     * @param array $content Updated content data
     * @param string $identifierColumn Column name for identifier (e.g., 'id', 'slug')
     * @return array Result
     * @throws DatabaseConnectorException
     */
    public function update(
        WebsiteConnector $connector,
        $identifier,
        array $content,
        string $identifierColumn = 'id'
    ): array {
        $connectionName = $this->createConnection($connector);

        try {
            $mappedData = $this->fieldMapper->map($content, $connector->field_mapping);
            $mappedData = $this->addTimestamps($mappedData, $connector, true);

            DB::connection($connectionName)->beginTransaction();

            try {
                $updated = DB::connection($connectionName)
                    ->table($connector->table_name)
                    ->where($identifierColumn, $identifier)
                    ->update($mappedData);

                DB::connection($connectionName)->commit();

                Log::info('Successfully updated content', [
                    'connector_id' => $connector->id,
                    'identifier' => $identifier,
                    'updated' => $updated
                ]);

                return [
                    'success' => true,
                    'updated' => $updated > 0,
                ];
            } catch (\Exception $e) {
                DB::connection($connectionName)->rollBack();
                throw $e;
            }
        } catch (\Exception $e) {
            Log::error('Failed to update content', [
                'connector_id' => $connector->id,
                'identifier' => $identifier,
                'error' => $e->getMessage()
            ]);

            throw new DatabaseConnectorException(
                "Failed to update content: {$e->getMessage()}",
                0,
                $e
            );
        } finally {
            DB::purge($connectionName);
        }
    }

    /**
     * Check if record exists in external database
     *
     * @param WebsiteConnector $connector
     * @param mixed $identifier
     * @param string $identifierColumn
     * @return bool
     */
    public function exists(
        WebsiteConnector $connector,
        $identifier,
        string $identifierColumn = 'id'
    ): bool {
        $connectionName = $this->createConnection($connector);

        try {
            $exists = DB::connection($connectionName)
                ->table($connector->table_name)
                ->where($identifierColumn, $identifier)
                ->exists();

            return $exists;
        } finally {
            DB::purge($connectionName);
        }
    }

    /**
     * Decrypt connection credentials
     *
     * @param string $encrypted
     * @return array
     */
    protected function decryptCredentials(string $encrypted): array
    {
        try {
            return json_decode(Crypt::decryptString($encrypted), true);
        } catch (\Exception $e) {
            throw new DatabaseConnectorException('Failed to decrypt credentials');
        }
    }

    /**
     * Add status to mapped data based on workflow configuration
     *
     * @param array $data
     * @param array $statusWorkflow
     * @return array
     */
    protected function addStatus(array $data, array $statusWorkflow): array
    {
        // Default to 'published' status
        $statusValue = $statusWorkflow['published'] ?? 1;

        // Find the status field in mapped data or use default 'status'
        $statusField = $this->findStatusField($data) ?? 'status';

        $data[$statusField] = $statusValue;

        return $data;
    }

    /**
     * Find the status field in mapped data
     *
     * @param array $data
     * @return string|null
     */
    protected function findStatusField(array $data): ?string
    {
        $commonStatusFields = ['status', 'post_status', 'state', 'published'];

        foreach ($commonStatusFields as $field) {
            if (array_key_exists($field, $data)) {
                return $field;
            }
        }

        return null;
    }

    /**
     * Ensure slug exists in data
     *
     * @param array $data
     * @param array $fieldMapping
     * @return array
     */
    protected function ensureSlug(array $data, array $fieldMapping): array
    {
        // Find slug field from mapping
        $slugField = $fieldMapping['slug'] ?? 'slug';

        // If slug doesn't exist, generate from title
        if (empty($data[$slugField])) {
            $titleField = $fieldMapping['title'] ?? 'title';

            if (!empty($data[$titleField])) {
                $data[$slugField] = \Illuminate\Support\Str::slug($data[$titleField]);
            }
        }

        return $data;
    }

    /**
     * Add timestamps to data if mapped
     *
     * @param array $data
     * @param WebsiteConnector $connector
     * @param bool $isUpdate
     * @return array
     */
    protected function addTimestamps(
        array $data,
        WebsiteConnector $connector,
        bool $isUpdate = false
    ): array {
        $now = now()->setTimezone($connector->timezone)->toDateTimeString();

        // Common timestamp field names
        $createdFields = ['created_at', 'created', 'date_created', 'post_date'];
        $updatedFields = ['updated_at', 'modified', 'date_modified', 'post_modified'];

        if (!$isUpdate) {
            // Add created_at if field exists in mapping
            foreach ($createdFields as $field) {
                if (array_key_exists($field, $connector->field_mapping)) {
                    $data[$connector->field_mapping[$field]] = $now;
                    break;
                }
            }
        }

        // Add updated_at if field exists in mapping
        foreach ($updatedFields as $field) {
            if (array_key_exists($field, $connector->field_mapping)) {
                $data[$connector->field_mapping[$field]] = $now;
                break;
            }
        }

        return $data;
    }

    /**
     * Get default port for database driver
     *
     * @param string $driver
     * @return int
     */
    protected function getDefaultPort(string $driver): int
    {
        return match($driver) {
            'mysql' => 3306,
            'pgsql' => 5432,
            'sqlsrv' => 1433,
            default => 3306,
        };
    }

    /**
     * Get default collation for database driver
     *
     * @param string $driver
     * @return string
     */
    protected function getDefaultCollation(string $driver): string
    {
        return match($driver) {
            'mysql' => 'utf8mb4_unicode_ci',
            'pgsql' => 'utf8',
            'sqlsrv' => 'utf8',
            default => 'utf8mb4_unicode_ci',
        };
    }
}
