<?php

declare(strict_types=1);

namespace Northvia\Core\Security;

/**
 * Simple file-based rate limiter
 */
class RateLimiter
{
    private string $storagePath;

    public function __construct(string $storagePath = null)
    {
        $this->storagePath = $storagePath ?? sys_get_temp_dir() . '/northvia_rate_limits/';
        
        if (!is_dir($this->storagePath)) {
            mkdir($this->storagePath, 0755, true);
        }
    }

    /**
     * Attempt to perform an action within rate limits
     */
    public function attempt(string $key, int $maxAttempts, int $decaySeconds): bool
    {
        $attempts = $this->getAttempts($key);
        $now = time();

        // Clean old attempts
        $attempts = array_filter($attempts, function($timestamp) use ($now, $decaySeconds) {
            return ($now - $timestamp) < $decaySeconds;
        });

        // Check if we're within limits
        if (count($attempts) >= $maxAttempts) {
            return false;
        }

        // Record this attempt
        $attempts[] = $now;
        $this->storeAttempts($key, $attempts);

        return true;
    }

    /**
     * Get the number of remaining attempts
     */
    public function remaining(string $key, int $maxAttempts, int $decaySeconds): int
    {
        $attempts = $this->getAttempts($key);
        $now = time();

        // Clean old attempts
        $attempts = array_filter($attempts, function($timestamp) use ($now, $decaySeconds) {
            return ($now - $timestamp) < $decaySeconds;
        });

        return max(0, $maxAttempts - count($attempts));
    }

    /**
     * Check if key is currently rate limited
     */
    public function tooManyAttempts(string $key, int $maxAttempts, int $decaySeconds): bool
    {
        return $this->remaining($key, $maxAttempts, $decaySeconds) === 0;
    }

    /**
     * Clear all attempts for a key
     */
    public function clear(string $key): void
    {
        $filePath = $this->getFilePath($key);
        
        if (file_exists($filePath)) {
            unlink($filePath);
        }
    }

    /**
     * Get seconds until key is no longer rate limited
     */
    public function availableIn(string $key, int $decaySeconds): int
    {
        $attempts = $this->getAttempts($key);
        
        if (empty($attempts)) {
            return 0;
        }

        $oldestAttempt = min($attempts);
        $availableAt = $oldestAttempt + $decaySeconds;
        
        return max(0, $availableAt - time());
    }

    /**
     * Get attempts for a key
     */
    private function getAttempts(string $key): array
    {
        $filePath = $this->getFilePath($key);
        
        if (!file_exists($filePath)) {
            return [];
        }

        $content = file_get_contents($filePath);
        return $content ? json_decode($content, true) : [];
    }

    /**
     * Store attempts for a key
     */
    private function storeAttempts(string $key, array $attempts): void
    {
        $filePath = $this->getFilePath($key);
        file_put_contents($filePath, json_encode($attempts));
    }

    /**
     * Get file path for a key
     */
    private function getFilePath(string $key): string
    {
        $hashedKey = md5($key);
        return $this->storagePath . $hashedKey . '.json';
    }

    /**
     * Clean up old rate limit files
     */
    public function cleanup(int $maxAge = 3600): void
    {
        $files = glob($this->storagePath . '*.json');
        $now = time();

        foreach ($files as $file) {
            if (($now - filemtime($file)) > $maxAge) {
                unlink($file);
            }
        }
    }
}