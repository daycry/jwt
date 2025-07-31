# 🚀 JWT Implementation Examples

This file contains practical implementation examples for the JWT library in real CodeIgniter 4 applications.

## 🔐 Authentication System Example

### 1. Login Controller

```php
<?php

namespace App\Controllers;

use CodeIgniter\RESTful\ResourceController;
use Daycry\JWT\JWT;

class AuthController extends ResourceController
{
    protected JWT $jwt;
    
    public function __construct()
    {
        $this->jwt = new JWT();
    }
    
    public function login()
    {
        $email = $this->request->getPost('email');
        $password = $this->request->getPost('password');
        
        // Validate credentials (your logic here)
        $userModel = model('UserModel');
        $user = $userModel->where('email', $email)->first();
        
        if (!$user || !password_verify($password, $user->password)) {
            return $this->respond([
                'error' => 'Invalid credentials'
            ], 401);
        }
        
        // Create JWT token
        $tokenData = [
            'user_id' => $user->id,
            'email' => $user->email,
            'roles' => $user->roles ?? [],
            'permissions' => $user->permissions ?? []
        ];
        
        $token = $this->jwt->encode($tokenData, $user->id);
        
        return $this->respond([
            'access_token' => $token,
            'token_type' => 'Bearer',
            'expires_in' => $this->jwt->getTimeToExpiry($token),
            'user' => [
                'id' => $user->id,
                'email' => $user->email,
                'name' => $user->name
            ]
        ]);
    }
    
    public function refresh()
    {
        $token = $this->extractToken();
        
        if (!$token || $this->jwt->isExpired($token)) {
            return $this->respond(['error' => 'Token expired'], 401);
        }
        
        // Quick check without full validation
        $claims = $this->jwt->extractClaimsUnsafe($token);
        $userId = $claims['uid'] ?? null;
        
        if (!$userId) {
            return $this->respond(['error' => 'Invalid token'], 401);
        }
        
        // Generate new token
        $userModel = model('UserModel');
        $user = $userModel->find($userId);
        
        $newTokenData = [
            'user_id' => $user->id,
            'email' => $user->email,
            'roles' => $user->roles ?? []
        ];
        
        $newToken = $this->jwt->encode($newTokenData, $user->id);
        
        return $this->respond([
            'access_token' => $newToken,
            'token_type' => 'Bearer',
            'expires_in' => $this->jwt->getTimeToExpiry($newToken)
        ]);
    }
    
    private function extractToken(): ?string
    {
        $header = $this->request->getHeaderLine('Authorization');
        
        if (preg_match('/Bearer\s+(.*)$/i', $header, $matches)) {
            return $matches[1];
        }
        
        return null;
    }
}
```

### 2. JWT Authentication Filter

```php
<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Daycry\JWT\JWT;

class JWTAuthFilter implements FilterInterface
{
    protected JWT $jwt;
    
    public function __construct()
    {
        $this->jwt = new JWT();
    }
    
    public function before(RequestInterface $request, $arguments = null)
    {
        $token = $this->extractToken($request);
        
        if (!$token) {
            return $this->unauthorizedResponse('Token not provided');
        }
        
        // Fast validation
        if (!$this->jwt->isValid($token)) {
            return $this->unauthorizedResponse('Invalid token');
        }
        
        // Quick expiry check
        if ($this->jwt->isExpired($token)) {
            return $this->unauthorizedResponse('Token expired');
        }
        
        // Extract user data and inject into request
        try {
            $claims = $this->jwt->decode($token);
            $request->jwtClaims = $claims;
            $request->currentUserId = $claims->get('uid');
            $request->userRoles = json_decode($claims->get('data'), true)['roles'] ?? [];
        } catch (\Exception $e) {
            return $this->unauthorizedResponse('Token validation failed');
        }
    }
    
    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // Add security headers
        $response->setHeader('X-Content-Type-Options', 'nosniff');
        $response->setHeader('X-Frame-Options', 'DENY');
        $response->setHeader('X-XSS-Protection', '1; mode=block');
    }
    
    private function extractToken(RequestInterface $request): ?string
    {
        $header = $request->getHeaderLine('Authorization');
        
        if (preg_match('/Bearer\s+(.*)$/i', $header, $matches)) {
            return $matches[1];
        }
        
        return null;
    }
    
    private function unauthorizedResponse(string $message): ResponseInterface
    {
        return response()->setStatusCode(401)->setJSON([
            'error' => 'Unauthorized',
            'message' => $message
        ]);
    }
}
```

### 3. Role-Based Access Filter

```php
<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class RoleFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $requiredRoles = $arguments ?? [];
        $userRoles = $request->userRoles ?? [];
        
        if (empty(array_intersect($requiredRoles, $userRoles))) {
            return response()->setStatusCode(403)->setJSON([
                'error' => 'Forbidden',
                'message' => 'Insufficient permissions'
            ]);
        }
    }
    
    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // No action needed
    }
}
```

### 4. Register Filters in Config/Filters.php

```php
<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

class Filters extends BaseConfig
{
    public array $aliases = [
        'csrf'     => CSRF::class,
        'toolbar'  => DebugToolbar::class,
        'honeypot' => Honeypot::class,
        'invalidchars' => InvalidChars::class,
        'secureheaders' => SecureHeaders::class,
        'jwt'      => \App\Filters\JWTAuthFilter::class,
        'role'     => \App\Filters\RoleFilter::class,
    ];
    
    public array $globals = [
        'before' => [
            // 'honeypot',
            // 'csrf',
            // 'invalidchars',
        ],
        'after' => [
            'toolbar',
            // 'honeypot',
            // 'secureheaders',
        ],
    ];
    
    public array $methods = [];
    
    public array $filters = [
        'jwt' => [
            'before' => [
                'api/*',
                'admin/*'
            ]
        ],
        'role:admin' => [
            'before' => [
                'admin/*'
            ]
        ]
    ];
}
```

## 🎯 Protected API Controller Example

```php
<?php

namespace App\Controllers\Api;

use CodeIgniter\RESTful\ResourceController;

class UsersController extends ResourceController
{
    public function index()
    {
        // JWT filter already validated the token
        $currentUserId = $this->request->currentUserId;
        $userRoles = $this->request->userRoles;
        
        $userModel = model('UserModel');
        
        // Admin can see all users, regular users only themselves
        if (in_array('admin', $userRoles)) {
            $users = $userModel->findAll();
        } else {
            $users = $userModel->find($currentUserId);
        }
        
        return $this->respond([
            'data' => $users,
            'current_user' => $currentUserId
        ]);
    }
    
    public function show($id = null)
    {
        $currentUserId = $this->request->currentUserId;
        $userRoles = $this->request->userRoles;
        
        // Users can only see their own profile unless they're admin
        if ($id != $currentUserId && !in_array('admin', $userRoles)) {
            return $this->respond([
                'error' => 'Access denied'
            ], 403);
        }
        
        $userModel = model('UserModel');
        $user = $userModel->find($id);
        
        if (!$user) {
            return $this->respond([
                'error' => 'User not found'
            ], 404);
        }
        
        return $this->respond([
            'data' => $user
        ]);
    }
}
```

## 🛠️ Helper Functions Implementation

Create `app/Helpers/jwt_helper.php`:

```php
<?php

if (!function_exists('jwt_encode')) {
    function jwt_encode(array $data, ?string $uid = null): string
    {
        $jwt = new \Daycry\JWT\JWT();
        return $jwt->encode($data, $uid);
    }
}

if (!function_exists('jwt_decode')) {
    function jwt_decode(string $token): ?array
    {
        try {
            $jwt = new \Daycry\JWT\JWT();
            $claims = $jwt->decode($token);
            return $claims->all();
        } catch (\Exception $e) {
            return null;
        }
    }
}

if (!function_exists('jwt_user_id')) {
    function jwt_user_id(): ?string
    {
        $request = service('request');
        return $request->currentUserId ?? null;
    }
}

if (!function_exists('jwt_user_roles')) {
    function jwt_user_roles(): array
    {
        $request = service('request');
        return $request->userRoles ?? [];
    }
}

if (!function_calls('jwt_check')) {
    function jwt_check(): bool
    {
        return jwt_user_id() !== null;
    }
}

if (!function_exists('jwt_has_role')) {
    function jwt_has_role(string $role): bool
    {
        $roles = jwt_user_roles();
        return in_array($role, $roles);
    }
}

if (!function_exists('jwt_can')) {
    function jwt_can(string $permission): bool
    {
        $request = service('request');
        $permissions = $request->userPermissions ?? [];
        return in_array($permission, $permissions);
    }
}
```

## 🔧 Environment Configuration

Add to your `.env` file:

```env
# JWT Configuration
JWT_SECRET_KEY=your-very-secure-base64-encoded-key-here
JWT_ISSUER=https://yourdomain.com
JWT_AUDIENCE=https://yourdomain.com
JWT_IDENTIFIER=your-app-unique-id
JWT_EXPIRES_AT="+2 hours"
JWT_ALGORITHM="Lcobucci\JWT\Signer\Hmac\Sha256"

# Development vs Production
CI_ENVIRONMENT=development
```

## 📱 Frontend Integration Example (JavaScript)

```javascript
class JWTAuth {
    constructor() {
        this.token = localStorage.getItem('jwt_token');
        this.apiBase = 'https://your-api.com/api';
    }
    
    async login(email, password) {
        try {
            const response = await fetch(`${this.apiBase}/auth/login`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ email, password })
            });
            
            const data = await response.json();
            
            if (response.ok) {
                this.setToken(data.access_token);
                return data;
            } else {
                throw new Error(data.error || 'Login failed');
            }
        } catch (error) {
            console.error('Login error:', error);
            throw error;
        }
    }
    
    setToken(token) {
        this.token = token;
        localStorage.setItem('jwt_token', token);
    }
    
    getToken() {
        return this.token;
    }
    
    async apiCall(endpoint, options = {}) {
        const headers = {
            'Content-Type': 'application/json',
            ...options.headers
        };
        
        if (this.token) {
            headers['Authorization'] = `Bearer ${this.token}`;
        }
        
        const response = await fetch(`${this.apiBase}${endpoint}`, {
            ...options,
            headers
        });
        
        if (response.status === 401) {
            this.logout();
            throw new Error('Authentication required');
        }
        
        return response;
    }
    
    logout() {
        this.token = null;
        localStorage.removeItem('jwt_token');
        window.location.href = '/login';
    }
    
    isAuthenticated() {
        return !!this.token;
    }
}

// Usage
const auth = new JWTAuth();

// Login
auth.login('user@example.com', 'password')
    .then(data => {
        console.log('Logged in:', data);
    })
    .catch(error => {
        console.error('Login failed:', error);
    });

// Make authenticated API calls
auth.apiCall('/users/profile')
    .then(response => response.json())
    .then(data => {
        console.log('Profile:', data);
    });
```

## 🧪 Testing Example

```php
<?php

namespace Tests\Feature;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;

class AuthenticationTest extends CIUnitTestCase
{
    use FeatureTestTrait;
    
    public function testLoginReturnsJWTToken()
    {
        $response = $this->post('/api/auth/login', [
            'email' => 'test@example.com',
            'password' => 'password123'
        ]);
        
        $response->assertStatus(200);
        $response->assertJSONFragment([
            'token_type' => 'Bearer'
        ]);
        
        $data = $response->getJSON(true);
        $this->assertArrayHasKey('access_token', $data);
    }
    
    public function testProtectedRouteRequiresToken()
    {
        $response = $this->get('/api/users');
        $response->assertStatus(401);
    }
    
    public function testProtectedRouteWithValidToken()
    {
        $jwt = new \Daycry\JWT\JWT();
        $token = $jwt->encode(['user_id' => 1], 1);
        
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}"
        ])->get('/api/users');
        
        $response->assertStatus(200);
    }
}
```

This comprehensive example shows how to implement a complete JWT authentication system in CodeIgniter 4 using the optimized JWT library.
