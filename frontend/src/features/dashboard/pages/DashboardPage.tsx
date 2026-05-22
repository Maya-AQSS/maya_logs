import { useCallback, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { PageTitle } from '@maya/shared-ui-react';
import {
  DashboardEditToggleButton,
  DashboardEditToolbar,
  DashboardSkeleton,
  WidgetGrid,
  useDashboardLayoutLocal,
  type LayoutItem,
  type SkeletonBlock,
} from '@maya/shared-dashboard-react';
import { useUserProfile } from '../../user-profile';
import { LOGS_PERMISSIONS } from '../../../permissions';
import { DEFAULT_LAYOUT, WIDGET_REGISTRY } from '../widgets/registry';

const STORAGE_KEY = 'maya:logs:dashboard-layout';

const SKELETON_BLOCKS: SkeletonBlock[] = [
  { colSpanClasses: 'col-span-12 sm:col-span-4', heightClass: 'h-32' },
  { colSpanClasses: 'col-span-12 sm:col-span-8', heightClass: 'h-32' },
  { colSpanClasses: 'col-span-12', heightClass: 'h-32' },
];

/**
 * Customizable dashboard for maya_logs. Layout persists to localStorage under
 * `maya:logs:dashboard-layout`. Toggle edit mode to drag, resize, add or remove
 * widgets — changes are saved on exit.
 */
export function DashboardPage() {
  const { t } = useTranslation('dashboard');
  const { hasPermission } = useUserProfile();
  const canEditDashboard = hasPermission(LOGS_PERMISSIONS.dashboardUpdate);
  const { layout, loading, saveLayout, resetToDefault } = useDashboardLayoutLocal({
    storageKey: STORAGE_KEY,
    defaultLayout: DEFAULT_LAYOUT,
  });
  const [editable, setEditable] = useState(false);
  const [draftLayout, setDraftLayout] = useState<LayoutItem[] | null>(null);
  const snapshotRef = useRef<LayoutItem[] | null>(null);

  const activeLayout = editable ? (draftLayout ?? layout) : layout;

  const handleToggleEdit = useCallback(() => {
    if (!canEditDashboard) return;
    setEditable((prev) => {
      if (prev) {
        setDraftLayout(null);
        return false;
      }
      snapshotRef.current = layout;
      setDraftLayout(layout);
      return true;
    });
  }, [canEditDashboard, layout]);

  const handleSave = useCallback(async () => {
    await saveLayout(draftLayout ?? layout);
    setEditable(false);
    setDraftLayout(null);
  }, [draftLayout, layout, saveLayout]);

  const handleCancel = useCallback(() => {
    setDraftLayout(null);
    setEditable(false);
  }, []);

  const handleLayoutChange = useCallback(
    (next: LayoutItem[]) => {
      if (!editable) return;
      setDraftLayout(next);
    },
    [editable],
  );

  const handleRemoveWidget = useCallback(
    (widgetId: string) => {
      setDraftLayout((prev) => (prev ?? layout).filter((item) => item.i !== widgetId));
    },
    [layout],
  );

  const handleAddWidget = useCallback(
    (widgetId: string) => {
      const def = WIDGET_REGISTRY[widgetId];
      if (!def) return;
      const current = draftLayout ?? layout;
      const maxY = current.reduce((m, item) => Math.max(m, item.y + item.h), 0);
      setDraftLayout([
        ...current,
        {
          i: widgetId,
          x: 0,
          y: maxY,
          w: def.defaultSize.w,
          h: def.defaultSize.h,
          minW: def.minSize.w,
          minH: def.minSize.h,
        },
      ]);
    },
    [draftLayout, layout],
  );

  const handleReset = useCallback(async () => {
    await resetToDefault();
    setDraftLayout(null);
    setEditable(false);
  }, [resetToDefault]);

  if (loading) {
    return <DashboardSkeleton blocks={SKELETON_BLOCKS} />;
  }

  return (
    <>
      <PageTitle
        title={t('nav.dashboard')}
        image={{ src: '/dashboard-header.png', alt: t('nav.dashboard') }}
        actions={
          canEditDashboard && editable ? (
            <DashboardEditToolbar
              layout={activeLayout}
              registry={WIDGET_REGISTRY}
              t={t}
              onSave={handleSave}
              onCancel={handleCancel}
              onReset={handleReset}
              onAddWidget={handleAddWidget}
              labels={{
                save: t('actions.save'),
                cancel: t('actions.cancel'),
                reset: t('actions.restore'),
                addWidget: t('dashboard.addWidget'),
              }}
            />
          ) : canEditDashboard ? (
            <DashboardEditToggleButton
              editable={editable}
              onToggle={handleToggleEdit}
              editLabel={t('actions.edit')}
            />
          ) : null
        }
      />

      <WidgetGrid
        registry={WIDGET_REGISTRY}
        layout={activeLayout}
        onLayoutChange={handleLayoutChange}
        editable={editable}
        onRemoveWidget={handleRemoveWidget}
        t={t}
        emptyKey="widgets.empty"
        removeAriaLabel={t('dashboard.removeWidget')}
      />
    </>
  );
}

export default DashboardPage;
