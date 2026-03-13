<?php

declare(strict_types=1);

namespace App\Services\Projects;

use App\Models\Project;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use VibecodePC\Common\Enums\ProjectFramework;

class PortAllocatorService
{
    private const MIN_PORT = 1024;

    private const MAX_PORT = 65535;

    private const MAX_RETRIES = 3;

    private const RETRY_BASE_DELAY_MS = 100;

    private const LOCK_KEY = 'port_allocator';

    public function allocate(ProjectFramework $framework): int
    {
        $lastException = null;

        for ($attempt = 0; $attempt < self::MAX_RETRIES; $attempt++) {
            if ($attempt > 0) {
                $delay = $this->calculateBackoffDelay($attempt);
                Log::debug('Port allocation conflict detected, retrying', [
                    'attempt' => $attempt + 1,
                    'max_retries' => self::MAX_RETRIES,
                    'delay_ms' => $delay,
                ]);
                usleep($delay * 1000);
            }

            try {
                return $this->attemptAllocate($framework);
            } catch (QueryException $e) {
                if ($this->isUniqueConstraintViolation($e)) {
                    $lastException = $e;

                    continue;
                }
                throw $e;
            }
        }

        throw new RuntimeException(
            sprintf(
                'Failed to allocate port after %d attempts due to concurrent conflicts. Last error: %s',
                self::MAX_RETRIES,
                $lastException?->getMessage() ?? 'Unknown error'
            )
        );
    }

    /**
     * Allocate a port and execute a callback within the same transaction.
     * This prevents race conditions by keeping the lock held during project creation.
     *
     * @param  ProjectFramework  $framework  The framework to allocate a port for
     * @param  Closure(int): Project  $callback  Callback that receives the allocated port and returns the Project
     * @return Project The created project
     *
     * @throws RuntimeException If port allocation fails after max retries
     */
    public function allocateAndCreate(ProjectFramework $framework, Closure $callback): Project
    {
        $lastException = null;

        for ($attempt = 0; $attempt < self::MAX_RETRIES; $attempt++) {
            if ($attempt > 0) {
                $delay = $this->calculateBackoffDelay($attempt);
                Log::debug('Port allocation conflict detected, retrying', [
                    'attempt' => $attempt + 1,
                    'max_retries' => self::MAX_RETRIES,
                    'delay_ms' => $delay,
                ]);
                usleep($delay * 1000);
            }

            try {
                return DB::transaction(function () use ($framework, $callback) {
                    // Acquire serialization lock to prevent concurrent port allocations
                    $this->acquireSerializationLock();

                    $port = $this->findAvailablePort($framework);

                    return $callback($port);
                });
            } catch (QueryException $e) {
                if ($this->isUniqueConstraintViolation($e)) {
                    $lastException = $e;

                    continue;
                }
                throw $e;
            }
        }

        throw new RuntimeException(
            sprintf(
                'Failed to allocate port after %d attempts due to concurrent conflicts. Last error: %s',
                self::MAX_RETRIES,
                $lastException?->getMessage() ?? 'Unknown error'
            )
        );
    }

    private function attemptAllocate(ProjectFramework $framework): int
    {
        return DB::transaction(function () use ($framework) {
            // Acquire serialization lock to prevent concurrent port allocations
            $this->acquireSerializationLock();

            return $this->findAvailablePort($framework);
        });
    }

    /**
     * Acquire a serialization lock using database advisory locks.
     * This ensures only one process can allocate ports at a time.
     *
     * Supported drivers: pgsql, mysql
     */
    private function acquireSerializationLock(): void
    {
        $driver = DB::getDriverName();

        match ($driver) {
            'pgsql' => $this->acquirePostgresAdvisoryLock(),
            'mysql', 'mariadb' => $this->acquireMysqlAdvisoryLock(),
            'sqlite' => $this->acquireSqliteLock(),
            default => $this->acquireFallbackLock(),
        };
    }

    /**
     * Acquire PostgreSQL advisory lock using pg_advisory_lock.
     * The lock is automatically released when the transaction ends.
     */
    private function acquirePostgresAdvisoryLock(): void
    {
        // Generate a 64-bit hash from the lock key
        $keyHash = crc32(self::LOCK_KEY);
        DB::statement('SELECT pg_advisory_xact_lock(?)', [$keyHash]);
    }

    /**
     * Acquire MySQL advisory lock using GET_LOCK.
     * Note: This requires the lock to be explicitly released, so we use a
     * transaction-scoped approach with a dedicated lock table.
     */
    private function acquireMysqlAdvisoryLock(): void
    {
        // Use table-level locking via a sentinel row
        // We insert a dummy row with a specific ID and lock it
        // This effectively serializes all port allocations
        DB::statement('
            INSERT INTO port_allocation_locks (lock_key, locked_at)
            VALUES (?, NOW())
            ON DUPLICATE KEY UPDATE locked_at = NOW()
        ', [self::LOCK_KEY]);

        // Now lock this row - this will block other transactions
        DB::table('port_allocation_locks')
            ->where('lock_key', self::LOCK_KEY)
            ->lockForUpdate()
            ->first();
    }

    /**
     * Acquire SQLite lock using a dedicated lock table.
     * SQLite uses exclusive transaction locks, but we add an explicit
     * row lock for consistency across database types.
     */
    private function acquireSqliteLock(): void
    {
        // For SQLite, we use the same approach as MySQL
        // Insert or replace the lock row
        DB::statement('
            INSERT INTO port_allocation_locks (lock_key, locked_at)
            VALUES (?, datetime("now"))
            ON CONFLICT(lock_key) DO UPDATE SET locked_at = datetime("now")
        ', [self::LOCK_KEY]);

        // Lock the row
        DB::table('port_allocation_locks')
            ->where('lock_key', self::LOCK_KEY)
            ->lockForUpdate()
            ->first();
    }

    /**
     * Fallback lock for unsupported database drivers.
     * Uses table-level locking via the projects table.
     */
    private function acquireFallbackLock(): void
    {
        // Lock all existing projects - this will block other transactions
        // Note: This is less efficient but works on any database
        Project::query()->lockForUpdate()->first();
    }

    private function findAvailablePort(ProjectFramework $framework): int
    {
        $port = $framework->defaultPort();

        if ($port < self::MIN_PORT) {
            $port = self::MIN_PORT;
        }

        if ($port > self::MAX_PORT) {
            throw new RuntimeException(
                "Framework default port {$port} exceeds maximum allowed port ".self::MAX_PORT
            );
        }

        // With the serialization lock held, this query is safe from race conditions
        $usedPorts = Project::pluck('port')
            ->filter()
            ->all();

        while (in_array($port, $usedPorts, true)) {
            $port++;

            if ($port > self::MAX_PORT) {
                throw new RuntimeException(
                    'No available ports in range '.self::MIN_PORT.'-'.self::MAX_PORT
                );
            }
        }

        return $port;
    }

    private function isUniqueConstraintViolation(QueryException $e): bool
    {
        $errorCode = $e->getCode();
        $errorMessage = $e->getMessage();

        $sqliteUniqueViolation = str_contains($errorMessage, 'UNIQUE constraint failed');
        $mysqlUniqueViolation = $errorCode === '23000' || str_contains($errorMessage, 'Duplicate entry');
        $postgresUniqueViolation = $errorCode === '23505' || str_contains($errorMessage, 'unique constraint');

        return $sqliteUniqueViolation || $mysqlUniqueViolation || $postgresUniqueViolation;
    }

    private function calculateBackoffDelay(int $attempt): int
    {
        $delay = self::RETRY_BASE_DELAY_MS * (2 ** ($attempt - 1));

        $jitter = random_int(0, (int) ($delay * 0.5));

        return min($delay + $jitter, 1000);
    }
}
