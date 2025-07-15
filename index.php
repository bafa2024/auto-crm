<?php
// index.php - Main entry point with advanced routing

// Use custom autoloader if Composer is not available
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
} else {
    require_once __DIR__ . '/autoload.php';
}

// Load required files
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/router/Router.php';

// Load all model files (temporary until proper autoloading)
$modelFiles = glob(__DIR__ . '/models/*.php');
foreach ($modelFiles as $modelFile) {
    require_once $modelFile;
}

// Load all controller files
$controllerFiles = glob(__DIR__ . '/controllers/*.php');
foreach ($controllerFiles as $controllerFile) {
    require_once $controllerFile;
}

// Load all service files
$serviceFiles = glob(__DIR__ . '/services/*.php');
foreach ($serviceFiles as $serviceFile) {
    require_once $serviceFile;
}

use Router\Router;
use Router\Request;
use Router\Response;

// Initialize router
$router = new Router();

// Initialize database
$database = new Database();
$db = $database->getConnection();

// Middleware for authentication
$authMiddleware = function($request, $next) {
    // Check if route requires authentication
    $publicRoutes = ['/login', '/signup', '/api/auth/login', '/api/auth/register'];
    $currentPath = parse_url($request->uri, PHP_URL_PATH);
    
    if (in_array($currentPath, $publicRoutes)) {
        return $next($request);
    }
    
    // Check session or JWT token
    session_start();
    if (!isset($_SESSION['user_id']) && strpos($currentPath, '/api/') === 0) {
        // For API routes, check Authorization header
        $authHeader = $request->header('Authorization');
        if (!$authHeader || !validateJWT($authHeader)) {
            $response = new Response();
            return $response->status(401)->json(['error' => 'Unauthorized']);
        }
    } elseif (!isset($_SESSION['user_id']) && strpos($currentPath, '/dashboard') === 0) {
        // For dashboard routes, redirect to login
        $response = new Response();
        return $response->redirect('/login');
    }
    
    return $next($request);
};

// Global middleware
$router->use($authMiddleware);

// CORS middleware for API routes
$router->use(function($request, $next) {
    if (strpos($request->uri, '/api/') === 0) {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization');
    }
    return $next($request);
});

// ===== WEB ROUTES =====

// Landing page
$router->get('/', function($request) {
    require_once __DIR__ . '/views/landing.php';
});

// Auth pages
$router->get('/login', function($request) {
    require_once __DIR__ . '/views/auth/login.php';
});

$router->get('/signup', function($request) {
    require_once __DIR__ . '/views/auth/signup.php';
});

$router->get('/logout', function($request) {
    session_start();
    session_destroy();
    $response = new Response();
    return $response->redirect('/');
});

// Dashboard routes
$router->get('/dashboard', function($request) {
    require_once __DIR__ . '/views/dashboard/index.php';
});

$router->get('/dashboard/profile', function($request) {
    require_once __DIR__ . '/views/dashboard/profile.php';
});

$router->get('/dashboard/contacts', function($request) {
    require_once __DIR__ . '/views/dashboard/contacts.php';
});

$router->get('/dashboard/campaigns', function($request) {
    require_once __DIR__ . '/views/dashboard/campaigns.php';
});

$router->get('/dashboard/analytics', function($request) {
    require_once __DIR__ . '/views/dashboard/analytics.php';
});

$router->get('/dashboard/settings', function($request) {
    require_once __DIR__ . '/views/dashboard/settings.php';
});

// ===== API ROUTES =====

// Auth endpoints
$router->post('/api/auth/login', function($request) use ($db) {
    $controller = new AuthController($db);
    return $controller->login($request);
});

$router->post('/api/auth/register', function($request) use ($db) {
    $controller = new AuthController($db);
    return $controller->register($request);
});

$router->post('/api/auth/logout', function($request) use ($db) {
    $controller = new AuthController($db);
    return $controller->logout($request);
});

$router->get('/api/auth/profile', function($request) use ($db) {
    $controller = new AuthController($db);
    return $controller->getProfile($request);
});

$router->put('/api/auth/profile', function($request) use ($db) {
    $controller = new AuthController($db);
    return $controller->updateProfile($request);
});

// Contact endpoints
$router->get('/api/contacts', function($request) use ($db) {
    $controller = new ContactController($db);
    return $controller->getContacts($request);
});

$router->post('/api/contacts', function($request) use ($db) {
    $controller = new ContactController($db);
    return $controller->createContact($request);
});

$router->get('/api/contacts/stats', function($request) use ($db) {
    $controller = new ContactController($db);
    return $controller->getStats($request);
});

$router->post('/api/contacts/bulk-upload', function($request) use ($db) {
    $controller = new ContactController($db);
    return $controller->bulkUpload($request);
});

$router->get('/api/contacts/{id}', function($request) use ($db) {
    $controller = new ContactController($db);
    return $controller->getContact($request);
});

$router->put('/api/contacts/{id}', function($request) use ($db) {
    $controller = new ContactController($db);
    return $controller->updateContact($request);
});

$router->delete('/api/contacts/{id}', function($request) use ($db) {
    $controller = new ContactController($db);
    return $controller->deleteContact($request);
});

// Campaign endpoints
$router->get('/api/campaigns', function($request) use ($db) {
    $controller = new EmailCampaignController($db);
    return $controller->getCampaigns($request);
});

$router->post('/api/campaigns', function($request) use ($db) {
    $controller = new EmailCampaignController($db);
    return $controller->createCampaign($request);
});

$router->get('/api/campaigns/stats', function($request) use ($db) {
    $controller = new EmailCampaignController($db);
    return $controller->getCampaignStats($request);
});

$router->get('/api/campaigns/{id}', function($request) use ($db) {
    $controller = new EmailCampaignController($db);
    return $controller->getCampaign($request);
});

$router->put('/api/campaigns/{id}', function($request) use ($db) {
    $controller = new EmailCampaignController($db);
    return $controller->updateCampaign($request);
});

$router->delete('/api/campaigns/{id}', function($request) use ($db) {
    $controller = new EmailCampaignController($db);
    return $controller->deleteCampaign($request);
});

$router->get('/api/campaigns/{id}/recipients', function($request) use ($db) {
    $controller = new EmailCampaignController($db);
    return $controller->getRecipients($request);
});

$router->post('/api/campaigns/{id}/recipients', function($request) use ($db) {
    $controller = new EmailCampaignController($db);
    return $controller->addRecipients($request);
});

$router->post('/api/campaigns/{id}/send', function($request) use ($db) {
    $controller = new EmailCampaignController($db);
    return $controller->sendCampaign($request);
});

// Template endpoints
$router->get('/api/templates', function($request) use ($db) {
    $controller = new EmailCampaignController($db);
    return $controller->getTemplates($request);
});

$router->post('/api/templates', function($request) use ($db) {
    $controller = new EmailCampaignController($db);
    return $controller->createTemplate($request);
});

// Tracking endpoints
$router->get('/api/track/open/{trackingId}', function($request) use ($db) {
    $emailService = new EmailService($db);
    $trackingId = $request->param('trackingId');
    $emailService->trackEmailOpen($trackingId);
    
    // Return 1x1 transparent pixel
    header('Content-Type: image/gif');
    echo base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
});

$router->get('/api/track/click/{trackingId}', function($request) use ($db) {
    $emailService = new EmailService($db);
    $trackingId = $request->param('trackingId');
    $url = $request->query('url');
    
    if ($url) {
        $emailService->trackEmailClick($trackingId, $url);
        $response = new Response();
        return $response->redirect($url);
    }
    
    $response = new Response();
    return $response->status(400)->json(['error' => 'URL parameter required']);
});

$router->get('/api/track/unsubscribe/{trackingId}', function($request) use ($db) {
    $emailService = new EmailService($db);
    $trackingId = $request->param('trackingId');
    $success = $emailService->unsubscribe($trackingId);
    
    $response = new Response();
    if ($success) {
        return $response->html('<h1>Unsubscribed Successfully</h1><p>You have been unsubscribed from our mailing list.</p>');
    } else {
        return $response->html('<h1>Error</h1><p>Unable to process unsubscribe request.</p>');
    }
});

// Health check endpoint for cloud platforms
$router->get('/health', function($request) {
    $response = new Response();
    return $response->json([
        'status' => 'healthy',
        'timestamp' => date('Y-m-d H:i:s'),
        'environment' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'
    ]);
});

// 404 handler
$router->setNotFoundHandler(function($request) {
    $response = new Response();
    
    if (strpos($request->uri, '/api/') === 0) {
        return $response->status(404)->json(['error' => 'Endpoint not found']);
    }
    
    // For web routes, show 404 page
    return $response->status(404)->html('<h1>404 - Page Not Found</h1>');
});

// Dispatch the request
$router->dispatch();

// Helper function to validate JWT (implement your JWT validation logic)
function validateJWT($authHeader) {
    // Extract token from "Bearer <token>"
    $token = str_replace('Bearer ', '', $authHeader);
    
    // Implement JWT validation using firebase/php-jwt
    // For now, return false
    return false;
}