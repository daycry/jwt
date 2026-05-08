<?php

/**
 * Bearer-token authentication filter.
 *
 * Drop this in app/Filters/JWTAuthFilter.php, then in app/Config/Filters.php:
 *
 *     public array $aliases = [
 *         'jwtAuth' => \App\Filters\JWTAuthFilter::class,
 *     ];
 *
 * And apply it in app/Config/Routes.php:
 *
 *     $routes->group('api', ['filter' => 'jwtAuth'], function ($routes) {
 *         $routes->get('profile', 'ProfileController::index');
 *     });
 *
 * Inside the controller, the parsed token is available on the request:
 *
 *     $userId = $this->request->jwt->claims()->get('uid');
 */

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Daycry\JWT\JWT;

class JWTAuthFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $header = $request->getHeaderLine('Authorization');

        if (! str_starts_with($header, 'Bearer ')) {
            return service('response')
                ->setStatusCode(401)
                ->setJSON(['error' => 'Missing bearer token']);
        }

        $token   = substr($header, 7);
        $decoded = JWT::for()->tryDecode($token);

        if ($decoded === null) {
            return service('response')
                ->setStatusCode(401)
                ->setJSON(['error' => 'Invalid or expired token']);
        }

        // Expose the parsed token to downstream handlers without re-parsing.
        $request->jwt = $decoded;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
    }
}
