import type { Log } from '../../../types/logs';

function normalizeMessage(message: string): string {
  return message.trim().toLowerCase();
}

export function errorSignature(log: Log): string {
  const appId = log.application?.id ?? 0;
  if (log.error_code?.id != null) {
    return `${appId}|ec:${log.error_code.id}`;
  }

  return `${appId}|msg:${normalizeMessage(log.message)}`;
}

export function resolveUniqueErrorCount(logs: Log[]): number {
  const unique = new Set<string>();
  for (const log of logs) {
    unique.add(errorSignature(log));
  }

  return unique.size;
}
