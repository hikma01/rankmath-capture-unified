# API Documentation

## 📚 RankMath Capture Unified - REST API Reference

Version: 1.0  
Base URL: `https://yoursite.com/wp-json/rmcu/v1`

## Authentication

The API supports multiple authentication methods:

### 1. Cookie Authentication (Default)
Standard WordPress authentication using cookies. Required for frontend requests.

### 2. Application Passwords
```bash
curl -u username:application_password https://yoursite.com/wp-json/rmcu/v1/captures
```

### 3. JWT Authentication (Optional)
```bash
curl -H "Authorization: Bearer YOUR_JWT_TOKEN" https://yoursite.com/wp-json/rmcu/v1/captures
```

### 4. API Key (Custom Header)
```bash
curl -H "X-RMCU-API-Key: YOUR_API_KEY" https://yoursite.com/wp-json/rmcu/v1/captures
```

---

## Endpoints

### Captures

#### List All Captures
```http
GET /captures
```

**Parameters:**
| Parameter | Type | Description | Default |
|-----------|------|-------------|---------|
| page | integer | Page number | 1 |
| per_page | integer | Items per page (max 100) | 10 |
| type | string | Filter by capture type (video/audio/screen/screenshot) | all |
| status | string | Filter by status (active/processing/failed) | all |
| user | integer | Filter by user ID | all |
| orderby | string | Sort field (id/date/title/type) | date |
| order | string | Sort direction (asc/desc) | desc |

**Response:**
```json
{
  "data": [
    {
      "id": 123,
      "title": "Sample Video",
      "type": "video",
      "status": "active",
      "file_url": "https://example.com/uploads/video.webm",
      "thumbnail_url": "https://example.com/uploads/thumb.jpg",
      "duration": 120,
      "size": 5242880,
      "user_id": 1,
      "post_id": 456,
      "created_at": "2024-01-15T10:30:00Z",
      "updated_at": "2024-01-15T10:35:00Z",
      "metadata": {
        "width": 1920,
        "height": 1080,
        "fps": 30,
        "codec": "vp9"
      }
    }
  ],
  "meta": {
    "total": 50,
    "pages": 5,
    "current_page": 1
  }
}
```

---

#### Get Single Capture
```http
GET /captures/{id}
```

**Response:**
```json
{
  "id": 123,
  "title": "Sample Video",
  "type": "video",
  "status": "active",
  "file_url": "https://example.com/uploads/video.webm",
  "thumbnail_url": "https://example.com/uploads/thumb.jpg",
  "duration": 120,
  "size": 5242880,
  "user_id": 1,
  "post_id": 456,
  "created_at": "2024-01-15T10:30:00Z",
  "updated_at": "2024-01-15T10:35:00Z",
  "metadata": {
    "width": 1920,
    "height": 1080,
    "fps": 30,
    "codec": "vp9",
    "bitrate": 2500000,
    "has_audio": true
  },
  "_links": {
    "self": "https://example.com/wp-json/rmcu/v1/captures/123",
    "collection": "https://example.com/wp-json/rmcu/v1/captures",
    "author": "https://example.com/wp-json/wp/v2/users/1",
    "post": "https://example.com/wp-json/wp/v2/posts/456"
  }
}
```

---

#### Create Capture
```http
POST /captures
```

**Request Body:**
```json
{
  "title": "My New Capture",
  "type": "video",
  "file": "base64_encoded_file_data",
  "post_id": 456,
  "metadata": {
    "duration": 120,
    "width": 1920,
    "height": 1080
  }
}
```

**Response:**
```json
{
  "id": 124,
  "message": "Capture created successfully",
  "file_url": "https://example.com/uploads/capture-124.webm",
  "thumbnail_url": "https://example.com/uploads/capture-124-thumb.jpg"
}
```

---

#### Update Capture
```http
PUT /captures/{id}
```

**Request Body:**
```json
{
  "title": "Updated Title",
  "metadata": {
    "tags": ["tutorial", "demo"],
    "description": "Updated description"
  }
}
```

---

#### Delete Capture
```http
DELETE /captures/{id}
```

**Parameters:**
| Parameter | Type | Description |
|-----------|------|-------------|
| permanent | boolean | Skip trash and delete permanently |

**Response:**
```json
{
  "deleted": true,
  "message": "Capture deleted successfully"
}
```

---

### Processing

#### Process Capture
```http
POST /process
```

**Request Body:**
```json
{
  "capture_id": 123,
  "operations": [
    {
      "type": "compress",
      "quality": 80
    },
    {
      "type": "watermark",
      "text": "© My Site",
      "position": "bottom-right"
    },
    {
      "type": "thumbnail",
      "time": 2
    }
  ]
}
```

---

#### Get Processing Status
```http
GET /process/{job_id}
```

**Response:**
```json
{
  "job_id": "abc123",
  "status": "processing",
  "progress": 45,
  "message": "Applying watermark...",
  "started_at": "2024-01-15T10:30:00Z",
  "estimated_completion": "2024-01-15T10:35:00Z"
}
```

---

### Queue

#### Get Queue Status
```http
GET /queue
```

**Response:**
```json
{
  "total": 15,
  "pending": 10,
  "processing": 3,
  "failed": 2,
  "items": [
    {
      "id": 1,
      "capture_id": 123,
      "status": "pending",
      "priority": 10,
      "created_at": "2024-01-15T10:30:00Z"
    }
  ]
}
```

---

#### Add to Queue
```http
POST /queue
```

**Request Body:**
```json
{
  "capture_id": 123,
  "priority": 5,
  "operations": ["compress", "thumbnail"]
}
```

---

### Webhooks

#### Register Webhook
```http
POST /webhooks
```

**Request Body:**
```json
{
  "url": "https://your-endpoint.com/webhook",
  "events": ["capture.created", "capture.processed", "capture.deleted"],
  "secret": "your_webhook_secret"
}
```

---

#### List Webhooks
```http
GET /webhooks
```

---

#### Delete Webhook
```http
DELETE /webhooks/{id}
```

---

### Settings

#### Get Settings
```http
GET /settings
```

**Response:**
```json
{
  "capture": {
    "enable_video": true,
    "enable_audio": true,
    "max_duration": 120,
    "video_quality": "medium"
  },
  "storage": {
    "location": "uploads",
    "cdn_url": "https://cdn.example.com"
  }
}
```

---

#### Update Settings
```http
PUT /settings
```

**Request Body:**
```json
{
  "capture": {
    "max_duration": 180
  }
}
```

---

## Webhook Events

### Event Types

| Event | Description |
|-------|-------------|
| capture.created | New capture created |
| capture.updated | Capture updated |
| capture.deleted | Capture deleted |
| capture.processed | Processing completed |
| capture.failed | Processing failed |
| queue.completed | Queue item processed |
| storage.uploaded | File uploaded to storage |

### Event Payload

```json
{
  "event": "capture.created",
  "timestamp": "2024-01-15T10:30:00Z",
  "data": {
    "id": 123,
    "type": "video",
    "url": "https://example.com/capture.webm"
  },
  "signature": "sha256=..."
}
```

### Webhook Security

Verify webhook signatures:

```php
$payload = file_get_contents('php://input');
$signature = $_SERVER['HTTP_X_RMCU_SIGNATURE'];
$expected = 'sha256=' . hash_hmac('sha256', $payload, $webhook_secret);

if (hash_equals($expected, $signature)) {
    // Authentic webhook
}
```

---

## Error Handling

### Error Response Format

```json
{
  "code": "invalid_param",
  "message": "Invalid capture type specified",
  "data": {
    "status": 400,
    "params": {
      "type": "invalid_value"
    }
  }
}
```

### Common Error Codes

| Code | HTTP Status | Description |
|------|-------------|-------------|
| invalid_param | 400 | Invalid parameter provided |
| missing_param | 400 | Required parameter missing |
| unauthorized | 401 | Authentication required |
| forbidden | 403 | Insufficient permissions |
| not_found | 404 | Resource not found |
| method_not_allowed | 405 | HTTP method not allowed |
| conflict | 409 | Resource conflict |
| too_large | 413 | File too large |
| rate_limited | 429 | Too many requests |
| server_error | 500 | Internal server error |
| not_implemented | 501 | Feature not implemented |

---

## Rate Limiting

- Default: 60 requests per minute per IP
- Authenticated: 120 requests per minute per user
- Bulk operations: 10 requests per minute

Rate limit headers:
```
X-RateLimit-Limit: 60
X-RateLimit-Remaining: 45
X-RateLimit-Reset: 1642248000
```

---

## PHP SDK Usage

### Installation
```bash
composer require rmcu/php-sdk
```

### Basic Usage
```php
use RMCU\Client;

$client = new Client([
    'base_url' => 'https://yoursite.com',
    'api_key' => 'your_api_key'
]);

// List captures
$captures = $client->captures()->list([
    'type' => 'video',
    'per_page' => 20
]);

// Create capture
$capture = $client->captures()->create([
    'title' => 'My Video',
    'type' => 'video',
    'file' => base64_encode($file_contents)
]);

// Process capture
$job = $client->process($capture->id, [
    ['type' => 'compress', 'quality' => 80],
    ['type' => 'thumbnail', 'time' => 5]
]);

// Check status
$status = $client->jobs()->get($job->id);
```

---

## JavaScript SDK Usage

### Installation
```bash
npm install @rmcu/js-sdk
```

### Basic Usage
```javascript
import { RMCUClient } from '@rmcu/js-sdk';

const client = new RMCUClient({
    baseUrl: 'https://yoursite.com',
    apiKey: 'your_api_key'
});

// List captures
const captures = await client.captures.list({
    type: 'video',
    perPage: 20
});

// Upload capture
const capture = await client.captures.create({
    title: 'My Video',
    type: 'video',
    file: videoBlob
});

// Process capture  
const job = await client.process(capture.id, [
    { type: 'compress', quality: 80 },
    { type: 'thumbnail', time: 5 }
]);

// Subscribe to events
client.on('capture.processed', (data) => {
    console.log('Capture processed:', data);
});
```

---

## cURL Examples

### Get all video captures
```bash
curl -X GET "https://yoursite.com/wp-json/rmcu/v1/captures?type=video" \
     -H "X-RMCU-API-Key: your_api_key"
```

### Upload new capture
```bash
curl -X POST "https://yoursite.com/wp-json/rmcu/v1/captures" \
     -H "X-RMCU-API-Key: your_api_key" \
     -H "Content-Type: application/json" \
     -d '{
       "title": "Test Video",
       "type": "video",
       "file": "base64_encoded_data..."
     }'
```

### Process capture with webhook
```bash
curl -X POST "https://yoursite.com/wp-json/rmcu/v1/process" \
     -H "X-RMCU-API-Key: your_api_key" \
     -H "Content-Type: application/json" \
     -d '{
       "capture_id": 123,
       "webhook_url": "https://your-webhook.com/endpoint",
       "operations": [
         {"type": "compress", "quality": 75}
       ]
     }'
```

---

## Testing

### Postman Collection
Download our [Postman collection](rmcu-api.postman_collection.json) for easy testing.

### Test Endpoints
- Sandbox: `https://sandbox.rmcu.com/wp-json/rmcu/v1`
- API Key: `test_key_123456789`

---

## Support

- Documentation: [https://docs.rmcu.com](https://docs.rmcu.com)
- Issues: [GitHub Issues](#)
- Email: api-support@rmcu.com