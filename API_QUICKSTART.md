# ARI Dialer API - Quick Start

## Your API Token

```
6b0b9ac41033029a22c6b4139dfc4115ad4bfa5101f7d475acc3a05ff23e7dcb
```

**⚠️ Save this token securely! You'll need it for all API requests.**

## Quick Test Commands

### 1. Test API Connection
```bash
curl -H "Authorization: Bearer 6b0b9ac41033029a22c6b4139dfc4115ad4bfa5101f7d475acc3a05ff23e7dcb" \
  http://localhost/adial/api
```

### 2. List All Campaigns
```bash
curl -H "Authorization: Bearer 6b0b9ac41033029a22c6b4139dfc4115ad4bfa5101f7d475acc3a05ff23e7dcb" \
  http://localhost/adial/api/campaigns
```

### 3. Get System Status
```bash
curl -H "Authorization: Bearer 6b0b9ac41033029a22c6b4139dfc4115ad4bfa5101f7d475acc3a05ff23e7dcb" \
  http://localhost/adial/api/monitoring/status
```

### 4. Create a Campaign
```bash
curl -X POST \
  -H "Authorization: Bearer 6b0b9ac41033029a22c6b4139dfc4115ad4bfa5101f7d475acc3a05ff23e7dcb" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Test Campaign",
    "trunk_type": "pjsip",
    "trunk_value": "my_trunk",
    "agent_dest_type": "exten",
    "agent_dest_value": "100",
    "concurrent_calls": 3
  }' \
  http://localhost/adial/api/campaigns
```

### 5. Add Numbers to Campaign (Replace :id with campaign ID)
```bash
curl -X POST \
  -H "Authorization: Bearer 6b0b9ac41033029a22c6b4139dfc4115ad4bfa5101f7d475acc3a05ff23e7dcb" \
  -H "Content-Type: application/json" \
  -d '{
    "numbers": [
      {"phone_number": "1234567890"},
      {"phone_number": "0987654321"}
    ]
  }' \
  http://localhost/adial/api/campaigns/:id/numbers/bulk
```

### 6. Start Campaign (Replace :id with campaign ID)
```bash
curl -X POST \
  -H "Authorization: Bearer 6b0b9ac41033029a22c6b4139dfc4115ad4bfa5101f7d475acc3a05ff23e7dcb" \
  http://localhost/adial/api/campaigns/:id/start
```

### 7. Get Campaign Statistics (Replace :id with campaign ID)
```bash
curl -H "Authorization: Bearer 6b0b9ac41033029a22c6b4139dfc4115ad4bfa5101f7d475acc3a05ff23e7dcb" \
  http://localhost/adial/api/campaigns/:id/stats
```

### 8. Stop Campaign (Replace :id with campaign ID)
```bash
curl -X POST \
  -H "Authorization: Bearer 6b0b9ac41033029a22c6b4139dfc4115ad4bfa5101f7d475acc3a05ff23e7dcb" \
  http://localhost/adial/api/campaigns/:id/stop
```

### 9. Get Real-time Monitoring Data
```bash
curl -H "Authorization: Bearer 6b0b9ac41033029a22c6b4139dfc4115ad4bfa5101f7d475acc3a05ff23e7dcb" \
  http://localhost/adial/api/monitoring/realtime
```

### 10. Get CDR Records
```bash
curl -H "Authorization: Bearer 6b0b9ac41033029a22c6b4139dfc4115ad4bfa5101f7d475acc3a05ff23e7dcb" \
  http://localhost/adial/api/cdr?page=1&per_page=20
```

## Environment Variables (Optional)

For easier testing, set these environment variables:

```bash
export API_TOKEN="6b0b9ac41033029a22c6b4139dfc4115ad4bfa5101f7d475acc3a05ff23e7dcb"
export API_URL="http://localhost/adial/api"

# Then use them in commands:
curl -H "Authorization: Bearer $API_TOKEN" $API_URL/campaigns
```

## Available Endpoints

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/api` | GET | API information |
| `/api/campaigns` | GET | List campaigns |
| `/api/campaigns` | POST | Create campaign |
| `/api/campaigns/:id` | GET | Get campaign details |
| `/api/campaigns/:id` | PUT | Update campaign |
| `/api/campaigns/:id` | DELETE | Delete campaign |
| `/api/campaigns/:id/start` | POST | Start campaign |
| `/api/campaigns/:id/stop` | POST | Stop campaign |
| `/api/campaigns/:id/pause` | POST | Pause campaign |
| `/api/campaigns/:id/resume` | POST | Resume campaign |
| `/api/campaigns/:id/stats` | GET | Campaign statistics |
| `/api/campaigns/:id/numbers` | GET | List numbers |
| `/api/campaigns/:id/numbers` | POST | Add single number |
| `/api/campaigns/:id/numbers/bulk` | POST | Bulk add numbers |
| `/api/numbers/:id` | GET | Get number details |
| `/api/numbers/:id` | DELETE | Delete number |
| `/api/cdr` | GET | List CDR records |
| `/api/cdr/:id` | GET | Get CDR details |
| `/api/cdr/stats` | GET | CDR statistics |
| `/api/monitoring/status` | GET | System status |
| `/api/monitoring/channels` | GET | Active channels |
| `/api/monitoring/realtime` | GET | Real-time data |
| `/api/ivr` | GET | List IVR menus |
| `/api/ivr/:id` | GET | Get IVR menu |
| `/api/ivr/:id` | DELETE | Delete IVR menu |
| `/api/users` | GET | List users (Admin) |
| `/api/users` | POST | Create user (Admin) |
| `/api/users/:id` | PUT | Update user (Admin) |
| `/api/users/:id` | DELETE | Delete user (Admin) |
| `/api/tokens` | GET | List your tokens |
| `/api/tokens` | POST | Create token |
| `/api/tokens/:id` | DELETE | Revoke token |

## Next Steps

1. Read the complete documentation: **[API_DOCUMENTATION.md](API_DOCUMENTATION.md)**
2. Import Postman collection: **[ARI_Dialer_API.postman_collection.json](ARI_Dialer_API.postman_collection.json)**
3. Review setup guide: **[API_Setup.md](API_Setup.md)**

## Generate More Tokens

To create additional API tokens:

```bash
TOKEN=$(openssl rand -hex 32)
mysql -u adialer_user -pXD3UBuaY53LCLiMn adialer -e "
INSERT INTO api_tokens (user_id, token, name, is_active, created_at)
VALUES (1, '$TOKEN', 'My New Token', 1, NOW());
"
echo "New Token: $TOKEN"
```

Or use the API:

```bash
curl -X POST \
  -H "Authorization: Bearer 6b0b9ac41033029a22c6b4139dfc4115ad4bfa5101f7d475acc3a05ff23e7dcb" \
  -H "Content-Type: application/json" \
  -d '{"name": "My New Token"}' \
  http://localhost/adial/api/tokens
```

## Troubleshooting

### 401 Unauthorized
- Check your token is correct
- Verify user is active: `SELECT * FROM users WHERE id=1;`
- Check API access: `SELECT * FROM users WHERE id=1;` (api_access should be 1)

### 403 Forbidden
- Check token permissions: `SELECT permissions FROM api_tokens WHERE token='your_token';`
- Some endpoints require admin role

### 404 Not Found
- Verify endpoint URL is correct
- Check campaign/resource ID exists

## Support

- **Logs:** `/var/www/html/adial/logs/`
- **Apache Logs:** `/var/log/httpd/error_log`
- **Documentation:** See API_DOCUMENTATION.md for complete reference
