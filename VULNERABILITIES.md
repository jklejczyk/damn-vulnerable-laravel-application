# Vulnerabilities

> **Warning:** this application is intentionally insecure. Run it only locally
> or in an isolated Docker container, never on a publicly reachable server.

This file lists every vulnerability deliberately planted in DVLA, where it lives
and which [Security Toolkit for Laravel](https://github.com/jklejczyk/security-toolkit-for-laravel)
rule detects it. Each new toolkit check comes with a matching entry here.

**Status:** `planned` (described, not yet in the code), `implemented` (present
and detected by the toolkit), `not reproducible` (covered only by the toolkit's
own tests, reason given).

## Overview

| ID | Vulnerability | Severity | CWE | CVSS | Toolkit rule | Status |
| --- | --- | --- | --- | --- | --- | --- |
| DVLA-001 | Debug mode enabled in production | High | CWE-209 | 7.5 | `config.debug-enabled` | implemented |
| DVLA-002 | Telescope dashboard open to every logged-in user | High | CWE-862 | 7.6 | `config.dashboard-open` | implemented |
| DVLA-003 | Publicly known `APP_KEY` | Critical | CWE-321 | 9.1 | `config.app-key-known` | implemented |
| DVLA-004 | Missing `APP_KEY` | Medium | CWE-1391 | 5.3 | `config.app-key-missing` | not reproducible |
| DVLA-005 | Horizon dashboard open to every logged-in user | High | CWE-862 | 7.1 | `config.dashboard-open` | implemented |
| DVLA-006 | Pulse dashboard open to every logged-in user | Medium | CWE-862 | 4.3 | `config.dashboard-open` | implemented |
| DVLA-007 | Log Viewer open to every logged-in user | High | CWE-862 | 7.6 | `config.dashboard-open` | implemented |
| DVLA-008 | Default filesystem disk is public | Low | CWE-552 | 3.7 | `files.default-disk-public` | implemented |
| DVLA-009 | Private storage linked into the public directory | Medium | CWE-552 | 5.9 | `files.private-storage-linked` | implemented |
| DVLA-010 | Session cookie without the Secure flag | Medium | CWE-614 | 6.4 | `auth.session-cookie-insecure` | implemented |
| DVLA-011 | Session cookie readable by JavaScript | Medium | CWE-1004 | — | `auth.session-cookie-script-accessible` | implemented |
| DVLA-012 | Default password rule allows 6 characters | Medium | CWE-521 | — | `auth.password-too-short` | implemented |
| DVLA-013 | Default password rule accepts leaked passwords | Medium | CWE-521 | — | `auth.password-not-checked-for-leaks` | implemented |
| DVLA-014 | Sanctum tokens never expire | Medium | CWE-613 | — | `auth.sanctum-tokens-never-expire` | implemented |
| DVLA-015 | Vulnerable `dompdf/dompdf` 2.0.0 | Critical | CWE-1395 | — | `dependencies.vulnerable-package` | implemented |
| DVLA-016 | Abandoned `doctrine/cache` | Info | CWE-1104 | — | `dependencies.abandoned-package` | implemented |
| DVLA-017 | Advisory without a published severity | Unknown | CWE-1395 | — | `dependencies.vulnerable-package` | not reproducible |

## How to scan

The toolkit is installed from Packagist. To scan with a local working copy of
`../security-toolkit-for-laravel`, link it with the
[composer-link](https://github.com/SanderSander/composer-link) plugin:
`composer link --no-scripts ../security-toolkit-for-laravel`, then
`php artisan package:discover` (`composer unlink` goes back to the release).

A plain scan reports everything that comes from code and dependencies:
DVLA-009, DVLA-012 to DVLA-016.

```bash
php artisan security:scan
```

Production misconfigurations live in `.env.production`. Laravel loads it when
a command runs with `--env=production`, which adds the rules that read `.env`
values (DVLA-001 to DVLA-008, DVLA-010 and DVLA-011):

```bash
php artisan security:scan --env=production
```

Composer 2.9 refuses to install packages with known advisories, so
`composer.json` sets `audit.block-insecure` to `false`. This does not hide
anything from `composer audit`.

## Details

### DVLA-001: Debug mode enabled in production

- **Where:** `.env.production` (`APP_DEBUG=true`)
- **Why it is dangerous:** any error renders a detailed page with the stack
  trace, source code, SQL queries with their values and request data such as
  headers, cookies and form fields. Secrets leak whenever they end up in an
  exception message (for example an API key in a failed request URL), and
  older Laravel versions and Ignition also exposed environment variables or
  allowed remote code execution (CVE-2021-3129). On Laravel 13 the database
  password and `APP_KEY` are not shown directly.
- **Fix:** set `APP_DEBUG=false` in every production environment.
- **Classification:** CWE-209, CVSS 7.5 (`CVSS:3.1/AV:N/AC:L/PR:N/UI:N/S:U/C:H/I:N/A:N`), high
- **Detected by:** `config.debug-enabled`

### DVLA-002: Telescope dashboard open to every logged-in user

- **Where:** `app/Providers/TelescopeServiceProvider.php` (the `viewTelescope`
  gate returns `true` for any user)
- **Why it is dangerous:** every logged-in user can open the dashboard. In
  production Telescope records exceptions, failed requests, failed jobs and
  scheduled tasks with their payloads and context (passwords, cookies and the
  `Authorization` header are hidden by default), and its Monitoring page lets
  anyone start recording every request of a chosen user by tag.
- **Fix:** allow only specific users in the `viewTelescope` gate, or do not run
  Telescope in production at all.
- **Classification:** CWE-862, CVSS 7.6 (`CVSS:3.1/AV:N/AC:L/PR:L/UI:N/S:U/C:H/I:L/A:L`), high;
  `A:L` because any logged-in user can clear all recorded entries
- **Detected by:** `config.dashboard-open`

### DVLA-003: Publicly known `APP_KEY`

- **Where:** `.env.production` (`APP_KEY` is the default key shipped with
  Snipe-IT, listed in `wordlists/snipe-it_default.txt` of
  [synacktiv/laravel-crypto-killer](https://github.com/synacktiv/laravel-crypto-killer))
- **Why it is dangerous:** with a known key an attacker can decrypt
  everything the application encrypts (cookies, encrypted model attributes,
  encrypted jobs) and forge signed URLs and encrypted payloads. If the
  application decrypts attacker-controlled data with unserialization, for
  example `decrypt()` on request input or the cookie session driver with PHP
  serialization, this leads to remote code execution.
- **Fix:** generate a unique key with `php artisan key:generate` and keep it
  out of version control. Do not move the leaked key to `APP_PREVIOUS_KEYS`:
  Laravel would keep decrypting cookies and accepting signed URLs made with it.
- **Classification:** CWE-321, CVSS 9.1 (`CVSS:3.1/AV:N/AC:L/PR:N/UI:N/S:U/C:H/I:H/A:N`), critical
- **Detected by:** `config.app-key-known`

### DVLA-004: Missing `APP_KEY`

- **Why it is dangerous:** Laravel cannot encrypt cookies or sessions, so
  requests that need them fail with an error, and signed URLs are signed with
  an empty key: anyone can forge a valid signature for routes that work without
  the encrypter, such as API routes.
- **Not reproducible here:** `.env.production` already carries the known key
  from DVLA-003, and a second env file would not be treated as production
  (`--env=<name>` sets the environment to `<name>`). The rule is covered by
  the toolkit's own tests.
- **Classification:** CWE-1391, CVSS 5.3 (`CVSS:3.1/AV:N/AC:L/PR:N/UI:N/S:U/C:N/I:L/A:N`), medium
- **Detected by:** `config.app-key-missing`

### DVLA-005: Horizon dashboard open to every logged-in user

- **Where:** `app/Providers/HorizonServiceProvider.php` (the `viewHorizon` gate
  returns `true` for any user)
- **Why it is dangerous:** Horizon shows the data of pending, completed and
  failed jobs, which often includes personal data and tokens, and lets the
  user retry failed jobs.
- **Fix:** allow only specific users in the `viewHorizon` gate.
- **Classification:** CWE-862, CVSS 7.1 (`CVSS:3.1/AV:N/AC:L/PR:L/UI:N/S:U/C:H/I:L/A:N`), high
- **Detected by:** `config.dashboard-open`

### DVLA-006: Pulse dashboard open to every logged-in user

- **Where:** `app/Providers/AppServiceProvider.php`, `configureDashboards()`
  (the `viewPulse` gate returns `true` for any user)
- **Why it is dangerous:** Pulse shows the most active users with their names
  and email addresses, slow requests and queries, and exceptions, revealing
  user identities and internals of the application.
- **Fix:** allow only specific users in the `viewPulse` gate.
- **Classification:** CWE-862, CVSS 4.3 (`CVSS:3.1/AV:N/AC:L/PR:L/UI:N/S:U/C:L/I:N/A:N`), medium
- **Detected by:** `config.dashboard-open`

### DVLA-007: Log Viewer open to every logged-in user

- **Where:** `app/Providers/AppServiceProvider.php`, `configureDashboards()`
  (the `viewLogViewer` gate returns `true` for any user)
- **Why it is dangerous:** Log Viewer lets the user read and download the
  application logs, which often contain personal data, tokens and exception
  details, and by default also delete them, which can hide traces of an
  attack.
- **Fix:** allow only specific users in the `viewLogViewer` gate, and restrict
  the `deleteLogFile` and `downloadLogFile` gates.
- **Classification:** CWE-862, CVSS 7.6 (`CVSS:3.1/AV:N/AC:L/PR:L/UI:N/S:U/C:H/I:L/A:L`), high
- **Detected by:** `config.dashboard-open`

### DVLA-008: Default filesystem disk is public

- **Where:** `.env.production` (`FILESYSTEM_DISK=public`)
- **Why it is dangerous:** files stored without naming a disk, for example
  with `$request->file('document')->store('documents')` or `Storage::put()`,
  land on the default disk. The `public` disk is served through the
  `public/storage` link, so anyone who knows or guesses such a file's path can
  download it, including uploads that were meant to stay private.
- **Fix:** set `FILESYSTEM_DISK=local` and name the public disk explicitly
  where a file must be public, for example `->store('avatars', 'public')`.
- **Classification:** CWE-552, CVSS 3.7 (`CVSS:3.1/AV:N/AC:H/PR:N/UI:N/S:U/C:L/I:N/A:N`), low
- **Detected by:** `files.default-disk-public`

### DVLA-009: Private storage linked into the public directory

- **Where:** `config/filesystems.php` (the link `public/backups` points to
  `storage/app`)
- **Why it is dangerous:** `storage/app` contains the private `local` disk
  (`storage/app/private`), so every private file can be downloaded without
  logging in by anyone who knows or guesses its path.
- **Fix:** link only the public disk directory (`storage/app/public`), delete
  the link and run `php artisan storage:link` again. Serve private files
  through a controller that checks authorization, or with temporary signed
  URLs.
- **Classification:** CWE-552, CVSS 5.9 (`CVSS:3.1/AV:N/AC:H/PR:N/UI:N/S:U/C:H/I:N/A:N`), medium
- **Detected by:** `files.private-storage-linked`

### DVLA-010: Session cookie without the Secure flag

- **Where:** `.env.production` (`SESSION_SECURE_COOKIE=false`; leaving it out
  has the same effect, because Laravel's default is `null`)
- **Why it is dangerous:** browsers also send the session cookie over plain
  HTTP: on a request typed without `https://`, through an `http://` link or
  redirect, or before HSTS takes effect. Anyone on the same network can copy
  the cookie and take over the session.
- **Fix:** set `SESSION_SECURE_COOKIE=true` and serve the application only
  over HTTPS, ideally with HSTS.
- **Classification:** CWE-614, CVSS 6.4 (`CVSS:3.1/AV:A/AC:H/PR:N/UI:R/S:U/C:H/I:H/A:N`), medium
- **Detected by:** `auth.session-cookie-insecure`

### DVLA-011: Session cookie readable by JavaScript

- **Where:** `.env.production` (`SESSION_HTTP_ONLY=false`)
- **Why it is dangerous:** any script on the page can read the session cookie
  through `document.cookie`, so a single XSS vulnerability or a compromised
  third-party script is enough to steal sessions.
- **Fix:** set `SESSION_HTTP_ONLY=true` (the Laravel default).
- **Classification:** CWE-1004, no CVSS vector (harmful only together with XSS), medium
- **Detected by:** `auth.session-cookie-script-accessible`

### DVLA-012: Default password rule allows 6 characters

- **Where:** `app/Providers/AppServiceProvider.php`, `configureDefaults()`
  (`Password::min(6)` in production); applied in
  `app/Concerns/PasswordValidationRules.php`
- **Why it is dangerous:** short passwords fall quickly to guessing and
  credential-stuffing attacks. OWASP ASVS and NIST SP 800-63B require at
  least 8 characters.
- **Fix:** raise the minimum, for example `Password::min(12)->uncompromised()`.
- **Classification:** CWE-521, no CVSS vector (hardening against password attacks), medium
- **Detected by:** `auth.password-too-short`

### DVLA-013: Default password rule accepts leaked passwords

- **Where:** same as DVLA-012 (no `uncompromised()`)
- **Why it is dangerous:** users can choose passwords that already appear in
  public breach lists, and attackers try exactly those first.
- **Fix:** add `->uncompromised()`. It checks Have I Been Pwned with
  k-anonymity: only the first five characters of the SHA-1 hash leave the
  server.
- **Classification:** CWE-521, no CVSS vector (hardening against credential stuffing), medium
- **Detected by:** `auth.password-not-checked-for-leaks`

### DVLA-014: Sanctum tokens never expire

- **Where:** `app/Http/Controllers/ApiTokenController.php` (`createToken()`
  without an expiry date, route `POST /api-tokens`), `app/Models/User.php`
  (`HasApiTokens`) and Sanctum's default `sanctum.expiration` of `null` (the
  config file is not published)
- **Why it is dangerous:** personal access tokens stay valid until someone
  deletes them, so a token leaked through logs, a browser extension, a mobile
  app backup or a former employee keeps working indefinitely.
- **Fix:** set `expiration` in `config/sanctum.php` or pass an expiry date to
  `createToken()`, and schedule `php artisan sanctum:prune-expired`.
- **Classification:** CWE-613, no CVSS vector (matters only once a token leaks), medium
- **Detected by:** `auth.sanctum-tokens-never-expire`

### DVLA-015: Vulnerable `dompdf/dompdf` 2.0.0

- **Where:** `composer.json` (`dompdf/dompdf` pinned to `2.0.0`, which also
  pulls in a vulnerable `phenx/php-svg-lib`)
- **Why it is dangerous:** the installed versions have published advisories,
  among them remote file inclusion, URI validation bypass in SVG parsing
  (CVE-2023-23924) and a restriction bypass in `php-svg-lib` that can lead to
  remote code execution.
- **Fix:** `composer update dompdf/dompdf` to a version outside the affected
  ranges.
- **Classification:** CWE-1395; severity comes from each advisory, no CVSS
  vector (`composer audit` does not publish one).
  CVEs: CVE-2022-41343, CVE-2023-23924, CVE-2023-50262, CVE-2026-55554,
  CVE-2026-55555, CVE-2026-56722, CVE-2026-59941, CVE-2026-59942,
  CVE-2026-59943 (`dompdf/dompdf`), CVE-2023-50251, CVE-2024-25117
  (`phenx/php-svg-lib`); one `php-svg-lib` advisory has no CVE.
- **Detected by:** `dependencies.vulnerable-package` (one finding per
  advisory)

### DVLA-016: Abandoned `doctrine/cache`

- **Where:** `composer.json` (`doctrine/cache`)
- **Why it is dangerous:** the maintainers no longer support the package, so
  future security issues may never be fixed or even published as advisories.
- **Fix:** move to a maintained cache library, for example
  `symfony/cache` (Composer names no replacement for this package).
- **Classification:** CWE-1104, no CVSS vector (maintenance risk), info
- **Detected by:** `dependencies.abandoned-package`

### DVLA-017: Advisory without a published severity

- **Not reproducible here:** every advisory for the packages installable in
  this application has a severity. An advisory without one is reported with
  the `unknown` severity, which meets every `--fail-on` threshold. The rule is
  covered by the toolkit's own tests.
- **Classification:** CWE-1395; no severity or CVSS, because the advisory publishes none.
- **Detected by:** `dependencies.vulnerable-package`
