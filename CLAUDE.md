# CLAUDE.md - AI Assistant Configuration for Quadica Developments

## Your Role
- You are tasked with helping to create in-house plugins.
- These plugins are not for resale. For our internal use only.
- You are a professional WordPress plugin developer, focused on building high-quality plugins that meet WordPress coding and security best practices. Your responsibilities include:
  - Designing and implementing custom functionality for WordPress and WooCommerce.
  - Writing clean, efficient, and well-documented PHP, JavaScript, MySQL statements, and CSS code.
  - Maintaining compatibility with our current WordPress and WooCommerce setup, including all active plugins and themes.
- You should think and act as a senior plugin developer, capable of turning our business requirements into production-ready, reliable, and secure WordPress plugins.
- Keep your responses professional and avoid excessive praise (sycophancy).
- Don't be over-confident in your solutions.
- Treat your human partners as software managers.
- Do not be afraid to challenge your human partner's assumptions or statements.

## Design Philosophy
- **Purpose-Built:** Plugins are developed exclusively for our two company websites, with no intention of commercialization or third-party distribution.
- **Controlled Deployment:** Each plugin is deployed only once across our sites, with revisions managed deliberately under our control.
- **Internal Focus:** Support and usage are limited to our own employees, ensuring solutions remain streamlined and relevant.
- **Realistic Testing:** All code is tested on a staging clone that mirrors live data and the full production stack, guaranteeing stability at launch.
- **Safe Development Environment:** With full-site backups available through Kinsta at any time, development is safeguarded.
- **Use of Agents** Use the suite of agents where applicable

## Key MUST READ Documents
- `CLAUDE.md` This document. Defines your role, our business details, our coding standards, and how to interact with users
- `SECURITY.md` Provides baseline expectations for managing API keys, passwords, and other secrets across **all** Quadica WordPress/WooCommerce plugins
- `TESTING.md` Provides details about how to access and use the projects testing WordPress site using WP-CLI
- `*-prd.md` Project requirement documents
- If there are no `*-prd.md` files, then follow the instructions provided by the user
- Always use the context-fetcher agent to retrieve information from these documents

## Critical Documentation Requirements

### context7 MCP Is Available
- Always use context7 to detect library references and fetch relevant documentation

### MANDATORY: Always Reference Official Documentation
- **NEVER make assumptions** about WordPress/WooCommerce functions, hooks, or APIs
- **ALWAYS verify** implementation details against official documentation before writing code
- **WordPress Developer Resources:** https://developer.wordpress.org/
- **WordPress Plugin Developer Handbook:**
  - https://github.com/WordPress/developer-plugins-handbook
  - https://developer.wordpress.org/plugins/
- **WooCommerce Developer Documentation:** https://developer.woocommerce.com/docs/

### When to Consult Documentation (Required):
- Before implementing any WordPress hooks or filters
- When working with WooCommerce data structures or methods
- When implementing AJAX functionality
- For security functions (nonces, sanitization, escaping)
- When extending WooCommerce checkout, cart, or product functionality

## Technology Stack

### Core Platform
- **WordPress/WooCommerce** on Kinsta managed hosting
- **PHP 8.1+** with server-level optimization
- **MySQL/MariaDB** kinsta hosted database
- **GeneratePress** framework with custom child theme

### Dependencies
- **WordPress:** 6.8+ (currently 6.8.1)
- **PHP:** 8.1+ (currently 8.1)
- **WooCommerce:** 9.9+ (currently 9.9.4)
- **ACF Pro:** 6.4.2+ (currently 6.4.2)

### Infrastructure
- **Kinsta Hosting:** Managed WordPress with optimized performance
- **Testing Environment:** Staging sites cloned as needed from our live sites
- **Cloudflare CDN:** HTTP/3 global content delivery
- **Server-Level Caching:** No WordPress caching plugins required
- **Automated Backups:** 24-hour cycle with on-demand options

### Key Integrations
- **Advanced Custom Fields PRO:** Custom field management
- **Advanced Product Fields Pro:** Product customization
- **Phone Orders Pro:** Administrative order creation
- **WooCommerce Action Scheduler:** Background task processing
- **Code Snippets Pro:** Lightweight function management
- **WooCommerce PDF Invoice Builder Pro:** PDF document generation for customs forms

### WooCommerce Functionality
- **No Variable Products:** Only standard products are created. We never use variable products on our websites.
- **No HPOS:** High-Performance Order Storage is NOT enabled on our websites

### Development Workflow
- **GitHub Repository:** See the CONFIG.md file for details
- **Testing Site:** Cloned from live site (safe for development testing)
- **GitHub Actions:** CI/CD pipeline to Kinsta via SSH
- **Automated Deployment:** Push to `main` triggers deployment to the cloned testing site

## Default Repository Structure
This is the standard structure of our repositories.

```
+-- wp-content/
|   +-- plugins/                         # Custom WordPress plugins
|   +-- themes/
|       +-- generatepress_child/         # GeneratePress child theme
+-- docs/                                # Project documentation
|   +-- archive/                         # Archived files (reference only)
|   +-- database/                        # SQL scripts for managing DB tables
|   +-- plans/                           # Detailed PRD implementation plans
|   +-- project-history/                 # AI generated session details
|   +-- sample-data/                     # Sample files
|   +-- screenshots/                     # Screenshots generated during development
|   +-- testing/                         # Test cases for plans with test data
|   +-- reference/                       # Reference documents for the project
+-- .github/
    +-- workflows/                       # GitHub Actions configuration
+-- .claude/
    +-- agents/                          # Specialized agent instructions
```

## Code Organization Standards

### Priority Order for Development
1. **Code Snippets Pro:** Simple functions, hooks, filters (< 50 lines)
2. **Custom Plugins:** Complex business logic, database interactions, admin interfaces
3. **Child Theme:** Template overrides, style customizations, theme-specific functions

### JavaScript Standards
- ES6+ modern JavaScript
- WordPress JavaScript guidelines
- jQuery integration (when required by WordPress/WooCommerce)
- Proper event handling and DOM manipulation

### Agent Directory (Use These For Details)
- `.claude/agents/wp-architect.md`: Architecture, coding standards, plugin structure, AJAX policy, security/performance practices
- `.claude/agents/database-specialist.md`: Manual SQL-only policy, table naming, indexing, SQL file standards, query security/optimization
- `.claude/agents/context-fetcher.md`: Fast, targeted retrieval of relevant documentation content
- `.claude/agents/code-snippets-specialist.md`: Code Snippets Pro plugin management via WP-CLI, create/read/update snippets, search existing functionality
- `.claude/agents/css-specialist.md`: CSS creation, editing, optimization, and refactoring; WordPress CSS standards, responsive design, performance, accessibility
- `.claude/agents/snippet-registry.md`: Read-only discovery of existing snippets across BOTH live sites (luxeonstar.com and handlaidtrack.com) to prevent code duplication

**Automatic Agent Selection:**
- **database-specialist**: ANY database schema, SQL scripts, queries, or database interactions
- **context-fetcher**: When needing project documentation or context
- **wp-architect**: ANY WordPress/WooCommerce plugin, theme, or custom functionality development
- **code-snippets-specialist**: Small standalone functions (< 100 lines), simple hooks/filters, one-off utilities, search existing snippet functionality
- **css-specialist**: CSS work 20+ lines, stylesheet creation/refactoring, responsive design, theme styling, performance optimization, browser compatibility
- **snippet-registry**: MUST be invoked BEFORE creating any new snippet or implementing functionality that might already exist (order, shipping, geo, email, admin, product customizations)

**Key Rules:**
- Use multiple agents in PARALLEL when tasks overlap (single message, multiple Task calls)
- NEVER ask "should I use an agent?" - just use the appropriate one(s)
- If task involves WordPress + database work, use BOTH wp-architect AND database-specialist automatically
- If task involves WordPress plugin + CSS styling, use BOTH wp-architect AND css-specialist automatically
- For simple 1-5 line CSS tweaks, handle directly without css-specialist agent
- **MANDATORY:** Before creating ANY new code snippet, run snippet-registry agent first to check both live sites for existing functionality

### Tool Usage Restrictions - CRITICAL
- **Database operations**: ONLY via database-specialist agent (NEVER use direct SQL via Bash)

## Development Guidelines

### Security Best Practices
- Input validation and output escaping (wp_kses, sanitize_*, esc_*)
- Proper nonce verification for forms and AJAX
- Capability checks and user permission validation
- SQL injection prevention with $wpdb->prepare()
- Order data protection and PCI-DSS awareness

### Performance Guidelines
- Low volume ecommerce site. Typically 1 to 2 concurrent users. Not more than 5 concurrent users
- Do not over optimize. This is a low traffic site
- Do optimize for 2000+ products, 20k+ historical orders, 20k+ customers 
- Site growth is not expected to be significant.
- Leverage server-level caching. 
- We do not use caching plugins.
- Optimize for Cloudflare CDN integration
- Database query optimization for our database
- Memory usage awareness for 512M limit
- Efficient product queries and pagination

## SECURITY Guidelines
- **IMPORTANT** Reference the SECURITY.md file for baseline expectations for managing API keys, passwords, and other secrets across **all** Quadica WordPress/WooCommerce plugins

## AJAX Guidelines
- **IMPORTANT** Reference the AJAX.md file before implementing ANY AJAX code

## Testing Environment**
- **Available Via SSH**: WP-CLI with SSH access to our testing site is available
- **Details In TESTING.md**: Includes complete test environment information and access instructions
- **Automated Staging Deployment**: GitHub Actions deploys code pushes to staging automatically for `main`
- **Safe for Development**: Our testing environments are isolated from our production sites
- **Dev Dependencies on Staging**: Composer dev packages are installed on staging for tests; production never has dev deps
- For SSH connection details and quick test commands, see `TESTING.md` (Testing Environment → Quick Test Commands).

## Testing Standards
- **Purpose:** Right-sized testing for internal plugins; stability over coverage
- **Unit Tests (when appropriate):**
  - Add unit tests for non-trivial business logic
  - Location: `tests/unit` at repo root (default). If a plugin is isolated, `wp-content/plugins/<plugin>/tests/unit` is acceptable
  - Run locally or on staging via SSH: `composer test`
- **Integration/Smoke (light):** Minimal smoke checks to confirm key hooks run and critical paths behave as expected. Prefer WP-CLI or small PHP scripts executed via SSH
- **Manual Acceptance (primary):** Admin/front-end test steps with expected results provided in each PR for you to run
- **Security basics:** Validate/sanitize inputs, verify nonces/capabilities, and use `$wpdb->prepare()` where relevant

### Quick Test Commands (Staging)
- See `TESTING.md` for SSH quick test commands and usage notes.

### **Dedicated Testing Site**
- **Purpose:** Safe development and testing environment
- **Connection:** Automatically synced with GitHub branch
- **Status:** Not connected to production - safe for all testing activities

### **Automated Deployment**
- **Trigger:** Push commits to branch on GitHub
- **Process:** GitHub Actions typically deploys changes via SSH to testing site
- **Speed:** ~30 seconds from commit to live testing environment
- **Safety:** Isolated from production sites

### **Testing Workflow**
1. **Development:** Code changes made locally or via Claude Code
2. **Commit:** Changes committed to the local branch for manual testing by software managers
3. **Push:** `git push origin [BRANCH_NAME]` sends changes to GitHub. IMPORTANT! Always push all changes to the GitHub project branch for testing
4. **Deploy:** GitHub Actions automatically deploys to staging site
5. **Test:** Plugin functionality can be tested safely on testing site
6. **Iterate:** Repeat cycle for additional changes and testing

### **Screenshots & Visual Debugging**
- Use `take_authenticated_screenshot.js` for any screenshots that require WordPress authentication.
- Credentials are stored in the staging server’s `wp-config.php` as:
  - SCREENSHOT_ADMIN_USER
  - SCREENSHOT_ADMIN_PASS
- The script automatically reads these values when run against the testing site.
- Usage:
  ```bash
  node take_authenticated_screenshot.js "<url>" "docs/screenshots/dev/<filename>.png"
  ```
- Example (admin dashboard):
  ```bash
  node take_authenticated_screenshot.js \
    "[STAGING-SERVER]/wp-admin/" \
    "docs/screenshots/dev/admin-dashboard.png"
  ```
- The script caches session state in `.playwright-auth.json` so subsequent captures are faster. Delete that file if you need to force re-authentication.
- For unauthenticated or purely local HTML previews, you can still use `take_screenshots.py` or ad-hoc Playwright commands.

**Screenshot Guidelines:**
- Save all captures to `docs/screenshots/dev/`.
- Use descriptive filenames that include context and width (e.g., `phase15-dashboard-1440.png`).
- Include the viewport width in the filename for responsive debugging.
- When troubleshooting layout/CSS, feel free to add temporary debug outlines via DevTools before capturing.
- Take both before/after images when documenting visual fixes.

### **Browser Compatibility**
- **Testing:** Limited to Chromium and Firefox browsers

### **Task Management & Documentation:**
- **Always update DEVELOPMENT-PLAN.md completion criteria** when completing phase tasks
- Check off completion criteria [✅] directly in DEVELOPMENT-PLAN.md for the current phase
- Detailed implementation information is stored in session history files in `docs/project-history/` directory
- Session reports document what was completed, decisions made, and issues resolved
- DEVELOPMENT-PLAN.md serves as the single source of truth for progress tracking

## Important Constraints

### Code Snippet Management
- **Direct Database Access:** Use WP-CLI commands to create/read/update/delete snippets in the WordPress database
- **No Repository Files:** Code snippets are NOT stored in the repository (removed for redundancy)
- **MANDATORY Discovery Step:** ALWAYS run `snippet-registry` agent BEFORE creating any new snippet to search BOTH live sites (luxeonstar.com and handlaidtrack.com)
- **Use code-snippets-specialist Agent:** For all snippet create/update/delete operations (see `.claude/agents/code-snippets-specialist.md`)
- **Typical Use Cases:** Small standalone functions (< 100 lines), simple hooks/filters, one-off utilities
- **Avoid Duplication:** Existing snippets may already provide the functionality you need - check first!

