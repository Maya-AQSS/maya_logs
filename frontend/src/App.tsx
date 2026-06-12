import { lazy, Suspense } from 'react';
import { useTranslation } from 'react-i18next';
import { Navigate, Route, Routes } from 'react-router-dom';
import { resolveServiceUrl } from '@ceedcv-maya/shared-auth-react';
import { MayaAppShell } from '@ceedcv-maya/shared-layout-react';
import { PlaceholderPage, SkeletonPage } from '@ceedcv-maya/shared-ui-react';
import { useNavItems } from './components/layout';
import { LOGS_PERMISSIONS } from './permissions';

// Code-splitting route-level: cada página carga en chunk separado bajo demanda.
const ArchivedLogDetailPage = lazy(() =>
  import('./pages/ArchivedLogDetailPage').then((m) => ({ default: m.ArchivedLogDetailPage })),
);
const ArchivedLogsPage = lazy(() =>
  import('./pages/ArchivedLogsPage').then((m) => ({ default: m.ArchivedLogsPage })),
);
const DashboardPage = lazy(() =>
  import('./pages/DashboardPage').then((m) => ({ default: m.DashboardPage })),
);
const ErrorCodeCreatePage = lazy(() =>
  import('./pages/ErrorCodeCreatePage').then((m) => ({ default: m.ErrorCodeCreatePage })),
);
const ErrorCodeDetailPage = lazy(() =>
  import('./pages/ErrorCodeDetailPage').then((m) => ({ default: m.ErrorCodeDetailPage })),
);
const ErrorCodesPage = lazy(() =>
  import('./pages/ErrorCodesPage').then((m) => ({ default: m.ErrorCodesPage })),
);
const LogDetailPage = lazy(() =>
  import('./pages/LogDetailPage').then((m) => ({ default: m.LogDetailPage })),
);
const LogsPage = lazy(() =>
  import('./pages/LogsPage').then((m) => ({ default: m.LogsPage })),
);

const DASHBOARD_URL = resolveServiceUrl(
  import.meta.env.VITE_DASHBOARD_URL as string | undefined,
  'dashboard',
);
const DASHBOARD_API_URL = resolveServiceUrl(
  import.meta.env.VITE_DASHBOARD_API_URL as string | undefined,
  'dashboard-api',
);

function AppRoutes() {
  const { t } = useTranslation('common');
  return (
    <Suspense fallback={<SkeletonPage />}>
      <Routes>
        <Route path="/" element={<Navigate to="/dashboard" replace />} />
        <Route path="/dashboard" element={<DashboardPage />} />
        <Route path="/logs" element={<LogsPage />} />
        <Route path="/logs/:id" element={<LogDetailPage />} />
        <Route path="/archived-logs" element={<ArchivedLogsPage />} />
        <Route path="/archived-logs/:id" element={<ArchivedLogDetailPage />} />
        <Route path="/error-codes" element={<ErrorCodesPage />} />
        <Route path="/error-codes/create" element={<ErrorCodeCreatePage />} />
        <Route path="/error-codes/:id" element={<ErrorCodeDetailPage />} />
        <Route path="*" element={<PlaceholderPage title={t('notFound')} />} />
      </Routes>
    </Suspense>
  );
}

/**
 * Shell unificado (MayaAppShell): OIDC + gate `logs.login` (redirige al portal
 * si el usuario tiene `dashboard.login`; si no, cierra sesión SSO) + AppLayout
 * con favoritas/notificaciones. Debe renderizarse dentro de <MayaProviders>.
 */
export default function App() {
  const { t } = useTranslation('auth');
  const navItems = useNavItems();

  return (
    <MayaAppShell
      brandName="TraCEED"
      brandVersion="v1.0"
      brandLogoUrl="/favicon.png"
      dashboardUrl={DASHBOARD_URL}
      dashboardApiUrl={DASHBOARD_API_URL}
      navItems={navItems}
      loginPermission={LOGS_PERMISSIONS.login}
      loadingInitializingMessage={t('auth.initializing')}
      loadingRedirectingMessage={t('auth.redirecting')}
      loadingProfileMessage={t('auth.initializing')}
      loadingNoPermissionMessage={t('signingOutNoPermission')}
    >
      <AppRoutes />
    </MayaAppShell>
  );
}
