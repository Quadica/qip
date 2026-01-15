# Micro-ID Decoder Rollback Plan

## Background

The Micro-ID Decoder feature was developed across sessions 084-101. The AI vision approach proved unreliable (~70-80% accuracy even on synthetic images), leading to a pivot to a "human-in-the-loop" Manual Decoder approach in session 097.

However, the complexity of changes has introduced instability. This plan outlines options to rollback while preserving the working manual decoder functionality.

## Timeline of Changes

| Session | Commit | Description |
|---------|--------|-------------|
| 083 | `0a1758c` | **Last stable state** - Queue race conditions and Rerun UX fixes |
| 084 | `f2a9925` | Phase 1: Database schema (decode logs), Claude Vision Client |
| 085-086 | Various | Code review fixes for Phase 1 |
| 087-088 | Various | Phase 2: AJAX Handler for AI decoding |
| 089-090 | Various | Phase 3: Frontend landing page (/id) for AI image upload |
| 091-092 | Various | Phase 4: Admin settings for Claude API key |
| 093-094 | Various | Phase 5: Plugin wiring |
| 095-096 | Various | Prompt engineering, multi-image POC |
| 097 | `493a9b0` | **AI Vision Pivot** - Removed POC code, planned manual decoder |
| 098-099 | Various | **Manual Decoder** - `/decode` page with human-in-the-loop |
| 100 | Various | Mobile camera capture fixes for manual decoder |
| 101 | `3edd1ef` | AI code removal (Claude Vision, Decode Log Repository) |

## Components Analysis

### Files Created for Micro-ID Decoder (Sessions 084-094):
1. `includes/Services/class-claude-vision-client.php` - **DELETED in 101**
2. `includes/Database/class-decode-log-repository.php` - **DELETED in 101**
3. `includes/Ajax/class-microid-decoder-ajax-handler.php` - AJAX handlers
4. `includes/Frontend/class-microid-landing-handler.php` - `/id` landing page
5. `docs/database/install/05-microid-decode-logs.sql` - Decode logs table schema
6. Various reference images in `assets/reference-images/`
7. Various POC files in `docs/poc/` - **DELETED in 097**

### Files Created for Manual Decoder (Sessions 097-100):
1. `includes/Frontend/class-microid-manual-decoder-handler.php` - **KEEP**
2. `docs/plans/microid-manual-decoder-plan.md` - Plan document

### Core Plugin Modifications (Sessions 084-094):
- `qsa-engraving.php` - Handler instantiation, rewrite rules
- `includes/Admin/class-admin-menu.php` - Settings tab, API key fields
- `includes/Ajax/class-lightburn-ajax-handler.php` - Nonce localization
- `tests/smoke/wp-smoke.php` - Added ~40 tests, now reduced to ~30

## What the Manual Decoder Needs

The `/decode` page (human-in-the-loop decoder) requires:
1. **The handler file** - `class-microid-manual-decoder-handler.php`
2. **Rewrite rule** - To route `/decode` URL
3. **Plugin settings check** - `microid_decoder_enabled` setting
4. **Redirect target** - When user decodes a serial, redirect to `/id?serial=XXXXXXXX`

The `/id` page needs:
1. **Serial lookup capability** - Query `quad_microid_serials` table by serial number
2. **Basic product info display** - SKU, product name, engraved date
3. **Staff details (optional)** - Order info for logged-in staff

**Key insight**: The Serial_Repository class (`class-serial-repository.php`) is part of the CORE engraving functionality, NOT the decoder feature. It was created in earlier sessions for serial number tracking during engraving.

## Rollback Options

### Option A: Selective Removal (Current Approach)
**Status**: Partially completed but problematic

We've been trying to surgically remove AI code while keeping manual decoder. This has proven fragile because:
- Multiple interdependencies between files
- Settings, admin UI, AJAX handlers all intertwined
- Smoke tests reference removed functionality
- Landing page was designed for AI image upload

**Pros**: Preserves git history, minimal code loss
**Cons**: Complex, error-prone, multiple sessions fixing breakages

### Option B: Hard Reset + Cherry-Pick
**Approach**:
1. Hard reset to `0a1758c` (session 083 - last stable state)
2. Cherry-pick manual decoder commits:
   - `19b6409` - Add Micro-ID Manual Decoder handler
   - `4b01afb` - Fix serial passing
   - Mobile camera fixes (multiple commits)
3. Create simplified `/id` page that only does serial lookup
4. Add minimal settings (just `microid_decoder_enabled` toggle)

**Pros**: Clean slate, known working baseline
**Cons**: May have merge conflicts, loses session reports/docs

### Option C: Fresh Implementation on Clean Base (Recommended)
**Approach**:
1. Hard reset to `0a1758c` (session 083 - last stable state)
2. Copy only these files from current HEAD:
   - `class-microid-manual-decoder-handler.php` (manual decoder)
3. Create NEW simplified files:
   - Simplified `/id` page (just serial lookup, no AI)
   - Minimal AJAX handler (just `qsa_microid_serial_lookup`)
   - Minimal plugin wiring (register handler, rewrite rules)
4. Add only necessary settings toggle

**Pros**:
- Clean, minimal implementation
- No AI baggage
- Clear understanding of what code does
- Easy to maintain

**Cons**:
- Loses session reports 084-101 from git log (can archive in docs)
- Need to recreate some wiring code

## Recommended Approach: Option C

### Step 1: Preserve Current Work
```bash
# Create backup branch of current state
git checkout -b microid-decoder-archive-v1
git push origin microid-decoder-archive-v1
```

### Step 2: Reset to Pre-Decoder State
```bash
git checkout Ron
git reset --hard 0a1758c
```

### Step 3: Copy Manual Decoder Handler
Copy `class-microid-manual-decoder-handler.php` from archive branch - this file is self-contained.

### Step 4: Create Simplified /id Page
Create new `class-microid-serial-lookup-handler.php` with:
- Rewrite rule for `/id`
- Query parameter support: `?serial=XXXXXXXX`
- Serial lookup using existing Serial_Repository
- Simple result display (product info only)
- No AI, no image upload, no complex JavaScript

### Step 5: Create Minimal AJAX Handler
Create simplified handler with just:
- `qsa_microid_serial_lookup` action
- Uses Serial_Repository to find serial
- Returns basic product info

### Step 6: Minimal Plugin Wiring
Add to `qsa-engraving.php`:
- Instantiate handlers
- Register rewrite rules
- Add activation hook for rule flush

### Step 7: Add Settings Toggle
Add simple `microid_decoder_enabled` checkbox to admin settings.

### Step 8: Update Smoke Tests
Add minimal tests:
- Manual decoder handler loads
- Serial lookup handler loads
- Rewrite rules registered

## Files After Rollback

### Keep (from core engraving):
- All existing files in `/includes/` except decoder-specific
- `class-serial-repository.php` (core functionality)
- All existing smoke tests for engraving

### New (for manual decoder):
- `class-microid-manual-decoder-handler.php` (copied from current)
- `class-microid-serial-lookup-handler.php` (new, simplified)
- `class-microid-serial-ajax-handler.php` (new, minimal)

### Remove (decoder baggage):
- `class-microid-landing-handler.php` (replaced by simpler lookup)
- `class-microid-decoder-ajax-handler.php` (replaced by simpler handler)
- `05-microid-decode-logs.sql` (decode log table not needed)
- All reference images in `assets/reference-images/`
- All decoder-related smoke tests

## Questions for User

1. **Archive branch**: Should we keep the archive branch (`microid-decoder-archive-v1`) permanently, or just for reference during rollback?

2. **Database table**: The `quad_microid_decode_logs` table (if created) - should we drop it or leave it?

3. **Session reports**: Keep session 084-101 reports in `docs/project-history/` for reference, or archive them elsewhere?

4. **Scope**: Should the `/id` page also support the `code` parameter (25-char grid code) for direct lookups, or just `serial`?

## Estimated Effort

- Archive and reset: ~5 minutes
- Copy manual decoder: ~5 minutes
- Create simplified /id page: ~30 minutes
- Create minimal AJAX handler: ~15 minutes
- Plugin wiring: ~10 minutes
- Settings toggle: ~10 minutes
- Smoke tests: ~15 minutes
- Testing and verification: ~20 minutes

**Total**: ~2 hours for clean implementation
