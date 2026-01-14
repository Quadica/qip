# Micro-ID Decoder Implementation Plan

**Feature:** Decode proprietary 5x5 Micro-ID codes from smartphone photos
**URL:** `luxeonstar.com/id`
**Technology:** Claude Vision API
**Platform:** Extension to qsa-engraving plugin
**Created:** 2026-01-13

---

## Overview

Allow customers to upload photos of their LED modules and decode the Micro-ID code to retrieve product information. Public access shows basic info; staff see full traceability.

### User Flow
```
Customer: /id → Upload Photo → See Basic Info (Serial, SKU, Product, Date)
Staff:    /id → Upload Photo → Basic Info → "Full Details" → WP Login → Full Traceability
```

---

## Phase 1: Database & API Foundation

### 1.1 Create Decode Logs Table

**File:** `docs/database/install/05-microid-decode-logs.sql`

```sql
CREATE TABLE IF NOT EXISTS {prefix}quad_microid_decode_logs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    session_id VARCHAR(64) NOT NULL,
    image_hash VARCHAR(64) NOT NULL,
    image_path VARCHAR(500) DEFAULT NULL,
    decoded_serial VARCHAR(8) DEFAULT NULL,
    serial_found TINYINT(1) DEFAULT 0,
    decode_status ENUM('success', 'failed', 'error', 'invalid_image') NOT NULL,
    error_code VARCHAR(50) DEFAULT NULL,
    error_message TEXT DEFAULT NULL,
    api_response_time_ms INT UNSIGNED DEFAULT NULL,
    client_ip VARCHAR(45) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_session_id (session_id),
    INDEX idx_decoded_serial (decoded_serial),
    INDEX idx_created_at (created_at)
);
```

### 1.2 Create Claude Vision Client Service

**File:** `includes/Services/class-claude-vision-client.php`

- Constructor loads API key from `qsa_engraving_settings` option
- `decode_micro_id($image_base64, $mime_type)` - Send to Claude, return decoded serial
- `test_connection()` - Validate API key works
- Structured prompt with Micro-ID spec (anchors, bit layout, parity)
- Returns `WP_Error` on failures

### 1.3 Create Decode Log Repository

**File:** `includes/Database/class-decode-log-repository.php`

- `log_decode_attempt(array $data): int|WP_Error`
- `get_recent_logs(int $limit, int $offset): array`
- `cleanup_old_logs(int $days): int`

---

## Phase 2: AJAX Handler

**File:** `includes/Ajax/class-microid-decoder-ajax-handler.php`

### Public Endpoints (no auth required)
- `wp_ajax_nopriv_qsa_microid_decode` - Upload image, decode, return basic info
- `wp_ajax_qsa_microid_decode` - Same, for logged-in users

### Staff-Only Endpoint
- `wp_ajax_qsa_microid_full_details` - Requires `manage_woocommerce`, returns full traceability

### Image Validation
- Max 10MB
- JPEG, PNG, WebP only
- Minimum 120px width (per spec)

### Rate Limiting
- 10 requests/minute/IP via transients

---

## Phase 3: Frontend Landing Page

**File:** `includes/Frontend/class-microid-landing-handler.php`

### URL Routing
- Rewrite rule: `^id/?$` → `index.php?microid_lookup=1`
- Query var: `microid_lookup`
- Renders full HTML page (following QSA_Landing_Handler pattern)

### Page UI (No React - vanilla JS)
- File drop zone with camera icon
- Mobile: `<input type="file" accept="image/*" capture="environment">`
- Loading spinner during decode
- Result card with Serial, SKU, Product, Date
- "Full Details" link for staff
- Error states with retry option

---

## Phase 4: Admin Integration

### Settings Page Additions
- Claude API Key (encrypted, masked display)
- Enable/Disable Decoder toggle
- Test Connection button
- Log retention days (default 90)

### Optional: Decode Logs Page
- Submenu under QSA Engraving
- Filterable log table
- Statistics (success rate, avg response time)

---

## Phase 5: Plugin Wiring

**File:** `qsa-engraving.php` modifications

```php
// New properties
private ?Database\Decode_Log_Repository $decode_log_repository = null;
private ?Services\Claude_Vision_Client $claude_vision_client = null;
private ?Ajax\MicroID_Decoder_Ajax_Handler $microid_decoder_ajax_handler = null;
private ?Frontend\MicroID_Landing_Handler $microid_landing_handler = null;

// Initialize in init_repositories() and init_services()
// Register handlers
// Add activation hook for table creation
```

---

## Files to Create/Modify

| Action | File |
|--------|------|
| CREATE | `docs/database/install/05-microid-decode-logs.sql` |
| CREATE | `includes/Services/class-claude-vision-client.php` |
| CREATE | `includes/Database/class-decode-log-repository.php` |
| CREATE | `includes/Ajax/class-microid-decoder-ajax-handler.php` |
| CREATE | `includes/Frontend/class-microid-landing-handler.php` |
| MODIFY | `qsa-engraving.php` (add new services, handlers, menu) |
| MODIFY | `includes/Admin/class-admin-menu.php` (settings fields) |

---

## Data Returned

| Field | Customer | Staff |
|-------|:--------:|:-----:|
| Serial Number | Yes | Yes |
| Product SKU | Yes | Yes |
| Product Name | Yes | Yes |
| Engraving Date | Yes | Yes |
| Order ID | - | Yes |
| Customer Name | - | Yes |
| LED Code(s) | - | Yes |
| Batch ID | - | Yes |

---

## Security Considerations

1. **API Key:** Encrypted storage per SECURITY.md, never logged
2. **Rate Limiting:** Transient-based, 10 req/min/IP
3. **File Validation:** Type, size, MIME checks before processing
4. **Image Storage:** Hash-based filenames in uploads folder, scheduled cleanup
5. **Staff Access:** Full details require `manage_woocommerce` + valid session

---

## Verification Plan

### Smoke Tests
1. Plugin activates without PHP errors
2. `/id` URL resolves to decoder page
3. File upload validation rejects invalid files
4. Claude API connection test passes
5. Decode logs write to database

### Manual Tests
1. Upload sample Micro-ID image → decodes correctly
2. Upload blurry/bad image → helpful error message
3. Rate limit triggers after 10 rapid requests
4. Staff login → sees full details
5. Settings save and persist correctly

### Test with Sample Image
- Use `docs/sample-data/micro-id-smartphone-sample.jpg` for testing
- Verify decoded serial matches expected value

---

## Reference Files

- `docs/reference/quadica-micro-id-specs.md` - Encoding/decoding algorithm
- `docs/sample-data/micro-id-smartphone-sample.jpg` - Test image
- `includes/Frontend/class-qsa-landing-handler.php` - Pattern for URL routing
- `includes/Services/class-lightburn-client.php` - Pattern for API client
- `includes/Ajax/class-config-import-ajax-handler.php` - Pattern for file uploads

---

## Discussion Summary

This plan emerged from a discussion about options for reading proprietary Micro-ID codes:

**Decisions Made:**
- **Use Case:** Both customer self-service and internal support
- **Technology:** AI Vision API (Claude) - reliability over cost, low volume (~50/month)
- **Channels:** Website first (`/id`), email processing Phase 2
- **Output:** Different by audience (customers see basic, staff see full)
- **Platform:** WordPress plugin extension (add to qsa-engraving)
- **Auth:** No auth required for public page (basic info)
- **Logging:** All attempts logged for troubleshooting

**Why AI over Traditional CV:**
- Low volume makes API cost negligible (~$2.50/month)
- Better handling of image quality variations
- Faster to develop
- Proven to work (tested by user)
