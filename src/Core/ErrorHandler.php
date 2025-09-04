<?php

declare(strict_types=1);

namespace Northvia\Core;

use Throwable;
use Northvia\Core\Http\Response;

/**
 * Global error handler
 */
class ErrorHandler
{
    private bool $debug;
    private string $logPath;

    public function __construct(bool $debug = null, string $logPath = null)
    {
        $this->debug = $debug ?? ($_ENV['APP_DEBUG'] === 'true');
        $this->logPath = $logPath ?? (ROOT_PATH . '/storage/logs/error.log');
    }

    /**
     * Handle uncaught exceptions
     */
    public function handleException(Throwable $exception): void
    {
        $this->logException($exception);

        // Send appropriate response
        $response = $this->createErrorResponse($exception);
        
        http_response_code($response->getStatusCode());
        
        foreach ($response->getHeaders() as $name => $values) {
            foreach ($values as $value) {
                header(sprintf('%s: %s', $name, $value), false);
            }
        }
        
        echo $response->getBody();
        exit;
    }

    /**
     * Handle PHP errors
     */
    public function handleError(int $severity, string $message, string $file = null, int $line = null): bool
    {
        if (!(error_reporting() & $severity)) {
            return false;
        }

        $this->logError($severity, $message, $file, $line);

        if ($this->debug) {
            echo json_encode([
                'error' => 'PHP Error',
                'message' => $message,
                'file' => $file,
                'line' => $line,
                'severity' => $this->getSeverityName($severity)
            ], JSON_PRETTY_PRINT);
            exit;
        }

        return true;
    }

    /**
     * Handle fatal errors
     */
    public function handleShutdown(): void
    {
        $error = error_get_last();
        
        if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
            $this->logError($error['type'], $error['message'], $error['file'], $error['line']);
            
            if (!headers_sent()) {
                http_response_code(500);
                header('Content-Type: application/json');
                
                if ($this->debug) {
                    echo json_encode([
                        'error' => 'Fatal Error',
                        'message' => $error['message'],
                        'file' => $error['file'],
                        'line' => $error['line']
                    ], JSON_PRETTY_PRINT);
                } else {
                    echo json_encode(['error' => 'Internal Server Error']);
                }
            }
        }
    }

    /**
     * Create error response from exception
     */
    private function createErrorResponse(Throwable $exception): Response
    {
        $statusCode = 500;
        $message = 'Internal Server Error';
        
        // Determine status code based on exception type
        if (method_exists($exception, 'getStatusCode')) {
            $statusCode = $exception->getStatusCode();
        } elseif ($exception instanceof \InvalidArgumentException) {
            $statusCode = 400;
            $message = 'Bad Request';
        }

        $responseData = [
            'success' => false,
            'error' => $message,
        ];

        if ($this->debug) {
            $responseData['debug'] = [
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTraceAsString()
            ];
        } else {
            $responseData['message'] = 'An error occurred while processing your request';
        }

        return Response::json($responseData, $statusCode);
    }

    /**
     * Log exception
     */
    private function logException(Throwable $exception): void
    {
        $message = sprintf(
            "[%s] %s: %s in %s:%d\nStack trace:\n%s\n\n",
            date('Y-m-d H:i:s'),
            get_class($exception),
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine(),
            $exception->getTraceAsString()
        );

        $this->writeLog($message);
    }

    /**
     * Log PHP error
     */
    private function logError(int $severity, string $message, string $file = null, int $line = null): void
    {
        $logMessage = sprintf(
            "[%s] %s: %s in %s:%d\n",
            date('Y-m-d H:i:s'),
            $this->getSeverityName($severity),
            $message,
            $file ?: 'unknown',
            $line ?: 0
        );

        $this->writeLog($logMessage);
    }

    /**
     * Write log message to file
     */
    private function writeLog(string $message): void
    {
        $logDir = dirname($this->logPath);
        
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        error_log($message, 3, $this->logPath);
    }

    /**
     * Get severity name from error constant
     */
    private function getSeverityName(int $severity): string
    {
        $severityNames = [
            E_ERROR => 'E_ERROR',
            E_WARNING => 'E_WARNING',
            E_PARSE => 'E_PARSE',
            E_NOTICE => 'E_NOTICE',
            E_CORE_ERROR => 'E_CORE_ERROR',
            E_CORE_WARNING => 'E_CORE_WARNING',
            E_COMPILE_ERROR => 'E_COMPILE_ERROR',
            E_COMPILE_WARNING => 'E_COMPILE_WARNING',
            E_USER_ERROR => 'E_USER_ERROR',
            E_USER_WARNING => 'E_USER_WARNING',
            E_USER_NOTICE => 'E_USER_NOTICE',
            E_STRICT => 'E_STRICT',
            E_RECOVERABLE_ERROR => 'E_RECOVERABLE_ERROR',
            E_DEPRECATED => 'E_DEPRECATED',
            E_USER_DEPRECATED => 'E_USER_DEPRECATED',
        ];

        return $severityNames[$severity] ?? 'UNKNOWN';
    }
}