# UPS Document Submission Test Cases

## Test Case Version
- **Version:** 1.0
- **Date:** 2025-01-31
- **Component:** UPS Customs Document Submission System
- **Environment:** WordPress staging site with DTM plugin v1.6.0

## Prerequisites

### 1. System Requirements
- [ ] WordPress 6.0+ with WooCommerce 7.0+
- [ ] Dynamic Tariff Management plugin activated
- [ ] PDFInvoiceBuilder Pro plugin activated
- [ ] Advanced Custom Fields (ACF) Pro plugin activated
- [ ] UPS API credentials configured in DTM Settings
- [ ] PDF template IDs configured in DTM Settings → PDF Template Mapping

### 2. Test Data Setup
- [ ] Create test products with the following ACF field configurations:
  - **Product A**: COO = "US" (CUSMA eligible)
  - **Product B**: COO = "CN" (non-CUSMA)
  - **Product C**: fda-required = 1
  - **Product D**: tsca-required = 1
  - **Product E**: usda-required = 1
- [ ] Create PDFInvoiceBuilder templates for each document type
- [ ] Configure template IDs in DTM Settings

### 3. Sample Orders
Create the following test orders:
1. **Order US-001**: US shipping, Product A (CUSMA required)
2. **Order US-002**: US shipping, Products C, D, E (FDA, TSCA, USDA required)
3. **Order CA-001**: Canada shipping, Product A (no documents required)
4. **Order INT-001**: UK shipping, Product B (commercial invoice only)

## Test Cases

### TC-001: Settings Configuration
**Objective:** Verify PDF template mapping settings can be configured

**Steps:**
1. Navigate to **DTM → Settings**
2. Scroll to "PDF Template Mapping" section
3. Enter template IDs for each document type:
   - Commercial Invoice: 5
   - CUSMA Certificate: 7
   - FDA Form: 8
   - TSCA Form: 9
   - USDA Form: 10
4. Enter UPS Shipper Number: "3R834W"
5. Click "Save Settings"

**Expected Results:**
- Settings saved successfully message appears
- Template IDs are retained after page refresh
- UPS Shipper Number is saved

**Actual Results:** _[To be filled during testing]_

---

### TC-002: Manual Document Submission - CUSMA Only
**Objective:** Test manual submission of CUSMA certificate

**Steps:**
1. Edit Order US-001
2. Add tracking number: "1Z3R834W0123456789" in ACF field
3. Save order
4. From "Order actions" dropdown, select "Submit UPS Documents"
5. Click the arrow button to execute

**Expected Results:**
- Admin notice shows "CUSMA Certificate submitted successfully"
- Order note added with submission details
- UPS Document Submissions meta box shows:
  - ✅ CUSMA Certificate with response ID
  - Tracking number displayed
- No other documents submitted

**Actual Results:** _[To be filled during testing]_

---

### TC-003: Multiple Document Submission
**Objective:** Test submission of multiple document types

**Steps:**
1. Edit Order US-002
2. Add tracking number: "1Z3R834W9876543210" 
3. Save order
4. Select "Submit UPS Documents" from Order actions
5. Execute action

**Expected Results:**
- Admin notice shows successful submission for:
  - Commercial Invoice
  - FDA Form
  - TSCA Form
  - USDA Form
- Meta box displays all 4 documents with ✅ status
- Each document has unique response ID

**Actual Results:** _[To be filled during testing]_

---

### TC-004: Automatic Submission on Order Completion
**Objective:** Verify automatic document submission when order status changes to completed

**Steps:**
1. Create new order with US shipping and Product A
2. Add tracking number: "1Z3R834W1111111111"
3. Set order status to "Processing"
4. Save order
5. Change order status to "Completed"
6. Save order

**Expected Results:**
- Documents automatically submitted without manual action
- Order note added: "UPS Document Submission: [details]"
- Meta box shows submitted documents
- No admin interaction required

**Actual Results:** _[To be filled during testing]_

---

### TC-005: Document Resubmission
**Objective:** Test resubmitting documents for an order

**Steps:**
1. Use Order US-001 (already has submissions)
2. From Order actions, select "Resubmit UPS Documents"
3. Execute action

**Expected Results:**
- Existing documents deleted from UPS
- New documents generated and submitted
- New response IDs in meta box
- Order note shows resubmission details

**Actual Results:** _[To be filled during testing]_

---

### TC-006: Status Change Document Deletion
**Objective:** Verify documents are deleted when order status changes from completed

**Steps:**
1. Use a completed order with submitted documents
2. Change order status from "Completed" to "Processing"
3. Save order

**Expected Results:**
- Order note added about document deletion
- Archived PDFs deleted from server
- Documents deleted from UPS
- Meta box shows updated status

**Actual Results:** _[To be filled during testing]_

---

### TC-007: Error Handling - Missing Tracking Number
**Objective:** Test behavior when no tracking number exists

**Steps:**
1. Create order without tracking number
2. Try to submit documents via Order actions

**Expected Results:**
- Error message: "No tracking number found"
- No documents submitted
- No errors in system

**Actual Results:** _[To be filled during testing]_

---

### TC-008: Error Handling - Missing Template Configuration
**Objective:** Test submission when template IDs not configured

**Steps:**
1. Remove Commercial Invoice template ID from settings
2. Try to submit documents for international order

**Expected Results:**
- Error message about missing template configuration
- Admin email notification sent
- Error logged in meta box

**Actual Results:** _[To be filled during testing]_

---

### TC-009: Canada Order - No Documents Required
**Objective:** Verify Canada orders don't require customs documents

**Steps:**
1. Edit Order CA-001
2. Add tracking number
3. Try to submit documents

**Expected Results:**
- Message: "No customs documents required for this order"
- No submission attempts made
- No errors

**Actual Results:** _[To be filled during testing]_

---

### TC-010: International Order - Commercial Invoice Only
**Objective:** Test non-US international orders get commercial invoice only

**Steps:**
1. Edit Order INT-001 (UK shipping)
2. Add tracking number
3. Submit documents

**Expected Results:**
- Only Commercial Invoice submitted
- No CUSMA or other US-specific forms
- Success message for single document

**Actual Results:** _[To be filled during testing]_

---

### TC-011: Concurrent Operation Protection
**Objective:** Test protection against simultaneous operations

**Steps:**
1. Open same order in two browser tabs
2. In Tab 1: Start document submission
3. In Tab 2: Immediately try to submit documents
4. Wait for both to complete

**Expected Results:**
- Tab 2 shows warning: "Document submission already in progress"
- No duplicate submissions
- No database conflicts

**Actual Results:** _[To be filled during testing]_

---

### TC-012: Archive Storage Verification
**Objective:** Verify PDFs are properly archived

**Steps:**
1. Submit documents for any order
2. Check archive directory: `/wp-content/uploads/customs-documents/YYYY/MM/`
3. Verify file naming convention

**Expected Results:**
- PDFs stored in year/month structure
- Files named: `order-[ID]-[type]-[timestamp].pdf`
- Files are valid PDFs
- .htaccess prevents direct access

**Actual Results:** _[To be filled during testing]_

---

### TC-013: Email Notification on Failure
**Objective:** Test admin email notification for failures

**Steps:**
1. Temporarily break UPS API credentials
2. Try to submit documents
3. Check admin email

**Expected Results:**
- Email sent to admin within 1 minute
- Email contains:
  - Order number
  - Document type that failed
  - Error message
  - Link to order edit page

**Actual Results:** _[To be filled during testing]_

---

### TC-014: Performance Test
**Objective:** Verify submission completes within acceptable time

**Steps:**
1. Create order requiring all 5 document types
2. Time the submission process
3. Monitor server resources

**Expected Results:**
- Total submission time < 10 seconds
- No timeout errors
- Server responds normally

**Actual Results:** _[To be filled during testing]_

---

### TC-015: UPS API Response Validation
**Objective:** Verify correct handling of UPS responses

**Steps:**
1. Enable debug logging
2. Submit documents
3. Check logs for API responses

**Expected Results:**
- Response IDs extracted correctly
- Proper error handling for API failures
- All responses logged

**Actual Results:** _[To be filled during testing]_

## Edge Cases

### EC-001: Special Characters in Product Names
Test with products containing:
- Apostrophes (LED's)
- Ampersands (R&D Module)
- Unicode characters (LED™)

### EC-002: Large Orders
Test with orders containing:
- 50+ line items
- Multiple CUSMA-eligible products
- Mixed document requirements

### EC-003: Network Interruption
- Start submission
- Disconnect network briefly
- Verify retry mechanism

## Post-Test Verification

### Database Integrity
```sql
-- Check for orphaned records
SELECT * FROM wp_quad_dtm_ups_document_submissions 
WHERE order_id NOT IN (SELECT ID FROM wp_posts WHERE post_type = 'shop_order');

-- Verify status consistency
SELECT order_id, document_type, submission_status, COUNT(*) 
FROM wp_quad_dtm_ups_document_submissions 
GROUP BY order_id, document_type, submission_status 
HAVING COUNT(*) > 1;
```

### File System Check
```bash
# Check archive directory size
du -sh wp-content/uploads/customs-documents/

# Find orphaned PDFs
find wp-content/uploads/customs-documents/ -name "*.pdf" -mtime +30
```

## Test Summary

| Test Case | Status | Notes |
|-----------|--------|-------|
| TC-001 | ⏳ Pending | |
| TC-002 | ⏳ Pending | |
| TC-003 | ⏳ Pending | |
| TC-004 | ⏳ Pending | |
| TC-005 | ⏳ Pending | |
| TC-006 | ⏳ Pending | |
| TC-007 | ⏳ Pending | |
| TC-008 | ⏳ Pending | |
| TC-009 | ⏳ Pending | |
| TC-010 | ⏳ Pending | |
| TC-011 | ⏳ Pending | |
| TC-012 | ⏳ Pending | |
| TC-013 | ⏳ Pending | |
| TC-014 | ⏳ Pending | |
| TC-015 | ⏳ Pending | |

**Legend:**
- ✅ Passed
- ❌ Failed
- ⚠️ Passed with issues
- ⏳ Pending
- 🚫 Blocked

## Known Issues & Workarounds

_[To be documented during testing]_

## Sign-off

- **Tester:** _________________
- **Date:** _________________
- **Environment:** _________________
- **Version Tested:** _________________
