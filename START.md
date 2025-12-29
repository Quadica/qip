# Start New Session

Before we continue, carefully review the following documents
- **CONFIG.md** - Project-specific configuration values (testing site URL, SSH credentials, paths)
- **CLAUDE.md** (If you are Claude Code)
- **AGENTS.md** (If you are GPT CODEX)
- **SECURITY.md**
- **TESTING.md**
- **PROJECT.md**
- The .prd document for the currently active project
- The most recent session report in the `docs\project-history\` directory to understand what was completed during the last session

## Confirm WP-CLI Available For The Testing Site
- Using the information in the TESTING.md file to confirm you can use WP-CLI to run tests on the testing website
- If successful, note that site testing is functional in your opening response to the user
- If unsuccessful, attempt to fix the problem yourself
- If your fix is successful, let the user know and suggest changes so that the problem does not recur in the future
- If your fix is unsuccessful, provide details of your analysis and next steps to solve the problem to the user.

## Confirm Playwright Availability for Screenshots

**REQUIRED AT START OF EVERY SESSION - DO NOT SKIP**

Using the screenshots agent:

1. **Call the screenshots agent** with instructions to take a test screenshot:
   - URL: The front page of the testing site (see TESTING_SITE_URL in CONFIG.md)
   - Name the file `session-start-test-[date-time].png` E.g., session-start-test-2025-08-04-18-34.png
   - Save location: `docs/screenshots/dev/`
   - Purpose: Verify Playwright screenshot functionality is operational

2. **Wait for the agent to complete** and return the screenshot

3. **Review the agent's report** to confirm:
   - Playwright browser launched successfully
   - Page navigation worked (HTTP 200 response)
   - Screenshot file was saved to the correct location
   - File size is reasonable (> 10KB indicates actual content)
   - Screenshot shows the expected page content

4. **If successful:** Note that screenshot functionality is operational in your opening response to the user

5. **If unsuccessful:**
   - Attempt to diagnose and fix the problem yourself
   - If your fix is successful, let the user know and suggest changes to prevent recurrence
   - If your fix is unsuccessful, provide details of your analysis and next steps to the user

**IMPORTANT:** Do not rely on screenshots from previous sessions. Take a fresh test screenshot at the start of EVERY session to ensure the system is currently functional.

## Additional Instructions
- Summarize the current project status
- List any pending tasks
- Identify any blockers or issues
- Only review information, DO NOT start any new document creation or coding work.
