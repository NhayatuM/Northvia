<?php

declare(strict_types=1);

namespace Northvia\Core\Http;

use Psr\Http\Message\ResponseInterface;

/**
 * HTTP Response implementation
 */
class Response implements ResponseInterface
{
    private int $statusCode;
    private array $headers;
    private string $body;
    private string $protocolVersion = '1.1';

    private const STATUS_CODES = [
        200 => 'OK',
        201 => 'Created',
        202 => 'Accepted',
        204 => 'No Content',
        301 => 'Moved Permanently',
        302 => 'Found',
        304 => 'Not Modified',
        400 => 'Bad Request',
        401 => 'Unauthorized',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        409 => 'Conflict',
        422 => 'Unprocessable Entity',
        429 => 'Too Many Requests',
        500 => 'Internal Server Error',
        502 => 'Bad Gateway',
        503 => 'Service Unavailable',
        504 => 'Gateway Timeout'
    ];

    public function __construct(int $statusCode = 200, array $headers = [], string $body = '')
    {
        $this->statusCode = $statusCode;
        $this->headers = $this->normalizeHeaders($headers);
        $this->body = $body;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function withStatus($code, $reasonPhrase = ''): self
    {
        $clone = clone $this;
        $clone->statusCode = $code;
        return $clone;
    }

    public function getReasonPhrase(): string
    {
        return self::STATUS_CODES[$this->statusCode] ?? 'Unknown';
    }

    public function getProtocolVersion(): string
    {
        return $this->protocolVersion;
    }

    public function withProtocolVersion($version): self
    {
        $clone = clone $this;
        $clone->protocolVersion = $version;
        return $clone;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function hasHeader($name): bool
    {
        return isset($this->headers[strtolower($name)]);
    }

    public function getHeader($name): array
    {
        $name = strtolower($name);
        return $this->headers[$name] ?? [];
    }

    public function getHeaderLine($name): string
    {
        return implode(', ', $this->getHeader($name));
    }

    public function withHeader($name, $value): self
    {
        $clone = clone $this;
        $clone->headers[strtolower($name)] = is_array($value) ? $value : [$value];
        return $clone;
    }

    public function withAddedHeader($name, $value): self
    {
        $clone = clone $this;
        $name = strtolower($name);
        $clone->headers[$name] = array_merge(
            $clone->headers[$name] ?? [],
            is_array($value) ? $value : [$value]
        );
        return $clone;
    }

    public function withoutHeader($name): self
    {
        $clone = clone $this;
        unset($clone->headers[strtolower($name)]);
        return $clone;
    }

    public function getBody()
    {
        return $this->body;
    }

    public function withBody($body): self
    {
        $clone = clone $this;
        $clone->body = (string) $body;
        return $clone;
    }

    /**
     * Create JSON response
     */
    public static function json($data, int $statusCode = 200, array $headers = []): self
    {
        $headers['Content-Type'] = 'application/json';
        $body = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        
        return new self($statusCode, $headers, $body);
    }

    /**
     * Create success response
     */
    public static function success($data = null, string $message = 'Success', int $statusCode = 200): self
    {
        $response = [
            'success' => true,
            'message' => $message,
        ];
        
        if ($data !== null) {
            $response['data'] = $data;
        }
        
        return self::json($response, $statusCode);
    }

    /**
     * Create error response
     */
    public static function error(string $message, int $statusCode = 400, $errors = null): self
    {
        $response = [
            'success' => false,
            'message' => $message,
        ];
        
        if ($errors !== null) {
            $response['errors'] = $errors;
        }
        
        return self::json($response, $statusCode);
    }

    /**
     * Create created response
     */
    public static function created($data = null, string $message = 'Created successfully'): self
    {
        return self::success($data, $message, 201);
    }

    /**
     * Create no content response
     */
    public static function noContent(): self
    {
        return new self(204);
    }

    /**
     * Create not found response
     */
    public static function notFound(string $message = 'Resource not found'): self
    {
        return self::error($message, 404);
    }

    /**
     * Create unauthorized response
     */
    public static function unauthorized(string $message = 'Unauthorized'): self
    {
        return self::error($message, 401);
    }

    /**
     * Create forbidden response
     */
    public static function forbidden(string $message = 'Forbidden'): self
    {
        return self::error($message, 403);
    }

    /**
     * Create validation error response
     */
    public static function validationError(array $errors, string $message = 'Validation failed'): self
    {
        return self::error($message, 422, $errors);
    }

    /**
     * Create internal server error response
     */
    public static function serverError(string $message = 'Internal server error'): self
    {
        return self::error($message, 500);
    }

    /**
     * Create rate limit exceeded response
     */
    public static function tooManyRequests(string $message = 'Too many requests'): self
    {
        return self::error($message, 429);
    }

    /**
     * Add pagination metadata to response
     */
    public function withPagination(array $pagination): self
    {
        $body = json_decode($this->body, true);
        $body['pagination'] = $pagination;
        
        return $this->withBody(json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /**
     * Add metadata to response
     */
    public function withMeta(array $meta): self
    {
        $body = json_decode($this->body, true);
        $body['meta'] = $meta;
        
        return $this->withBody(json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /**
     * Normalize header names to lowercase
     */
    private function normalizeHeaders(array $headers): array
    {
        $normalized = [];
        
        foreach ($headers as $name => $value) {
            $normalized[strtolower($name)] = is_array($value) ? $value : [$value];
        }
        
        return $normalized;
    }
}