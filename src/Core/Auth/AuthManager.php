<?php

declare(strict_types=1);

namespace Northvia\Core\Auth;

use Northvia\Core\Database\Database;
use Northvia\Core\Security\JWTManager;
use Northvia\Core\Security\PasswordHasher;
use Northvia\Modules\Users\Models\User;
use Carbon\Carbon;

/**
 * Authentication manager for handling user authentication
 */
class AuthManager
{
    private Database $db;
    private JWTManager $jwt;
    private PasswordHasher $hasher;
    private ?User $currentUser = null;

    public function __construct(Database $db, JWTManager $jwt, PasswordHasher $hasher)
    {
        $this->db = $db;
        $this->jwt = $jwt;
        $this->hasher = $hasher;
    }

    /**
     * Attempt to authenticate user with email/phone and password
     */
    public function attempt(string $identifier, string $password, bool $remember = false): ?AuthResult
    {
        // Find user by email or phone
        $user = $this->findUserByIdentifier($identifier);
        
        if (!$user) {
            $this->logFailedLoginAttempt($identifier, 'user_not_found');
            return null;
        }

        // Check if account is locked
        if ($this->isAccountLocked($user)) {
            $this->logFailedLoginAttempt($identifier, 'account_locked', $user->id);
            return AuthResult::failed('Account is locked due to too many failed attempts');
        }

        // Verify password
        if (!$this->hasher->verify($password, $user->password_hash)) {
            $this->handleFailedLogin($user);
            $this->logFailedLoginAttempt($identifier, 'invalid_password', $user->id);
            return null;
        }

        // Check account status
        if ($user->status !== 'active') {
            $this->logFailedLoginAttempt($identifier, 'inactive_account', $user->id);
            return AuthResult::failed("Account is {$user->status}");
        }

        // Successful login
        $this->handleSuccessfulLogin($user);
        
        // Generate tokens
        $accessToken = $this->jwt->generateAccessToken($user);
        $refreshToken = $remember ? $this->jwt->generateRefreshToken($user) : null;

        return AuthResult::success($user, $accessToken, $refreshToken);
    }

    /**
     * Register a new user
     */
    public function register(array $userData): AuthResult
    {
        try {
            // Validate unique email and phone
            if ($this->emailExists($userData['email'])) {
                return AuthResult::failed('Email already exists');
            }

            if (isset($userData['phone']) && $this->phoneExists($userData['phone'])) {
                return AuthResult::failed('Phone number already exists');
            }

            // Hash password
            $userData['password_hash'] = $this->hasher->hash($userData['password']);
            unset($userData['password']);

            // Generate referral code
            $userData['referral_code'] = $this->generateReferralCode();
            
            // Set default status
            $userData['status'] = 'pending_verification';
            
            // Insert user
            $userId = $this->db->table('users')->insertGetId($userData);
            
            $user = $this->findUserById($userId);
            
            // Generate verification token
            $verificationToken = $this->generateEmailVerificationToken($user);

            return AuthResult::success($user, null, null, [
                'verification_token' => $verificationToken
            ]);

        } catch (\Exception $e) {
            return AuthResult::failed('Registration failed: ' . $e->getMessage());
        }
    }

    /**
     * Verify JWT token and get user
     */
    public function verifyToken(string $token): ?User
    {
        try {
            $payload = $this->jwt->decode($token);
            
            if (!isset($payload->user_id)) {
                return null;
            }

            $user = $this->findUserById($payload->user_id);
            
            if (!$user || $user->status !== 'active') {
                return null;
            }

            $this->currentUser = $user;
            return $user;

        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Refresh access token using refresh token
     */
    public function refreshToken(string $refreshToken): ?AuthResult
    {
        try {
            $payload = $this->jwt->decode($refreshToken);
            
            if (!isset($payload->user_id) || $payload->type !== 'refresh') {
                return null;
            }

            $user = $this->findUserById($payload->user_id);
            
            if (!$user || $user->status !== 'active') {
                return null;
            }

            $accessToken = $this->jwt->generateAccessToken($user);
            $newRefreshToken = $this->jwt->generateRefreshToken($user);

            return AuthResult::success($user, $accessToken, $newRefreshToken);

        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Logout user (invalidate tokens)
     */
    public function logout(?User $user = null): bool
    {
        if ($user) {
            // Update last login time
            $this->db->table('users')
                ->where('id', $user->id)
                ->update(['last_login_at' => Carbon::now()]);
        }

        $this->currentUser = null;
        return true;
    }

    /**
     * Get current authenticated user
     */
    public function user(): ?User
    {
        return $this->currentUser;
    }

    /**
     * Check if user is authenticated
     */
    public function check(): bool
    {
        return $this->currentUser !== null;
    }

    /**
     * Verify email with token
     */
    public function verifyEmail(string $token): bool
    {
        $verification = $this->db->table('email_verifications')
            ->where('token', $token)
            ->where('expires_at', '>', Carbon::now())
            ->where('verified_at', null)
            ->first();

        if (!$verification) {
            return false;
        }

        // Mark email as verified
        $this->db->table('email_verifications')
            ->where('id', $verification->id)
            ->update(['verified_at' => Carbon::now()]);

        // Update user status
        $this->db->table('users')
            ->where('id', $verification->user_id)
            ->update([
                'email_verified_at' => Carbon::now(),
                'status' => 'active'
            ]);

        return true;
    }

    /**
     * Send password reset email
     */
    public function sendPasswordResetLink(string $email): bool
    {
        $user = $this->findUserByIdentifier($email);
        
        if (!$user) {
            return false;
        }

        // Generate reset token
        $token = bin2hex(random_bytes(32));
        
        $this->db->table('password_resets')->insert([
            'user_id' => $user->id,
            'email' => $email,
            'token' => $token,
            'expires_at' => Carbon::now()->addHours(1),
            'created_at' => Carbon::now()
        ]);

        // TODO: Send email with reset link
        
        return true;
    }

    /**
     * Reset password with token
     */
    public function resetPassword(string $token, string $password): bool
    {
        $reset = $this->db->table('password_resets')
            ->where('token', $token)
            ->where('expires_at', '>', Carbon::now())
            ->where('used_at', null)
            ->first();

        if (!$reset) {
            return false;
        }

        // Update password
        $hashedPassword = $this->hasher->hash($password);
        
        $this->db->table('users')
            ->where('id', $reset->user_id)
            ->update([
                'password_hash' => $hashedPassword,
                'failed_login_attempts' => 0,
                'locked_until' => null
            ]);

        // Mark reset token as used
        $this->db->table('password_resets')
            ->where('id', $reset->id)
            ->update(['used_at' => Carbon::now()]);

        return true;
    }

    /**
     * Enable two-factor authentication
     */
    public function enableTwoFactor(User $user): string
    {
        $secret = $this->generateTwoFactorSecret();
        
        $this->db->table('users')
            ->where('id', $user->id)
            ->update([
                'two_factor_secret' => $secret,
                'two_factor_enabled' => true
            ]);

        return $secret;
    }

    /**
     * Verify two-factor code
     */
    public function verifyTwoFactorCode(User $user, string $code): bool
    {
        if (!$user->two_factor_enabled || !$user->two_factor_secret) {
            return false;
        }

        // TODO: Implement TOTP verification
        return true;
    }

    // Private helper methods

    private function findUserByIdentifier(string $identifier): ?User
    {
        $query = $this->db->table('users');
        
        if (filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
            $query->where('email', $identifier);
        } else {
            $query->where('phone', $identifier);
        }
        
        $userData = $query->first();
        
        return $userData ? User::fromArray((array) $userData) : null;
    }

    private function findUserById(int $id): ?User
    {
        $userData = $this->db->table('users')->where('id', $id)->first();
        return $userData ? User::fromArray((array) $userData) : null;
    }

    private function emailExists(string $email): bool
    {
        return $this->db->table('users')->where('email', $email)->exists();
    }

    private function phoneExists(string $phone): bool
    {
        return $this->db->table('users')->where('phone', $phone)->exists();
    }

    private function isAccountLocked(User $user): bool
    {
        if (!$user->locked_until) {
            return false;
        }

        return Carbon::parse($user->locked_until)->isFuture();
    }

    private function handleFailedLogin(User $user): void
    {
        $attempts = $user->failed_login_attempts + 1;
        $lockedUntil = null;

        // Lock account after 5 failed attempts for 30 minutes
        if ($attempts >= 5) {
            $lockedUntil = Carbon::now()->addMinutes(30);
        }

        $this->db->table('users')
            ->where('id', $user->id)
            ->update([
                'failed_login_attempts' => $attempts,
                'locked_until' => $lockedUntil
            ]);
    }

    private function handleSuccessfulLogin(User $user): void
    {
        $this->db->table('users')
            ->where('id', $user->id)
            ->update([
                'failed_login_attempts' => 0,
                'locked_until' => null,
                'last_login_at' => Carbon::now(),
                'login_ip' => $_SERVER['REMOTE_ADDR'] ?? null
            ]);
    }

    private function logFailedLoginAttempt(string $identifier, string $reason, ?int $userId = null): void
    {
        // Log to authentication logs table
        // TODO: Implement logging
    }

    private function generateReferralCode(): string
    {
        do {
            $code = strtoupper(substr(uniqid(), -8));
        } while ($this->db->table('users')->where('referral_code', $code)->exists());

        return $code;
    }

    private function generateEmailVerificationToken(User $user): string
    {
        $token = bin2hex(random_bytes(32));
        
        $this->db->table('email_verifications')->insert([
            'user_id' => $user->id,
            'email' => $user->email,
            'token' => $token,
            'expires_at' => Carbon::now()->addHours(24),
            'created_at' => Carbon::now()
        ]);

        return $token;
    }

    private function generateTwoFactorSecret(): string
    {
        return base64_encode(random_bytes(20));
    }
}