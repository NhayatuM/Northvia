<?php

declare(strict_types=1);

namespace Northvia\Core\Http;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;

/**
 * HTTP Request implementation
 */
class Request implements ServerRequestInterface
{
    private string $method;
    private UriInterface $uri;
    private array $headers;
    private string $body;
    private array $serverParams;
    private array $cookieParams;
    private array $queryParams;
    private $parsedBody;
    private array $uploadedFiles;
    private array $attributes = [];
    private string $protocolVersion = '1.1';

    public function __construct(
        string $method,
        string $uri,
        array $headers = [],
        string $body = '',
        array $queryParams = [],
        $parsedBody = null,
        array $cookieParams = [],
        array $uploadedFiles = [],
        array $serverParams = []
    ) {
        $this->method = strtoupper($method);
        $this->uri = new Uri($uri);
        $this->headers = $this->normalizeHeaders($headers);
        $this->body = $body;
        $this->queryParams = $queryParams;
        $this->parsedBody = $parsedBody;
        $this->cookieParams = $cookieParams;
        $this->uploadedFiles = $uploadedFiles;
        $this->serverParams = $serverParams;
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function withMethod($method): self
    {
        $clone = clone $this;
        $clone->method = strtoupper($method);
        return $clone;
    }

    public function getUri(): UriInterface
    {
        return $this->uri;
    }

    public function withUri(UriInterface $uri, $preserveHost = false): self
    {
        $clone = clone $this;
        $clone->uri = $uri;
        
        if (!$preserveHost) {
            $host = $uri->getHost();
            if ($host !== '') {
                $port = $uri->getPort();
                if ($port !== null) {
                    $host .= ':' . $port;
                }
                $clone->headers['Host'] = [$host];
            }
        }
        
        return $clone;
    }

    public function getRequestTarget(): string
    {
        $target = $this->uri->getPath();
        $query = $this->uri->getQuery();
        
        if ($query !== '') {
            $target .= '?' . $query;
        }
        
        return $target;
    }

    public function withRequestTarget($requestTarget): self
    {
        $clone = clone $this;
        // Implementation would parse the request target and update URI accordingly
        return $clone;
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

    public function getServerParams(): array
    {
        return $this->serverParams;
    }

    public function getCookieParams(): array
    {
        return $this->cookieParams;
    }

    public function withCookieParams(array $cookies): self
    {
        $clone = clone $this;
        $clone->cookieParams = $cookies;
        return $clone;
    }

    public function getQueryParams(): array
    {
        return $this->queryParams;
    }

    public function withQueryParams(array $query): self
    {
        $clone = clone $this;
        $clone->queryParams = $query;
        return $clone;
    }

    public function getUploadedFiles(): array
    {
        return $this->uploadedFiles;
    }

    public function withUploadedFiles(array $uploadedFiles): self
    {
        $clone = clone $this;
        $clone->uploadedFiles = $uploadedFiles;
        return $clone;
    }

    public function getParsedBody()
    {
        return $this->parsedBody;
    }

    public function withParsedBody($data): self
    {
        $clone = clone $this;
        $clone->parsedBody = $data;
        return $clone;
    }

    public function getAttributes(): array
    {
        return $this->attributes;
    }

    public function getAttribute($name, $default = null)
    {
        return $this->attributes[$name] ?? $default;
    }

    public function withAttribute($name, $value): self
    {
        $clone = clone $this;
        $clone->attributes[$name] = $value;
        return $clone;
    }

    public function withoutAttribute($name): self
    {
        $clone = clone $this;
        unset($clone->attributes[$name]);
        return $clone;
    }

    /**
     * Get request input (JSON body or form data)
     */
    public function input(string $key = null, $default = null)
    {
        $data = $this->parsedBody ?? [];
        
        if ($key === null) {
            return $data;
        }
        
        return $data[$key] ?? $default;
    }

    /**
     * Get query parameter
     */
    public function query(string $key = null, $default = null)
    {
        if ($key === null) {
            return $this->queryParams;
        }
        
        return $this->queryParams[$key] ?? $default;
    }

    /**
     * Check if request expects JSON response
     */
    public function expectsJson(): bool
    {
        $accept = $this->getHeaderLine('Accept');
        return strpos($accept, 'application/json') !== false;
    }

    /**
     * Check if request is JSON
     */
    public function isJson(): bool
    {
        $contentType = $this->getHeaderLine('Content-Type');
        return strpos($contentType, 'application/json') !== false;
    }

    /**
     * Get bearer token from Authorization header
     */
    public function bearerToken(): ?string
    {
        $authorization = $this->getHeaderLine('Authorization');
        
        if (preg_match('/Bearer\s+(.*)$/i', $authorization, $matches)) {
            return $matches[1];
        }
        
        return null;
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