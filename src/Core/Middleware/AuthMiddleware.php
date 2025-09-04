<?php

declare(strict_types=1);

namespace Northvia\Core\Middleware;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Northvia\Core\Auth\AuthManager;
use Northvia\Core\Http\Response;

/**
 * Authentication middleware
 */
class AuthMiddleware
{
    private AuthManager $auth;

    public function __construct(AuthManager $auth = null)
    {
        // For now, we'll inject it manually in the process method
        $this->auth = $auth;
    }

    /**
     * Process the request
     */
    public function process(ServerRequestInterface $request, callable $next): ResponseInterface
    {
        // Get authorization header
        $authHeader = $request->getHeaderLine('Authorization');
        
        if (empty($authHeader)) {
            return Response::unauthorized('Authorization header missing');
        }

        // Extract bearer token
        if (!preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            return Response::unauthorized('Invalid authorization header format');
        }

        $token = $matches[1];

        // Get auth manager from container (simplified for now)
        global $container;
        if ($container) {
            $this->auth = $container->get(AuthManager::class);
        }

        if (!$this->auth) {
            return Response::serverError('Authentication service unavailable');
        }

        // Verify token and get user
        $user = $this->auth->verifyToken($token);
        
        if (!$user) {
            return Response::unauthorized('Invalid or expired token');
        }

        // Add user to request attributes
        $request = $request->withAttribute('user', $user);
        
        // Add client IP for rate limiting
        $clientIp = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $request = $request->withAttribute('client_ip', $clientIp);

        return $next($request);
    }
}