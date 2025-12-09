# ARI Dialer REST API

Complete RESTful API for ARI Dialer campaign management system. Control campaigns, manage numbers, monitor calls, and access CDR data programmatically.

## Quick Start

### 1. Install API Support

```bash
# Install database schema for API tokens
mysql -u root -p adialer < database_api_tokens.sql
```

### 2. Generate API Token

```bash
# Quick method - generate token via command line
TOKEN=$(openssl rand -hex 32)
mysql -u root -p adialer -e "
INSERT INTO api_tokens (user_id, token, name, is_active, created_at)
VALUES (1, '$TOKEN', 'My API Token', 1, NOW());
"
echo "Your API Token: $TOKEN"
```

### 3. Test API

```bash
# Test connection
curl -H "Authorization: Bearer YOUR_TOKEN" \
  http://your-server/adial/api

# List campaigns
curl -H "Authorization: Bearer YOUR_TOKEN" \
  http://your-server/adial/api/campaigns
```

## API Features

### Campaign Management
- ✅ List, create, update, delete campaigns
- ✅ Start, stop, pause, resume campaigns
- ✅ Real-time campaign statistics
- ✅ Concurrent call control

### Numbers Management
- ✅ Add single or bulk numbers
- ✅ Upload with custom data (name, fields)
- ✅ Track number status and attempts
- ✅ Paginated number lists

### Call Detail Records (CDR)
- ✅ Query CDR with filters
- ✅ Get CDR statistics
- ✅ Filter by campaign, disposition, date range
- ✅ Paginated results

### Real-time Monitoring
- ✅ System status (Asterisk, Database)
- ✅ Active channels and calls
- ✅ Live campaign metrics
- ✅ Today's call statistics

### IVR Management
- ✅ List IVR menus
- ✅ Get IVR with actions
- ✅ Delete IVR menus

### User Management (Admin)
- ✅ List, create, update, delete users
- ✅ Role-based access control
- ✅ API access control per user

### Security Features
- ✅ Token-based authentication (Bearer tokens)
- ✅ Role-based authorization (Admin/User)
- ✅ Per-token permissions
- ✅ Token expiration support
- ✅ CORS support

## Available Endpoints

### Campaigns
```
GET    /api/campaigns              - List all campaigns
GET    /api/campaigns/:id          - Get campaign details
POST   /api/campaigns              - Create campaign
PUT    /api/campaigns/:id          - Update campaign
DELETE /api/campaigns/:id          - Delete campaign
POST   /api/campaigns/:id/start    - Start campaign
POST   /api/campaigns/:id/stop     - Stop campaign
POST   /api/campaigns/:id/pause    - Pause campaign
POST   /api/campaigns/:id/resume   - Resume campaign
GET    /api/campaigns/:id/stats    - Get statistics
```

### Numbers
```
GET    /api/campaigns/:id/numbers       - List campaign numbers
POST   /api/campaigns/:id/numbers       - Add single number
POST   /api/campaigns/:id/numbers/bulk  - Bulk add numbers
GET    /api/numbers/:id                 - Get number details
DELETE /api/numbers/:id                 - Delete number
```

### CDR
```
GET    /api/cdr        - List CDR records
GET    /api/cdr/:id    - Get CDR details
GET    /api/cdr/stats  - Get CDR statistics
```

### Monitoring
```
GET    /api/monitoring/status    - System status
GET    /api/monitoring/channels  - Active channels
GET    /api/monitoring/realtime  - Real-time data
```

### IVR
```
GET    /api/ivr       - List IVR menus
GET    /api/ivr/:id   - Get IVR menu with actions
DELETE /api/ivr/:id   - Delete IVR menu
```

### Users (Admin Only)
```
GET    /api/users       - List users
POST   /api/users       - Create user
PUT    /api/users/:id   - Update user
DELETE /api/users/:id   - Delete user
```

### API Tokens
```
GET    /api/tokens       - List your tokens
POST   /api/tokens       - Create new token
DELETE /api/tokens/:id   - Revoke token
```

## Documentation

- **[API Documentation](API_DOCUMENTATION.md)** - Complete API reference with examples
- **[Setup Guide](API_Setup.md)** - Installation and configuration
- **[Postman Collection](ARI_Dialer_API.postman_collection.json)** - Import into Postman for testing

## Example Usage

### Start a Campaign with Numbers

```bash
#!/bin/bash
API_TOKEN="your_token"
BASE_URL="http://your-server/adial/api"

# Create campaign
CAMPAIGN=$(curl -s -X POST \
  -H "Authorization: Bearer $API_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Sales Campaign",
    "trunk_type": "pjsip",
    "trunk_value": "my_trunk",
    "callerid": "1234567890",
    "agent_dest_type": "exten",
    "agent_dest_value": "100",
    "concurrent_calls": 5,
    "record_calls": 1
  }' \
  "$BASE_URL/campaigns")

CAMPAIGN_ID=$(echo $CAMPAIGN | jq -r '.data.id')
echo "Created campaign: $CAMPAIGN_ID"

# Add numbers
curl -X POST \
  -H "Authorization: Bearer $API_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "numbers": [
      {"phone_number": "5551111111", "data": {"name": "John"}},
      {"phone_number": "5552222222", "data": {"name": "Jane"}},
      {"phone_number": "5553333333", "data": {"name": "Bob"}}
    ]
  }' \
  "$BASE_URL/campaigns/$CAMPAIGN_ID/numbers/bulk"

# Start campaign
curl -X POST \
  -H "Authorization: Bearer $API_TOKEN" \
  "$BASE_URL/campaigns/$CAMPAIGN_ID/start"

echo "Campaign started!"
```

### Monitor Campaign Progress

```bash
#!/bin/bash
API_TOKEN="your_token"
BASE_URL="http://your-server/adial/api"
CAMPAIGN_ID=1

# Get campaign stats
STATS=$(curl -s -H "Authorization: Bearer $API_TOKEN" \
  "$BASE_URL/campaigns/$CAMPAIGN_ID/stats")

echo "Campaign Statistics:"
echo $STATS | jq '{
  total: .data.total,
  pending: .data.pending,
  calling: .data.calling,
  answered: .data.answered,
  completed: .data.completed,
  failed: .data.failed
}'
```

### Python Integration

```python
import requests

class ARIDialer:
    def __init__(self, base_url, token):
        self.base_url = base_url
        self.headers = {'Authorization': f'Bearer {token}'}

    def create_campaign(self, **kwargs):
        response = requests.post(
            f'{self.base_url}/campaigns',
            json=kwargs,
            headers=self.headers
        )
        return response.json()

    def add_numbers(self, campaign_id, numbers):
        response = requests.post(
            f'{self.base_url}/campaigns/{campaign_id}/numbers/bulk',
            json={'numbers': numbers},
            headers=self.headers
        )
        return response.json()

    def start_campaign(self, campaign_id):
        response = requests.post(
            f'{self.base_url}/campaigns/{campaign_id}/start',
            headers=self.headers
        )
        return response.json()

# Usage
dialer = ARIDialer('http://your-server/adial/api', 'your_token')

# Create campaign
campaign = dialer.create_campaign(
    name='Python Campaign',
    trunk_type='pjsip',
    trunk_value='trunk1',
    agent_dest_type='exten',
    agent_dest_value='100',
    concurrent_calls=3
)

# Add numbers
dialer.add_numbers(campaign['data']['id'], [
    {'phone_number': '5551234567'},
    {'phone_number': '5559876543'}
])

# Start
dialer.start_campaign(campaign['data']['id'])
```

## Authentication

All requests require a Bearer token in the Authorization header:

```bash
Authorization: Bearer your_api_token_here
```

### Token Permissions

Control what each token can access:

```json
{
  "permissions": [
    "campaigns/*",           // All campaign endpoints
    "campaigns/list",        // Only list campaigns
    "campaigns/view",        // Only view details
    "cdr/*",                 // All CDR endpoints
    "monitoring/*"           // All monitoring endpoints
  ]
}
```

### Token Expiration

Set expiration when creating tokens:

```json
{
  "name": "Temporary Token",
  "expires_at": "2025-12-31 23:59:59"
}
```

## Response Format

### Success Response
```json
{
  "success": true,
  "code": 200,
  "message": "Operation successful",
  "data": { ... }
}
```

### Error Response
```json
{
  "success": false,
  "code": 400,
  "message": "Error description",
  "errors": { ... }
}
```

### Paginated Response
```json
{
  "success": true,
  "data": [ ... ],
  "pagination": {
    "total": 100,
    "per_page": 20,
    "current_page": 1,
    "total_pages": 5,
    "from": 1,
    "to": 20
  }
}
```

## HTTP Status Codes

| Code | Description |
|------|-------------|
| 200 | Success |
| 201 | Resource created |
| 400 | Bad request |
| 401 | Unauthorized |
| 403 | Forbidden |
| 404 | Not found |
| 500 | Server error |

## Testing with Postman

1. Import the Postman collection:
   - Open Postman
   - Click "Import"
   - Select `ARI_Dialer_API.postman_collection.json`

2. Configure variables:
   - Set `base_url` to your server URL
   - Set `api_token` to your API token

3. Start testing!

## Testing with curl

```bash
# Set variables
export API_TOKEN="your_token_here"
export API_URL="http://your-server/adial/api"

# Test API
curl -H "Authorization: Bearer $API_TOKEN" $API_URL

# List campaigns
curl -H "Authorization: Bearer $API_TOKEN" $API_URL/campaigns

# Get system status
curl -H "Authorization: Bearer $API_TOKEN" $API_URL/monitoring/status

# Create campaign
curl -X POST \
  -H "Authorization: Bearer $API_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Test Campaign",
    "trunk_type": "pjsip",
    "trunk_value": "trunk1",
    "agent_dest_type": "exten",
    "agent_dest_value": "100"
  }' \
  $API_URL/campaigns
```

## Use Cases

### 1. Automated Campaign Management
- Schedule campaigns via cron jobs
- Auto-start/stop based on business hours
- Dynamic number addition from external systems

### 2. Integration with CRM Systems
- Import contacts from Salesforce, HubSpot, etc.
- Update CRM with call results (CDR data)
- Sync campaign statuses

### 3. Real-time Monitoring Dashboard
- Build custom dashboards
- Display live call metrics
- Alert on campaign issues

### 4. Reporting & Analytics
- Extract CDR data for analysis
- Generate custom reports
- Track performance metrics

### 5. Multi-tenant Applications
- Manage multiple client campaigns
- Separate permissions per client
- White-label dialer solutions

## Security Best Practices

1. **Use HTTPS in production**
2. **Rotate tokens regularly**
3. **Limit token permissions**
4. **Set token expiration dates**
5. **Monitor token usage**
6. **Revoke compromised tokens immediately**

## Troubleshooting

### 401 Unauthorized
- Invalid token
- Expired token
- User inactive or no API access

### 403 Forbidden
- Insufficient permissions
- Admin-only endpoint

### 404 Not Found
- Invalid endpoint
- Resource doesn't exist

### 500 Internal Server Error
- Check application logs: `/var/www/html/adial/logs/`
- Check Apache logs: `/var/log/httpd/error_log`

## Support

- **Documentation:** [API_DOCUMENTATION.md](API_DOCUMENTATION.md)
- **Setup Guide:** [API_Setup.md](API_Setup.md)
- **Logs:** `/var/www/html/adial/logs/`
- **Stasis App:** `journalctl -u ari-dialer -f`

## Version

**API Version:** 1.0
**Last Updated:** 2024-12-09

## License

Part of ARI Dialer project

---

**Ready to get started?** Read the [Setup Guide](API_Setup.md) or explore the [Full Documentation](API_DOCUMENTATION.md).
