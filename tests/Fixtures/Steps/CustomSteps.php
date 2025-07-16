<?php

declare(strict_types=1);

namespace Tests\Fixtures\Steps;

use Closure;
use Exception;
use Grazulex\LaravelFlowpipe\Contracts\FlowStep;

/**
 * Example custom step for authentication
 */
final class AuthenticationStep implements FlowStep
{
    public function __construct(
        private array $requiredPermissions = []
    ) {}

    public function handle(mixed $payload, Closure $next): mixed
    {
        if (! isset($payload['user_id'])) {
            return $next([
                ...$payload,
                'error' => 'Authentication required',
                'authenticated' => false,
            ]);
        }

        $hasPermissions = empty($this->requiredPermissions) ||
            $this->checkPermissions($payload['user_id'], $this->requiredPermissions);

        if (! $hasPermissions) {
            return $next([
                ...$payload,
                'error' => 'Insufficient permissions',
                'authenticated' => false,
            ]);
        }

        return $next([
            ...$payload,
            'authenticated' => true,
            'permissions_checked' => $this->requiredPermissions,
        ]);
    }

    private function checkPermissions(int $userId, array $permissions): bool
    {
        // Simulation - in real app, check against database
        return true;
    }
}

/**
 * Example custom step for data validation
 */
final class DataValidationStep implements FlowStep
{
    public function __construct(
        private array $rules = []
    ) {}

    public function handle(mixed $payload, Closure $next): mixed
    {
        $errors = [];
        $validatedData = $payload;

        foreach ($this->rules as $field => $rule) {
            if (! isset($payload[$field])) {
                $errors[$field] = "Field {$field} is required";

                continue;
            }

            if ($rule === 'email' && ! filter_var($payload[$field], FILTER_VALIDATE_EMAIL)) {
                $errors[$field] = "Field {$field} must be a valid email";
            }

            if ($rule === 'numeric' && ! is_numeric($payload[$field])) {
                $errors[$field] = "Field {$field} must be numeric";
            }
        }

        if (! empty($errors)) {
            return $next([
                ...$payload,
                'validation_errors' => $errors,
                'is_valid' => false,
            ]);
        }

        return $next([
            ...$payload,
            'is_valid' => true,
            'validated_at' => time(),
        ]);
    }
}

/**
 * Example custom step for API calls
 */
final class ApiCallStep implements FlowStep
{
    public function __construct(
        private string $endpoint,
        private string $method = 'GET',
        private array $headers = []
    ) {}

    public function handle(mixed $payload, Closure $next): mixed
    {
        // Simulate API call
        $response = $this->makeApiCall($payload);

        return $next([
            ...$payload,
            'api_response' => $response,
            'api_call_made' => true,
            'endpoint' => $this->endpoint,
            'method' => $this->method,
        ]);
    }

    private function makeApiCall(array $payload): array
    {
        // Simulation - in real app, make actual HTTP request
        return [
            'status' => 'success',
            'data' => $payload,
            'timestamp' => microtime(true),
        ];
    }
}

/**
 * Example custom step for database operations
 */
final class DatabaseStep implements FlowStep
{
    public function __construct(
        private string $operation,
        private string $table,
        private array $conditions = []
    ) {}

    public function handle(mixed $payload, Closure $next): mixed
    {
        $result = $this->performDatabaseOperation($payload);

        return $next([
            ...$payload,
            'database_result' => $result,
            'database_operation' => $this->operation,
            'table' => $this->table,
        ]);
    }

    private function performDatabaseOperation(array $payload): array
    {
        // Simulation - in real app, perform actual database operation
        return [
            'affected_rows' => 1,
            'last_insert_id' => 123,
            'operation' => $this->operation,
            'table' => $this->table,
        ];
    }
}

/**
 * Example custom step for notifications
 */
final class NotificationStep implements FlowStep
{
    public function __construct(
        private string $type,
        private array $recipients = [],
        private string $template = 'default'
    ) {}

    public function handle(mixed $payload, Closure $next): mixed
    {
        $sent = $this->sendNotification($payload);

        return $next([
            ...$payload,
            'notification_sent' => $sent,
            'notification_type' => $this->type,
            'recipients' => $this->recipients,
            'template' => $this->template,
        ]);
    }

    private function sendNotification(array $payload): bool
    {
        // Simulation - in real app, send actual notification
        return true;
    }
}

/**
 * Example custom step for file operations
 */
final class FileOperationStep implements FlowStep
{
    public function __construct(
        private string $operation,
        private string $path,
        private array $options = []
    ) {}

    public function handle(mixed $payload, Closure $next): mixed
    {
        $result = $this->performFileOperation($payload);

        return $next([
            ...$payload,
            'file_operation_result' => $result,
            'file_operation' => $this->operation,
            'file_path' => $this->path,
        ]);
    }

    private function performFileOperation(array $payload): array
    {
        // Simulation - in real app, perform actual file operation
        return [
            'success' => true,
            'operation' => $this->operation,
            'path' => $this->path,
            'size' => 1024,
        ];
    }
}

/**
 * Example custom step for caching
 */
final class CacheStep implements FlowStep
{
    public function __construct(
        private string $key,
        private int $ttl = 3600,
        private bool $useCache = true
    ) {}

    public function handle(mixed $payload, Closure $next): mixed
    {
        if ($this->useCache) {
            $cached = $this->getFromCache($this->key);
            if ($cached !== null) {
                return $next([
                    ...$payload,
                    'cached_result' => $cached,
                    'cache_hit' => true,
                ]);
            }
        }

        $result = $next($payload);

        if ($this->useCache) {
            $this->storeInCache($this->key, $result, $this->ttl);
        }

        return $result;
    }

    private function getFromCache(string $key): ?array
    {
        // Simulation - in real app, get from actual cache
        return null;
    }

    private function storeInCache(string $key, array $data, int $ttl): void
    {
        // Simulation - in real app, store in actual cache
    }
}

/**
 * Example custom step for logging
 */
final class LoggingStep implements FlowStep
{
    public function __construct(
        private string $level = 'info',
        private string $message = 'Step executed',
        private array $context = []
    ) {}

    public function handle(mixed $payload, Closure $next): mixed
    {
        $this->log($payload);

        return $next([
            ...$payload,
            'logged' => true,
            'log_level' => $this->level,
            'log_message' => $this->message,
        ]);
    }

    private function log(array $payload): void
    {
        // Simulation - in real app, use actual logging
        error_log("[{$this->level}] {$this->message} - ".json_encode($payload));
    }
}

/**
 * Example custom step for error handling
 */
final class ErrorHandlingStep implements FlowStep
{
    public function __construct(
        private bool $throwOnError = false,
        private array $errorHandlers = []
    ) {}

    public function handle(mixed $payload, Closure $next): mixed
    {
        if (isset($payload['error']) && $this->throwOnError) {
            throw new Exception($payload['error']);
        }

        if (isset($payload['error']) && ! empty($this->errorHandlers)) {
            foreach ($this->errorHandlers as $handler) {
                $handler($payload['error'], $payload);
            }
        }

        return $next([
            ...$payload,
            'error_handled' => isset($payload['error']),
        ]);
    }
}
