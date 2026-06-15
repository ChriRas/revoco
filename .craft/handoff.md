# Handoff — slice-005-operator-backend

Status: awaiting-test
Phase: 5
Written: 2026-06-16T00:00:00Z
Branch: slice-005-operator-backend

## Title

Phase 5 human exercise: verify the Filament admin panel end-to-end

## Body

Phase 4 (Build) is complete and `task check` is green (Pint + PHPStan max + 39
Pest tests, all passing). Phase 5 requires a human to exercise the running app.

### Demo invocation (trigger → effect)

1. Ensure the dev stack is up: `task up`
2. Provision the operator account:
   - Add to `.env`:
     ```
     OPERATOR_EMAIL=operator@local.test
     OPERATOR_PASSWORD=secret
     ```
   - Run: `task artisan -- app:operator`
3. Open `http://localhost:8580/admin` in a browser.
4. Verify guest redirect → login page (no data exposed).
5. Log in with the credentials set above.
6. Verify: the Withdrawals list is empty (no submissions yet).
7. (Optional) Submit a withdrawal at `http://localhost:8580/` to seed a row,
   then return to `/admin/withdrawals`.
8. Verify the list columns: Received (Europe/Berlin), Name, Email, Order #,
   Subject, Spam icon, Handled icon.
9. Click "Mark handled" on a row → icon changes to checked / green.
10. Click "Unmark handled" → icon reverts.
11. Click a row name/email → opens read-only view page (no edit/delete buttons).
12. Verify: no "Create" button anywhere in the panel.

### Suggested next action

- If all steps pass: set slice plan `Status: review` and resume with
  `/craft:execute slice-005` (or manually advance to Phase 6).
- If a UI issue is found: note it under `## Bugs` in the slice plan and
  set `Status: paused` with a Pause Note.
