# Security policy

## Supported runtime

This module targets supported WHMCS releases on PHP 8.2, 8.3, or 8.4. Do not
deploy it on an end-of-life WHMCS or PHP release.

## Reporting a vulnerability

Do not open a public issue containing credentials, customer data, request
bodies, or exploitable details. Report privately to the repository owner and
include the affected commit, WHMCS/PHP versions, reproduction steps, and a
redacted module-log correlation ID.

## Security invariants

- Contabo credentials are password-type WHMCS product settings and are never
  included in ordinary module-log request payloads.
- All API traffic is fixed to the official HTTPS endpoints with certificate
  and hostname verification enabled; redirects are refused.
- Client state changes require authenticated WHMCS ownership, POST, and the
  WHMCS CSRF token.
- A create operation with an unknown provider outcome is never retried
  automatically. It must reconcile to exactly one deterministic display name.
- Automatic Contabo cancellation is disabled by default and is idempotently
  recorded only after a successful API response.
- API errors shown to users contain bounded safe messages and request IDs, not
  access tokens, passwords, response bodies, or stack traces.
