#!/bin/bash
################################################################################
# A-Dial AMI Predictive Dialer - FreePBX Installation Script
# Compatible with FreePBX 14+, CentOS 7/8, Rocky Linux 8
################################################################################

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Installation directory
INSTALL_DIR="/var/www/html/adial"
ASTERISK_SOUNDS_DIR="/var/lib/asterisk/sounds/dialer"
RECORDINGS_DIR="/var/spool/asterisk/monitor/dialer"

echo "================================"
echo "A-Dial AMI Dialer Installation"
echo "FreePBX Edition"
echo "================================"
echo ""

# Check if running as root
if [[ $EUID -ne 0 ]]; then
   echo -e "${RED}Error: This script must be run as root${NC}"
   exit 1
fi

# Check if FreePBX is installed
if [ ! -f /etc/freepbx.conf ] && [ ! -f /etc/asterisk/freepbx.conf ]; then
    echo -e "${RED}Error: FreePBX not detected. This script is for FreePBX systems only.${NC}"
    exit 1
fi

echo -e "${GREEN}✓ FreePBX detected${NC}"

# Check if Asterisk is running
if ! asterisk -rx "core show version" > /dev/null 2>&1; then
    echo -e "${RED}Error: Asterisk is not running${NC}"
    exit 1
fi

echo -e "${GREEN}✓ Asterisk is running${NC}"

# Check PHP version
PHP_VERSION=$(php -v | head -n 1 | cut -d " " -f 2 | cut -d "." -f 1,2)
if (( $(echo "$PHP_VERSION < 7.0" | bc -l) )); then
    echo -e "${RED}Error: PHP 7.0 or higher required (found $PHP_VERSION)${NC}"
    exit 1
fi

echo -e "${GREEN}✓ PHP $PHP_VERSION detected${NC}"

# Check if MySQL/MariaDB is running
if ! systemctl is-active --quiet mariadb && ! systemctl is-active --quiet mysql; then
    echo -e "${RED}Error: MySQL/MariaDB is not running${NC}"
    exit 1
fi

echo -e "${GREEN}✓ MySQL/MariaDB is running${NC}"

echo ""
echo "================================"
echo "Step 1: Database Configuration"
echo "================================"

# Get MySQL root password
read -sp "Enter MySQL root password: " MYSQL_ROOT_PASS
echo ""

# Test MySQL connection
if ! mysql -u root -p"$MYSQL_ROOT_PASS" -e "SELECT 1" > /dev/null 2>&1; then
    echo -e "${RED}Error: Invalid MySQL root password${NC}"
    exit 1
fi

echo -e "${GREEN}✓ MySQL connection successful${NC}"

# Generate random password for database user
DB_PASSWORD=$(openssl rand -base64 16)

# Create database and user
echo "Creating database and user..."

# Create database
mysql -u root -p"$MYSQL_ROOT_PASS" <<EOF
CREATE DATABASE IF NOT EXISTS adialer CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
EOF

# Drop user if exists (ignore errors for compatibility with older versions)
mysql -u root -p"$MYSQL_ROOT_PASS" -e "DROP USER 'adialer_user'@'localhost'" 2>/dev/null || true

# Create user (compatible with all MySQL/MariaDB versions)
mysql -u root -p"$MYSQL_ROOT_PASS" <<EOF
CREATE USER 'adialer_user'@'localhost' IDENTIFIED BY '$DB_PASSWORD';
GRANT ALL PRIVILEGES ON adialer.* TO 'adialer_user'@'localhost';
FLUSH PRIVILEGES;
EOF

echo -e "${GREEN}✓ Database and user created${NC}"

# Import database schema
if [ -f "$INSTALL_DIR/database/schema.sql" ]; then
    echo "Importing database schema..."
    mysql -u root -p"$MYSQL_ROOT_PASS" adialer < "$INSTALL_DIR/database/schema.sql"
    echo -e "${GREEN}✓ Database schema imported${NC}"
else
    echo -e "${YELLOW}⚠ Schema file not found, skipping import${NC}"
fi

echo ""
echo "================================"
echo "Step 2: AMI Configuration"
echo "================================"

# Generate AMI password
AMI_PASSWORD=$(openssl rand -base64 16)

# Create AMI user configuration
cat > /etc/asterisk/manager_custom.conf <<EOF
; A-Dial AMI User
[dialer]
secret = $AMI_PASSWORD
deny=0.0.0.0/0.0.0.0
permit=127.0.0.1/255.255.255.255
read = system,call,log,verbose,command,agent,user,originate
write = system,call,log,verbose,command,agent,user,originate
writetimeout = 5000
EOF

echo -e "${GREEN}✓ AMI user created${NC}"

# Reload AMI
asterisk -rx "manager reload" > /dev/null 2>&1
echo -e "${GREEN}✓ AMI configuration reloaded${NC}"

echo ""
echo "================================"
echo "Step 3: Dialplan Configuration"
echo "================================"

# Add dialplan include to extensions_custom.conf
if ! grep -q "extensions_dialer.conf" /etc/asterisk/extensions_custom.conf 2>/dev/null; then
    cat >> /etc/asterisk/extensions_custom.conf <<EOF

; A-Dial Generated Dialplan
#include extensions_dialer.conf
EOF
    echo -e "${GREEN}✓ Dialplan include added to extensions_custom.conf${NC}"
else
    echo -e "${YELLOW}⚠ Dialplan include already exists${NC}"
fi

# Reload dialplan
asterisk -rx "dialplan reload" > /dev/null 2>&1
echo -e "${GREEN}✓ Dialplan reloaded${NC}"

echo ""
echo "================================"
echo "Step 4: Directory Setup"
echo "================================"

# Create sounds directory
mkdir -p "$ASTERISK_SOUNDS_DIR"
chown -R asterisk:asterisk "$ASTERISK_SOUNDS_DIR"
echo -e "${GREEN}✓ Sounds directory created: $ASTERISK_SOUNDS_DIR${NC}"

# Create recordings directory
mkdir -p "$RECORDINGS_DIR"
chown -R asterisk:asterisk "$RECORDINGS_DIR"
echo -e "${GREEN}✓ Recordings directory created: $RECORDINGS_DIR${NC}"

# Create logs directory (writable by both apache and asterisk)
mkdir -p "$INSTALL_DIR/logs"
chown -R asterisk:asterisk "$INSTALL_DIR/logs"
chmod 775 "$INSTALL_DIR/logs"
echo -e "${GREEN}✓ Logs directory created${NC}"

# Create uploads directory
mkdir -p "$INSTALL_DIR/uploads"
chown -R apache:apache "$INSTALL_DIR/uploads"
echo -e "${GREEN}✓ Uploads directory created${NC}"

echo ""
echo "================================"
echo "Step 5: Configuration Files"
echo "================================"

# Update database configuration
cat > "$INSTALL_DIR/application/config/database.php" <<EOF
<?php
defined('BASEPATH') OR exit('No direct script access allowed');

\$active_group = 'default';
\$query_builder = TRUE;

// Main A-Dial database
\$db['default'] = array(
    'dsn'   => '',
    'hostname' => '127.0.0.1',
    'username' => 'adialer_user',
    'password' => '$DB_PASSWORD',
    'database' => 'adialer',
    'dbdriver' => 'mysqli',
    'dbprefix' => '',
    'pconnect' => FALSE,
    'db_debug' => (ENVIRONMENT !== 'production'),
    'cache_on' => FALSE,
    'cachedir' => '',
    'char_set' => 'utf8mb4',
    'dbcollat' => 'utf8mb4_unicode_ci',
    'swap_pre' => '',
    'encrypt' => FALSE,
    'compress' => FALSE,
    'stricton' => FALSE,
    'failover' => array(),
    'save_queries' => TRUE
);

// Asterisk CDR database (FreePBX)
// NOTE: Update password to match your FreePBX installation
\$db['asteriskcdr'] = array(
    'dsn'   => '',
    'hostname' => '127.0.0.1',
    'username' => 'freepbxuser',
    'password' => 'CHANGE_ME',
    'database' => 'asteriskcdrdb',
    'dbdriver' => 'mysqli',
    'dbprefix' => '',
    'pconnect' => FALSE,
    'db_debug' => FALSE,
    'cache_on' => FALSE,
    'cachedir' => '',
    'char_set' => 'utf8',
    'dbcollat' => 'utf8_general_ci',
    'swap_pre' => '',
    'encrypt' => FALSE,
    'compress' => FALSE,
    'stricton' => FALSE,
    'failover' => array(),
    'save_queries' => FALSE
);
EOF

echo -e "${GREEN}✓ Database configuration updated${NC}"

# Update AMI daemon configuration
cat > "$INSTALL_DIR/ami-daemon/config.php" <<EOF
<?php
/**
 * A-Dial AMI Daemon Configuration
 */

return [
    // Asterisk AMI Configuration
    'ami' => [
        'host' => '127.0.0.1',
        'port' => 5038,
        'username' => 'dialer',
        'password' => '$AMI_PASSWORD',
        'connect_timeout' => 10000, // milliseconds
        'read_timeout' => 100 // milliseconds
    ],

    // MySQL Database Configuration
    'database' => [
        'host' => '127.0.0.1',
        'port' => 3306,
        'username' => 'adialer_user',
        'password' => '$DB_PASSWORD',
        'database' => 'adialer',
        'charset' => 'utf8mb4'
    ],

    // Asterisk CDR Database Configuration
    'cdr_database' => [
        'host' => '127.0.0.1',
        'port' => 3306,
        'username' => 'freepbxuser',
        'password' => 'CHANGE_ME',
        'database' => 'asteriskcdrdb',
        'charset' => 'utf8mb4'
    ],

    // Application Settings
    'app' => [
        'debug_mode' => false,
        'log_level' => 'info', // debug, info, warning, error
        'log_file' => '$INSTALL_DIR/logs/ami-daemon.log',
        'pid_file' => '$INSTALL_DIR/ami-daemon/daemon.pid',
        'recordings_path' => '$RECORDINGS_DIR',
        'sounds_path' => '$ASTERISK_SOUNDS_DIR'
    ],

    // Campaign Processing Settings
    'campaigns' => [
        // How often to check for active campaigns (in seconds)
        'reload_interval' => 5,

        // How often to process each campaign (in seconds)
        'process_interval' => 2,

        // Minimum retry delay (in seconds)
        'min_retry_delay' => 60
    ]
];
EOF

echo -e "${GREEN}✓ AMI daemon configuration updated${NC}"

# Update Asterisk configuration in CodeIgniter
cat > "$INSTALL_DIR/application/config/asterisk.php" <<EOF
<?php
defined('BASEPATH') OR exit('No direct script access allowed');

\$config['asterisk_sounds_dir'] = '$ASTERISK_SOUNDS_DIR/';
\$config['asterisk_recordings_dir'] = '$RECORDINGS_DIR/';
EOF

echo -e "${GREEN}✓ Asterisk configuration updated${NC}"

echo ""
echo "================================"
echo "Step 6: File Permissions"
echo "================================"

# Set ownership
chown -R apache:apache "$INSTALL_DIR"
chown -R asterisk:asterisk "$INSTALL_DIR/ami-daemon"
chmod +x "$INSTALL_DIR/ami-daemon/start-daemon.sh"
chmod +x "$INSTALL_DIR/ami-daemon/stop-daemon.sh"
chmod +x "$INSTALL_DIR/start-dialer.sh"
chmod +x "$INSTALL_DIR/stop-dialer.sh"

echo -e "${GREEN}✓ File permissions set${NC}"

echo ""
echo "================================"
echo "Step 7: Systemd Service"
echo "================================"

# Create systemd service
cat > /etc/systemd/system/adial-ami.service <<EOF
[Unit]
Description=A-Dial AMI Daemon
After=network.target asterisk.service mysql.service

[Service]
Type=simple
User=asterisk
WorkingDirectory=$INSTALL_DIR/ami-daemon
ExecStart=/usr/bin/php $INSTALL_DIR/ami-daemon/daemon.php
Restart=always
RestartSec=10

[Install]
WantedBy=multi-user.target
EOF

systemctl daemon-reload
systemctl enable adial-ami.service

echo -e "${GREEN}✓ Systemd service created and enabled${NC}"

echo ""
echo "================================"
echo "Step 8: SELinux Configuration"
echo "================================"

# Check if SELinux is enabled
if command -v getenforce > /dev/null 2>&1 && [ "$(getenforce)" != "Disabled" ]; then
    echo "Configuring SELinux contexts..."

    # Set SELinux contexts
    semanage fcontext -a -t httpd_sys_rw_content_t "$INSTALL_DIR/logs(/.*)?" 2>/dev/null || true
    semanage fcontext -a -t httpd_sys_rw_content_t "$INSTALL_DIR/uploads(/.*)?" 2>/dev/null || true
    semanage fcontext -a -t asterisk_var_lib_t "$ASTERISK_SOUNDS_DIR(/.*)?" 2>/dev/null || true
    semanage fcontext -a -t asterisk_var_lib_t "$RECORDINGS_DIR(/.*)?" 2>/dev/null || true

    restorecon -Rv "$INSTALL_DIR" 2>/dev/null || true
    restorecon -Rv "$ASTERISK_SOUNDS_DIR" 2>/dev/null || true
    restorecon -Rv "$RECORDINGS_DIR" 2>/dev/null || true

    echo -e "${GREEN}✓ SELinux contexts configured${NC}"
else
    echo -e "${YELLOW}⚠ SELinux not enabled, skipping${NC}"
fi

echo ""
echo "================================"
echo "Step 9: Initial Dialplan Generation"
echo "================================"

# Generate initial dialplan
if [ -f "$INSTALL_DIR/test-dialplan-generator.php" ]; then
    cd "$INSTALL_DIR"
    php test-dialplan-generator.php > /dev/null 2>&1
    echo -e "${GREEN}✓ Initial dialplan generated${NC}"
else
    echo -e "${YELLOW}⚠ Dialplan generator not found, skipping${NC}"
fi

echo ""
echo "================================"
echo "Step 10: Start Services"
echo "================================"

# Start AMI daemon
systemctl start adial-ami.service
sleep 2

if systemctl is-active --quiet adial-ami.service; then
    echo -e "${GREEN}✓ AMI daemon started successfully${NC}"
else
    echo -e "${RED}✗ Failed to start AMI daemon${NC}"
    echo "Check logs: tail -f $INSTALL_DIR/logs/ami-daemon.log"
fi

echo ""
echo "================================"
echo "Installation Complete!"
echo "================================"
echo ""
echo -e "${GREEN}✓ A-Dial AMI Dialer has been installed successfully${NC}"
echo ""
echo "Important Information:"
echo "======================"
echo ""
echo "Database Credentials:"
echo "  Database: adialer"
echo "  Username: adialer_user"
echo "  Password: $DB_PASSWORD"
echo ""
echo "AMI Credentials:"
echo "  Username: dialer"
echo "  Password: $AMI_PASSWORD"
echo ""
echo "Directories:"
echo "  Application: $INSTALL_DIR"
echo "  Sounds: $ASTERISK_SOUNDS_DIR"
echo "  Recordings: $RECORDINGS_DIR"
echo "  Logs: $INSTALL_DIR/logs"
echo ""
echo "Services:"
echo "  Start: systemctl start adial-ami"
echo "  Stop: systemctl stop adial-ami"
echo "  Status: systemctl status adial-ami"
echo "  Logs: tail -f $INSTALL_DIR/logs/ami-daemon.log"
echo ""
echo "Web Interface:"
echo "  URL: http://$(hostname -I | awk '{print $1}')/adial"
echo ""
echo "Next Steps:"
echo "==========="
echo "1. Configure your web server to point to $INSTALL_DIR"
echo "2. Access the web interface and create your first campaign"
echo "3. Configure SIP/PJSIP trunks in FreePBX"
echo "4. Upload IVR audio files"
echo "5. Create campaigns and start dialing!"
echo ""
echo "Documentation:"
echo "  Logs: $INSTALL_DIR/logs/ami-daemon.log"
echo "  Asterisk CLI: asterisk -rvvv"
echo "  Daemon Status: systemctl status adial-ami"
echo ""
echo "IMPORTANT: Save the credentials above to a secure location!"
echo ""
echo "MANUAL CONFIGURATION REQUIRED:"
echo "=============================="
echo "1. Edit $INSTALL_DIR/ami-daemon/config.php and update:"
echo "   - cdr_database password (replace CHANGE_ME with FreePBX database password)"
echo ""
echo "2. Edit $INSTALL_DIR/application/config/database.php and update:"
echo "   - asteriskcdr password (replace CHANGE_ME with FreePBX database password)"
echo ""
echo "To find FreePBX database password run:"
echo "   grep AMPDBPASS /etc/freepbx.conf"
echo ""
echo "Default login credentials:"
echo "   Username: admin"
echo "   Password: admin"
echo ""
