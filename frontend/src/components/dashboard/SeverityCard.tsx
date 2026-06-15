import { Link } from 'react-router-dom';
import { severityCardPaletteFor } from '../severity/palette';

type SeverityCardProps = {
  title: string;
  href: string;
  severityKey: string;
  unresolvedCount: number;
  resolvedCount: number;
  unresolvedLabel: string;
  resolvedLabel: string;
};

export function SeverityCard({
  title,
  href,
  severityKey,
  unresolvedCount,
  resolvedCount,
  unresolvedLabel,
  resolvedLabel,
}: SeverityCardProps) {
  const palette = severityCardPaletteFor(severityKey);
  const cls = [
    'block rounded-lg border p-4 shadow-card transition',
    'hover:-translate-y-0.5 hover:shadow-card-md',
    'focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-odoo-purple',
    palette.border,
    palette.background,
  ].join(' ');

  return (
    <Link to={href} className={cls}>
      <p className={`text-xs uppercase tracking-wide font-medium ${palette.text} opacity-80`}>
        {title}
      </p>
      <p className={`text-3xl font-bold mt-1 leading-none ${palette.text}`}>{unresolvedCount}</p>
      <div className={`mt-3 flex items-center justify-between text-xs ${palette.text}`}>
        <span className="opacity-70">{unresolvedLabel}</span>
        <span className="opacity-60">
          {resolvedLabel}: <strong>{resolvedCount}</strong>
        </span>
      </div>
    </Link>
  );
}
