# ARI Dialer Installation on FreePBX/Sangoma Linux

This guide covers the installation of ARI Dialer on FreePBX systems (Sangoma Linux).

## Important Notes for FreePBX

### Configuration Files

**DO NOT** directly modify FreePBX-managed configuration files in `/etc/asterisk/`. FreePBX manages these files and will overwrite your changes.

Instead, use these FreePBX-safe methods:

1. **ARI Configuration**: Use `ari_additional_custom.conf` instead of `ari.conf`
2. **Extensions**: Use `extensions_custom.conf` instead of `extensions.conf`
3. **Other configs**: Use `*_custom.conf` or `*_additional_custom.conf` files for any Asterisk configuration

### Automated Installation

The `install.sh` script now detects FreePBX systems and:
- Uses `ari_additional_custom.conf` for ARI user configuration
- Avoids overwriting FreePBX Apache configurations
- Installs alongside FreePBX without conflicts
- Reloads Asterisk modules instead of full restart

Run the installation:

```bash
cd /var/www/html/adial
chmod +x install.sh
./install.sh
```

## Manual ARI Configuration (if needed)

If you need to manually configure ARI on FreePBX:

### Step 1: Enable ARI via FreePBX GUI

1. Log in to FreePBX web interface: `http://your-server/admin`
2. Navigate to: **Settings** → **Asterisk REST Interface (ARI)**
3. Enable ARI if not already enabled
4. Note the default credentials or create new ones

### Step 2: Create ARI User in ari_additional_custom.conf

Create or edit `/etc/asterisk/ari_additional_custom.conf`:

```ini
; ARI Dialer User Configuration
[dialer]
type = user
read_only = no
password = YOUR_SECURE_PASSWORD
```

### Step 3: Verify ARI is Enabled

FreePBX automatically includes `ari_additional_custom.conf` if it exists.

Ensure the [general] section in `/etc/asterisk/ari.conf` has:

```ini
[general]
enabled = yes
pretty = yes
allowed_origins = *
```

### Step 4: Reload ARI Module

```bash
asterisk -rx "module reload res_ari.so"
```

## Verifying Installation

### Check ARI is Running

```bash
# Test ARI endpoint
curl -u dialer:YOUR_PASSWORD http://localhost:8088/ari/asterisk/info

# Check Asterisk CLI
asterisk -rx "ari show users"
```

### Check Services

```bash
systemctl status ari-dialer
systemctl status asterisk
systemctl status httpd
systemctl status mariadb
```

## Web Interface Access

- **ARI Dialer**: http://your-server/adial
- **FreePBX GUI**: http://your-server/admin

Default ARI Dialer credentials:
- Username: `admin`
- Password: `admin` (⚠️ Change immediately!)

## Troubleshooting

### ARI Connection Failed

1. Verify ARI is enabled in FreePBX GUI
2. Check `/etc/asterisk/ari_additional_custom.conf` exists and has correct credentials
3. Verify ARI is enabled in `/etc/asterisk/ari.conf`
4. Check ARI credentials in `/var/www/html/adial/application/config/ari.php`
5. Check Stasis app credentials in `/var/www/html/adial/stasis-app/.env`

```bash
# Test ARI connectivity
curl -u dialer:password http://localhost:8088/ari/asterisk/info

# Check ARI users
asterisk -rx "ari show users"

# Check ARI module
asterisk -rx "module show like res_ari"
```

### Stasis App Not Connecting

```bash
# Check logs
journalctl -u ari-dialer -n 50

# Check service status
systemctl status ari-dialer

# Restart service
systemctl restart ari-dialer
```

### Permission Issues

```bash
# Fix ownership
chown -R apache:apache /var/www/html/adial
chown -R asterisk:asterisk /var/lib/asterisk/sounds/dialer
chown -R asterisk:asterisk /var/spool/asterisk/monitor

# Fix permissions
chmod -R 755 /var/www/html/adial
chmod -R 777 /var/www/html/adial/logs
chmod -R 777 /var/www/html/adial/recordings
chmod -R 777 /var/www/html/adial/uploads
```

### Database Connection Issues

Check database credentials in:
- `/var/www/html/adial/application/config/database.php`
- `/var/www/html/adial/stasis-app/.env`

Test database connection:
```bash
mysql -u adialer_user -p adialer
```

## Configuration File Locations

| Purpose | File Location |
|---------|---------------|
| ARI User | `/etc/asterisk/ari_additional_custom.conf` |
| Database Config | `/var/www/html/adial/application/config/database.php` |
| ARI Connection | `/var/www/html/adial/application/config/ari.php` |
| Stasis App Env | `/var/www/html/adial/stasis-app/.env` |
| Service File | `/etc/systemd/system/ari-dialer.service` |
| Logs | `/var/www/html/adial/logs/` |
| Recordings | `/var/spool/asterisk/monitor/` |
| IVR Sounds | `/var/lib/asterisk/sounds/dialer/` |

## Updating Configuration

When updating ARI credentials:

1. Update `/etc/asterisk/ari_additional_custom.conf`
2. Update `/var/www/html/adial/application/config/ari.php`
3. Update `/var/www/html/adial/stasis-app/.env`
4. Reload Asterisk: `asterisk -rx "module reload res_ari.so"`
5. Restart Stasis app: `systemctl restart ari-dialer`

## FreePBX Integration Best Practices

1. **Never edit FreePBX-managed files directly** - Use `*_custom.conf` or `*_additional_custom.conf` files
2. **Use FreePBX GUI when possible** - For trunks, extensions, routes, etc.
3. **Keep ARI Dialer separate** - Don't mix ARI Dialer extensions with FreePBX extensions
4. **Regular backups** - Backup both FreePBX and ARI Dialer databases
5. **Monitor logs** - Check both Asterisk and Stasis app logs

## Uninstallation

To remove ARI Dialer from FreePBX:

```bash
# Stop and disable service
systemctl stop ari-dialer
systemctl disable ari-dialer
rm /etc/systemd/system/ari-dialer.service
systemctl daemon-reload

# Remove files
rm -rf /var/www/html/adial
rm /etc/httpd/conf.d/adial-allowoverride.conf

# Remove database
mysql -e "DROP DATABASE adialer;"
mysql -e "DROP USER 'adialer_user'@'localhost';"

# Remove ARI user (optional - only if not needed)
# Edit /etc/asterisk/ari_additional_custom.conf and remove the [dialer] section
# Or delete the entire file if no other ARI users exist
# rm /etc/asterisk/ari_additional_custom.conf
asterisk -rx "module reload res_ari.so"

# Restart Apache
systemctl restart httpd
```

## Support

For issues specific to:
- **FreePBX**: Visit FreePBX forums or support
- **ARI Dialer**: Check project documentation and logs
- **Integration**: Review this guide and check both system logs
