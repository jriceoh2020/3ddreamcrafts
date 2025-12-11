# Deployment Scripts

This directory contains scripts for deploying code changes and updating the database for the 3DDreamCrafts website.

## Scripts Overview

### 1. `deploy_changes.sh` - Code Deployment
Copies the latest changes from your git repository to the Apache web root.

**What it does:**
- Creates backup of current deployment
- Copies files from `~/3ddreamcrafts` to `/var/www/3ddreamcrafts/public`
- Preserves uploads and configuration files
- Creates required directories (uploads/logos, cache, logs, etc.)
- Sets proper permissions for Apache
- Clears cache
- Restarts Apache

**Usage:**
```bash
cd ~/3ddreamcrafts
sudo ./scripts/deploy_changes.sh
```

### 2. `update_database.sh` - Database Updates
Applies database schema changes and verifies integrity.

**What it does:**
- Creates backup of database
- Checks database schema for required tables
- Adds missing settings (like `site_logo`)
- Runs integrity checks
- Optimizes database (VACUUM, ANALYZE)
- Sets proper permissions
- Creates logos upload directory

**Usage:**
```bash
cd ~/3ddreamcrafts
sudo ./scripts/update_database.sh
```

## Typical Deployment Workflow

### For the Logo Feature (Current Update)

```bash
# 1. SSH into your server
ssh user@your-server

# 2. Navigate to git repository
cd ~/3ddreamcrafts

# 3. Pull latest changes (if needed)
git pull origin main

# 4. Deploy code changes
sudo ./scripts/deploy_changes.sh

# 5. Update database (creates logo setting and uploads directory)
sudo ./scripts/update_database.sh

# 6. Verify deployment
# Visit: https://your-domain.com/admin/settings/design.php
```

### For Future Updates

```bash
# Always run in this order:
sudo ./scripts/deploy_changes.sh     # Deploy code first
sudo ./scripts/update_database.sh    # Then update database
```

## Important Notes

### File Locations

- **Git Repository:** `~/3ddreamcrafts`
- **Web Root:** `/var/www/3ddreamcrafts`
- **Database:** `/var/www/3ddreamcrafts/database/craftsite.db`
- **Deployment Backups:** `~/3ddreamcrafts_backups/`
- **Database Backups:** `~/3ddreamcrafts_db_backups/`

### Permissions

Both scripts require `sudo` because they:
- Write to `/var/www/` directory
- Change file ownership to `www-data`
- Restart Apache service

### Backups

- **Automatic:** Both scripts create timestamped backups before making changes
- **Retention:** Database script keeps last 10 backups automatically
- **Manual Restore:** Backups can be restored manually if needed

### What Gets Excluded

The deployment script excludes:
- `uploads/*` - User-uploaded files (preserved)
- `.htaccess` - Server configuration (preserved)
- `craftsite.db` - Database file (not overwritten)
- `design_backups/*` - Design setting backups (preserved)

## Logo Feature Specifics

For the logo upload feature you just deployed:

### Database Changes
**None required!** The feature uses the existing `settings` table.

The update script will:
- Add `site_logo` setting if it doesn't exist (default: empty)
- Create `/var/www/3ddreamcrafts/public/uploads/logos/` directory
- Set proper permissions (775, writable by Apache)

### Files Modified
- `includes/content.php` - Added `site_logo` to whitelist
- `includes/design-backup.php` - Added `site_logo` to backups
- `public/admin/settings/design.php` - Added upload UI and handlers
- `public/index.php` - Added header logo display + CSS
- `public/shows.php` - Added header logo display + CSS
- `public/news.php` - Added header logo display + CSS

### After Deployment

1. **Test Admin Panel:**
   ```
   https://your-domain.com/admin/settings/design.php
   ```
   - Should see new "Site Logo" section
   - Can upload PNG/JPG/GIF/WEBP files (max 5MB)

2. **Upload Logo:**
   - Recommended: PNG with transparency, 400px wide
   - Uploads to `/var/www/3ddreamcrafts/public/uploads/logos/`

3. **Verify Display:**
   - Logo appears in header navigation (50px height)
   - Logo appears in hero section (120px height)
   - Falls back to text if no logo uploaded

## Troubleshooting

### "Permission denied" errors
```bash
# Ensure scripts are executable
chmod +x ~/3ddreamcrafts/scripts/*.sh

# Run with sudo
sudo ./scripts/deploy_changes.sh
```

### Apache won't restart
```bash
# Check Apache configuration
sudo apache2ctl configtest

# View error logs
sudo tail -f /var/log/apache2/error.log
```

### Database locked errors
```bash
# Stop Apache temporarily
sudo systemctl stop apache2

# Run database update
sudo ./scripts/update_database.sh

# Start Apache
sudo systemctl start apache2
```

### Logo upload fails
```bash
# Check directory exists and is writable
ls -la /var/www/3ddreamcrafts/public/uploads/logos/

# Should show: drwxrwxr-x www-data www-data

# Fix permissions if needed
sudo chown -R www-data:www-data /var/www/3ddreamcrafts/public/uploads
sudo chmod -R 775 /var/www/3ddreamcrafts/public/uploads
```

### Check deployment logs
```bash
# Apache error log
sudo tail -f /var/log/apache2/error.log

# Application logs (if created)
sudo tail -f /var/www/3ddreamcrafts/logs/error.log
```

## Rollback Procedure

If deployment causes issues:

### Rollback Code Changes
```bash
# List available backups
ls -lh ~/3ddreamcrafts_backups/

# Restore from backup (replace TIMESTAMP)
cd /var/www/3ddreamcrafts
sudo tar -xzf ~/3ddreamcrafts_backups/deployment_backup_TIMESTAMP.tar.gz

# Restart Apache
sudo systemctl restart apache2
```

### Rollback Database Changes
```bash
# List available backups
ls -lh ~/3ddreamcrafts_db_backups/

# Stop Apache
sudo systemctl stop apache2

# Restore database (replace TIMESTAMP)
sudo cp ~/3ddreamcrafts_db_backups/craftsite_backup_TIMESTAMP.db \
    /var/www/3ddreamcrafts/database/craftsite.db

# Fix permissions
sudo chown www-data:www-data /var/www/3ddreamcrafts/database/craftsite.db
sudo chmod 664 /var/www/3ddreamcrafts/database/craftsite.db

# Start Apache
sudo systemctl start apache2
```

## Security Notes

- Both scripts require root/sudo access
- Backups are created in user home directory (not web-accessible)
- Database backups contain sensitive data - protect them
- Scripts validate paths before making changes
- File permissions follow principle of least privilege

## Maintenance

### Clean Old Backups Manually
```bash
# Keep last 10 deployment backups
cd ~/3ddreamcrafts_backups
ls -t deployment_backup_*.tar.gz | tail -n +11 | xargs rm -f

# Database backups are auto-cleaned (last 10 kept)
```

### Verify Deployment
```bash
# Check files were copied
ls -la /var/www/3ddreamcrafts/public/admin/settings/design.php

# Check database setting exists
sqlite3 /var/www/3ddreamcrafts/database/craftsite.db \
    "SELECT * FROM settings WHERE setting_name='site_logo';"

# Check logs directory
ls -la /var/www/3ddreamcrafts/logs/
```

## Support

For issues or questions:
1. Check error logs first
2. Verify file permissions
3. Test database connectivity
4. Review Apache configuration

Happy deploying! 🚀
