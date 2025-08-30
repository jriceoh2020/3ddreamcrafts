<?php
/**
 * Dynamic CSS Generation
 * Generates CSS with custom variables based on database settings
 */

// Set content type to CSS
header('Content-Type: text/css');

// Enable caching for better performance
$lastModified = filemtime(__FILE__);
$etag = md5_file(__FILE__);

header("Last-Modified: " . gmdate("D, d M Y H:i:s", $lastModified) . " GMT");
header("Etag: $etag");

if (isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) || isset($_SERVER['HTTP_IF_NONE_MATCH'])) {
    if ($_SERVER['HTTP_IF_MODIFIED_SINCE'] == gmdate("D, d M Y H:i:s", $lastModified) . " GMT" ||
        $_SERVER['HTTP_IF_NONE_MATCH'] == $etag) {
        header('HTTP/1.1 304 Not Modified');
        exit;
    }
}

// Load configuration and content management
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/content.php';

try {
    $contentManager = new ContentManager();
    $settings = $contentManager->getSettings();
} catch (Exception $e) {
    // Fallback to default settings if database is not available
    $settings = [
        'theme_color' => '#2563eb',
        'accent_color' => '#dc2626',
        'font_family' => 'Arial, sans-serif'
    ];
}

// Generate CSS with custom properties
?>
:root {
    --theme-color: <?php echo $settings['theme_color']; ?>;
    --accent-color: <?php echo $settings['accent_color']; ?>;
    --font-family: <?php echo $settings['font_family']; ?>;
    
    /* Derived colors for better theming */
    --theme-color-light: <?php echo adjustBrightness($settings['theme_color'], 20); ?>;
    --theme-color-dark: <?php echo adjustBrightness($settings['theme_color'], -20); ?>;
    --accent-color-light: <?php echo adjustBrightness($settings['accent_color'], 20); ?>;
    --accent-color-dark: <?php echo adjustBrightness($settings['accent_color'], -20); ?>;
    
    /* Text colors based on theme */
    --text-on-theme: <?php echo getContrastColor($settings['theme_color']); ?>;
    --text-on-accent: <?php echo getContrastColor($settings['accent_color']); ?>;
}

/* Apply custom font family */
body {
    font-family: var(--font-family);
}

/* Theme-based styling */
.header,
.nav-primary {
    background-color: var(--theme-color);
    color: var(--text-on-theme);
}

.header:hover,
.nav-primary:hover {
    background-color: var(--theme-color-dark);
}

.btn-primary,
.btn {
    background-color: var(--theme-color);
    color: var(--text-on-theme);
}

.btn-primary:hover,
.btn:hover {
    background-color: var(--theme-color-dark);
}

.btn-accent {
    background-color: var(--accent-color);
    color: var(--text-on-accent);
}

.btn-accent:hover {
    background-color: var(--accent-color-dark);
}

.card h3,
.section-title {
    color: var(--theme-color);
}

.link-primary {
    color: var(--theme-color);
}

.link-primary:hover {
    color: var(--theme-color-dark);
}

.link-accent {
    color: var(--accent-color);
}

.link-accent:hover {
    color: var(--accent-color-dark);
}

/* Form elements with theme colors */
.form-input:focus {
    border-color: var(--theme-color);
    box-shadow: 0 0 0 2px rgba(<?php echo hexToRgb($settings['theme_color']); ?>, 0.1);
}

/* Alert styling with theme colors */
.alert-primary {
    background-color: rgba(<?php echo hexToRgb($settings['theme_color']); ?>, 0.1);
    border-color: var(--theme-color);
    color: var(--theme-color-dark);
}

.alert-accent {
    background-color: rgba(<?php echo hexToRgb($settings['accent_color']); ?>, 0.1);
    border-color: var(--accent-color);
    color: var(--accent-color-dark);
}

/* Navigation and menu styling */
.nav-links a:hover {
    background-color: rgba(<?php echo hexToRgb($settings['theme_color']); ?>, 0.1);
}

/* Featured content styling */
.featured-print {
    border-left: 4px solid var(--accent-color);
}

.featured-print h3 {
    color: var(--accent-color);
}

/* Social media links */
.social-links a {
    color: var(--theme-color);
}

.social-links a:hover {
    color: var(--accent-color);
}

/* Admin interface theming */
.admin-header {
    background: linear-gradient(135deg, var(--theme-color), var(--theme-color-dark));
    color: var(--text-on-theme);
}

.admin-sidebar {
    border-right: 2px solid var(--theme-color);
}

.admin-sidebar a.active {
    background-color: var(--theme-color);
    color: var(--text-on-theme);
}

.admin-sidebar a:hover {
    background-color: rgba(<?php echo hexToRgb($settings['theme_color']); ?>, 0.1);
}

/* Table styling */
.table-header {
    background-color: var(--theme-color);
    color: var(--text-on-theme);
}

.table-row:hover {
    background-color: rgba(<?php echo hexToRgb($settings['theme_color']); ?>, 0.05);
}

/* Progress and status indicators */
.status-active {
    color: var(--accent-color);
}

.status-inactive {
    color: #6c757d;
}

.progress-bar {
    background-color: var(--theme-color);
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .header {
        background: linear-gradient(to bottom, var(--theme-color), var(--theme-color-dark));
    }
}

<?php

/**
 * Helper function to adjust color brightness
 * @param string $hex Hex color code
 * @param int $percent Percentage to adjust (-100 to 100)
 * @return string Adjusted hex color
 */
function adjustBrightness($hex, $percent) {
    // Remove # if present
    $hex = ltrim($hex, '#');
    
    // Convert to RGB
    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));
    
    // Adjust brightness
    $r = max(0, min(255, $r + ($r * $percent / 100)));
    $g = max(0, min(255, $g + ($g * $percent / 100)));
    $b = max(0, min(255, $b + ($b * $percent / 100)));
    
    // Convert back to hex
    return sprintf('#%02x%02x%02x', $r, $g, $b);
}

/**
 * Get contrasting text color (black or white) for a background color
 * @param string $hex Background hex color
 * @return string #ffffff or #000000
 */
function getContrastColor($hex) {
    // Remove # if present
    $hex = ltrim($hex, '#');
    
    // Convert to RGB
    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));
    
    // Calculate luminance
    $luminance = (0.299 * $r + 0.587 * $g + 0.114 * $b) / 255;
    
    // Return black or white based on luminance
    return $luminance > 0.5 ? '#000000' : '#ffffff';
}

/**
 * Convert hex color to RGB values
 * @param string $hex Hex color code
 * @return string RGB values as comma-separated string
 */
function hexToRgb($hex) {
    // Remove # if present
    $hex = ltrim($hex, '#');
    
    // Convert to RGB
    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));
    
    return "$r, $g, $b";
}

?>