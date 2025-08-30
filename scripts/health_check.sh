#!/bin/bash

# 3DDreamCrafts System Health Check Script
# This script performs comprehensive health checks on the website system

# Configuration
WEBSITE_URL="http://localhost"
DB_PATH="/var/www/html/database/craftsite.db"
LOG_FILE="/var/log/3ddreamcrafts/health_check.log"
ALERT_EMAIL=""  # Set email address for alerts
CRITICAL_THRESHOLD=90  # Disk usage percentage threshold
MEMORY_THRESHOLD=80    # Memory usage percentage threshold

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Create log directory if it doesn't exist
mkdir -p "$(dirname "$LOG_FILE")"

# Function to log messages
log_message() {
    local level="$1"
    local message="$2"
    echo "$(date '+%Y-%m-%d %H:%M:%S') [$level] $message" | tee -a "$LOG_FILE"
}

# Function to print colored output
print_status() {
    local status="$1"
    local message="$2"
    
    case $status in
        "OK")
            echo -e "${GREEN}✓${NC} $message"
            ;;
        "WARNING")
            echo -e "${YELLOW}⚠${NC} $message"
            ;;
        "CRITICAL")
            echo -e "${RED}✗${NC} $message"
            ;;
        *)
            echo "$message"
            ;;
    esac
}

# Function to check web server status
check_web_server() {
    log_message "INFO" "Checking web server status..."
    
    # Check Apache
    if systemctl is-active --quiet apache2; then
        print_status "OK" "Apache web server is running"
        return 0
    fi
    
    # Check Nginx
    if systemctl is-active --quiet nginx; then
        print_status "OK" "Nginx web server is running"
        return 0
    fi
    
    print_status "CRITICAL" "No web server (Apache/Nginx) is running"
    log_message "CRITICAL" "Web server is not running"
    return 1
}

# Function to check PHP-FPM status
check_php_fpm() {
    log_message "INFO" "Checking PHP-FPM status..."
    
    if systemctl is-active --quiet php8.2-fpm; then
        print_status "OK" "PHP-FPM is running"
        return 0
    else
        print_status "CRITICAL" "PHP-FPM is not running"
        log_message "CRITICAL" "PHP-FPM is not running"
        return 1
    fi
}

# Function to check database connectivity
check_database() {
    log_message "INFO" "Checking database connectivity..."
    
    if [ ! -f "$DB_PATH" ]; then
        print_status "CRITICAL" "Database file not found: $DB_PATH"
        log_message "CRITICAL" "Database file not found"
        return 1
    fi
    
    # Test database connection
    if sqlite3 "$DB_PATH" "SELECT 1;" >/dev/null 2>&1; then
        print_status "OK" "Database is accessible"
        
        # Check database integrity
        local integrity_result=$(sqlite3 "$DB_PATH" "PRAGMA integrity_check;")
        if [ "$integrity_result" = "ok" ]; then
            print_status "OK" "Database integrity check passed"
        else
            print_status "WARNING" "Database integrity check failed: $integrity_result"
            log_message "WARNING" "Database integrity issue: $integrity_result"
        fi
        return 0
    else
        print_status "CRITICAL" "Cannot connect to database"
        log_message "CRITICAL" "Database connection failed"
        return 1
    fi
}

# Function to check website accessibility
check_website() {
    log_message "INFO" "Checking website accessibility..."
    
    # Check main page
    local http_code=$(curl -s -o /dev/null -w "%{http_code}" "$WEBSITE_URL" --max-time 10)
    
    if [ "$http_code" = "200" ]; then
        print_status "OK" "Website is accessible (HTTP $http_code)"
        
        # Check response time
        local response_time=$(curl -s -o /dev/null -w "%{time_total}" "$WEBSITE_URL" --max-time 10)
        local response_ms=$(echo "$response_time * 1000" | bc -l | cut -d. -f1)
        
        if [ "$response_ms" -lt 3000 ]; then
            print_status "OK" "Website response time: ${response_ms}ms"
        else
            print_status "WARNING" "Website response time slow: ${response_ms}ms"
            log_message "WARNING" "Slow response time: ${response_ms}ms"
        fi
        
    else
        print_status "CRITICAL" "Website not accessible (HTTP $http_code)"
        log_message "CRITICAL" "Website returned HTTP $http_code"
        return 1
    fi
}

# Function to check disk usage
check_disk_usage() {
    log_message "INFO" "Checking disk usage..."
    
    local disk_usage=$(df / | awk 'NR==2 {print $5}' | sed 's/%//')
    
    if [ "$disk_usage" -lt "$CRITICAL_THRESHOLD" ]; then
        print_status "OK" "Disk usage: ${disk_usage}%"
    else
        print_status "CRITICAL" "Disk usage critical: ${disk_usage}%"
        log_message "CRITICAL" "Disk usage at ${disk_usage}%"
        return 1
    fi
}

# Function to check memory usage
check_memory_usage() {
    log_message "INFO" "Checking memory usage..."
    
    local memory_usage=$(free | awk 'NR==2{printf "%.0f", $3*100/$2}')
    
    if [ "$memory_usage" -lt "$MEMORY_THRESHOLD" ]; then
        print_status "OK" "Memory usage: ${memory_usage}%"
    else
        print_status "WARNING" "Memory usage high: ${memory_usage}%"
        log_message "WARNING" "Memory usage at ${memory_usage}%"
    fi
}

# Function to check file permissions
check_permissions() {
    log_message "INFO" "Checking file permissions..."
    
    local issues=0
    
    # Check database permissions
    if [ -f "$DB_PATH" ]; then
        local db_perms=$(stat -c "%a" "$DB_PATH")
        if [ "$db_perms" = "644" ]; then
            print_status "OK" "Database file permissions correct (644)"
        else
            print_status "WARNING" "Database file permissions incorrect: $db_perms (should be 644)"
            ((issues++))
        fi
    fi
    
    # Check uploads directory
    if [ -d "/var/www/html/public/uploads" ]; then
        local uploads_perms=$(stat -c "%a" "/var/www/html/public/uploads")
        if [ "$uploads_perms" = "755" ]; then
            print_status "OK" "Uploads directory permissions correct (755)"
        else
            print_status "WARNING" "Uploads directory permissions incorrect: $uploads_perms (should be 755)"
            ((issues++))
        fi
    fi
    
    # Check for world-writable files (security risk)
    local writable_files=$(find /var/www/html -type f -perm /o+w 2>/dev/null | wc -l)
    if [ "$writable_files" -eq 0 ]; then
        print_status "OK" "No world-writable files found"
    else
        print_status "WARNING" "$writable_files world-writable files found (security risk)"
        log_message "WARNING" "$writable_files world-writable files detected"
        ((issues++))
    fi
    
    return $issues
}

# Function to check log file sizes
check_log_sizes() {
    log_message "INFO" "Checking log file sizes..."
    
    local large_logs=0
    
    # Check Apache/Nginx logs
    for log_file in /var/log/apache2/*.log /var/log/nginx/*.log; do
        if [ -f "$log_file" ]; then
            local size=$(stat -c%s "$log_file" 2>/dev/null || echo 0)
            local size_mb=$((size / 1024 / 1024))
            
            if [ "$size_mb" -gt 100 ]; then
                print_status "WARNING" "Large log file: $(basename "$log_file") (${size_mb}MB)"
                ((large_logs++))
            fi
        fi
    done
    
    if [ "$large_logs" -eq 0 ]; then
        print_status "OK" "Log file sizes are reasonable"
    else
        log_message "WARNING" "$large_logs large log files detected"
    fi
}

# Function to check SSL certificate (if HTTPS is configured)
check_ssl_certificate() {
    log_message "INFO" "Checking SSL certificate..."
    
    # Try to get certificate info
    local cert_info=$(echo | openssl s_client -servername localhost -connect localhost:443 2>/dev/null | openssl x509 -noout -dates 2>/dev/null)
    
    if [ -n "$cert_info" ]; then
        local expiry_date=$(echo "$cert_info" | grep "notAfter" | cut -d= -f2)
        local expiry_timestamp=$(date -d "$expiry_date" +%s 2>/dev/null)
        local current_timestamp=$(date +%s)
        local days_until_expiry=$(( (expiry_timestamp - current_timestamp) / 86400 ))
        
        if [ "$days_until_expiry" -gt 30 ]; then
            print_status "OK" "SSL certificate valid for $days_until_expiry days"
        elif [ "$days_until_expiry" -gt 0 ]; then
            print_status "WARNING" "SSL certificate expires in $days_until_expiry days"
            log_message "WARNING" "SSL certificate expires soon: $days_until_expiry days"
        else
            print_status "CRITICAL" "SSL certificate has expired"
            log_message "CRITICAL" "SSL certificate expired"
            return 1
        fi
    else
        print_status "OK" "No SSL certificate configured (HTTP only)"
    fi
}

# Function to check backup status
check_backup_status() {
    log_message "INFO" "Checking backup status..."
    
    local backup_dir="/var/backups/3ddreamcrafts"
    
    if [ -d "$backup_dir" ]; then
        local latest_backup=$(find "$backup_dir" -name "craftsite_backup_*.db.gz" -type f -printf '%T@ %p\n' 2>/dev/null | sort -rn | head -1 | cut -d' ' -f2-)
        
        if [ -n "$latest_backup" ]; then
            local backup_age=$(( ($(date +%s) - $(stat -c %Y "$latest_backup")) / 86400 ))
            
            if [ "$backup_age" -le 1 ]; then
                print_status "OK" "Recent backup found (${backup_age} days old)"
            elif [ "$backup_age" -le 7 ]; then
                print_status "WARNING" "Backup is ${backup_age} days old"
                log_message "WARNING" "Backup age: ${backup_age} days"
            else
                print_status "CRITICAL" "No recent backup found (${backup_age} days old)"
                log_message "CRITICAL" "Backup too old: ${backup_age} days"
                return 1
            fi
        else
            print_status "CRITICAL" "No backup files found"
            log_message "CRITICAL" "No backup files found"
            return 1
        fi
    else
        print_status "WARNING" "Backup directory not found"
        log_message "WARNING" "Backup directory missing"
    fi
}

# Function to send alert email
send_alert() {
    local subject="$1"
    local message="$2"
    
    if [ -n "$ALERT_EMAIL" ] && command -v mail >/dev/null 2>&1; then
        echo "$message" | mail -s "$subject" "$ALERT_EMAIL"
        log_message "INFO" "Alert email sent to $ALERT_EMAIL"
    fi
}

# Main health check function
main() {
    echo "=== 3DDreamCrafts System Health Check ==="
    echo "Started at: $(date)"
    echo ""
    
    log_message "INFO" "=== Starting health check ==="
    
    local total_checks=0
    local failed_checks=0
    local warnings=0
    
    # Run all health checks
    checks=(
        "check_web_server"
        "check_php_fpm"
        "check_database"
        "check_website"
        "check_disk_usage"
        "check_memory_usage"
        "check_permissions"
        "check_log_sizes"
        "check_ssl_certificate"
        "check_backup_status"
    )
    
    for check in "${checks[@]}"; do
        echo ""
        ((total_checks++))
        
        if ! $check; then
            ((failed_checks++))
        fi
    done
    
    echo ""
    echo "=== Health Check Summary ==="
    echo "Total checks: $total_checks"
    echo "Failed checks: $failed_checks"
    
    if [ "$failed_checks" -eq 0 ]; then
        print_status "OK" "All health checks passed"
        log_message "INFO" "All health checks passed"
        exit 0
    else
        print_status "CRITICAL" "$failed_checks health checks failed"
        log_message "CRITICAL" "$failed_checks health checks failed"
        
        # Send alert if configured
        send_alert "3DDreamCrafts Health Check Alert" "Health check failed with $failed_checks critical issues. Check $LOG_FILE for details."
        
        exit 1
    fi
}

# Check if running with appropriate permissions
if [ "$EUID" -ne 0 ] && [ "$(whoami)" != "www-data" ]; then
    echo "Warning: This script should be run as root or www-data for complete checks"
fi

# Install bc if not available (for calculations)
if ! command -v bc >/dev/null 2>&1; then
    echo "Installing bc for calculations..."
    apt-get update && apt-get install -y bc >/dev/null 2>&1
fi

# Run main function
main