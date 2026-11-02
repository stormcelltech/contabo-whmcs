# ADR 0001: Fail-closed provisioning idempotency

- Status: Accepted
- Date: 2026-11-02

## Context

Creating a Contabo instance is billable and the provider create endpoint does
not expose an idempotency-key contract. A timeout or broken connection after a
successful provider-side create therefore has an ambiguous outcome. Blindly
retrying can purchase a duplicate instance.

## Decision

The module owns one durable provisioning intent per WHMCS service. Before the
external call it stores a cryptographic request ID, a hash of the immutable
request, and `pending` status in WHMCS Service Properties while holding a
database advisory lock. A successful response stores the provider instance ID.

Create calls are never transport-retried. If the outcome is ambiguous, later
attempts perform only an exact-name lookup using the deterministic name
`<configured-prefix>-<WHMCS-service-id>`:

- one match is adopted;
- no match remains blocked until an operator proves that no resource or charge
  exists and explicitly clears the attempt evidence;
- multiple matches remain blocked and raise a duplicate-resource alert.

The request hash prevents changed product, image, region, or contract settings
from silently reusing old evidence. Per-service advisory locks prevent
concurrent module workers from starting competing attempts.

## Consequences

- A transient ambiguous failure can require manual reconciliation, trading
  availability for protection against duplicate spend.
- Provider display names must remain unique and must not be edited while an
  attempt is unresolved.
- WHMCS Service Properties and the Contabo control panel are both required for
  incident investigation; neither may be cleared merely to make a retry pass.
- Staging must exercise the ambiguous-outcome and concurrent-invocation paths
  before production authorization.
