<?php
/**
 * ACRM Database Migration Runner
 * Production-safe migration system for ACRM Campaign Scheduling
 * 
 * Usage:
 * php migrate.php [--dry-run] [--force] [--migration=001]
 */

require_once 'config/database.php';

class MigrationRunner {
    private $db;
    private $isDryRun = false;
    private $isForce = false;
    private $specificMigration = null;
    
    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
        
        // Parse command line arguments
        $this->parseArguments();
    }
    
    private function parseArguments() {
        global $argv;
        if (!isset($argv)) return;
        
        foreach ($argv as $arg) {
            if ($arg === '--dry-run') {
                $this->isDryRun = true;
            } elseif ($arg === '--force') {
                $this->isForce = true;
            } elseif (strpos($arg, '--migration=') === 0) {
                $this->specificMigration = substr($arg, 12);
            }
        }
    }
    
    /**
     * Run database migrations
     */
    public function run() {
        echo "🚀 ACRM Database Migration Runner\n";
        echo "================================\n\n";
        
        if ($this->isDryRun) {
            echo "⚠️  DRY RUN MODE - No changes will be made\n\n";
        }
        
        try {
            // Check if we can connect to database
            if (!$this->db) {
                throw new Exception("Cannot connect to database");
            }
            
            echo "✅ Database connection successful\n";
            
            // Create migrations table if it doesn't exist
            $this->createMigrationsTable();
            
            // Get list of migrations to run
            $migrations = $this->getMigrationsToRun();
            
            if (empty($migrations)) {
                echo "✅ No migrations to run - database is up to date\n";
                return;
            }
            
            echo "📋 Found " . count($migrations) . " migration(s) to run:\n";
            foreach ($migrations as $migration) {
                echo "   - $migration\n";
            }
            echo "\n";
            
            if (!$this->isDryRun && !$this->isForce) {
                echo "⚠️  This will modify your database. Continue? (y/N): ";
                $handle = fopen("php://stdin", "r");
                $line = fgets($handle);
                fclose($handle);
                
                if (strtolower(trim($line)) !== 'y') {
                    echo "❌ Migration cancelled\n";
                    return;
                }
            }
            
            // Run each migration
            foreach ($migrations as $migration) {
                $this->runMigration($migration);
            }
            
            echo "\n🎉 All migrations completed successfully!\n";
            $this->printNextSteps();
            
        } catch (Exception $e) {
            echo "❌ Migration failed: " . $e->getMessage() . "\n";
            echo "Stack trace: " . $e->getTraceAsString() . "\n";
            exit(1);
        }
    }
    
    /**
     * Create migrations tracking table
     */
    private function createMigrationsTable() {
        $sql = "CREATE TABLE IF NOT EXISTS migrations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            migration VARCHAR(255) NOT NULL UNIQUE,
            executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_migration (migration)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        
        if (!$this->isDryRun) {
            $this->db->exec($sql);
        }
        
        echo "✅ Migration tracking table ready\n";
    }
    
    /**
     * Get list of migrations that need to be run
     */
    private function getMigrationsToRun() {
        // Get executed migrations from database
        $executedMigrations = [];
        if (!$this->isDryRun) {
            try {
                $stmt = $this->db->query("SELECT migration FROM migrations");
                $executedMigrations = $stmt->fetchAll(PDO::FETCH_COLUMN);
            } catch (Exception $e) {
                // Table might not exist yet
                $executedMigrations = [];
            }
        }
        
        // Get available migration files
        $migrationDir = __DIR__ . '/database/migrations/';
        $availableMigrations = [];
        
        if (is_dir($migrationDir)) {
            $files = scandir($migrationDir);
            foreach ($files as $file) {
                if (preg_match('/^\d{3}_.*\.sql$/', $file)) {
                    $migrationName = pathinfo($file, PATHINFO_FILENAME);
                    $availableMigrations[] = $migrationName;
                }
            }
        }
        
        sort($availableMigrations);
        
        // Filter by specific migration if requested
        if ($this->specificMigration) {
            $availableMigrations = array_filter($availableMigrations, function($migration) {
                return strpos($migration, $this->specificMigration) === 0;
            });
        }
        
        // Return migrations that haven't been executed
        return array_diff($availableMigrations, $executedMigrations);
    }
    
    /**
     * Run a specific migration
     */
    private function runMigration($migrationName) {
        echo "🔄 Running migration: $migrationName\n";
        
        $migrationFile = __DIR__ . "/database/migrations/$migrationName.sql";
        
        if (!file_exists($migrationFile)) {
            throw new Exception("Migration file not found: $migrationFile");
        }
        
        $sql = file_get_contents($migrationFile);
        
        if ($this->isDryRun) {
            echo "   📄 Would execute SQL from: $migrationFile\n";
            echo "   📊 SQL size: " . number_format(strlen($sql)) . " bytes\n";
            return;
        }
        
        // Begin transaction
        $this->db->beginTransaction();
        
        try {
            // Split SQL into individual statements
            $statements = $this->splitSqlStatements($sql);
            $executedStatements = 0;
            
            foreach ($statements as $statement) {
                $statement = trim($statement);
                if (empty($statement) || strpos($statement, '--') === 0) {
                    continue; // Skip empty lines and comments
                }
                
                try {
                    $this->db->exec($statement);
                    $executedStatements++;
                } catch (Exception $e) {
                    // Some statements might fail if tables/columns already exist
                    // This is okay for CREATE TABLE IF NOT EXISTS, etc.
                    if (strpos($e->getMessage(), 'already exists') === false && 
                        strpos($e->getMessage(), 'Duplicate') === false) {
                        throw $e;
                    }
                }
            }
            
            // Record successful migration
            $stmt = $this->db->prepare("INSERT INTO migrations (migration) VALUES (?)");
            $stmt->execute([$migrationName]);
            
            // Commit transaction
            $this->db->commit();
            
            echo "   ✅ Migration completed ($executedStatements statements executed)\n";
            
        } catch (Exception $e) {
            // Rollback transaction
            $this->db->rollback();
            throw new Exception("Migration '$migrationName' failed: " . $e->getMessage());
        }
    }
    
    /**
     * Split SQL file into individual statements
     */
    private function splitSqlStatements($sql) {
        // Remove SQL comments
        $sql = preg_replace('/--.*$/m', '', $sql);
        
        // Split by semicolon, but be careful about semicolons in strings
        $statements = [];
        $current = '';
        $inString = false;
        $stringChar = '';
        
        for ($i = 0; $i < strlen($sql); $i++) {
            $char = $sql[$i];
            
            if (!$inString && ($char === '"' || $char === "'")) {
                $inString = true;
                $stringChar = $char;
            } elseif ($inString && $char === $stringChar && $sql[$i-1] !== '\\') {
                $inString = false;
            } elseif (!$inString && $char === ';') {
                $statements[] = $current;
                $current = '';
                continue;
            }
            
            $current .= $char;
        }
        
        if (!empty(trim($current))) {
            $statements[] = $current;
        }
        
        return $statements;
    }
    
    /**
     * Print next steps after migration
     */
    private function printNextSteps() {
        echo "\n📋 Next Steps:\n";
        echo "=============\n";
        echo "1. Create logs directory:\n";
        echo "   mkdir -p logs && chmod 755 logs\n\n";
        echo "2. Set up cron job for scheduled campaigns:\n";
        echo "   */5 * * * * php " . __DIR__ . "/process_scheduled_campaigns.php\n\n";
        echo "3. Test the scheduling system:\n";
        echo "   php test_simple_scheduler.php\n\n";
        echo "4. Access the web interface:\n";
        echo "   - Campaign management: campaigns.php\n";
        echo "   - Scheduled campaigns: scheduled_campaigns.php\n\n";
        echo "5. Monitor logs:\n";
        echo "   tail -f logs/email_log.txt\n\n";
    }
    
    /**
     * Show help information
     */
    public static function showHelp() {
        echo "ACRM Database Migration Runner\n";
        echo "==============================\n\n";
        echo "Usage: php migrate.php [options]\n\n";
        echo "Options:\n";
        echo "  --dry-run           Show what would be done without making changes\n";
        echo "  --force             Skip confirmation prompts\n";
        echo "  --migration=001     Run only a specific migration\n";
        echo "  --help              Show this help message\n\n";
        echo "Examples:\n";
        echo "  php migrate.php                    # Run all pending migrations\n";
        echo "  php migrate.php --dry-run          # See what migrations would run\n";
        echo "  php migrate.php --force            # Run without confirmation\n";
        echo "  php migrate.php --migration=001    # Run only migration 001\n\n";
    }
}

// Command line entry point
if (php_sapi_name() === 'cli') {
    // Check for help flag
    if (in_array('--help', $argv ?? [])) {
        MigrationRunner::showHelp();
        exit(0);
    }
    
    $runner = new MigrationRunner();
    $runner->run();
} else {
    echo "This script must be run from the command line.\n";
    echo "Usage: php migrate.php [--dry-run] [--force] [--migration=001]\n";
    exit(1);
}
?>
