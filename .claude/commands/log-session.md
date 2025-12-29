# Session Report Creation Using The `session-reporter` Agent
- Use the `session-reporter` agent to create a session report that documents what changed and what was accomplished during this development session to enable future AI sessions to pick up where we left off
- **Important:** Always use the `session-reporter` agent to create the session report
- Save the report as a markdown file in the `docs/project-history` directory
- **File Naming Format:** `session-###-[DESCRIPTION].md`
  - `###`: Three-digit zero-padded sequential number (e.g., 001, 002, 013)
  - `DESCRIPTION`: Brief, descriptive slug (max 30 characters, lowercase, hyphens for spaces)
  - Examples: 
    - `session-001-initial-setup.md`
    - `session-013-csv-bug-fixed.md`
    - `session-027-auth-system-refactor.md`
- **Use Next Sequential Number:** Check existing session files to determine the next sequential number to use for this report. If the last session was `session-012-*.md`, then create `session-013-*.md`.

 $ARGUMENTS
