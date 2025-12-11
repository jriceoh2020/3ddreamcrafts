#!/bin/bash
###############################################################################
# 3DDreamCrafts - Database Update Script
# Apply database schema changes and updates
#
# Usage: sudo ./scripts/update_database.sh
###############################################################################

set -e  # Exit on error

# Color codes for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
# Get the actual user's home directory (not root's when using sudo)
if [ -n "$SUDO_USER" ]; then
    ACTUAL_USER="$SUDO_USER"
    ACTUAL_HOME=$(eval echo ~$SUDO_USER)
else
    ACTUAL_USER="$USER"
    ACTUAL_HOME="$HOME"
fi

WEB_ROOT="${WEB_ROOT:-/var/www/3ddreamcrafts}"
DB_PATH="$WEB_ROOT/database/craftsite.db"
BACKUP_DIR="${BACKUP_DIR:-$ACTUAL_HOME/3ddreamcrafts_db_backups}"
TIMESTAMP=$(date +%Y%m%d_%H%M%S)

# Check if running as root or with sudo
if [ "$EUID" -ne 0 ]; then
    echo -e "${RED}ERROR: This script must be run as root or with sudo${NC}"
    echo "Usage: sudo ./scripts/update_database.sh"
    exit 1
fi

echo -e "${BLUE}╔════════════════════════════════════════╗${NC}"
echo -e "${BLUE}║  3DDreamCrafts Database Update Script ║${NC}"
echo -e "${BLUE}╚════════════════════════════════════════╝${NC}"
echo ""
echo -e "  Running as:  ${GREEN}$ACTUAL_USER${NC}"
echo -e "  Web root:    ${GREEN}$WEB_ROOT${NC}"
echo -e "  Database:    ${GREEN}$DB_PATH${NC}"
echo ""

# Create backup directory if it doesn't exist
mkdir -p "$BACKUP_DIR"

# Check if database exists
if [ ! -f "$DB_PATH" ]; then
    echo -e "${RED}ERROR: Database not found at $DB_PATH${NC}"
    echo ""
    echo "Would you like to initialize a new database? (y/n)"
    read -p "> " -n 1 -r
    echo
    if [[ $REPLY =~ ^[Yy]$ ]]; then
        echo -e "${YELLOW}Initializing new database...${NC}"
        cd "$WEB_ROOT"
        if [ -f "$WEB_ROOT/database/deploy.sh" ]; then
            bash "$WEB_ROOT/database/deploy.sh"
            echo -e "${GREEN}✓ Database initialized${NC}"
        elif [ -f "$WEB_ROOT/database/init_database.php" ]; then
            php "$WEB_ROOT/database/init_database.php"
            echo -e "${GREEN}✓ Database initialized${NC}"
        else
            echo -e "${RED}ERROR: No database initialization script found${NC}"
            exit 1
        fi
    else
        exit 1
    fi
fi

echo -e "${YELLOW}[1/5] Backing up current database...${NC}"

# Create backup
BACKUP_FILE="$BACKUP_DIR/craftsite_backup_$TIMESTAMP.db"
cp "$DB_PATH" "$BACKUP_FILE"

# Also backup WAL and SHM files if they exist
if [ -f "$DB_PATH-wal" ]; then
    cp "$DB_PATH-wal" "$BACKUP_FILE-wal"
fi
if [ -f "$DB_PATH-shm" ]; then
    cp "$DB_PATH-shm" "$BACKUP_FILE-shm"
fi

BACKUP_SIZE=$(du -h "$BACKUP_FILE" | cut -f1)
echo -e "${GREEN}✓ Backup created: $BACKUP_FILE ($BACKUP_SIZE)${NC}"
echo ""

echo -e "${YELLOW}[2/5] Checking database schema...${NC}"

# Function to check if a table exists
check_table() {
    local table_name=$1
    sqlite3 "$DB_PATH" "SELECT name FROM sqlite_master WHERE type='table' AND name='$table_name';" 2>/dev/null
}

# Function to check if a column exists
check_column() {
    local table_name=$1
    local column_name=$2
    sqlite3 "$DB_PATH" "PRAGMA table_info($table_name);" | grep -q "$column_name"
}

# Check required tables
required_tables=("admin_users" "featured_prints" "craft_shows" "news_articles" "settings")
missing_tables=()

for table in "${required_tables[@]}"; do
    result=$(check_table "$table")
    if [ -z "$result" ]; then
        missing_tables+=("$table")
        echo -e "  ${RED}✗ Missing table: $table${NC}"
    else
        echo -e "  ${GREEN}✓ Table exists: $table${NC}"
    fi
done

echo ""

echo -e "${YELLOW}[3/5] Applying schema updates...${NC}"

# Logo feature update (no schema changes needed - using existing settings table)
echo "  Checking logo feature requirements..."

# Check if site_logo setting exists, if not add it
LOGO_SETTING=$(sqlite3 "$DB_PATH" "SELECT COUNT(*) FROM settings WHERE setting_name='site_logo';" 2>/dev/null || echo "0")

if [ "$LOGO_SETTING" = "0" ]; then
    echo "    Adding default site_logo setting..."
    sqlite3 "$DB_PATH" "INSERT INTO settings (setting_name, setting_value, updated_at) VALUES ('site_logo', '', datetime('now'));" 2>/dev/null || true
    echo -e "    ${GREEN}✓ site_logo setting added${NC}"
else
    echo -e "    ${GREEN}✓ site_logo setting already exists${NC}"
fi

echo ""

echo -e "${YELLOW}[4/5] Verifying database integrity...${NC}"

# Run integrity check
INTEGRITY_CHECK=$(sqlite3 "$DB_PATH" "PRAGMA integrity_check;" 2>/dev/null)

if [ "$INTEGRITY_CHECK" = "ok" ]; then
    echo -e "${GREEN}✓ Database integrity check passed${NC}"
else
    echo -e "${RED}✗ Database integrity check failed:${NC}"
    echo "$INTEGRITY_CHECK"
    echo ""
    echo -e "${YELLOW}Would you like to restore from backup? (y/n)${NC}"
    read -p "> " -n 1 -r
    echo
    if [[ $REPLY =~ ^[Yy]$ ]]; then
        cp "$BACKUP_FILE" "$DB_PATH"
        echo -e "${GREEN}✓ Database restored from backup${NC}"
        exit 1
    fi
fi

# Check for database optimization
echo ""
echo "  Running database optimization..."
sqlite3 "$DB_PATH" "VACUUM;" 2>/dev/null || echo -e "${YELLOW}⚠ VACUUM failed (database may be in use)${NC}"
sqlite3 "$DB_PATH" "ANALYZE;" 2>/dev/null || echo -e "${YELLOW}⚠ ANALYZE failed${NC}"
echo -e "${GREEN}✓ Database optimized${NC}"

echo ""

echo -e "${YELLOW}[5/5] Setting permissions...${NC}"

# Set proper ownership and permissions
chown www-data:www-data "$DB_PATH"
chmod 664 "$DB_PATH"

# Set permissions on WAL and SHM files if they exist
if [ -f "$DB_PATH-wal" ]; then
    chown www-data:www-data "$DB_PATH-wal"
    chmod 664 "$DB_PATH-wal"
fi

if [ -f "$DB_PATH-shm" ]; then
    chown www-data:www-data "$DB_PATH-shm"
    chmod 664 "$DB_PATH-shm"
fi

# Ensure database directory is writable
chown www-data:www-data "$WEB_ROOT/database"
chmod 775 "$WEB_ROOT/database"

echo -e "${GREEN}✓ Permissions set${NC}"
echo ""

# Display database information
echo -e "${BLUE}╔════════════════════════════════════════╗${NC}"
echo -e "${BLUE}║       Database Update Summary          ║${NC}"
echo -e "${BLUE}╚════════════════════════════════════════╝${NC}"

# Get table counts
echo -e "  ${BLUE}Table Statistics:${NC}"
for table in "${required_tables[@]}"; do
    if check_table "$table" >/dev/null 2>&1; then
        count=$(sqlite3 "$DB_PATH" "SELECT COUNT(*) FROM $table;" 2>/dev/null || echo "N/A")
        printf "    %-20s %s\n" "$table:" "$count rows"
    fi
done

echo ""
echo -e "  Database:    ${GREEN}$DB_PATH${NC}"
echo -e "  Backup:      ${GREEN}$BACKUP_FILE${NC}"
echo -e "  Status:      ${GREEN}✓ UPDATE SUCCESSFUL${NC}"
echo ""

# Display additional information
echo -e "${BLUE}Additional Information:${NC}"

# Check site_logo setting value
LOGO_VALUE=$(sqlite3 "$DB_PATH" "SELECT setting_value FROM settings WHERE setting_name='site_logo';" 2>/dev/null || echo "")
if [ -z "$LOGO_VALUE" ]; then
    echo -e "  Logo Status: ${YELLOW}No logo uploaded yet${NC}"
    echo "    Upload a logo via: Admin Panel > Design Settings > Site Logo"
else
    echo -e "  Logo Status: ${GREEN}Logo configured${NC}"
    echo "    Path: $LOGO_VALUE"
fi

echo ""

# Create uploads/logos directory if it doesn't exist
LOGOS_DIR="$WEB_ROOT/public/uploads/logos"
if [ ! -d "$LOGOS_DIR" ]; then
    mkdir -p "$LOGOS_DIR"
    chown www-data:www-data "$LOGOS_DIR"
    chmod 775 "$LOGOS_DIR"
    echo -e "${GREEN}✓ Created logos upload directory: $LOGOS_DIR${NC}"
fi

echo ""
echo -e "${BLUE}Next Steps:${NC}"
echo "  1. Test the admin panel:"
echo "     ${GREEN}https://your-domain.com/admin/settings/design.php${NC}"
echo ""
echo "  2. Upload a logo through the admin interface"
echo ""
echo "  3. Verify logo appears on public pages:"
echo "     ${GREEN}https://your-domain.com/${NC}"
echo ""

# Keep last 10 backups only
echo -e "${YELLOW}Cleaning old backups (keeping last 10)...${NC}"
cd "$BACKUP_DIR"
ls -t craftsite_backup_*.db 2>/dev/null | tail -n +11 | xargs rm -f 2>/dev/null || true
BACKUP_COUNT=$(ls -1 craftsite_backup_*.db 2>/dev/null | wc -l)
echo -e "${GREEN}✓ $BACKUP_COUNT backup(s) retained${NC}"
echo ""

echo -e "${GREEN}Database update completed successfully!${NC}"
