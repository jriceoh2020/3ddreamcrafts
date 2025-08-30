#!/bin/bash

# 3DDreamCrafts Database Restore Script
# This script restores the SQLite database from a backup

# Configuration
DB_PATH="/var/www/html/database/craftsite.db"
BACKUP_DIR="/var/backups/3ddreamcrafts"
LOG_FILE="/var/log/3ddreamcrafts/restore.log"

# Create log directory if it doesn't exist
mkdir -p "$(dirname "$LOG_FILE")"

# Function to log messages
log_message() {
    echo "$(date '+%Y-%m-%d %H:%M:%S') - $1" | tee -a "$LOG_FILE"
}

# Function to list available backups
list_backups() {
    echo "Available backups:"
    find "$BACKUP_DIR" -name "craftsite_backup_*.db.gz" -type f -printf '%T@ %p\n' | sort -rn | while read timestamp file; do
        local date=$(date -d "@$timestamp" '+%Y-%m-%d %H:%M:%S')
        local basename=$(basename "$file")
        echo "  $basename ($date)"
    done
}

# Function to restore database
restore_database() {
    local backup_file="$1"
    local create_backup_current="$2"
    
    log_message "Starting database restore from: $backup_file"
    
    # Check if backup file exists
    if [ ! -f "$backup_file" ]; then
        log_message "ERROR: Backup file not found: $backup_file"
        return 1
    fi
    
    # Create backup of current database if requested
    if [ "$create_backup_current" = "yes" ]; then
        if [ -f "$DB_PATH" ]; then
            local current_backup="${DB_PATH}.pre-restore.$(date '+%Y%m%d_%H%M%S')"
            log_message "Creating backup of current database: $current_backup"
            cp "$DB_PATH" "$current_backup"
        fi
    fi
    
    # Decompress and restore
    local temp_file="/tmp/restore_$$.db"
    
    log_message "Decompressing backup file..."
    if ! gunzip -c "$backup_file" > "$temp_file"; then
        log_message "ERROR: Failed to decompress backup file"
        rm -f "$temp_file"
        return 1
    fi
    
    # Verify backup integrity before restore
    log_message "Verifying backup integrity..."
    if ! sqlite3 "$temp_file" "PRAGMA integrity_check;" | grep -q "ok"; then
        log_message "ERROR: Backup file is corrupted"
        rm -f "$temp_file"
        return 1
    fi
    
    # Stop web server to prevent database access during restore
    log_message "Stopping web server..."
    systemctl stop apache2 2>/dev/null || systemctl stop nginx 2>/dev/null || true
    
    # Perform restore
    log_message "Restoring database..."
    if cp "$temp_file" "$DB_PATH"; then
        # Set appropriate permissions
        chown www-data:www-data "$DB_PATH"
        chmod 644 "$DB_PATH"
        
        log_message "Database restored successfully"
        
        # Start web server
        log_message "Starting web server..."
        systemctl start apache2 2>/dev/null || systemctl start nginx 2>/dev/null || true
        
        # Cleanup
        rm -f "$temp_file"
        
        return 0
    else
        log_message "ERROR: Failed to restore database"
        
        # Start web server even if restore failed
        systemctl start apache2 2>/dev/null || systemctl start nginx 2>/dev/null || true
        
        rm -f "$temp_file"
        return 1
    fi
}

# Function to show usage
show_usage() {
    echo "Usage: $0 [OPTIONS] [BACKUP_FILE]"
    echo ""
    echo "Options:"
    echo "  -l, --list              List available backups"
    echo "  -b, --backup-current    Create backup of current database before restore"
    echo "  -h, --help              Show this help message"
    echo ""
    echo "Examples:"
    echo "  $0 -l                                    # List available backups"
    echo "  $0 craftsite_backup_20240101_120000.db.gz  # Restore specific backup"
    echo "  $0 -b craftsite_backup_20240101_120000.db.gz  # Restore with current backup"
    echo ""
    echo "If no backup file is specified, the most recent backup will be used."
}

# Parse command line arguments
BACKUP_CURRENT="no"
BACKUP_FILE=""
LIST_ONLY="no"

while [[ $# -gt 0 ]]; do
    case $1 in
        -l|--list)
            LIST_ONLY="yes"
            shift
            ;;
        -b|--backup-current)
            BACKUP_CURRENT="yes"
            shift
            ;;
        -h|--help)
            show_usage
            exit 0
            ;;
        -*)
            echo "Unknown option: $1"
            show_usage
            exit 1
            ;;
        *)
            BACKUP_FILE="$1"
            shift
            ;;
    esac
done

# Check if running as root or appropriate user
if [ "$EUID" -ne 0 ] && [ "$(whoami)" != "www-data" ]; then
    echo "This script should be run as root or www-data user"
    exit 1
fi

# Handle list option
if [ "$LIST_ONLY" = "yes" ]; then
    list_backups
    exit 0
fi

# If no backup file specified, use the most recent one
if [ -z "$BACKUP_FILE" ]; then
    BACKUP_FILE=$(find "$BACKUP_DIR" -name "craftsite_backup_*.db.gz" -type f -printf '%T@ %p\n' | sort -rn | head -1 | cut -d' ' -f2-)
    
    if [ -z "$BACKUP_FILE" ]; then
        log_message "ERROR: No backup files found in $BACKUP_DIR"
        exit 1
    fi
    
    log_message "Using most recent backup: $(basename "$BACKUP_FILE")"
else
    # If relative path provided, prepend backup directory
    if [[ "$BACKUP_FILE" != /* ]]; then
        BACKUP_FILE="$BACKUP_DIR/$BACKUP_FILE"
    fi
fi

# Confirm restore operation
echo "WARNING: This will replace the current database with the backup."
echo "Backup file: $BACKUP_FILE"
echo "Current database will be backed up: $BACKUP_CURRENT"
echo ""
read -p "Are you sure you want to continue? (yes/no): " confirm

if [ "$confirm" != "yes" ]; then
    echo "Restore cancelled."
    exit 0
fi

# Perform restore
log_message "=== Starting restore process ==="
if restore_database "$BACKUP_FILE" "$BACKUP_CURRENT"; then
    log_message "=== Restore process completed successfully ==="
else
    log_message "=== Restore process failed ==="
    exit 1
fi