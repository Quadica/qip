# AJAX Implementation Guidelines

## Business Context for AJAX Decisions
- **Site Volume:** 5-20 orders daily, 1-2 concurrent admin users typical
- **Complexity vs. Benefit:** AJAX adds complexity for minimal user experience gain in low-traffic scenarios
- **Development Philosophy:** Prioritize maintainability and simplicity over premature optimization

## When to USE AJAX (Limited Cases):
- **Live product availability checks** during checkout
- **Critical real-time validations** (e.g., checking if custom LED configuration is valid)
- **Search-as-you-type** for product searches with 2000+ products

## When to AVOID AJAX (Default Approach):
- **Admin interface updates** - page reloads are acceptable for admin users
- **Form submissions** in admin areas - traditional POST is simpler and more reliable
- **Data imports/exports** - use traditional form submission with proper feedback
- **Settings pages** - page reload ensures all settings are properly refreshed
- **Report generation** - standard page loads prevent timeout issues

## Decision Framework:
1. **Default to traditional page loads** unless there's a compelling reason
2. **Ask yourself:** "Will the page reload significantly impact user workflow?"
3. **Consider maintenance:** AJAX requires more error handling and testing
4. **When uncertain:** Implement without AJAX first, then enhance if requested by the software manager

## Implementation Requirements (When AJAX is Justified):
- Always include proper error handling and user feedback
- Implement fallback for when JavaScript is disabled
- Use WordPress nonces for security
- Show loading indicators for operations > 1 second

## Consultation Trigger:
**Always consult before implementing AJAX if:**
- The feature affects the checkout process
- It involves payment or order data
- Multiple concurrent users might interact with the same data
- The traditional approach would work equally well
