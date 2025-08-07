<?php
/**
 * ACRM Campaign Scheduling - Rollback Script
 * Safe rollback for the new scheduling system
 * 
 * Usage: php rollback.php [--confirm]
 */

require_once 'config/database.php';

class RollbackRunner {
    private $db;
    private $isConfirmed = false;
    
    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
        
        // Parse command line arguments
        global $argv;
        if (in_array('--confirm', $argv ?? [])) {
            $this->isConfirmed = true;
        }
    }
    
    public function run() {
        echo "🔄 ACRM Campaign Scheduling - Rollback Script\n";
        echo "=============================================\n\n";
        
        if (!$this->db) {
            echo "❌ Cannot connect to database\n";
            exit(1);
        }
        
        echo "⚠️  WARNING: This will remove the new scheduling system!\n";
        echo "This includes:\n";
        echo "- scheduled_campaigns table\n";
        echo "- schedule_log table\n";
        echo "- migrations table\n";
        echo "- All scheduled campaign data will be lost!\n\n";
        
        if (!$this->isConfirmed) {
            echo "Type 'ROLLBACK' to confirm: ";
            $handle = fopen("php://stdin", "r");
            $line = fgets($handle);
            fclose($handle);
            
            if (trim($line) !== 'ROLLBACK') {
                echo "❌ Rollback cancelled\n";
                exit(0);
            }
        }
        
        try {
            echo "🗑️  Starting rollback...\n\n";
            
            // Check what tables exist before rollback
            $this->checkExistingTables();
            
            // Drop tables in correct order (respect foreign keys)
            $this->dropTable('schedule_log');
            $this->dropTable('scheduled_campaigns');
            $this->dropTable('migrations');
            
            echo "\n✅ Database rollback completed successfully!\n\n";
            
            $this->printCleanupInstructions();
            
        } catch (Exception $e) {
            echo "❌ Rollback failed: " . $e->getMessage() . "\n";
            exit(1);
        }
    }
    
    private function checkExistingTables() {
        echo "📋 Checking existing tables...\n";
        
        $tables = ['scheduled_campaigns', 'schedule_log', 'migrations'];
        $existingTables = [];
        
        foreach ($tables as $table) {
            try {
                $stmt = $this->db->query("SHOW TABLES LIKE '$table'");
                if ($stmt->rowCount() > 0) {
                    $existingTables[] = $table;
                    echo "   ✅ Found: $table\n";
                } else {
                    echo "   ⚪ Not found: $table\n";
                }
            } catch (Exception $e) {
                echo "   ❌ Error checking $table: " . $e->getMessage() . "\n";
            }
        }
        
        if (empty($existingTables)) {
            echo "\n⚠️  No scheduling tables found - nothing to rollback\n";
            exit(0);
        }
        
        echo "\n";
    }
    
    private function dropTable($tableName) {
        try {
            // Check if table exists first
            $stmt = $this->db->query("SHOW TABLES LIKE '$tableName'");
            if ($stmt->rowCount() === 0) {
                echo "⚪ Table '$tableName' doesn't exist - skipping\n";
                return;
            }
            
            // Get row count before dropping
            $countStmt = $this->db->query("SELECT COUNT(*) as count FROM $tableName");
            $rowCount = $countStmt->fetch(PDO::FETCH_ASSOC)['count'];
            
            // Drop the table
            $this->db->exec("DROP TABLE $tableName");
            
            echo "🗑️  Dropped table '$tableName' ($rowCount rows)\n";
            
        } catch (Exception $e) {
            echo "❌ Failed to drop table '$tableName': " . $e->getMessage() . "\n";
            throw $e;
        }
    }
    
    private function printCleanupInstructions() {
        echo "📋 Manual Cleanup Instructions:\n";
        echo "===============================\n\n";
        
        echo "1. Remove new files from your server:\n";
        echo "   rm -f services/SimpleCampaignScheduler.php\n";
        echo "   rm -f api/schedule_campaign.php\n";
        echo "   rm -f process_scheduled_campaigns.php\n";
        echo "   rm -f scheduled_campaigns.php\n";
        echo "   rm -rf database/migrations/\n";
        echo "   rm -f migrate.php\n";
        echo "   rm -f rollback.php\n\n";
        
        echo "2. Remove cron job:\n";
        echo "   crontab -e\n";
        echo "   # Remove the line with process_scheduled_campaigns.php\n\n";
        
        echo "3. Restore original campaigns.php if needed:\n";
        echo "   # Replace with your backup version\n\n";
        
        echo "4. Optional - Remove logs directory:\n";
        echo "   rm -rf logs/\n\n";
        
        echo "5. Verify system is working:\n";
        echo "   # Test your original campaign functionality\n\n";
        
        echo "✅ The system has been rolled back to the state before\n";
        echo "   the scheduling system was installed.\n";
    }
}

// Command line entry point
if (php_sapi_name() === 'cli') {
    $runner = new RollbackRunner();
    $runner->run();
} else {
    echo "This script must be run from the command line.\n";
    echo "Usage: php rollback.php [--confirm]\n";
    exit(1);
}
?>
