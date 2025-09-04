<?php

declare(strict_types=1);

namespace Northvia\Core\Auth;

use Northvia\Modules\Users\Models\User;

/**
 * Authentication result object
 */
class AuthResult
{
    private bool $success;
    private ?User $user;
    private ?string $accessToken;
    private ?string $refreshToken;
    private ?string $message;
    private array $metadata;

    public function __construct(
        bool $success,
        ?User $user = null,
        ?string $accessToken = null,
        ?string $refreshToken = null,
        ?string $message = null,
        array $metadata = []
    ) {
        $this->success = $success;
        $this->user = $user;
        $this->accessToken = $accessToken;
        $this->refreshToken = $refreshToken;
        $this->message = $message;
        $this->metadata = $metadata;
    }

    public static function success(
        User $user,
        ?string $accessToken = null,
        ?string $refreshToken = null,
        array $metadata = []
    ): self {
        return new self(true, $user, $accessToken, $refreshToken, null, $metadata);
    }

    public static function failed(string $message): self
    {
        return new self(false, null, null, null, $message);
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function getAccessToken(): ?string
    {
        return $this->accessToken;
    }

    public function getRefreshToken(): ?string
    {
        return $this->refreshToken;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function toArray(): array
    {
        $result = [
            'success' => $this->success,
        ];

        if ($this->success) {
            $result['user'] = $this->user ? $this->user->toArray() : null;
            $result['access_token'] = $this->accessToken;
            $result['refresh_token'] = $this->refreshToken;
            $result['metadata'] = $this->metadata;
        } else {
            $result['message'] = $this->message;
        }

        return $result;
    }
}