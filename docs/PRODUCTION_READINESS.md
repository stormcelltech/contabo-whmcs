# Production readiness and acceptance

Passing repository checks makes a commit release-candidate quality. It does not
prove compatibility with a particular licensed WHMCS installation or Contabo
account. Production authorization requires the staging evidence below.

## Required staging evidence

Run on a non-production WHMCS 9.1 installation using PHP 8.2, 8.3, or 8.4 and a
dedicated least-privilege Contabo API user.

1. Install the exact CI archive and verify its SHA-256 digest.
2. Configure one low-cost test product with an exact current product ID, image
   UUID, region, and one-month contract. Keep automatic cancellation disabled.
3. Place and pay one test order. Verify exactly one Contabo instance exists,
   WHMCS becomes Active, `Contabo Instance ID` is populated, and no credential
   appears in the module log.
4. Invoke CreateAccount again. Verify it returns success without purchasing a
   second instance. Exercise suspend, unsuspend, and optional client power.
5. Simulate a transport failure after create in an isolated test account. Verify
   the operation becomes ambiguous and a retry only reconciles; it must never
   submit a second create.
6. Enable automatic cancellation only for the test product, terminate it, and
   verify the intended Contabo cancellation date and WHMCS status. Invoke it
   again and verify no second cancellation request is sent.
7. Review Contabo billing, WHMCS activity/module logs, customer visibility, and
   the rollback procedure. Record approver, timestamp, commit, artifact digest,
   WHMCS/PHP versions, and test service/instance IDs.

Any failed or missing item blocks production.

## Rollback

1. Disable automatic module actions for affected products.
2. Preserve `Contabo Instance ID`, create status/request ID/hash, termination
   status, and WHMCS module logs. These are reconciliation evidence.
3. Restore the previously accepted module directory atomically.
4. Do not delete or re-create a Contabo instance solely because WHMCS reports a
   module error. Reconcile WHMCS service properties with the Contabo control
   panel first.
5. Re-run read-only instance status checks before re-enabling automation.

## Operational alerts

Alert on these module-log actions or codes:

- `create_outcome_ambiguous` or `create_manual_reconciliation_required`: human
  reconciliation required; never reset blindly.
- `termination_manual_reconciliation_required`: verify provider cancellation
  and billing before changing any termination evidence or retrying.
- `duplicate_instances_detected`: freeze provisioning for the product and
  investigate billing immediately.
- repeated `authentication_failed` or `authentication_transport_failure`:
  check API-user status, credential rotation, egress, DNS, and TLS.
- repeated `api_http_429`: reduce provisioning concurrency and contact Contabo
  if throttling persists.

For ambiguous creates, follow
[AMBIGUOUS_CREATE_REPAIR.md](AMBIGUOUS_CREATE_REPAIR.md). The governing design
decision is [ADR-0001](ADR-0001-IDEMPOTENT-PROVISIONING.md).
For uncertain cancellations, follow
[AMBIGUOUS_TERMINATION_REPAIR.md](AMBIGUOUS_TERMINATION_REPAIR.md).

Security authorization also requires closure of every staging item in the
[penetration-test report](security/PENETRATION_TEST_REPORT.md) and treatment or
formal acceptance of the medium residual risks in the
[ISO 27001 risk record](security/ISO27001_RISK_AND_CONTROL_RECORD.md).
