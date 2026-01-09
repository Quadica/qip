# Stallion Tracking Data Extraction Snippet - Implementation Plan

**Date:** 2026-01-09
**Status:** Awaiting Approval
**Target Site:** luxeonstar.com

---

## Summary

Create a Code Snippet that automatically extracts tracking information added by Stallion Express to the customer note field when an order is marked as completed, saves it to ACF fields, and cleans up the note.

---

## Requirements (Confirmed)

| Requirement | Decision |
|-------------|----------|
| Trigger | On order status change to `completed` |
| Source Data | `_customer_note` meta field |
| ACF Fields | Already exist: `tracking_no`, `shipped_carrier`, `shipped_date_ss` |
| Date Format | `m/d/Y` (01/09/2026) |
| Tracking Link | Discard (not stored) |
| Existing Data | Always overwrite ACF fields |
| Logging | Silent operation (no logging unless errors) |
| Target Site | luxeonstar.com only |

---

## Input Format

Stallion adds tracking info to the customer note in this format:

```
Tracking Number: 9212490374019100194974
Carrier: USPS
Tracking Link: https://tools.usps.com/go/TrackConfirmAction?tLabels=9212490374019100194974
```

The customer note may also contain other text (e.g., customer instructions from checkout) that must be preserved.

---

## Implementation Plan

### Hook

```php
add_action( 'woocommerce_order_status_completed', 'quadica_process_stallion_tracking', 10, 2 );
```

This hook fires when any order transitions to `completed` status, providing both order ID and WC_Order object.

### Processing Logic

1. **Get customer note** from order meta (`_customer_note` or via `$order->get_customer_note()`)

2. **Check for tracking pattern** using regex:
   ```
   /Tracking Number:\s*(.+)/i
   /Carrier:\s*(.+)/i
   /Tracking Link:\s*(.+)/i
   ```

3. **If tracking info found:**
   - Extract tracking number → save to ACF `tracking_no`
   - Extract carrier → save to ACF `shipped_carrier`
   - Set current date (m/d/Y format) → save to ACF `shipped_date_ss`

4. **Clean up customer note:**
   - Remove the three tracking lines
   - Trim extra whitespace/blank lines
   - Update the order's customer note field

5. **Save order** to persist changes

### Edge Cases

| Scenario | Handling |
|----------|----------|
| No tracking info in note | Skip processing, leave order unchanged |
| Partial tracking info (e.g., only tracking number) | Extract what's available, skip missing fields |
| Empty note after cleanup | Set note to empty string |
| Note has other content | Preserve non-tracking content |

---

## Snippet Code Structure

```php
<?php
/**
 * Stallion Tracking Data Extraction
 *
 * Extracts tracking information from Stallion Express when orders
 * are marked as completed, saves to ACF fields, and cleans up notes.
 *
 * @package Quadica
 * @since 1.0.0
 */

add_action( 'woocommerce_order_status_completed', 'quadica_process_stallion_tracking', 10, 2 );

function quadica_process_stallion_tracking( $order_id, $order ) {
    // 1. Get customer note
    // 2. Parse tracking info with regex
    // 3. If found, update ACF fields
    // 4. Clean tracking lines from note
    // 5. Save updated note
}
```

**Estimated Size:** ~60-80 lines (suitable for Code Snippets)

---

## ACF Field Mapping

| Source | ACF Field | Example Value |
|--------|-----------|---------------|
| `Tracking Number:` line | `tracking_no` | `9212490374019100194974` |
| `Carrier:` line | `shipped_carrier` | `USPS` |
| Current date | `shipped_date_ss` | `01/09/2026` |

---

## Testing Plan

### Pre-Implementation
- [ ] Verify ACF fields exist on orders: `tracking_no`, `shipped_carrier`, `shipped_date_ss`
- [ ] Check an actual Stallion-processed order to confirm note format

### Post-Implementation
- [ ] Test with order containing only tracking info in note
- [ ] Test with order containing tracking info + other customer text
- [ ] Test with order that has no tracking info (should be skipped)
- [ ] Verify ACF fields populated correctly
- [ ] Verify customer note cleaned properly (tracking lines removed, other text preserved)
- [ ] Verify date format is `m/d/Y`

---

## Deployment

1. Create snippet via WP-CLI on luxeonstar.com (live site)
2. Set snippet scope: Run everywhere (or admin-only if sufficient)
3. Activate snippet
4. Monitor next Stallion shipment for correct processing

---

## Approval Checklist

- [ ] Requirements correctly captured
- [ ] Hook selection appropriate
- [ ] ACF field names correct
- [ ] Date format confirmed (m/d/Y)
- [ ] Edge case handling acceptable
- [ ] Ready to implement

---

## Questions Before Implementation

None remaining - all requirements clarified.
