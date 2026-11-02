# Authorized white-box security assessment

- Assessment date: 2026-11-02
- Target: local `contabo-whmcs` feature branch
- Assessment type: source-assisted, non-destructive penetration test
- Authorization: repository owner requested testing of the local connector
- Overall result: **no open critical or high source-code findings**
- Production decision: **blocked pending staging security acceptance**

## Scope and rules of engagement

Included: PHP source, Smarty template, unit-test abuse cases, Composer lock
file, Git history secret patterns, and GitHub Actions configuration.

Excluded: live WHMCS, Contabo accounts, provider infrastructure, GitHub account
settings, network scanning, credential testing, social engineering, and access
to customer data. No external endpoint was probed and no billable resource was
created. Test payloads were executed only against validation code or fakes.

## Methodology and evidence

- OWASP Top 10:2021 source-assisted review and business-logic abuse analysis.
- Automated API checklist: 28 checks generated; source scanner reported zero
  pattern findings.
- High-confidence secret scan of the working tree and every reachable Git
  commit: zero candidates.
- Composer advisory and abandoned-package audit: clean.
- PHP syntax lint, PHPStan level 8, and focused security regression tests.
- Manual review of access-control boundaries, SQL binding, output encoding,
  CSRF, SSRF, transport security, logging, retries, locking, and supply chain.

## Findings

| ID | Severity | Finding | Status |
|---|---:|---|---|
| PT-001 | Medium | Ambiguous cancellation could be submitted again on a later WHMCS retry | Remediated |
| PT-002 | Medium | Concurrent or rapid client power commands lacked a shared mutation lock and local throttle | Remediated |

### PT-001 — Ambiguous destructive-operation replay

An I/O failure after Contabo accepted a cancellation left no durable attempt
state. A later WHMCS retry could submit the destructive request again. The fix
stores request ID, request hash, and `pending` status before the provider call;
ambiguous outcomes become fail-closed and require operator reconciliation.
Non-ambiguous rejection becomes `failed`, while confirmed success becomes
`completed`. Regression coverage proves that an ambiguous cancellation is sent
only once.

### PT-002 — Power-command race and provider abuse

Authenticated customers could issue rapid sequential or concurrent start,
stop, and restart operations. The fix serializes suspend, unsuspend,
cancellation, and client power actions under one per-service advisory lock and
applies a 15-second per-service client power cooldown persisted in WHMCS
Service Properties. POST and WHMCS CSRF validation remain mandatory.

## OWASP coverage

| Category | Result | Evidence or limitation |
|---|---|---|
| A01 Broken Access Control | Partial | Client functions are explicit, opt-in, POST-only, and dispatched by WHMCS. Cross-customer isolation requires two-account staging proof. |
| A02 Cryptographic Failures | Pass in component | HTTPS-only fixed hosts, peer/hostname verification, no redirects, cryptographic UUIDv4 identifiers, no embedded secrets. |
| A03 Injection | Pass in component | Strict identifier allowlists, bound SQL parameters, contextual HTML escaping, and no command execution or dynamic includes. Malicious payload tests pass. |
| A04 Insecure Design | Pass in component | Durable intent, ambiguous-outcome blocking, common mutation lock, client cooldown, and destructive action opt-in. |
| A05 Security Misconfiguration | Partial | Safe defaults are implemented. Production PHP, web server, file permissions, and WHMCS configuration require staging evidence. |
| A06 Vulnerable Components | Pass | Locked development dependencies have no known advisories; production artifact has no third-party runtime library. |
| A07 Authentication Failures | Inherited/partial | Authentication and session management are owned by WHMCS and Contabo. MFA and session policy require deployment evidence. |
| A08 Integrity Failures | Pass in pipeline design | CI actions are commit-pinned; package is commit-addressed with SHA-256; packaging waits for tests and history secret scan. Branch protection and artifact signing remain operator controls. |
| A09 Logging Failures | Partial | Secrets and raw responses are excluded; safe request/service identifiers are logged. Central alerting, retention, and immutable storage remain operator controls. |
| A10 SSRF | Pass | Outbound hosts and HTTPS scheme are constants; redirects are disabled; no user-controlled URL reaches cURL. |

## Residual staging tests

These tests require an explicitly authorized non-production WHMCS installation:

1. Use two customer accounts and prove one customer cannot invoke power actions
   or read instance details for the other's service.
2. Replay client power requests without a CSRF token, with another session's
   token, and with GET; each must fail.
3. Confirm the 15-second cooldown and common mutation lock under concurrent
   requests without creating extra provider actions.
4. Induce controlled post-send failures for create and cancellation against a
   disposable low-cost service; prove no automatic replay occurs.
5. Inspect real module/activity logs and prove credentials, bearer tokens,
   response bodies, and customer PII are absent.

Until these tests pass on the exact release artifact, this report is not a
production penetration-test attestation.
