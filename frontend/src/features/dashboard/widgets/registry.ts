import type { LayoutItem, WidgetRegistry } from '@maya/shared-dashboard-react';
import RecentLogsWidget from './RecentLogsWidget';
import ErrorCountWidget from './ErrorCountWidget';
import SeverityCardsWidget from './SeverityCardsWidget';
import ApplicationTotalsWidget from './ApplicationTotalsWidget';

/**
 * Widget catalog for the maya_logs dashboard. The keys must match the `i`
 * field of the LayoutItem entries persisted in localStorage.
 */
export const WIDGET_REGISTRY: WidgetRegistry = {
  'error-count': {
    id: 'error-count',
    titleKey: 'widgets.errorCount.title',
    defaultSize: { w: 4, h: 2 },
    minSize: { w: 3, h: 2 },
    component: ErrorCountWidget,
  },
  'recent-logs': {
    id: 'recent-logs',
    titleKey: 'widgets.recentLogs.title',
    defaultSize: { w: 8, h: 4 },
    minSize: { w: 4, h: 3 },
    component: RecentLogsWidget,
  },
  'severity-cards': {
    id: 'severity-cards',
    titleKey: 'widgets.severityCards.title',
    defaultSize: { w: 12, h: 3 },
    minSize: { w: 6, h: 2 },
    component: SeverityCardsWidget,
  },
  'application-totals': {
    id: 'application-totals',
    titleKey: 'widgets.applicationTotals.title',
    defaultSize: { w: 12, h: 3 },
    minSize: { w: 6, h: 2 },
    component: ApplicationTotalsWidget,
  },
};

/** Default layout — shown on first visit and after `Reset`. */
export const DEFAULT_LAYOUT: LayoutItem[] = [
  { i: 'error-count', x: 0, y: 0, w: 4, h: 2, minW: 3, minH: 2 },
  { i: 'recent-logs', x: 4, y: 0, w: 8, h: 4, minW: 4, minH: 3 },
  { i: 'severity-cards', x: 0, y: 4, w: 12, h: 3, minW: 6, minH: 2 },
  { i: 'application-totals', x: 0, y: 7, w: 12, h: 3, minW: 6, minH: 2 },
];
