<?php

/**
 * Service Container Configuration
 */

use Northvia\Core\Application;
use Northvia\Core\Database\Database;
use Northvia\Core\Auth\AuthManager;
use Northvia\Core\Security\JWTManager;
use Northvia\Core\Security\PasswordHasher;
use Northvia\Core\Security\RateLimiter;
use Northvia\Core\Validation\Validator;
use Northvia\Core\ErrorHandler;
use Northvia\Modules\Auth\Controllers\AuthController;
use Northvia\Modules\Users\Controllers\UserController;
use Northvia\Modules\Products\Controllers\ProductController;
use Psr\Container\ContainerInterface;

// Container self-reference
$container->singleton(ContainerInterface::class, function() use ($container) {
    return $container;
});

// Database
$container->singleton(Database::class, function() {
    $config = require ROOT_PATH . '/config/database.php';
    return new Database($config['connections'][$config['default']]);
});

// Security Services
$container->singleton(JWTManager::class, function() {
    return new JWTManager();
});

$container->singleton(PasswordHasher::class, function() {
    return new PasswordHasher();
});

$container->singleton(RateLimiter::class, function() {
    return new RateLimiter(ROOT_PATH . '/storage/rate_limits/');
});

// Validation
$container->singleton(Validator::class, function() {
    return new Validator();
});

// Authentication
$container->singleton(AuthManager::class, function($container) {
    return new AuthManager(
        $container->get(Database::class),
        $container->get(JWTManager::class),
        $container->get(PasswordHasher::class)
    );
});

// Error Handler
$container->singleton(ErrorHandler::class, function() {
    return new ErrorHandler();
});

// Controllers
$container->bind(AuthController::class, function($container) {
    return new AuthController(
        $container->get(AuthManager::class),
        $container->get(Validator::class),
        $container->get(RateLimiter::class)
    );
});

$container->bind(UserController::class, function($container) {
    return new UserController(
        $container->get(Database::class),
        $container->get(Validator::class),
        $container->get(PasswordHasher::class)
    );
});

$container->bind(ProductController::class, function($container) {
    return new ProductController(
        $container->get(Database::class),
        $container->get(Validator::class)
    );
});

// Application
$container->singleton(Application::class, function($container) {
    return new Application($container);
});