#!/bin/bash
###############################################################################
# 3DDreamCrafts - AWS EC2 Ubuntu Server Deployment Script
# This script sets up a fresh Ubuntu server with all dependencies and
# configurations needed to run the 3DDreamCrafts PHP website
###############################################################################

set -e  # Exit on any error

# Color codes for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Configuration variables
INSTALL_DIR="/var/www/3ddreamcrafts"
WEB_USER="www-data"
DOMAIN_OR_IP="${1:-localhost}"  # First argument or localhost
PHP_VERSION="8.1"

echo -e "${GREEN}========================================${NC}"
echo -e "${GREEN}3DDreamCrafts Deployment Script${NC}"
echo -e "${GREEN}========================================${NC}"
echo ""

# Check if running as root
if [[ $EUID -ne 0 ]]; then
   echo -e "${RED}Error: This script must be run as root${NC}"
   echo "Please run with: sudo bash deploy_to_ubuntu.sh [your-domain-or-ip]"
   exit 1
fi

echo -e "${YELLOW}Installation directory: ${INSTALL_DIR}${NC}"
echo -e "${YELLOW}Domain/IP: ${DOMAIN_OR_IP}${NC}"
echo -e "${YELLOW}PHP Version: ${PHP_VERSION}${NC}"
echo ""

read -p "Continue with installation? (y/n) " -n 1 -r
echo
if [[ ! $REPLY =~ ^[Yy]$ ]]; then
    echo "Installation cancelled."
    exit 1
fi

###############################################################################
# Step 1: Update system packages
###############################################################################
echo -e "${GREEN}Step 1: Updating system packages...${NC}"
apt-get update
apt-get upgrade -y

###############################################################################
# Step 2: Install Apache web server
###############################################################################
echo -e "${GREEN}Step 2: Installing Apache web server...${NC}"
apt-get install -y apache2
systemctl enable apache2

###############################################################################
# Step 3: Install PHP and required extensions
###############################################################################
echo -e "${GREEN}Step 3: Installing PHP ${PHP_VERSION} and extensions...${NC}"

# Add PHP repository if needed
apt-get install -y software-properties-common
add-apt-repository -y ppa:ondrej/php
apt-get update

# Install PHP and extensions
apt-get install -y \
    php${PHP_VERSION} \
    php${PHP_VERSION}-cli \
    php${PHP_VERSION}-common \
    php${PHP_VERSION}-sqlite3 \
    php${PHP_VERSION}-mbstring \
    php${PHP_VERSION}-xml \
    php${PHP_VERSION}-curl \
    php${PHP_VERSION}-gd \
    php${PHP_VERSION}-zip \
    php${PHP_VERSION}-bcmath \
    libapache2-mod-php${PHP_VERSION}

# Enable PHP module
a2enmod php${PHP_VERSION}

###############################################################################
# Step 4: Install SQLite3
###############################################################################
echo -e "${GREEN}Step 4: Installing SQLite3...${NC}"
apt-get install -y sqlite3 libsqlite3-dev

###############################################################################
# Step 5: Install additional utilities
###############################################################################
echo -e "${GREEN}Step 5: Installing additional utilities...${NC}"
apt-get install -y git unzip curl wget

###############################################################################
# Step 6: Configure PHP settings
###############################################################################
echo -e "${GREEN}Step 6: Configuring PHP settings...${NC}"

PHP_INI="/etc/php/${PHP_VERSION}/apache2/php.ini"

# Backup original php.ini
cp ${PHP_INI} ${PHP_INI}.backup

# Update PHP settings for the application
sed -i 's/upload_max_filesize = .*/upload_max_filesize = 10M/' ${PHP_INI}
sed -i 's/post_max_size = .*/post_max_size = 10M/' ${PHP_INI}
sed -i 's/max_execution_time = .*/max_execution_time = 300/' ${PHP_INI}
sed -i 's/memory_limit = .*/memory_limit = 256M/' ${PHP_INI}
sed -i 's/;date.timezone =.*/date.timezone = America\/New_York/' ${PHP_INI}

# Enable error logging
sed -i 's/display_errors = .*/display_errors = Off/' ${PHP_INI}
sed -i 's/log_errors = .*/log_errors = On/' ${PHP_INI}
sed -i 's/;error_log = .*/error_log = \/var\/log\/php_errors.log/' ${PHP_INI}

echo -e "${GREEN}PHP configuration updated${NC}"

###############################################################################
# Step 7: Create installation directory and set up file structure
###############################################################################
echo -e "${GREEN}Step 7: Creating installation directory...${NC}"

# Create main directory if it doesn't exist
mkdir -p ${INSTALL_DIR}

# If the current directory contains the website files, copy them
if [ -f "./includes/database.php" ]; then
    echo -e "${YELLOW}Copying website files from current directory...${NC}"
    cp -r . ${INSTALL_DIR}/
    cd ${INSTALL_DIR}
else
    echo -e "${YELLOW}Please ensure website files are in: ${INSTALL_DIR}${NC}"
    cd ${INSTALL_DIR}
fi

# Create required writable directories
mkdir -p ${INSTALL_DIR}/database
mkdir -p ${INSTALL_DIR}/public/uploads
mkdir -p ${INSTALL_DIR}/cache
mkdir -p ${INSTALL_DIR}/logs

###############################################################################
# Step 8: Set proper file permissions
###############################################################################
echo -e "${GREEN}Step 8: Setting file permissions...${NC}"

# Set ownership to web server user
chown -R ${WEB_USER}:${WEB_USER} ${INSTALL_DIR}

# Set directory permissions
find ${INSTALL_DIR} -type d -exec chmod 755 {} \;
find ${INSTALL_DIR} -type f -exec chmod 644 {} \;

# Make writable directories writable by web server
chmod -R 775 ${INSTALL_DIR}/database
chmod -R 775 ${INSTALL_DIR}/public/uploads
chmod -R 775 ${INSTALL_DIR}/cache
chmod -R 775 ${INSTALL_DIR}/logs

# Make scripts executable
if [ -d "${INSTALL_DIR}/scripts" ]; then
    chmod +x ${INSTALL_DIR}/scripts/*.sh
fi
if [ -d "${INSTALL_DIR}/database" ]; then
    chmod +x ${INSTALL_DIR}/database/deploy.sh 2>/dev/null || true
fi

echo -e "${GREEN}File permissions set${NC}"

###############################################################################
# Step 9: Initialize database
###############################################################################
echo -e "${GREEN}Step 9: Initializing database...${NC}"

if [ -f "${INSTALL_DIR}/database/deploy.sh" ]; then
    cd ${INSTALL_DIR}/database
    sudo -u ${WEB_USER} bash deploy.sh
    cd ${INSTALL_DIR}
elif [ -f "${INSTALL_DIR}/database/init_database.php" ]; then
    sudo -u ${WEB_USER} php ${INSTALL_DIR}/database/init_database.php
else
    echo -e "${YELLOW}Warning: Database initialization scripts not found${NC}"
    echo -e "${YELLOW}Please run database initialization manually${NC}"
fi

# Ensure database file has correct permissions
if [ -f "${INSTALL_DIR}/database/craftsite.db" ]; then
    chown ${WEB_USER}:${WEB_USER} ${INSTALL_DIR}/database/craftsite.db
    chmod 664 ${INSTALL_DIR}/database/craftsite.db
fi

###############################################################################
# Step 10: Configure Apache Virtual Host
###############################################################################
echo -e "${GREEN}Step 10: Configuring Apache virtual host...${NC}"

VHOST_FILE="/etc/apache2/sites-available/3ddreamcrafts.conf"

cat > ${VHOST_FILE} << EOF
<VirtualHost *:80>
    ServerName ${DOMAIN_OR_IP}
    ServerAdmin admin@${DOMAIN_OR_IP}
    DocumentRoot ${INSTALL_DIR}/public

    <Directory ${INSTALL_DIR}/public>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    # Deny access to sensitive directories
    <Directory ${INSTALL_DIR}/includes>
        Require all denied
    </Directory>

    <Directory ${INSTALL_DIR}/database>
        Require all denied
    </Directory>

    <Directory ${INSTALL_DIR}/cache>
        Require all denied
    </Directory>

    <Directory ${INSTALL_DIR}/config>
        Require all denied
    </Directory>

    <Directory ${INSTALL_DIR}/scripts>
        Require all denied
    </Directory>

    <Directory ${INSTALL_DIR}/tests>
        Require all denied
    </Directory>

    # Logging
    ErrorLog \${APACHE_LOG_DIR}/3ddreamcrafts_error.log
    CustomLog \${APACHE_LOG_DIR}/3ddreamcrafts_access.log combined

    # Security headers
    Header always set X-Frame-Options "SAMEORIGIN"
    Header always set X-Content-Type-Options "nosniff"
    Header always set X-XSS-Protection "1; mode=block"
</VirtualHost>
EOF

# Enable required Apache modules
a2enmod rewrite
a2enmod headers
a2enmod expires

# Disable default site and enable new site
a2dissite 000-default.conf
a2ensite 3ddreamcrafts.conf

###############################################################################
# Step 11: Create .htaccess file in public directory
###############################################################################
echo -e "${GREEN}Step 11: Creating .htaccess file...${NC}"

cat > ${INSTALL_DIR}/public/.htaccess << 'EOF'
# 3DDreamCrafts .htaccess Configuration

# Enable rewrite engine
RewriteEngine On

# Security: Prevent access to files starting with dot (except .well-known)
RewriteRule ^\.(?!well-known) - [F]

# Security headers
<IfModule mod_headers.c>
    Header set X-Frame-Options "SAMEORIGIN"
    Header set X-Content-Type-Options "nosniff"
    Header set X-XSS-Protection "1; mode=block"
</IfModule>

# PHP security settings
<IfModule mod_php8.c>
    php_flag display_errors Off
    php_flag log_errors On
</IfModule>

# Disable directory browsing
Options -Indexes

# Cache control for static assets
<IfModule mod_expires.c>
    ExpiresActive On
    ExpiresByType image/jpg "access plus 1 year"
    ExpiresByType image/jpeg "access plus 1 year"
    ExpiresByType image/gif "access plus 1 year"
    ExpiresByType image/png "access plus 1 year"
    ExpiresByType image/webp "access plus 1 year"
    ExpiresByType text/css "access plus 1 month"
    ExpiresByType application/javascript "access plus 1 month"
</IfModule>

# Compression
<IfModule mod_deflate.c>
    AddOutputFilterByType DEFLATE text/html text/plain text/xml text/css text/javascript application/javascript application/json
</IfModule>
EOF

chown ${WEB_USER}:${WEB_USER} ${INSTALL_DIR}/public/.htaccess

###############################################################################
# Step 12: Configure firewall (UFW)
###############################################################################
echo -e "${GREEN}Step 12: Configuring firewall...${NC}"

# Install UFW if not present
apt-get install -y ufw

# Allow SSH, HTTP, and HTTPS
ufw allow ssh
ufw allow 'Apache Full'

# Enable firewall (with auto-confirm)
echo "y" | ufw enable

echo -e "${GREEN}Firewall configured${NC}"

###############################################################################
# Step 13: Restart Apache
###############################################################################
echo -e "${GREEN}Step 13: Restarting Apache...${NC}"
systemctl restart apache2

###############################################################################
# Step 14: Run verification
###############################################################################
echo -e "${GREEN}Step 14: Running verification checks...${NC}"

if [ -f "${INSTALL_DIR}/verify_setup.php" ]; then
    php ${INSTALL_DIR}/verify_setup.php
fi

###############################################################################
# Step 15: Set up maintenance scripts
###############################################################################
echo -e "${GREEN}Step 15: Setting up maintenance tasks...${NC}"

# Create a daily cron job for cache cleanup
CRON_FILE="/etc/cron.daily/3ddreamcrafts-maintenance"

cat > ${CRON_FILE} << EOF
#!/bin/bash
# 3DDreamCrafts daily maintenance tasks

# Clean old login attempts
sqlite3 ${INSTALL_DIR}/database/craftsite.db "DELETE FROM login_attempts WHERE attempt_time < datetime('now', '-2 days');"

# Clean performance logs older than 30 days
if [ -f "${INSTALL_DIR}/logs/performance.log" ]; then
    find ${INSTALL_DIR}/logs -name "*.log" -type f -mtime +30 -delete
fi

# Clean expired cache files
find ${INSTALL_DIR}/cache -name "*.cache" -type f -mtime +7 -delete
EOF

chmod +x ${CRON_FILE}

echo -e "${GREEN}Maintenance cron job created${NC}"

###############################################################################
# Installation Complete
###############################################################################
echo ""
echo -e "${GREEN}========================================${NC}"
echo -e "${GREEN}Installation Complete!${NC}"
echo -e "${GREEN}========================================${NC}"
echo ""
echo -e "${GREEN}Your 3DDreamCrafts website is now installed and running.${NC}"
echo ""
echo -e "${YELLOW}Important Information:${NC}"
echo -e "  Installation Directory: ${INSTALL_DIR}"
echo -e "  Website URL: http://${DOMAIN_OR_IP}"
echo -e "  Admin Panel: http://${DOMAIN_OR_IP}/admin/"
echo ""
echo -e "${YELLOW}Default Admin Credentials:${NC}"
echo -e "  Username: ${RED}admin${NC}"
echo -e "  Password: ${RED}admin123${NC}"
echo -e "  ${RED}*** CHANGE THESE IMMEDIATELY AFTER FIRST LOGIN! ***${NC}"
echo ""
echo -e "${YELLOW}Apache Configuration:${NC}"
echo -e "  Virtual Host: ${VHOST_FILE}"
echo -e "  Error Log: /var/log/apache2/3ddreamcrafts_error.log"
echo -e "  Access Log: /var/log/apache2/3ddreamcrafts_access.log"
echo ""
echo -e "${YELLOW}Important Directories:${NC}"
echo -e "  Database: ${INSTALL_DIR}/database/craftsite.db"
echo -e "  Uploads: ${INSTALL_DIR}/public/uploads/"
echo -e "  Cache: ${INSTALL_DIR}/cache/"
echo -e "  Logs: ${INSTALL_DIR}/logs/"
echo ""
echo -e "${YELLOW}Next Steps:${NC}"
echo -e "  1. Visit http://${DOMAIN_OR_IP} to see your website"
echo -e "  2. Login to admin panel at http://${DOMAIN_OR_IP}/admin/"
echo -e "  3. Change the default admin password"
echo -e "  4. Update site settings in includes/config.php:"
echo -e "     - Set DEBUG_MODE to false for production"
echo -e "     - Configure timezone if needed"
echo -e "  5. (Optional) Set up SSL certificate with Let's Encrypt:"
echo -e "     sudo apt-get install certbot python3-certbot-apache"
echo -e "     sudo certbot --apache -d ${DOMAIN_OR_IP}"
echo ""
echo -e "${YELLOW}Useful Commands:${NC}"
echo -e "  Check Apache status: systemctl status apache2"
echo -e "  Restart Apache: sudo systemctl restart apache2"
echo -e "  View error logs: tail -f /var/log/apache2/3ddreamcrafts_error.log"
echo -e "  View PHP errors: tail -f /var/log/php_errors.log"
echo -e "  Run tests: php ${INSTALL_DIR}/tests/run_tests.php"
echo ""
echo -e "${GREEN}Deployment completed successfully!${NC}"
echo ""
