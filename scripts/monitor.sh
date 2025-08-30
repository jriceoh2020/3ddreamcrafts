#!/bin/bash

# 3DDreamCrafts System Monitoring Script
# This script provides real-time monitoring and alerting for the website

# Configuration
MONITOR_INTERVAL=60  # Check interval in seconds
LOG_FILE="/var/log/3ddreamcrafts/monitor.log"
PID_FILE="/var/run/3ddreamcrafts-monitor.pid"
ALERT_EMAIL=""  # Set email address for alerts
WEBSITE_URL="http://localhost"
DB_PATH="/var/www/html/database/craftsite.db"

# Thresholds
CPU_THRESHOLD=80
MEMORY_THRESHOLD=80
DISK_THRESHOLD=90
RESPONSE_TIME_THRESHOLD=3000  # milliseconds

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
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
    local level="$1"
    local message="$2"
    
    case $level in
        "INFO")
            echo -e "${BLUE}[INFO]${NC} $message"
            ;;
        "OK")
            echo -e "${GREEN}[OK]${NC} $message"
            ;;
        "WARNING")
            echo -e "${YELLOW}[WARNING]${NC} $message"
            ;;
        "CRITICAL")
            echo -e "${RED}[CRITICAL]${NC} $message"
            ;;
        *)
            echo "[$level] $message"
            ;;
    esac
}

# Function to send alert
send_alert() {
    local subject="$1"
    local message="$2"
    
    if [ -n "$ALERT_EMAIL" ] && command -v mail >/dev/null 2>&1; then
        echo "$message" | mail -s "$subject" "$ALERT_EMAIL"
        log_message "INFO" "Alert sent: $subject"
    fi
}

# Function to check if process is already running
check_running() {
    if [ -f "$PID_FILE" ]; then
        local pid=$(cat "$PID_FILE")
        if ps -p "$pid" > /dev/null 2>&1; then
            echo "Monitor is already running (PID: $pid)"
            exit 1
        else
            rm -f "$PID_FILE"
        fi
    fi
}

# Function to cleanup on exit
cleanup() {
    log_message "INFO" "Monitor stopping..."
    rm -f "$PID_FILE"
    exit 0
}

# Function to monitor CPU usage
monitor_cpu() {
    local cpu_usage=$(top -bn1 | grep "Cpu(s)" | awk '{print $2}' | sed 's/%us,//')
    local cpu_percent=$(echo "$cpu_usage" | cut -d'%' -f1)
    
    if (( $(echo "$cpu_percent > $CPU_THRESHOLD" | bc -l) )); then
        print_status "WARNING" "High CPU usage: ${cpu_percent}%"
        log_message "WARNING" "CPU usage: ${cpu_percent}%"
        send_alert "High CPU Usage Alert" "CPU usage is at ${cpu_percent}%"
        return 1
    else
        print_status "OK" "CPU usage: ${cpu_percent}%"
        return 0
    fi
}

# Function to monitor memory usage
monitor_memory() {
    local memory_info=$(free | awk 'NR==2{printf "%.1f %.1f", $3*100/$2, ($3+$5)*100/$2}')
    local used_percent=$(echo "$memory_info" | cut -d' ' -f1)
    local available_percent=$(echo "100 - $(echo "$memory_info" | cut -d' ' -f2)" | bc -l)
    
    if (( $(echo "$used_percent > $MEMORY_THRESHOLD" | bc -l) )); then
        print_status "WARNING" "High memory usage: ${used_percent}% (${available_percent}% available)"
        log_message "WARNING" "Memory usage: ${used_percent}%"
        send_alert "High Memory Usage Alert" "Memory usage is at ${used_percent}%"
        return 1
    else
        print_status "OK" "Memory usage: ${used_percent}% (${available_percent}% available)"
        return 0
    fi
}

# Function to monitor disk usage
monitor_disk() {
    local disk_usage=$(df / | awk 'NR==2 {print $5}' | sed 's/%//')
    
    if [ "$disk_usage" -gt "$DISK_THRESHOLD" ]; then
        print_status "CRITICAL" "Critical disk usage: ${disk_usage}%"
        log_message "CRITICAL" "Disk usage: ${disk_usage}%"
        send_alert "Critical Disk Usage Alert" "Disk usage is at ${disk_usage}%"
        return 1
    elif [ "$disk_usage" -gt 75 ]; then
        print_status "WARNING" "High disk usage: ${disk_usage}%"
        log_message "WARNING" "Disk usage: ${disk_usage}%"
        return 1
    else
        print_status "OK" "Disk usage: ${disk_usage}%"
        return 0
    fi
}

# Function to monitor website response
monitor_website() {
    local start_time=$(date +%s%3N)
    local http_code=$(curl -s -o /dev/null -w "%{http_code}" "$WEBSITE_URL" --max-time 10)
    local end_time=$(date +%s%3N)
    local response_time=$((end_time - start_time))
    
    if [ "$http_code" != "200" ]; then
        print_status "CRITICAL" "Website not responding (HTTP $http_code)"
        log_message "CRITICAL" "Website HTTP error: $http_code"
        send_alert "Website Down Alert" "Website returned HTTP $http_code"
        return 1
    elif [ "$response_time" -gt "$RESPONSE_TIME_THRESHOLD" ]; then
        print_status "WARNING" "Slow website response: ${response_time}ms"
        log_message "WARNING" "Slow response: ${response_time}ms"
        return 1
    else
        print_status "OK" "Website responding: ${response_time}ms"
        return 0
    fi
}

# Function to monitor database
monitor_database() {
    if [ ! -f "$DB_PATH" ]; then
        print_status "CRITICAL" "Database file missing"
        log_message "CRITICAL" "Database file not found"
        send_alert "Database Missing Alert" "Database file not found at $DB_PATH"
        return 1
    fi
    
    # Test database connection
    if sqlite3 "$DB_PATH" "SELECT 1;" >/dev/null 2>&1; then
        print_status "OK" "Database accessible"
        return 0
    else
        print_status "CRITICAL" "Database connection failed"
        log_message "CRITICAL" "Cannot connect to database"
        send_alert "Database Connection Alert" "Cannot connect to database"
        return 1
    fi
}

# Function to monitor services
monitor_services() {
    local services_down=0
    
    # Check web server
    if systemctl is-active --quiet apache2 || systemctl is-active --quiet nginx; then
        print_status "OK" "Web server running"
    else
        print_status "CRITICAL" "Web server not running"
        log_message "CRITICAL" "Web server down"
        send_alert "Web Server Down Alert" "Web server is not running"
        ((services_down++))
    fi
    
    # Check PHP-FPM
    if systemctl is-active --quiet php8.2-fpm; then
        print_status "OK" "PHP-FPM running"
    else
        print_status "CRITICAL" "PHP-FPM not running"
        log_message "CRITICAL" "PHP-FPM down"
        send_alert "PHP-FPM Down Alert" "PHP-FPM is not running"
        ((services_down++))
    fi
    
    return $services_down
}

# Function to monitor log files for errors
monitor_logs() {
    local error_count=0
    local current_time=$(date +%s)
    local one_minute_ago=$((current_time - 60))
    
    # Check Apache/Nginx error logs for recent errors
    for log_file in /var/log/apache2/*error.log /var/log/nginx/*error.log; do
        if [ -f "$log_file" ]; then
            # Count errors in the last minute
            local recent_errors=$(awk -v start="$one_minute_ago" '
                {
                    # Parse log timestamp and convert to epoch
                    # This is a simplified check - adjust based on your log format
                    if ($0 ~ /error|Error|ERROR/) {
                        print $0
                    }
                }' "$log_file" 2>/dev/null | wc -l)
            
            if [ "$recent_errors" -gt 5 ]; then
                print_status "WARNING" "High error rate in $(basename "$log_file"): $recent_errors errors/minute"
                log_message "WARNING" "High error rate: $recent_errors errors in $log_file"
                ((error_count++))
            fi
        fi
    done
    
    if [ "$error_count" -eq 0 ]; then
        print_status "OK" "No excessive errors in logs"
    fi
    
    return $error_count
}

# Function to display system summary
display_summary() {
    echo ""
    echo "=== System Summary ==="
    
    # Load average
    local load_avg=$(uptime | awk -F'load average:' '{print $2}')
    echo "Load average:$load_avg"
    
    # Uptime
    local uptime_info=$(uptime -p)
    echo "Uptime: $uptime_info"
    
    # Active connections (if netstat is available)
    if command -v netstat >/dev/null 2>&1; then
        local connections=$(netstat -an | grep :80 | grep ESTABLISHED | wc -l)
        echo "Active HTTP connections: $connections"
    fi
    
    # Database size
    if [ -f "$DB_PATH" ]; then
        local db_size=$(du -h "$DB_PATH" | cut -f1)
        echo "Database size: $db_size"
    fi
    
    echo ""
}

# Main monitoring loop
monitor_loop() {
    log_message "INFO" "Starting monitoring loop (interval: ${MONITOR_INTERVAL}s)"
    
    while true; do
        local timestamp=$(date '+%Y-%m-%d %H:%M:%S')
        echo "=== Monitor Check: $timestamp ==="
        
        local total_issues=0
        
        # Run all monitoring checks
        monitor_cpu || ((total_issues++))
        monitor_memory || ((total_issues++))
        monitor_disk || ((total_issues++))
        monitor_website || ((total_issues++))
        monitor_database || ((total_issues++))
        monitor_services || ((total_issues++))
        monitor_logs || ((total_issues++))
        
        # Display summary
        display_summary
        
        if [ "$total_issues" -eq 0 ]; then
            print_status "OK" "All systems normal"
        else
            print_status "WARNING" "$total_issues issues detected"
        fi
        
        echo "Next check in ${MONITOR_INTERVAL} seconds..."
        echo "----------------------------------------"
        
        sleep "$MONITOR_INTERVAL"
    done
}

# Function to show usage
show_usage() {
    echo "Usage: $0 [OPTIONS]"
    echo ""
    echo "Options:"
    echo "  start           Start monitoring daemon"
    echo "  stop            Stop monitoring daemon"
    echo "  status          Show monitoring status"
    echo "  check           Run single health check"
    echo "  -h, --help      Show this help message"
    echo ""
    echo "Configuration:"
    echo "  Edit the script to set ALERT_EMAIL and other parameters"
}

# Handle command line arguments
case "${1:-start}" in
    "start")
        check_running
        echo $$ > "$PID_FILE"
        trap cleanup EXIT INT TERM
        
        print_status "INFO" "Starting 3DDreamCrafts monitor..."
        log_message "INFO" "Monitor started (PID: $$)"
        
        monitor_loop
        ;;
    
    "stop")
        if [ -f "$PID_FILE" ]; then
            local pid=$(cat "$PID_FILE")
            if ps -p "$pid" > /dev/null 2>&1; then
                kill "$pid"
                rm -f "$PID_FILE"
                echo "Monitor stopped"
            else
                echo "Monitor not running"
                rm -f "$PID_FILE"
            fi
        else
            echo "Monitor not running"
        fi
        ;;
    
    "status")
        if [ -f "$PID_FILE" ]; then
            local pid=$(cat "$PID_FILE")
            if ps -p "$pid" > /dev/null 2>&1; then
                echo "Monitor is running (PID: $pid)"
                echo "Log file: $LOG_FILE"
            else
                echo "Monitor not running (stale PID file)"
                rm -f "$PID_FILE"
            fi
        else
            echo "Monitor not running"
        fi
        ;;
    
    "check")
        echo "Running single health check..."
        monitor_cpu
        monitor_memory
        monitor_disk
        monitor_website
        monitor_database
        monitor_services
        monitor_logs
        display_summary
        ;;
    
    "-h"|"--help")
        show_usage
        ;;
    
    *)
        echo "Unknown option: $1"
        show_usage
        exit 1
        ;;
esac