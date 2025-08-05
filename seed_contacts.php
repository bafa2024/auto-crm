<?php
// Simple contact seeder - run from project root
echo "🌱 Starting contact seeding process...\n";

// Database configuration (adjust these values for your setup)
$host = 'localhost';
$dbname = 'autocrm'; // or your database name
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "✅ Database connection established successfully.\n";
} catch (PDOException $e) {
    die("❌ Database connection failed: " . $e->getMessage() . "\n");
}

// Sample data arrays
$firstNames = [
    'John', 'Jane', 'Michael', 'Sarah', 'David', 'Lisa', 'Robert', 'Jennifer', 
    'William', 'Mary', 'Richard', 'Linda', 'Joseph', 'Patricia', 'Thomas', 'Barbara',
    'Christopher', 'Elizabeth', 'Charles', 'Jessica', 'Daniel', 'Sarah', 'Matthew', 'Karen',
    'Anthony', 'Nancy', 'Mark', 'Betty', 'Donald', 'Helen', 'Steven', 'Sandra', 'Paul',
    'Donna', 'Andrew', 'Carol', 'Joshua', 'Ruth', 'Kenneth', 'Sharon', 'Kevin', 'Michelle',
    'Brian', 'Laura', 'George', 'Emily', 'Edward', 'Kimberly', 'Ronald', 'Deborah'
];

$lastNames = [
    'Smith', 'Johnson', 'Williams', 'Brown', 'Jones', 'Garcia', 'Miller', 'Davis',
    'Rodriguez', 'Martinez', 'Hernandez', 'Lopez', 'Gonzalez', 'Wilson', 'Anderson',
    'Thomas', 'Taylor', 'Moore', 'Jackson', 'Martin', 'Lee', 'Perez', 'Thompson',
    'White', 'Harris', 'Sanchez', 'Clark', 'Ramirez', 'Lewis', 'Robinson', 'Walker',
    'Young', 'Allen', 'King', 'Wright', 'Scott', 'Torres', 'Nguyen', 'Hill', 'Flores',
    'Green', 'Adams', 'Nelson', 'Baker', 'Hall', 'Rivera', 'Campbell', 'Mitchell',
    'Carter', 'Roberts'
];

$companies = [
    'ABC Trucking Co', 'XYZ Logistics', 'Fast Freight Solutions', 'Reliable Transport',
    'Express Delivery Inc', 'Premium Cargo Services', 'Swift Shipping Co', 'Trusted Haulers',
    'Elite Transport Group', 'Professional Logistics', 'Quick Ship Express', 'Dependable Freight',
    'Superior Transport', 'Prime Cargo Solutions', 'Excellence Logistics', 'Quality Hauling Co',
    'Reliable Express', 'Swift Solutions', 'Premium Transport', 'Elite Freight Services',
    'Professional Haulers', 'Trusted Transport', 'Express Logistics', 'Quality Shipping',
    'Superior Hauling', 'Prime Express', 'Excellence Cargo', 'Reliable Solutions',
    'Swift Transport', 'Premium Logistics', 'Elite Shipping', 'Professional Express',
    'Trusted Solutions', 'Express Hauling', 'Quality Transport', 'Superior Logistics',
    'Prime Shipping', 'Excellence Express', 'Reliable Cargo', 'Swift Hauling',
    'Premium Solutions', 'Elite Transport', 'Professional Shipping', 'Trusted Logistics',
    'Express Solutions', 'Quality Hauling', 'Superior Shipping', 'Prime Transport',
    'Excellence Solutions', 'Reliable Hauling', 'Swift Shipping'
];

$domains = [
    'gmail.com', 'yahoo.com', 'hotmail.com', 'outlook.com', 'company.com',
    'business.net', 'corporate.org', 'enterprise.com', 'professional.net'
];

// Check if table exists
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'email_recipients'");
    if ($stmt->rowCount() == 0) {
        echo "❌ Table 'email_recipients' does not exist. Creating table...\n";
        
        $createTableSQL = "
        CREATE TABLE email_recipients (
            id INT AUTO_INCREMENT PRIMARY KEY,
            campaign_id INT NULL,
            email VARCHAR(255) NOT NULL,
            name VARCHAR(255) NOT NULL,
            company VARCHAR(255),
            error_message TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            dot VARCHAR(50)
        )";
        
        $pdo->exec($createTableSQL);
        echo "✅ Table 'email_recipients' created successfully.\n";
    } else {
        echo "✅ Table 'email_recipients' exists.\n";
    }
} catch (Exception $e) {
    die("❌ Error checking/creating table: " . $e->getMessage() . "\n");
}

// Check if email_campaigns table exists and create a dummy campaign if needed
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'email_campaigns'");
    if ($stmt->rowCount() == 0) {
        echo "❌ Table 'email_campaigns' does not exist. Creating table and dummy campaign...\n";
        
        $createCampaignsTableSQL = "
        CREATE TABLE email_campaigns (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            subject VARCHAR(255),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )";
        
        $pdo->exec($createCampaignsTableSQL);
        echo "✅ Table 'email_campaigns' created successfully.\n";
        
        // Create a dummy campaign
        $stmt = $pdo->prepare("INSERT INTO email_campaigns (name, subject) VALUES (?, ?)");
        $stmt->execute(['Sample Campaign', 'Sample Subject']);
        $dummyCampaignId = $pdo->lastInsertId();
        echo "✅ Created dummy campaign with ID: $dummyCampaignId\n";
    } else {
        // Check if there are any campaigns
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM email_campaigns");
        $campaignCount = $stmt->fetch()['count'];
        
        if ($campaignCount == 0) {
            echo "⚠️ No campaigns exist. Creating a dummy campaign...\n";
            $stmt = $pdo->prepare("INSERT INTO email_campaigns (name, subject) VALUES (?, ?)");
            $stmt->execute(['Sample Campaign', 'Sample Subject']);
            $dummyCampaignId = $pdo->lastInsertId();
            echo "✅ Created dummy campaign with ID: $dummyCampaignId\n";
        } else {
            // Get the first campaign ID
            $stmt = $pdo->query("SELECT id FROM email_campaigns LIMIT 1");
            $dummyCampaignId = $stmt->fetch()['id'];
            echo "✅ Using existing campaign with ID: $dummyCampaignId\n";
        }
    }
} catch (Exception $e) {
    die("❌ Error with campaigns table: " . $e->getMessage() . "\n");
}

// Check if contacts already exist
$stmt = $pdo->query("SELECT COUNT(*) as count FROM email_recipients");
$existingCount = $stmt->fetch()['count'];

if ($existingCount > 0) {
    echo "⚠️ Found $existingCount existing contacts. Adding more sample data...\n";
} else {
    echo "📝 No existing contacts found. Creating 50 sample contacts...\n";
}

// Generate and insert 50 sample contacts
$insertedCount = 0;
$errors = [];

for ($i = 1; $i <= 50; $i++) {
    try {
        // Generate random data
        $firstName = $firstNames[array_rand($firstNames)];
        $lastName = $lastNames[array_rand($lastNames)];
        $fullName = $firstName . ' ' . $lastName;
        
        $company = $companies[array_rand($companies)];
        $domain = $domains[array_rand($domains)];
        $email = strtolower($firstName . '.' . $lastName . '@' . $domain);
        
        // Generate DOT number (6 digits)
        $dot = str_pad(rand(100000, 999999), 6, '0', STR_PAD_LEFT);
        
        // Insert contact with campaign_id
        $stmt = $pdo->prepare("
            INSERT INTO email_recipients (name, email, company, dot, campaign_id, created_at) 
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([
            $fullName,
            $email,
            $company,
            $dot,
            $dummyCampaignId
        ]);
        
        $insertedCount++;
        
        // Progress indicator
        if ($i % 10 == 0) {
            echo "📊 Progress: $i/50 contacts processed...\n";
        }
        
    } catch (Exception $e) {
        $errors[] = "Contact $i: " . $e->getMessage();
    }
}

// Final summary
echo "\n🎉 Contact seeding completed!\n";
echo "✅ Successfully inserted: $insertedCount contacts\n";

if (!empty($errors)) {
    echo "⚠️ Errors encountered:\n";
    foreach ($errors as $error) {
        echo "   - $error\n";
    }
}

// Get final count
$stmt = $pdo->query("SELECT COUNT(*) as total FROM email_recipients");
$totalCount = $stmt->fetch()['total'];

echo "📊 Total contacts in database: $totalCount\n";

// Show sample of inserted data
echo "\n📋 Sample of inserted contacts:\n";
$stmt = $pdo->query("SELECT name, email, company FROM email_recipients ORDER BY created_at DESC LIMIT 5");
$sampleContacts = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($sampleContacts as $contact) {
    echo "   - {$contact['name']} ({$contact['email']}) - {$contact['company']}\n";
}

echo "\n✅ Database seeding completed successfully!\n";
echo "🌐 You can now test the contacts API at: /api/contacts_api.php?action=list_all\n";
?> 