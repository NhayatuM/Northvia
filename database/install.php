<?php
/**
 * NorthVia Database Installation Script
 * Run this script to install/reinstall the complete database schema
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;

echo "=== NorthVia Database Installation ===\n\n";

try {
    // Load environment variables
    $dotenv = Dotenv::createImmutable(dirname(__DIR__));
    if (file_exists(dirname(__DIR__) . '/.env')) {
        $dotenv->load();
    } else {
        echo "❌ Error: .env file not found\n";
        exit(1);
    }

    // Database configuration
    $host = $_ENV['DB_HOST'] ?? '127.0.0.1';
    $port = $_ENV['DB_PORT'] ?? 3306;
    $database = $_ENV['DB_DATABASE'] ?? 'northvia';
    $username = $_ENV['DB_USERNAME'] ?? 'root';
    $password = $_ENV['DB_PASSWORD'] ?? '';

    echo "📊 Database Configuration:\n";
    echo "   Host: {$host}:{$port}\n";
    echo "   Database: {$database}\n";
    echo "   Username: {$username}\n\n";

    // Ask for confirmation
    echo "⚠️  WARNING: This will DROP the existing '{$database}' database if it exists!\n";
    echo "Do you want to continue? (yes/no): ";
    $confirmation = trim(fgets(STDIN));
    
    if (strtolower($confirmation) !== 'yes') {
        echo "❌ Installation cancelled.\n";
        exit(0);
    }

    echo "\n🔄 Starting database installation...\n\n";

    // Connect to MySQL server (without database)
    $dsn = "mysql:host={$host};port={$port};charset=utf8mb4";
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);

    // Drop existing database if it exists
    echo "🗑️  Dropping existing database (if exists)...\n";
    $pdo->exec("DROP DATABASE IF EXISTS `{$database}`");

    // Create new database
    echo "🏗️  Creating new database...\n";
    $pdo->exec("CREATE DATABASE `{$database}` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");

    // Use the new database
    $pdo->exec("USE `{$database}`");

    // Read and execute the schema file
    echo "📋 Reading schema file...\n";
    $schemaFile = __DIR__ . '/northvia_complete_schema.sql';
    
    if (!file_exists($schemaFile)) {
        throw new Exception("Schema file not found: {$schemaFile}");
    }

    $sql = file_get_contents($schemaFile);
    
    if ($sql === false) {
        throw new Exception("Failed to read schema file");
    }

    echo "⚡ Executing schema...\n";
    
    // Split SQL statements and execute them
    $statements = array_filter(
        array_map('trim', explode(';', $sql)),
        function($stmt) {
            return !empty($stmt) && 
                   !preg_match('/^(\/\*|--|SET|START|COMMIT)/', $stmt) &&
                   strpos($stmt, '/*!') !== 0;
        }
    );

    $executed = 0;
    $errors = 0;

    foreach ($statements as $statement) {
        try {
            $pdo->exec($statement);
            $executed++;
            
            // Show progress for major operations
            if (preg_match('/^(CREATE TABLE|INSERT INTO|ALTER TABLE)\s+`?(\w+)`?/i', $statement, $matches)) {
                $operation = strtoupper($matches[1]);
                $table = $matches[2] ?? '';
                echo "   ✅ {$operation} {$table}\n";
            }
            
        } catch (PDOException $e) {
            $errors++;
            echo "   ❌ Error executing statement: " . $e->getMessage() . "\n";
            echo "      Statement: " . substr($statement, 0, 100) . "...\n";
        }
    }

    echo "\n📊 Installation Summary:\n";
    echo "   ✅ Statements executed: {$executed}\n";
    echo "   ❌ Errors: {$errors}\n";

    if ($errors > 0) {
        echo "   ⚠️  Some errors occurred. Please check the output above.\n";
    }

    // Verify installation
    echo "\n🔍 Verifying installation...\n";
    
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    $tableCount = count($tables);
    
    echo "   📋 Tables created: {$tableCount}\n";
    
    // Check some key tables
    $keyTables = ['users', 'admin_users', 'vendors', 'products', 'orders', 'categories', 'brands'];
    $missingTables = [];
    
    foreach ($keyTables as $table) {
        if (in_array($table, $tables)) {
            echo "   ✅ {$table}\n";
        } else {
            echo "   ❌ {$table} (MISSING)\n";
            $missingTables[] = $table;
        }
    }

    // Check sample data
    echo "\n📝 Checking sample data...\n";
    
    $userCount = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $adminCount = $pdo->query("SELECT COUNT(*) FROM admin_users")->fetchColumn();
    $categoryCount = $pdo->query("SELECT COUNT(*) FROM categories")->fetchColumn();
    $brandCount = $pdo->query("SELECT COUNT(*) FROM brands")->fetchColumn();
    $currencyCount = $pdo->query("SELECT COUNT(*) FROM currencies")->fetchColumn();
    
    echo "   👥 Users: {$userCount}\n";
    echo "   🔐 Admin Users: {$adminCount}\n";
    echo "   📂 Categories: {$categoryCount}\n";
    echo "   🏷️  Brands: {$brandCount}\n";
    echo "   💰 Currencies: {$currencyCount}\n";

    if (empty($missingTables) && $errors === 0) {
        echo "\n🎉 Database installation completed successfully!\n\n";
        
        echo "🔑 Default Admin Accounts:\n";
        echo "   Super Admin: admin@northvia.com / password123\n";
        echo "   Admin: manager@northvia.com / password123\n";
        echo "   Support: support@northvia.com / password123\n\n";
        
        echo "🧪 Demo Accounts:\n";
        echo "   Customer: customer@demo.com / password123\n";
        echo "   Vendor: vendor@demo.com / password123\n\n";
        
        echo "✅ You can now start using the NorthVia platform!\n";
        
    } else {
        echo "\n⚠️  Installation completed with issues:\n";
        if (!empty($missingTables)) {
            echo "   - Missing tables: " . implode(', ', $missingTables) . "\n";
        }
        if ($errors > 0) {
            echo "   - {$errors} SQL errors occurred\n";
        }
        echo "\nPlease check the errors above and fix them manually.\n";
    }

} catch (Exception $e) {
    echo "\n❌ Installation failed: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
?>