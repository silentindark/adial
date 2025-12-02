#!/bin/bash

# Asterisk ARI Dialer - Stop Script

echo "================================"
echo "Stopping Asterisk ARI Dialer"
echo "================================"
echo ""

# Stop Stasis application
echo "Stopping Stasis application..."
if systemctl is-active --quiet ari-dialer; then
    systemctl stop ari-dialer
    sleep 2
    if systemctl is-active --quiet ari-dialer; then
        echo "✗ Failed to stop Stasis application"
    else
        echo "✓ Stasis application stopped"
    fi
else
    echo "  Stasis application is not running"
fi

echo ""
echo "================================"
echo "Dialer stopped"
echo "================================"
echo ""
echo "Note: MySQL, Asterisk, and Web Server are still running."
echo "To stop them manually:"
echo "  systemctl stop asterisk"
echo "  systemctl stop httpd (or apache2)"
echo "  systemctl stop mariadb (or mysql)"
