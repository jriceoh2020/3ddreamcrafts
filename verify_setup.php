<?php
/**
 * Setup verification script for 3DDreamCrafts website
 * Checks that all required directories and files are in place
 */

echo "3DDreamCrafts Setup Verification\n";
echo "===============================\n\n";

$errors = [];
$warnings = [];

// Required directories
$requiredDirs = [
    'public',
    'public/assets',
    'public/assets/css',
    'public/assets/js',
    'public/assets/images',
    'public/uploads',
    'admin',
    'admin/manage',
    'admin/settings',
    'includes',
    'database'
];

echo "Checking directory structure...\n";
foreach ($requiredDirs as $dir) {
    if (is_dir($dir)) {
        echo "✓ $dir/\n";
    } else {
        echo "✗ $dir/ (MISSING)\n";
        $errors[] = "Missing directory: $dir";
    }
}

// Required files
$requiredFiles = [
    'public/index.php',
    'public/shows.php',
    'public/news.php',
    'public/assets/css/style.css',
    'public/assets/js/main.js',
    'admin/index.php',
    'admin/login.php',
    'admin/logout.php',
    'includes/config.php',
    'includes/database.php',
    'includes/auth.php',
    'includes/functions.php',
    'database/schema.sql',
    'database/sample_data.sql',
    'database/init_database.php',
    'database/deploy.sh',
    'database/deploy.bat'
];

echo "\nChecking required files...\n";
foreach ($requiredFiles as $file) {
    if (file_exists($file)) {
        echo "✓ $file\n";
    } else {
        echo "✗ $file (MISSING)\n";
        $errors[] = "Missing file: $file";
    }
}

// Check database file (may not exist yet)
echo "\nChecking database...\n";
if (file_exists('database/craftsite.db')) {
    echo "✓ database/craftsite.db (Database exists)\n";
} else {
    echo "⚠ database/craftsite.db (Not created yet - run deployment script)\n";
    $warnings[] = "Database not created yet. Run database deployment script.";
}

// Check permissions
echo "\nChecking permissions...\n";
$writableDirs = ['database', 'public/uploads'];
foreach ($writableDirs as $dir) {
    if (is_writable($dir)) {
        echo "✓ $dir/ (Writable)\n";
    } else {
        echo "⚠ $dir/ (Not writable)\n";
        $warnings[] = "Directory $dir should be writable by web server";
    }
}

// Check PHP extensions
echo "\nChecking PHP environment...\n";
echo "PHP Version: " . PHP_VERSION . "\n";

$requiredExtensions = ['pdo', 'pdo_sqlite'];
foreach ($requiredExtensions as $ext) {
    if (extension_loaded($ext)) {
        echo "✓ $ext extension loaded\n";
    } else {
        echo "⚠ $ext extension not loaded\n";
        $warnings[] = "PHP extension '$ext' is required for database functionality";
    }
}

// Summary
echo "\n" . str_repeat("=", 40) . "\n";
echo "VERIFICATION SUMMARY\n";
echo str_repeat("=", 40) . "\n";

if (empty($errors)) {
    echo "✓ All required files and directories are present!\n";
} else {
    echo "✗ " . count($errors) . " error(s) found:\n";
    foreach ($errors as $error) {
        echo "  - $error\n";
    }
}

if (!empty($warnings)) {
    echo "\n⚠ " . count($warnings) . " warning(s):\n";
    foreach ($warnings as $warning) {
        echo "  - $warning\n";
    }
}

if (empty($errors)) {
    echo "\nNext steps:\n";
    echo "1. Run database deployment script (database/deploy.sh or database/deploy.bat)\n";
    echo "2. Configure web server to serve from public/ directory\n";
    echo "3. Set appropriate file permissions for production\n";
    echo "4. Access admin panel and change default password\n";
} else {
    echo "\nPlease fix the errors above before proceeding.\n";
}

echo "\n";
?>