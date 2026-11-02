# ISO 27001:2022 component risk and control record

## Scope and limitation

This record covers the Contabo–WHMCS connector source, its provider credentials,
provisioning evidence, release artifact, and operational logs. It supports an
organization's ISMS; it is not an ISO 27001 certification or an organization-
wide Statement of Applicability. Management approval, people, physical,
supplier, and runtime infrastructure controls are outside this repository.

The assessment follows ISO 27001 Clause 6.1.2 using likelihood and impact on a
1–5 scale. Scores 20–25 are critical, 15–19 high, 10–14 medium, 5–9 low, and
1–4 minimal.

## Assets

The controlled inventory is maintained in [assets.csv](assets.csv). Critical
assets are the Contabo API credentials and the provisioning/cancellation
workflow. High assets are Service Properties, source, release artifacts, and
audit evidence.

## Curated risk register

| ID | Risk | Inherent | Controls | Residual | Owner | Required treatment/evidence |
|---|---|---:|---|---:|---|---|
| R-01 | Contabo credential compromise enables resource or billing abuse | 20 Critical | Password-type settings, secret-safe logs, fixed TLS hosts, history secret scan | 10 Medium | Security | Dedicated least-privilege API user, WHMCS admin MFA, rotation record, restricted secret access |
| R-02 | Ambiguous provider response creates duplicate purchase or destructive replay | 20 Critical | Durable intent/hash/request ID, locks, fail-closed reconciliation, no unsafe retries | 5 Low | Engineering | Pass controlled ambiguous create/cancel staging tests |
| R-03 | Client power abuse causes race or availability impact | 12 Medium | WHMCS auth, opt-in controls, POST+CSRF, shared lock, 15-second cooldown | 4 Minimal | Operations | Two-account authorization and concurrency staging evidence |
| R-04 | CI or artifact tampering introduces malicious code | 16 High | Commit-pinned actions, least CI permissions, tests, secret scan, commit-addressed ZIP and SHA-256 | 8 Low | DevOps | Protected branch, required reviews/checks, artifact retention and optional signing evidence |
| R-05 | Credentials or tokens leak through source or logs | 20 Critical | No embedded secrets, replacement values, safe context only, no raw bodies, history scan | 5 Low | Security | Inspect real staging logs; document retention and access restrictions |
| R-06 | Provider/WHMCS outage leaves inconsistent authoritative state | 16 High | Service Properties evidence, exact-name reconciliation, rollback and repair runbooks | 8 Low | Operations | Alerting and rehearsed recovery evidence |
| R-07 | Cross-customer invocation exposes or changes another service | 20 Critical | WHMCS service model and custom-action dispatcher, no caller-supplied instance ID | 10 Medium | Security | Mandatory two-account BOLA/BFLA staging test before production |
| R-08 | Security events are logged but not detected or retained | 12 Medium | Structured module events and documented alert codes | 9 Medium | Security | Central alert rules, owner/on-call route, protected retention, quarterly review |

No critical or high residual risk is accepted by this record. Medium residual
risks R-01, R-07, and R-08 block production until the named evidence exists or
management formally accepts them under the organization's risk process.

## Technological control mapping

The evidence-limited automated mapping in
[iso27001-technological-controls.csv](iso27001-technological-controls.csv)
records 11 implemented and 17 partial technological controls (69.6% weighted
component score). Partial does not mean deficient source code; it means the
control also depends on the WHMCS host, identity provider, repository settings,
people, or monitoring platform.

Key implemented component controls:

- A.8.8 technical vulnerability management: tests, static analysis, advisory
  audit, secret scanning, and update automation.
- A.8.9 configuration management: typed/validated product configuration and
  fail-closed defaults.
- A.8.12 data leakage prevention: secret-safe logging and no raw provider body.
- A.8.21 network service security and A.8.24 cryptography: HTTPS-only fixed
  provider endpoints with certificate/hostname verification.
- A.8.25–A.8.28: documented secure lifecycle, security requirements,
  architecture decision, typed boundaries, and abuse-case regression tests.

Key partial controls requiring operational evidence:

- A.8.2/A.8.3/A.8.5: privileged access, authorization, MFA, and account review.
- A.8.13/A.8.14: backup, restore testing, and runtime resilience.
- A.8.15/A.8.16: central logging, alerting, review, and integrity protection.
- A.8.20/A.8.22: WHMCS host network security and segregation.

## Review cadence

Review this record before first staging use, before production authorization,
after a Contabo or WHMCS API/security contract change, after any security
incident, and at least annually. Vulnerability and dependency checks run on
every proposed source change; operational access and alerting evidence should
be reviewed quarterly.
