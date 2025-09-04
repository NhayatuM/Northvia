<?php

/**
 * Application Routes
 */

use Northvia\Core\Middleware\AuthMiddleware;
use Northvia\Core\Middleware\CorsMiddleware;
use Northvia\Modules\Auth\Controllers\AuthController;
use Northvia\Modules\Users\Controllers\UserController;
use Northvia\Modules\Products\Controllers\ProductController;

// Add global middleware
$app->addMiddleware(new CorsMiddleware());

// Authentication routes
$app->addRoute('POST', '/api/auth/register', [AuthController::class, 'register']);
$app->addRoute('POST', '/api/auth/login', [AuthController::class, 'login']);
$app->addRoute('POST', '/api/auth/refresh', [AuthController::class, 'refresh']);
$app->addRoute('POST', '/api/auth/logout', [AuthController::class, 'logout'], [AuthMiddleware::class]);
$app->addRoute('GET', '/api/auth/me', [AuthController::class, 'me'], [AuthMiddleware::class]);

// Email verification
$app->addRoute('GET', '/api/auth/verify-email/{token}', [AuthController::class, 'verifyEmail']);

// Password reset
$app->addRoute('POST', '/api/auth/forgot-password', [AuthController::class, 'forgotPassword']);
$app->addRoute('POST', '/api/auth/reset-password', [AuthController::class, 'resetPassword']);
$app->addRoute('POST', '/api/auth/change-password', [AuthController::class, 'changePassword'], [AuthMiddleware::class]);

// Two-factor authentication
$app->addRoute('POST', '/api/auth/2fa/enable', [AuthController::class, 'enableTwoFactor'], [AuthMiddleware::class]);
$app->addRoute('POST', '/api/auth/2fa/verify', [AuthController::class, 'verifyTwoFactor'], [AuthMiddleware::class]);

// User management routes
$app->addRoute('GET', '/api/user/profile', [UserController::class, 'profile'], [AuthMiddleware::class]);
$app->addRoute('PUT', '/api/user/profile', [UserController::class, 'updateProfile'], [AuthMiddleware::class]);
$app->addRoute('POST', '/api/user/avatar', [UserController::class, 'uploadAvatar'], [AuthMiddleware::class]);
$app->addRoute('DELETE', '/api/user/account', [UserController::class, 'deleteAccount'], [AuthMiddleware::class]);

// User addresses
$app->addRoute('GET', '/api/user/addresses', [UserController::class, 'getAddresses'], [AuthMiddleware::class]);
$app->addRoute('POST', '/api/user/addresses', [UserController::class, 'addAddress'], [AuthMiddleware::class]);
$app->addRoute('PUT', '/api/user/addresses/{address_id}', [UserController::class, 'updateAddress'], [AuthMiddleware::class]);
$app->addRoute('DELETE', '/api/user/addresses/{address_id}', [UserController::class, 'deleteAddress'], [AuthMiddleware::class]);

// User notifications
$app->addRoute('GET', '/api/user/notifications', [UserController::class, 'getNotifications'], [AuthMiddleware::class]);
$app->addRoute('PUT', '/api/user/notifications/{notification_id}/read', [UserController::class, 'markNotificationAsRead'], [AuthMiddleware::class]);
$app->addRoute('PUT', '/api/user/notifications/read-all', [UserController::class, 'markAllNotificationsAsRead'], [AuthMiddleware::class]);

// Product management routes
$app->addRoute('GET', '/api/products', [ProductController::class, 'index']);
$app->addRoute('GET', '/api/products/search', [ProductController::class, 'search']);
$app->addRoute('GET', '/api/products/featured', [ProductController::class, 'getFeatured']);
$app->addRoute('GET', '/api/products/categories', [ProductController::class, 'getCategories']);
$app->addRoute('GET', '/api/products/brands', [ProductController::class, 'getBrands']);
$app->addRoute('GET', '/api/products/category/{category_id}', [ProductController::class, 'getByCategory']);
$app->addRoute('GET', '/api/products/{id}', [ProductController::class, 'show']);

// Health check
$app->addRoute('GET', '/api/health', function($request) {
    return \Northvia\Core\Http\Response::success([
        'status' => 'healthy',
        'timestamp' => date('c'),
        'version' => '1.0.0'
    ], 'API is healthy');
});

// Test database connection
$app->addRoute('GET', '/api/test-db', function($request) use ($container) {
    try {
        $db = $container->get(\Northvia\Core\Database\Database::class);
        $result = $db->query('SELECT COUNT(*) as count FROM users')->fetch();
        
        // Convert stdClass to array if needed
        $count = is_object($result) ? $result->count : ($result['count'] ?? 0);
        
        return \Northvia\Core\Http\Response::success([
            'database' => 'connected',
            'users_count' => (int)$count,
            'timestamp' => date('c')
        ], 'Database connection successful');
    } catch (Exception $e) {
        return \Northvia\Core\Http\Response::error('Database connection failed: ' . $e->getMessage(), 500);
    }
});