# DVLA – Damn Vulnerable Laravel Application

> **Warning:** this application is intentionally insecure. Run it only locally
> or in an isolated Docker container, never on a publicly reachable server.

DVLA is an invoicing app for small businesses with security bugs planted on
purpose. Companies issue invoices to their clients, send them by email, get
paid through a payment gateway webhook and connect their accounting software
through an API. Each planted bug fits that story ("an accountant of Acme edits
an invoice of Globex") and is listed in [VULNERABILITIES.md](VULNERABILITIES.md),
together with the [Security Toolkit for Laravel](https://github.com/jklejczyk/security-toolkit-for-laravel)
rule that detects it. Everything that is not on that list is meant to be secure.

Built with Laravel 13, Livewire 4 and Flux.

## Getting started

```bash
composer install --no-scripts
cp .env.example .env
php artisan key:generate
php artisan migrate:fresh --seed
npm install && npm run build
composer run dev
```

## Accounts

Every account uses the password `password`.

| Email | Company | Role | Can |
| --- | --- | --- | --- |
| `admin@dvla.test` | – | operator | everything in every company: lists of all users, invoices, clients and payments, editing any invoice, and "Manage as operator" to use every page of a company with full rights; dashboards (Telescope, Horizon, Pulse, Log Viewer) |
| `owner@acme.test` | Acme | owner | everything in the company: invoices, clients, team, settings, API tokens |
| `accountant@acme.test` | Acme | accountant | invoices and clients |
| `member@acme.test` | Acme | member | reads invoices, creates drafts |
| `owner@globex.test` | Globex | owner | as above, for Globex |
| `accountant@globex.test` | Globex | accountant | as above, for Globex |
| `member@globex.test` | Globex | member | as above, for Globex |

Each company has three clients and five invoices (`ACME/2026/001` to
`ACME/2026/005`): paid, sent, overdue, draft and void. Signing up creates a new
company with you as its owner.

## Scanning

See [VULNERABILITIES.md](VULNERABILITIES.md#how-to-scan).
