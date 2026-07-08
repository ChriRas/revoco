# Slice 021 — Operator notification recipient (fix + operator-managed address)

> Completed: 2026-07-06
> Commits: 66a1fc2 (PR #33)
> Origin: staging finding **A** (0.6.0), highest priority.

## What

The withdrawal notification e-mail now reaches a **defined, operator-managed
recipient** instead of silently not sending. A new `NotificationSettings`
(`spatie/laravel-settings`, one `notification_email` field) is edited on a new
Filament `ManageNotifications` settings page, which also carries a **"Test-Mail
senden"** action (synchronous send to the resolved recipient, Filament
success/error notification) and a read-only "mail transport configured?" hint.
A `NotificationRecipient::resolve()` resolver feeds the recipient into
`SendWithdrawalNotifications`. The spam verdict is now a **prominent subject
prefix** instead of a trailing tag. The consumer acknowledgment (§ 356a Abs. 4)
is untouched.

## Why

On staging the operator received nothing: `SendWithdrawalNotifications` only sent
when `config('revoco.merchant_email')` (env `MERCHANT_NOTIFICATION_EMAIL`,
default `null`) was set — env-only and unset. The recipient had to become
operator-manageable with a safe fallback, and the operator needed a way to prove
delivery without editable SMTP credentials.

## Decisions

- **Secret vs. business-config split.** SMTP transport stays in `MAIL_*` env
  (secrets do not belong in a DB settings table); the notification **recipient**
  is business config → operator-managed panel setting. Mirrors the project's
  existing split (transport in env, legal content in DB settings).
- **Recipient precedence:** `NotificationSettings::notification_email` (panel)
  → `config('revoco.merchant_email')` (env, backward-compat) →
  `LegalSettings::imprint_email` (the already-required imprint address) →
  `null` (nothing sends; panel shows an "unconfigured" hint). Empty string is
  treated as unset at each level. Serves both the automated/Docker deployer (env)
  and the plain-webspace operator (panel).
- **"Send test e-mail" button, not editable SMTP.** A synchronous test action + a
  read-only "configured?" indicator — the comfort win for non-technical operators
  — without moving credentials into the DB.
- **E-mail templates stay in code.** § 356a Abs. 4 (advertising-free
  acknowledgment) compliance risk + scope; not operator-editable.
- **Body & push already complete.** The notification body already lists every
  field + spam status/reason and the push already carries the spam signal — this
  slice only added the recipient surface + subject prominence.

## Commits

- `66a1fc2` — feat(notifications): operator-managed notification recipient + test
  mail + prominent spam subject

Files: `app/Settings/NotificationSettings.php`,
`app/Support/NotificationRecipient.php`,
`app/Filament/Pages/ManageNotifications.php`,
`app/Listeners/SendWithdrawalNotifications.php`,
`app/Mail/WithdrawalNotification.php`, `config/settings.php`,
`database/settings/2026_07_06_000000_create_notification_settings.php`,
`lang/{de,en}/panel.php`, `lang/de/mail.php`,
`tests/Feature/NotificationRecipientTest.php`.

## Follow-ups

- Archived retroactively during the 2026-07-08 plan/state reconciliation: the
  slice shipped straight to `main` via PR #33 without a formal Phase 6–9 recap
  pass, so this entry is reconstructed from the plan intent + the merged diff.
