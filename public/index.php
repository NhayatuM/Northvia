<?php

/**
 * NorthVia E-commerce Platform
 * Entry point for all HTTP requests
 */

declare(strict_types=1);

use Northvia\Core\Application;
use Northvia\Core\Container;
use Northvia\Core\ErrorHandler;
use Dotenv\Dotenv;

// Define constants
define('ROOT_PATH', dirname(__DIR__));
define('APP_START_TIME', microtime(true));

// Load autoloader
require_once ROOT_PATH . '/vendor/autoload.php';

try {
    // Load environment variables
    $dotenv = Dotenv::createImmutable(ROOT_PATH);
    if (file_exists(ROOT_PATH . '/.env')) {
        $dotenv->load();
    }

    // Set timezone
    date_default_timezone_set($_ENV['APP_TIMEZONE'] ?? 'UTC');

    // Set error reporting based on environment
    if (($_ENV['APP_ENV'] ?? 'production') === 'development') {
        error_reporting(E_ALL);
        ini_set('display_errors', '1');
        ini_set('display_startup_errors', '1');
    } else {
        error_reporting(0);
        ini_set('display_errors', '0');
    }

    // Initialize dependency injection container
    $container = new Container();
    
    // Load services
    require_once ROOT_PATH . '/config/services.php';
    
    // Initialize application
    $app = $container->get(Application::class);
    
    // Set up error handler
    $errorHandler = $container->get(ErrorHandler::class);
    set_exception_handler([$errorHandler, 'handleException']);
    set_error_handler([$errorHandler, 'handleError']);
    register_shutdown_function([$errorHandler, 'handleShutdown']);

    // Load routes
    require_once ROOT_PATH . '/config/routes.php';
    
    // Handle the request
    $response = $app->handleRequest();
    
    // Send response
    $app->sendResponse($response);

} catch (Throwable $e) {
    // Fallback error handling
    http_response_code(500);
    
    if (($_ENV['APP_ENV'] ?? 'production') === 'development') {
        echo json_encode([
            'error' => 'Internal Server Error',
            'message' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ], JSON_PRETTY_PRINT);
    } else {
        echo json_encode(['error' => 'Internal Server Error']);
    }
    
    error_log($e->getMessage() . "\n" . $e->getTraceAsString());
}