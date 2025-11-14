# ARI Dialer - Frequently Asked Questions (FAQ)

## Table of Contents

1. [General Questions](#general-questions)
2. [Installation & Setup](#installation--setup)
3. [Campaign Management](#campaign-management)
4. [IVR Configuration](#ivr-configuration)
5. [Call Issues](#call-issues)
6. [Recording & Audio](#recording--audio)
7. [Performance & Scalability](#performance--scalability)
8. [Security](#security)
9. [Troubleshooting](#troubleshooting)
10. [Integration](#integration)

---

## General Questions

### What is ARI Dialer?

ARI Dialer is a web-based auto-dialer application built on Asterisk's ARI (Asterisk REST Interface). It allows you to:
- Create and manage outbound calling campaigns
- Configure IVR (Interactive Voice Response) menus
- Monitor calls in real-time
- Record and review call history
- Generate detailed reports (CDR)

### What are the system requirements?

**Minimum:**
- 2 CPU cores
- 2 GB RAM
- 10 GB disk space
- CentOS 7+ or Ubuntu 18.04+
- Asterisk 16+
- PHP 7.2+
- MariaDB 10.2+
- Node.js 14+

**Recommended:**
- 4+ CPU cores
- 4+ GB RAM
- 20+ GB disk space (for recordings)

### Is ARI Dialer open source?

The licensing depends on your deployment. Check the LICENSE file in the repository for details.

### What languages are supported?

Currently supported languages:
- **English** - Full support
- **Russian** - Полная поддержка

Additional languages can be added by creating language files in `application/language/[language_code]/`.

### Can I use ARI Dialer with cloud-based Asterisk?

Yes! ARI Dialer can connect to:
- Local Asterisk installation
- Remote Asterisk server
- Cloud-hosted Asterisk (AWS, DigitalOcean, etc.)
- Asterisk in Docker containers

Just configure the ARI connection settings in `application/config/ari.php`.

---

## Installation & Setup

### How do I install ARI Dialer?

**Quick Method:**
```bash
cd /var/www/html/adial
sudo ./install.sh
```

The automated installer handles everything. See `INSTALL.md` for detailed instructions.

### Can I install on Windows?

ARI Dialer is designed for Linux. For Windows:
- Use WSL2 (Windows Subsystem for Linux)
- Use a Linux VM (VirtualBox, VMware)
- Use Docker containers

### Do I need root access to install?

Yes, root/sudo access is required for:
- Installing system packages
- Configuring Apache/web server
- Setting up systemd services
- Configuring Asterisk
- Setting file permissions

### How do I upgrade to a newer version?

```bash
cd /var/www/html/adial
git pull origin master
systemctl restart ari-dialer
systemctl restart httpd
```

For major updates, check release notes for additional steps.

### Can I run multiple instances on the same server?

Yes, but you'll need to:
- Use separate directories
- Configure different database names
- Use different ports or virtual hosts
- Run separate Stasis app instances

---

## Campaign Management

### How many concurrent calls can I make?

This depends on:
- **Server resources** - 1 call ≈ 0.1 CPU core + 10 MB RAM
- **Network bandwidth** - ~100 Kbps per call
- **Trunk capacity** - Your SIP provider's limit
- **Asterisk performance** - Typical: 100-500 calls per server

**Recommendation:** Start with 5-10 concurrent calls, then scale based on performance.

### What's the maximum number of numbers in a campaign?

There's no hard limit. Tested with:
- ✅ 1,000 numbers - Excellent performance
- ✅ 10,000 numbers - Good performance
- ✅ 100,000 numbers - Works, may need optimization

For very large lists (>100k), consider:
- Breaking into multiple campaigns
- Database indexing optimization
- Increasing server resources

### Can I pause and resume campaigns?

Yes! Campaign states:
- **Stopped** - Not running, can edit
- **Running** - Active dialing
- **Paused** - Temporarily stopped, can resume
- Completed calls continue even when paused

### How do I schedule a campaign for later?

Currently, campaigns must be started manually. For scheduled campaigns:
- Use cron jobs to call campaign start API
- Use system scheduler with curl commands
- Or manually start at desired time

**Example cron:**
```bash
# Start campaign ID 5 at 9 AM daily
0 9 * * * curl -X POST http://localhost/adial/campaigns/control/5/start
```

### Can I import numbers from CRM?

Yes! Export from your CRM as CSV:
```csv
phone_number,data
+12125551001,{"crm_id":"12345","name":"John Doe"}
+12125551002,{"crm_id":"67890","name":"Jane Smith"}
```

The `data` column can store any JSON data for reference.

### What happens if a call fails?

Failed calls are:
1. Marked with "Failed" disposition in CDR
2. Automatically retried based on campaign settings
3. Retry attempts tracked in database
4. Final status updated after max retries exceeded

### How do retries work?

Configure in campaign settings:
- **Retry Times** - Number of attempts (0-10)
- **Retry Delay** - Seconds between retries (60-3600)

Example: 3 retries with 300 second delay
- Attempt 1 - Fails at 10:00
- Attempt 2 - Retries at 10:05
- Attempt 3 - Retries at 10:10
- Attempt 4 - Final retry at 10:15

---

## IVR Configuration

### What audio formats are supported?

**Upload formats:**
- WAV (any sample rate)
- MP3 (any bitrate)

**System automatically converts to:**
- WAV format
- 8000 Hz sample rate
- Mono channel
- PCM encoding

### How do I create professional IVR audio?

**Options:**
1. **Professional service** - Hire voice talent
2. **Text-to-Speech** - Use online TTS services
3. **Record yourself** - Use Audacity or similar
4. **Online studios** - Fiverr, Upwork, etc.

**Tips:**
- Use quiet environment
- Speak clearly and slowly
- Keep messages under 20 seconds
- Test before uploading

### Can I have multi-level IVR menus?

Yes! Chain IVR menus together:

```
Main Menu:
  Press 1 → Sales IVR
  Press 2 → Support IVR

Sales IVR:
  Press 1 → New Sales Agent
  Press 2 → Existing Customers Agent
```

### How many DTMF options can I have?

Maximum 14 options per IVR:
- 0-9 (10 digits)
- * (star)
- # (hash)
- i (invalid input)
- t (timeout)

**Recommended:** 3-4 main options for best user experience.

### Can IVR transfer to external numbers?

Yes, if your Asterisk dialplan allows:
```
Action Type: Call Extension
Value: Local/+12125551234@outbound
```

Configure the `outbound` context in Asterisk to handle external transfers.

### What happens if caller presses an unmapped key?

If you defined 'i' (invalid) handler:
- That action executes (e.g., replay menu)

If no 'i' handler:
- Call may hang up (depends on Asterisk dialplan)

**Always add 'i' and 't' handlers for better UX!**

---

## Call Issues

### Why are all my calls failing?

**Common causes:**

1. **Trunk not registered**
   ```bash
   asterisk -rx "pjsip show endpoints"
   # Check trunk status
   ```

2. **Invalid phone numbers**
   - Use E.164 format: +[country][number]
   - Example: +12125551234

3. **Wrong trunk configuration**
   - Verify trunk name in campaign settings
   - Test: `asterisk -rx "channel originate PJSIP/trunk/1234567890 application echo"`

4. **Credentials issue**
   - Check SIP username/password
   - Verify with provider

5. **Firewall blocking**
   - Open ports: 5060 (SIP), 10000-20000 (RTP)
   - Check firewall rules

### Calls connect but no audio

**Solutions:**

1. **NAT issues**
   - Configure `external_media_address` in pjsip.conf
   - Set `external_signaling_address`

2. **Codec mismatch**
   - Enable common codecs: ulaw, alaw
   - Check both sides support same codec

3. **Firewall blocking RTP**
   - Open RTP ports: 10000-20000 UDP
   - Configure firewall rules

4. **One-way audio**
   - Usually NAT/firewall issue
   - Check Asterisk NAT settings

### Calls answer then immediately hang up

**Causes:**

1. **Agent destination unreachable**
   - Verify extension exists: `asterisk -rx "pjsip show endpoints"`
   - Check extension is registered

2. **Dialplan error**
   - Review Asterisk full log
   - Check Stasis application name matches

3. **Permission issue**
   - Verify ARI user has correct permissions
   - Check ari.conf: `read_only = no`

### How do I test if Asterisk ARI is working?

```bash
# Test ARI connectivity
curl -u username:password http://localhost:8088/ari/asterisk/info

# Should return JSON with Asterisk version info
```

If fails:
- Check ARI enabled: `/etc/asterisk/ari.conf`
- Verify credentials
- Restart Asterisk: `systemctl restart asterisk`

---

## Recording & Audio

### Where are call recordings stored?

Default location: `/var/spool/asterisk/monitor/`

Configured in `application/config/ari.php`:
```php
$config['recording_dir'] = '/var/spool/asterisk/monitor';
```

### What recording formats are available?

- **WAV** - Uncompressed, high quality, large files
- **MP3** - Compressed, smaller files (if configured)

Configure in `application/config/ari.php`:
```php
$config['recording_format'] = 'wav'; // or 'mp3'
```

### How much disk space do recordings use?

**Approximate sizes:**
- WAV: ~1 MB per minute
- MP3: ~0.5 MB per minute

**Example:**
- 100 calls/day
- 5 minutes average
- WAV: ~500 MB/day = ~15 GB/month
- MP3: ~250 MB/day = ~7.5 GB/month

**Plan accordingly!**

### Can I automatically delete old recordings?

Yes, create a cron job:

```bash
# Delete recordings older than 30 days
0 2 * * * find /var/spool/asterisk/monitor -name "*.wav" -mtime +30 -delete
```

Or use logrotate-style scripts.

### How do I download multiple recordings at once?

1. **From web interface:**
   - Go to CDR
   - Filter desired calls
   - Click individual "Download" buttons

2. **From server:**
   ```bash
   # Zip recordings from specific date
   cd /var/spool/asterisk/monitor
   zip recordings-2024-11-14.zip *2024-11-14*.wav
   ```

3. **Programmatically:**
   - Use CDR API to get recording file paths
   - Download via SFTP/SCP

### Why is there no recording for some calls?

**Reasons:**

1. **Recording not enabled**
   - Check campaign settings: "Record Calls" must be ✓

2. **Call too short**
   - No answer = no recording
   - Check disposition: must be "Answered"

3. **Disk space full**
   - Check: `df -h`
   - Free up space

4. **Permission issue**
   - Asterisk needs write permission
   - Fix: `chmod 777 /var/spool/asterisk/monitor`

5. **Recording failed**
   - Check Asterisk logs: `/var/log/asterisk/full`

---

## Performance & Scalability

### How do I improve performance?

**Database:**
```sql
-- Add indexes
CREATE INDEX idx_campaign_numbers_status ON campaign_numbers(status);
CREATE INDEX idx_cdr_campaign ON cdr(campaign_id, start_time);
CREATE INDEX idx_cdr_start_time ON cdr(start_time);
```

**Server:**
- Increase PHP memory: `memory_limit = 512M`
- Increase PHP max execution time
- Use PHP OPcache
- Optimize MySQL settings

**Asterisk:**
- Tune Asterisk for high call volume
- Use efficient codecs (ulaw)
- Disable unnecessary modules

### Can I use multiple servers?

Yes, for horizontal scaling:

**Architecture:**
```
Load Balancer
    ↓
┌───────┬───────┬───────┐
│ Web 1 │ Web 2 │ Web 3 │
└───┬───┴───┬───┴───┬───┘
    └───────┼───────┘
        Database
            ↓
    Asterisk Server(s)
```

**Requirements:**
- Shared database (MySQL cluster or single server)
- Shared file storage for recordings (NFS, S3)
- Load balancer (HAProxy, Nginx)

### What's the maximum call capacity?

**Single server limits:**
- **100-500 concurrent calls** - Typical setup
- **1000+ calls** - High-end server with optimization

**Factors:**
- CPU: 0.1-0.2 cores per call
- RAM: 10-20 MB per call
- Network: 100 Kbps per call
- Disk I/O: Important for recordings

**Example server:**
- 16 cores, 32 GB RAM
- Theoretical: 80-160 concurrent calls
- Practical: 50-100 concurrent calls (with headroom)

### How do I monitor system performance?

```bash
# CPU and memory
top
htop

# Asterisk calls
asterisk -rx "core show channels"

# Database queries
mysql -e "SHOW PROCESSLIST;"

# Disk I/O
iostat -x 1

# Network
iftop
```

**Monitoring tools:**
- Grafana + Prometheus
- Nagios
- Zabbix
- Custom scripts

---

## Security

### How do I secure ARI Dialer?

**Web Interface:**
1. Use HTTPS (SSL/TLS)
2. Strong passwords
3. Restrict access by IP
4. Enable firewall
5. Regular updates

**Database:**
1. Change default passwords
2. Use strong passwords
3. Bind to localhost only
4. Regular backups
5. Limit user privileges

**Asterisk:**
1. Change ARI password
2. Use complex passwords
3. Restrict ARI access by IP
4. Use fail2ban for SIP
5. Disable unnecessary services

### Should I use HTTPS?

**Yes!** Especially if:
- Accessing over internet
- Multiple users
- Sensitive data
- Compliance requirements (HIPAA, PCI, etc.)

**Setup with Let's Encrypt:**
```bash
certbot --apache -d your-domain.com
```

### How do I restrict access by IP?

**Apache configuration:**
```apache
<Directory /var/www/html/adial>
    Require ip 192.168.1.0/24
    Require ip 10.0.0.0/8
</Directory>
```

**Or use firewall:**
```bash
firewall-cmd --permanent --add-rich-rule='rule family="ipv4" source address="192.168.1.0/24" port port="80" protocol="tcp" accept'
```

### What ports need to be open?

**Web Interface:**
- 80 (HTTP) - Can restrict to internal network
- 443 (HTTPS) - For SSL

**Asterisk:**
- 8088 (ARI HTTP/WebSocket) - Localhost only
- 5060 (SIP signaling) - SIP trunk provider only
- 10000-20000 (RTP media) - SIP trunk provider only

**Database:**
- 3306 (MySQL) - Localhost only

**Recommendation:** Only expose what's necessary!

### How do I backup the system?

**Database backup:**
```bash
mysqldump -u root -p adialer > adialer_backup_$(date +%Y%m%d).sql
```

**Files backup:**
```bash
tar -czf adial_files_$(date +%Y%m%d).tar.gz \
  /var/www/html/adial \
  --exclude=logs/* \
  --exclude=recordings/*
```

**Recordings backup:**
```bash
rsync -av /var/spool/asterisk/monitor/ /backup/recordings/
```

**Automated daily backup:**
```bash
0 2 * * * /usr/local/bin/backup-adial.sh
```

---

## Troubleshooting

### Stasis application not starting

**Check status:**
```bash
systemctl status ari-dialer
journalctl -u ari-dialer -n 50
```

**Common issues:**

1. **Node modules missing**
   ```bash
   cd /var/www/html/adial/stasis-app
   npm install
   ```

2. **Database connection failed**
   - Check `.env` file
   - Test: `mysql -u username -p database_name`

3. **ARI connection failed**
   - Check Asterisk running
   - Verify ARI credentials
   - Test ARI endpoint

4. **Port already in use**
   - Check what's using port
   - Change port in configuration

### Web interface shows blank page

**Check logs:**
```bash
tail -f /var/log/httpd/error_log  # CentOS
tail -f /var/log/apache2/error.log  # Ubuntu
```

**Common fixes:**

1. **PHP errors**
   - Enable display_errors in php.ini (dev only)
   - Check PHP error log

2. **Permission issues**
   ```bash
   chown -R apache:apache /var/www/html/adial  # CentOS
   chown -R www-data:www-data /var/www/html/adial  # Ubuntu
   chmod -R 755 /var/www/html/adial
   ```

3. **Missing PHP extensions**
   ```bash
   php -m  # List installed modules
   # Install missing: php-mysql, php-json, etc.
   ```

### Database connection errors

**Error:** "Connection failed"

**Solutions:**
1. Check MySQL running: `systemctl status mariadb`
2. Test credentials: `mysql -u username -p`
3. Verify database exists: `SHOW DATABASES;`
4. Check config: `application/config/database.php`

### High server load

**Diagnose:**
```bash
top  # Check CPU/RAM usage
iotop  # Check disk I/O
asterisk -rx "core show channels"  # Active calls
```

**Common causes:**
1. Too many concurrent calls
2. Database query inefficiency
3. Disk I/O bottleneck (recordings)
4. Memory leak

**Solutions:**
1. Reduce concurrent calls
2. Optimize database queries
3. Use faster storage
4. Restart services
5. Add resources

---

## Integration

### Can I integrate with a CRM?

Yes! Options:

1. **CSV Export/Import**
   - Export campaigns from CRM
   - Import to ARI Dialer
   - Export CDR results
   - Import back to CRM

2. **API Integration**
   - Use ARI Dialer API endpoints
   - Programmatic campaign creation
   - Real-time status updates

3. **Database direct access**
   - CRM reads from ARI Dialer database
   - Real-time call status
   - Requires proper permissions

### Is there an API?

Yes, RESTful API available at:
```
http://your-server/adial/api/
```

**Common endpoints:**
- `GET /api/campaigns` - List campaigns
- `POST /api/campaigns` - Create campaign
- `POST /api/campaigns/{id}/start` - Start campaign
- `GET /api/cdr` - Get call records

See `API.md` for full documentation (if available).

### Can I send campaign results to webhook?

Currently not built-in, but you can:

1. **Custom modification:**
   - Edit Stasis app (`stasis-app/app.js`)
   - Add webhook calls on call events

2. **Poll API:**
   - Periodically check CDR API
   - Fetch new records
   - Send to webhook

3. **Database triggers:**
   - MySQL triggers on CDR table
   - Call external webhook

### How do I export data to Excel?

1. **From web interface:**
   - Go to CDR
   - Click "Export to CSV"
   - Open CSV in Excel

2. **Direct database export:**
   ```bash
   mysql -u root -p -e "SELECT * FROM cdr" adialer > cdr_export.csv
   ```

3. **Formatted export:**
   - Use MySQL Workbench
   - Export as CSV/Excel format

### Can I use with Microsoft Teams / Zoom?

Indirect integration possible:
- Use SIP gateway to bridge Asterisk ↔ Teams/Zoom
- Route calls through gateway
- Requires additional setup

---

## Additional Resources

- **Installation Guide:** `INSTALL.md`
- **User Manual:** `USER_MANUAL.md`
- **Troubleshooting:** `TROUBLESHOOTING.md`
- **Quick Start:** `QUICKSTART.md`

---

**Still have questions?**

1. Check system logs
2. Review documentation
3. Search GitHub issues
4. Consult Asterisk ARI documentation

---

**Document Version:** 1.0
**Last Updated:** 2024-11-14
