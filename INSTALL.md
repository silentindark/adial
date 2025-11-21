# ARI Dialer - Installation Manual

## Table of Contents

1. [System Requirements](#system-requirements)
2. [Supported Operating Systems](#supported-operating-systems)
3. [Pre-Installation Checklist](#pre-installation-checklist)
4. [Automated Installation](#automated-installation)
5. [Manual Installation](#manual-installation)
6. [Post-Installation Configuration](#post-installation-configuration)
7. [Verification and Testing](#verification-and-testing)
8. [Troubleshooting](#troubleshooting)
9. [Uninstallation](#uninstallation)

---

## System Requirements

### Hardware Requirements

- **Minimum:**
  - CPU: 2 cores
  - RAM: 2 GB
  - Disk: 10 GB free space

- **Recommended:**
  - CPU: 4+ cores
  - RAM: 4+ GB
  - Disk: 20+ GB free space (for recordings)

### Software Requirements

- **Operating System:** CentOS 7/8, RHEL 7/8, Ubuntu 18.04+, Debian 9+
- **Web Server:** Apache 2.4+
- **PHP:** 7.2 or higher
  - Extensions: mysqli, json, mbstring, xml, gd, curl
- **Database:** MariaDB 10.2+ or MySQL 5.7+
- **Asterisk:** 16+ (with ARI support)
- **Node.js:** 14+ (LTS recommended)
- **Additional Tools:**
  - FFmpeg (for audio conversion)
  - SOX (for audio processing)
  - Git (optional, for version control)

---

## Supported Operating Systems

The ARI Dialer has been tested on:

- ✅ CentOS 7.x
- ✅ CentOS 8.x / Rocky Linux 8.x
- ✅ Ubuntu 18.04 LTS
- ✅ Ubuntu 20.04 LTS
- ✅ Ubuntu 22.04 LTS
- ✅ Debian 9 (Stretch)
- ✅ Debian 10 (Buster)
- ✅ Debian 11 (Bullseye)

---

## Pre-Installation Checklist

Before starting the installation, ensure:

- [ ] You have root/sudo access to the server
- [ ] The server has internet connectivity
- [ ] Firewall allows HTTP (port 80) and ARI (port 8088) traffic
- [ ] SELinux is set to permissive mode (for CentOS/RHEL)
- [ ] No conflicting web servers or applications are running
- [ ] You have backed up any existing data

### Port Requirements

The following ports must be available:

- **80** - HTTP (Web Interface)
- **443** - HTTPS (optional, for SSL)
- **8088** - Asterisk ARI HTTP/WebSocket
- **3306** - MySQL/MariaDB (localhost only)

---

## Automated Installation

The automated installation script handles all configuration automatically.

### Quick Install

1. **Download or navigate to ARI Dialer directory:**
   ```bash
   cd /var/www/html/adial
   ```

2. **Make the installation script executable:**
   ```bash
   chmod +x install.sh
   ```

3. **Run the installation script as root:**
   ```bash
   sudo ./install.sh
   ```

4. **Follow the on-screen prompts:**
   - The script will detect your OS
   - Check system requirements
   - Ask for confirmation before proceeding
   - Install all dependencies
   - Configure services automatically

5. **Save the credentials:**
   - At the end of installation, credentials will be displayed
   - Credentials are also saved to `.credentials` file
   - **Keep these credentials secure!**

### What the Script Does

The automated installation script will:

1. ✅ Detect your operating system
2. ✅ Check system requirements (disk space, RAM)
3. ✅ Install all dependencies (Apache, PHP, MariaDB, Node.js)
4. ✅ Install or verify Asterisk installation
5. ✅ Create and configure database
6. ✅ Set up application configuration files
7. ✅ Create required directories with proper permissions
8. ✅ Configure Apache virtual host
9. ✅ Configure SELinux and firewall (CentOS/RHEL)
10. ✅ Configure Asterisk ARI
11. ✅ Install Node.js dependencies
12. ✅ Create systemd service for Stasis app
13. ✅ Start all services
14. ✅ Display installation summary

---

## Manual Installation

If you prefer manual installation or the automated script fails, follow these steps:

### Step 1: Install System Dependencies

#### For CentOS/RHEL 7/8:

```bash
# Update system
sudo yum update -y

# Install EPEL repository
sudo yum install -y epel-release

# Install basic tools
sudo yum install -y wget curl git vim nano unzip

# Install Apache
sudo yum install -y httpd httpd-tools
sudo systemctl enable httpd
sudo systemctl start httpd

# Install PHP (for CentOS 8)
sudo yum install -y php php-mysqlnd php-json php-mbstring php-xml php-gd php-curl

# For CentOS 7, install Remi repository for PHP 7.x
sudo yum install -y http://rpms.remirepo.net/enterprise/remi-release-7.rpm
sudo yum install -y yum-utils
sudo yum-config-manager --enable remi-php74
sudo yum install -y php php-mysqlnd php-json php-mbstring php-xml php-gd php-curl

# Install MariaDB
sudo yum install -y mariadb-server mariadb
sudo systemctl enable mariadb
sudo systemctl start mariadb

# Secure MariaDB installation
sudo mysql_secure_installation

# Install Node.js 16
curl -fsSL https://rpm.nodesource.com/setup_16.x | sudo bash -
sudo yum install -y nodejs

# Install audio tools
sudo yum install -y sox ffmpeg
```

#### For Ubuntu/Debian:

```bash
# Update system
sudo apt-get update
sudo apt-get upgrade -y

# Install basic tools
sudo apt-get install -y wget curl git vim nano unzip software-properties-common

# Install Apache
sudo apt-get install -y apache2
sudo systemctl enable apache2
sudo systemctl start apache2

# Install PHP
sudo apt-get install -y php php-mysql php-json php-mbstring php-xml php-gd php-curl libapache2-mod-php

# Install MariaDB
sudo apt-get install -y mariadb-server mariadb-client
sudo systemctl enable mariadb
sudo systemctl start mariadb

# Secure MariaDB installation
sudo mysql_secure_installation

# Install Node.js 16
curl -fsSL https://deb.nodesource.com/setup_16.x | sudo bash -
sudo apt-get install -y nodejs

# Install audio tools
sudo apt-get install -y sox ffmpeg

# Enable Apache modules
sudo a2enmod rewrite
sudo a2enmod headers
sudo systemctl restart apache2
```

### Step 2: Install Asterisk

If Asterisk is not installed:

#### For CentOS/RHEL:
```bash
sudo yum install -y asterisk
```

#### For Ubuntu/Debian:
```bash
sudo apt-get install -y asterisk
```

#### Or compile from source (recommended for latest version):

See official Asterisk documentation: https://wiki.asterisk.org/wiki/display/AST/Installing+Asterisk+From+Source

### Step 3: Set Up Database

```bash
# Login to MySQL as root
sudo mysql -u root -p

# Create database and user
CREATE DATABASE adialer DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci;
CREATE USER 'adialer_user'@'localhost' IDENTIFIED BY 'your_secure_password';
GRANT ALL PRIVILEGES ON adialer.* TO 'adialer_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;

# Import database schema
sudo mysql -u adialer_user -p adialer < /var/www/html/adial/database_schema.sql
```

### Step 4: Configure Application

#### Database Configuration

Edit `/var/www/html/adial/application/config/database.php`:

```php
$db['default'] = array(
    'hostname' => 'localhost',
    'username' => 'adialer_user',
    'password' => 'your_secure_password',
    'database' => 'adialer',
    'dbdriver' => 'mysqli',
    // ... other settings
);
```

#### ARI Configuration

Edit `/var/www/html/adial/application/config/ari.php`:

```php
$config['ari_host'] = 'localhost';
$config['ari_port'] = '8088';
$config['ari_username'] = 'dialer';
$config['ari_password'] = 'your_ari_password';
$config['ari_stasis_app'] = 'dialer';
```

### Step 5: Configure Asterisk ARI

Edit `/etc/asterisk/ari.conf`:

```ini
[general]
enabled = yes
pretty = yes
allowed_origins = *

[dialer]
type = user
read_only = no
password = your_ari_password
```

Restart Asterisk:
```bash
sudo systemctl restart asterisk
```

### Step 6: Set Up Directories

```bash
# Create required directories
sudo mkdir -p /var/lib/asterisk/sounds/dialer
sudo mkdir -p /var/spool/asterisk/monitor
sudo mkdir -p /var/www/html/adial/logs
sudo mkdir -p /var/www/html/adial/recordings
sudo mkdir -p /var/www/html/adial/uploads

# Set ownership (for CentOS/RHEL)
sudo chown -R apache:apache /var/www/html/adial
sudo chown -R asterisk:asterisk /var/lib/asterisk/sounds/dialer
sudo chown -R asterisk:asterisk /var/spool/asterisk/monitor

# Or for Ubuntu/Debian
sudo chown -R www-data:www-data /var/www/html/adial
sudo chown -R asterisk:asterisk /var/lib/asterisk/sounds/dialer
sudo chown -R asterisk:asterisk /var/spool/asterisk/monitor

# Set permissions
sudo chmod -R 755 /var/www/html/adial
sudo chmod -R 777 /var/www/html/adial/logs
sudo chmod -R 777 /var/www/html/adial/recordings
sudo chmod -R 777 /var/www/html/adial/uploads
sudo chmod -R 777 /var/lib/asterisk/sounds/dialer
```

### Step 7: Configure Apache

#### For CentOS/RHEL:

Create `/etc/httpd/conf.d/adial.conf`:

```apache
<VirtualHost *:80>
    ServerName your-domain.com
    DocumentRoot /var/www/html/adial

    <Directory /var/www/html/adial>
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog /var/log/httpd/adial-error.log
    CustomLog /var/log/httpd/adial-access.log combined
</VirtualHost>
```

Configure SELinux:
```bash
sudo setenforce 0
sudo sed -i 's/^SELINUX=enforcing/SELINUX=permissive/' /etc/selinux/config
```

Configure firewall:
```bash
sudo firewall-cmd --permanent --add-service=http
sudo firewall-cmd --reload
```

Restart Apache:
```bash
sudo systemctl restart httpd
```

#### For Ubuntu/Debian:

Create `/etc/apache2/sites-available/adial.conf`:

```apache
<VirtualHost *:80>
    ServerName your-domain.com
    DocumentRoot /var/www/html/adial

    <Directory /var/www/html/adial>
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/adial-error.log
    CustomLog ${APACHE_LOG_DIR}/adial-access.log combined
</VirtualHost>
```

Enable site:
```bash
sudo a2ensite adial.conf
sudo a2dissite 000-default.conf
sudo systemctl restart apache2
```

### Step 8: Set Up Node.js Stasis Application

```bash
# Navigate to stasis app directory
cd /var/www/html/adial/stasis-app

# Copy example environment file and configure it
cp .env.example .env

# Edit .env file with your actual credentials
nano .env
# Or use your preferred editor (vim, vi, etc.)
```

**Configure the following values in `.env`:**

```ini
# Asterisk ARI Configuration
ARI_HOST=localhost
ARI_PORT=8088
ARI_USERNAME=dialer
ARI_PASSWORD=your_ari_password        # Replace with actual ARI password
ARI_APP_NAME=dialer

# Database Configuration
DB_HOST=localhost
DB_USER=adialer_user                  # Replace with database username
DB_PASSWORD=your_secure_password      # Replace with database password
DB_NAME=adialer

# Application Settings
DEBUG_MODE=true
LOG_LEVEL=info
RECORDINGS_PATH=/var/spool/asterisk/monitor/adial
SOUNDS_PATH=/var/lib/asterisk/sounds/dialer
```

**Important:** Never commit the `.env` file to version control as it contains sensitive credentials.

```bash
# Install dependencies
npm install --production
```

### Step 9: Create Systemd Service

Create `/etc/systemd/system/ari-dialer.service`:

```ini
[Unit]
Description=Asterisk ARI Dialer - Stasis Application
After=network.target asterisk.service mariadb.service
Requires=asterisk.service mariadb.service

[Service]
Type=simple
User=root
WorkingDirectory=/var/www/html/adial/stasis-app
ExecStart=/usr/bin/node app.js
Restart=always
RestartSec=10
StandardOutput=append:/var/www/html/adial/logs/stasis-combined.log
StandardError=append:/var/www/html/adial/logs/stasis-combined.log

[Install]
WantedBy=multi-user.target
```

Enable and start service:
```bash
sudo systemctl daemon-reload
sudo systemctl enable ari-dialer
sudo systemctl start ari-dialer
```

### Step 10: Verify Installation

Check service status:
```bash
sudo systemctl status ari-dialer
sudo systemctl status asterisk
sudo systemctl status httpd  # or apache2
sudo systemctl status mariadb
```

---

## Post-Installation Configuration

### 1. Access Web Interface

Open your web browser and navigate to:
- `http://your-server-ip/adial`
- or `http://localhost/adial` (if on the server)

### 2. Default Login

The system creates a default administrator account during installation:

**Default Credentials:**
- **Username:** `admin`
- **Password:** `admin`

**⚠️ IMPORTANT SECURITY NOTICE:**
- Change the default password immediately after first login
- See [AUTHENTICATION.md](AUTHENTICATION.md) for detailed user management and password reset instructions

**First Login Steps:**
1. Navigate to `http://your-server-ip/adial`
2. Log in with username: `admin` and password: `admin`
3. Navigate to User Management (if available) or use the password reset process
4. Change the default password to a strong password

### 3. Configure Asterisk Extensions

You need to configure your Asterisk dialplan to work with ARI Dialer. Example:

Edit `/etc/asterisk/extensions.conf`:

```ini
[default]
exten => _X.,1,NoOp(Incoming call to ${EXTEN})
 same => n,Answer()
 same => n,Stasis(dialer,${EXTEN})
 same => n,Hangup()
```

Reload Asterisk:
```bash
sudo asterisk -rx "dialplan reload"
```

### 4. Test ARI Connection

Test if Asterisk ARI is accessible:

```bash
curl -u dialer:your_ari_password http://localhost:8088/ari/asterisk/info
```

You should see JSON output with Asterisk information.

### 5. Configure Trunks

Before creating campaigns, configure your SIP/PJSIP trunks in Asterisk:

Edit `/etc/asterisk/pjsip.conf` or `/etc/asterisk/sip.conf` depending on your setup.

---

## Verification and Testing

### Check All Services

Run the startup script to verify all services:

```bash
cd /var/www/html/adial
sudo ./start-dialer.sh
```

### Test Campaign Creation

1. Access the web interface
2. Navigate to "Campaigns" → "New Campaign"
3. Fill in campaign details:
   - Name: Test Campaign
   - Trunk: Your configured trunk
   - Agent Destination: Test extension
4. Upload a CSV with phone numbers
5. Start the campaign

### Check Logs

Monitor logs for errors:

```bash
# Stasis app logs
sudo journalctl -u ari-dialer -f

# Or
sudo tail -f /var/www/html/adial/logs/stasis-combined.log

# Apache logs (CentOS/RHEL)
sudo tail -f /var/log/httpd/adial-error.log

# Apache logs (Ubuntu/Debian)
sudo tail -f /var/log/apache2/adial-error.log

# Asterisk logs
sudo tail -f /var/log/asterisk/full
```

---

## Troubleshooting

### Issue: Web page shows "500 Internal Server Error"

**Solutions:**
1. Check Apache error logs
2. Verify PHP is installed and enabled
3. Check file permissions: `chmod -R 755 /var/www/html/adial`
4. Verify database connection in `config/database.php`

### Issue: "Database connection failed"

**Solutions:**
1. Verify MySQL/MariaDB is running: `systemctl status mariadb`
2. Test database credentials: `mysql -u adialer_user -p`
3. Check database configuration in `application/config/database.php`
4. Ensure database exists: `SHOW DATABASES;`

### Issue: "Cannot connect to Asterisk ARI"

**Solutions:**
1. Verify Asterisk is running: `systemctl status asterisk`
2. Check ARI is enabled in `/etc/asterisk/ari.conf`
3. Test ARI endpoint: `curl http://localhost:8088/ari/asterisk/info`
4. Verify ARI credentials in `application/config/ari.php`
5. Check Asterisk logs: `asterisk -rvvv`

### Issue: Stasis app not starting

**Solutions:**
1. Check service status: `systemctl status ari-dialer`
2. View logs: `journalctl -u ari-dialer -n 50`
3. Verify Node.js is installed: `node --version`
4. Check `.env` file in `stasis-app/` directory
5. Manually test: `cd /var/www/html/adial/stasis-app && node app.js`

### Issue: Calls not connecting

**Solutions:**
1. Verify trunk configuration in Asterisk
2. Check Asterisk CLI: `asterisk -rvvv` and watch for errors
3. Verify campaign settings (trunk type, destination)
4. Check if Stasis app is receiving events
5. Review ARI logs: `journalctl -u ari-dialer -f`

### Issue: Recordings not saving

**Solutions:**
1. Check directory permissions: `ls -la /var/spool/asterisk/monitor`
2. Verify recording is enabled in campaign settings
3. Check Asterisk has permission to write: `chown -R asterisk:asterisk /var/spool/asterisk/monitor`
4. Verify recording format is supported (WAV, MP3)

### Issue: IVR audio not playing

**Solutions:**
1. Verify audio file is in correct format (WAV, 8000Hz, Mono)
2. Check audio file location: `/var/lib/asterisk/sounds/dialer/`
3. Verify file permissions: `chmod 644 /var/lib/asterisk/sounds/dialer/*.wav`
4. Test audio conversion: `sox input.mp3 -r 8000 -c 1 output.wav`

### Issue: SELinux blocking operations (CentOS/RHEL)

**Solutions:**
1. Check SELinux status: `getenforce`
2. Set to permissive mode: `setenforce 0`
3. Make permanent: `sed -i 's/^SELINUX=enforcing/SELINUX=permissive/' /etc/selinux/config`
4. Or configure proper SELinux contexts:
   ```bash
   chcon -R -t httpd_sys_rw_content_t /var/www/html/adial/logs
   chcon -R -t httpd_sys_rw_content_t /var/www/html/adial/recordings
   ```

### Issue: Firewall blocking connections

**Solutions:**
1. Check firewall status: `firewall-cmd --list-all`
2. Open HTTP port: `firewall-cmd --permanent --add-service=http`
3. Open custom ports: `firewall-cmd --permanent --add-port=8088/tcp`
4. Reload firewall: `firewall-cmd --reload`

---

## Uninstallation

To completely remove ARI Dialer:

```bash
# Stop and disable services
sudo systemctl stop ari-dialer
sudo systemctl disable ari-dialer
sudo rm /etc/systemd/system/ari-dialer.service
sudo systemctl daemon-reload

# Remove database
sudo mysql -e "DROP DATABASE IF EXISTS adialer;"
sudo mysql -e "DROP USER IF EXISTS 'adialer_user'@'localhost';"

# Remove application files
sudo rm -rf /var/www/html/adial

# Remove Apache configuration
sudo rm /etc/httpd/conf.d/adial.conf  # CentOS/RHEL
sudo rm /etc/apache2/sites-available/adial.conf  # Ubuntu/Debian
sudo systemctl restart httpd  # or apache2

# Remove Asterisk directories
sudo rm -rf /var/lib/asterisk/sounds/dialer
sudo rm -rf /var/spool/asterisk/monitor

# Optional: Remove ARI configuration from Asterisk
sudo nano /etc/asterisk/ari.conf  # Remove [dialer] section
sudo systemctl restart asterisk
```

---

## Additional Resources

- **Asterisk Documentation:** https://wiki.asterisk.org/
- **Asterisk ARI Documentation:** https://wiki.asterisk.org/wiki/display/AST/Asterisk+ARI
- **CodeIgniter Documentation:** https://codeigniter.com/userguide3/
- **Node.js ARI Client:** https://github.com/asterisk/node-ari-client

---

## Support

For issues and questions:

1. Check the troubleshooting section above
2. Review log files for error messages
3. Consult Asterisk and CodeIgniter documentation
4. Check GitHub issues (if applicable)

---

## License

See LICENSE file for details.

---

**Installation Date:** Use `date` command to record your installation date

**Installed By:** Record your name/organization

**Notes:** Add any custom notes about your installation here
