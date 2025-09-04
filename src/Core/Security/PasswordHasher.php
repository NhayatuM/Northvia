<?php

declare(strict_types=1);

namespace Northvia\Core\Security;

/**
 * Password hashing and verification utility
 */
class PasswordHasher
{
    private string $algorithm;
    private array $options;

    public function __construct(string $algorithm = 'bcrypt', array $options = [])
    {
        $this->algorithm = $algorithm;
        $this->options = array_merge([
            'cost' => 12, // For bcrypt
            'memory_cost' => 65536, // For argon2
            'time_cost' => 4, // For argon2
            'threads' => 3, // For argon2
        ], $options);
    }

    /**
     * Hash a password
     */
    public function hash(string $password): string
    {
        switch ($this->algorithm) {
            case 'bcrypt':
                return password_hash($password, PASSWORD_BCRYPT, [
                    'cost' => $this->options['cost']
                ]);

            case 'argon2i':
                return password_hash($password, PASSWORD_ARGON2I, [
                    'memory_cost' => $this->options['memory_cost'],
                    'time_cost' => $this->options['time_cost'],
                    'threads' => $this->options['threads']
                ]);

            case 'argon2id':
                return password_hash($password, PASSWORD_ARGON2ID, [
                    'memory_cost' => $this->options['memory_cost'],
                    'time_cost' => $this->options['time_cost'],
                    'threads' => $this->options['threads']
                ]);

            default:
                return password_hash($password, PASSWORD_DEFAULT);
        }
    }

    /**
     * Verify a password against its hash
     */
    public function verify(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    /**
     * Check if password needs rehashing
     */
    public function needsRehash(string $hash): bool
    {
        switch ($this->algorithm) {
            case 'bcrypt':
                return password_needs_rehash($hash, PASSWORD_BCRYPT, [
                    'cost' => $this->options['cost']
                ]);

            case 'argon2i':
                return password_needs_rehash($hash, PASSWORD_ARGON2I, [
                    'memory_cost' => $this->options['memory_cost'],
                    'time_cost' => $this->options['time_cost'],
                    'threads' => $this->options['threads']
                ]);

            case 'argon2id':
                return password_needs_rehash($hash, PASSWORD_ARGON2ID, [
                    'memory_cost' => $this->options['memory_cost'],
                    'time_cost' => $this->options['time_cost'],
                    'threads' => $this->options['threads']
                ]);

            default:
                return password_needs_rehash($hash, PASSWORD_DEFAULT);
        }
    }

    /**
     * Generate secure random password
     */
    public function generateRandomPassword(int $length = 12): string
    {
        $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()';
        $password = '';
        $charactersLength = strlen($characters);

        for ($i = 0; $i < $length; $i++) {
            $password .= $characters[random_int(0, $charactersLength - 1)];
        }

        return $password;
    }

    /**
     * Validate password strength
     */
    public function validatePasswordStrength(string $password): array
    {
        $errors = [];
        $score = 0;

        // Minimum length check
        if (strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters long';
        } else {
            $score += 10;
        }

        // Contains lowercase letter
        if (!preg_match('/[a-z]/', $password)) {
            $errors[] = 'Password must contain at least one lowercase letter';
        } else {
            $score += 10;
        }

        // Contains uppercase letter
        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = 'Password must contain at least one uppercase letter';
        } else {
            $score += 10;
        }

        // Contains number
        if (!preg_match('/[0-9]/', $password)) {
            $errors[] = 'Password must contain at least one number';
        } else {
            $score += 10;
        }

        // Contains special character
        if (!preg_match('/[^a-zA-Z0-9]/', $password)) {
            $errors[] = 'Password must contain at least one special character';
        } else {
            $score += 15;
        }

        // Length bonus
        $length = strlen($password);
        if ($length >= 12) {
            $score += 15;
        } elseif ($length >= 10) {
            $score += 10;
        } elseif ($length >= 8) {
            $score += 5;
        }

        // Diversity bonus
        $uniqueChars = count(array_unique(str_split($password)));
        if ($uniqueChars >= 8) {
            $score += 10;
        } elseif ($uniqueChars >= 6) {
            $score += 5;
        }

        // Check for common patterns
        if ($this->hasCommonPatterns($password)) {
            $score -= 20;
            $errors[] = 'Password contains common patterns';
        }

        // Determine strength
        $strength = 'Very Weak';
        if ($score >= 80) {
            $strength = 'Very Strong';
        } elseif ($score >= 60) {
            $strength = 'Strong';
        } elseif ($score >= 40) {
            $strength = 'Medium';
        } elseif ($score >= 20) {
            $strength = 'Weak';
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'score' => $score,
            'strength' => $strength
        ];
    }

    /**
     * Check for common password patterns
     */
    private function hasCommonPatterns(string $password): bool
    {
        $commonPatterns = [
            '/^(.)\1+$/', // All same character
            '/^(012|123|234|345|456|567|678|789|890)+/', // Sequential numbers
            '/^(abc|bcd|cde|def|efg|fgh|ghi|hij|ijk|jkl|klm|lmn|mno|nop|opq|pqr|qrs|rst|stu|tuv|uvw|vwx|wxy|xyz)+/i', // Sequential letters
            '/^(qwerty|asdf|zxcv|password|admin|login)+/i', // Common keyboard patterns/words
        ];

        foreach ($commonPatterns as $pattern) {
            if (preg_match($pattern, strtolower($password))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Generate password hash info
     */
    public function getHashInfo(string $hash): array
    {
        return password_get_info($hash);
    }
}