This report provides details of the code changes made to address the issues you identified and suggestions you made. Please perform a code review and security check of the fixes to ensure that the fix has solved the problem and that the changes made have not introduced any additional problems.

---

# Session 088: Micro-ID Decoder AJAX Handler Security Fixes
- Date/Time: 2026-01-13 23:18
- Session Type(s): code-review-fixes, security-fixes
- Primary Focus Area(s): backend, ajax, security

## Overview
This session addressed 6 security and validation issues identified during code review of the Micro-ID Decoder AJAX Handler. All fixes have been implemented, tested with 6 new smoke tests, and deployed successfully with all 202 tests passing.

## Changes Made
### Files Modified
- `wp-content/plugins/qsa-engraving/includes/Ajax/class-microid-decoder-ajax-handler.php`: Fixed 6 security and validation issues
- `wp-content/plugins/qsa-engraving/tests/smoke/wp-smoke.php`: Added 6 new smoke tests (TC-MID-DEC-016 to TC-MID-DEC-021)

### Tasks Addressed
- `docs/plans/microid-decoder-plan.md` - Phase 2: AJAX Handler - security hardening complete
- Security Considerations section requirements addressed (file validation, rate limiting)

### New Functionality Added
None - this session focused on security hardening of existing functionality.

### Problems & Bugs Fixed

#### Issue 1 (Low): Cached lookup runs even when decode logs table doesn't exist
- **Problem:** `get_by_image_hash()` was called without checking if the table exists, causing potential database errors
- **Solution:** Added `table_exists()` guard before cached lookup call at lines ~180-196
- **Test:** TC-MID-DEC-018

#### Issue 2 (Medium): Uploaded file not validated with is_uploaded_file()/file_exists()
- **Problem:** File operations proceeded without confirming the file was a genuine PHP upload
- **Solution:** Added `is_uploaded_file($file['tmp_name'])` and `file_exists($file['tmp_name'])` checks before any file operations at lines ~367-371
- **Test:** TC-MID-DEC-019

#### Issue 3 (Medium): getimagesize() failure doesn't reject upload
- **Problem:** Non-image content could slip through if getimagesize() failed but other checks passed
- **Solution:** Return `WP_Error('invalid_image')` immediately when getimagesize() returns false at lines ~373-377
- **Test:** TC-MID-DEC-020

#### Issue 4 (Low): Minimum dimension check used AND condition
- **Problem:** Check was `width < 120 && height < 120`, meaning a 50x200 image would pass (only fails if BOTH dimensions are too small)
- **Solution:** Changed to `min($dimensions['width'], $dimensions['height']) < MIN_IMAGE_DIMENSION` at lines ~384-394
- **Test:** TC-MID-DEC-021

#### Issue 5 (Medium): Rate limiting trusted spoofable headers
- **Problem:** X-Forwarded-For and X-Real-IP headers were trusted, which attackers can spoof to bypass rate limiting
- **Solution:** Removed X-Forwarded-For and X-Real-IP from trusted headers. Now only trusts CF-Connecting-IP (Cloudflare) and REMOTE_ADDR (direct connection) at lines ~447-479
- **Test:** TC-MID-DEC-017

#### Issue 6 (Low): get_request_param() accepted GET requests
- **Problem:** Reading from $_GET exposes nonces in URLs, referrer headers, and browser history
- **Solution:** Removed $_GET handling entirely - now only reads from $_POST at lines ~661-677
- **Test:** TC-MID-DEC-016

### Git Commits
Key commits from this session (newest first):
- `8c7c274` - Fix security and validation issues in Micro-ID Decoder AJAX Handler

## Technical Decisions

### IP Detection Hardening
Rather than implementing full Cloudflare IP range verification (which adds complexity and maintenance burden), we removed trust in spoofable headers entirely:
- **CF-Connecting-IP:** Trusted because Cloudflare CDN sets this and it's not user-controllable when requests pass through Cloudflare
- **REMOTE_ADDR:** Always trusted as it cannot be spoofed
- **X-Forwarded-For, X-Real-IP:** No longer trusted (removed)

### Image Validation Order
Reorganized `validate_upload()` to perform security checks (is_uploaded_file, getimagesize) BEFORE reading file contents. This prevents processing of invalid/malicious uploads and follows defense-in-depth principles.

### Dimension Check Logic
Changed from `width < MIN && height < MIN` (only fails if BOTH too small) to `min(width, height) < MIN` (fails if EITHER too small). This ensures both dimensions meet the 120px minimum required for reliable Micro-ID decoding.

### POST-Only Request Parameters
Restricting nonce and parameter reading to POST prevents:
- Nonces appearing in URLs (can be logged, cached, shared)
- Nonces appearing in Referrer headers (leaked to other sites)
- Nonces appearing in browser history (accessible to other users)

## Current State
The Micro-ID Decoder AJAX Handler now has robust security and validation:
- **File upload validation:** is_uploaded_file(), file_exists(), getimagesize() all must pass
- **Rate limiting:** Uses non-spoofable IP detection (CF-Connecting-IP or REMOTE_ADDR only)
- **Request handling:** POST-only for security-sensitive parameters
- **Image validation:** Both dimensions must meet 120px minimum
- **Database safety:** Cached lookup guarded by table existence check

All 202 smoke tests pass, including 6 new tests specifically verifying these security fixes.

## Next Steps
### Immediate Tasks
- [ ] Create database table via phpMyAdmin (required for decode logging to work)
- [ ] Proceed with Phase 3: Frontend Landing Page when ready

### Known Issues
- **Decode logs table:** The `quad_microid_decode_logs` table must be created manually via phpMyAdmin before logging works. The code gracefully handles the missing table but logging is disabled until created.

## Notes for Next Session
- The Micro-ID Decoder Phase 2 (AJAX Handler) is now complete with all security fixes applied
- Phase 3 (Frontend Landing Page) is the next logical step
- The security pattern established here (IP detection, upload validation, POST-only) should be followed for any future AJAX handlers
- Test count increased from 196 to 202 (6 new security verification tests)
