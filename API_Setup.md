# ARI Dialer API Setup Guide

## Quick Setup

### Step 1: Install Database Schema

Run the SQL migration to add API token support:

```bash
mysql -u root -p adialer < /var/www/html/adial/database_api_tokens.sql
```

Or manually execute the SQL:

```sql
-- API Tokens table
DROP TABLE IF EXISTS `api_tokens`;
CREATE TABLE `api_tokens` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `token` varchar(64) NOT NULL,
  `name` varchar(100) DEFAULT NULL COMMENT 'Token description/name',
  `permissions` text COMMENT 'JSON array of permitted endpoints',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `last_used` datetime DEFAULT NULL,
  `expires_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `token` (`token`),
  KEY `user_id` (`user_id`),
  KEY `is_active` (`is_active`),
  CONSTRAINT `api_tokens_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Add API access field to users table
ALTER TABLE `users` ADD COLUMN `api_access` tinyint(1) NOT NULL DEFAULT '1' AFTER `is_active`;
```

### Step 2: Generate Your First API Token

You have two options:

#### Option A: Via Database (Quick Start)

```bash
# Generate a token
TOKEN=$(openssl rand -hex 32)
USER_ID=1  # Your user ID

# Insert into database
mysql -u root -p adialer -e "
INSERT INTO api_tokens (user_id, token, name, is_active, created_at)
VALUES ($USER_ID, '$TOKEN', 'My First Token', 1, NOW());
"

# Display token
echo "Your API Token: $TOKEN"
echo "Save this token securely!"
```

#### Option B: Via Web Interface (Recommended)

1. Log in to ARI Dialer web interface
2. Navigate to **Settings** → **API Tokens** (feature to be added to UI)
3. Click "Generate New Token"
4. Copy and save the token securely

#### Option C: Via API (Bootstrap Token)

Create a bootstrap token manually, then use it to create more tokens via API:

```bash
# Create bootstrap token
TOKEN=$(openssl rand -hex 32)
mysql -u root -p adialer -e "
INSERT INTO api_tokens (user_id, token, name, is_active, created_at)
VALUES (1, '$TOKEN', 'Bootstrap Token', 1, NOW());
"

# Use it to create more tokens via API
curl -X POST \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"name": "Production Token"}' \
  http://your-server/adial/api/tokens
```

### Step 3: Test the API

```bash
# Set your token
export API_TOKEN="your_token_here"
export API_URL="http://your-server/adial/api"

# Test connection
curl -H "Authorization: Bearer $API_TOKEN" $API_URL

# List campaigns
curl -H "Authorization: Bearer $API_TOKEN" $API_URL/campaigns

# Get system status
curl -H "Authorization: Bearer $API_TOKEN" $API_URL/monitoring/status
```

## Configuration

### Enable/Disable API Access for Users

```sql
-- Disable API access for a user
UPDATE users SET api_access = 0 WHERE id = 2;

-- Enable API access for a user
UPDATE users SET api_access = 1 WHERE id = 2;
```

### Token Permissions

When creating tokens, you can restrict access to specific endpoints:

```bash
curl -X POST \
  -H "Authorization: Bearer $ADMIN_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Read-only Token",
    "permissions": [
      "campaigns/list",
      "campaigns/view",
      "cdr/list",
      "cdr/view",
      "monitoring/*"
    ]
  }' \
  http://your-server/adial/api/tokens
```

**Permission Patterns:**
- `campaigns/*` - All campaign endpoints
- `campaigns/list` - Only list campaigns
- `campaigns/view` - Only view campaign details
- `*` - Full access (if no permissions specified)

### Token Expiration

Set expiration date when creating tokens:

```bash
curl -X POST \
  -H "Authorization: Bearer $ADMIN_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Temporary Token",
    "expires_at": "2025-12-31 23:59:59"
  }' \
  http://your-server/adial/api/tokens
```

## Security Best Practices

1. **Use HTTPS in Production**
   - API tokens are sent in headers
   - Always use HTTPS to encrypt traffic

2. **Rotate Tokens Regularly**
   - Create new tokens periodically
   - Revoke old tokens

3. **Limit Token Permissions**
   - Use specific permissions instead of wildcard
   - Create separate tokens for different purposes

4. **Monitor Token Usage**
   ```sql
   SELECT * FROM api_tokens ORDER BY last_used DESC;
   ```

5. **Set Expiration Dates**
   - Use short-lived tokens when possible
   - Implement token refresh mechanism if needed

6. **Revoke Compromised Tokens Immediately**
   ```sql
   UPDATE api_tokens SET is_active = 0 WHERE token = 'compromised_token';
   ```

## Troubleshooting

### 401 Unauthorized Error

**Possible causes:**
1. Invalid token
2. Expired token
3. User is inactive
4. User has no API access

**Check:**
```sql
SELECT
  t.*,
  u.is_active as user_active,
  u.api_access
FROM api_tokens t
JOIN users u ON u.id = t.user_id
WHERE t.token = 'your_token_here';
```

### 403 Forbidden Error

**Possible causes:**
1. Token lacks permission for endpoint
2. Admin-only endpoint accessed by regular user

**Check token permissions:**
```sql
SELECT permissions FROM api_tokens WHERE token = 'your_token_here';
```

### 500 Internal Server Error

**Check logs:**
```bash
# PHP errors
tail -f /var/www/html/adial/logs/log-*.php

# Apache errors
tail -f /var/log/httpd/error_log
```

## API Rate Limiting (Future Enhancement)

To add rate limiting, create a tracking table:

```sql
CREATE TABLE api_rate_limits (
  id INT AUTO_INCREMENT PRIMARY KEY,
  token_id INT NOT NULL,
  endpoint VARCHAR(255),
  request_count INT DEFAULT 0,
  window_start DATETIME,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY (token_id),
  KEY (window_start)
);
```

## Backup and Migration

### Backup API Tokens

```bash
mysqldump -u root -p adialer api_tokens > api_tokens_backup.sql
```

### Migrate to New Server

```bash
# On old server
mysqldump -u root -p adialer api_tokens > api_tokens.sql

# On new server
mysql -u root -p adialer < api_tokens.sql
```

## Example Scripts

### Bulk Token Creation

```bash
#!/bin/bash
# create-api-tokens.sh

DB_USER="root"
DB_PASS="password"
DB_NAME="adialer"

# Create tokens for all active users
mysql -u $DB_USER -p$DB_PASS $DB_NAME <<EOF
INSERT INTO api_tokens (user_id, token, name, is_active, created_at)
SELECT
  id as user_id,
  SHA2(CONCAT(username, UUID(), NOW()), 256) as token,
  CONCAT(username, ' API Token') as name,
  1 as is_active,
  NOW() as created_at
FROM users
WHERE is_active = 1
AND id NOT IN (SELECT user_id FROM api_tokens WHERE is_active = 1);
EOF

echo "API tokens created!"
```

### Token Cleanup

```bash
#!/bin/bash
# cleanup-expired-tokens.sh

DB_USER="root"
DB_PASS="password"
DB_NAME="adialer"

# Delete expired tokens
mysql -u $DB_USER -p$DB_PASS $DB_NAME <<EOF
DELETE FROM api_tokens
WHERE expires_at < NOW()
AND expires_at IS NOT NULL;
EOF

echo "Expired tokens cleaned up!"
```

### Monitor API Usage

```bash
#!/bin/bash
# monitor-api-usage.sh

DB_USER="root"
DB_PASS="password"
DB_NAME="adialer"

# Show token usage statistics
mysql -u $DB_USER -p$DB_PASS $DB_NAME <<EOF
SELECT
  u.username,
  t.name as token_name,
  t.last_used,
  DATEDIFF(NOW(), t.last_used) as days_since_use,
  t.is_active
FROM api_tokens t
JOIN users u ON u.id = t.user_id
ORDER BY t.last_used DESC;
EOF
```

## Integration Examples

### Shell Script Integration

```bash
#!/bin/bash
# campaign-manager.sh

API_TOKEN="your_token_here"
API_URL="http://your-server/adial/api"

# Function to call API
api_call() {
  local method=$1
  local endpoint=$2
  local data=$3

  if [ -z "$data" ]; then
    curl -s -X $method \
      -H "Authorization: Bearer $API_TOKEN" \
      "$API_URL$endpoint"
  else
    curl -s -X $method \
      -H "Authorization: Bearer $API_TOKEN" \
      -H "Content-Type: application/json" \
      -d "$data" \
      "$API_URL$endpoint"
  fi
}

# Start campaign
api_call POST "/campaigns/1/start"

# Check status
api_call GET "/campaigns/1/stats"
```

### Cron Job Example

```bash
# Add to crontab: crontab -e

# Check campaign status every 5 minutes
*/5 * * * * /usr/local/bin/check-campaigns.sh >> /var/log/campaign-monitor.log 2>&1
```

```bash
#!/usr/bin/env bash
# /usr/local/bin/check-campaigns.sh

API_TOKEN="your_token_here"
API_URL="http://localhost/adial/api"

# Get real-time data
DATA=$(curl -s -H "Authorization: Bearer $API_TOKEN" "$API_URL/monitoring/realtime")

# Parse and log
echo "$(date): $DATA"

# Alert if no active campaigns
ACTIVE=$(echo $DATA | jq -r '.data.campaigns | length')
if [ "$ACTIVE" -eq 0 ]; then
  echo "ALERT: No active campaigns!" | mail -s "Campaign Alert" admin@example.com
fi
```

## Next Steps

1. Read the full [API Documentation](API_DOCUMENTATION.md)
2. Import the Postman collection for testing
3. Implement your integration
4. Set up monitoring and alerting
5. Configure HTTPS for production use

## Support

For issues or questions:
- Check logs: `/var/www/html/adial/logs/`
- Review documentation: `API_DOCUMENTATION.md`
- Test with Postman collection
