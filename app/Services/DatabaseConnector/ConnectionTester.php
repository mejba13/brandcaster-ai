<?php

namespace App\Services\DatabaseConnector;

use App\Models\WebsiteConnector;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

/**
 * Connection Tester
 *
 * Tests database connections and validates schema compatibility
 * before saving connector configurations.
 */
class ConnectionTester
{
    /**
     * Test connection to external database
     *
     * @param WebsiteConnector $connector
     * @return bool
     */
    public function test(WebsiteConnector $connector): bool
    {
        $connectionName = "test_connection_" . uniqid();

        try {
            $credentials = $this->decryptCredentials($connector->encrypted_credentials);

            // Create temporary connection config
            config([
                "database.connections.$connectionName" => [
                    'driver' => $connector->driver,
                    'host' => $credentials['host'],
                    'port' => $credentials['port'] ?? $this->getDefaultPort($connector->driver),
                    'database' => $credentials['database'],
                    'username' => $credentials['username'],
                    'password' => $credentials['password'],
                    'charset' => $credentials['charset'] ?? 'utf8mb4',
                    'collation' => $credentials['collation'] ?? 'utf8mb4_unicode_ci',
                ]
            ]);

            // Test connection
            DB::connection($connectionName)->getPdo();

            Log::info('Database connection test successful', [
                'connector_id' => $connector->id,
                'host' => $credentials['host'],
                'database' => $credentials['database']
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Database connection test failed', [
                'connector_id' => $connector->id,
                'error' => $e->getMessage()
            ]);

            return false;
        } finally {
            // Purge test connection
            DB::purge($connectionName);
        }
    }

    /**
     * Test connection and validate table exists
     *
     * @param WebsiteConnector $connector
     * @return array ['success' => bool, 'error' => string|null]
     */
    public function testWithTableValidation(WebsiteConnector $connector): array
    {
        $connectionName = "test_connection_" . uniqid();

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
                    'collation' => $credentials['collation'] ?? 'utf8mb4_unicode_ci',
                ]
            ]);

            // Test connection
            DB::connection($connectionName)->getPdo();

            // Test table exists
            if (!$this->tableExists($connectionName, $connector->table_name)) {
                return [
                    'success' => false,
                    'error' => "Table '{$connector->table_name}' does not exist in the database"
                ];
            }

            // Test write permissions (try to get count, which requires read permission)
            try {
                DB::connection($connectionName)->table($connector->table_name)->count();
            } catch (\Exception $e) {
                return [
                    'success' => false,
                    'error' => "No read permission for table '{$connector->table_name}': {$e->getMessage()}"
                ];
            }

            return [
                'success' => true,
                'error' => null
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        } finally {
            DB::purge($connectionName);
        }
    }

    /**
     * Get table schema (columns and types)
     *
     * @param WebsiteConnector $connector
     * @return array Column information
     */
    public function getTableSchema(WebsiteConnector $connector): array
    {
        $connectionName = "test_connection_" . uniqid();

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
                    'charset' => 'utf8mb4',
                    'collation' => 'utf8mb4_unicode_ci',
                ]
            ]);

            $columns = $this->getColumns($connectionName, $connector->table_name, $connector->driver);

            return $columns;
        } catch (\Exception $e) {
            Log::error('Failed to get table schema', [
                'connector_id' => $connector->id,
                'error' => $e->getMessage()
            ]);

            return [];
        } finally {
            DB::purge($connectionName);
        }
    }

    /**
     * Check if table exists
     *
     * @param string $connectionName
     * @param string $tableName
     * @return bool
     */
    protected function tableExists(string $connectionName, string $tableName): bool
    {
        try {
            $driver = config("database.connections.$connectionName.driver");

            return match($driver) {
                'mysql' => $this->mysqlTableExists($connectionName, $tableName),
                'pgsql' => $this->pgsqlTableExists($connectionName, $tableName),
                'sqlsrv' => $this->sqlsrvTableExists($connectionName, $tableName),
                default => false,
            };
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Check if MySQL table exists
     *
     * @param string $connectionName
     * @param string $tableName
     * @return bool
     */
    protected function mysqlTableExists(string $connectionName, string $tableName): bool
    {
        $database = config("database.connections.$connectionName.database");
        $result = DB::connection($connectionName)
            ->select("SELECT COUNT(*) as count FROM information_schema.tables WHERE table_schema = ? AND table_name = ?", [$database, $tableName]);

        return $result[0]->count > 0;
    }

    /**
     * Check if PostgreSQL table exists
     *
     * @param string $connectionName
     * @param string $tableName
     * @return bool
     */
    protected function pgsqlTableExists(string $connectionName, string $tableName): bool
    {
        $result = DB::connection($connectionName)
            ->select("SELECT EXISTS (SELECT FROM information_schema.tables WHERE table_name = ?) as exists", [$tableName]);

        return $result[0]->exists;
    }

    /**
     * Check if SQL Server table exists
     *
     * @param string $connectionName
     * @param string $tableName
     * @return bool
     */
    protected function sqlsrvTableExists(string $connectionName, string $tableName): bool
    {
        $result = DB::connection($connectionName)
            ->select("SELECT COUNT(*) as count FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = ?", [$tableName]);

        return $result[0]->count > 0;
    }

    /**
     * Get table columns
     *
     * @param string $connectionName
     * @param string $tableName
     * @param string $driver
     * @return array
     */
    protected function getColumns(string $connectionName, string $tableName, string $driver): array
    {
        return match($driver) {
            'mysql' => $this->getMysqlColumns($connectionName, $tableName),
            'pgsql' => $this->getPgsqlColumns($connectionName, $tableName),
            'sqlsrv' => $this->getSqlsrvColumns($connectionName, $tableName),
            default => [],
        };
    }

    /**
     * Get MySQL table columns
     *
     * @param string $connectionName
     * @param string $tableName
     * @return array
     */
    protected function getMysqlColumns(string $connectionName, string $tableName): array
    {
        $database = config("database.connections.$connectionName.database");
        $columns = DB::connection($connectionName)
            ->select("SELECT COLUMN_NAME, DATA_TYPE, IS_NULLABLE, COLUMN_DEFAULT, COLUMN_KEY FROM information_schema.columns WHERE table_schema = ? AND table_name = ? ORDER BY ORDINAL_POSITION", [$database, $tableName]);

        return array_map(function($column) {
            return [
                'name' => $column->COLUMN_NAME,
                'type' => $column->DATA_TYPE,
                'nullable' => $column->IS_NULLABLE === 'YES',
                'default' => $column->COLUMN_DEFAULT,
                'key' => $column->COLUMN_KEY,
            ];
        }, $columns);
    }

    /**
     * Get PostgreSQL table columns
     *
     * @param string $connectionName
     * @param string $tableName
     * @return array
     */
    protected function getPgsqlColumns(string $connectionName, string $tableName): array
    {
        $columns = DB::connection($connectionName)
            ->select("SELECT column_name, data_type, is_nullable, column_default FROM information_schema.columns WHERE table_name = ? ORDER BY ordinal_position", [$tableName]);

        return array_map(function($column) {
            return [
                'name' => $column->column_name,
                'type' => $column->data_type,
                'nullable' => $column->is_nullable === 'YES',
                'default' => $column->column_default,
            ];
        }, $columns);
    }

    /**
     * Get SQL Server table columns
     *
     * @param string $connectionName
     * @param string $tableName
     * @return array
     */
    protected function getSqlsrvColumns(string $connectionName, string $tableName): array
    {
        $columns = DB::connection($connectionName)
            ->select("SELECT COLUMN_NAME, DATA_TYPE, IS_NULLABLE, COLUMN_DEFAULT FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = ? ORDER BY ORDINAL_POSITION", [$tableName]);

        return array_map(function($column) {
            return [
                'name' => $column->COLUMN_NAME,
                'type' => $column->DATA_TYPE,
                'nullable' => $column->IS_NULLABLE === 'YES',
                'default' => $column->COLUMN_DEFAULT,
            ];
        }, $columns);
    }

    /**
     * Decrypt credentials
     *
     * @param string $encrypted
     * @return array
     */
    protected function decryptCredentials(string $encrypted): array
    {
        return json_decode(Crypt::decryptString($encrypted), true);
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
}
