<?php

declare(strict_types=1);

namespace Northvia\Core;

use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use FastRoute\Dispatcher;
use FastRoute\RouteCollector;
use Northvia\Core\Http\Request;
use Northvia\Core\Http\Response;
use Northvia\Core\Middleware\MiddlewareStack;

/**
 * Main application class that handles HTTP request/response cycle
 */
class Application
{
    private ContainerInterface $container;
    private Dispatcher $dispatcher;
    private MiddlewareStack $middlewareStack;
    private array $routes = [];

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        $this->middlewareStack = new MiddlewareStack();
        $this->initializeDispatcher();
    }

    /**
     * Add a route to the application
     */
    public function addRoute(string $method, string $pattern, $handler, array $middleware = []): void
    {
        $this->routes[] = [
            'method' => $method,
            'pattern' => $pattern,
            'handler' => $handler,
            'middleware' => $middleware
        ];
        
        // Reinitialize dispatcher with new routes
        $this->initializeDispatcher();
    }

    /**
     * Add global middleware
     */
    public function addMiddleware($middleware): void
    {
        $this->middlewareStack->add($middleware);
    }

    /**
     * Handle incoming HTTP request
     */
    public function handleRequest(): ResponseInterface
    {
        $request = $this->createRequestFromGlobals();
        
        // Process request through middleware stack
        return $this->middlewareStack->process($request, function($request) {
            return $this->dispatchRequest($request);
        });
    }

    /**
     * Send HTTP response
     */
    public function sendResponse(ResponseInterface $response): void
    {
        // Send status code
        http_response_code($response->getStatusCode());
        
        // Send headers
        foreach ($response->getHeaders() as $name => $values) {
            foreach ($values as $value) {
                header(sprintf('%s: %s', $name, $value), false);
            }
        }
        
        // Send body
        echo $response->getBody();
    }

    /**
     * Create request object from PHP globals
     */
    private function createRequestFromGlobals(): ServerRequestInterface
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $headers = $this->getAllHeaders();
        
        // Parse URI and query string
        $uriParts = parse_url($uri);
        $path = $uriParts['path'] ?? '/';
        $query = [];
        if (isset($uriParts['query'])) {
            parse_str($uriParts['query'], $query);
        }

        // Get body
        $body = file_get_contents('php://input');
        
        // Parse body based on content type
        $parsedBody = null;
        $contentType = $headers['Content-Type'] ?? '';
        
        if (strpos($contentType, 'application/json') !== false) {
            $parsedBody = json_decode($body, true);
        } elseif (strpos($contentType, 'application/x-www-form-urlencoded') !== false) {
            parse_str($body, $parsedBody);
        } elseif ($method === 'POST') {
            $parsedBody = $_POST;
        }

        return new Request(
            $method,
            $path,
            $headers,
            $body,
            $query,
            $parsedBody,
            $_COOKIE,
            $_FILES,
            $_SERVER
        );
    }

    /**
     * Get all HTTP headers
     */
    private function getAllHeaders(): array
    {
        $headers = [];
        
        if (function_exists('getallheaders')) {
            $headers = getallheaders();
        } else {
            // Fallback for when getallheaders() is not available
            foreach ($_SERVER as $name => $value) {
                if (substr($name, 0, 5) === 'HTTP_') {
                    $headerName = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))));
                    $headers[$headerName] = $value;
                }
            }
        }
        
        return $headers;
    }

    /**
     * Dispatch request to appropriate handler
     */
    private function dispatchRequest(ServerRequestInterface $request): ResponseInterface
    {
        $routeInfo = $this->dispatcher->dispatch(
            $request->getMethod(),
            $request->getUri()->getPath()
        );

        switch ($routeInfo[0]) {
            case Dispatcher::NOT_FOUND:
                return new Response(404, ['Content-Type' => 'application/json'], 
                    json_encode(['error' => 'Not Found']));

            case Dispatcher::METHOD_NOT_ALLOWED:
                return new Response(405, ['Content-Type' => 'application/json'], 
                    json_encode(['error' => 'Method Not Allowed']));

            case Dispatcher::FOUND:
                $handler = $routeInfo[1];
                $vars = $routeInfo[2];
                
                // Add route parameters to request attributes
                foreach ($vars as $key => $value) {
                    $request = $request->withAttribute($key, $value);
                }
                
                return $this->executeHandler($handler, $request);

            default:
                return new Response(500, ['Content-Type' => 'application/json'], 
                    json_encode(['error' => 'Internal Server Error']));
        }
    }

    /**
     * Execute route handler
     */
    private function executeHandler($handler, ServerRequestInterface $request): ResponseInterface
    {
        if (is_string($handler)) {
            // Handler is a string like "Controller@method"
            [$controllerClass, $method] = explode('@', $handler, 2);
            $controller = $this->container->get($controllerClass);
            return $controller->$method($request);
        }
        
        if (is_callable($handler)) {
            // Handler is a callable
            return $handler($request);
        }
        
        if (is_array($handler) && count($handler) === 2) {
            // Handler is [Controller::class, 'method']
            [$controllerClass, $method] = $handler;
            $controller = $this->container->get($controllerClass);
            return $controller->$method($request);
        }
        
        throw new \RuntimeException('Invalid route handler');
    }

    /**
     * Initialize the FastRoute dispatcher
     */
    private function initializeDispatcher(): void
    {
        $this->dispatcher = \FastRoute\simpleDispatcher(function(RouteCollector $r) {
            foreach ($this->routes as $route) {
                $r->addRoute($route['method'], $route['pattern'], $route['handler']);
            }
        });
    }

    /**
     * Refresh dispatcher with current routes
     */
    public function refreshRoutes(): void
    {
        $this->initializeDispatcher();
    }
}