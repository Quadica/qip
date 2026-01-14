This report provides details of the code that was created to implement phase 2 of this project.

Please perform a comprehensive code and security review covering:
- Correctness of functionality vs. intended behavior
- Code quality (readability, maintainability, adherence to best practices)
- Security vulnerabilities (injection, XSS, CSRF, data validation, authentication, authorization, etc.)
- Performance and scalability concerns
- Compliance with WordPress and WooCommerce coding standards (if applicable)

Provide your response in this structure:
- Summary of overall findings
- Detailed list of issues with file name, line numbers (if applicable), issue description, and recommended fix
- Security risk level (Low / Medium / High) for each issue
- Suggested improvements or refactoring recommendations
- End with a brief final assessment (e.g., "Ready for deployment", "Requires moderate refactoring", etc.).

---

# Session 087: Micro-ID Decoder Phase 2 - AJAX Handler
- Date/Time: 2026-01-13 23:02
- Session Type(s): feature-implementation
- Primary Focus Area(s): backend, ajax

## Overview
Implemented Phase 2 of the Micro-ID Decoder feature - the AJAX Handler. This ~650-line class provides public and staff-only endpoints for decoding Micro-ID codes from smartphone images. The implementation includes image validation, rate limiting, result caching, comprehensive logging, and proper capability checks for staff-only data.

## Changes Made
### Files Modified
- `wp-content/plugins/qsa-engraving/includes/Ajax/class-microid-decoder-ajax-handler.php`: New file implementing AJAX endpoints for Micro-ID decoding
- `wp-content/plugins/qsa-engraving/tests/smoke/wp-smoke.php`: Added 5 new smoke tests (TC-MID-DEC-011 to TC-MID-DEC-015)

### Tasks Addressed
- `docs/plans/microid-decoder-plan.md` - Phase 2: AJAX Handler - Complete implementation
  - Public Endpoints: Both `wp_ajax_nopriv_qsa_microid_decode` and `wp_ajax_qsa_microid_decode` for image upload and decode
  - Staff-Only Endpoint: `wp_ajax_qsa_microid_full_details` requiring `manage_woocommerce` capability
  - Image Validation: Size, MIME type, and dimension checks
  - Rate Limiting: 10 requests per minute per IP via transients

### New Functionality Added

#### 1. MicroID_Decoder_Ajax_Handler Class
**Namespace:** `Quadica\QSA_Engraving\Ajax`
**Lines of code:** ~650

Constructor accepts three injected dependencies:
- `Claude_Vision_Client` - For API calls
- `Decode_Log_Repository` - For logging and cache lookup
- `Serial_Repository` - For serial/product lookup

#### 2. AJAX Endpoints

| Action | Auth Required | Purpose |
|--------|---------------|---------|
| `qsa_microid_decode` | No | Accept image upload, validate, send to Claude Vision API, return basic serial info |
| `qsa_microid_full_details` | Yes (`manage_woocommerce`) | Return complete traceability information for a decoded serial |

#### 3. Constants Defined

```php
NONCE_ACTION = 'qsa_microid_decode'
RATE_LIMIT_MAX = 10
RATE_LIMIT_WINDOW = 60 (seconds)
MAX_IMAGE_SIZE = 10 * 1024 * 1024 (10 MB)
MIN_IMAGE_DIMENSION = 120 (pixels)
ALLOWED_MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp']
STAFF_CAPABILITY = 'manage_woocommerce'
```

#### 4. Image Validation Pipeline

1. **Upload check:** Verifies file exists in `$_FILES`
2. **Upload error handling:** Maps PHP upload error codes to user-friendly messages
3. **Size validation:** Rejects files > 10 MB
4. **MIME validation:** Uses `wp_check_filetype_and_ext()` with `finfo` fallback
5. **Dimension validation:** Requires at least one dimension >= 120px
6. **Hash calculation:** SHA-256 for caching/deduplication

#### 5. Rate Limiting

- Transient-based, keyed by MD5 of client IP
- 10 requests per 60-second window
- Cloudflare-aware IP detection (checks `CF-Connecting-IP`, `X-Forwarded-For`, `X-Real-IP`, `REMOTE_ADDR`)

#### 6. Result Caching/Deduplication

- Checks `decode_log_repository` for existing successful decode with same image hash
- Returns cached result without re-calling Claude API
- Response indicates `cached: true` when using cached result

#### 7. Serial Info Methods

| Method | Access | Data Returned |
|--------|--------|---------------|
| `get_basic_serial_info()` | Public | SKU, product name (via `wc_get_product`), engraved date |
| `get_full_serial_info()` | Staff | All basic info plus order URL, customer name, batch ID, sequence, timestamps |

#### 8. Comprehensive Logging

All decode attempts logged with:
- Session ID (UUID)
- Image hash (SHA-256)
- Decode status (success/failed/error/invalid_image)
- Serial (if decoded)
- Error code/message (if failed)
- Image metadata (size, dimensions)
- API metrics (response time, tokens)
- Client info (IP, user agent, user ID)

### Problems & Bugs Fixed
- N/A - New implementation

### Git Commits
Key commits from this session (newest first):
- `493582d` - Implement Micro-ID Decoder Phase 2: AJAX Handler

## Technical Decisions

- **Public decode endpoint for basic info:** Allows anonymous users to decode images and see basic product info. Full details require staff login. This matches the PRD requirement for customer self-service at `/id`.

- **Transient-based rate limiting:** Simple and effective for low-volume use case (~50 requests/month expected). No external dependencies (Redis, etc.). Cleanup is automatic via WordPress transient expiration.

- **Image hash caching:** Prevents unnecessary API calls for duplicate images. Users who retry the same image get instant cached results. Hash is calculated before API call, checked against successful decode logs.

- **Cloudflare IP detection:** Site uses Cloudflare CDN, so we check `CF-Connecting-IP` header first to get real client IP behind the proxy. Falls back through standard proxy headers.

- **finfo fallback for MIME detection:** WordPress's `wp_check_filetype_and_ext()` can fail on some systems. Using `finfo` as fallback ensures reliable MIME detection from actual file content, not just filename extension.

- **Dependency injection:** Handler accepts dependencies via constructor rather than creating them internally. This allows for easier testing and follows the pattern established in the existing AJAX handlers.

## Current State

The AJAX handler is fully implemented with all planned features from Phase 2:

- [x] Public decode endpoint (no auth required for basic info)
- [x] Staff-only full details endpoint (requires `manage_woocommerce`)
- [x] Image validation (size <=10 MB, type JPEG/PNG/WebP, dimension >= 120px)
- [x] Rate limiting (10 req/min/IP via transients)
- [x] Result caching (by image hash)
- [x] Comprehensive logging (all metadata captured)
- [x] Smoke tests (5 new tests)

All 196 smoke tests pass on staging (5 new tests added).

## Next Steps
### Immediate Tasks
- [ ] Create database table (`docs/database/install/05-microid-decode-logs.sql`) via phpMyAdmin
- [ ] Wire handler in main plugin file (`qsa-engraving.php`)
- [ ] Continue with Phase 3: Frontend Landing Page (`/id` URL routing)
- [ ] Create file drop zone UI with mobile camera capture support
- [ ] Add result display with "Full Details" link for staff

### Known Issues
- **Database table not created:** The `lw_quad_microid_decode_logs` table must be created via phpMyAdmin before logging works. Handler gracefully skips logging if table doesn't exist.
- **Claude API key not configured:** Phase 4 will add admin UI for API key configuration. Currently uses placeholder/empty key.
- **Handler not wired:** Phase 5 (Plugin Wiring) will register the handler in the main plugin file.

## Notes for Next Session

1. **Phase 3 Pattern Reference:** Use `includes/Frontend/class-qsa-landing-handler.php` as the pattern for URL routing. The handler implements a rewrite rule for the `/id` path.

2. **Mobile Camera Capture:** The frontend form should use `<input type="file" accept="image/*" capture="environment">` to trigger the device camera on mobile.

3. **No React for Landing Page:** Per the plan, the `/id` page uses vanilla JavaScript, not React. This keeps the page lightweight for mobile users.

4. **Staff Login Flow:** When a non-staff user clicks "Full Details", they should be redirected to WordPress login, then back to the decoder page with their serial pre-populated.

5. **Test Image Available:** `docs/sample-data/micro-id-smartphone-sample.jpg` is available for testing the complete flow once the frontend is built.

6. **196 Total Smoke Tests:** The test count grew from 191 (session 086) to 196 (this session). Five new tests verify the AJAX handler class structure, constants, methods, nonce creation, and instantiation.
