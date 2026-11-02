# Ambiguous termination repair runbook

Use this runbook when the module reports
`termination_manual_reconciliation_required`. Automatic resubmission is blocked
because the earlier cancellation may already have reached Contabo.

1. Disable automatic module actions for the affected service.
2. Preserve the instance ID, termination status, request ID/hash, module log,
   Contabo account activity, and billing evidence.
3. Confirm in the Contabo control panel or through support whether cancellation
   is scheduled and record the effective date.
4. If cancellation is confirmed, set `Contabo Termination Status` to
   `completed` through a reviewed WHMCS-supported administrative repair.
5. If Contabo confirms that no cancellation exists, obtain a second operator's
   approval, preserve the incident evidence, and clear only the termination
   status/request ID/hash before invoking Terminate once.
6. If the result remains uncertain, do not retry and escalate to Contabo.

Do not modify the WHMCS database directly and do not cancel the provider
resource manually without reconciling the WHMCS service and billing state.
