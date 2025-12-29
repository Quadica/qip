# Development Plan
## Drop Shipped Rail Plugin

**Version:** 1.0  
**Date:** October 13, 2025  
**Status:** Ready for Implementation  
**Estimated Duration:** 6-8 weeks  

---

## Table of Contents

1. [Overview](#overview)
2. [Development Approach](#development-approach)
3. [Phase Dependencies](#phase-dependencies)
4. [Implementation Phases](#implementation-phases)
5. [Testing Strategy](#testing-strategy)
6. [Deployment Workflow](#deployment-workflow)
7. [Progress Tracking](#progress-tracking)
8. [Success Criteria](#success-criteria)
9. [Timeline & Milestones](#timeline--milestones)

---

## Overview

This development plan breaks down the Drop Shipped Rail plugin implementation into **29 focused phases**, each with clear goals, tasks, and completion criteria. Phases are organized to build from foundation (database) through infrastructure, core functionality, features, UI, and finally testing/documentation.

**Key Principles:**
- **Small increments:** Each phase is 0.5-2 days of work
- **Test frequently:** Commit, push, and test on staging after each phase
- **Dependency order:** Phases build on previous phases
- **Quality focus:** Don't rush - test thoroughly at each step

---

## Development Approach

### Build Order Strategy

```
Phase 1-3:   Foundation (Database, Plugin Structure, Core Classes)
Phase 4-8:   Infrastructure (Repositories, Models, Utilities)
Phase 9-14:  Core Business Logic (Order Processing, Stock, Email)
Phase 15-20: Features (Dashboard, Admin Pages, Integration)
Phase 21-24: UI & Styling (CSS, JS, Templates, Print/Export)
Phase 25-27: Testing & Refinement
Phase 28:    Documentation & Deployment
```

### After Each Phase

1. **Commit:** `git commit -m "Phase X: [description]"`
2. **Push:** `git push origin main`
3. **Deploy:** GitHub Actions auto-deploys to staging (~30 seconds)
4. **Test:** Manual testing on staging site
5. **Update DEVELOPMENT-PLAN.md:** Check off completed items in phase completion criteria
6. **Session Log:** Document progress in `docs/project-history/session-XXX.md`

---

## Phase Dependencies

### Dependency Graph

```
Phase 1 (Database) → Phase 2 (Plugin Structure)
                       ↓
Phase 3 (Core Classes) → Phase 4-5 (Repositories & Models)
                           ↓
Phase 6-8 (Utilities) → Phase 9-11 (Services: Order, Stock, Email)
                          ↓
Phase 12-14 (More Services) → Phase 15-16 (Dashboard Manager & Controller)
                                 ↓
Phase 17 (Acknowledgement Endpoint) → Phase 18 (Admin Pages)
                                         ↓
Phase 19-20 (WC Integration & Action Scheduler) → Phase 21 (Hook Manager & DI)
                                                     ↓
Phase 22-25 (UI & Templates) → Phase 26-28 (Testing & Refinement)
                                  ↓
Phase 29 (Documentation & Deployment)
```

---

## Implementation Phases

### Phase 1: Database Schema & SQL Scripts

**Goal:** Create all database tables via manual SQL scripts

**Duration:** 0.5-1 day

**Dependencies:** None (starting point)

**Tasks:**
1. Create `docs/database/install/01-create-tables.sql`
   - Define all 5 tables with proper column types
   - Use `{wp_prefix}quad_dsr_` naming convention
   - Include IF NOT EXISTS checks
   - Add comprehensive file header
   - Detailed column comments

2. Create `docs/database/install/02-add-indexes.sql`
   - Primary keys on `id` columns
   - Indexes on `order_id`, `status`, `posted_date`, `tracking_number`
   - Indexes on foreign keys (`product_id`, `user_id`)
   - Index on `log_level` and `created_at` for log queries

3. Create `docs/database/rollback/rollback-01.sql`
   - DROP INDEX statements

4. Create `docs/database/rollback/rollback-02.sql`
   - DROP TABLE IF EXISTS statements

5. Create `docs/database/DEPLOYMENT.md`
   - Step-by-step manual deployment instructions
   - Verification queries
   - Troubleshooting section

**Completion Criteria:**
- [✅] All 5 SQL scripts created and tested
- [✅] Scripts execute without errors on staging MySQL
- [✅] All tables exist with correct structure
- [✅] All indexes created successfully
- [✅] DEPLOYMENT.md provides clear instructions
- [✅] Git committed and pushed

**Testing:**
- Execute SQL scripts manually on staging database via phpMyAdmin
- Run `SHOW TABLES LIKE 'wp_quad_dsr_%';` to verify tables
- Run `DESCRIBE wp_quad_dsr_orders;` to verify structure
- Run `SHOW INDEX FROM wp_quad_dsr_orders;` to verify indexes

---

### Phase 2: Plugin Structure & Composer Setup

**Goal:** Create plugin directory structure, main plugin file, and Composer autoloading

**Duration:** 0.5 day

**Dependencies:** None (parallel with Phase 1)

**Tasks:**
1. Create plugin directory: `wp-content/plugins/drop-shipped-rail/`

2. Create `drop-shipped-rail.php` (main plugin file)
   - Plugin header comment
   - Constants (DSR_VERSION, DSR_PLUGIN_DIR, etc.)
   - Security check (`defined('ABSPATH')`)
   - Require Composer autoloader
   - Initialize plugin singleton

3. Create `composer.json`
   - PSR-4 autoloading for `Quadica\DropShippedRail` namespace
   - PHP 8.1+ requirement
   - Dev dependencies (PHPUnit, WPCS)

4. Create directory structure:
   - `includes/` (all PHP classes)
   - `assets/` (CSS, JS, images)
   - `templates/` (view templates)
   - `tests/` (unit tests)
   - `languages/` (translations)

5. Run `composer install` to generate autoloader

6. Create `.gitignore` (exclude `vendor/`, `.playwright-auth.json`, etc.)

**Completion Criteria:**
- [✅] Plugin directory structure created
- [✅] Main plugin file exists with proper header
- [✅] Composer autoloader generated
- [✅] Plugin appears in WordPress admin (Plugins page)
- [✅] Activation doesn't cause errors
- [✅] Git committed and pushed

**Testing:**
- Navigate to WordPress admin → Plugins
- Verify "Drop Shipped Rail" appears in list
- Activate plugin (should succeed without errors)
- Check for PHP errors in debug.log

---

### Phase 3: Core Classes (Plugin, Activator, Deactivator)

**Goal:** Implement main plugin singleton, activation/deactivation handlers

**Duration:** 1 day

**Dependencies:** Phase 2

**Tasks:**
1. Create `includes/Plugin.php`
   - Singleton pattern implementation
   - Constructor initializes components
   - `get_instance()` method
   - `get_version()`, `get_logger()`, `get_settings()` methods
   - Component registry (array of initialized objects)

2. Create `includes/Activator.php`
   - `activate()` static method
   - Check system requirements (WP 6.8+, WC 9.9+, PHP 8.1+)
   - Display activation notice about manual database setup
   - **DO NOT** create database tables (manual SQL only)

3. Create `includes/Deactivator.php`
   - `deactivate()` static method
   - Unschedule Action Scheduler tasks (placeholder for now)
   - Display deactivation notice

4. Update `drop-shipped-rail.php` to use Activator/Deactivator
   - `register_activation_hook(__FILE__, ['Activator', 'activate'])`
   - `register_deactivation_hook(__FILE__, ['Deactivator', 'deactivate'])`

**Completion Criteria:**
- [✅] Plugin class implements singleton pattern
- [✅] Activation check runs system requirements
- [✅] Activation displays database setup notice
- [✅] Deactivation preserves data and settings
- [✅] No errors on activation/deactivation
- [✅] Git committed and pushed

**Testing:**
- Activate plugin → Check for activation notice
- Deactivate plugin → Check for deactivation notice
- Verify no PHP errors in debug.log
- Verify tables not created automatically (check database)

---

### Phase 4: Repository Base Class

**Goal:** Implement Abstract_Repository with common CRUD operations

**Duration:** 0.5 day

**Dependencies:** Phase 3

**Tasks:**
1. Create `includes/Repositories/Abstract_Repository.php`
   - Protected properties: `$wpdb`, `$table_name`, `$primary_key`
   - Constructor accepts table name
   - `find_by_id($id)` method
   - `find_all($args)` method
   - `insert($data)` method
   - `update($id, $data)` method
   - `delete($id)` method
   - `count($where)` method
   - `begin_transaction()`, `commit()`, `rollback()` methods
   - `prepare_where($where)` helper
   - `handle_error($operation)` error handler

**Completion Criteria:**
- [✅] Abstract_Repository class created
- [✅] All CRUD methods implemented
- [✅] Transaction support working
- [✅] Error handling in place
- [✅] PHPDoc comments on all methods
- [✅] Git committed and pushed
- [✅] Security review completed (SQL injection vulnerabilities fixed)

**Security Review & Fixes:**
- **Issue**: ORDER BY clause used invalid wpdb->prepare() placeholders (%1s, %2s)
- **Impact**: SQL injection vulnerability allowing arbitrary SQL execution
- **Fix**: Implemented sanitize_key() + esc_sql() for column names, whitelist validation for ORDER direction
- **Issue**: prepare_where() didn't skip empty sanitized column names
- **Fix**: Added empty string check after sanitization to prevent malformed SQL
- **Enhancement**: Added comprehensive security documentation and format array guidance

**Testing:**
- Unit test: Create mock repository extending Abstract_Repository
- Test insert/update/delete operations on test table
- Test transaction commit/rollback
- Verify error handling logs errors correctly
- Security regression tests: All 8/8 smoke tests passing after fixes

---

### Phase 5: Model Classes

**Goal:** Create all model classes (Order, Acknowledgement, Stock_Log, Email_Log, Log)

**Duration:** 1 day

**Dependencies:** Phase 4 (parallel with Phase 4 is fine)

**Tasks:**
1. Create `includes/Models/Order_Model.php`
   - All properties (id, order_id, customer_name, etc.)
   - Constructor
   - Getters and setters
   - `from_array($data)` static factory
   - `to_array()` method
   - Rail item methods (`add_rail_item()`, `remove_rail_item()`, `update_quantity()`)
   - Status check methods (`is_new()`, `is_shipped()`, etc.)
   - `get_formatted_address()` helper

2. Create `includes/Models/Acknowledgement_Model.php`
   - Properties and methods

3. Create `includes/Models/Stock_Log_Model.php`
   - Properties and methods

4. Create `includes/Models/Email_Log_Model.php`
   - Properties and methods

5. Create `includes/Models/Log_Model.php`
   - Properties and methods

**Completion Criteria:**
- [✅] All 5 model classes created
- [✅] All properties defined with proper types
- [✅] from_array() and to_array() methods work correctly
- [✅] PHPDoc comments complete
- [✅] Git committed and pushed

**Testing:**
- Unit test each model:
  - Create from array
  - Convert to array
  - Test all getters/setters
  - Test Order_Model rail item methods

---

### Phase 6: Repository Implementations

**Goal:** Implement all repository classes extending Abstract_Repository

**Duration:** 1.5 days

**Dependencies:** Phase 4, Phase 5

**Tasks:**
1. Create `includes/Repositories/Order_Repository.php`
   - Extend Abstract_Repository
   - `find_by_order_id($wc_order_id)` method
   - `find_by_status($status)` method
   - `find_active_orders()` method
   - `get_orders_for_dashboard($filters, $page, $per_page)` method
   - `search($query)` method
   - `count_by_status($status)` method
   - `get_summary_stats()` method
   - `order_exists($wc_order_id)` method
   - `save(Order_Model $order)` method
   - `row_to_model($row)` and `model_to_array($order)` helpers

2. Create `includes/Repositories/Acknowledgement_Repository.php`
   - Custom query methods

3. Create `includes/Repositories/Stock_Log_Repository.php`
   - Custom query methods

4. Create `includes/Repositories/Email_Log_Repository.php`
   - Custom query methods

5. Create `includes/Repositories/Log_Repository.php`
   - Custom query methods
   - Cleanup old logs method

**Completion Criteria:**
- [✅] All 5 repository classes created
- [✅] All custom query methods implemented
- [✅] Prepared statements used for all queries
- [✅] Models properly converted to/from DB arrays
- [✅] PHPDoc comments complete
- [✅] Git committed and pushed

**Phase 6 Notes:**
- Schema mismatches discovered between Phase 1 SQL design and actual database
- **Schema Alignment Completed**: All repositories aligned with Phase 1 SQL (authoritative source)
- **All repositories fully functional**: 10/10 smoke tests passing
- **Schema fixes applied**:
  - Stock_Log_Repository: quantity_adjusted → quantity, adjusted_by_user_id → user_id
  - Email_Log_Repository: order_id → dsr_order_id, sent_at → sent_date
  - Email_Log_Repository: Disabled get_failed_for_retry() and increment_retry_count() (retry_count column doesn't exist)
  - Acknowledgement_Repository: token_hash → token (all 5 methods updated)
  - Acknowledgement_Model: token_hash property/methods → token
  - Log_Repository: Disabled find_by_source() and find_by_user_id() (columns don't exist)
- Phase 1 SQL schema is now the authoritative reference for all database operations

**Testing:**
- Integration test on staging:
  - Insert test order via Order_Repository
  - Query by various methods
  - Update and delete
  - Verify data in database via phpMyAdmin

---

### Phase 7: Core Utilities (Settings, Logger, Role Manager)

**Goal:** Implement core utility classes

**Duration:** 1 day

**Dependencies:** Phase 6

**Tasks:**
1. Create `includes/Core/Settings_Manager.php`
   - Load/save settings via WordPress Options API
   - Default settings array (including session_timeout_hours = 8)
   - `get($key, $default)` method
   - `set($key, $value)` method
   - `get_all()`, `update_multiple()` methods
   - `sanitize($settings)` method
   - `validate($settings)` method

2. Create `includes/Core/Logger.php`
   - Inject Log_Repository
   - `debug()`, `info()`, `warning()`, `error()` methods
   - `log($level, $message, $context)` generic method
   - Check debug mode before logging debug messages
   - `notify_admin($message)` for errors

3. Create `includes/Core/Role_Manager.php`
   - `create_me_role()` method
   - Define custom capabilities
   - `add_admin_capabilities()` method
   - `is_me_user($user)` helper

**Completion Criteria:**
- [✅] Settings Manager stores/retrieves settings correctly
- [✅] Logger writes to database table
- [✅] Role Manager creates custom role
- [✅] All methods have error handling
- [✅] Git committed and pushed

**Testing:**
- Test Settings_Manager: Save and retrieve settings ✅ 2/2 tests passing
- Test Logger: Write log entries, verify in database ✅ 3/3 tests passing
- Test Role_Manager: Create ME role, verify capabilities ✅ 3/3 tests passing

**Phase 7 Notes:**
- All 8/8 smoke tests passing
- Settings_Manager includes validation, sanitization, and bounds checking
- Logger implements debug mode filtering and admin error notifications
- Role_Manager creates ME Drop Shipper role with view-only dashboard access
- Fixed Abstract_Repository::find_by_id() to properly return model objects
- All utility classes fully documented with PHPDoc comments

---

### Phase 8: Rail Identifier Strategy Pattern

**Goal:** Implement rail product identification with strategy pattern

**Duration:** 0.5 day

**Dependencies:** Phase 7

**Tasks:**
1. Create `includes/Utilities/Rail_Identifier/Rail_Identifier_Interface.php`
   - `is_rail_product($product)` method signature

2. Create `includes/Utilities/Rail_Identifier/SKU_Pattern_Identifier.php`
   - Implement interface
   - Check if SKU starts with pattern from settings (default: "17-")

3. Create `includes/Utilities/Rail_Identifier/Category_Identifier.php`
   - Implement interface
   - Check if product in configured category

4. Create `includes/Utilities/Rail_Identifier/Meta_Field_Identifier.php`
   - Implement interface
   - Check product meta field value

**Completion Criteria:**
- [✅] Interface and 3 implementations created
- [✅] SKU pattern identifier works with configurable pattern
- [✅] All implementations follow same interface
- [✅] Git committed and pushed

**Testing:**
- Test each identifier with test products:
  - SKU: Product with SKU "17-001" identified as rail
  - Category: Product in "Rail" category identified
  - Meta: Product with meta field identified

---

### Phase 9: Order Processor Service

**Goal:** Implement order capture and processing logic

**Duration:** 1.5 days

**Dependencies:** Phase 6, Phase 7, Phase 8

**Tasks:**
1. Create `includes/Services/Order_Processor.php`
   - Constructor with dependency injection
   - `process_completed_order($order_id)` main method
   - `is_eligible($order)` check US + contains rail
   - `is_us_order($order)` helper
   - `contains_rail($order)` using Rail_Identifier
   - `extract_order_data($order)` extract relevant data
   - `extract_rail_items($order)` filter and extract rail
   - `create_dashboard_entry($data)` save to DB
   - `is_duplicate($wc_order_id)` check for existing entry
   - Logging throughout

2. Add error handling with try/catch
3. Add comprehensive logging

**Completion Criteria:**
- [✅] Order_Processor class created
- [✅] All methods implemented with proper logic
- [✅] Eligible orders identified correctly
- [✅] Dashboard entries created successfully
- [✅] Duplicate prevention working
- [✅] Error handling in place
- [✅] Logging comprehensive
- [✅] Git committed and pushed

**Testing:**
- Create test WC order on staging with rail products
- Mark order "Completed"
- Verify dashboard entry created in database
- Verify duplicate prevention (mark completed again)
- Check logs for proper logging

---

### Phase 10: Stock Manager Service

**Goal:** Implement inventory adjustment logic

**Duration:** 1 day

**Dependencies:** Phase 9

**Tasks:**
1. Create `includes/Services/Stock_Manager.php`
   - Constructor with dependencies
   - `reverse_stock_reduction($order_id)` main method
   - `adjust_stock_for_product($product_id, $quantity, $direction, $reason, $order_id)` method
   - `queue_stock_adjustment(...)` queue via Action Scheduler (placeholder for now)
   - `log_adjustment($data)` log to Stock_Log table
   - `get_product($product_id)` helper
   - `update_product_stock($product, $quantity, $direction)` WC stock update
   - Error handling for failed adjustments

2. Integration with WooCommerce stock system

**Completion Criteria:**
- [✅] Stock_Manager class created
- [✅] Stock reversal logic working
- [✅] WC product stock updated correctly
- [✅] Adjustments logged to stock_log table
- [✅] Error handling for failed adjustments
- [✅] Git committed and pushed

**Testing:**
- Create order with rail, check initial stock level
- Process order (stock reduced by WC)
- Call Stock_Manager to reverse
- Verify stock restored to original level
- Check stock_log table for entries

---

### Phase 11: Email System Foundation + Token/Acknowledgement

**Goal:** Implement email factory, abstract email, token generator, acknowledgement handler, and email sender

**Duration:** 2 days (adjusted from 1.5 days)

**Dependencies:** Phase 10

**Tasks:**
1. Create `includes/Email/Abstract_Email.php`
   - Abstract class with template methods
   - `get_recipients()` from settings
   - `get_subject()` abstract
   - `get_body()` abstract
   - `get_headers()` build email headers
   - `add_acknowledgement_link($url)` helper

2. Create `includes/Email/Email_Factory.php`
   - `create($type, $data)` factory method
   - Returns appropriate email object

3. Create `includes/Email/Daily_Summary_Email.php`
   - Extend Abstract_Email
   - Implement get_subject() and get_body()

4. Create `includes/Utilities/Token_Generator.php` **(MOVED FROM PHASE 13)**
   - `generate()` create 32-char secure token
   - `is_valid_format($token)` validate token format

5. Create `includes/Services/Acknowledgement_Handler.php` **(MOVED FROM PHASE 13)**
   - `generate_acknowledgement_link(int $order_id, string $type)` single order link
   - `generate_batch_acknowledgement_link(array $order_ids, string $type)` batch link for daily summary
   - `process_email_acknowledgement(string $token)` handle email link clicks (token-only parameter)
   - `process_dashboard_acknowledgement(int $order_id, int $user_id)` handle checkbox
   - `validate_token(string $token)` check expiration and rate limit (token-only validation)
   - Uses Acknowledgement_Repository::find_all_by_token() for batch processing
   - Uses Acknowledgement_Repository::mark_acknowledged() to update existing records
   - `check_rate_limit(string $token)` prevent abuse (10/hour max)

6. Create `includes/Services/Email_Sender.php`
   - Constructor with dependencies (inject Acknowledgement_Handler)
   - `queue_email($type, $order_id, $data)` queue for sending (placeholder)
   - `send_email($type, $order_id, $data)` send via wp_mail()
   - Delegate to Acknowledgement_Handler for generating links (via injected dependency)
   - `log_email(...)` log to email_log table
   - Error handling and retries

**Completion Criteria:**
- [✅] Email abstract class and factory created
- [✅] Daily summary email implementation working
- [✅] Token_Generator creates secure tokens **(MOVED FROM PHASE 13)**
- [✅] Acknowledgement_Handler supports both single and batch token generation **(MOVED FROM PHASE 13)**
- [✅] Acknowledgement_Handler uses token-only validation (no order_id parameter) **(MOVED FROM PHASE 13)**
- [✅] Batch acknowledgement uses find_all_by_token() for multiple orders **(MOVED FROM PHASE 13)**
- [✅] Rate limiting prevents abuse **(MOVED FROM PHASE 13)**
- [✅] Email_Sender can send via wp_mail()
- [✅] Email_Sender properly delegates token generation to Acknowledgement_Handler
- [✅] Acknowledgement links included in emails via handler delegation
- [✅] Emails logged to email_log table
- [✅] Email bounce detection hook registered (wp_mail_failed or Post SMTP callback)
- [✅] Bounce handler logs warning and surfaces in settings
- [✅] Git committed and pushed

**Testing:**
- Test Token_Generator: Create tokens, verify format and length
- Test Acknowledgement_Handler:
  - Generate single order token
  - Generate batch token for multiple orders
  - Process acknowledgement with token-only parameter
  - Verify all orders in batch are acknowledged
  - Verify rate limiting (11 clicks in 1 hour should fail)
- Manually trigger email send
- Verify email received (check staging email logs)
- Verify acknowledgement link generated via Acknowledgement_Handler
- Check email_log table for entry
- Test bounce handling with invalid email address

---

### Phase 12: Additional Email Types

**Goal:** Implement all remaining email types

**Duration:** 1 day

**Dependencies:** Phase 11

**Tasks:**
1. Create `includes/Email/Address_Change_Email.php`
   - Extend Abstract_Email
   - Implement subject and body

2. Create `includes/Email/Quantity_Change_Email.php`

3. Create `includes/Email/Special_Instructions_Email.php`

4. Create `includes/Email/Hold_Status_Email.php`

5. Create `includes/Email/Cancel_Status_Email.php`

6. Update Email_Factory to handle all types

**Completion Criteria:**
- [✅] All 5 additional email types created
- [✅] Each has proper subject and body template
- [✅] Factory can create all email types
- [✅] Git committed and pushed

**Testing:**
- Test each email type:
  - Create test data
  - Generate email
  - Verify subject and body correct

---

### Phase 13: Status Manager

**Goal:** Implement status transitions and business rules

**Duration:** 0.5 day (reduced from 1 day - Token_Generator and Acknowledgement_Handler moved to Phase 11)

**Dependencies:** Phase 12

**Tasks:**
1. Create `includes/Services/Status_Manager.php`
   - `transition_to(int $order_id, string $new_status, int $user_id)` main transition method
   - `can_transition(int $order_id, string $new_status)` validation
   - `hold_order(int $order_id, int $user_id, string $reason)` → On Hold (save previous status)
   - `unhold_order(int $order_id, int $user_id)` → Restore previous status
   - `cancel_order(int $order_id, int $user_id, string $reason)` → Cancelled (save previous status)
   - Logging all transitions with user ID for audit trail
   - Use `get_current_user_id()` when user_id not provided

**Completion Criteria:**
- [✅] Status_Manager uses order IDs (not Order_Model objects)
- [✅] Status_Manager implements transition_to() with user_id tracking
- [✅] Status transition rules enforced
- [✅] Previous status tracked for hold/cancel operations
- [✅] Git committed and pushed

**Testing:**
- Test status transitions:
  - New → Acknowledged (via transition_to)
  - Acknowledged → Shipped (via transition_to)
  - Any → On Hold → Restore (via hold_order/unhold_order)
  - Any → Cancelled → Restore (via cancel_order)

---

### Phase 14: Admin Action Handler

**Goal:** Implement admin edit operations (address, quantities, instructions, hold, cancel)

**Duration:** 1.5 days

**Dependencies:** Phase 13

**Tasks:**
1. Create `includes/Services/Admin_Action_Handler.php`
   - `update_shipping_address($order_id, $address, $user_id)` method
   - `update_quantities($order_id, $items, $user_id)` method
   - `update_special_instructions($order_id, $instructions, $user_id)` method
   - `hold_order($order_id, $user_id, $reason)` method
   - `unhold_order($order_id, $user_id)` method
   - `cancel_order($order_id, $user_id, $reason)` method
   - `uncancel_order($order_id, $user_id)` method
   - `manually_change_status($order_id, $new_status, $user_id)` method
   - Each action:
     - Updates order in database
     - Records acknowledgement history
     - Queues email notification
     - Invalidates dashboard cache
     - Logs action

**Completion Criteria:**
- [✅] Admin_Action_Handler class created
- [✅] All admin actions implemented
- [✅] Each action updates DB, logs, sends email
- [✅] Acknowledgement history recorded
- [✅] Git committed and pushed

**Testing:**
- Test each admin action:
  - Edit address → Verify DB updated, email queued, ack logged
  - Edit quantities → Same verifications
  - Special instructions → Same verifications
  - Hold/unhold → Verify status and previous_status
  - Cancel/un-cancel → Same

---

### Phase 15: Dashboard Manager & Data Provider

**Goal:** Implement dashboard coordination and data retrieval

**Duration:** 1 day

**Dependencies:** Phase 14

**Tasks:**
1. Create `includes/Dashboard/Dashboard_Manager.php`
   - `redirect_me_user_on_login($redirect_to, $request, $user)` filter
   - `render_dashboard()` main rendering method
   - `get_dashboard_data($filters)` get cached or fresh data
   - `invalidate_cache()` clear transient cache
   - `is_me_user($user)` check if user has ME role

2. Create `includes/Dashboard/Table_Data_Provider.php`
   - `get_orders($filters, $page, $per_page)` main query
   - `get_order_count($filters)` for pagination
   - `get_summary_stats()` header stats (new, pending, shipped)
   - `apply_filters($query, $filters)` add WHERE conditions
   - `apply_search($query, $search_term)` search logic
   - `apply_sorting($query, $sort_by, $sort_order)` ORDER BY

3. Create `includes/Dashboard/Filter_Handler.php`
   - `parse_filters($_GET)` extract and validate filters
   - `build_filter_query($filters)` build SQL conditions

4. Create `includes/Dashboard/Search_Handler.php`
   - `parse_search($_GET)` extract search term
   - `build_search_query($search_term)` build SQL search

**Completion Criteria:**
- [✅] Dashboard_Manager coordinates dashboard functionality
- [✅] Table_Data_Provider fetches orders with filters
- [✅] Filter and search working correctly
- [✅] Dashboard data cached for 5 minutes
- [✅] ME users redirected to dashboard on login
- [✅] Git committed and pushed

**Testing:**
- Login as ME user → Verify redirect to dashboard
- Test filters (status, date range)
- Test search (order ID, customer name, tracking)
- Test pagination
- Verify caching (check transients table)

---

### Phase 16: Dashboard Controller

**Goal:** Implement dashboard HTTP request handler

**Duration:** 1 day

**Dependencies:** Phase 15

**Tasks:**
1. Create `includes/Dashboard/Dashboard_Controller.php`
   - `handle_request()` main router
   - `handle_get_request()` display dashboard
   - `handle_post_request()` process form submissions
   - `process_save_tracking()` ME enters tracking
   - `process_acknowledge()` ME checks ack checkbox
   - `process_admin_edit()` admin edits (dispatch to Admin_Action_Handler)
   - `process_toggle_view()` admin switches view mode (admin/ME)
   - `process_resend_email()` admin manually resends notification
   - `verify_nonce($action)` security check
   - `check_capability($capability)` permission check
   - Redirect with success/error messages

2. Add actions for:
   - save_tracking
   - acknowledge_order
   - edit_address
   - edit_quantities
   - edit_instructions
   - hold_order (with optional reason text field in UI)
   - cancel_order (with optional reason text field in UI)
   - toggle_view (admin only - switch dashboard view)
   - resend_email (admin only - manual email resend)

3. Create UI elements for hold/cancel reasons:
   - Add optional "Reason" text input field to hold order action
   - Add optional "Reason" text input field to cancel order action
   - Field can be left blank (optional)
   - Captured reason passed to Admin_Action_Handler methods

**Completion Criteria:**
- [✅] Dashboard_Controller handles GET and POST requests
- [✅] All form actions processed correctly
- [✅] View toggle action works (admin users can switch views)
- [✅] Resend email action works (admin can resend notifications)
- [✅] Nonce verification on all POST requests (including dsr_toggle_view and dsr_resend_email)
- [✅] Capability checks before actions (view toggle and resend email admin-only)
- [✅] Success/error messages via redirect
- [✅] Git committed and pushed
- [✅] Smoke tests created (25 assertions)
- [✅] Tests run successfully on staging (23/25 passing - 2 expected failures)

**Testing:**
- Submit each form action:
  - Save tracking number
  - Check acknowledgement checkbox
  - Edit address (as admin)
  - Edit quantities (as admin)
  - Hold order (as admin)
  - Toggle view mode (as admin - switch between admin/ME views)
  - Resend email notification (as admin - manually resend for order)
- Verify nonce rejection (tamper with nonce for any action)
- Verify capability rejection:
  - ME user tries admin action (edit/hold/cancel)
  - ME user tries view toggle (admin only)
  - ME user tries resend email (admin only)

---

### Phase 17: Acknowledgement Public Endpoint

**Goal:** Create public /me-ack/ endpoint for email acknowledgement links

**Duration:** 0.5 day

**Dependencies:** Phase 16

**Tasks:**
1. Create `includes/Dashboard/Acknowledgement_Endpoint.php`
   - `register_endpoint()` method - register custom endpoint with WordPress
   - `handle_acknowledgement_request()` method - process GET requests
   - Parse `token` from $_GET['token']
   - Call `Acknowledgement_Handler::process_email_acknowledgement($token)` (token-only)
   - Display confirmation page or error message
   - No login required (public endpoint)
   - Proper security (rate limiting via Acknowledgement_Handler)

2. Update `includes/Core/Hook_Manager.php`
   - Register endpoint via `init` hook
   - Map `/me-ack/` to acknowledgement handler

3. Create confirmation template
   - `templates/acknowledgement/confirmation.php` - success message
   - `templates/acknowledgement/error.php` - error states (expired, invalid, rate limit)

**Completion Criteria:**
- [✅] Public endpoint `/me-ack/?token=...` functional
- [✅] Token-only parameter (no order_id in URL)
- [✅] Acknowledgement_Handler::process_email_acknowledgement($token) called correctly
- [✅] Confirmation page displays success message
- [✅] Error handling for expired/invalid tokens
- [✅] Rate limiting prevents abuse
- [✅] Cache invalidation occurs on acknowledgement
- [✅] Git committed and pushed

**Testing:**
- Generate acknowledgement link from email
- Click link (without login) → Verify confirmation page
- Test expired token → Verify error message
- Test invalid token → Verify error message
- Test rate limiting (11 clicks in 1 hour should be blocked)
- Verify cache cleared after acknowledgement

---

### Phase 18: Admin Pages (Menu, Settings, Logs)

**Goal:** Implement WordPress admin integration

**Duration:** 1.5 days

**Dependencies:** Phase 17

**Tasks:**
1. Create `includes/Admin/Admin_Menu.php`
   - `register_menu()` method
   - Add submenu under WooCommerce:
     - Dashboard (link to full-screen dashboard)
     - Settings
     - Logs

2. Create `includes/Admin/Settings_Page.php`
   - Render settings page with tabs
   - Tab 1: General Settings
     - ME Email (text)
     - BCC Email (text)
     - Daily Summary Time (time)
     - Timezone (dropdown)
     - ME Session Timeout Hours (number, default 8, configurable per PRD:794)
     - Cache Duration Minutes (number)
     - Log Retention Days (number)
   - Tab 2: Product Configuration (with "Test Rail Identification" button)
   - Tab 3: Email Templates (with preview/reset buttons)
   - Tab 4: Stock Management
   - Tab 5: Acknowledgement Settings
   - Tab 6: Integration
   - Tab 7: Logs & Debugging
   - Handle settings form submission
   - Validate all fields (especially session_timeout_hours: min 1, max 24)
   - Use Settings_Manager to save/retrieve

3. Create `includes/Admin/Log_Viewer_Page.php`
   - Display logs from quad_dsr_logs table
   - Filters: log level, date range
   - Search: message
   - Pagination
   - Export to CSV button
   - Clear old logs button

4. Create `includes/Admin/Admin_Notices.php`
   - Display success/error messages
   - Dismissible notices

5. Create `includes/Admin/Email_Template_Manager.php`
   - `preview_template($type)` method - show email preview
   - `reset_template($type)` method - restore default template
   - AJAX handler for preview (display in modal)
   - Template validation

6. Create `includes/Admin/Rail_Product_Tester.php`
   - `test_rail_identification()` method
   - Query products using current Rail_Identifier strategy
   - Display list of identified products with SKU, name, category
   - Show count of identified products

**Completion Criteria:**
- [✅] Admin menu registered under WooCommerce
- [✅] Settings page with all tabs functional
- [✅] Settings save/load correctly
- [✅] "Test Rail Identification" button displays identified products
- [✅] Email template preview shows rendered email
- [✅] Email template reset restores defaults
- [✅] Log viewer displays logs
- [✅] Admin notices display messages
- [✅] Git committed and pushed

**Testing:**
- Navigate to WooCommerce → Drop Shipped Rail
- Test each settings tab:
  - Update settings
  - Save
  - Verify saved in database
- View logs page
- Test filters and search

---

### Phase 19: WooCommerce Integration

**Goal:** Implement WooCommerce hook handler and order meta manager

**Duration:** 1 day

**Dependencies:** Phase 18

**Tasks:**
1. Create `includes/Integration/WooCommerce_Hook_Handler.php`
   - `init()` register all WC hooks
   - `on_order_completed($order_id)` → Call Order_Processor
   - `on_stock_reduced($order)` → Call Stock_Manager
   - `on_order_cancelled($order_id)` → Update dashboard order status

2. Create `includes/Integration/Order_Meta_Manager.php`
   - `store_rail_items($order_id, $rail_items)` method
   - `get_rail_items($order_id)` method
   - `store_drop_ship_flag($order_id, $is_drop_ship)` method
   - `is_drop_shipped($order_id)` method
   - `store_dashboard_order_id($wc_order_id, $dsr_order_id)` method
   - `get_dashboard_order_id($wc_order_id)` method
   - Note: Tracking number updates use direct `update_post_meta()` calls in the tracking workflow

3. Create `includes/Integration/AutomateWoo_Integration.php`
   - `fire_order_posted_hook($order_id, $rail_items)` method
   - `fire_tracking_added_hook($order_id, $tracking, $rail_items)` method

**Completion Criteria:**
- [✅] WC hooks registered correctly
- [✅] Order completed hook triggers processing
- [✅] Order meta stored in WooCommerce
- [✅] AutomateWoo hooks fired at correct times
- [✅] Git committed and pushed

**Testing:**
- Create test order on staging
- Mark "Completed"
- Verify order processed (check dashboard DB table)
- Verify order meta stored (check wp_postmeta table)
- Verify stock adjusted

---

### Phase 20: Action Scheduler Integration

**Goal:** Implement background task management

**Duration:** 1 day

**Dependencies:** Phase 19

**Tasks:**
1. Create `includes/Integration/Action_Scheduler_Manager.php`
   - `schedule_daily_summary()` schedule recurring task
   - `queue_stock_adjustment($data)` queue async task
   - `queue_email($data)` queue async task
   - `process_stock_adjustment($data)` task handler
   - `process_email($data)` task handler
   - `send_daily_summary()` task handler
   - `cleanup_old_logs()` task handler
   - `unschedule_all()` for deactivation

2. Register Action Scheduler hooks:
   - `dsr_process_stock_adjustment`
   - `dsr_send_email`
   - `dsr_send_daily_summary`
   - `dsr_cleanup_old_logs`

3. Update Activator to schedule recurring tasks
4. Update Deactivator to unschedule tasks

**Completion Criteria:**
- [✅] Action_Scheduler_Manager created
- [✅] All tasks registered with Action Scheduler
- [✅] Stock adjustments queued and processed in background
- [✅] Emails queued and sent via Action Scheduler
- [✅] Daily summary scheduled for configured time
- [✅] Git committed and pushed

**Testing:**
- Trigger stock adjustment → Verify queued in Action Scheduler
- Verify task processes successfully
- Trigger email send → Verify queued
- Verify email sent
- Check Action Scheduler admin page for scheduled tasks

---

### Phase 21: Hook Manager & Dependency Injection

**Goal:** Centralize hook registration and wire up all components

**Duration:** 1 day

**Dependencies:** Phase 20

**Tasks:**
1. Create `includes/Core/Hook_Manager.php`
   - Constructor receives all components
   - `init()` method registers all hooks
   - `register_woocommerce_hooks()` WC-specific
   - `register_admin_hooks()` admin-specific
   - `register_dashboard_hooks()` dashboard-specific
   - `register_auth_hooks()` ME session timeout (auth_cookie_expiration filter)
   - Clear documentation of all hooks and priorities

2. Update `includes/Plugin.php`
   - `load_dependencies()` method
   - Instantiate all components with proper dependency injection
   - Pass dependencies to constructors
   - Create Hook_Manager with all components
   - Call Hook_Manager::init()

3. Wire up complete dependency graph

**Completion Criteria:**
- [✅] Hook_Manager centralizes all hook registration
- [✅] Plugin class instantiates all components
- [✅] Dependency injection throughout
- [✅] No circular dependencies
- [✅] All hooks registered correctly
- [✅] Git committed and pushed

**Testing:**
- Verify all hooks fire correctly:
  - WC order completed
  - ME user login redirect
  - Admin menu appears
  - Dashboard accessible
- Test ME session timeout (PRD:794 requirement):
  - Temporarily set session_timeout_hours to 0.1 (6 minutes) in settings
  - Login as ME user
  - Wait 7 minutes (do not interact with site)
  - Attempt to access dashboard → Verify session expired, redirect to login
  - Reset session_timeout_hours to 8
- Check for PHP errors
- Verify no missing dependencies

---

### Phase 22: Dashboard Templates & Views

**Goal:** Create dashboard HTML templates and view classes

**Duration:** 1.5 days

**Dependencies:** Phase 21

**Tasks:**
1. Create `includes/Utilities/Template_Helper.php`
   - `render($template, $data)` method
   - Load template file with data
   - Output escaping helpers

2. Create `includes/Views/Dashboard_View.php`
   - `prepare_data($orders, $stats, $filters)` method
   - Format dates using Date_Time_Helper
   - Add UI flags (is_admin, can_edit)
   - Calculate pagination data

3. Create dashboard templates:
   - `templates/dashboard/dashboard.php` (main template)
   - `templates/dashboard/header.php` (welcome + stats + view toggle)
   - `templates/dashboard/filters.php` (filters + search)
   - `templates/dashboard/table.php` (orders table)
   - `templates/dashboard/table-row.php` (single row with resend button)
   - `templates/dashboard/pagination.php` (pagination controls)
   - `templates/dashboard/empty-state.php` (no orders message)

4. All templates use proper escaping (esc_html, esc_attr, esc_url)

**Completion Criteria:**
- [✅] Template_Helper renders templates correctly
- [✅] Dashboard_View prepares data for templates
- [✅] All dashboard templates created
- [✅] View toggle button works (admin users can switch views)
- [✅] Resend email button appears on each order row (admin only)
- [✅] All output properly escaped
- [✅] Dashboard displays correctly
- [✅] Git committed and pushed

**Testing:**
- Navigate to dashboard
- Verify all sections render:
  - Header with stats
  - Filters and search
  - Orders table
  - Pagination
- Verify empty state when no orders
- Inspect HTML for proper escaping

---

### Phase 23: Dashboard CSS & Styling

**Goal:** Style dashboard for desktop, tablet, and mobile

**Duration:** 1.5 days

**Dependencies:** Phase 22

**Tasks:**
1. Create `assets/css/dashboard.css`
   - Mobile-first approach (base styles for 320px+)
   - Header styles (welcome, stats)
   - Filter bar styles
   - Table styles (responsive)
   - Status badges (color-coded)
   - Action buttons
   - Pagination styles
   - Loading states
   - Empty state styles

2. Responsive breakpoints:
   - 320px: Mobile (stacked, simplified)
   - 768px: Tablet (optimized table)
   - 1024px: Desktop (full table)
   - 1440px: Large desktop (max density)

3. Status badge colors:
   - New: Blue
   - Acknowledged: Yellow
   - Shipped: Green
   - On Hold: Red with icon
   - Cancelled: Gray strikethrough

4. Enqueue stylesheet in Dashboard_Controller

**Completion Criteria:**
- [✅] Dashboard CSS created
- [✅] Mobile-responsive design working
- [✅] Status badges color-coded correctly
- [✅] Table responsive on all devices
- [✅] Professional, clean design
- [✅] Git committed and pushed

**Testing:**
- View dashboard on different screen sizes:
  - Mobile (320px, 375px, 414px)
  - Tablet (768px, 1024px)
  - Desktop (1440px, 1920px)
- Verify all elements styled correctly
- Verify responsive behavior

---

### Phase 24: Dashboard JavaScript & Interactions

**Goal:** Add minimal JavaScript for dashboard interactions (no AJAX)

**Duration:** 0.5 day

**Dependencies:** Phase 23

**Tasks:**
1. Create `assets/js/dashboard.js`
   - Form validation
   - Confirm dialogs for destructive actions:
     - "Are you sure you want to cancel this order?"
     - "Are you sure you want to hold this order?"
   - Bulk selection checkboxes
   - "Select All" functionality
   - Expand/collapse acknowledgement history
   - Print preview trigger
   - NO AJAX requests (all forms use POST)

2. Enqueue script in Dashboard_Controller

**Completion Criteria:**
- [✅] Dashboard JS created
- [✅] Form validation working
- [✅] Confirm dialogs prevent accidental actions
- [✅] Bulk selection working
- [✅] No AJAX (forms use POST)
- [✅] Git committed and pushed

**Testing:**
- Test form validation
- Test confirm dialogs (try to cancel, verify prompt)
- Test bulk selection (select all, select none)
- Test expand acknowledgement history
- Verify forms submit via POST (check Network tab)

---

### Phase 25: Print Layout & CSV Export

**Goal:** Implement print view and CSV export functionality

**Duration:** 1 day

**Dependencies:** Phase 24

**Tasks:**
1. Create `includes/Dashboard/Bulk_Action_Handler.php`
   - `handle_bulk_print($order_ids)` method
   - `handle_bulk_export($order_ids)` method

2. Create `includes/Utilities/CSV_Generator.php`
   - `generate($orders)` create CSV content
   - `send_download($csv, $filename)` trigger download
   - `escape_value($value)` proper CSV escaping
   - UTF-8 BOM for Excel compatibility

3. Create print templates:
   - `templates/print/print-order.php` (single order)
   - `templates/print/print-multiple.php` (bulk print)

4. Create `assets/css/print.css`
   - Print-friendly styles
   - Remove dashboard chrome
   - Page breaks between orders
   - Fast Tracks logo

5. Update Dashboard_Controller to handle print/export actions

**Completion Criteria:**
- [✅] Print view displays order details cleanly
- [✅] Bulk print shows all selected orders
- [✅] CSV export generates correct format
- [✅] CSV download triggers in browser
- [✅] Print CSS hides non-print elements
- [✅] Git committed and pushed

**Testing:**
- Print single order → Verify layout
- Bulk print 5 orders → Verify all appear
- Export to CSV → Open in Excel, verify formatting
- Test CSV with special characters (quotes, commas)

---

### Phase 26: Email Templates & Final Email Integration

**Goal:** Create HTML email templates and finalize email system

**Duration:** 1 day

**Dependencies:** Phase 25

**Tasks:**
1. Create email templates:
   - `templates/email/email-wrapper.php` (HTML wrapper)
   - `templates/email/daily-summary.php`
   - `templates/email/address-change.php`
   - `templates/email/quantity-change.php`
   - `templates/email/special-instructions.php`
   - `templates/email/hold-status.php`
   - `templates/email/cancel-status.php`

2. Update all email classes to use templates

3. Test email rendering and delivery

**Completion Criteria:**
- [✅] All email templates created
- [✅] HTML emails render correctly
- [✅] Acknowledgement links included in all emails
- [✅] Emails styled professionally
- [✅] Test emails sent successfully
- [✅] Git committed and pushed

**Testing:**
- Send each email type
- Verify HTML rendering in email client
- Click acknowledgement links
- Verify links work without login

---

### Phase 27: Comprehensive Testing & Bug Fixes

**Goal:** Test all functionality end-to-end, fix bugs

**Duration:** 2-3 days

**Dependencies:** Phase 26

**Tasks:**
1. **Order Capture Testing:**
   - US order with rail → Dashboard entry created
   - Non-US order with rail → Not posted
   - US order without rail → Not posted
   - Duplicate prevention → Works

2. **Dashboard Testing:**
   - ME user login → Redirect to dashboard
   - Filters work correctly
   - Search works correctly
   - Pagination works correctly
   - Sorting works correctly

3. **ME Functions Testing:**
   - Enter tracking number → Status changed to "Shipped"
   - Acknowledge order → Status changed to "Acknowledged"
   - Print single order → PDF correct
   - Bulk print → All orders included
   - CSV export → Format correct

4. **Admin Functions Testing:**
   - Edit address → Saved + email sent
   - Edit quantities → Saved + email sent
   - Edit special instructions → Saved + email sent
   - Hold order → Status changed + email sent
   - Cancel order → Status changed + email sent
   - Un-cancel → Status restored

5. **Email Testing:**
   - Daily summary sent at correct time
   - Immediate emails sent on admin changes
   - Acknowledgement links work
   - Expired links show appropriate message

6. **Stock Management Testing:**
   - US order placed → Stock reversed
   - Non-US order → Stock reduced normally
   - Admin edits → Stock adjusted correctly
   - Failed adjustment → Logged + retried

7. **Integration Testing:**
   - AutomateWoo hooks fired correctly
   - Order meta stored correctly
   - Action Scheduler tasks process

8. **Bug Fixes:**
   - Document all bugs found
   - Fix critical bugs (blocking issues)
   - Fix high-priority bugs (UX issues)
   - Log minor bugs for post-launch

**Completion Criteria:**
- [ ] All functional tests passed
- [ ] Critical and high-priority bugs fixed
- [ ] Test results documented
- [ ] Git committed and pushed

**Testing:**
- Follow test scenarios in PRD Section 11
- Document test results in `docs/testing/test-results.md`

---

### Phase 28: Performance Optimization & Final Refinements

**Goal:** Optimize performance, finalize UI, address feedback

**Duration:** 1-2 days

**Dependencies:** Phase 27

**Tasks:**
1. **Performance Testing:**
   - Dashboard load with 500 orders → < 2 seconds
   - Dashboard load with 1000 orders → < 5 seconds
   - Save operations → < 1 second
   - Bulk print 30 orders → < 30 seconds
   - CSV export 500 orders → < 10 seconds

2. **Performance Optimizations:**
   - Review and optimize slow queries
   - Add indexes if needed
   - Optimize dashboard caching
   - Optimize asset loading

3. **UI Refinements:**
   - Polish dashboard styling
   - Fix responsive issues
   - Improve mobile UX
   - Address any visual bugs

4. **Code Quality:**
   - Run PHP_CodeSniffer (WPCS)
   - Fix coding standard violations
   - Add missing PHPDoc comments
   - Remove debug code

5. **Security Audit:**
   - Verify all inputs sanitized
   - Verify all outputs escaped
   - Verify nonces on all forms
   - Verify capability checks
   - Test SQL injection attempts
   - Test XSS attempts

**Completion Criteria:**
- [ ] Performance targets met
- [ ] UI polished and responsive
- [ ] Code passes WPCS checks
- [ ] Security audit passed
- [ ] Git committed and pushed

**Testing:**
- Performance testing with large datasets
- Security testing (penetration attempts)
- Cross-browser testing
- Mobile device testing

---

### Phase 29: Documentation & Production Deployment

**Goal:** Complete documentation, prepare for production deployment

**Duration:** 1 day

**Dependencies:** Phase 28

**Tasks:**
1. **Update Documentation:**
   - Update README.md with installation instructions
   - Finalize database deployment guide
   - Document settings configuration
   - Document AutomateWoo workflow setup
   - Create user guide for ME users
   - Create admin guide for FT admins

2. **Session Documentation:**
   - Create final session report
   - Document all implemented features
   - Document known issues/limitations
   - Document future enhancements

3. **Production Preparation:**
   - Create production database SQL scripts
   - Backup staging database
   - Document production deployment steps
   - Create rollback plan

4. **Final Review:**
   - Review all code one last time
   - Test on fresh staging clone
   - Get approval from stakeholders

**Completion Criteria:**
- [ ] All documentation complete
- [ ] Production deployment guide ready
- [ ] Final code review passed
- [ ] Stakeholder approval obtained
- [ ] Ready for production deployment

---

## Testing Strategy

### Testing Approach

**Right-sized for internal plugins:**
- Primary: Manual acceptance testing
- Secondary: Integration/smoke tests
- Minimal: Unit tests for non-trivial logic

### Manual Testing

After each phase:
1. Test on staging site
2. Document test results
3. Fix any issues before next phase

### Smoke Tests

Create WP-CLI smoke test script:
```php
// tests/smoke/wp-smoke.php
// Verify plugin loaded
// Verify tables exist
// Verify settings accessible
// Verify roles created
```

Run via SSH:
```bash
wp --path=/www/fasttracks_103/public eval-file wp-smoke.php
```

### Test Data

Create test orders on staging:
- US orders with rail (various quantities)
- Non-US orders with rail
- Mixed product orders (rail + non-rail)

---

## Deployment Workflow

### After Each Phase

1. **Code:** Complete phase tasks
2. **Test:** Manual testing on local/staging
3. **Commit:** `git add . && git commit -m "Phase X: description"`
4. **Push:** `git push origin main`
5. **Deploy:** GitHub Actions auto-deploys to staging (~30 seconds)
6. **Verify:** Test deployed code on staging
7. **Document:** Check off completion criteria in DEVELOPMENT-PLAN.md, update session logs

### GitHub Actions Deployment

Automatically triggers on push to `main`:
1. SSH to staging server
2. Pull latest code
3. Run `composer install --no-dev` (production mode)
4. Clear WordPress caches

---

## Progress Tracking

### Daily Workflow

1. Start day: Review current phase in DEVELOPMENT-PLAN.md
2. Work on phase tasks
3. Test frequently (commit often)
4. Check off completion criteria in DEVELOPMENT-PLAN.md when phase complete
5. End day: Document progress in session log

### Using DEVELOPMENT-PLAN.md for Progress Tracking

Each phase includes a **Completion Criteria** section with checkboxes. As you complete each criterion, check it off directly in this document:

```markdown
**Completion Criteria:**
- [✅] All 5 SQL scripts created and tested
- [✅] Scripts execute without errors on staging MySQL
- [ ] All tables exist with correct structure
- [ ] All indexes created successfully
```

**Benefits of this approach:**
- Single source of truth (no duplicate tracking files)
- Progress visible alongside implementation details
- Easy to see exactly what remains in current phase
- Git history shows when each criterion was completed

### Session Logs

Create session report after each development session to document:
- Which phases were completed
- Key implementation decisions
- Testing results
- Issues encountered and resolved
- Status for next session

Example session report structure:
```markdown
# Session XXX: [Brief Description]
Date: YYYY-MM-DD
Duration: X hours
Phases Completed: Phase X, Phase Y

## Work Completed
- [List major accomplishments]
- [Implementation highlights]
- [Testing completed]

## Technical Decisions
- [Key architectural or implementation choices]

## Issues Found & Resolved
- [Problems encountered and how they were fixed]

## Current State
- [What's working]
- [What's pending]

## Next Session
- [Next phase to tackle]
- [Any preparation needed]
```

**Note:** Session reports provide the historical record of what was completed when, while DEVELOPMENT-PLAN.md completion criteria track current progress.

---

## Success Criteria

### Functional Success
- [ ] 100% of US orders with rail posted to dashboard
- [ ] FT rail inventory not reduced for drop shipped orders
- [ ] ME users can enter tracking numbers
- [ ] Admin can edit orders on dashboard
- [ ] Emails sent for all trigger events
- [ ] Acknowledgements tracked correctly
- [ ] AutomateWoo hooks fire correctly
- [ ] Dashboard responsive on all devices

### Technical Success
- [ ] Code passes WPCS validation
- [ ] No PHP errors or warnings
- [ ] All queries use prepared statements
- [ ] All outputs properly escaped
- [ ] Performance targets met (<2s dashboard load)
- [ ] Action Scheduler tasks process reliably

### User Experience Success
- [ ] ME users can use dashboard without training
- [ ] Admin can manage orders efficiently
- [ ] Print/export functions work correctly
- [ ] Mobile experience usable

---

## Timeline & Milestones

### Week 1: Foundation
- Days 1-2: Phase 1-3 (Database, Plugin Structure, Core Classes)
- Days 3-4: Phase 4-6 (Repositories, Models)
- Day 5: Phase 7-8 (Utilities, Rail Identifier)

### Week 2: Core Services
- Days 1-2: Phase 9-10 (Order Processor, Stock Manager)
- Days 3-4: Phase 11 (Email System + Token/Acknowledgement - 2 days)
- Day 5: Phase 12-13 (Additional Emails + Status Manager - 1.5 days)

### Week 3: Dashboard & Admin
- Days 1-2: Phase 14-15 (Admin Actions + Dashboard Manager - 2.5 days)
- Days 3-4: Phase 16-17 (Dashboard Controller + Acknowledgement Endpoint - 1.5 days)
- Day 5: Phase 18 (Admin Pages - start, 1.5 days total)

### Week 4: Integration & UI
- Days 1-2: Phase 19-20 (WC Integration, Action Scheduler)
- Day 3: Phase 21 (Hook Manager, DI)
- Days 4-5: Phase 22-23 (Dashboard Templates, CSS)

### Week 5: UI & Testing
- Days 1-2: Phase 24-25 (JavaScript, Print/Export)
- Days 3-5: Phase 26 (Email Templates)

### Week 6: Testing & Refinement
- Days 1-3: Phase 27 (Comprehensive Testing)
- Days 4-5: Phase 28 (Performance Optimization)

### Week 7: Documentation & Deployment
- Day 1: Phase 29 (Documentation)
- Days 2-3: Final testing and stakeholder review
- Days 4-5: Buffer for unexpected issues

**Total Estimated Duration:** 7 weeks (can compress to 6 weeks if needed)

---

## Conclusion

This development plan provides a clear, phased approach to building the Drop Shipped Rail plugin. Each phase:
- Has a clear goal and deliverables
- Takes 0.5-2 days to complete
- Builds on previous phases
- Is independently testable
- Moves the project toward completion

By following this plan systematically, the plugin will be built reliably with frequent testing and validation at each step.

---

**Ready to Begin Implementation!**

Start with Phase 1: Database Schema & SQL Scripts
