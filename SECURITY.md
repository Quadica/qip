# SECURITY.md – Quadica Credential Handling Standard

## Purpose
This document defines the baseline expectations for managing API keys, passwords, and other secrets across **all** Quadica WordPress/WooCommerce plugins. Every developer or AI agent working in our repositories must follow these guidelines to ensure consistent, auditable, and secure handling of credentials.

## Scope & Assumptions
- Applies to any code committed to Quadica-owned repositories (plugins, snippets, tooling).
- Enforced on development, staging, and production environments.
- Assumes WordPress ≥ 6.8 running on Kinsta-managed hosting with PHP 8.1+.

## Core Principles
- **Never store secrets in source control.** Plaintext credentials, sample API keys, or screenshots containing secrets are prohibited.
- **Single source of truth is the WordPress database.** Avoid `wp-config.php` constants or environment variables for plugin-specific keys unless explicitly documented otherwise.
- **Encrypt sensitive values at rest.** Secrets must be encrypted before being saved to the database and only decrypted in memory when needed.
- **Centralize credential access.** Provide a dedicated credential manager class per plugin/namespace so that storage, retrieval, logging, and masking logic live in one place.
- **Minimize exposure.** Secrets must never appear in logs, notices, REST responses, or HTML output beyond masked placeholders.

### context7 MCP Is Available
- Always use context7 to detect library references and fetch relevant documentation

## Approved Storage Pattern
1. **Credential Manager**
   - Implement a namespaced utility class (e.g., `Acme\Plugin\Utilities\Credential_Manager`).
   - Maintain an allowlist (e.g., `CREDENTIAL_KEYS`) and a subset of keys requiring encryption.
   - Provide static methods: `store_credential()`, `get_credential()`, `has_credential()`, `delete_credential()`, and optional helpers (masking, validation).
2. **Encryption**
   - Use AES-256-CBC with a 256-bit key and 128-bit IV derived from WordPress salts: `substr( wp_salt(), 0, 32 )` and `substr( wp_salt( 'secure_auth' ), 0, 16 )`.
   - Encode encrypted payloads with `base64_encode()` for storage.
   - Reference: [wp_salt()](https://developer.wordpress.org/reference/functions/wp_salt/), [openssl_encrypt](https://www.php.net/manual/en/function.openssl-encrypt.php).
3. **Option Storage**
   - Persist credentials in a single option array (e.g., `your_plugin_settings`) using `update_option()` (https://developer.wordpress.org/reference/functions/update_option/).
   - The Settings API sanitize callback must return the full array, including encrypted values, to avoid wiping secrets.
4. **Admin Experience**
   - Credential fields live on an authenticated settings page (capability check `manage_options` or stricter).
   - Password-type inputs display `**********` when a value exists; submitting the placeholder must leave the stored secret untouched.
   - Text/select inputs retrieve values through the credential manager, which handles decryption.

## Prohibited Practices
- Committing `.env`, `.sql`, or configuration files containing real credentials.
- Logging secrets with `error_log`, `Logger`, or `WP_CLI::log`.
- Embedding keys in JavaScript bundles, front-end HTML, or client-side storage.
- Sharing credentials via pull requests, GitHub issues, or chat transcripts.

## Adding a New Credential
1. Extend the credential manager allowlist (and encrypted subset, if sensitive).
2. Update the settings UI to capture and validate the value.
3. Add unit tests covering storage, retrieval, deletion, and edge cases (empty input, masked submission).
4. Document required values in the relevant PRD or plan without exposing real secrets.

## Testing & Tooling Guidance
- During automated tests, use obvious placeholders such as `TEST_ONLY_SECRET` and reset the option after assertions.
- Ensure WP-CLI commands that manipulate credentials read from stdin or environment variables and never echo values back to the terminal.
- Before deployment, confirm Action Scheduler jobs or background tasks that depend on credentials can still authenticate after any rotation.

## Credential Rotation Procedure
1. Rotate the key/secret with the upstream provider.
2. Update the value via the plugin’s settings page (preferred) or a secure WP-CLI wrapper command.
3. Trigger applicable smoke tests (API ping, OAuth handshake, etc.).
4. Record the rotation in `docs/project-history/` for traceability.

## Reviewer Checklist
- [ ] Secrets retrieved exclusively through the credential manager.
- [ ] Encrypted fields are never logged or returned by REST endpoints.
- [ ] Settings sanitize callbacks return complete arrays containing encrypted values.
- [ ] Unit/integration tests avoid real secrets and clean up after themselves.
- [ ] Documentation references official WordPress and PHP encryption APIs.

Adhering to this standard ensures every Quadica plugin protects customer data, simplifies audits, and keeps credential management consistent across projects.
