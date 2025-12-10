# 3DDreamCrafts - AWS EC2 Ubuntu Deployment Guide

This guide explains how to deploy the 3DDreamCrafts website on a fresh AWS EC2 Ubuntu server.

## Prerequisites

- AWS EC2 instance running Ubuntu 20.04 LTS or 22.04 LTS
- SSH access to the server with root/sudo privileges
- Your EC2 instance's public IP address or domain name

## Quick Deployment

### Step 1: Connect to Your EC2 Instance

```bash
ssh -i your-key.pem ubuntu@your-ec2-ip-address
```

### Step 2: Upload Website Files

Option A - Using SCP from your local machine:
```bash
# From your local machine (not EC2)
scp -i your-key.pem -r /path/to/3ddreamcrafts ubuntu@your-ec2-ip:/home/ubuntu/
```

Option B - Using Git (if your code is in a repository):
```bash
# On your EC2 instance
git clone https://github.com/yourusername/3ddreamcrafts.git
cd 3ddreamcrafts
```

### Step 3: Run the Deployment Script

```bash
# Navigate to the website directory
cd /home/ubuntu/3ddreamcrafts

# Make the script executable
chmod +x deploy_to_ubuntu.sh

# Run the script with your domain or IP
sudo ./deploy_to_ubuntu.sh your-domain.com
# OR
sudo ./deploy_to_ubuntu.sh your-ec2-ip-address
```

The script will:
1. Update system packages
2. Install Apache web server
3. Install PHP 8.1 and all required extensions
4. Install SQLite3
5. Configure PHP settings
6. Set up the directory structure
7. Set proper file permissions
8. Initialize the database
9. Configure Apache virtual host
10. Set up security headers
11. Configure firewall (UFW)
12. Set up automated maintenance tasks

### Step 4: Verify Installation

After the script completes, visit:
- **Website**: `http://your-domain-or-ip`
- **Admin Panel**: `http://your-domain-or-ip/admin/`

Default admin credentials:
- Username: `admin`
- Password: `admin123`

**⚠️ CHANGE THESE IMMEDIATELY AFTER FIRST LOGIN!**

## Post-Installation Steps

### 1. Change Admin Password

1. Login to admin panel at `http://your-domain-or-ip/admin/`
2. Navigate to settings
3. Change the default password

### 2. Configure Production Settings

Edit `/var/www/3ddreamcrafts/includes/config.php`:

```php
// Set debug mode to false for production
define('DEBUG_MODE', false);

// Verify timezone is correct
define('TIMEZONE', 'America/New_York');  // Change to your timezone
```

### 3. Set Up SSL Certificate (Recommended)

For production, set up HTTPS with Let's Encrypt:

```bash
# Install Certbot
sudo apt-get install certbot python3-certbot-apache

# Obtain and install certificate
sudo certbot --apache -d your-domain.com

# Auto-renewal is set up automatically
# Test renewal with:
sudo certbot renew --dry-run
```

### 4. Configure AWS Security Group

In AWS Console, configure your EC2 instance's Security Group to allow:
- **SSH (Port 22)**: Your IP address only (for security)
- **HTTP (Port 80)**: 0.0.0.0/0 (anywhere)
- **HTTPS (Port 443)**: 0.0.0.0/0 (anywhere) - if using SSL

## Directory Structure on Server

```
/var/www/3ddreamcrafts/
├── public/              # Web root (DocumentRoot)
│   ├── index.php
│   ├── admin/          # Admin panel
│   └── uploads/        # Writable - user uploads
├── includes/           # Protected - PHP classes
├── database/           # Protected - SQLite database
│   └── craftsite.db    # Writable - database file
├── cache/              # Writable - cache files
├── logs/               # Writable - application logs
├── scripts/            # Protected - maintenance scripts
└── tests/              # Protected - test suites
```

## Useful Commands

### Apache Management

```bash
# Check status
sudo systemctl status apache2

# Restart Apache
sudo systemctl restart apache2

# Reload configuration
sudo systemctl reload apache2

# View error logs
sudo tail -f /var/log/apache2/3ddreamcrafts_error.log

# View access logs
sudo tail -f /var/log/apache2/3ddreamcrafts_access.log
```

### Database Management

```bash
# Access database
sqlite3 /var/www/3ddreamcrafts/database/craftsite.db

# Backup database
sudo -u www-data /var/www/3ddreamcrafts/scripts/backup_database.sh

# Restore database
sudo -u www-data /var/www/3ddreamcrafts/scripts/restore_database.sh
```

### Application Maintenance

```bash
# Clear cache
sudo rm -rf /var/www/3ddreamcrafts/cache/*.cache

# View PHP errors
sudo tail -f /var/log/php_errors.log

# Run tests
php /var/www/3ddreamcrafts/tests/run_tests.php

# Check system health
php /var/www/3ddreamcrafts/verify_setup.php
```

### File Permissions

If you need to reset permissions:

```bash
# Set ownership
sudo chown -R www-data:www-data /var/www/3ddreamcrafts

# Set directory permissions
sudo find /var/www/3ddreamcrafts -type d -exec chmod 755 {} \;
sudo find /var/www/3ddreamcrafts -type f -exec chmod 644 {} \;

# Set writable directories
sudo chmod -R 775 /var/www/3ddreamcrafts/database
sudo chmod -R 775 /var/www/3ddreamcrafts/public/uploads
sudo chmod -R 775 /var/www/3ddreamcrafts/cache
sudo chmod -R 775 /var/www/3ddreamcrafts/logs
```

## Automated Maintenance

The deployment script sets up a daily cron job at `/etc/cron.daily/3ddreamcrafts-maintenance` that:
- Cleans login attempts older than 2 days
- Removes performance logs older than 30 days
- Deletes expired cache files

## Troubleshooting

### Website Not Loading

1. Check Apache is running:
   ```bash
   sudo systemctl status apache2
   ```

2. Check error logs:
   ```bash
   sudo tail -50 /var/log/apache2/3ddreamcrafts_error.log
   ```

3. Verify DocumentRoot:
   ```bash
   cat /etc/apache2/sites-enabled/3ddreamcrafts.conf
   ```

### Database Errors

1. Check database file exists:
   ```bash
   ls -la /var/www/3ddreamcrafts/database/craftsite.db
   ```

2. Verify permissions:
   ```bash
   # Should be owned by www-data with 664 permissions
   sudo chown www-data:www-data /var/www/3ddreamcrafts/database/craftsite.db
   sudo chmod 664 /var/www/3ddreamcrafts/database/craftsite.db
   ```

3. Reinitialize database:
   ```bash
   cd /var/www/3ddreamcrafts/database
   sudo -u www-data bash deploy.sh
   ```

### Upload Errors

1. Check upload directory permissions:
   ```bash
   ls -la /var/www/3ddreamcrafts/public/uploads
   ```

2. Verify PHP upload settings:
   ```bash
   php -i | grep upload_max_filesize
   php -i | grep post_max_size
   ```

### Permission Denied Errors

```bash
# Reset all permissions
sudo chown -R www-data:www-data /var/www/3ddreamcrafts
sudo chmod -R 775 /var/www/3ddreamcrafts/database
sudo chmod -R 775 /var/www/3ddreamcrafts/public/uploads
sudo chmod -R 775 /var/www/3ddreamcrafts/cache
sudo chmod -R 775 /var/www/3ddreamcrafts/logs
```

## Performance Optimization

### Enable OPcache

Edit `/etc/php/8.1/apache2/php.ini`:

```ini
[opcache]
opcache.enable=1
opcache.memory_consumption=128
opcache.interned_strings_buffer=8
opcache.max_accelerated_files=10000
opcache.revalidate_freq=60
```

Restart Apache:
```bash
sudo systemctl restart apache2
```

### Apply Database Optimizations

```bash
php /var/www/3ddreamcrafts/database/apply_optimizations.php
```

## Backup Strategy

### Manual Backup

```bash
# Backup database
sudo cp /var/www/3ddreamcrafts/database/craftsite.db \
        /var/www/3ddreamcrafts/database/backup_$(date +%Y%m%d_%H%M%S).db

# Backup entire site
sudo tar -czf /home/ubuntu/3ddreamcrafts_backup_$(date +%Y%m%d).tar.gz \
              /var/www/3ddreamcrafts/
```

### Automated Backups

Set up automated backups using cron:

```bash
# Edit crontab
sudo crontab -e

# Add daily backup at 2 AM
0 2 * * * /var/www/3ddreamcrafts/scripts/backup_database.sh
```

## Monitoring

### Monitor Server Resources

```bash
# CPU and memory
htop

# Disk usage
df -h

# Apache status
systemctl status apache2
```

### Monitor Application Performance

View performance logs:
```bash
cat /var/www/3ddreamcrafts/logs/performance.log
```

### Monitor Security

View security logs:
```bash
sqlite3 /var/www/3ddreamcrafts/database/craftsite.db \
  "SELECT * FROM security_log ORDER BY created_at DESC LIMIT 50;"
```

## Updating the Application

```bash
# Backup current version
sudo tar -czf /home/ubuntu/backup_before_update_$(date +%Y%m%d).tar.gz \
              /var/www/3ddreamcrafts/

# Upload new files
# ... upload new version ...

# Set permissions
sudo chown -R www-data:www-data /var/www/3ddreamcrafts
sudo chmod -R 775 /var/www/3ddreamcrafts/database
sudo chmod -R 775 /var/www/3ddreamcrafts/cache

# Clear cache
sudo rm -rf /var/www/3ddreamcrafts/cache/*.cache

# Restart Apache
sudo systemctl restart apache2
```

## Security Checklist

- [ ] Changed default admin password
- [ ] Set `DEBUG_MODE = false` in production
- [ ] Configured firewall (UFW)
- [ ] Set up SSL certificate
- [ ] Restricted SSH access to your IP only
- [ ] Regular backups configured
- [ ] Security logs monitored regularly
- [ ] Keep system packages updated: `sudo apt-get update && sudo apt-get upgrade`

## Support

For issues or questions:
- Review logs: `/var/log/apache2/3ddreamcrafts_error.log`
- Run verification: `php /var/www/3ddreamcrafts/verify_setup.php`
- Check PHP errors: `/var/log/php_errors.log`
