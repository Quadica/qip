# TESTING.md - Test Site Access Instructions

## Testing Site
A dedicated testing site is available and can be accessed using WP-CLI for this project. Here are instructions for running tests on this site.

### Testing
- [ ] **Testing Framework**
  - WP-CLI for automation
  - Browser testing tools
  - Performance monitoring

- [ ] **Testing Environment**
  - **Access Information**: See the CONFIG.md document for placeholder values. E.g., KEY, PORT, HOST, etc.
  - **WP-CLI**: Available (v2.12.0)
    - Non-interactive (automation/Codex key) — REQUIRED for all sessions:
      `ssh -i ~/.ssh/KEY -o BatchMode=yes -o IdentitiesOnly=yes -o StrictHostKeyChecking=accept-new -p PORT USER@HOST 'echo ok'`
  - **Automated Deployment**: GitHub Actions deploys changes automatically
  - **Safe for Development**: Isolated from production luxeonstar.com
  - **Test Data**: The data in the test site is cloned from our production site
  - **TREAT TEST DATA AS CONFIDENTIAL**: Cloned data includes customer information

#### Quick Test Commands (Staging)
- Non-interactive connectivity test — REQUIRED:
  - `ssh -i ~/.ssh/KEY -o BatchMode=yes -o IdentitiesOnly=yes -o StrictHostKeyChecking=accept-new -p PORT USER@HOST 'echo ok'`
- SSH into staging: `ssh USER@HOST -p PORT`
- Ensure plugin is active: `wp --path=PATH plugin activate PLUGIN_NAME`
- Run smoke test (from WP root):
  - `cd PATH && wp eval-file wp-content/plugins/PLUGIN_NAME/tests/smoke/wp-smoke.php`
- Run smoke test (from anywhere):
  - `wp --path=PATH eval-file PATH/wp-content/plugins/PLUGIN_NAME/tests/smoke/wp-smoke.php`
- Optional alias: `alias wpst='wp --path=PATH'` then `wpst eval-file wp-content/plugins/PLUGIN_NAME/tests/smoke/wp-smoke.php`

Notes:
- `wp eval-file` needs a valid WordPress context. If you are not in the WP root, use `--path` to point WP‑CLI at `PATH`.
- The file path given to `eval-file` is resolved by the shell. Use absolute paths or run from the WP root so relative paths resolve correctly.

#### Standard Session Start (Non-Interactive)
- Connectivity check (required):
  - `ssh -i ~/.ssh/KEY -o BatchMode=yes -o IdentitiesOnly=yes -o StrictHostKeyChecking=accept-new -p PORT USER@HOST 'echo ok'`
- Ensure plugin is active (idempotent):
  - `ssh -i ~/.ssh/KEY -o BatchMode=yes -o IdentitiesOnly=yes -o StrictHostKeyChecking=accept-new -p PORT USER@HOST 'wp --path=PATH plugin is-active PLUGIN_NAME || wp --path=PATH plugin activate PLUGIN_NAME'`
- Run plugin smoke test via WP‑CLI:
  - `ssh -i ~/.ssh/KEY -o BatchMode=yes -o IdentitiesOnly=yes -o StrictHostKeyChecking=accept-new -p PORT USER@HOST 'wp --path=PATH eval-file PATH/wp-content/plugins/PLUGIN_NAME/tests/smoke/wp-smoke.php'`

#### Non-Interactive SSH Requirements (Agent)
- Private key path must exist with strict perms: `~/.ssh/KEY` (chmod 600)
- Pre-seed host key once per machine: `ssh-keyscan -p PORT HOST >> ~/.ssh/known_hosts && chmod 644 ~/.ssh/known_hosts`
- Fast bootstrap (run at session start if needed):
  - `test -f ~/.ssh/KEY && chmod 600 ~/.ssh/KEY || echo 'MISSING: ~/.ssh/KEY'`
  - `ssh -i ~/.ssh/KEY -o BatchMode=yes -o IdentitiesOnly=yes -o StrictHostKeyChecking=accept-new -p PORT USER@HOST 'echo ok'`
