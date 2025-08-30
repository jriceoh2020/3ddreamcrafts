#!/bin/bash

# 3DDreamCrafts Database Backup Script
# This script creates backups of the SQLite database with rotation

# Configuration
DB_PATH="/var/www/html/database/craftsite.db"
BACKUP_DIR="/var/backups/3ddreamcrafts"
BACKUP_PREFIX="craftsite_backup"
MAX_BACKUPS=7  # Keep 7 days of backups
LOG_FILE="/var/log/3ddreamcrafts/backup.log"

# Create backup directory if it doesn't exist
mkdir -p "$BACKUP_DIR"
mkdir -p "$(dirname "$LOG_FILE")"

# Function to log messages
log_message() {
    echo "$(date '+%Y-%m-%d %H:%M:%S') - $1" | tee -a "$LOG_FILE"
}

# Function to create backup
create_backup() {
    local timestamp=$(date '+%Y%m%d_%H%M%S')
    local backup_file="${BACKUP_DIR}/${BACKUP_PREFIX}_${timestamp}.db"
    
    log_message "Starting database backup..."
    
    # Check if source database exists
    if [ ! -f "$DB_PATH" ]; then
        log_message "ERROR: Source database not found at $DB_PATH"
        exit 1
    fi
    
    # Create backup using SQLite backup command
    sqlite3 "$DB_PATH" ".backup '$backup_file'"
    
    if [ $? -eq 0 ]; then
        log_message "Backup created successfully: $backup_file"
        
        # Compress the backup
        gzip "$backup_file"
        log_message "Backup compressed: ${backup_file}.gz"
        
        # Set appropriate permissions
        chmod 600 "${backup_file}.gz"
        
        return 0
    else
        log_message "ERROR: Backup failed"
        return 1
    fi
}

# Function to rotate old backups
rotate_backups() {
    log_message "Rotating old backups (keeping $MAX_BACKUPS)..."
    
    # Find and remove old backups
    find "$BACKUP_DIR" -name "${BACKUP_PREFIX}_*.db.gz" -type f -mtime +$MAX_BACKUPS -delete
    
    local remaining=$(find "$BACKUP_DIR" -name "${BACKUP_PREFIX}_*.db.gz" -type f | wc -l)
    log_message "Backup rotation complete. $remaining backup files remaining."
}

# Function to verify backup integrity
verify_backup() {
    local backup_file="$1"
    
    log_message "Verifying backup integrity..."
    
    # Decompress temporarily for verification
    local temp_file="/tmp/verify_backup_$$.db"
    gunzip -c "$backup_file" > "$temp_file"
    
    # Check database integrity
    sqlite3 "$temp_file" "PRAGMA integrity_check;" > /tmp/integrity_check_$$
    
    if grep -q "ok" /tmp/integrity_check_$$; then
        log_message "Backup integrity verification passed"
        rm -f "$temp_file" "/tmp/integrity_check_$$"
        return 0
    else
        log_message "ERROR: Backup integrity verification failed"
        rm -f "$temp_file" "/tmp/integrity_check_$$"
        return 1
    fi
}

# Main execution
main() {
    log_message "=== Starting backup process ==="
    
    # Create backup
    if create_backup; then
        # Get the latest backup file
        latest_backup=$(find "$BACKUP_DIR" -name "${BACKUP_PREFIX}_*.db.gz" -type f -printf '%T@ %p\n' | sort -n | tail -1 | cut -d' ' -f2-)
        
        # Verify backup
        if verify_backup "$latest_backup"; then
            # Rotate old backups
            rotate_backups
            log_message "=== Backup process completed successfully ==="
        else
            log_message "=== Backup process failed during verification ==="
            exit 1
        fi
    else
        log_message "=== Backup process failed ==="
        exit 1
    fi
}

# Check if running as root or appropriate user
if [ "$EUID" -ne 0 ] && [ "$(whoami)" != "www-data" ]; then
    echo "This script should be run as root or www-data user"
    exit 1
fi

# Run main function
main