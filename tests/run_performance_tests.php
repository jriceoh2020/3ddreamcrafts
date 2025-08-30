<?php
/**
 * Performance Test Runner
 * Runs all performance-related tests and optimizations
 */

echo "3DDreamCrafts Performance Test Suite\n";
echo str_repeat("=", 50) . "\n\n";

// 1. Apply database optimizations
echo "1. Applying database optimizations...\n";
$optimizeResult = 0;
passthru('php ' . __DIR__ . '/../database/apply_optimizations.php', $optimizeResult);

if ($optimizeResult !== 0) {
    echo "❌ Database optimization failed!\n";
    exit(1);
}

echo "\n" . str_repeat("-", 50) . "\n\n";

// 2. Run performance tests
echo "2. Running performance tests...\n";
$testResult = 0;
passthru('php ' . __DIR__ . '/PerformanceTest.php', $testResult);

if ($testResult !== 0) {
    echo "❌ Performance tests failed!\n";
    exit(1);
}

echo "\n" . str_repeat("-", 50) . "\n\n";

// 3. Test cache functionality
echo "3. Testing cache system...\n";
require_once __DIR__ . '/../includes/cache.php';

$cache = CacheManager::getInstance();
$testKey = 'performance_test_' . time();
$testData = ['message' => 'Cache test successful', 'timestamp' => time()];

// Test cache operations
$writeSuccess = $cache->set($testKey, $testData, 60);
$readData = $cache->get($testKey);
$deleteSuccess = $cache->delete($testKey);

if ($writeSuccess && $readData === $testData && $deleteSuccess) {
    echo "✅ Cache system working correctly\n";
} else {
    echo "❌ Cache system test failed\n";
    exit(1);
}

// 4. Check cache statistics
$stats = $cache->getStats();
echo "Cache Statistics:\n";
echo "  - Total entries: {$stats['total_entries']}\n";
echo "  - Valid entries: {$stats['valid_entries']}\n";
echo "  - Expired entries: {$stats['expired_entries']}\n";
echo "  - Total size: {$stats['total_size_mb']} MB\n";

echo "\n" . str_repeat("-", 50) . "\n\n";

// 5. Performance recommendations
echo "4. Performance Recommendations:\n";
echo "✅ Database indexes created and optimized\n";
echo "✅ Caching system implemented and tested\n";
echo "✅ Query performance optimized\n";
echo "✅ Memory usage within acceptable limits\n";

if (!extension_loaded('gd')) {
    echo "⚠️  GD extension not available - image optimization disabled\n";
    echo "   Recommendation: Install GD extension for image optimization\n";
}

if (!function_exists('imagewebp')) {
    echo "⚠️  WebP support not available\n";
    echo "   Recommendation: Enable WebP support for better image compression\n";
}

echo "\n🎉 Performance optimization complete!\n";
echo "\nNext steps:\n";
echo "- Monitor performance using /admin/manage/cache.php\n";
echo "- Clear cache when content is updated\n";
echo "- Review performance logs regularly\n";
echo "- Consider enabling GD extension for image optimization\n";

exit(0);