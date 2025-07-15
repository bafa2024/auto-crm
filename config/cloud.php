<?php
// config/cloud.php
// Cloud platform-specific configuration

class CloudConfig {
    private static $platform = null;
    
    /**
     * Detect which cloud platform we're running on
     */
    public static function detectPlatform() {
        if (self::$platform !== null) {
            return self::$platform;
        }
        
        // AWS detection
        if (isset($_SERVER['AWS_EXECUTION_ENV']) || 
            isset($_SERVER['AWS_REGION']) || 
            file_exists('/opt/elasticbeanstalk')) {
            self::$platform = 'aws';
            return 'aws';
        }
        
        // Azure detection
        if (isset($_SERVER['WEBSITE_INSTANCE_ID']) || 
            isset($_SERVER['WEBSITE_SITE_NAME']) ||
            isset($_SERVER['AZURE_FUNCTIONS_ENVIRONMENT'])) {
            self::$platform = 'azure';
            return 'azure';
        }
        
        // Google Cloud detection
        if (isset($_SERVER['GAE_APPLICATION']) || 
            isset($_SERVER['GAE_RUNTIME']) ||
            isset($_SERVER['GOOGLE_CLOUD_PROJECT'])) {
            self::$platform = 'gcp';
            return 'gcp';
        }
        
        // Heroku detection
        if (isset($_SERVER['DYNO']) || isset($_SERVER['HEROKU_APP_NAME'])) {
            self::$platform = 'heroku';
            return 'heroku';
        }
        
        // Default to local/unknown
        self::$platform = 'local';
        return 'local';
    }
    
    /**
     * Get database configuration based on platform
     */
    public static function getDatabaseConfig() {
        $platform = self::detectPlatform();
        
        switch ($platform) {
            case 'aws':
                // AWS RDS configuration
                if (isset($_SERVER['RDS_HOSTNAME'])) {
                    return [
                        'host' => $_SERVER['RDS_HOSTNAME'],
                        'port' => $_SERVER['RDS_PORT'] ?? '3306',
                        'database' => $_SERVER['RDS_DB_NAME'],
                        'username' => $_SERVER['RDS_USERNAME'],
                        'password' => $_SERVER['RDS_PASSWORD']
                    ];
                }
                break;
                
            case 'azure':
                // Azure Database configuration
                if (isset($_SERVER['MYSQLCONNSTR_localdb'])) {
                    $connStr = $_SERVER['MYSQLCONNSTR_localdb'];
                    $config = self::parseAzureConnectionString($connStr);
                    return $config;
                }
                break;
                
            case 'gcp':
                // Google Cloud SQL configuration
                if (isset($_SERVER['DB_CONNECTION_NAME'])) {
                    return [
                        'host' => null, // Use Unix socket
                        'socket' => '/cloudsql/' . $_SERVER['DB_CONNECTION_NAME'],
                        'database' => $_SERVER['DB_NAME'] ?? 'autocrm',
                        'username' => $_SERVER['DB_USER'] ?? 'root',
                        'password' => $_SERVER['DB_PASS'] ?? ''
                    ];
                }
                break;
                
            case 'heroku':
                // Heroku ClearDB/JawsDB configuration
                if (isset($_SERVER['CLEARDB_DATABASE_URL'])) {
                    return self::parseDatabaseUrl($_SERVER['CLEARDB_DATABASE_URL']);
                } elseif (isset($_SERVER['JAWSDB_URL'])) {
                    return self::parseDatabaseUrl($_SERVER['JAWSDB_URL']);
                }
                break;
        }
        
        // Default configuration from environment variables
        return [
            'host' => $_ENV['DB_HOST'] ?? $_SERVER['DB_HOST'] ?? 'localhost',
            'port' => $_ENV['DB_PORT'] ?? $_SERVER['DB_PORT'] ?? '3306',
            'database' => $_ENV['DB_NAME'] ?? $_SERVER['DB_NAME'] ?? 'autocrm',
            'username' => $_ENV['DB_USER'] ?? $_SERVER['DB_USER'] ?? 'root',
            'password' => $_ENV['DB_PASS'] ?? $_SERVER['DB_PASS'] ?? ''
        ];
    }
    
    /**
     * Get storage configuration based on platform
     */
    public static function getStorageConfig() {
        $platform = self::detectPlatform();
        
        switch ($platform) {
            case 'aws':
                return [
                    'driver' => 's3',
                    'bucket' => $_ENV['AWS_BUCKET'] ?? 'autocrm-uploads',
                    'region' => $_ENV['AWS_DEFAULT_REGION'] ?? 'us-east-1',
                    'key' => $_ENV['AWS_ACCESS_KEY_ID'] ?? '',
                    'secret' => $_ENV['AWS_SECRET_ACCESS_KEY'] ?? ''
                ];
                
            case 'azure':
                return [
                    'driver' => 'azure',
                    'account' => $_ENV['AZURE_STORAGE_ACCOUNT'] ?? '',
                    'key' => $_ENV['AZURE_STORAGE_KEY'] ?? '',
                    'container' => $_ENV['AZURE_STORAGE_CONTAINER'] ?? 'uploads'
                ];
                
            case 'gcp':
                return [
                    'driver' => 'gcs',
                    'project_id' => $_ENV['GOOGLE_CLOUD_PROJECT'] ?? '',
                    'bucket' => $_ENV['GCS_BUCKET'] ?? 'autocrm-uploads',
                    'key_file' => $_ENV['GOOGLE_APPLICATION_CREDENTIALS'] ?? ''
                ];
                
            default:
                return [
                    'driver' => 'local',
                    'path' => __DIR__ . '/../uploads/'
                ];
        }
    }
    
    /**
     * Get cache configuration
     */
    public static function getCacheConfig() {
        $platform = self::detectPlatform();
        
        // Check for Redis first (common across platforms)
        if (isset($_ENV['REDIS_URL'])) {
            $redisConfig = self::parseRedisUrl($_ENV['REDIS_URL']);
            return array_merge(['driver' => 'redis'], $redisConfig);
        }
        
        switch ($platform) {
            case 'aws':
                if (isset($_ENV['ELASTICACHE_ENDPOINT'])) {
                    return [
                        'driver' => 'memcached',
                        'servers' => [[
                            'host' => $_ENV['ELASTICACHE_ENDPOINT'],
                            'port' => $_ENV['ELASTICACHE_PORT'] ?? 11211
                        ]]
                    ];
                }
                break;
                
            case 'azure':
                if (isset($_ENV['AZURE_CACHE_CONNECTION'])) {
                    return [
                        'driver' => 'redis',
                        'connection' => $_ENV['AZURE_CACHE_CONNECTION']
                    ];
                }
                break;
        }
        
        // Default to file cache
        return [
            'driver' => 'file',
            'path' => __DIR__ . '/../cache/'
        ];
    }
    
    /**
     * Get logging configuration
     */
    public static function getLoggingConfig() {
        $platform = self::detectPlatform();
        
        switch ($platform) {
            case 'aws':
                return [
                    'driver' => 'cloudwatch',
                    'group' => $_ENV['CLOUDWATCH_LOG_GROUP'] ?? '/aws/elasticbeanstalk/autocrm',
                    'stream' => $_ENV['CLOUDWATCH_LOG_STREAM'] ?? 'application'
                ];
                
            case 'azure':
                return [
                    'driver' => 'azure',
                    'workspace_id' => $_ENV['AZURE_LOG_WORKSPACE_ID'] ?? '',
                    'primary_key' => $_ENV['AZURE_LOG_PRIMARY_KEY'] ?? ''
                ];
                
            case 'gcp':
                return [
                    'driver' => 'stackdriver',
                    'project_id' => $_ENV['GOOGLE_CLOUD_PROJECT'] ?? ''
                ];
                
            default:
                return [
                    'driver' => 'file',
                    'path' => __DIR__ . '/../logs/app.log',
                    'level' => $_ENV['LOG_LEVEL'] ?? 'debug'
                ];
        }
    }
    
    /**
     * Parse database URL (for Heroku and similar)
     */
    private static function parseDatabaseUrl($url) {
        $parsed = parse_url($url);
        
        return [
            'host' => $parsed['host'] ?? 'localhost',
            'port' => $parsed['port'] ?? 3306,
            'database' => ltrim($parsed['path'] ?? '', '/'),
            'username' => $parsed['user'] ?? '',
            'password' => $parsed['pass'] ?? ''
        ];
    }
    
    /**
     * Parse Azure connection string
     */
    private static function parseAzureConnectionString($connStr) {
        $config = [];
        $parts = explode(';', $connStr);
        
        foreach ($parts as $part) {
            list($key, $value) = explode('=', $part, 2);
            switch (strtolower($key)) {
                case 'data source':
                case 'server':
                    list($config['host'], $config['port']) = explode(':', $value . ':3306');
                    break;
                case 'database':
                    $config['database'] = $value;
                    break;
                case 'user id':
                case 'uid':
                    $config['username'] = $value;
                    break;
                case 'password':
                case 'pwd':
                    $config['password'] = $value;
                    break;
            }
        }
        
        return $config;
    }
    
    /**
     * Parse Redis URL
     */
    private static function parseRedisUrl($url) {
        $parsed = parse_url($url);
        
        return [
            'host' => $parsed['host'] ?? 'localhost',
            'port' => $parsed['port'] ?? 6379,
            'password' => $parsed['pass'] ?? null,
            'database' => isset($parsed['path']) ? intval(ltrim($parsed['path'], '/')) : 0
        ];
    }
    
    /**
     * Get application URL based on environment
     */
    public static function getAppUrl() {
        // Check environment variables first
        if (isset($_ENV['APP_URL'])) {
            return rtrim($_ENV['APP_URL'], '/');
        }
        
        // Detect from server variables
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        
        return $protocol . '://' . $host;
    }
}