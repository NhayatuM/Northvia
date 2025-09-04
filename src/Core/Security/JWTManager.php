<?php

declare(strict_types=1);

namespace Northvia\Core\Security;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Northvia\Modules\Users\Models\User;
use Carbon\Carbon;

/**
 * JWT token manager for authentication
 */
class JWTManager
{
    private string $secret;
    private string $algorithm;
    private int $accessTokenExpiration;
    private int $refreshTokenExpiration;

    public function __construct(
        string $secret = null,
        string $algorithm = 'HS256',
        int $accessTokenExpiration = 86400,
        int $refreshTokenExpiration = 2592000
    ) {
        $this->secret = $secret ?? $_ENV['JWT_SECRET'] ?? 'default-secret-key';
        $this->algorithm = $algorithm;
        $this->accessTokenExpiration = $accessTokenExpiration;
        $this->refreshTokenExpiration = $refreshTokenExpiration;
    }

    /**
     * Generate access token for user
     */
    public function generateAccessToken(User $user): string
    {
        $now = Carbon::now();
        $payload = [
            'iss' => $_ENV['APP_URL'] ?? 'northvia.com',
            'aud' => $_ENV['APP_URL'] ?? 'northvia.com',
            'iat' => $now->timestamp,
            'exp' => $now->addSeconds($this->accessTokenExpiration)->timestamp,
            'user_id' => $user->id,
            'email' => $user->email,
            'type' => 'access',
            'user_role' => $this->getUserRole($user),
            'permissions' => $this->getUserPermissions($user)
        ];

        return JWT::encode($payload, $this->secret, $this->algorithm);
    }

    /**
     * Generate refresh token for user
     */
    public function generateRefreshToken(User $user): string
    {
        $now = Carbon::now();
        $payload = [
            'iss' => $_ENV['APP_URL'] ?? 'northvia.com',
            'aud' => $_ENV['APP_URL'] ?? 'northvia.com',
            'iat' => $now->timestamp,
            'exp' => $now->addSeconds($this->refreshTokenExpiration)->timestamp,
            'user_id' => $user->id,
            'type' => 'refresh'
        ];

        return JWT::encode($payload, $this->secret, $this->algorithm);
    }

    /**
     * Decode and validate JWT token
     */
    public function decode(string $token): object
    {
        return JWT::decode($token, new Key($this->secret, $this->algorithm));
    }

    /**
     * Validate token and return payload
     */
    public function validate(string $token): ?object
    {
        try {
            return $this->decode($token);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Generate API key for external integrations
     */
    public function generateApiKey(User $user, array $scopes = []): string
    {
        $payload = [
            'iss' => $_ENV['APP_URL'] ?? 'northvia.com',
            'aud' => $_ENV['APP_URL'] ?? 'northvia.com',
            'iat' => Carbon::now()->timestamp,
            'exp' => Carbon::now()->addYear()->timestamp,
            'user_id' => $user->id,
            'type' => 'api_key',
            'scopes' => $scopes
        ];

        return JWT::encode($payload, $this->secret, $this->algorithm);
    }

    /**
     * Generate temporary token for password reset, email verification, etc.
     */
    public function generateTemporaryToken(User $user, string $purpose, int $expirationMinutes = 60): string
    {
        $payload = [
            'iss' => $_ENV['APP_URL'] ?? 'northvia.com',
            'aud' => $_ENV['APP_URL'] ?? 'northvia.com',
            'iat' => Carbon::now()->timestamp,
            'exp' => Carbon::now()->addMinutes($expirationMinutes)->timestamp,
            'user_id' => $user->id,
            'type' => 'temporary',
            'purpose' => $purpose
        ];

        return JWT::encode($payload, $this->secret, $this->algorithm);
    }

    /**
     * Extract user ID from token without full validation
     */
    public function extractUserId(string $token): ?int
    {
        try {
            $parts = explode('.', $token);
            if (count($parts) !== 3) {
                return null;
            }

            $payload = json_decode(base64_decode($parts[1]), true);
            return $payload['user_id'] ?? null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Check if token is expired
     */
    public function isExpired(string $token): bool
    {
        try {
            $payload = $this->decode($token);
            return $payload->exp < Carbon::now()->timestamp;
        } catch (\Exception $e) {
            return true;
        }
    }

    /**
     * Get user role for JWT payload
     */
    private function getUserRole(User $user): string
    {
        // Check if user is an admin
        // This would be determined by checking admin_users table or user roles
        return 'customer'; // Default role
    }

    /**
     * Get user permissions for JWT payload
     */
    private function getUserPermissions(User $user): array
    {
        $permissions = ['read_profile', 'update_profile'];
        
        // Add vendor permissions if user is a vendor
        // Add admin permissions if user is an admin
        
        return $permissions;
    }
}