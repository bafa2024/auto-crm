<?php
/**
 * Version Management System
 * This file tracks the current version and deployment information
 */

class VersionManager {
    private static $version = '3.1.1';
    private static $buildDate = '2025-01-21';
    private static $commitHash = '';
    private static $deploymentDate = '';
    private static $timezone = 'America/New_York'; // Default timezone
    
    /**
     * Initialize timezone
     */
    public static function initializeTimezone() {
        // Try to get timezone from config or use default
        $configFile = __DIR__ . '/config/config.php';
        if (file_exists($configFile)) {
            require_once $configFile;
            if (defined('APP_TIMEZONE')) {
                self::$timezone = APP_TIMEZONE;
            }
        }
        date_default_timezone_set(self::$timezone);
    }
    
    /**
     * Get current version
     */
    public static function getVersion() {
        return self::$version;
    }
    
    /**
     * Get build date
     */
    public static function getBuildDate() {
        return self::$buildDate;
    }
    
    /**
     * Get deployment date with timezone
     */
    public static function getDeploymentDate($format = 'Y-m-d H:i:s T') {
        self::initializeTimezone();
        if (empty(self::$deploymentDate)) {
            // Try to get from file, or use current time
            $deploymentFile = __DIR__ . '/deployment.txt';
            if (file_exists($deploymentFile)) {
                $timestamp = filemtime($deploymentFile);
                self::$deploymentDate = date($format, $timestamp);
            } else {
                self::$deploymentDate = date($format);
            }
        }
        return self::$deploymentDate;
    }
    
    /**
     * Get commit hash
     */
    public static function getCommitHash() {
        if (empty(self::$commitHash)) {
            // Try to get from git
            $gitDir = __DIR__ . '/.git';
            if (is_dir($gitDir)) {
                $headFile = $gitDir . '/HEAD';
                if (file_exists($headFile)) {
                    $head = trim(file_get_contents($headFile));
                    if (strpos($head, 'ref:') === 0) {
                        $ref = trim(substr($head, 5));
                        $refFile = $gitDir . '/' . $ref;
                        if (file_exists($refFile)) {
                            self::$commitHash = substr(trim(file_get_contents($refFile)), 0, 8);
                        }
                    } else {
                        self::$commitHash = substr($head, 0, 8);
                    }
                }
            }
        }
        return self::$commitHash;
    }
    
    /**
     * Get full version info
     */
    public static function getVersionInfo() {
        return [
            'version' => self::getVersion(),
            'build_date' => self::getBuildDate(),
            'deployment_date' => self::getDeploymentDate(),
            'commit_hash' => self::getCommitHash(),
            'environment' => self::getEnvironment()
        ];
    }
    
    /**
     * Get environment
     */
    public static function getEnvironment() {
        $host = $_SERVER['HTTP_HOST'] ?? '';
        if (strpos($host, 'localhost') !== false || strpos($host, '127.0.0.1') !== false) {
            return 'local';
        } elseif (strpos($host, 'regrowup.ca') !== false) {
            return 'live';
        } else {
            return 'development';
        }
    }
    
    /**
     * Update deployment date
     */
    public static function updateDeploymentDate() {
        self::initializeTimezone();
        $deploymentFile = __DIR__ . '/deployment.txt';
        $currentDate = date('Y-m-d H:i:s T');
        file_put_contents($deploymentFile, $currentDate);
        self::$deploymentDate = $currentDate;
        
        // Also update version info file for deployment verification
        $versionInfo = [
            'version' => self::$version,
            'build_date' => self::$buildDate,
            'deployment_date' => $currentDate,
            'commit_hash' => self::getCommitHash(),
            'environment' => self::getEnvironment(),
            'timezone' => self::$timezone,
            'server_time' => date('Y-m-d H:i:s T'),
            'timestamp' => time()
        ];
        file_put_contents(__DIR__ . '/version-info.json', json_encode($versionInfo, JSON_PRETTY_PRINT));
        
        return $currentDate;
    }
    
    /**
     * Get version badge HTML
     */
    public static function getVersionBadge() {
        self::initializeTimezone();
        $env = self::getEnvironment();
        $version = self::getVersion();
        $deploymentDate = self::getDeploymentDate('M d, Y H:i');
        $commitHash = self::getCommitHash();
        
        $envClass = $env === 'live' ? 'bg-success' : ($env === 'local' ? 'bg-warning' : 'bg-info');
        $envText = ucfirst($env);
        
        // Get current server time for display
        $currentTime = date('H:i T');
        
        return "
        <div class='version-badge d-flex align-items-center gap-2'>
            <span class='badge {$envClass}'>v{$version}</span>
            <span class='badge bg-secondary'>{$envText}</span>
            <small class='text-muted d-none d-md-inline' title='Deployment Date'>Deployed: {$deploymentDate}</small>
            " . ($commitHash ? "<small class='text-muted d-none d-lg-inline' title='Git Commit'>#{$commitHash}</small>" : "") . "
            <small class='text-muted d-none d-xl-inline' title='Server Time'>{$currentTime}</small>
        </div>";
    }
    
    /**
     * Get version info for API
     */
    public static function getApiVersionInfo() {
        return [
            'success' => true,
            'data' => self::getVersionInfo()
        ];
    }
}

// Auto-update deployment date if this file is accessed directly
if (basename($_SERVER['SCRIPT_NAME'] ?? '') === 'version.php') {
    VersionManager::updateDeploymentDate();
}
?> 