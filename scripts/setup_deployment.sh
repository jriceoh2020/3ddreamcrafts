#!/bin/bash

# 3DDreamCrafts Deployment Setup Script
# This script automates the deployment setup process

# Configuration
DOMAIN_NAME=""  # Set your domain name
WEB_ROOT="/var/www/html"
ADMIN_EMAIL=""  # Set admin email for SSL certificates

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

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
        "ERROR")
            echo -e "${RED}[ERROR]${NC} $message"
            ;;
    esac
}

# Function to check if running as root
check_root() {
    if [ "$EUID" -ne 0 ]; then
        print_status "ERROR" "This script must be run as root"
        exit 1
    fi
}

# Function to update system packages
update_system() {
    print_status "INFO" "Updating system packages..."
    apt update && apt upgrade -y
    print_status "OK" "System packages updated"
}

# Function to install required packages
install_packages() {
    print_status "INFO" "Installing required packages..."
    
    apt install -y \
        apache2 \
        php8.2 \
        php8.2-fpm \
        php8.2-sqlite3 \
        php8.2-gd \
        php8.2-curl \
        php8.2-mbstring \
        php8.2-xml \
        php8.2-zip \
        sqlite3 \
        certbot \
        python3-certbot-apache \
        git \
        unzip \
        bc \
        mailutils \
        logrotate
    
    print_status "OK" "Packages installed"
}

# Function to configure Apache
configure_apache() {
    print_status "INFO" "Configuring Apache..."
    
    # Enable required modules
    a2enmod rewrite ssl headers deflate expires php8.2
    
    # Copy virtual host configuration
    if [ -f "$WEB_ROOT/apache/3ddreamcrafts.conf" ]; then
        cp "$WEB_ROOT/apache/3ddreamcrafts.conf" /etc/apache2/sites-available/
        
        # Update domain name if provided
        if [ -n "$DOMAIN_NAME" ]; then
            sed -i "s/3ddreamcrafts.com/$DOMAIN_NAME/g" /etc/apache2/sites-available/3ddreamcrafts.conf
        fi
        
        # Disable default site and enable new site
        a2dissite 000-default
        a2ensite 3ddreamcrafts
        
        print_status "OK" "Apache configured"
    else
        print_status "ERROR" "Apache configuration file not found"
        return 1
    fi
}

# Function to configure PHP
configure_php() {
    print_status "INFO" "Configuring PHP..."
    
    # Copy PHP configuration files
    if [ -f "$WEB_ROOT/config/php-security.ini" ]; then
        cp "$WEB_ROOT/config/php-security.ini" /etc/php/8.2/fpm/conf.d/99-security.ini
    fi
    
    if [ -f "$WEB_ROOT/config/php-performance.ini" ]; then
        cp "$WEB_ROOT/config/php-performance.ini" /etc/php/8.2/fpm/conf.d/99-performance.ini
    fi
    
    # Create PHP session directory
    mkdir -p /var/lib/php/sessions
    chown www-data:www-data /var/lib/php/sessions
    chmod 700 /var/lib/php/sessions
    
    print_status "OK" "PHP configured"
}

# Function to set up directories and permissions
setup_directories() {
    print_status "INFO" "Setting up directories and permissions..."
    
    # Create log directories
    mkdir -p /var/log/3ddreamcrafts
    chown www-data:www-data /var/log/3ddreamcrafts
    
    # Create backup directories
    mkdir -p /var/backups/3ddreamcrafts
    chown www-data:www-data /var/backups/3ddreamcrafts
    
    # Set web directory permissions
    chown -R www-data:www-data "$WEB_ROOT"
    find "$WEB_ROOT" -type d -exec chmod 755 {} \;
    find "$WEB_ROOT" -type f -exec chmod 644 {} \;
    
    # Make scripts executable
    chmod +x "$WEB_ROOT"/scripts/*.sh
    
    # Secure sensitive directories
    chmod 700 "$WEB_ROOT/database/"
    chmod 700 "$WEB_ROOT/includes/"
    
    print_status "OK" "Directories and permissions set"
}

# Function to initialize database
initialize_database() {
    print_status "INFO" "Initializing database..."
    
    if [ -f "$WEB_ROOT/database/init_database.php" ]; then
        cd "$WEB_ROOT/database"
        php init_database.php
        
        # Set database permissions
        chown www-data:www-data craftsite.db
        chmod 644 craftsite.db
        
        print_status "OK" "Database initialized"
    else
        print_status "ERROR" "Database initialization script not found"
        return 1
    fi
}

# Function to set up cron jobs
setup_cron() {
    print_status "INFO" "Setting up cron jobs..."
    
    if [ -f "$WEB_ROOT/config/crontab.txt" ]; then
        # Add cron jobs for www-data user
        crontab -u www-data "$WEB_ROOT/config/crontab.txt"
        print_status "OK" "Cron jobs configured"
    else
        print_status "WARNING" "Crontab configuration file not found"
    fi
}

# Function to configure firewall
configure_firewall() {
    print_status "INFO" "Configuring firewall..."
    
    # Enable UFW if not already enabled
    ufw --force enable
    
    # Allow SSH, HTTP, and HTTPS
    ufw allow ssh
    ufw allow 'Apache Full'
    
    print_status "OK" "Firewall configured"
}

# Function to start services
start_services() {
    print_status "INFO" "Starting services..."
    
    # Start and enable services
    systemctl start apache2
    systemctl enable apache2
    systemctl start php8.2-fpm
    systemctl enable php8.2-fpm
    
    # Test Apache configuration
    if apache2ctl configtest; then
        systemctl restart apache2
        print_status "OK" "Services started"
    else
        print_status "ERROR" "Apache configuration test failed"
        return 1
    fi
}

# Function to set up SSL certificate
setup_ssl() {
    if [ -n "$DOMAIN_NAME" ] && [ -n "$ADMIN_EMAIL" ]; then
        print_status "INFO" "Setting up SSL certificate..."
        
        # Install SSL certificate
        certbot --apache -d "$DOMAIN_NAME" -d "www.$DOMAIN_NAME" \
                --email "$ADMIN_EMAIL" \
                --agree-tos \
                --non-interactive
        
        if [ $? -eq 0 ]; then
            print_status "OK" "SSL certificate installed"
        else
            print_status "WARNING" "SSL certificate installation failed"
        fi
    else
        print_status "INFO" "Skipping SSL setup (domain name or email not configured)"
    fi
}

# Function to run initial health check
run_health_check() {
    print_status "INFO" "Running initial health check..."
    
    if [ -f "$WEB_ROOT/scripts/health_check.sh" ]; then
        "$WEB_ROOT/scripts/health_check.sh"
    else
        print_status "WARNING" "Health check script not found"
    fi
}

# Function to display completion message
show_completion() {
    echo ""
    echo "========================================"
    print_status "OK" "Deployment setup completed!"
    echo "========================================"
    echo ""
    echo "Next steps:"
    echo "1. Create admin user: cd $WEB_ROOT/admin && php create_admin.php"
    echo "2. Configure domain name and SSL if not done automatically"
    echo "3. Test website functionality"
    echo "4. Set up monitoring alerts by configuring email in scripts"
    echo ""
    echo "Important files:"
    echo "- Website: http://$(hostname -I | awk '{print $1}')"
    echo "- Admin panel: http://$(hostname -I | awk '{print $1}')/admin/"
    echo "- Logs: /var/log/3ddreamcrafts/"
    echo "- Backups: /var/backups/3ddreamcrafts/"
    echo ""
    echo "For SSL setup, run:"
    echo "sudo certbot --apache -d yourdomain.com -d www.yourdomain.com"
    echo ""
}

# Main setup function
main() {
    echo "========================================"
    echo "3DDreamCrafts Deployment Setup"
    echo "========================================"
    echo ""
    
    # Check if configuration is needed
    if [ -z "$DOMAIN_NAME" ]; then
        print_status "WARNING" "Domain name not configured in script"
        read -p "Enter domain name (or press Enter to skip): " DOMAIN_NAME
    fi
    
    if [ -z "$ADMIN_EMAIL" ]; then
        print_status "WARNING" "Admin email not configured in script"
        read -p "Enter admin email for SSL certificates (or press Enter to skip): " ADMIN_EMAIL
    fi
    
    # Run setup steps
    check_root
    update_system
    install_packages
    configure_apache
    configure_php
    setup_directories
    initialize_database
    setup_cron
    configure_firewall
    start_services
    
    # Optional SSL setup
    if [ -n "$DOMAIN_NAME" ] && [ -n "$ADMIN_EMAIL" ]; then
        setup_ssl
    fi
    
    run_health_check
    show_completion
}

# Run main function
main "$@"