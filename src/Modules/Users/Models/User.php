<?php

declare(strict_types=1);

namespace Northvia\Modules\Users\Models;

use Carbon\Carbon;

/**
 * User model
 */
class User
{
    public int $id;
    public string $uuid;
    public string $email;
    public ?string $phone;
    public string $password_hash;
    public string $first_name;
    public string $last_name;
    public ?string $middle_name;
    public ?string $avatar;
    public ?string $date_of_birth;
    public ?string $gender;
    public ?Carbon $email_verified_at;
    public ?Carbon $phone_verified_at;
    public bool $two_factor_enabled;
    public ?string $two_factor_secret;
    public string $status;
    public int $failed_login_attempts;
    public ?Carbon $locked_until;
    public ?Carbon $last_login_at;
    public ?string $login_ip;
    public string $kyc_status;
    public ?array $kyc_documents;
    public ?Carbon $kyc_verified_at;
    public string $preferred_language;
    public string $preferred_currency;
    public bool $newsletter_subscribed;
    public bool $sms_notifications;
    public ?string $referral_code;
    public ?int $referred_by;
    public Carbon $created_at;
    public Carbon $updated_at;

    public function __construct(array $data = [])
    {
        $this->fill($data);
    }

    /**
     * Create User instance from array data
     */
    public static function fromArray(array $data): self
    {
        return new self($data);
    }

    /**
     * Fill model with data
     */
    public function fill(array $data): void
    {
        foreach ($data as $key => $value) {
            if (property_exists($this, $key)) {
                // Handle special cases for date fields
                if (in_array($key, ['email_verified_at', 'phone_verified_at', 'locked_until', 'last_login_at', 'kyc_verified_at', 'created_at', 'updated_at'])) {
                    $this->$key = $value ? Carbon::parse($value) : null;
                } elseif ($key === 'kyc_documents') {
                    $this->$key = is_string($value) ? json_decode($value, true) : $value;
                } elseif (in_array($key, ['two_factor_enabled', 'newsletter_subscribed', 'sms_notifications'])) {
                    $this->$key = (bool) $value;
                } else {
                    $this->$key = $value;
                }
            }
        }
    }

    /**
     * Convert to array
     */
    public function toArray(): array
    {
        $data = [];
        
        foreach (get_object_vars($this) as $key => $value) {
            if ($value instanceof Carbon) {
                $data[$key] = $value->toISOString();
            } elseif ($key === 'password_hash' || $key === 'two_factor_secret') {
                // Don't include sensitive data
                continue;
            } else {
                $data[$key] = $value;
            }
        }
        
        return $data;
    }

    /**
     * Get full name
     */
    public function getFullName(): string
    {
        $parts = array_filter([$this->first_name, $this->middle_name, $this->last_name]);
        return implode(' ', $parts);
    }

    /**
     * Check if email is verified
     */
    public function isEmailVerified(): bool
    {
        return $this->email_verified_at !== null;
    }

    /**
     * Check if phone is verified
     */
    public function isPhoneVerified(): bool
    {
        return $this->phone_verified_at !== null;
    }

    /**
     * Check if account is active
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Check if account is locked
     */
    public function isLocked(): bool
    {
        return $this->locked_until && $this->locked_until->isFuture();
    }

    /**
     * Check if user has completed KYC
     */
    public function isKycVerified(): bool
    {
        return $this->kyc_status === 'verified';
    }

    /**
     * Get user's age
     */
    public function getAge(): ?int
    {
        if (!$this->date_of_birth) {
            return null;
        }
        
        return Carbon::parse($this->date_of_birth)->age;
    }

    /**
     * Get profile completion percentage
     */
    public function getProfileCompletionPercentage(): int
    {
        $requiredFields = [
            'first_name', 'last_name', 'email', 'phone', 
            'date_of_birth', 'gender', 'email_verified_at', 'phone_verified_at'
        ];
        
        $completedFields = 0;
        
        foreach ($requiredFields as $field) {
            if (!empty($this->$field)) {
                $completedFields++;
            }
        }
        
        return (int) (($completedFields / count($requiredFields)) * 100);
    }

    /**
     * Get display name (preferred name for UI)
     */
    public function getDisplayName(): string
    {
        return $this->first_name . ' ' . $this->last_name;
    }

    /**
     * Get avatar URL with fallback
     */
    public function getAvatarUrl(): string
    {
        if ($this->avatar) {
            return $this->avatar;
        }
        
        // Generate Gravatar URL as fallback
        $hash = md5(strtolower(trim($this->email)));
        return "https://www.gravatar.com/avatar/{$hash}?d=mp&s=200";
    }

    /**
     * Check if user can perform action based on status
     */
    public function canPerformAction(string $action): bool
    {
        switch ($action) {
            case 'login':
                return $this->status === 'active' && !$this->isLocked();
                
            case 'place_order':
                return $this->status === 'active' && $this->isEmailVerified();
                
            case 'become_vendor':
                return $this->status === 'active' && $this->isEmailVerified() && $this->isPhoneVerified();
                
            case 'kyc_verification':
                return $this->status === 'active' && in_array($this->kyc_status, ['not_started', 'rejected']);
                
            default:
                return $this->status === 'active';
        }
    }

    /**
     * Get user preferences
     */
    public function getPreferences(): array
    {
        return [
            'language' => $this->preferred_language,
            'currency' => $this->preferred_currency,
            'newsletter' => $this->newsletter_subscribed,
            'sms_notifications' => $this->sms_notifications,
            'two_factor_enabled' => $this->two_factor_enabled
        ];
    }

    /**
     * Get user statistics
     */
    public function getStats(): array
    {
        return [
            'profile_completion' => $this->getProfileCompletionPercentage(),
            'is_email_verified' => $this->isEmailVerified(),
            'is_phone_verified' => $this->isPhoneVerified(),
            'is_kyc_verified' => $this->isKycVerified(),
            'member_since' => $this->created_at->format('Y-m-d'),
            'last_login' => $this->last_login_at?->format('Y-m-d H:i:s'),
            'failed_attempts' => $this->failed_login_attempts,
            'is_locked' => $this->isLocked()
        ];
    }
}