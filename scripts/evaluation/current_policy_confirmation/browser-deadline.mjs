export const DELIVERY_ALLOWANCE_MS = 15_000;
export function completionDeadline(submittedAtMs, runTimeoutSeconds, overallDeadlineMs, authoritativeDeadlineMs = null) {
  if (![submittedAtMs, overallDeadlineMs].every(Number.isFinite) || !Number.isInteger(runTimeoutSeconds) || runTimeoutSeconds <= 0) throw Error('Unbound browser deadline');
  const backend = authoritativeDeadlineMs ?? submittedAtMs + runTimeoutSeconds * 1000;
  if (!Number.isFinite(backend)) throw Error('Invalid authoritative deadline');
  return Math.min(backend + DELIVERY_ALLOWANCE_MS, overallDeadlineMs);
}
export function assertBeforeDeadline(now, deadline) {
  if (now >= deadline) throw Error('Browser completion deadline expired');
}
export function settledPresentation(state, run, text, citations) {
  return state.runId === run.id && state.pending === 'false' && state.progress === ''
    && state.announcement === (run.status === 'clarification_required' ? 'Clarification required.' : 'Answer complete.')
    && state.text === text && state.count === (run.assistant_message_id ? 1 : 0)
    && JSON.stringify(state.citations) === JSON.stringify(citations);
}
