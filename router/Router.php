<?php
// router/Router.php
namespace Router;

class Router {
    private $routes = [];
    private $middlewares = [];
    private $basePath = '';
    private $notFoundHandler;
    
    public function __construct($basePath = '') {
        $this->basePath = rtrim($basePath, '/');
        $this->notFoundHandler = function() {
            http_response_code(404);
            echo json_encode(['error' => 'Route not found']);
        };
    }
    
    /**
     * Add a route
     */
    public function addRoute($method, $pattern, $handler, $middlewares = []) {
        $pattern = $this->basePath . '/' . ltrim($pattern, '/');
        $pattern = rtrim($pattern, '/') ?: '/';
        
        $this->routes[] = [
            'method' => strtoupper($method),
            'pattern' => $pattern,
            'handler' => $handler,
            'middlewares' => $middlewares,
            'regex' => $this->patternToRegex($pattern),
            'params' => $this->extractParamNames($pattern)
        ];
    }
    
    /**
     * HTTP method shortcuts
     */
    public function get($pattern, $handler, $middlewares = []) {
        $this->addRoute('GET', $pattern, $handler, $middlewares);
    }
    
    public function post($pattern, $handler, $middlewares = []) {
        $this->addRoute('POST', $pattern, $handler, $middlewares);
    }
    
    public function put($pattern, $handler, $middlewares = []) {
        $this->addRoute('PUT', $pattern, $handler, $middlewares);
    }
    
    public function delete($pattern, $handler, $middlewares = []) {
        $this->addRoute('DELETE', $pattern, $handler, $middlewares);
    }
    
    public function patch($pattern, $handler, $middlewares = []) {
        $this->addRoute('PATCH', $pattern, $handler, $middlewares);
    }
    
    public function options($pattern, $handler, $middlewares = []) {
        $this->addRoute('OPTIONS', $pattern, $handler, $middlewares);
    }
    
    /**
     * Add global middleware
     */
    public function use($middleware) {
        $this->middlewares[] = $middleware;
    }
    
    /**
     * Set 404 handler
     */
    public function setNotFoundHandler($handler) {
        $this->notFoundHandler = $handler;
    }
    
    /**
     * Convert route pattern to regex
     */
    private function patternToRegex($pattern) {
        // Escape special regex characters except for {}
        $pattern = preg_quote($pattern, '#');
        
        // Replace {param} with named regex groups
        $pattern = preg_replace('/\\\{([a-zA-Z_][a-zA-Z0-9_]*)\\\}/', '(?P<$1>[^/]+)', $pattern);
        
        // Replace {param:regex} with custom regex
        $pattern = preg_replace('/\\\{([a-zA-Z_][a-zA-Z0-9_]*):([^}]+)\\\}/', '(?P<$1>$2)', $pattern);
        
        return '#^' . $pattern . '$#';
    }
    
    /**
     * Extract parameter names from pattern
     */
    private function extractParamNames($pattern) {
        preg_match_all('/{([a-zA-Z_][a-zA-Z0-9_]*)(?::[^}]+)?}/', $pattern, $matches);
        return $matches[1];
    }
    
    /**
     * Get current request URI
     */
    private function getCurrentUri() {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        
        // Remove query string
        if (false !== $pos = strpos($uri, '?')) {
            $uri = substr($uri, 0, $pos);
        }
        
        // Decode URI
        $uri = rawurldecode($uri);
        
        // Remove trailing slash
        $uri = rtrim($uri, '/') ?: '/';
        
        return $uri;
    }
    
    /**
     * Dispatch the request
     */
    public function dispatch() {
        $method = $_SERVER['REQUEST_METHOD'];
        $uri = $this->getCurrentUri();
        
        // Handle CORS preflight
        if ($method === 'OPTIONS') {
            $this->handleCors();
            return;
        }
        
        // Find matching route
        foreach ($this->routes as $route) {
            if ($route['method'] !== $method && $route['method'] !== 'ANY') {
                continue;
            }
            
            if (preg_match($route['regex'], $uri, $matches)) {
                // Extract parameters
                $params = [];
                foreach ($route['params'] as $param) {
                    if (isset($matches[$param])) {
                        $params[$param] = $matches[$param];
                    }
                }
                
                // Create request object
                $request = new Request($params);
                
                // Run middlewares
                $middlewares = array_merge($this->middlewares, $route['middlewares']);
                $response = $this->runMiddlewares($middlewares, $request, function($request) use ($route) {
                    return $this->callHandler($route['handler'], $request);
                });
                
                // Send response
                $this->sendResponse($response);
                return;
            }
        }
        
        // No route found
        $this->sendResponse($this->callHandler($this->notFoundHandler, new Request()));
    }
    
    /**
     * Run middlewares
     */
    private function runMiddlewares($middlewares, $request, $next) {
        if (empty($middlewares)) {
            return $next($request);
        }
        
        $middleware = array_shift($middlewares);
        
        return $this->callHandler($middleware, $request, function($request) use ($middlewares, $next) {
            return $this->runMiddlewares($middlewares, $request, $next);
        });
    }
    
    /**
     * Call route handler
     */
    private function callHandler($handler, ...$args) {
        if (is_callable($handler)) {
            return call_user_func_array($handler, $args);
        }
        
        if (is_string($handler) && strpos($handler, '@') !== false) {
            list($class, $method) = explode('@', $handler);
            $controller = new $class();
            return call_user_func_array([$controller, $method], $args);
        }
        
        throw new \RuntimeException('Invalid route handler');
    }
    
    /**
     * Send response
     */
    private function sendResponse($response) {
        if ($response instanceof Response) {
            $response->send();
        } elseif (is_array($response) || is_object($response)) {
            header('Content-Type: application/json');
            echo json_encode($response);
        } else {
            echo $response;
        }
    }
    
    /**
     * Handle CORS
     */
    private function handleCors() {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '*';
        
        header("Access-Control-Allow-Origin: $origin");
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS, PATCH');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Max-Age: 86400');
        
        http_response_code(200);
    }
}

/**
 * Request class
 */
class Request {
    public $params = [];
    public $query = [];
    public $body = [];
    public $headers = [];
    public $method;
    public $uri;
    
    public function __construct($params = []) {
        $this->params = $params;
        $this->query = $_GET;
        $this->method = $_SERVER['REQUEST_METHOD'];
        $this->uri = $_SERVER['REQUEST_URI'] ?? '/';
        $this->headers = $this->getAllHeaders();
        
        // Parse body
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        if (strpos($contentType, 'application/json') !== false) {
            $this->body = json_decode(file_get_contents('php://input'), true) ?? [];
        } elseif ($this->method === 'POST') {
            $this->body = $_POST;
        }
    }
    
    public function param($key, $default = null) {
        return $this->params[$key] ?? $default;
    }
    
    public function query($key, $default = null) {
        return $this->query[$key] ?? $default;
    }
    
    public function body($key, $default = null) {
        return $this->body[$key] ?? $default;
    }
    
    public function header($key, $default = null) {
        $key = strtolower($key);
        return $this->headers[$key] ?? $default;
    }
    
    public function all() {
        return array_merge($this->query, $this->body, $this->params);
    }
    
    private function getAllHeaders() {
        $headers = [];
        
        if (function_exists('getallheaders')) {
            foreach (getallheaders() as $name => $value) {
                $headers[strtolower($name)] = $value;
            }
        } else {
            foreach ($_SERVER as $name => $value) {
                if (substr($name, 0, 5) == 'HTTP_') {
                    $name = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))));
                    $headers[strtolower($name)] = $value;
                }
            }
        }
        
        return $headers;
    }
}

/**
 * Response class
 */
class Response {
    private $status = 200;
    private $headers = [];
    private $body = '';
    
    public function status($code) {
        $this->status = $code;
        return $this;
    }
    
    public function header($key, $value) {
        $this->headers[$key] = $value;
        return $this;
    }
    
    public function json($data) {
        $this->header('Content-Type', 'application/json');
        $this->body = json_encode($data);
        return $this;
    }
    
    public function text($text) {
        $this->header('Content-Type', 'text/plain');
        $this->body = $text;
        return $this;
    }
    
    public function html($html) {
        $this->header('Content-Type', 'text/html');
        $this->body = $html;
        return $this;
    }
    
    public function redirect($url, $status = 302) {
        $this->status = $status;
        $this->header('Location', $url);
        return $this;
    }
    
    public function send() {
        http_response_code($this->status);
        
        foreach ($this->headers as $key => $value) {
            header("$key: $value");
        }
        
        echo $this->body;
    }
}