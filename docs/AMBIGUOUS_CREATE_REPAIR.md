# Ambiguous create repair runbook

Use this runbook only when the module reports `create_outcome_ambiguous` or
`create_manual_reconciliation_required`. Do not place another order, rename a
possible matching instance, or clear evidence during investigation.

## Reconcile

1. Disable automatic module actions for the affected WHMCS service.
2. Record the service ID, expected display name, request ID, request hash,
   timestamp, module-log trace ID, and current Contabo billing state.
3. Search the Contabo control panel and API for the exact deterministic display
   name. Also check recently created resources and invoices in case indexing is
   delayed.
4. If exactly one matching resource exists, run **Create** once from WHMCS. The
   module will adopt its instance ID without submitting another purchase.
5. If multiple possible resources exist, keep provisioning disabled, preserve
   every identifier, and escalate to Contabo before cancelling anything.
6. If no resource or charge exists, wait through the account's observed API
   consistency window and repeat the search. Obtain a second operator's review
   before resetting the attempt.

## Reset after a proved zero-result outcome

WHMCS Service Properties appear as service custom fields. From the affected
service's administrative view, preserve an incident copy and then clear only:

- `Contabo Create Status`
- `Contabo Create Request ID`
- `Contabo Create Request Hash`

Do not clear `Contabo Instance ID` if it has a value. Re-enable the module
action for this service and invoke **Create** once. Record the new request ID
and verify that exactly one provider resource and one charge result.

If the WHMCS installation does not expose these properties for controlled
administrative editing, stop. Do not update the database directly; use a
reviewed one-time repair command built against that installation's supported
WHMCS API.
