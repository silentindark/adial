#!/bin/bash

# Asterisk ARI Dialer - Startup Script

echo "================================"
echo "Asterisk ARI Dialer Startup"
echo "================================"
echo ""

# Check if running as root
if [ "$EUID" -eq 0 ]; then
    echo "Running as root..."
else
    echo "Note: Some operations may require root privileges"
fi

# Check MySQL
echo "Checking MySQL..."
if systemctl is-active --quiet mariadb || systemctl is-active --quiet mysql; then
    echo "✓ MySQL is running"
else
    echo "✗ MySQL is not running. Starting..."
    systemctl start mariadb || systemctl start mysql
fi

# Check Asterisk
echo ""
echo "Checking Asterisk..."
if systemctl is-active --quiet asterisk; then
    echo "✓ Asterisk is running"
else
    echo "✗ Asterisk is not running. Starting..."
    systemctl start asterisk
fi

# Check Apache/Httpd
echo ""
echo "Checking Web Server..."
if systemctl is-active --quiet httpd || systemctl is-active --quiet apache2; then
    echo "✓ Web server is running"
else
    echo "✗ Web server is not running. Starting..."
    systemctl start httpd || systemctl start apache2
fi

# Check if Node.js is installed
echo ""
echo "Checking Node.js..."
if command -v node &> /dev/null; then
    echo "✓ Node.js is installed ($(node --version))"
else
    echo "✗ Node.js is not installed"
    exit 1
fi

# Check if Stasis app is already running
echo ""
echo "Checking Stasis Application..."
if systemctl is-active --quiet ari-dialer; then
    echo "✓ Stasis application is already running"
    echo "  To restart: systemctl restart ari-dialer"
else
    echo "✗ Stasis application is not running. Starting..."
    cd /var/www/html/adial/stasis-app

    # Check if dependencies are installed
    if [ ! -d "node_modules" ]; then
        echo "  Installing Node.js dependencies..."
        npm install
    fi

    # Start Stasis app via systemd
    systemctl start ari-dialer
    sleep 2

    # Check if it's running
    if systemctl is-active --quiet ari-dialer; then
        echo "✓ Stasis application is running"
    else
        echo "✗ Stasis application failed to start. Check logs:"
        echo "  journalctl -u ari-dialer -n 50"
        echo "  or: tail -f /var/www/html/adial/logs/stasis-combined.log"
        exit 1
    fi
fi

# Check permissions
echo ""
echo "Checking directory permissions..."
chmod -R 777 /var/www/html/adial/uploads 2>/dev/null
chmod -R 777 /var/www/html/adial/logs 2>/dev/null
chmod -R 777 /var/www/html/adial/recordings 2>/dev/null
chmod -R 777 /var/lib/asterisk/sounds/dialer 2>/dev/null
echo "✓ Permissions set"

# Display status summary
echo ""
echo "================================"
echo "Status Summary"
echo "================================"
echo ""
echo "Services:"
echo "  • MySQL:       $(systemctl is-active mariadb mysql 2>/dev/null | grep active | head -1 || echo 'inactive')"
echo "  • Asterisk:    $(systemctl is-active asterisk 2>/dev/null || echo 'inactive')"
echo "  • Web Server:  $(systemctl is-active httpd apache2 2>/dev/null | grep active | head -1 || echo 'inactive')"
echo "  • Stasis App:  $(systemctl is-active ari-dialer 2>/dev/null || echo 'inactive')"
echo ""
echo "Web Interface:"
echo "  URL: http://$(hostname -I | awk '{print $1}')/adial"
echo "  or   http://localhost/adial"
echo ""
echo "Logs:"
echo "  Web:     /var/www/html/adial/logs/"
echo "  Stasis:  /var/www/html/adial/logs/stasis-combined.log"
echo "           journalctl -u ari-dialer -f"
echo "  Apache:  /var/log/httpd/ or /var/log/apache2/"
echo ""
echo "To manage Stasis app:"
echo "  systemctl stop ari-dialer"
echo "  systemctl restart ari-dialer"
echo "  systemctl enable ari-dialer   (auto-start on boot)"
echo ""
echo "================================"
echo "System is ready!"
echo "================================"
