<?php
/**
 * Version Management System
 * This file tracks the current version and deployment information
 */

class VersionManager {
    private static $version = '3.1.0';
    private static $buildDate = '2025-01-20';
    private static $commitHash = '';
    private static $deploymentDate = '';
    
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
     * Get deployment date
     */
    public static function getDeploymentDate() {
        if (empty(self::$deploymentDate)) {
            // Try to get from file, or use current time
            $deploymentFile = __DIR__ . '/deployment.txt';
            if (file_exists($deploymentFile)) {
                self::$deploymentDate = file_get_contents($deploymentFile);
            } else {
                self::$deploymentDate = date('Y-m-d H:i:s');
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
        $deploymentFile = __DIR__ . '/deployment.txt';
        $currentDate = date('Y-m-d H:i:s');
        file_put_contents($deploymentFile, $currentDate);
        self::$deploymentDate = $currentDate;
        return $currentDate;
    }
    
    /**
     * Get version badge HTML
     */
    public static function getVersionBadge() {
        $env = self::getEnvironment();
        $version = self::getVersion();
        $deploymentDate = self::getDeploymentDate();
        $commitHash = self::getCommitHash();
        
        $envClass = $env === 'live' ? 'bg-success' : ($env === 'local' ? 'bg-warning' : 'bg-info');
        $envText = ucfirst($env);
        
        return "
        <div class='version-badge d-flex align-items-center gap-2'>
            <span class='badge {$envClass}'>v{$version}</span>
            <span class='badge bg-secondary'>{$envText}</span>
            <small class='text-muted d-none d-md-inline'>Deployed: {$deploymentDate}</small>
            " . ($commitHash ? "<small class='text-muted d-none d-lg-inline'>Commit: {$commitHash}</small>" : "") . "
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