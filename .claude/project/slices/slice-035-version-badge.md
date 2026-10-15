# Slice 035 — Version Badge

> Completed: 2026-10-15
> Commits: 862695b..f06e83f (branch epic-004-operator-insights, autopilot)
> Epic: [epic-004 — Operator Insights](./epic-004-operator-insights.md)

## What

A release image now knows its own version. The scheduler asks the public GitHub Releases API once a day for the latest
stable release (unless the operator set `REVOCO_UPDATE_CHECK=false`), and every logged-in panel page shows "Revoco
<version>" at the sidebar foot, with a coloured "<newer> available" badge linking to that release's notes when a newer
stable release is cached. Rendering never touches the network.

## Why

Operators had no way to see which version they run or that a new release exists. The version comes from the git tag
(single source of truth, baked in by the release build); the lookup runs in the scheduler, never in the request path,
and is anonymous with a one-line opt-out, so a self-hosted deployment can stay fully silent towards third parties.

## Walk-through

1. `docker/Dockerfile` (prod stage) takes `ARG REVOCO_VERSION=dev` into `ENV`; `config/revoco.php` exposes `version`
   and an `update_check` block; the stale `version` field left `composer.json` (lock hash refreshed, nothing else).
2. `release.yml` passes the tag's SemVer as build arg to both build steps; the smoke step asserts the baked version.
3. `App\Services\ReleaseChecker` does the one GET and caches `{version, tag}` forever (last good value wins);
   `App\Services\ReleaseInfo` answers running version / newer stable release from config + cache only.
   `revoco:release-check` wraps the checker (opt-out returns early; failure logs a warning and exits non-zero) and is
   scheduled `dailyAt('04:45')->withoutOverlapping()`.
4. `AdminPanelProvider` registers a `SIDEBAR_FOOTER` render hook for `panels/version.blade.php`; strings in
   `panel.version.*` (en + de).
5. `tests/Feature/VersionBadgeTest.php` (18 tests) covers command, opt-out, failure modes, schedule, config fallback and
   all badge cases under `Http::preventStrayRequests()`. Compose, `.env.example` and the README document the opt-out.

Verification: `verify-run.sh` round 1 passed 7/7 (including a prod image built with `REVOCO_VERSION=9.9.9-verify`).

## Decisions

- **Settled by sources** (2026-10-15) — daily, anonymous, opt-out GitHub Releases check by the scheduler; badge only for a
  newer *stable* release; no external call in the request path; "Revoco <version>" at the sidebar foot, logged-in panel
  only, badge "<version> available" linking to the release notes; version baked in at release build from the tag
  (`design/operator-insights.md`, decisions rounds 1 + 2 and working assumption 3).
- **Version source of truth is the git tag, via a build arg baked as `ENV REVOCO_VERSION`** (2026-10-15, judgment call)
  — `composer.json` `version` is dropped instead of kept in sync: Composer's schema docs say the field "should be
  omitted" for VCS-published packages (verified 2026-10-15), and a second hand-maintained copy is what went stale at
  1.0.2. Dropping it changes the lock's content-hash (`version` is one of `Locker::getContentHash`'s relevant keys,
  verified in Composer's source), hence `composer update --lock`. An ENV (not a baked file) keeps it visible in `docker
  inspect`; compose does not pass `REVOCO_VERSION`, so the image value wins.
- **Untagged builds display `dev`, with no badge** (2026-10-15, judgment call) — the same applies to any running version
  that is not SemVer. Release candidates (`v1.1.0-rc.1` builds) bake `1.1.0-rc.1` and do get a badge once the matching
  stable release is out (`version_compare('1.1.0-rc.1', '1.1.0') = -1`, checked with PHP).
- **Opt-out env var `REVOCO_UPDATE_CHECK`, default `true`** (2026-10-15, judgment call) — `REVOCO_` prefix as for the
  other Revoco-specific settings. Set on `app` (render hides the badge when off, even with a cached result) and
  `scheduler` (no request when off); the queue does not need it. The repository (`ChriRas/revoco`) and timeout stay
  code defaults, not env — the entry asks for an opt-out only, and the upstream repo is no operator-specific URL.
- **Stable = `releases/latest` plus a `vX.Y.Z` guard** (2026-10-15) — verified live: anonymous `GET
  /repos/ChriRas/revoco/releases/latest` returns the newest non-prerelease, non-draft release (v1.0.3; prereleases
  v0.6.0/v0.7.0 skipped), anonymous rate limit 60/h per IP. The regex guard also rejects a tag with a suffix that was
  published without the prerelease flag.
- **Result kept in the cache, forever, last good value wins** (2026-10-15, judgment call) — the prod cache store is the
  database (shared SQLite on `app_storage`), so the scheduler writes and the `app` container reads without a new table;
  a failed check keeps the previous value (as the network-data refresh keeps its previous files). Stale is harmless: a
  cached release that is not newer than the running version shows no badge. A `cache:clear` hides the badge until the
  next daily run.
- **Badge link built from the tag, not taken from the API's `html_url`** (2026-10-15, judgment call) —
  `https://github.com/<repository>/releases/tag/<tag>`; deterministic and never renders a URL from a response body.
- **Schedule `dailyAt('04:45')`, badge colour `info`** (2026-10-15, judgment call) — after the 04:15 network refresh;
  the design record only says "coloured badge".
- **Finding for the human: the badge follows published GitHub Releases, not tags** (2026-10-15) — `v1.1.0` is tagged
  (and its image pushed) but has no GitHub Release yet, so today `releases/latest` answers `v1.0.3`. Creating Releases
  stays the human's manual step (automating it in `release.yml` would grow this entry); the README "CI / Release" note
  says so. Not a blocker for this slice.
- **Refactor candidate (autopilot, not applied):** `ReleaseChecker` and `ReleaseInfo` each carry their own `X.Y.Z` regex literal — a single `Semver` helper (or class constant) would keep the "what counts as a version" rule in one place if a third consumer appears.
- **`Depends-On: []`** (2026-10-15) — no code of slice-033 or slice-034 is needed; shared files (`README.md`,
  `.env.example`, `routes/console.php`) only meet at merge.

## Commits

- `862695b` — build(release): bake the release version into the image
- `f06e83f` — feat(panel): show the running version with an update badge
