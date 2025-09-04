<?php

declare(strict_types=1);

namespace Northvia\Modules\Auth\Controllers;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Northvia\Core\Auth\AuthManager;
use Northvia\Core\Http\Response;
use Northvia\Core\Validation\Validator;
use Northvia\Core\Security\RateLimiter;

/**
 * Authentication controller
 */
class AuthController
{
    private AuthManager $auth;
    private Validator $validator;
    private RateLimiter $rateLimiter;

    public function __construct(AuthManager $auth, Validator $validator, RateLimiter $rateLimiter)
    {
        $this->auth = $auth;
        $this->validator = $validator;
        $this->rateLimiter = $rateLimiter;
    }

    /**
     * User login
     */
    public function login(ServerRequestInterface $request): ResponseInterface
    {
        // Rate limiting
        $clientId = $request->getAttribute('client_ip', '127.0.0.1');
        if (!$this->rateLimiter->attempt('auth:login:' . $clientId, 5, 300)) {
            return Response::tooManyRequests('Too many login attempts. Please try again later.');
        }

        // Validate request
        $data = $request->input();
        $validation = $this->validator->validate($data, [
            'identifier' => 'required|string|max:255',
            'password' => 'required|string',
            'remember' => 'boolean'
        ]);

        if (!$validation->isValid()) {
            return Response::validationError($validation->getErrors());
        }

        // Attempt authentication
        $result = $this->auth->attempt(
            $data['identifier'],
            $data['password'],
            $data['remember'] ?? false
        );

        if (!$result || !$result->isSuccess()) {
            return Response::unauthorized($result?->getMessage() ?? 'Invalid credentials');
        }

        // Return success response
        return Response::success($result->toArray(), 'Login successful');
    }

    /**
     * User registration
     */
    public function register(ServerRequestInterface $request): ResponseInterface
    {
        // Rate limiting
        $clientId = $request->getAttribute('client_ip', '127.0.0.1');
        if (!$this->rateLimiter->attempt('auth:register:' . $clientId, 3, 300)) {
            return Response::tooManyRequests('Too many registration attempts. Please try again later.');
        }

        // Validate request
        $data = $request->input();
        $validation = $this->validator->validate($data, [
            'email' => 'required|email|max:255',
            'phone' => 'nullable|string|max:20',
            'password' => 'required|string|min:8',
            'password_confirmation' => 'required|string|same:password',
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'middle_name' => 'nullable|string|max:100',
            'date_of_birth' => 'nullable|date',
            'gender' => 'nullable|in:male,female,other',
            'referral_code' => 'nullable|string|max:20',
            'newsletter_subscribed' => 'boolean',
            'terms_accepted' => 'required|boolean|accepted'
        ]);

        if (!$validation->isValid()) {
            return Response::validationError($validation->getErrors());
        }

        // Remove confirmation field
        unset($data['password_confirmation'], $data['terms_accepted']);

        // Attempt registration
        $result = $this->auth->register($data);

        if (!$result->isSuccess()) {
            return Response::error($result->getMessage(), 400);
        }

        // TODO: Send verification email

        return Response::created($result->toArray(), 'Registration successful. Please check your email to verify your account.');
    }

    /**
     * Refresh access token
     */
    public function refresh(ServerRequestInterface $request): ResponseInterface
    {
        $data = $request->input();
        $validation = $this->validator->validate($data, [
            'refresh_token' => 'required|string'
        ]);

        if (!$validation->isValid()) {
            return Response::validationError($validation->getErrors());
        }

        $result = $this->auth->refreshToken($data['refresh_token']);

        if (!$result) {
            return Response::unauthorized('Invalid refresh token');
        }

        return Response::success($result->toArray(), 'Token refreshed successfully');
    }

    /**
     * User logout
     */
    public function logout(ServerRequestInterface $request): ResponseInterface
    {
        $user = $request->getAttribute('user');
        
        $this->auth->logout($user);

        return Response::success(null, 'Logged out successfully');
    }

    /**
     * Get authenticated user profile
     */
    public function me(ServerRequestInterface $request): ResponseInterface
    {
        $user = $request->getAttribute('user');

        if (!$user) {
            return Response::unauthorized('Not authenticated');
        }

        return Response::success($user->toArray(), 'User profile retrieved successfully');
    }

    /**
     * Verify email address
     */
    public function verifyEmail(ServerRequestInterface $request): ResponseInterface
    {
        $token = $request->getAttribute('token');

        if (!$token) {
            return Response::error('Verification token is required', 400);
        }

        $success = $this->auth->verifyEmail($token);

        if (!$success) {
            return Response::error('Invalid or expired verification token', 400);
        }

        return Response::success(null, 'Email verified successfully');
    }

    /**
     * Send password reset link
     */
    public function forgotPassword(ServerRequestInterface $request): ResponseInterface
    {
        // Rate limiting
        $clientId = $request->getAttribute('client_ip', '127.0.0.1');
        if (!$this->rateLimiter->attempt('auth:forgot:' . $clientId, 3, 300)) {
            return Response::tooManyRequests('Too many password reset attempts. Please try again later.');
        }

        $data = $request->input();
        $validation = $this->validator->validate($data, [
            'email' => 'required|email|max:255'
        ]);

        if (!$validation->isValid()) {
            return Response::validationError($validation->getErrors());
        }

        $success = $this->auth->sendPasswordResetLink($data['email']);

        // Always return success to prevent email enumeration
        return Response::success(null, 'If the email exists, a password reset link has been sent.');
    }

    /**
     * Reset password with token
     */
    public function resetPassword(ServerRequestInterface $request): ResponseInterface
    {
        $data = $request->input();
        $validation = $this->validator->validate($data, [
            'token' => 'required|string',
            'password' => 'required|string|min:8',
            'password_confirmation' => 'required|string|same:password'
        ]);

        if (!$validation->isValid()) {
            return Response::validationError($validation->getErrors());
        }

        $success = $this->auth->resetPassword($data['token'], $data['password']);

        if (!$success) {
            return Response::error('Invalid or expired reset token', 400);
        }

        return Response::success(null, 'Password reset successfully');
    }

    /**
     * Change password for authenticated user
     */
    public function changePassword(ServerRequestInterface $request): ResponseInterface
    {
        $user = $request->getAttribute('user');
        $data = $request->input();

        $validation = $this->validator->validate($data, [
            'current_password' => 'required|string',
            'password' => 'required|string|min:8',
            'password_confirmation' => 'required|string|same:password'
        ]);

        if (!$validation->isValid()) {
            return Response::validationError($validation->getErrors());
        }

        // Verify current password
        $result = $this->auth->attempt($user->email, $data['current_password']);
        if (!$result || !$result->isSuccess()) {
            return Response::error('Current password is incorrect', 400);
        }

        // TODO: Implement password change logic
        
        return Response::success(null, 'Password changed successfully');
    }

    /**
     * Enable two-factor authentication
     */
    public function enableTwoFactor(ServerRequestInterface $request): ResponseInterface
    {
        $user = $request->getAttribute('user');
        
        $secret = $this->auth->enableTwoFactor($user);
        
        // Generate QR code URL
        $qrCodeUrl = $this->generateTwoFactorQrCode($user, $secret);
        
        return Response::success([
            'secret' => $secret,
            'qr_code_url' => $qrCodeUrl,
            'backup_codes' => $this->generateBackupCodes()
        ], 'Two-factor authentication enabled successfully');
    }

    /**
     * Verify two-factor code
     */
    public function verifyTwoFactor(ServerRequestInterface $request): ResponseInterface
    {
        $user = $request->getAttribute('user');
        $data = $request->input();

        $validation = $this->validator->validate($data, [
            'code' => 'required|string|size:6'
        ]);

        if (!$validation->isValid()) {
            return Response::validationError($validation->getErrors());
        }

        $valid = $this->auth->verifyTwoFactorCode($user, $data['code']);

        if (!$valid) {
            return Response::error('Invalid two-factor code', 400);
        }

        return Response::success(null, 'Two-factor code verified successfully');
    }

    /**
     * Generate backup codes for two-factor authentication
     */
    private function generateBackupCodes(): array
    {
        $codes = [];
        for ($i = 0; $i < 10; $i++) {
            $codes[] = strtoupper(substr(bin2hex(random_bytes(5)), 0, 8));
        }
        return $codes;
    }

    /**
     * Generate QR code URL for two-factor setup
     */
    private function generateTwoFactorQrCode($user, string $secret): string
    {
        $appName = $_ENV['APP_NAME'] ?? 'NorthVia';
        $issuer = $_ENV['APP_URL'] ?? 'northvia.com';
        
        $otpauthUrl = sprintf(
            'otpauth://totp/%s:%s?secret=%s&issuer=%s',
            rawurlencode($appName),
            rawurlencode($user->email),
            $secret,
            rawurlencode($issuer)
        );
        
        // Generate QR code using Google Charts API or similar service
        return 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' . urlencode($otpauthUrl);
    }
}