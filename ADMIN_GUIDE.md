# ARI Dialer - Administrator Guide

Complete system administration guide for managing and maintaining ARI Dialer installation.

## Table of Contents

1. [System Administration](#system-administration)
2. [Service Management](#service-management)
3. [Database Management](#database-management)
4. [Backup and Recovery](#backup-and-recovery)
5. [Monitoring and Logging](#monitoring-and-logging)
6. [Performance Tuning](#performance-tuning)
7. [Security Hardening](#security-hardening)
8. [Maintenance Tasks](#maintenance-tasks)
9. [Upgrades and Updates](#upgrades-and-updates)
10. [Disaster Recovery](#disaster-recovery)

---

## System Administration

### Directory Structure

```
/var/www/html/adial/
├── application/          # CodeIgniter application
│   ├── config/          # Configuration files
│   ├── controllers/     # Application controllers
│   ├── models/          # Database models
│   ├── views/           # View templates
│   └── language/        # Translation files
├── stasis-app/          # Node.js Stasis application
│   ├── app.js          # Main application file
│   ├── .env            # Environment configuration
│   └── node_modules/   # Dependencies
├── logs/                # Application logs
├── recordings/          # Web-accessible recordings
├── uploads/             # Uploaded files (CSV, etc.)
├── public/              # Public assets (CSS, JS)
└── system/              # CodeIgniter framework

/var/lib/asterisk/sounds/dialer/   # IVR audio files
/var/spool/asterisk/monitor/       # Call recordings
/etc/asterisk/                     # Asterisk configuration
/etc/systemd/system/ari-dialer.service  # Systemd service
```

### File Permissions

**Application directory:**
```bash
chown -R apache:apache /var/www/html/adial  # CentOS/RHEL
chown -R www-data:www-data /var/www/html/adial  # Ubuntu/Debian
chmod -R 755 /var/www/html/adial
```

**Writable directories:**
```bash
chmod 777 /var/www/html/adial/logs
chmod 777 /var/www/html/adial/recordings
chmod 777 /var/www/html/adial/uploads
```

**Asterisk directories:**
```bash
chown -R asterisk:asterisk /var/lib/asterisk/sounds/dialer
chown -R asterisk:asterisk /var/spool/asterisk/monitor
chmod 755 /var/lib/asterisk/sounds/dialer
chmod 755 /var/spool/asterisk/monitor

# Allow web server to write to Asterisk directories
setfacl -R -m u:apache:rwx /var/lib/asterisk/sounds/dialer
setfacl -R -m u:apache:rwx /var/spool/asterisk/monitor
```

### User and Group Management

**Application users:**
- `apache` or `www-data` - Web server user
- `asterisk` - Asterisk process user
- `root` - Stasis app (can be changed)

**Create dedicated user for Stasis app (optional):**
```bash
useradd -r -s /bin/false aridialer
chown -R aridialer:aridialer /var/www/html/adial/stasis-app

# Update systemd service
sed -i 's/User=root/User=aridialer/' /etc/systemd/system/ari-dialer.service
systemctl daemon-reload
systemctl restart ari-dialer
```

---

## Service Management

### Managing Services

**ARI Dialer Stasis Application:**
```bash
# Status
systemctl status ari-dialer

# Start/Stop/Restart
systemctl start ari-dialer
systemctl stop ari-dialer
systemctl restart ari-dialer

# Enable/Disable at boot
systemctl enable ari-dialer
systemctl disable ari-dialer

# View logs
journalctl -u ari-dialer -f
journalctl -u ari-dialer -n 100
journalctl -u ari-dialer --since "1 hour ago"
```

**Asterisk:**
```bash
systemctl status asterisk
systemctl restart asterisk
systemctl reload asterisk  # Reload config without restart

# Asterisk CLI
asterisk -rvvv

# Reload specific modules
asterisk -rx "module reload res_pjsip.so"
asterisk -rx "dialplan reload"
```

**Web Server:**
```bash
# Apache (CentOS/RHEL)
systemctl status httpd
systemctl restart httpd
systemctl reload httpd  # Graceful restart

# Apache (Ubuntu/Debian)
systemctl status apache2
systemctl restart apache2
systemctl reload apache2
```

**Database:**
```bash
systemctl status mariadb
systemctl restart mariadb

# Check connections
mysql -e "SHOW PROCESSLIST;"

# Optimize tables
mysqlcheck -o adialer -u root -p
```

### Service Dependencies

**Start order:**
1. MariaDB (database)
2. Asterisk (telephony)
3. Apache (web server)
4. ARI Dialer (stasis app)

**Check dependencies:**
```bash
systemctl list-dependencies ari-dialer
```

### Automatic Service Recovery

**Configure automatic restart on failure:**

Edit `/etc/systemd/system/ari-dialer.service`:
```ini
[Service]
Restart=always
RestartSec=10
StartLimitBurst=5
StartLimitIntervalSec=60
```

```bash
systemctl daemon-reload
systemctl restart ari-dialer
```

### Process Management

**Check running processes:**
```bash
# ARI Dialer
ps aux | grep node
pgrep -f "node app.js"

# Asterisk
ps aux | grep asterisk
asterisk -rx "core show channels"

# Apache
ps aux | grep httpd  # or apache2
```

**Kill stuck processes:**
```bash
# Graceful
systemctl stop ari-dialer

# Force kill
pkill -9 -f "node app.js"

# Restart
systemctl start ari-dialer
```

---

## Database Management

### Database Access

**Login to MySQL:**
```bash
mysql -u root -p
mysql -u adialer_user -p adialer
```

### Database Maintenance

**Optimize tables:**
```bash
mysqlcheck -o adialer -u root -p
```

**Repair tables:**
```bash
mysqlcheck -r adialer -u root -p
```

**Analyze tables:**
```bash
mysqlcheck -a adialer -u root -p
```

**All maintenance at once:**
```bash
mysqlcheck -o -r -a adialer -u root -p
```

### Performance Tuning

**Check table sizes:**
```sql
SELECT
    table_name,
    ROUND(((data_length + index_length) / 1024 / 1024), 2) AS "Size (MB)"
FROM information_schema.TABLES
WHERE table_schema = "adialer"
ORDER BY (data_length + index_length) DESC;
```

**Add indexes for performance:**
```sql
USE adialer;

-- Campaign numbers optimization
CREATE INDEX idx_campaign_status ON campaign_numbers(campaign_id, status);
CREATE INDEX idx_phone_number ON campaign_numbers(phone_number);

-- CDR optimization
CREATE INDEX idx_cdr_campaign_date ON cdr(campaign_id, start_time);
CREATE INDEX idx_cdr_disposition ON cdr(disposition);
CREATE INDEX idx_cdr_date_range ON cdr(start_time, end_time);

-- Active channels optimization
CREATE INDEX idx_channel_campaign ON active_channels(campaign_id, created_at);

-- Show indexes
SHOW INDEX FROM campaign_numbers;
```

### Data Cleanup

**Delete old CDR records:**
```sql
-- Delete CDR older than 90 days
DELETE FROM cdr
WHERE start_time < DATE_SUB(NOW(), INTERVAL 90 DAY);
```

**Archive old campaigns:**
```sql
-- Archive completed campaigns to separate table
CREATE TABLE campaigns_archive LIKE campaigns;

INSERT INTO campaigns_archive
SELECT * FROM campaigns
WHERE status = 'stopped'
AND updated_at < DATE_SUB(NOW(), INTERVAL 180 DAY);

-- Verify and delete
DELETE FROM campaigns
WHERE status = 'stopped'
AND updated_at < DATE_SUB(NOW(), INTERVAL 180 DAY);
```

**Clean up orphaned records:**
```sql
-- Find campaign numbers without parent campaign
SELECT COUNT(*) FROM campaign_numbers
WHERE campaign_id NOT IN (SELECT id FROM campaigns);

-- Delete orphaned records
DELETE FROM campaign_numbers
WHERE campaign_id NOT IN (SELECT id FROM campaigns);
```

### Database Users

**Create read-only user for reporting:**
```sql
CREATE USER 'adialer_readonly'@'localhost' IDENTIFIED BY 'password';
GRANT SELECT ON adialer.* TO 'adialer_readonly'@'localhost';
FLUSH PRIVILEGES;
```

**Change passwords:**
```sql
ALTER USER 'adialer_user'@'localhost' IDENTIFIED BY 'new_password';
FLUSH PRIVILEGES;
```

Update in configuration files:
```bash
nano /var/www/html/adial/application/config/database.php
nano /var/www/html/adial/stasis-app/.env
```

---

## Backup and Recovery

### Full System Backup

**Backup script (`/usr/local/bin/backup-adial.sh`):**
```bash
#!/bin/bash

BACKUP_DIR="/backup/adial"
DATE=$(date +%Y%m%d_%H%M%S)
RETENTION_DAYS=30

mkdir -p $BACKUP_DIR

# 1. Database backup
echo "Backing up database..."
mysqldump -u root -p'password' adialer | gzip > $BACKUP_DIR/db_$DATE.sql.gz

# 2. Application files
echo "Backing up application..."
tar -czf $BACKUP_DIR/app_$DATE.tar.gz \
  --exclude='logs/*' \
  --exclude='recordings/*' \
  --exclude='node_modules/*' \
  /var/www/html/adial

# 3. Configuration files
echo "Backing up configurations..."
tar -czf $BACKUP_DIR/config_$DATE.tar.gz \
  /etc/asterisk/ari.conf \
  /etc/asterisk/pjsip.conf \
  /etc/systemd/system/ari-dialer.service \
  /etc/httpd/conf.d/adial.conf

# 4. Recordings (optional - can be large)
# echo "Backing up recordings..."
# rsync -av /var/spool/asterisk/monitor/ $BACKUP_DIR/recordings_$DATE/

# 5. Delete old backups
find $BACKUP_DIR -name "*.gz" -mtime +$RETENTION_DAYS -delete
find $BACKUP_DIR -name "*.tar.gz" -mtime +$RETENTION_DAYS -delete

echo "Backup completed: $BACKUP_DIR"
```

**Make executable and schedule:**
```bash
chmod +x /usr/local/bin/backup-adial.sh

# Add to crontab (daily at 2 AM)
crontab -e
0 2 * * * /usr/local/bin/backup-adial.sh >> /var/log/adial-backup.log 2>&1
```

### Database Backup

**Full backup:**
```bash
mysqldump -u root -p adialer > adialer_$(date +%Y%m%d).sql
gzip adialer_$(date +%Y%m%d).sql
```

**Schema only:**
```bash
mysqldump -u root -p --no-data adialer > adialer_schema.sql
```

**Specific tables:**
```bash
mysqldump -u root -p adialer campaigns campaign_numbers > campaigns_backup.sql
```

**Automated daily backup:**
```bash
#!/bin/bash
mysqldump -u root -p'password' adialer | gzip > /backup/db/adialer_$(date +%Y%m%d).sql.gz
find /backup/db -name "*.sql.gz" -mtime +30 -delete
```

### Restore from Backup

**Restore database:**
```bash
# Unzip if compressed
gunzip adialer_20241114.sql.gz

# Restore
mysql -u root -p adialer < adialer_20241114.sql

# Or create new database first
mysql -u root -p -e "DROP DATABASE IF EXISTS adialer; CREATE DATABASE adialer;"
mysql -u root -p adialer < adialer_20241114.sql
```

**Restore application files:**
```bash
cd /var/www/html
tar -xzf /backup/adial/app_20241114.tar.gz
chown -R apache:apache adial
```

**Restore configurations:**
```bash
tar -xzf /backup/adial/config_20241114.tar.gz -C /
systemctl daemon-reload
systemctl restart asterisk
systemctl restart httpd
systemctl restart ari-dialer
```

### Disaster Recovery Plan

**1. Prepare recovery server:**
```bash
# Install base system
sudo ./install.sh
```

**2. Restore database:**
```bash
mysql -u root -p adialer < latest_backup.sql
```

**3. Restore application:**
```bash
tar -xzf app_backup.tar.gz
chown -R apache:apache /var/www/html/adial
```

**4. Restore configurations:**
```bash
cp ari.conf /etc/asterisk/
cp pjsip.conf /etc/asterisk/
systemctl restart asterisk
```

**5. Verify services:**
```bash
systemctl status asterisk
systemctl status httpd
systemctl status ari-dialer
```

**6. Test functionality:**
- Access web interface
- Create test campaign
- Make test call
- Verify recordings

---

## Monitoring and Logging

### Log Locations

```
Application Logs:
/var/www/html/adial/logs/                 # Application logs
/var/www/html/adial/logs/stasis-combined.log  # Stasis app logs

Asterisk Logs:
/var/log/asterisk/full                    # Full Asterisk log
/var/log/asterisk/messages                # Main messages
/var/log/asterisk/queue_log               # Queue events

Web Server Logs:
/var/log/httpd/error_log                  # Apache errors (CentOS)
/var/log/httpd/access_log                 # Apache access (CentOS)
/var/log/apache2/error.log                # Apache errors (Ubuntu)
/var/log/apache2/access.log               # Apache access (Ubuntu)

System Logs:
journalctl -u ari-dialer                  # Systemd logs
/var/log/messages                         # System messages
```

### Real-Time Log Monitoring

**Tail multiple logs:**
```bash
# Application
tail -f /var/www/html/adial/logs/*.log

# Stasis app
journalctl -u ari-dialer -f

# Asterisk
tail -f /var/log/asterisk/full

# All together (using multitail)
multitail /var/log/asterisk/full /var/www/html/adial/logs/stasis-combined.log
```

### Log Rotation

**Configure logrotate for application logs:**

Create `/etc/logrotate.d/adial`:
```
/var/www/html/adial/logs/*.log {
    daily
    missingok
    rotate 30
    compress
    delaycompress
    notifempty
    create 0640 apache apache
    sharedscripts
}
```

**Asterisk log rotation:**
```
/var/log/asterisk/full
/var/log/asterisk/messages {
    daily
    rotate 30
    compress
    delaycompress
    missingok
    notifempty
    postrotate
        /usr/sbin/asterisk -rx 'logger reload' > /dev/null 2>&1
    endscript
}
```

**Force rotation:**
```bash
logrotate -f /etc/logrotate.d/adial
```

### System Monitoring

**CPU and Memory:**
```bash
# Real-time monitoring
top
htop

# Current usage
free -h
df -h

# Process specific
ps aux | grep -E 'asterisk|httpd|node'
```

**Disk Space:**
```bash
# Overall
df -h

# Directory sizes
du -sh /var/www/html/adial/*
du -sh /var/spool/asterisk/monitor
du -sh /var/lib/asterisk/sounds/dialer

# Largest files
find /var/spool/asterisk/monitor -type f -exec du -h {} + | sort -rh | head -20
```

**Network:**
```bash
# Connections
netstat -tulpn | grep -E '80|8088|5060'
ss -tulpn | grep -E '80|8088|5060'

# Traffic
iftop
nethogs

# SIP connections
asterisk -rx "pjsip show endpoints"
```

### Performance Metrics

**Database performance:**
```bash
# Current queries
mysql -e "SHOW PROCESSLIST;"

# Slow queries
mysql -e "SHOW VARIABLES LIKE 'slow_query%';"

# Table sizes and rows
mysql -e "SELECT table_name, table_rows, ROUND(((data_length + index_length) / 1024 / 1024), 2) AS Size_MB FROM information_schema.TABLES WHERE table_schema = 'adialer';"
```

**Asterisk performance:**
```bash
asterisk -rx "core show channels"
asterisk -rx "core show calls"
asterisk -rx "core show uptime"
asterisk -rx "core show sysinfo"
```

### Alerting

**Create monitoring script (`/usr/local/bin/adial-healthcheck.sh`):**
```bash
#!/bin/bash

ALERT_EMAIL="admin@example.com"

# Check services
if ! systemctl is-active --quiet ari-dialer; then
    echo "ARI Dialer service is down!" | mail -s "ALERT: ARI Dialer Down" $ALERT_EMAIL
    systemctl restart ari-dialer
fi

if ! systemctl is-active --quiet asterisk; then
    echo "Asterisk is down!" | mail -s "ALERT: Asterisk Down" $ALERT_EMAIL
    systemctl restart asterisk
fi

# Check disk space (alert if >90%)
DISK_USAGE=$(df / | awk 'NR==2 {print $5}' | sed 's/%//')
if [ $DISK_USAGE -gt 90 ]; then
    echo "Disk usage is at ${DISK_USAGE}%" | mail -s "ALERT: Low Disk Space" $ALERT_EMAIL
fi

# Check database connectivity
if ! mysql -u adialer_user -p'password' -e "USE adialer;" 2>/dev/null; then
    echo "Database connection failed!" | mail -s "ALERT: Database Issue" $ALERT_EMAIL
fi
```

**Schedule:**
```bash
chmod +x /usr/local/bin/adial-healthcheck.sh

# Run every 5 minutes
crontab -e
*/5 * * * * /usr/local/bin/adial-healthcheck.sh
```

---

## Performance Tuning

### PHP Optimization

**Edit `/etc/php.ini`:**
```ini
memory_limit = 512M
max_execution_time = 300
upload_max_filesize = 100M
post_max_size = 100M

; Enable OPcache
opcache.enable=1
opcache.memory_consumption=256
opcache.max_accelerated_files=10000
opcache.revalidate_freq=60
```

**Restart Apache:**
```bash
systemctl restart httpd
```

### MySQL Optimization

**Edit `/etc/my.cnf` or `/etc/mysql/my.cnf`:**
```ini
[mysqld]
# InnoDB settings
innodb_buffer_pool_size = 2G
innodb_log_file_size = 256M
innodb_flush_log_at_trx_commit = 2
innodb_flush_method = O_DIRECT

# Query cache (MySQL 5.7 only)
query_cache_type = 1
query_cache_size = 256M

# Connection settings
max_connections = 500
max_allowed_packet = 64M

# Logging
slow_query_log = 1
slow_query_log_file = /var/log/mysql/slow-query.log
long_query_time = 2
```

**Restart MariaDB:**
```bash
systemctl restart mariadb
```

### Apache Optimization

**Edit `/etc/httpd/conf/httpd.conf`:**
```apache
# MPM Prefork settings
<IfModule mpm_prefork_module>
    StartServers             5
    MinSpareServers          5
    MaxSpareServers         10
    MaxRequestWorkers      250
    MaxConnectionsPerChild   0
</IfModule>

# Enable compression
<IfModule mod_deflate.c>
    AddOutputFilterByType DEFLATE text/html text/plain text/xml text/css text/javascript application/javascript
</IfModule>

# Enable caching
<IfModule mod_expires.c>
    ExpiresActive On
    ExpiresByType image/jpg "access plus 1 year"
    ExpiresByType image/jpeg "access plus 1 year"
    ExpiresByType image/gif "access plus 1 year"
    ExpiresByType image/png "access plus 1 year"
    ExpiresByType text/css "access plus 1 month"
    ExpiresByType application/javascript "access plus 1 month"
</IfModule>
```

### Asterisk Optimization

**Edit `/etc/asterisk/asterisk.conf`:**
```ini
[options]
maxload = 2.0
maxcalls = 500
```

**Edit `/etc/asterisk/rtp.conf`:**
```ini
[general]
rtpstart=10000
rtpend=20000
```

**Optimize codecs - prefer ulaw for best performance:**
```ini
[endpoint]
allow=!all,ulaw
```

---

## Security Hardening

### Firewall Configuration

**CentOS/RHEL (firewalld):**
```bash
# Allow HTTP/HTTPS
firewall-cmd --permanent --add-service=http
firewall-cmd --permanent --add-service=https

# Restrict ARI to localhost only
# (No external rule needed - bind to 127.0.0.1)

# Allow SIP from trusted IPs only
firewall-cmd --permanent --add-rich-rule='rule family="ipv4" source address="PROVIDER_IP" port port="5060" protocol="udp" accept'

# Allow RTP from trusted IPs
firewall-cmd --permanent --add-rich-rule='rule family="ipv4" source address="PROVIDER_IP" port port="10000-20000" protocol="udp" accept'

firewall-cmd --reload
```

**Ubuntu/Debian (ufw):**
```bash
ufw allow 80/tcp
ufw allow 443/tcp
ufw allow from PROVIDER_IP to any port 5060 proto udp
ufw allow from PROVIDER_IP to any port 10000:20000 proto udp
ufw enable
```

### SSL/TLS Configuration

**Install Let's Encrypt certificate:**
```bash
# Install certbot
yum install certbot python3-certbot-apache  # CentOS
apt install certbot python3-certbot-apache  # Ubuntu

# Get certificate
certbot --apache -d your-domain.com

# Auto-renewal
echo "0 3 * * * /usr/bin/certbot renew --quiet" | crontab -
```

### Fail2Ban for Asterisk

**Install:**
```bash
yum install fail2ban  # CentOS
apt install fail2ban  # Ubuntu
```

**Configure `/etc/fail2ban/jail.local`:**
```ini
[asterisk]
enabled = true
port = 5060,5061
protocol = udp
filter = asterisk
logpath = /var/log/asterisk/messages
maxretry = 5
bantime = 3600
```

**Start:**
```bash
systemctl enable fail2ban
systemctl start fail2ban
```

### Secure Passwords

**Change all default passwords:**
```bash
# Database
mysql -u root -p
ALTER USER 'adialer_user'@'localhost' IDENTIFIED BY 'STRONG_PASSWORD';

# ARI
nano /etc/asterisk/ari.conf
# Change password

# Update configs
nano /var/www/html/adial/application/config/database.php
nano /var/www/html/adial/application/config/ari.php
nano /var/www/html/adial/stasis-app/.env

# Restart services
systemctl restart ari-dialer
```

### File Integrity Monitoring

**Install AIDE:**
```bash
yum install aide  # CentOS
apt install aide  # Ubuntu

# Initialize
aide --init
mv /var/lib/aide/aide.db.new.gz /var/lib/aide/aide.db.gz

# Check integrity daily
echo "0 5 * * * /usr/sbin/aide --check" | crontab -
```

---

## Maintenance Tasks

### Daily Tasks

- ✅ Check service status
- ✅ Review error logs
- ✅ Monitor disk space
- ✅ Check backup completion

### Weekly Tasks

- ✅ Review CDR reports
- ✅ Analyze call statistics
- ✅ Check for failed campaigns
- ✅ Clean up old logs
- ✅ Optimize database tables

### Monthly Tasks

- ✅ Archive old campaigns
- ✅ Clean up old recordings
- ✅ Review security logs
- ✅ Update system packages
- ✅ Test backup restoration
- ✅ Review user accounts

### Quarterly Tasks

- ✅ Full system audit
- ✅ Performance review
- ✅ Capacity planning
- ✅ Documentation update

---

## Upgrades and Updates

### System Updates

**CentOS/RHEL:**
```bash
yum update -y
reboot  # If kernel updated
```

**Ubuntu/Debian:**
```bash
apt update
apt upgrade -y
reboot  # If kernel updated
```

### Application Updates

```bash
cd /var/www/html/adial

# Backup first!
/usr/local/bin/backup-adial.sh

# Pull updates
git fetch
git pull origin master

# Update Node dependencies
cd stasis-app
npm install

# Restart services
systemctl restart ari-dialer
systemctl restart httpd
```

### Database Schema Updates

**Check for migrations:**
```bash
# If migration files exist
mysql -u root -p adialer < migrations/update_v2.0.sql
```

---

## Disaster Recovery

### Recovery Time Objectives

- **RTO (Recovery Time Objective):** 4 hours
- **RPO (Recovery Point Objective):** 24 hours (daily backups)

### Recovery Procedures

See [Backup and Recovery](#backup-and-recovery) section above.

### Business Continuity

**Failover server preparation:**
1. Maintain standby server
2. Daily database replication
3. Synchronized recordings
4. Documented procedures

---

**Document Version:** 1.0
**Last Updated:** 2024-11-14
