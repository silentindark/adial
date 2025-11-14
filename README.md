# ARI Dialer

![Version](https://img.shields.io/badge/version-1.0.0-blue.svg)
![License](https://img.shields.io/badge/license-Proprietary-red.svg)
![Asterisk](https://img.shields.io/badge/asterisk-16%2B-green.svg)
![PHP](https://img.shields.io/badge/php-7.2%2B-purple.svg)
![Node.js](https://img.shields.io/badge/node.js-14%2B-brightgreen.svg)

A powerful, feature-rich auto-dialer system built on Asterisk's REST Interface (ARI). Perfect for outbound calling campaigns, customer surveys, appointment reminders, and emergency notifications.

## ✨ Key Features

### 📞 Campaign Management
- **Multiple concurrent campaigns** with independent configurations
- **Flexible routing** - PJSIP, SIP, or custom dial strings
- **Smart retry logic** with configurable attempts and delays
- **Real-time control** - Start, Stop, Pause campaigns on-the-fly
- **Bulk number import** via CSV with custom data fields
- **Live progress tracking** with detailed statistics

### 🎙️ Interactive Voice Response (IVR)
- **Multi-level IVR menus** with unlimited nesting
- **DTMF detection** with 14 possible inputs (0-9, *, #, i, t)
- **Flexible actions** - Transfer, Queue, Hangup, Playback, Chain IVRs
- **Audio management** - Upload WAV/MP3, automatic conversion to Asterisk format
- **Professional menus** for customer self-service

### 📊 Reporting & Analytics
- **Comprehensive CDR** - Complete call history with disposition tracking
- **Advanced filtering** - By campaign, date range, status, phone number
- **Export capabilities** - CSV export for external analysis
- **Real-time statistics** - Answer rates, talk time, call volume
- **Visual dashboards** - Quick overview of system performance

### 🎯 Real-Time Monitoring
- **Live channel tracking** - See all active calls
- **Campaign progress** - Real-time updates every 3 seconds
- **System health** - Service status, resource utilization
- **Today's metrics** - Calls, answer rate, average duration

### 🔊 Call Recording
- **Dual-channel recording** - Record both agent and customer
- **Stereo mixing** - Combine channels into single file
- **Web playback** - Listen directly from browser
- **Download & archive** - Save recordings for compliance
- **Format options** - WAV or MP3

### 🌐 Multi-Language Support
- **English** - Full support
- **Russian** - Полная поддержка
- **Easy expansion** - Add new languages via language files
- **Per-user preference** - Language switcher in top-right corner

### 🛠️ Technical Features
- **RESTful API** - Programmatic access to all features
- **Responsive UI** - Bootstrap 4 mobile-friendly interface
- **Systemd integration** - Proper service management
- **Comprehensive logging** - Debug and audit trails
- **Security hardening** - Best practices implemented

## 📋 System Requirements

### Minimum Requirements
- **OS:** CentOS 7+, RHEL 7+, Ubuntu 18.04+, Debian 9+
- **CPU:** 2 cores
- **RAM:** 2 GB
- **Disk:** 10 GB free space
- **Asterisk:** 16+ with ARI enabled
- **PHP:** 7.2+ with extensions (mysqli, json, mbstring, xml, gd, curl)
- **Database:** MariaDB 10.2+ or MySQL 5.7+
- **Node.js:** 14 LTS or higher
- **Tools:** FFmpeg or SoX for audio conversion

### Recommended for Production
- **CPU:** 4+ cores
- **RAM:** 4+ GB
- **Disk:** 20+ GB SSD (for recordings)
- **Network:** Dedicated 100 Mbps+ connection

### Concurrent Call Capacity
- **5-10 calls** - Basic server
- **50-100 calls** - Recommended server (4 cores, 4GB RAM)
- **100-500 calls** - High-performance server (8+ cores, 16+ GB RAM)

## 🚀 Quick Start

### Automated Installation (Recommended)

```bash
cd /var/www/html/adial
sudo chmod +x install.sh
sudo ./install.sh
```

The installer will:
- ✅ Install all dependencies (Apache, PHP, MariaDB, Node.js)
- ✅ Configure database and create tables
- ✅ Set up Asterisk ARI
- ✅ Configure web server
- ✅ Create systemd service
- ✅ Set proper permissions
- ✅ Start all services

**Installation time:** 5-10 minutes

### Access After Installation

```
Web Interface: http://YOUR_SERVER_IP/adial
Language: EN/RU (top-right corner)
```

Credentials will be displayed at installation completion and saved to `.credentials` file.

## 📚 Documentation

| Document | Description |
|----------|-------------|
| **[INSTALL.md](INSTALL.md)** | Complete installation guide (automated + manual) |
| **[QUICKSTART.md](QUICKSTART.md)** | 5-minute quick start guide |
| **[USER_MANUAL.md](USER_MANUAL.md)** | Comprehensive user guide with examples |
| **[ADMIN_GUIDE.md](ADMIN_GUIDE.md)** | System administration and maintenance |
| **[FAQ.md](FAQ.md)** | Frequently asked questions |
| **[TROUBLESHOOTING.md](TROUBLESHOOTING.md)** | Common issues and solutions |
| **[FEATURES.md](FEATURES.md)** | Detailed feature descriptions |

## 🎓 Usage Examples

### Example 1: Simple Sales Campaign

```bash
# 1. Create campaign via web interface
Name: "November Sales"
Trunk: PJSIP/sales-trunk
Destination: PJSIP/100 (sales agent)
Concurrent Calls: 5

# 2. Upload phone numbers (CSV)
+12125551001
+12125551002
+12125551003

# 3. Start campaign
Click "Play" button → Monitor in real-time
```

### Example 2: Customer Survey with IVR

```bash
# 1. Create IVR menu
Name: "Satisfaction Survey"
Audio: "Rate your experience: Press 1-5"
Actions:
  1 → Playback "thank-you"
  2 → Playback "thank-you"
  ...

# 2. Create campaign
Name: "Monthly Survey"
Destination: IVR Menu → "Satisfaction Survey"

# 3. Upload customers
# 4. Start campaign
```

### Example 3: Appointment Reminders

```bash
# IVR: "You have an appointment tomorrow. Press 1 to confirm."
# Campaign connects to IVR
# Automated calls 24 hours before appointment
```

See **[USER_MANUAL.md](USER_MANUAL.md)** for more examples!

## 💻 Manual Installation

For manual installation or custom setups, see **[INSTALL.md](INSTALL.md)** for step-by-step instructions covering:

- Dependency installation per OS
- Database setup
- Asterisk configuration

### 2. Asterisk Configuration

Create ARI user in `/etc/asterisk/ari.conf`:

```ini
[dialer]
type=user
password=76e6d233237c5323b9bb71860e322b61
read_only=no
```

Reload Asterisk:

```bash
asterisk -rx "module reload res_ari.so"
```

### 3. Start the Stasis Application

```bash
cd /var/www/html/adial/stasis-app
npm install  # Already done
node app.js
```

Or use PM2 for production:

```bash
npm install -g pm2
pm2 start app.js --name "ari-dialer"
pm2 save
pm2 startup
```

### 4. Configure Web Server

For Apache, the `.htaccess` file is already configured. Ensure `mod_rewrite` is enabled:

```bash
a2enmod rewrite
systemctl restart httpd
```

### 5. Set Permissions

```bash
chmod -R 777 /var/www/html/adial/uploads
chmod -R 777 /var/www/html/adial/logs
chmod -R 777 /var/www/html/adial/recordings
chmod -R 777 /var/lib/asterisk/sounds/dialer
```

## Usage

### Accessing the Web Interface

Open your browser and navigate to:
```
http://your-server-ip/adial
```

### Creating a Campaign

1. Go to **Campaigns** → **New Campaign**
2. Fill in campaign details:
   - **Name**: Campaign identifier
   - **Description**: Optional description
   - **Trunk Configuration**:
     - Custom: `Local/${EXTEN}@from-internal`
     - PJSIP: Select from available trunks
     - SIP: Select from available trunks
   - **Caller ID**: Outbound caller ID
   - **Agent Destination**:
     - Custom: `PJSIP/100` or `Local/100@from-internal`
     - Extension: Select from endpoints
     - IVR: Configure IVR menu separately
   - **Recording**: Enable to record both channels
   - **Concurrent Calls**: Max simultaneous calls
   - **Retry Settings**: Configure retry attempts and delay

3. Click **Create Campaign**

### Adding Numbers to Campaign

1. View the campaign details
2. Upload CSV file with phone numbers (one per line)
3. Or add numbers manually

### Starting a Campaign

1. Go to **Campaigns**
2. Click the **Play** button to start the campaign
3. Monitor progress in real-time on the **Monitoring** page

### Creating IVR Menus

1. Go to **IVR** → **New IVR Menu**
2. Select campaign
3. Upload audio file (WAV or MP3) - will be auto-converted
4. Configure DTMF actions:
   - **Press 1**: Call Extension (PJSIP/100)
   - **Press 2**: Add to Queue (sales)
   - **Press 3**: Hangup
   - **Press 0**: Playback message

### Viewing Call Records

1. Go to **CDR**
2. Filter by campaign, date, or disposition
3. Play or download recordings
4. Export to CSV

### Real-time Monitoring

1. Go to **Monitoring**
2. View:
   - Today's call statistics
   - Active campaigns with progress
   - Active channels
   - Answer rates and average talk time

## Configuration

### ARI Settings

Edit `/var/www/html/adial/application/config/ari.php`:

```php
$config['ari_host'] = 'localhost';
$config['ari_port'] = '8088';
$config['ari_username'] = 'dialer';
$config['ari_password'] = '76e6d233237c5323b9bb71860e322b61';
$config['ari_stasis_app'] = 'dialer';
```

### Stasis App Settings

Edit `/var/www/html/adial/stasis-app/.env`:

```env
ARI_HOST=localhost
ARI_PORT=8088
ARI_USERNAME=asterisk
ARI_PASSWORD=asterisk
ARI_APP_NAME=dialer

DB_HOST=localhost
DB_USER=root
DB_PASSWORD=mahapharata
DB_NAME=adialer

DEBUG_MODE=true
```

## Directory Structure

```
/var/www/html/adial/
├── application/          # CodeIgniter application
│   ├── controllers/      # Web controllers
│   ├── models/          # Database models
│   ├── views/           # HTML views
│   ├── libraries/       # ARI client library
│   └── config/          # Configuration files
├── stasis-app/          # Node.js Stasis application
│   ├── app.js           # Main application
│   ├── package.json     # Node dependencies
│   └── .env             # Environment configuration
├── recordings/          # Call recordings (MP3)
├── uploads/             # Temporary file uploads
├── logs/                # Application logs
└── public/              # Public assets

/var/lib/asterisk/sounds/dialer/  # IVR audio files
```

## API Endpoints

The system provides AJAX endpoints for real-time updates:

- `GET /dashboard/get_status` - System status
- `GET /dashboard/get_channels` - Active channels
- `POST /campaigns/control/{id}/{action}` - Control campaigns
- `GET /monitoring/get_realtime_data` - Real-time monitoring data
- `GET /cdr/stats` - CDR statistics

## Troubleshooting

### Stasis App Not Connecting

1. Check Asterisk ARI configuration:
   ```bash
   asterisk -rx "ari show users"
   ```

2. Verify ARI is listening:
   ```bash
   netstat -tulpn | grep 8088
   ```

3. Check Stasis app logs:
   ```bash
   tail -f /var/www/html/adial/logs/stasis-combined.log
   ```

### Calls Not Originating

1. Check trunk configuration in campaign
2. Verify endpoint is registered:
   ```bash
   asterisk -rx "pjsip show endpoints"
   ```
3. Check ARI logs in database:
   ```sql
   SELECT * FROM ari_logs ORDER BY created_at DESC LIMIT 10;
   ```

### Recordings Not Working

1. Verify recording path permissions:
   ```bash
   ls -la /var/www/html/adial/recordings
   ```

2. Check if sox/ffmpeg is installed:
   ```bash
   which sox
   which ffmpeg
   ```

3. Check Stasis app logs for recording errors

### IVR Audio Not Playing

1. Verify audio file format:
   ```bash
   file /var/lib/asterisk/sounds/dialer/*.wav
   ```

2. Should be: `RIFF (little-endian) data, WAVE audio, 8000 Hz, mono`

3. Manually convert if needed:
   ```bash
   sox input.wav -r 8000 -c 1 output.wav
   ```

## Security Notes

- Change default database password
- Update ARI credentials
- Restrict web access with authentication
- Use HTTPS in production
- Set proper file permissions
- Enable firewall rules

## Support

For issues and questions:
- Check logs in `/var/www/html/adial/logs/`
- Review ARI logs in database
- Check Asterisk logs: `/var/log/asterisk/full`

## 📁 Project Structure

```
/var/www/html/adial/
├── application/              # CodeIgniter application
│   ├── controllers/         # Campaign, CDR, IVR controllers
│   ├── models/              # Database models
│   ├── views/               # UI templates
│   ├── language/            # EN/RU translations
│   └── config/              # Configuration files
├── stasis-app/              # Node.js Stasis application
│   ├── app.js               # Main ARI handler
│   ├── .env                 # Environment config
│   └── package.json         # Dependencies
├── database_schema.sql      # Database structure
├── install.sh               # Automated installer
├── start-dialer.sh          # Startup script
└── Documentation...         # Guides and manuals

/var/lib/asterisk/sounds/dialer/     # IVR audio files
/var/spool/asterisk/monitor/         # Call recordings
```

## 🔧 Configuration

### Quick Configuration

After installation, edit:

**Database:**
```bash
nano application/config/database.php
```

**ARI Connection:**
```bash
nano application/config/ari.php
```

**Stasis App:**
```bash
nano stasis-app/.env
```

See **[ADMIN_GUIDE.md](ADMIN_GUIDE.md)** for advanced configuration.

## 🛡️ Security

**Important:** Change default credentials immediately after installation!

```bash
# Database password
mysql -u root -p
ALTER USER 'adialer_user'@'localhost' IDENTIFIED BY 'NEW_PASSWORD';

# Update configs
nano application/config/database.php
nano stasis-app/.env

# Restart services
systemctl restart ari-dialer
```

**Additional security measures:**
- Enable HTTPS (Let's Encrypt)
- Restrict access by IP
- Use strong passwords
- Enable firewall
- Regular security updates

See **[ADMIN_GUIDE.md](ADMIN_GUIDE.md)** for security hardening.

## 📊 System Monitoring

### Service Status

```bash
# Check all services
sudo /var/www/html/adial/start-dialer.sh

# Individual services
systemctl status ari-dialer
systemctl status asterisk
systemctl status httpd  # or apache2
systemctl status mariadb
```

### View Logs

```bash
# Stasis app logs
journalctl -u ari-dialer -f

# Asterisk logs
tail -f /var/log/asterisk/full

# Web server logs
tail -f /var/log/httpd/error_log  # CentOS
tail -f /var/log/apache2/error.log  # Ubuntu
```

## 🔄 Backup and Recovery

### Quick Backup

```bash
# Database
mysqldump -u root -p adialer > backup_$(date +%Y%m%d).sql

# Application files
tar -czf adial_backup.tar.gz /var/www/html/adial

# Recordings
rsync -av /var/spool/asterisk/monitor/ /backup/recordings/
```

See **[ADMIN_GUIDE.md](ADMIN_GUIDE.md)** for automated backup setup.

## 🆘 Troubleshooting

### Common Issues

**Services not starting?**
```bash
systemctl status ari-dialer
journalctl -u ari-dialer -n 50
```

**Calls not connecting?**
```bash
asterisk -rx "pjsip show endpoints"
asterisk -rx "ari show users"
```

**Web interface not loading?**
```bash
systemctl status httpd
tail -f /var/log/httpd/error_log
```

See **[FAQ.md](FAQ.md)** and **[TROUBLESHOOTING.md](TROUBLESHOOTING.md)** for more solutions.

## 🤝 Support

**Resources:**
- 📖 **[User Manual](USER_MANUAL.md)** - How to use the web interface
- 🔧 **[Admin Guide](ADMIN_GUIDE.md)** - System administration
- ❓ **[FAQ](FAQ.md)** - Frequently asked questions
- 🔍 **[Troubleshooting](TROUBLESHOOTING.md)** - Problem solving

**For technical issues:**
1. Check documentation above
2. Review log files
3. Search GitHub issues
4. Consult Asterisk ARI documentation

## 🌟 Screenshots

Access the web interface to see:
- Modern, responsive dashboard
- Real-time monitoring charts
- Intuitive campaign management
- Professional IVR configuration
- Comprehensive CDR reports

## 📝 License

Proprietary - All rights reserved

---

**Version:** 1.0.0
**Last Updated:** 2024-11-14
**Compatibility:** Asterisk 16+, PHP 7.2+, Node.js 14+

---

Made with ❤️ using Asterisk ARI, CodeIgniter, Node.js, and Bootstrap
