<?php
echo "PHP Version: " . PHP_VERSION . "\n";
echo "Available PDO drivers: " . implode(', ', PDO::getAvailableDrivers()) . "\n";
echo "SQLite3 extension loaded: " . (extension_loaded('sqlite3') ? 'Yes' : 'No') . "\n";
echo "PDO SQLite extension loaded: " . (extension_loaded('pdo_sqlite') ? 'Yes' : 'No') . "\n";
?>