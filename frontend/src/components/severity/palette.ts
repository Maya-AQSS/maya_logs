import type { LogSeverity } from '../../types/logs';

export type SeverityKey = LogSeverity | 'all';

export type SeverityCardPalette = {
  background: string;
  text: string;
  border: string;
};

/**
 * Paleta de severidad (badges y tarjetas): contrastes revisados con QA —
 * Low gris azulado (no verde “resuelto”), Medium azul vs High ámbar, Critical rojo.
 */
export const SEVERITY_BADGE_CLASSES: Record<LogSeverity, string> = {
  critical:
    'inline-flex items-center rounded-full bg-[#DC2626]/15 px-2 py-0.5 text-xs font-semibold text-[#991B1B] ring-1 ring-inset ring-[#DC2626]/25 dark:bg-[#DC2626]/25 dark:text-[#FECACA] dark:ring-[#DC2626]/40',
  high: 'inline-flex items-center rounded-full bg-[#F59E0B]/15 px-2 py-0.5 text-xs font-semibold text-[#B45309] ring-1 ring-inset ring-[#F59E0B]/30 dark:bg-[#F59E0B]/20 dark:text-[#FDE68A] dark:ring-[#F59E0B]/35',
  medium:
    'inline-flex items-center rounded-full bg-[#3B82F6]/15 px-2 py-0.5 text-xs font-semibold text-[#1D4ED8] ring-1 ring-inset ring-[#3B82F6]/25 dark:bg-[#3B82F6]/25 dark:text-[#BFDBFE] dark:ring-[#3B82F6]/40',
  low: 'inline-flex items-center rounded-full bg-[#64748B]/15 px-2 py-0.5 text-xs font-semibold text-[#475569] ring-1 ring-inset ring-[#64748B]/25 dark:bg-[#64748B]/25 dark:text-[#CBD5E1] dark:ring-[#64748B]/35',
  other:
    'inline-flex items-center rounded-full bg-ui-body px-2 py-0.5 text-xs font-semibold text-text-secondary ring-1 ring-inset ring-ui-border/40 dark:text-text-dark-secondary dark:ring-ui-dark-border/50',
};

export const SEVERITY_CARD_CLASSES: Record<LogSeverity, SeverityCardPalette> = {
  critical: {
    background: 'bg-[#DC2626]/10 dark:bg-[#DC2626]/20',
    text: 'text-[#991B1B] dark:text-[#FECACA]',
    border: 'border-[#DC2626]/25 dark:border-[#DC2626]/45',
  },
  high: {
    background: 'bg-[#F59E0B]/10 dark:bg-[#F59E0B]/15',
    text: 'text-[#B45309] dark:text-[#FDE68A]',
    border: 'border-[#F59E0B]/30 dark:border-[#F59E0B]/40',
  },
  medium: {
    background: 'bg-[#3B82F6]/10 dark:bg-[#3B82F6]/20',
    text: 'text-[#1D4ED8] dark:text-[#BFDBFE]',
    border: 'border-[#3B82F6]/25 dark:border-[#3B82F6]/45',
  },
  low: {
    background: 'bg-[#64748B]/10 dark:bg-[#64748B]/20',
    text: 'text-[#475569] dark:text-[#CBD5E1]',
    border: 'border-[#64748B]/25 dark:border-[#64748B]/40',
  },
  other: {
    background: 'bg-ui-card dark:bg-ui-dark-card',
    text: 'text-text-primary dark:text-text-dark-primary',
    border: 'border-ui-border dark:border-ui-dark-border',
  },
};

/** Tarjeta «Todos» (todas las severidades): índigo, distinto del slate de Baja y del azul de Media. */
export const SEVERITY_CARD_ALL: SeverityCardPalette = {
  background: 'bg-[#4F46E5]/10 dark:bg-[#4F46E5]/25',
  text: 'text-[#3730A3] dark:text-[#C7D2FE]',
  border: 'border-[#4F46E5]/30 dark:border-[#4F46E5]/50',
};

export const SEVERITY_CARD_DEFAULT: SeverityCardPalette = {
  background: 'bg-odoo-purple/10 dark:bg-odoo-purple/40',
  text: 'text-odoo-purple-d dark:text-text-dark-primary',
  border: 'border-odoo-purple/20 dark:border-odoo-purple/50',
};

export function severityCardPaletteFor(key: string): SeverityCardPalette {
  if (key === 'all') {
    return SEVERITY_CARD_ALL;
  }

  return (SEVERITY_CARD_CLASSES as Record<string, SeverityCardPalette>)[key] ?? SEVERITY_CARD_DEFAULT;
}

export function severityBadgeClassFor(severity: string | null | undefined): string {
  if (!severity) return 'text-text-muted dark:text-text-dark-muted';
  return (
    (SEVERITY_BADGE_CLASSES as Record<string, string>)[severity] ?? 'text-text-muted dark:text-text-dark-muted'
  );
}

export const SEVERITY_LABELS_ES: Record<string, string> = {
  all: 'Todos',
  critical: 'Crítica',
  high: 'Alta',
  medium: 'Media',
  low: 'Baja',
  other: 'Otra',
};

export function severityLabel(key: string): string {
  return SEVERITY_LABELS_ES[key] ?? key;
}
