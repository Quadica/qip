# Implement the Next Project Phase

## Core Instructions

1. Think harder and implement Phase $ARGUMENTS of this project now.
2. When you are finished, use the session-reporter agent to create a session document that provides complete details about the code you created for this phase.
3. When the session-reporter agent has finished creating the session report, add the following review request statement to the beginning of the document:

## Required Steps (MUST COMPLETE ALL)

**Step 1:** Implement this phase using think harder methodology

**Step 2:** Use the session-reporter agent to create a session report with complete details about the code you created

**Step 3 (CRITICAL - DO NOT SKIP):** After the session-reporter agent finishes, you MUST add the following text block to the BEGINNING of the generated session report:

```
This report provides details of the code that was created to implement phase [$ARGUMENTS] of this project.

Please perform a comprehensive code and security review covering:
- Correctness of functionality vs. intended behavior
- Code quality (readability, maintainability, adherence to best practices)
- Security vulnerabilities (injection, XSS, CSRF, data validation, authentication, authorization, etc.)
- Performance and scalability concerns
- Compliance with WordPress and WooCommerce coding standards (if applicable)

Provide your response in this structure:
- Summary of overall findings
- Detailed list of issues with file name, line numbers (if applicable), issue description, and recommended fix
- Security risk level (Low / Medium / High) for each issue
- Suggested improvements or refactoring recommendations
- End with a brief final assessment (e.g., "Ready for deployment", "Requires moderate refactoring", etc.).

```

⚠️ **IMPORTANT:** Step 3 must be completed AFTER the session-reporter finishes. This is not optional.

---
*Note: This instruction set is static. Do not modify or check off any items above.*