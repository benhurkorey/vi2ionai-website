# Agent instructions

## Working rules

- Work autonomously on routine development tasks. Avoid unnecessary permission requests and interruptions wherever platform settings permit.
- Before starting, read existing project instructions and recent progress relevant to the task.
- Do not repeat completed audits, investigations or tests without a specific reason. Fix identified problems instead of repeatedly analysing them.
- Run targeted tests for changed code. Run full suites when necessary for shared functionality, security or releases; do not run application test suites for documentation-only changes.
- Avoid unnecessary documentation, planning phases and approval loops. Reuse existing infrastructure and solutions.
- Minimise token usage and redundant tool calls. Keep responses concise and focused on results.
- Report only completed changes, relevant test results and genuine blockers.
- Ask for approval only when required for consequential production changes, destructive operations, credentials, security or costs. Never bypass essential security or deployment protections.
- Preserve project-specific rules and safeguards; these working rules do not relax them.

## Ten-step autonomous delivery workflow

For routine, previously authorised changes, complete steps 1–9 autonomously without interrupting the owner:
1. Identify and confirm the problem.
2. Investigate and implement the smallest safe fix.
3. Run relevant local tests.
4. Commit with a traceable ID.
5. Push to GitHub.
6. Create or update the PR.
7. Run CI and resolve failures.
8. Merge into the correct up-to-date release branch, where authorised and permitted by branch protections.
9. Deploy through the established authorised process; verify the live version, functionality, service health and rollback readiness.
10. Return to the owner once for final review of steps 1–9, with commit, PR, CI, deployed version, live checks and remaining issues.

Do not interrupt for routine approvals. This workflow does not override access permissions, required reviews, explicit production or migration approvals, security controls, backups or rollback requirements. Stop for genuine blockers or unapproved high-risk/destructive changes. Never deploy stale code over newer production, expose secrets or claim unverified success. Avoid redundant audits and tests. Documentation-only changes require no deployment.
