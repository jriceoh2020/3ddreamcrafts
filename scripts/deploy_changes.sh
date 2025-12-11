#!/bin/bash
###############################################################################
# 3DDreamCrafts - Deploy Changes Script
# Copy latest changes from git repository to Apache web root
#
# Usage: sudo ./scripts/deploy_changes.sh
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

GIT_REPO_DIR="${GIT_REPO_DIR:-$ACTUAL_HOME/3ddreamcrafts}"
WEB_ROOT="${WEB_ROOT:-/var/www/3ddreamcrafts}"
BACKUP_DIR="${BACKUP_DIR:-$ACTUAL_HOME/3ddreamcrafts_backups}"
TIMESTAMP=$(date +%Y%m%d_%H%M%S)

# Check if running as root or with sudo
if [ "$EUID" -ne 0 ]; then
    echo -e "${RED}ERROR: This script must be run as root or with sudo${NC}"
    echo "Usage: sudo ./scripts/deploy_changes.sh"
    exit 1
fi

echo -e "${BLUE}╔════════════════════════════════════════╗${NC}"
echo -e "${BLUE}║  3DDreamCrafts Deployment Script      ║${NC}"
echo -e "${BLUE}╚════════════════════════════════════════╝${NC}"
echo ""
echo -e "  Running as:  ${GREEN}$ACTUAL_USER${NC}"
echo -e "  Git repo:    ${GREEN}$GIT_REPO_DIR${NC}"
echo -e "  Web root:    ${GREEN}$WEB_ROOT${NC}"
echo ""

# Verify git repository exists
if [ ! -d "$GIT_REPO_DIR" ]; then
    echo -e "${RED}ERROR: Git repository not found at $GIT_REPO_DIR${NC}"
    echo ""
    echo "Please specify the correct path to your git repository:"
    echo "  Option 1: Set GIT_REPO_DIR environment variable"
    echo "    ${GREEN}sudo GIT_REPO_DIR=/path/to/repo ./scripts/deploy_changes.sh${NC}"
    echo ""
    echo "  Option 2: Edit the script and change GIT_REPO_DIR"
    echo ""
    read -p "Enter git repository path (or press Enter to exit): " CUSTOM_REPO
    if [ -n "$CUSTOM_REPO" ] && [ -d "$CUSTOM_REPO" ]; then
        GIT_REPO_DIR="$CUSTOM_REPO"
        echo -e "${GREEN}✓ Using: $GIT_REPO_DIR${NC}"
        echo ""
    else
        exit 1
    fi
fi

# Verify web root exists
if [ ! -d "$WEB_ROOT" ]; then
    echo -e "${YELLOW}WARNING: Web root $WEB_ROOT does not exist${NC}"
    read -p "Create it? (y/n) " -n 1 -r
    echo
    if [[ $REPLY =~ ^[Yy]$ ]]; then
        mkdir -p "$WEB_ROOT"
        echo -e "${GREEN}✓ Created web root directory${NC}"
    else
        exit 1
    fi
fi

# Create backup directory if it doesn't exist
mkdir -p "$BACKUP_DIR"

echo -e "${YELLOW}[1/7] Checking git repository status...${NC}"
cd "$GIT_REPO_DIR"

# Show current branch and latest commit
CURRENT_BRANCH=$(git rev-parse --abbrev-ref HEAD)
LATEST_COMMIT=$(git log -1 --pretty=format:"%h - %s (%cr)")
echo -e "  Branch: ${GREEN}$CURRENT_BRANCH${NC}"
echo -e "  Latest commit: ${GREEN}$LATEST_COMMIT${NC}"
echo ""

# Optional: Pull latest changes
read -p "Pull latest changes from remote? (y/n) " -n 1 -r
echo
if [[ $REPLY =~ ^[Yy]$ ]]; then
    echo -e "${YELLOW}Pulling latest changes...${NC}"
    git pull
    echo -e "${GREEN}✓ Git pull completed${NC}"
    echo ""
fi

echo -e "${YELLOW}[2/7] Creating backup of current deployment...${NC}"
if [ -d "$WEB_ROOT" ]; then
    BACKUP_FILE="$BACKUP_DIR/deployment_backup_$TIMESTAMP.tar.gz"
    tar -czf "$BACKUP_FILE" -C "$WEB_ROOT" . 2>/dev/null || true

    if [ -f "$BACKUP_FILE" ]; then
        BACKUP_SIZE=$(du -h "$BACKUP_FILE" | cut -f1)
        echo -e "${GREEN}✓ Backup created: $BACKUP_FILE ($BACKUP_SIZE)${NC}"
    else
        echo -e "${YELLOW}⚠ No backup created (web root may be empty)${NC}"
    fi
else
    echo -e "${YELLOW}⚠ No existing deployment to backup${NC}"
fi
echo ""

echo -e "${YELLOW}[3/7] Copying files to web root...${NC}"

# Copy public directory
echo "  Copying public/ directory..."
rsync -av --delete \
    --exclude='uploads/*' \
    --exclude='.htaccess' \
    "$GIT_REPO_DIR/public/" "$WEB_ROOT/public/"

# Copy includes directory
echo "  Copying includes/ directory..."
mkdir -p "$WEB_ROOT/includes"
rsync -av --delete \
    "$GIT_REPO_DIR/includes/" "$WEB_ROOT/includes/"

# Copy database directory (scripts only, not the database file itself)
echo "  Copying database scripts..."
mkdir -p "$WEB_ROOT/database"
rsync -av \
    --exclude='craftsite.db' \
    --exclude='craftsite.db-shm' \
    --exclude='craftsite.db-wal' \
    --exclude='design_backups/*' \
    "$GIT_REPO_DIR/database/" "$WEB_ROOT/database/"

# Copy scripts directory
echo "  Copying scripts/ directory..."
mkdir -p "$WEB_ROOT/scripts"
rsync -av --delete \
    "$GIT_REPO_DIR/scripts/" "$WEB_ROOT/scripts/"

echo -e "${GREEN}✓ Files copied successfully${NC}"
echo ""

echo -e "${YELLOW}[4/7] Creating required directories...${NC}"

# Create necessary directories with proper permissions
directories=(
    "$WEB_ROOT/public/uploads"
    "$WEB_ROOT/public/uploads/featured"
    "$WEB_ROOT/public/uploads/gallery"
    "$WEB_ROOT/public/uploads/misc"
    "$WEB_ROOT/public/uploads/logos"
    "$WEB_ROOT/database/design_backups"
    "$WEB_ROOT/cache"
    "$WEB_ROOT/logs"
)

for dir in "${directories[@]}"; do
    if [ ! -d "$dir" ]; then
        mkdir -p "$dir"
        echo "  Created: $dir"
    fi
done

echo -e "${GREEN}✓ Directories verified${NC}"
echo ""

echo -e "${YELLOW}[5/7] Setting file permissions...${NC}"

# Set ownership to www-data (Apache user)
chown -R www-data:www-data "$WEB_ROOT"

# Set directory permissions (755 = rwxr-xr-x)
find "$WEB_ROOT" -type d -exec chmod 755 {} \;

# Set file permissions (644 = rw-r--r--)
find "$WEB_ROOT" -type f -exec chmod 644 {} \;

# Make scripts executable (755 = rwxr-xr-x)
if [ -d "$WEB_ROOT/scripts" ]; then
    find "$WEB_ROOT/scripts" -type f -name "*.sh" -exec chmod 755 {} \;
fi

# Ensure writable directories for web server
writable_dirs=(
    "$WEB_ROOT/public/uploads"
    "$WEB_ROOT/database"
    "$WEB_ROOT/cache"
    "$WEB_ROOT/logs"
)

for dir in "${writable_dirs[@]}"; do
    if [ -d "$dir" ]; then
        chmod 775 "$dir"
        chmod -R 775 "$dir"
    fi
done

echo -e "${GREEN}✓ Permissions set${NC}"
echo ""

echo -e "${YELLOW}[6/7] Clearing cache...${NC}"

# Clear file cache if it exists
if [ -d "$WEB_ROOT/cache" ]; then
    rm -rf "$WEB_ROOT/cache"/*
    echo -e "${GREEN}✓ Cache cleared${NC}"
else
    echo -e "${YELLOW}⚠ Cache directory not found${NC}"
fi
echo ""

echo -e "${YELLOW}[7/7] Restarting Apache...${NC}"

# Restart Apache
if systemctl is-active --quiet apache2; then
    systemctl restart apache2
    echo -e "${GREEN}✓ Apache restarted${NC}"
elif systemctl is-active --quiet httpd; then
    systemctl restart httpd
    echo -e "${GREEN}✓ Apache (httpd) restarted${NC}"
else
    echo -e "${YELLOW}⚠ Could not detect Apache service (apache2 or httpd)${NC}"
    echo "  Please restart your web server manually"
fi
echo ""

# Display deployment summary
echo -e "${BLUE}╔════════════════════════════════════════╗${NC}"
echo -e "${BLUE}║       Deployment Summary               ║${NC}"
echo -e "${BLUE}╚════════════════════════════════════════╝${NC}"
echo -e "  Source:      ${GREEN}$GIT_REPO_DIR${NC}"
echo -e "  Destination: ${GREEN}$WEB_ROOT${NC}"
echo -e "  Backup:      ${GREEN}$BACKUP_FILE${NC}"
echo -e "  Status:      ${GREEN}✓ DEPLOYMENT SUCCESSFUL${NC}"
echo ""

# Display next steps
echo -e "${BLUE}Next Steps:${NC}"
echo "  1. Run database update script (if schema changes exist):"
echo "     ${GREEN}sudo ./scripts/update_database.sh${NC}"
echo ""
echo "  2. Test the website:"
echo "     ${GREEN}Visit your website and verify changes${NC}"
echo ""
echo "  3. Check logs if issues occur:"
echo "     ${GREEN}tail -f $WEB_ROOT/logs/error.log${NC}"
echo "     ${GREEN}tail -f /var/log/apache2/error.log${NC}"
echo ""

# Optional: Show files that were changed
read -p "Show list of changed files? (y/n) " -n 1 -r
echo
if [[ $REPLY =~ ^[Yy]$ ]]; then
    echo -e "${YELLOW}Files modified in last commit:${NC}"
    cd "$GIT_REPO_DIR"
    git diff --name-status HEAD~1 HEAD | head -20
    echo ""
fi

echo -e "${GREEN}Deployment completed successfully!${NC}"
