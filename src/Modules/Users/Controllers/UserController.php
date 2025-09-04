<?php

declare(strict_types=1);

namespace Northvia\Modules\Users\Controllers;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Northvia\Core\Database\Database;
use Northvia\Core\Http\Response;
use Northvia\Core\Validation\Validator;
use Northvia\Core\Security\PasswordHasher;
use Northvia\Modules\Users\Models\User;
use Carbon\Carbon;

/**
 * User management controller
 */
class UserController
{
    private Database $db;
    private Validator $validator;
    private PasswordHasher $hasher;

    public function __construct(Database $db, Validator $validator, PasswordHasher $hasher)
    {
        $this->db = $db;
        $this->validator = $validator;
        $this->hasher = $hasher;
    }

    /**
     * Get user profile
     */
    public function profile(ServerRequestInterface $request): ResponseInterface
    {
        $user = $request->getAttribute('user');

        if (!$user) {
            return Response::unauthorized('Not authenticated');
        }

        // Get additional profile data
        $addresses = $this->db->table('user_addresses')
            ->where('user_id', $user->id)
            ->orderBy('is_default', 'DESC')
            ->get();

        $profileData = $user->toArray();
        $profileData['addresses'] = $addresses;
        $profileData['stats'] = $user->getStats();
        $profileData['preferences'] = $user->getPreferences();
        $profileData['profile_completion'] = $user->getProfileCompletionPercentage();

        return Response::success($profileData, 'Profile retrieved successfully');
    }

    /**
     * Update user profile
     */
    public function updateProfile(ServerRequestInterface $request): ResponseInterface
    {
        $user = $request->getAttribute('user');
        $data = $request->input();

        // Validate request
        $validation = $this->validator->validate($data, [
            'first_name' => 'nullable|string|max:100',
            'last_name' => 'nullable|string|max:100',
            'middle_name' => 'nullable|string|max:100',
            'phone' => 'nullable|string|max:20',
            'date_of_birth' => 'nullable|date',
            'gender' => 'nullable|in:male,female,other',
            'preferred_language' => 'nullable|string|max:10',
            'preferred_currency' => 'nullable|string|max:3',
            'newsletter_subscribed' => 'boolean',
            'sms_notifications' => 'boolean'
        ]);

        if (!$validation->isValid()) {
            return Response::validationError($validation->getErrors());
        }

        // Check if phone number is already taken by another user
        if (isset($data['phone']) && $data['phone'] !== $user->phone) {
            $existingUser = $this->db->table('users')
                ->where('phone', $data['phone'])
                ->where('id', '!=', $user->id)
                ->first();

            if ($existingUser) {
                return Response::error('Phone number is already taken', 400);
            }

            // If phone is changed, mark as unverified
            $data['phone_verified_at'] = null;
        }

        // Update user profile
        $updateData = array_filter($data, function($value) {
            return $value !== null;
        });

        if (!empty($updateData)) {
            $updateData['updated_at'] = Carbon::now()->toDateTimeString();
            
            $this->db->table('users')
                ->where('id', $user->id)
                ->update($updateData);
        }

        // Get updated user data
        $updatedUserData = $this->db->table('users')->where('id', $user->id)->first();
        $updatedUser = User::fromArray((array) $updatedUserData);

        return Response::success($updatedUser->toArray(), 'Profile updated successfully');
    }

    /**
     * Change user password
     */
    public function changePassword(ServerRequestInterface $request): ResponseInterface
    {
        $user = $request->getAttribute('user');
        $data = $request->input();

        // Validate request
        $validation = $this->validator->validate($data, [
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:8',
            'new_password_confirmation' => 'required|string|same:new_password'
        ]);

        if (!$validation->isValid()) {
            return Response::validationError($validation->getErrors());
        }

        // Verify current password
        if (!$this->hasher->verify($data['current_password'], $user->password_hash)) {
            return Response::error('Current password is incorrect', 400);
        }

        // Hash new password
        $newPasswordHash = $this->hasher->hash($data['new_password']);

        // Update password
        $this->db->table('users')
            ->where('id', $user->id)
            ->update([
                'password_hash' => $newPasswordHash,
                'updated_at' => Carbon::now()->toDateTimeString()
            ]);

        return Response::success(null, 'Password changed successfully');
    }

    /**
     * Upload user avatar
     */
    public function uploadAvatar(ServerRequestInterface $request): ResponseInterface
    {
        $user = $request->getAttribute('user');
        $files = $request->getUploadedFiles();

        if (!isset($files['avatar'])) {
            return Response::error('No avatar file provided', 400);
        }

        $file = $files['avatar'];

        // Validate file
        if ($file->getError() !== UPLOAD_ERR_OK) {
            return Response::error('File upload failed', 400);
        }

        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
        if (!in_array($file->getClientMediaType(), $allowedTypes)) {
            return Response::error('Invalid file type. Only JPEG, PNG, and GIF are allowed', 400);
        }

        if ($file->getSize() > 2 * 1024 * 1024) { // 2MB limit
            return Response::error('File size must be less than 2MB', 400);
        }

        // Generate file name
        $extension = pathinfo($file->getClientFilename(), PATHINFO_EXTENSION);
        $filename = 'avatar_' . $user->id . '_' . time() . '.' . $extension;
        $uploadPath = 'storage/uploads/avatars/' . $filename;

        // Create directory if it doesn't exist
        $uploadDir = dirname($uploadPath);
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        // Move uploaded file
        try {
            $file->moveTo($uploadPath);
        } catch (\Exception $e) {
            return Response::error('Failed to save avatar', 500);
        }

        // Update user avatar path
        $avatarUrl = '/' . $uploadPath;
        $this->db->table('users')
            ->where('id', $user->id)
            ->update([
                'avatar' => $avatarUrl,
                'updated_at' => Carbon::now()->toDateTimeString()
            ]);

        return Response::success(['avatar_url' => $avatarUrl], 'Avatar uploaded successfully');
    }

    /**
     * Add user address
     */
    public function addAddress(ServerRequestInterface $request): ResponseInterface
    {
        $user = $request->getAttribute('user');
        $data = $request->input();

        // Validate request
        $validation = $this->validator->validate($data, [
            'type' => 'required|in:home,work,other',
            'label' => 'nullable|string|max:50',
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'phone' => 'nullable|string|max:20',
            'address_line1' => 'required|string|max:255',
            'address_line2' => 'nullable|string|max:255',
            'city' => 'required|string|max:100',
            'state' => 'required|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'country' => 'nullable|string|max:100',
            'landmark' => 'nullable|string|max:255',
            'delivery_instructions' => 'nullable|string',
            'is_default' => 'boolean'
        ]);

        if (!$validation->isValid()) {
            return Response::validationError($validation->getErrors());
        }

        // If this is set as default, unset other defaults
        if ($data['is_default'] ?? false) {
            $this->db->table('user_addresses')
                ->where('user_id', $user->id)
                ->update(['is_default' => 0]);
        }

        // Add address
        $addressData = array_merge($data, [
            'user_id' => $user->id,
            'country' => $data['country'] ?? 'Nigeria',
            'created_at' => Carbon::now()->toDateTimeString()
        ]);

        $addressId = $this->db->table('user_addresses')->insertGetId($addressData);

        // Get created address
        $address = $this->db->table('user_addresses')->where('id', $addressId)->first();

        return Response::created($address, 'Address added successfully');
    }

    /**
     * Update user address
     */
    public function updateAddress(ServerRequestInterface $request): ResponseInterface
    {
        $user = $request->getAttribute('user');
        $addressId = $request->getAttribute('address_id');
        $data = $request->input();

        // Check if address belongs to user
        $address = $this->db->table('user_addresses')
            ->where('id', $addressId)
            ->where('user_id', $user->id)
            ->first();

        if (!$address) {
            return Response::notFound('Address not found');
        }

        // Validate request
        $validation = $this->validator->validate($data, [
            'type' => 'nullable|in:home,work,other',
            'label' => 'nullable|string|max:50',
            'first_name' => 'nullable|string|max:100',
            'last_name' => 'nullable|string|max:100',
            'phone' => 'nullable|string|max:20',
            'address_line1' => 'nullable|string|max:255',
            'address_line2' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'country' => 'nullable|string|max:100',
            'landmark' => 'nullable|string|max:255',
            'delivery_instructions' => 'nullable|string',
            'is_default' => 'boolean'
        ]);

        if (!$validation->isValid()) {
            return Response::validationError($validation->getErrors());
        }

        // If this is set as default, unset other defaults
        if ($data['is_default'] ?? false) {
            $this->db->table('user_addresses')
                ->where('user_id', $user->id)
                ->update(['is_default' => 0]);
        }

        // Update address
        $updateData = array_filter($data, function($value) {
            return $value !== null;
        });

        if (!empty($updateData)) {
            $updateData['updated_at'] = Carbon::now()->toDateTimeString();
            
            $this->db->table('user_addresses')
                ->where('id', $addressId)
                ->update($updateData);
        }

        // Get updated address
        $updatedAddress = $this->db->table('user_addresses')->where('id', $addressId)->first();

        return Response::success($updatedAddress, 'Address updated successfully');
    }

    /**
     * Delete user address
     */
    public function deleteAddress(ServerRequestInterface $request): ResponseInterface
    {
        $user = $request->getAttribute('user');
        $addressId = $request->getAttribute('address_id');

        // Check if address belongs to user
        $address = $this->db->table('user_addresses')
            ->where('id', $addressId)
            ->where('user_id', $user->id)
            ->first();

        if (!$address) {
            return Response::notFound('Address not found');
        }

        // Delete address
        $this->db->table('user_addresses')
            ->where('id', $addressId)
            ->delete();

        return Response::success(null, 'Address deleted successfully');
    }

    /**
     * Get user addresses
     */
    public function getAddresses(ServerRequestInterface $request): ResponseInterface
    {
        $user = $request->getAttribute('user');

        $addresses = $this->db->table('user_addresses')
            ->where('user_id', $user->id)
            ->orderBy('is_default', 'DESC')
            ->orderBy('created_at', 'DESC')
            ->get();

        return Response::success($addresses, 'Addresses retrieved successfully');
    }

    /**
     * Delete user account
     */
    public function deleteAccount(ServerRequestInterface $request): ResponseInterface
    {
        $user = $request->getAttribute('user');
        $data = $request->input();

        // Validate password for security
        $validation = $this->validator->validate($data, [
            'password' => 'required|string',
            'confirmation' => 'required|string|same:password'
        ]);

        if (!$validation->isValid()) {
            return Response::validationError($validation->getErrors());
        }

        // Verify password
        if (!$this->hasher->verify($data['password'], $user->password_hash)) {
            return Response::error('Password is incorrect', 400);
        }

        // Check for active orders or other dependencies
        $activeOrders = $this->db->table('orders')
            ->where('user_id', $user->id)
            ->whereIn('status', ['pending', 'confirmed', 'processing', 'packed', 'shipped'])
            ->count();

        if ($activeOrders > 0) {
            return Response::error('Cannot delete account with active orders. Please wait for orders to complete or contact support.', 400);
        }

        // Soft delete by updating status
        $this->db->table('users')
            ->where('id', $user->id)
            ->update([
                'status' => 'deleted',
                'email' => $user->email . '_deleted_' . time(),
                'phone' => null,
                'updated_at' => Carbon::now()->toDateTimeString()
            ]);

        return Response::success(null, 'Account deleted successfully');
    }

    /**
     * Get user notifications
     */
    public function getNotifications(ServerRequestInterface $request): ResponseInterface
    {
        $user = $request->getAttribute('user');
        $page = max(1, (int) $request->query('page', '1'));
        $limit = min(50, max(10, (int) $request->query('limit', '20')));
        $offset = ($page - 1) * $limit;

        $notifications = $this->db->table('notifications')
            ->where('user_id', $user->id)
            ->orderBy('created_at', 'DESC')
            ->limit($limit)
            ->offset($offset)
            ->get();

        $totalCount = $this->db->table('notifications')
            ->where('user_id', $user->id)
            ->count();

        $unreadCount = $this->db->table('notifications')
            ->where('user_id', $user->id)
            ->whereNull('read_at')
            ->count();

        return Response::success($notifications, 'Notifications retrieved successfully')
            ->withPagination([
                'current_page' => $page,
                'per_page' => $limit,
                'total' => $totalCount,
                'last_page' => (int) ceil($totalCount / $limit),
                'from' => $offset + 1,
                'to' => min($offset + $limit, $totalCount)
            ])
            ->withMeta(['unread_count' => $unreadCount]);
    }

    /**
     * Mark notification as read
     */
    public function markNotificationAsRead(ServerRequestInterface $request): ResponseInterface
    {
        $user = $request->getAttribute('user');
        $notificationId = $request->getAttribute('notification_id');

        $notification = $this->db->table('notifications')
            ->where('id', $notificationId)
            ->where('user_id', $user->id)
            ->first();

        if (!$notification) {
            return Response::notFound('Notification not found');
        }

        if (!$notification->read_at) {
            $this->db->table('notifications')
                ->where('id', $notificationId)
                ->update(['read_at' => Carbon::now()->toDateTimeString()]);
        }

        return Response::success(null, 'Notification marked as read');
    }

    /**
     * Mark all notifications as read
     */
    public function markAllNotificationsAsRead(ServerRequestInterface $request): ResponseInterface
    {
        $user = $request->getAttribute('user');

        $this->db->table('notifications')
            ->where('user_id', $user->id)
            ->whereNull('read_at')
            ->update(['read_at' => Carbon::now()->toDateTimeString()]);

        return Response::success(null, 'All notifications marked as read');
    }
}