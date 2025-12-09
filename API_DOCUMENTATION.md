# ARI Dialer REST API Documentation v1.0

## Table of Contents
- [Authentication](#authentication)
- [API Tokens Management](#api-tokens-management)
- [Response Format](#response-format)
- [Campaign Management](#campaign-management)
- [Campaign Numbers](#campaign-numbers)
- [Call Detail Records (CDR)](#call-detail-records-cdr)
- [Monitoring & Real-time Data](#monitoring--real-time-data)
- [IVR Management](#ivr-management)
- [User Management](#user-management-admin-only)
- [Error Codes](#error-codes)

---

## Base URL

```
http://your-server/adial/api
```

---

## Authentication

All API requests require authentication using a Bearer token.

### Getting an API Token

1. Log in to the ARI Dialer web interface
2. Navigate to Settings → API Tokens
3. Click "Generate New Token"
4. Copy and save the token securely (it will not be shown again)

### Using the Token

Include the token in the `Authorization` header:

```
Authorization: Bearer your_api_token_here
```

**Example with curl:**

```bash
curl -H "Authorization: Bearer abc123..." \
     http://your-server/adial/api/campaigns
```

### Token Permissions

Tokens can have restricted permissions for specific endpoints:
- `campaigns/*` - All campaign endpoints
- `campaigns/list` - Only list campaigns
- `cdr/*` - All CDR endpoints
- etc.

If no permissions are specified, the token has full access to all endpoints the user is authorized for.

### Token Expiration

Tokens can have an expiration date. Set `expires_at` when creating a token, or leave blank for non-expiring tokens.

---

## API Tokens Management

### List Your API Tokens

```
GET /api/tokens
```

**Response:**
```json
{
  "success": true,
  "message": "API tokens retrieved successfully",
  "data": [
    {
      "id": 1,
      "token": "abc12345...xyz98765",
      "name": "Production Token",
      "is_active": 1,
      "last_used": "2024-12-09 10:30:00",
      "expires_at": null,
      "created_at": "2024-12-01 09:00:00"
    }
  ]
}
```

### Create New API Token

```
POST /api/tokens
```

**Request Body:**
```json
{
  "name": "My API Token",
  "permissions": ["campaigns/*", "cdr/*"],
  "expires_at": "2025-12-31 23:59:59"
}
```

**Response:**
```json
{
  "success": true,
  "message": "API token created successfully",
  "data": {
    "token": "64_character_token_string_here",
    "name": "My API Token",
    "message": "Save this token securely. It will not be shown again."
  }
}
```

### Revoke API Token

```
DELETE /api/tokens/:id
```

**Response:**
```json
{
  "success": true,
  "message": "API token revoked successfully"
}
```

---

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
  "message": "Data retrieved successfully",
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

---

## Campaign Management

### List All Campaigns

```
GET /api/campaigns
```

**Query Parameters:**
- `status` (optional) - Filter by status: `stopped`, `running`, `paused`

**Response:**
```json
{
  "success": true,
  "message": "Campaigns retrieved successfully",
  "data": [
    {
      "id": 1,
      "name": "Sales Campaign",
      "description": "Outbound sales calls",
      "trunk_type": "pjsip",
      "trunk_value": "my_trunk",
      "callerid": "1234567890",
      "agent_dest_type": "exten",
      "agent_dest_value": "100",
      "record_calls": 1,
      "status": "running",
      "concurrent_calls": 5,
      "retry_times": 2,
      "retry_delay": 300,
      "created_at": "2024-12-01 10:00:00",
      "updated_at": "2024-12-09 09:00:00",
      "stats": {
        "total": 1000,
        "pending": 500,
        "calling": 5,
        "answered": 300,
        "completed": 300,
        "failed": 50,
        "no_answer": 100,
        "busy": 45,
        "total_calls": 495,
        "answered_calls": 300
      }
    }
  ]
}
```

### Get Campaign Details

```
GET /api/campaigns/:id
```

**Response:**
```json
{
  "success": true,
  "message": "Campaign retrieved successfully",
  "data": {
    "id": 1,
    "name": "Sales Campaign",
    ...
    "stats": { ... }
  }
}
```

### Create Campaign

```
POST /api/campaigns
```

**Request Body:**
```json
{
  "name": "New Campaign",
  "description": "Campaign description",
  "trunk_type": "pjsip",
  "trunk_value": "my_trunk",
  "callerid": "1234567890",
  "agent_dest_type": "exten",
  "agent_dest_value": "100",
  "record_calls": 1,
  "concurrent_calls": 5,
  "retry_times": 2,
  "retry_delay": 300
}
```

**Required Fields:**
- `name` - Campaign name
- `trunk_type` - One of: `custom`, `pjsip`, `sip`
- `trunk_value` - Trunk name or custom dial string
- `agent_dest_type` - One of: `custom`, `exten`, `ivr`
- `agent_dest_value` - Destination value

**Optional Fields:**
- `description` - Campaign description
- `callerid` - Caller ID for outbound calls
- `record_calls` - Enable recording (0 or 1, default: 0)
- `concurrent_calls` - Max concurrent calls (default: 1)
- `retry_times` - Number of retry attempts (default: 0)
- `retry_delay` - Delay between retries in seconds (default: 300)

**Response:**
```json
{
  "success": true,
  "code": 201,
  "message": "Campaign created successfully",
  "data": { ... }
}
```

### Update Campaign

```
PUT /api/campaigns/:id
```

**Request Body:** (Include only fields to update)
```json
{
  "name": "Updated Name",
  "concurrent_calls": 10,
  "record_calls": 1
}
```

**Note:** Cannot update a running campaign. Stop it first.

**Response:**
```json
{
  "success": true,
  "message": "Campaign updated successfully",
  "data": { ... }
}
```

### Delete Campaign

```
DELETE /api/campaigns/:id
```

**Note:** Cannot delete a running campaign. Stop it first.

**Response:**
```json
{
  "success": true,
  "message": "Campaign deleted successfully"
}
```

### Start Campaign

```
POST /api/campaigns/:id/start
```

**Response:**
```json
{
  "success": true,
  "message": "Campaign started successfully",
  "data": {
    "campaign_id": 1,
    "status": "running"
  }
}
```

### Stop Campaign

```
POST /api/campaigns/:id/stop
```

**Note:** Stopping resets all pending/calling numbers to pending status with 0 attempts.

**Response:**
```json
{
  "success": true,
  "message": "Campaign stopped successfully",
  "data": {
    "campaign_id": 1,
    "status": "stopped"
  }
}
```

### Pause Campaign

```
POST /api/campaigns/:id/pause
```

**Note:** Pausing does NOT reset numbers. Use to temporarily halt dialing.

**Response:**
```json
{
  "success": true,
  "message": "Campaign paused successfully",
  "data": {
    "campaign_id": 1,
    "status": "paused"
  }
}
```

### Resume Campaign

```
POST /api/campaigns/:id/resume
```

**Response:**
```json
{
  "success": true,
  "message": "Campaign resumed successfully",
  "data": {
    "campaign_id": 1,
    "status": "running"
  }
}
```

### Get Campaign Statistics

```
GET /api/campaigns/:id/stats
```

**Response:**
```json
{
  "success": true,
  "message": "Campaign statistics retrieved successfully",
  "data": {
    "total": 1000,
    "pending": 500,
    "calling": 5,
    "answered": 300,
    "completed": 300,
    "failed": 50,
    "no_answer": 100,
    "busy": 45,
    "total_calls": 495,
    "answered_calls": 300
  }
}
```

---

## Campaign Numbers

### List Campaign Numbers

```
GET /api/campaigns/:id/numbers
```

**Query Parameters:**
- `status` (optional) - Filter by status: `pending`, `calling`, `answered`, `failed`, `completed`, `no_answer`, `busy`
- `page` (optional) - Page number (default: 1)
- `per_page` (optional) - Items per page (default: 20, max: 100)

**Response:**
```json
{
  "success": true,
  "message": "Numbers retrieved successfully",
  "data": [
    {
      "id": 1,
      "campaign_id": 1,
      "phone_number": "1234567890",
      "status": "pending",
      "attempts": 0,
      "last_attempt": null,
      "data": "{\"name\": \"John Doe\"}",
      "created_at": "2024-12-09 10:00:00",
      "updated_at": null
    }
  ],
  "pagination": {
    "total": 1000,
    "per_page": 20,
    "current_page": 1,
    "total_pages": 50,
    "from": 1,
    "to": 20
  }
}
```

### Add Single Number

```
POST /api/campaigns/:id/numbers
```

**Request Body:**
```json
{
  "phone_number": "1234567890",
  "data": {
    "name": "John Doe",
    "custom_field": "value"
  }
}
```

**Required Fields:**
- `phone_number` - Phone number to dial

**Optional Fields:**
- `data` - JSON object with custom data (name, etc.)

**Response:**
```json
{
  "success": true,
  "code": 201,
  "message": "Number added successfully",
  "data": {
    "campaign_id": 1,
    "phone_number": "1234567890"
  }
}
```

### Bulk Add Numbers

```
POST /api/campaigns/:id/numbers/bulk
```

**Request Body:**
```json
{
  "numbers": [
    {
      "phone_number": "1234567890",
      "data": {"name": "John Doe"}
    },
    {
      "phone_number": "0987654321",
      "data": {"name": "Jane Smith"}
    }
  ]
}
```

**Response:**
```json
{
  "success": true,
  "code": 201,
  "message": "2 numbers added successfully",
  "data": {
    "campaign_id": 1,
    "count": 2
  }
}
```

### Get Number Details

```
GET /api/numbers/:id
```

**Response:**
```json
{
  "success": true,
  "message": "Number retrieved successfully",
  "data": {
    "id": 1,
    "campaign_id": 1,
    "phone_number": "1234567890",
    "status": "answered",
    "attempts": 1,
    "last_attempt": "2024-12-09 10:30:00",
    "data": "{\"name\": \"John Doe\"}",
    "created_at": "2024-12-09 10:00:00",
    "updated_at": "2024-12-09 10:30:00"
  }
}
```

### Delete Number

```
DELETE /api/numbers/:id
```

**Note:** Cannot delete a number with status "calling".

**Response:**
```json
{
  "success": true,
  "message": "Number deleted successfully"
}
```

---

## Call Detail Records (CDR)

### List CDR Records

```
GET /api/cdr
```

**Query Parameters:**
- `campaign_id` (optional) - Filter by campaign ID
- `disposition` (optional) - Filter by disposition: `answered`, `no_answer`, `busy`, `failed`, `cancelled`
- `start_date` (optional) - Filter by start date (YYYY-MM-DD)
- `end_date` (optional) - Filter by end date (YYYY-MM-DD)
- `search` (optional) - Search in caller ID, destination, agent
- `page` (optional) - Page number (default: 1)
- `per_page` (optional) - Items per page (default: 20, max: 100)

**Response:**
```json
{
  "success": true,
  "message": "CDR records retrieved successfully",
  "data": [
    {
      "id": 1,
      "campaign_id": 1,
      "campaign_number_id": 1,
      "channel_id": "1234567890.1",
      "uniqueid": "1234567890.1",
      "callerid": "1234567890",
      "destination": "100",
      "agent": "100",
      "start_time": "2024-12-09 10:30:00",
      "answer_time": "2024-12-09 10:30:05",
      "end_time": "2024-12-09 10:35:00",
      "duration": 300,
      "billsec": 295,
      "disposition": "answered",
      "recording_file": "recording-123456.wav",
      "recording_leg1": null,
      "recording_leg2": null,
      "created_at": "2024-12-09 10:35:00"
    }
  ],
  "pagination": { ... }
}
```

### Get CDR Record Details

```
GET /api/cdr/:id
```

**Response:**
```json
{
  "success": true,
  "message": "CDR record retrieved successfully",
  "data": { ... }
}
```

### Get CDR Statistics

```
GET /api/cdr/stats
```

**Query Parameters:**
- `campaign_id` (optional) - Filter by campaign ID
- `start_date` (optional) - Start date (YYYY-MM-DD)
- `end_date` (optional) - End date (YYYY-MM-DD)

**Response:**
```json
{
  "success": true,
  "message": "CDR statistics retrieved successfully",
  "data": {
    "total_calls": 1000,
    "answered": 700,
    "no_answer": 150,
    "busy": 100,
    "failed": 30,
    "cancelled": 20,
    "avg_duration": 250.5,
    "avg_billsec": 245.3,
    "total_duration": 250500,
    "total_billsec": 245300
  }
}
```

---

## Monitoring & Real-time Data

### Get System Status

```
GET /api/monitoring/status
```

**Response:**
```json
{
  "success": true,
  "message": "System status retrieved successfully",
  "data": {
    "asterisk": true,
    "database": true,
    "active_channels": 10,
    "active_campaigns": 3,
    "timestamp": "2024-12-09 10:30:00"
  }
}
```

### Get Active Channels

```
GET /api/monitoring/channels
```

**Response:**
```json
{
  "success": true,
  "message": "Active channels retrieved successfully",
  "data": {
    "channels": [
      {
        "id": "1234567890.1",
        "state": "Up",
        "caller": {
          "name": "John Doe",
          "number": "1234567890"
        },
        "connected": {
          "name": "Agent",
          "number": "100"
        },
        "creationtime": "2024-12-09T10:30:00.000+0000"
      }
    ],
    "count": 1
  }
}
```

### Get Real-time Data

```
GET /api/monitoring/realtime
```

**Response:**
```json
{
  "success": true,
  "message": "Real-time data retrieved successfully",
  "data": {
    "campaigns": [
      {
        "id": 1,
        "name": "Sales Campaign",
        "status": "running",
        "stats": { ... }
      }
    ],
    "channels": [ ... ],
    "channel_count": 10,
    "today_stats": {
      "total_calls": 500,
      "answered": 350,
      "no_answer": 75,
      "busy": 50,
      "failed": 25
    },
    "timestamp": "2024-12-09 10:30:00"
  }
}
```

---

## IVR Management

### List IVR Menus

```
GET /api/ivr
```

**Query Parameters:**
- `campaign_id` (optional) - Filter by campaign ID

**Response:**
```json
{
  "success": true,
  "message": "IVR menus retrieved successfully",
  "data": [
    {
      "id": 1,
      "campaign_id": 1,
      "name": "Main Menu",
      "audio_file": "main-menu.wav",
      "timeout": 10,
      "max_digits": 1,
      "created_at": "2024-12-01 10:00:00",
      "updated_at": null
    }
  ]
}
```

### Get IVR Menu with Actions

```
GET /api/ivr/:id
```

**Response:**
```json
{
  "success": true,
  "message": "IVR menu retrieved successfully",
  "data": {
    "id": 1,
    "campaign_id": 1,
    "name": "Main Menu",
    "audio_file": "main-menu.wav",
    "timeout": 10,
    "max_digits": 1,
    "created_at": "2024-12-01 10:00:00",
    "updated_at": null,
    "actions": [
      {
        "id": 1,
        "ivr_menu_id": 1,
        "dtmf_digit": "1",
        "action_type": "exten",
        "action_value": "100",
        "created_at": "2024-12-01 10:00:00"
      },
      {
        "id": 2,
        "ivr_menu_id": 1,
        "dtmf_digit": "2",
        "action_type": "queue",
        "action_value": "sales",
        "created_at": "2024-12-01 10:00:00"
      }
    ]
  }
}
```

### Delete IVR Menu

```
DELETE /api/ivr/:id
```

**Response:**
```json
{
  "success": true,
  "message": "IVR menu deleted successfully"
}
```

---

## User Management (Admin Only)

### List All Users

```
GET /api/users
```

**Authorization:** Admin role required

**Response:**
```json
{
  "success": true,
  "message": "Users retrieved successfully",
  "data": [
    {
      "id": 1,
      "username": "admin",
      "email": "admin@example.com",
      "full_name": "Administrator",
      "role": "admin",
      "is_active": 1,
      "api_access": 1,
      "last_login": "2024-12-09 09:00:00",
      "created_at": "2024-12-01 10:00:00",
      "updated_at": null
    }
  ]
}
```

### Create User

```
POST /api/users
```

**Authorization:** Admin role required

**Request Body:**
```json
{
  "username": "newuser",
  "password": "secure_password",
  "email": "user@example.com",
  "full_name": "New User",
  "role": "user",
  "api_access": 1
}
```

**Required Fields:**
- `username` - Unique username
- `password` - User password (will be hashed)
- `email` - User email address

**Optional Fields:**
- `full_name` - Full name
- `role` - User role: `admin` or `user` (default: `user`)
- `api_access` - Enable API access (0 or 1, default: 1)

**Response:**
```json
{
  "success": true,
  "code": 201,
  "message": "User created successfully",
  "data": { ... }
}
```

### Update User

```
PUT /api/users/:id
```

**Authorization:** Admin role required

**Request Body:** (Include only fields to update)
```json
{
  "email": "newemail@example.com",
  "role": "admin",
  "is_active": 1
}
```

**Response:**
```json
{
  "success": true,
  "message": "User updated successfully",
  "data": { ... }
}
```

### Delete User

```
DELETE /api/users/:id
```

**Authorization:** Admin role required

**Note:** Cannot delete the last admin user.

**Response:**
```json
{
  "success": true,
  "message": "User deleted successfully"
}
```

---

## Error Codes

| HTTP Code | Description |
|-----------|-------------|
| 200 | Success |
| 201 | Resource created successfully |
| 400 | Bad request (validation error, missing fields, etc.) |
| 401 | Unauthorized (invalid or missing token) |
| 403 | Forbidden (insufficient permissions) |
| 404 | Resource not found |
| 500 | Internal server error |

---

## Example Usage

### Example 1: Start a Campaign

```bash
#!/bin/bash
API_TOKEN="your_api_token_here"
BASE_URL="http://your-server/adial/api"

# Start campaign ID 1
curl -X POST \
  -H "Authorization: Bearer $API_TOKEN" \
  -H "Content-Type: application/json" \
  "$BASE_URL/campaigns/1/start"
```

### Example 2: Add Numbers to Campaign

```bash
#!/bin/bash
API_TOKEN="your_api_token_here"
BASE_URL="http://your-server/adial/api"

# Add multiple numbers to campaign ID 1
curl -X POST \
  -H "Authorization: Bearer $API_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "numbers": [
      {"phone_number": "1234567890", "data": {"name": "John Doe"}},
      {"phone_number": "0987654321", "data": {"name": "Jane Smith"}}
    ]
  }' \
  "$BASE_URL/campaigns/1/numbers/bulk"
```

### Example 3: Get Real-time Monitoring Data

```bash
#!/bin/bash
API_TOKEN="your_api_token_here"
BASE_URL="http://your-server/adial/api"

# Get real-time data
curl -H "Authorization: Bearer $API_TOKEN" \
  "$BASE_URL/monitoring/realtime"
```

### Example 4: Get CDR Statistics

```bash
#!/bin/bash
API_TOKEN="your_api_token_here"
BASE_URL="http://your-server/adial/api"

# Get today's CDR stats for campaign 1
TODAY=$(date +%Y-%m-%d)
curl -H "Authorization: Bearer $API_TOKEN" \
  "$BASE_URL/cdr/stats?campaign_id=1&start_date=$TODAY&end_date=$TODAY"
```

### Example 5: Create and Start Campaign

```bash
#!/bin/bash
API_TOKEN="your_api_token_here"
BASE_URL="http://your-server/adial/api"

# Step 1: Create campaign
CAMPAIGN_ID=$(curl -s -X POST \
  -H "Authorization: Bearer $API_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "New Sales Campaign",
    "trunk_type": "pjsip",
    "trunk_value": "my_trunk",
    "callerid": "1234567890",
    "agent_dest_type": "exten",
    "agent_dest_value": "100",
    "concurrent_calls": 5,
    "record_calls": 1
  }' \
  "$BASE_URL/campaigns" | jq -r '.data.id')

echo "Created campaign ID: $CAMPAIGN_ID"

# Step 2: Add numbers
curl -X POST \
  -H "Authorization: Bearer $API_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "numbers": [
      {"phone_number": "1111111111"},
      {"phone_number": "2222222222"},
      {"phone_number": "3333333333"}
    ]
  }' \
  "$BASE_URL/campaigns/$CAMPAIGN_ID/numbers/bulk"

# Step 3: Start campaign
curl -X POST \
  -H "Authorization: Bearer $API_TOKEN" \
  "$BASE_URL/campaigns/$CAMPAIGN_ID/start"

echo "Campaign started!"
```

---

## Python Examples

### Example 1: Campaign Management Class

```python
import requests
import json

class ARIDialerAPI:
    def __init__(self, base_url, api_token):
        self.base_url = base_url
        self.headers = {
            'Authorization': f'Bearer {api_token}',
            'Content-Type': 'application/json'
        }

    def get_campaigns(self, status=None):
        url = f'{self.base_url}/campaigns'
        params = {'status': status} if status else {}
        response = requests.get(url, headers=self.headers, params=params)
        return response.json()

    def create_campaign(self, data):
        url = f'{self.base_url}/campaigns'
        response = requests.post(url, headers=self.headers, json=data)
        return response.json()

    def start_campaign(self, campaign_id):
        url = f'{self.base_url}/campaigns/{campaign_id}/start'
        response = requests.post(url, headers=self.headers)
        return response.json()

    def stop_campaign(self, campaign_id):
        url = f'{self.base_url}/campaigns/{campaign_id}/stop'
        response = requests.post(url, headers=self.headers)
        return response.json()

    def add_numbers(self, campaign_id, numbers):
        url = f'{self.base_url}/campaigns/{campaign_id}/numbers/bulk'
        data = {'numbers': numbers}
        response = requests.post(url, headers=self.headers, json=data)
        return response.json()

    def get_cdr(self, campaign_id=None, page=1, per_page=20):
        url = f'{self.base_url}/cdr'
        params = {
            'campaign_id': campaign_id,
            'page': page,
            'per_page': per_page
        }
        response = requests.get(url, headers=self.headers, params=params)
        return response.json()

    def get_realtime_data(self):
        url = f'{self.base_url}/monitoring/realtime'
        response = requests.get(url, headers=self.headers)
        return response.json()

# Usage
api = ARIDialerAPI('http://your-server/adial/api', 'your_token_here')

# Create campaign
campaign = api.create_campaign({
    'name': 'Python Campaign',
    'trunk_type': 'pjsip',
    'trunk_value': 'trunk1',
    'agent_dest_type': 'exten',
    'agent_dest_value': '100',
    'concurrent_calls': 3
})

campaign_id = campaign['data']['id']

# Add numbers
api.add_numbers(campaign_id, [
    {'phone_number': '1234567890'},
    {'phone_number': '0987654321'}
])

# Start campaign
api.start_campaign(campaign_id)

# Monitor
realtime = api.get_realtime_data()
print(json.dumps(realtime, indent=2))
```

---

## Support

For additional support:
- Check application logs: `/var/www/html/adial/logs/`
- Check Stasis app logs: `journalctl -u ari-dialer -f`
- Review Asterisk logs: `/var/log/asterisk/`

## Version History

- **v1.0** (2024-12-09) - Initial release
  - Campaign management
  - Numbers management
  - CDR access
  - Monitoring endpoints
  - IVR management
  - User management
  - Token-based authentication
