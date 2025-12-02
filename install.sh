#!/bin/bash

################################################################################
# ARI Dialer - Installation Script
# Automated installation for CentOS/RHEL 7/8 and Debian/Ubuntu systems
################################################################################

set -e  # Exit on error

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration variables
INSTALL_DIR="/var/www/html/adial"
DB_NAME="adialer"
DB_USER="adialer_user"
DB_PASS=""
ASTERISK_SOUNDS_DIR="/var/lib/asterisk/sounds/dialer"
RECORDINGS_DIR="/var/spool/asterisk/monitor"
ARI_USER="dialer"
ARI_PASS=""
WEB_USER="apache"
MIN_PHP_VERSION="7.2"
MIN_NODE_VERSION="14"

################################################################################
# Helper Functions
################################################################################

print_header() {
    echo -e "${BLUE}"
    echo "========================================================================"
    echo "$1"
    echo "========================================================================"
    echo -e "${NC}"
}

print_success() {
    echo -e "${GREEN}✓ $1${NC}"
}

print_error() {
    echo -e "${RED}✗ $1${NC}"
}

print_warning() {
    echo -e "${YELLOW}⚠ $1${NC}"
}

print_info() {
    echo -e "${BLUE}ℹ $1${NC}"
}

check_root() {
    if [ "$EUID" -ne 0 ]; then
        print_error "This script must be run as root"
        exit 1
    fi
}

detect_os() {
    if [ -f /etc/os-release ]; then
        . /etc/os-release
        OS=$ID
        OS_VERSION=$VERSION_ID
    else
        print_error "Cannot detect operating system"
        exit 1
    fi

    print_info "Detected OS: $OS $OS_VERSION"
}

generate_password() {
    openssl rand -base64 16 | tr -dc 'a-zA-Z0-9' | head -c 16
}

################################################################################
# System Requirements Check
################################################################################

check_requirements() {
    print_header "Checking System Requirements"

    # Check disk space (minimum 5GB)
    available_space=$(df -BG / | awk 'NR==2 {print $4}' | sed 's/G//')
    if [ "$available_space" -lt 5 ]; then
        print_error "Insufficient disk space. At least 5GB required."
        exit 1
    fi
    print_success "Disk space: ${available_space}GB available"

    # Check RAM (minimum 2GB)
    total_ram=$(free -g | awk 'NR==2 {print $2}')
    if [ "$total_ram" -lt 2 ]; then
        print_warning "Low RAM detected (${total_ram}GB). At least 2GB recommended."
    else
        print_success "RAM: ${total_ram}GB available"
    fi

    echo ""
}

################################################################################
# Install Dependencies
################################################################################

install_dependencies() {
    print_header "Installing System Dependencies"

    if [[ "$OS" == "centos" ]] || [[ "$OS" == "rhel" ]] || [[ "$OS" == "sangoma" ]]; then
        install_dependencies_centos
    elif [[ "$OS" == "ubuntu" ]] || [[ "$OS" == "debian" ]]; then
        install_dependencies_debian
    else
        print_error "Unsupported operating system: $OS"
        exit 1
    fi
}

install_dependencies_centos() {
    print_info "Installing dependencies for CentOS/RHEL..."

    # Update system
    yum update -y

    # Install EPEL repository
    yum install -y epel-release

    # Install basic tools
    yum install -y wget curl git vim nano unzip net-tools policycoreutils-python

    # Install Apache
    yum install -y httpd httpd-tools
    systemctl enable httpd

    # Install PHP 7.x or higher
    if [ "${OS_VERSION%%.*}" -ge 8 ]; then
        yum install -y php php-mysqlnd php-json php-mbstring php-xml php-gd php-curl
    else
        # For CentOS 7, install Remi repository for PHP 7.x
        yum install -y http://rpms.remirepo.net/enterprise/remi-release-7.rpm
        yum install -y yum-utils
        yum-config-manager --enable remi-php74
        yum install -y php php-mysqlnd php-json php-mbstring php-xml php-gd php-curl
    fi

    # Install MariaDB
    yum install -y mariadb-server mariadb
    systemctl enable mariadb
    systemctl start mariadb

    # Install Node.js
    curl -fsSL https://rpm.nodesource.com/setup_16.x | bash -
    yum install -y nodejs

    # Install FFmpeg for audio conversion
    yum install -y ffmpeg || print_warning "FFmpeg not available in default repos"

    # Install SOX for audio processing
    yum install -y sox

    print_success "Dependencies installed for CentOS/RHEL"
}

install_dependencies_debian() {
    print_info "Installing dependencies for Debian/Ubuntu..."

    # Update system
    apt-get update
    apt-get upgrade -y

    # Install basic tools
    apt-get install -y wget curl git vim nano unzip net-tools software-properties-common

    # Install Apache
    apt-get install -y apache2
    systemctl enable apache2

    # Install PHP
    apt-get install -y php php-mysql php-json php-mbstring php-xml php-gd php-curl libapache2-mod-php

    # Install MariaDB
    apt-get install -y mariadb-server mariadb-client
    systemctl enable mariadb
    systemctl start mariadb

    # Install Node.js
    curl -fsSL https://deb.nodesource.com/setup_16.x | bash -
    apt-get install -y nodejs

    # Install FFmpeg and SOX
    apt-get install -y ffmpeg sox

    # Set web user
    WEB_USER="www-data"

    print_success "Dependencies installed for Debian/Ubuntu"
}

install_asterisk() {
    print_header "Checking Asterisk Installation"

    if command -v asterisk &> /dev/null; then
        asterisk_version=$(asterisk -V | cut -d' ' -f2)
        print_success "Asterisk is already installed: $asterisk_version"

        # Check if ARI is enabled
        if ! grep -q "enabled = yes" /etc/asterisk/ari.conf 2>/dev/null; then
            print_warning "ARI may not be enabled in Asterisk"
        fi
    else
        print_warning "Asterisk is not installed"
        print_info "Please install Asterisk manually or run:"
        print_info "  For CentOS/RHEL: yum install asterisk"
        print_info "  For Ubuntu/Debian: apt-get install asterisk"

        read -p "Do you want to continue without Asterisk? (y/n): " -n 1 -r
        echo
        if [[ ! $REPLY =~ ^[Yy]$ ]]; then
            exit 1
        fi
    fi

    echo ""
}

################################################################################
# Database Setup
################################################################################

setup_database() {
    print_header "Setting Up Database"

    # Generate database password if not set
    if [ -z "$DB_PASS" ]; then
        DB_PASS=$(generate_password)
        print_info "Generated database password: $DB_PASS"
    fi

    # Create database and user
    print_info "Creating database '$DB_NAME' and user '$DB_USER'..."

    mysql -e "CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci;" 2>/dev/null || {
        print_error "Failed to create database"
        exit 1
    }

    mysql -e "CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';" 2>/dev/null
    mysql -e "GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'localhost';" 2>/dev/null
    mysql -e "FLUSH PRIVILEGES;" 2>/dev/null

    # Import database schema
    if [ -f "${INSTALL_DIR}/database_schema.sql" ]; then
        print_info "Importing database schema..."
        mysql "$DB_NAME" < "${INSTALL_DIR}/database_schema.sql"
        print_success "Database schema imported"
    else
        print_warning "Database schema file not found"
    fi

    print_success "Database setup completed"
    echo ""
}

################################################################################
# Application Configuration
################################################################################

configure_application() {
    print_header "Configuring Application"

    # Configure database connection
    print_info "Configuring database connection..."

    if [ -f "${INSTALL_DIR}/application/config/database.php" ]; then
        sed -i "s/'hostname' => '.*'/'hostname' => 'localhost'/g" "${INSTALL_DIR}/application/config/database.php"
        sed -i "s/'username' => '.*'/'username' => '${DB_USER}'/g" "${INSTALL_DIR}/application/config/database.php"
        sed -i "s/'password' => '.*'/'password' => '${DB_PASS}'/g" "${INSTALL_DIR}/application/config/database.php"
        sed -i "s/'database' => '.*'/'database' => '${DB_NAME}'/g" "${INSTALL_DIR}/application/config/database.php"
        print_success "Database configuration updated"
    else
        print_error "Database config file not found"
    fi

    # Configure ARI connection
    if [ -z "$ARI_PASS" ]; then
        ARI_PASS=$(generate_password)
        print_info "Generated ARI password: $ARI_PASS"
    fi

    if [ -f "${INSTALL_DIR}/application/config/ari.php" ]; then
        sed -i "s/\$config\['ari_username'\] = '.*'/\$config['ari_username'] = '${ARI_USER}'/g" "${INSTALL_DIR}/application/config/ari.php"
        sed -i "s/\$config\['ari_password'\] = '.*'/\$config['ari_password'] = '${ARI_PASS}'/g" "${INSTALL_DIR}/application/config/ari.php"
        print_success "ARI configuration updated"
    fi

    # Configure stasis app .env file
    if [ -d "${INSTALL_DIR}/stasis-app" ]; then
        cat > "${INSTALL_DIR}/stasis-app/.env" << EOF
# Asterisk ARI Configuration
ARI_HOST=localhost
ARI_PORT=8088
ARI_USERNAME=${ARI_USER}
ARI_PASSWORD=${ARI_PASS}
ARI_APP=dialer

# Database Configuration
DB_HOST=localhost
DB_USER=${DB_USER}
DB_PASSWORD=${DB_PASS}
DB_NAME=${DB_NAME}

# Logging
LOG_LEVEL=info
EOF
        print_success "Stasis app environment configured"
    fi

    echo ""
}

################################################################################
# Directory Setup
################################################################################

setup_directories() {
    print_header "Setting Up Directories"

    # Create required directories
    mkdir -p "${ASTERISK_SOUNDS_DIR}"
    mkdir -p "${RECORDINGS_DIR}"
    mkdir -p "${INSTALL_DIR}/logs"
    mkdir -p "${INSTALL_DIR}/recordings"
    mkdir -p "${INSTALL_DIR}/uploads"

    # Set permissions
    print_info "Setting directory permissions..."
    chown -R ${WEB_USER}:${WEB_USER} "${INSTALL_DIR}"
    chmod -R 755 "${INSTALL_DIR}"
    chmod -R 777 "${INSTALL_DIR}/logs"
    chmod -R 777 "${INSTALL_DIR}/recordings"
    chmod -R 777 "${INSTALL_DIR}/uploads"

    # Set Asterisk directories
    chown -R asterisk:asterisk "${ASTERISK_SOUNDS_DIR}" 2>/dev/null || true
    chmod -R 755 "${ASTERISK_SOUNDS_DIR}"

    chown -R asterisk:asterisk "${RECORDINGS_DIR}" 2>/dev/null || true
    chmod -R 755 "${RECORDINGS_DIR}"

    # Allow web server to write to Asterisk directories
    setfacl -R -m u:${WEB_USER}:rwx "${ASTERISK_SOUNDS_DIR}" 2>/dev/null || chmod -R 777 "${ASTERISK_SOUNDS_DIR}"
    setfacl -R -m u:${WEB_USER}:rwx "${RECORDINGS_DIR}" 2>/dev/null || chmod -R 777 "${RECORDINGS_DIR}"

    print_success "Directories created and permissions set"
    echo ""
}

################################################################################
# Web Server Configuration
################################################################################

configure_webserver() {
    print_header "Configuring Web Server"

    if [[ "$OS" == "centos" ]] || [[ "$OS" == "rhel" ]]; then
        configure_apache_centos
    elif [[ "$OS" == "ubuntu" ]] || [[ "$OS" == "debian" ]]; then
        configure_apache_debian
    fi
}

configure_apache_centos() {
    print_info "Configuring Apache for CentOS/RHEL..."

    # Create Apache configuration
    cat > /etc/httpd/conf.d/adial.conf << 'EOF'
<VirtualHost *:80>
    ServerName localhost
    DocumentRoot /var/www/html/adial

    <Directory /var/www/html/adial>
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog /var/log/httpd/adial-error.log
    CustomLog /var/log/httpd/adial-access.log combined
</VirtualHost>
EOF

    # Configure SELinux if enabled
    if command -v setenforce &> /dev/null; then
        print_info "Configuring SELinux..."
        setenforce 0 2>/dev/null || true
        sed -i 's/^SELINUX=enforcing/SELINUX=permissive/' /etc/selinux/config 2>/dev/null || true

        # Set SELinux contexts
        chcon -R -t httpd_sys_rw_content_t "${INSTALL_DIR}/logs" 2>/dev/null || true
        chcon -R -t httpd_sys_rw_content_t "${INSTALL_DIR}/recordings" 2>/dev/null || true
        chcon -R -t httpd_sys_rw_content_t "${INSTALL_DIR}/uploads" 2>/dev/null || true
    fi

    # Configure firewall
    if command -v firewall-cmd &> /dev/null; then
        print_info "Configuring firewall..."
        firewall-cmd --permanent --add-service=http 2>/dev/null || true
        firewall-cmd --reload 2>/dev/null || true
    fi

    # Restart Apache
    systemctl restart httpd
    systemctl enable httpd

    print_success "Apache configured for CentOS/RHEL"
}

configure_apache_debian() {
    print_info "Configuring Apache for Debian/Ubuntu..."

    # Enable required modules
    a2enmod rewrite
    a2enmod headers

    # Create Apache configuration
    cat > /etc/apache2/sites-available/adial.conf << 'EOF'
<VirtualHost *:80>
    ServerName localhost
    DocumentRoot /var/www/html/adial

    <Directory /var/www/html/adial>
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/adial-error.log
    CustomLog ${APACHE_LOG_DIR}/adial-access.log combined
</VirtualHost>
EOF

    # Enable site and disable default
    a2ensite adial.conf
    a2dissite 000-default.conf 2>/dev/null || true

    # Restart Apache
    systemctl restart apache2
    systemctl enable apache2

    print_success "Apache configured for Debian/Ubuntu"
}

################################################################################
# Asterisk Configuration
################################################################################

configure_asterisk() {
    print_header "Configuring Asterisk ARI"

    if ! command -v asterisk &> /dev/null; then
        print_warning "Asterisk not installed, skipping configuration"
        return
    fi

    print_info "Configuring ARI user..."

    # Backup original ari.conf
    if [ -f /etc/asterisk/ari.conf ]; then
        cp /etc/asterisk/ari.conf /etc/asterisk/ari.conf.bak
    fi

    # Configure ARI
    cat > /etc/asterisk/ari.conf << EOF
[general]
enabled = yes
pretty = yes
allowed_origins = *

[${ARI_USER}]
type = user
read_only = no
password = ${ARI_PASS}
EOF

    # Restart Asterisk
    systemctl restart asterisk 2>/dev/null || asterisk -rx "core reload" 2>/dev/null || true

    print_success "Asterisk ARI configured"
    echo ""
}

################################################################################
# Node.js Application Setup
################################################################################

setup_nodejs_app() {
    print_header "Setting Up Node.js Stasis Application"

    if [ ! -d "${INSTALL_DIR}/stasis-app" ]; then
        print_warning "Stasis app directory not found"
        return
    fi

    cd "${INSTALL_DIR}/stasis-app"

    print_info "Installing Node.js dependencies..."
    npm install --production

    print_success "Node.js dependencies installed"
    echo ""
}

################################################################################
# Systemd Service Setup
################################################################################

setup_systemd_service() {
    print_header "Setting Up Systemd Service"

    print_info "Creating systemd service for ARI Dialer..."

    cat > /etc/systemd/system/ari-dialer.service << EOF
[Unit]
Description=Asterisk ARI Dialer - Stasis Application
After=network.target asterisk.service mariadb.service
Requires=asterisk.service mariadb.service

[Service]
Type=simple
User=root
WorkingDirectory=${INSTALL_DIR}/stasis-app
ExecStart=/usr/bin/node app.js
Restart=always
RestartSec=10
StandardOutput=append:${INSTALL_DIR}/logs/stasis-combined.log
StandardError=append:${INSTALL_DIR}/logs/stasis-combined.log

[Install]
WantedBy=multi-user.target
EOF

    # Reload systemd and enable service
    systemctl daemon-reload
    systemctl enable ari-dialer

    print_success "Systemd service created and enabled"
    echo ""
}

################################################################################
# Final Steps
################################################################################

start_services() {
    print_header "Starting Services"

    # Start all services
    systemctl start mariadb 2>/dev/null || true
    systemctl start asterisk 2>/dev/null || true

    if [[ "$OS" == "centos" ]] || [[ "$OS" == "rhel" ]]; then
        systemctl start httpd
    else
        systemctl start apache2
    fi

    # Start ARI Dialer service
    print_info "Starting ARI Dialer service..."
    systemctl start ari-dialer
    sleep 3

    # Check service status
    if systemctl is-active --quiet ari-dialer; then
        print_success "ARI Dialer service started successfully"
    else
        print_error "ARI Dialer service failed to start"
        print_info "Check logs: journalctl -u ari-dialer -n 50"
    fi

    echo ""
}

print_summary() {
    print_header "Installation Complete!"

    # Get server IP
    SERVER_IP=$(hostname -I | awk '{print $1}')

    echo ""
    echo "========================================================================"
    echo "                    Installation Summary"
    echo "========================================================================"
    echo ""
    echo "Web Interface:"
    echo "  URL: http://${SERVER_IP}/adial"
    echo "  or:  http://localhost/adial"
    echo "  Username: admin"
    echo "  Password: admin"
    echo "  ⚠️  CHANGE DEFAULT PASSWORD IMMEDIATELY!"
    echo ""
    echo "Database:"
    echo "  Database: ${DB_NAME}"
    echo "  Username: ${DB_USER}"
    echo "  Password: ${DB_PASS}"
    echo ""
    echo "Asterisk ARI:"
    echo "  Username: ${ARI_USER}"
    echo "  Password: ${ARI_PASS}"
    echo "  URL: http://localhost:8088/ari"
    echo ""
    echo "Services:"
    echo "  • Web Server: $(systemctl is-active httpd apache2 2>/dev/null | grep active | head -1)"
    echo "  • Database:   $(systemctl is-active mariadb mysql 2>/dev/null | grep active | head -1)"
    echo "  • Asterisk:   $(systemctl is-active asterisk 2>/dev/null || echo 'inactive')"
    echo "  • ARI Dialer: $(systemctl is-active ari-dialer 2>/dev/null || echo 'inactive')"
    echo ""
    echo "Directories:"
    echo "  • Install:    ${INSTALL_DIR}"
    echo "  • Logs:       ${INSTALL_DIR}/logs"
    echo "  • Recordings: ${RECORDINGS_DIR}"
    echo "  • IVR Sounds: ${ASTERISK_SOUNDS_DIR}"
    echo ""
    echo "Management Commands:"
    echo "  • Start:   systemctl start ari-dialer"
    echo "  • Stop:    systemctl stop ari-dialer"
    echo "  • Restart: systemctl restart ari-dialer"
    echo "  • Status:  systemctl status ari-dialer"
    echo "  • Logs:    journalctl -u ari-dialer -f"
    echo ""
    echo "Startup Script:"
    echo "  • Run: ${INSTALL_DIR}/start-dialer.sh"
    echo ""
    echo "IMPORTANT: Save the credentials above in a secure location!"
    echo ""
    echo "========================================================================"

    # Save credentials to file
    cat > "${INSTALL_DIR}/.credentials" << EOF
ARI Dialer Installation Credentials
Generated: $(date)

Web Interface:
  URL: http://${SERVER_IP}/adial
  Username: admin
  Password: admin
  ⚠️  CHANGE DEFAULT PASSWORD IMMEDIATELY!

Database:
  Database: ${DB_NAME}
  Username: ${DB_USER}
  Password: ${DB_PASS}

Asterisk ARI:
  Username: ${ARI_USER}
  Password: ${ARI_PASS}
EOF

    chmod 600 "${INSTALL_DIR}/.credentials"
    print_info "Credentials saved to: ${INSTALL_DIR}/.credentials"
    echo ""
}

################################################################################
# Main Installation Flow
################################################################################

main() {
    print_header "ARI Dialer Installation Script"

    # Pre-installation checks
    check_root
    detect_os
    check_requirements

    # Ask for confirmation
    echo ""
    read -p "Do you want to proceed with the installation? (y/n): " -n 1 -r
    echo ""
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        echo "Installation cancelled."
        exit 0
    fi

    # Installation steps
    install_dependencies
    install_asterisk
    setup_database
    setup_directories
    configure_application
    configure_webserver
    configure_asterisk
    setup_nodejs_app
    setup_systemd_service
    start_services

    # Print summary
    print_summary

    print_success "Installation completed successfully!"
}

# Run main function
main "$@"
