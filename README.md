# Contabo provisioning for WHMCS

A fail-closed WHMCS server module for automated Contabo instance creation,
suspension, unsuspension, cancellation, status display, and optional customer
power controls.

This release replaces the legacy provisioning path with typed contracts,
durable idempotency evidence, fail-closed recovery, security tests, and an
auditable release process. It is not an official Contabo or WHMCS product.

## Supported platform

- WHMCS 9.1 on its supported Linux/LAMP deployment model
- PHP 8.2, 8.3, or 8.4 with cURL and JSON
- Contabo Compute Management API v1
- MySQL/MariaDB with support for `GET_LOCK`

Older WHMCS/PHP versions are intentionally unsupported because they are no
longer an acceptable production-security baseline.

## Safety model

- **Durable create intent:** request ID, request hash, status, and resulting
  instance ID are stored as WHMCS Service Properties.
- **Concurrency control:** a database advisory lock serializes create and
  terminate commands for each service.
- **Ambiguous-outcome protection:** create calls are never automatically
  retried after an uncertain response. A later WHMCS retry searches the exact
  deterministic display name and adopts it only when exactly one match exists.
- **Bounded safe retries:** GET and idempotent power actions retry 408, 425, 429,
  and 5xx responses with capped exponential backoff. Purchase, cancellation,
  and other non-idempotent operations do not.
- **Secret-safe observability:** module logs contain service, instance, request,
  and safe error codes; configured credentials are supplied to WHMCS as log
  replacement values and raw provider response bodies are not logged.
- **Fail-closed destruction:** automatic Contabo cancellation is disabled by
  default. Client power controls are optional, POST-only, and CSRF validated.
- **Transport hardening:** fixed official HTTPS endpoints, certificate and
  hostname verification, no redirects, finite connect/response timeouts, and
  cryptographic UUIDv4 request IDs.

## Install

1. Download the CI-built archive and verify its `.sha256` file.
2. Extract the contained `contabo` directory to
   `WHMCS_ROOT/modules/servers/contabo`.
3. In WHMCS, open **System Settings → Products/Services → Products**, select the
   product, and choose **Contabo Cloud Compute** as its module.
4. Configure:
   - OAuth2 Client ID and Client Secret;
   - a dedicated least-privilege Contabo API user and password;
   - exact region, current product ID, and image UUID;
   - hostname prefix and contract period;
   - optional client power controls.
5. Keep **Automatic Cancellation** disabled until staging has verified the
   account's contract/cancellation behavior.

Do not manually create an `instanceId` custom field. WHMCS Service Properties
create and own `Contabo Instance ID` and the idempotency evidence automatically.

## Lifecycle behavior

| WHMCS command | Contabo action | Retry semantics |
|---|---|---|
| CreateAccount | `POST /v1/compute/instances` | Never automatically retried after an ambiguous outcome |
| SuspendAccount | `POST .../actions/stop` | Bounded retry; convergent action |
| UnsuspendAccount | `POST .../actions/start` | Bounded retry; convergent action |
| TerminateAccount | `POST .../cancel` | Opt-in; durable intent; ambiguous outcomes block replay |
| ClientPowerOn | `POST .../actions/start` | Optional, POST + CSRF, common lock and cooldown |
| ClientPowerOff | `POST .../actions/stop` | Optional, POST + CSRF, common lock and cooldown |
| ClientReboot | `POST .../actions/restart` | Optional, POST + CSRF, common lock and cooldown |

WHMCS retains ownership of billing and service state. Contabo owns the external
compute resource. This module records provider observations and identifiers; it
does not infer payment state from Contabo.

## Development and verification

```bash
composer install
composer check
composer audit --locked
```

`composer check` runs PHP syntax lint, PHPUnit contract tests, and PHPStan level
8. CI executes the suite on PHP 8.2, 8.3, and 8.4, audits locked dependencies,
scans Git history for secrets, and produces a commit-addressed ZIP plus SHA-256
digest. CI never deploys.

The repository tests use a fake transport and never call Contabo or create
billable resources. A real staging acceptance is mandatory before production;
follow [docs/PRODUCTION_READINESS.md](docs/PRODUCTION_READINESS.md).

## Recovery from an ambiguous create

Do not clear Service Properties or click Create repeatedly. Re-run the WHMCS
module command once: it performs a read-only search for the deterministic
display name. Exactly one match is adopted; zero or multiple matches remain
blocked for operator reconciliation. Verify the Contabo control panel and
billing before resetting any evidence. Follow the reviewed
[ambiguous-create repair runbook](docs/AMBIGUOUS_CREATE_REPAIR.md); the safety
trade-off is recorded in
[ADR 0001](docs/ADR-0001-IDEMPOTENT-PROVISIONING.md).

An ambiguous cancellation is also fail-closed. Follow the
[termination repair runbook](docs/AMBIGUOUS_TERMINATION_REPAIR.md); never clear
its request evidence or submit another cancellation merely to remove a WHMCS
error.

The authorized white-box assessment and ISO 27001 component risk record are in
[docs/security](docs/security). They intentionally keep production blocked
until the listed WHMCS staging and operational controls have evidence.

## License and security

MIT licensed. See [LICENSE](LICENSE) and [SECURITY.md](SECURITY.md).
