# Login Troubleshooting Guide

If you're experiencing issues logging in with the default credentials (admin/admin) after a fresh installation, follow these steps:

## Quick Diagnostic

Run the automated diagnostic script:

```bash
cd /var/www/html/adial
php test_full_login.php
```

This will check:
- PHP password functions
- Database connection
- User table and admin user
- Password hash verification
- CodeIgniter framework files
- Session configuration
- File permissions

## Common Issues and Solutions

### 1. Admin User Not Found

**Symptom:** Login fails, diagnostic shows "Admin user not found"

**Solution:** Import or recreate the admin user:

```sql
mysql -u root -p adialer

INSERT INTO `users` (`username`, `password`, `email`, `full_name`, `role`, `is_active`, `created_at`) VALUES
('admin', '$2y$10$nG4K5S6hSflCLUCsgn62ze7rohekGbOgEMgvFpqhPHPHMzzoFdCA.', 'admin@localhost', 'Administrator', 'admin', 1, NOW());
```

### 2. Incorrect Password Hash

**Symptom:** Login fails, diagnostic shows "Password 'admin' does NOT verify"

**Solution:** Update the password hash:

```sql
mysql -u root -p adialer

UPDATE users SET password = '$2y$10$nG4K5S6hSflCLUCsgn62ze7rohekGbOgEMgvFpqhPHPHMzzoFdCA.' WHERE username = 'admin';
```

### 3. User Account Inactive

**Symptom:** Login fails, diagnostic shows "User is INACTIVE"

**Solution:** Activate the user account:

```sql
mysql -u root -p adialer

UPDATE users SET is_active = 1 WHERE username = 'admin';
```

### 4. Database Configuration Wrong

**Symptom:** Cannot connect to database

**Solution:** Check database credentials in:

```bash
nano /var/www/html/adial/application/config/database.php
```

Make sure these values match your MySQL setup:
```php
'hostname' => 'localhost',
'username' => 'root',  // or your database user
'password' => 'your_password',
'database' => 'adialer',
```

### 5. Session Directory Not Writable

**Symptom:** Login appears to work but redirects back to login page

**Solution:** Check session directory permissions:

```bash
# Find session path
php -r "echo session_save_path() ?: sys_get_temp_dir();"

# Make it writable
sudo chmod 777 /var/lib/php/session  # or the path from above
```

Or configure CodeIgniter to use a custom session path:

```bash
mkdir -p /var/www/html/adial/application/sessions
chmod 777 /var/www/html/adial/application/sessions
```

Then edit `/var/www/html/adial/application/config/config.php`:
```php
$config['sess_save_path'] = APPPATH . 'sessions';
```

### 6. Base URL Not Configured

**Symptom:** Login redirects to wrong URL or 404 errors

**Solution:** Set the base URL in `/var/www/html/adial/application/config/config.php`:

```php
$config['base_url'] = 'http://your-server-ip/adial/';
```

### 7. mod_rewrite Not Enabled

**Symptom:** 404 errors or "Page not found"

**Solution:** Enable Apache mod_rewrite:

```bash
# CentOS/RHEL
sudo a2enmod rewrite
sudo systemctl restart httpd

# Ubuntu/Debian
sudo a2enmod rewrite
sudo systemctl restart apache2
```

### 8. .htaccess Not Working

**Symptom:** URLs not rewriting properly

**Solution:** Make sure AllowOverride is set in Apache config:

Edit `/etc/httpd/conf/httpd.conf` (CentOS) or `/etc/apache2/apache2.conf` (Ubuntu):

```apache
<Directory "/var/www/html">
    AllowOverride All
    Require all granted
</Directory>
```

Then restart Apache:
```bash
sudo systemctl restart httpd  # or apache2
```

## Manual Login Test

If the diagnostic passes but web login still fails, test manually:

1. **Access the login page directly:**
   ```
   http://your-server-ip/adial/login
   ```

2. **Check browser console for JavaScript errors:**
   - Open browser Developer Tools (F12)
   - Go to Console tab
   - Try to log in
   - Look for any errors

3. **Check Apache error logs:**
   ```bash
   # CentOS/RHEL
   tail -f /var/log/httpd/error_log

   # Ubuntu/Debian
   tail -f /var/log/apache2/error.log
   ```

4. **Check PHP error logs:**
   ```bash
   tail -f /var/log/php-fpm/error.log
   # or
   tail -f /var/log/php/error.log
   ```

5. **Check CodeIgniter logs:**
   ```bash
   tail -f /var/www/html/adial/application/logs/*.php
   ```

6. **Enable PHP error display (temporarily):**

   Edit `/var/www/html/adial/index.php` and change:
   ```php
   define('ENVIRONMENT', 'development');  // change from 'production'
   ```

   **Remember to change it back to 'production' when done!**

## Verify Installation

Double-check your installation:

```bash
# 1. Check database exists and has tables
mysql -u root -p adialer -e "SHOW TABLES;"

# Should show:
# active_channels, campaign_numbers, campaigns, cdr,
# ivr_actions, ivr_menus, settings, users

# 2. Check admin user exists
mysql -u root -p adialer -e "SELECT username, role, is_active FROM users WHERE username='admin';"

# 3. Check file permissions
ls -la /var/www/html/adial/

# 4. Check Apache is running
systemctl status httpd  # or apache2

# 5. Check if site is accessible
curl -I http://localhost/adial/
```

## Still Not Working?

If you've tried everything above and login still fails:

1. **Re-import the database schema:**
   ```bash
   mysql -u root -p adialer < /var/www/html/adial/database_schema.sql
   ```

2. **Check the AUTHENTICATION.md file** for more advanced user management options

3. **Review the complete installation guide** in INSTALL.md

4. **Check for SELinux issues** (CentOS/RHEL):
   ```bash
   sudo setenforce 0  # Temporary disable
   # If this fixes it, you need to configure SELinux properly
   ```

## Default Credentials

After fresh installation, use these credentials:

- **Username:** `admin`
- **Password:** `admin`

**⚠️ SECURITY WARNING:** Change the default password immediately after first login!

## Need More Help?

1. Run the diagnostic: `php test_full_login.php`
2. Check all log files mentioned above
3. Review TROUBLESHOOTING.md for general issues
4. Review FAQ.md for frequently asked questions
