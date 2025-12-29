# UPS Tracking Change Detection - Simplified Test Cases

**Date:** September 11, 2025  
**Version:** 1.0  
**Purpose:** Test the simplified tracking number change detection implementation

## Prerequisites

1. Testing environment with WooCommerce and ACF installed
2. UPS API credentials configured and enabled
3. At least one test order with a US shipping address
4. Access to order admin interface

## Test Data Setup

Create or identify test orders with:
- Order status: Completed
- Shipping country: US
- Valid UPS tracking number in ACF field

## Test Cases

### Test 1: Basic Tracking Number Change Detection

**Objective:** Verify that changing the tracking number triggers document resubmission

**Steps:**
1. Create a new order with US shipping address
2. Add a UPS tracking number: `1Z12345E0205271688`
3. Change order status to "Completed"
4. Verify in order notes that UPS documents were submitted
5. Check database: `SELECT meta_value FROM wp_postmeta WHERE post_id = [ORDER_ID] AND meta_key = '_ups_docs_submitted_tracking'`
6. Change the tracking number to: `1Z12345E0390515214`
7. Save the order

**Expected Results:**
- Order note appears: "UPS tracking number changed from 1Z12345E0205271688 to 1Z12345E0390515214. Resubmitting customs documents..."
- New order note shows successful document resubmission
- Database shows new tracking number in `_ups_docs_submitted_tracking`

### Test 2: Non-UPS Tracking Number

**Objective:** Verify that non-UPS tracking numbers are ignored

**Steps:**
1. Take a completed order with UPS documents submitted
2. Change tracking number to FedEx format: `123456789012`
3. Save the order

**Expected Results:**
- No resubmission occurs
- No new order notes added
- Original UPS tracking remains in database

### Test 3: Clear Tracking Number

**Objective:** Verify that clearing the tracking field doesn't trigger errors

**Steps:**
1. Take a completed order with UPS documents submitted
2. Clear the tracking number field (empty)
3. Save the order

**Expected Results:**
- No resubmission occurs
- No errors displayed
- Tracking flag remains in database

### Test 4: Multiple Tracking Changes

**Objective:** Verify multiple tracking changes work correctly

**Steps:**
1. Start with completed order with tracking `1Z12345E0205271688`
2. Change to `1Z12345E0390515214` and save
3. Wait for submission to complete
4. Change to `1Z12345E1505270452` and save

**Expected Results:**
- Each change triggers a new submission
- Order notes show both changes
- Final tracking number is stored in database

### Test 5: Status Change Flow

**Objective:** Verify tracking flag is cleared when order status changes

**Steps:**
1. Start with completed order with submitted documents
2. Change status to "Processing"
3. Verify tracking flag is cleared: `SELECT * FROM wp_postmeta WHERE post_id = [ORDER_ID] AND meta_key = '_ups_docs_submitted_tracking'`
4. Change tracking number
5. Change status back to "Completed"

**Expected Results:**
- Tracking flag cleared when moving away from completed
- Documents resubmitted when returning to completed
- New tracking number stored

### Test 6: Migration Tool

**Objective:** Test the migration tool for existing orders

**Steps:**
1. Manually create old-style flags in database:
   ```sql
   INSERT INTO wp_postmeta (post_id, meta_key, meta_value) 
   VALUES ([ORDER_ID], '_ups_docs_auto_submitted', 'yes');
   ```
2. Go to DTM Settings > UPS API Integration
3. Click "Migrate Tracking Flags" button
4. Confirm the migration

**Expected Results:**
- Success message shows number of orders migrated
- Old `_ups_docs_auto_submitted` flags removed
- New `_ups_docs_submitted_tracking` entries created with actual tracking numbers

### Test 7: Concurrent Edit Protection

**Objective:** Verify no duplicate submissions during rapid changes

**Steps:**
1. Open the same order in two browser windows
2. Change tracking number in first window
3. Immediately change to different tracking in second window
4. Save both rapidly

**Expected Results:**
- Both changes processed sequentially
- No duplicate submissions
- Final tracking number is what's stored

### Test 8: ACF Field Name Configuration

**Objective:** Verify system respects configured tracking field name

**Steps:**
1. Check DTM Settings for configured tracking field name
2. Create order with tracking in that specific ACF field
3. Complete order and verify submission
4. Change tracking number

**Expected Results:**
- System correctly identifies the configured field
- Change detection works with custom field name

## Performance Testing

### Load Test
1. Create 10 orders with tracking numbers
2. Use bulk edit to change all tracking numbers
3. Monitor system performance

**Expected Results:**
- All orders process successfully
- No timeout errors
- Server remains responsive

## Troubleshooting

### Common Issues:

1. **ACF Hook Not Firing**
   - Verify ACF Pro is installed and activated
   - Check that tracking field name matches configuration
   - Ensure order is in "completed" status

2. **Documents Not Resubmitting**
   - Check PHP error log for issues
   - Verify UPS API credentials are valid
   - Ensure tracking number is valid UPS format

3. **Migration Fails**
   - Check for orders without tracking numbers
   - Verify database permissions
   - Look for corrupted order data

## Verification Queries

```sql
-- Check current tracking flags
SELECT post_id, meta_value 
FROM wp_postmeta 
WHERE meta_key = '_ups_docs_submitted_tracking' 
ORDER BY post_id DESC 
LIMIT 10;

-- Find orders with old flags (pre-migration)
SELECT COUNT(*) 
FROM wp_postmeta 
WHERE meta_key = '_ups_docs_auto_submitted';

-- Verify specific order
SELECT meta_key, meta_value 
FROM wp_postmeta 
WHERE post_id = [ORDER_ID] 
AND meta_key LIKE '%ups%';
```

## Success Criteria

- ✅ All tracking number changes on completed orders trigger resubmission
- ✅ Non-UPS tracking numbers are ignored
- ✅ Status changes properly manage tracking flags
- ✅ Migration tool successfully converts old flags
- ✅ No performance degradation
- ✅ Clear audit trail in order notes
