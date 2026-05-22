import { lazy, Suspense, useEffect } from 'react';
import { useTranslation } from 'react-i18next';
import { Navigate, Route, Routes } from 'react-router-dom';
import { AppLayout } from '@maya/shared-layout-react';
import { NotificationsBell, SidebarFavorites } from '@maya/shared-sidebar-react';
import { PlaceholderPage, SkeletonPage } from '@maya/shared-ui-react';
import { useKeycloakLocaleSync } from '@maya/shared-i18n-react';
import { useOidcSession } from '@maya/shared-auth-react';
import { useRequireAppAccess } from '@maya/shared-profile-react';
import { useNavItems } from './components/layout';
import { profileDisplayInitials, useUserProfile } from './features/user-profile';
import { resolveServiceUrl } from './lib/peerService';
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

function AppWithLayout() {
  const { logout, user } = useOidcSession();
  const { profile } = useUserProfile();
  const navItems = useNavItems();
  useKeycloakLocaleSync();

  // Prefer backend profile name; fall back to Keycloak token (same pattern as other apps)
  const tokenDisplayName = ((user?.name ?? user?.preferred_username ?? '') as string).trim();
  const userName = profile?.name?.trim() || tokenDisplayName;
  const userInitials = profile
    ? profileDisplayInitials(profile)
    : tokenDisplayName
      ? tokenDisplayName.split(' ').map((w) => w[0]).slice(0, 2).join('').toUpperCase() || 'U'
      : 'U';

  const userEmail = (profile?.email ?? user?.email) as string | undefined;
  const onProfile = () => {
    const dashboardOrigin = resolveServiceUrl(
      import.meta.env.VITE_DASHBOARD_URL as string | undefined,
      'dashboard',
    );
    window.location.assign(`${dashboardOrigin}/profile`);
  };

  return (
    <AppLayout
      navItems={navItems}
      brandName="LogCEED"
      brandVersion="v1.0"
      brandLogoUrl="/favicon.png"
      userName={userName}
      userEmail={userEmail}
      userInitials={userInitials}
      onLogout={logout}
      onProfile={onProfile}
      favoritesSlot={<SidebarFavorites label="Favoritas" dashboardApiUrl={DASHBOARD_API_URL} />}
      notificationsSlot={<NotificationsBell dashboardApiUrl={DASHBOARD_API_URL} />}
    >
      <AppRoutes />
    </AppLayout>
  );
}

function AuthLoadingScreen({ message }: { message: string }) {
  return (
    <div className="flex items-center justify-center h-screen bg-ui-body dark:bg-ui-dark-bg text-text-muted dark:text-text-dark-muted font-sans">
      {message}
    </div>
  );
}

/**
 * Requiere `logs.login` en /me. Si falta:
 *  - Si el usuario tiene `dashboard.login`, redirige al portal (preserva SSO).
 *  - Si no, cierra sesión SSO.
 */
function AppAfterProfile() {
  const { t } = useTranslation('auth');
  const dashboardOrigin = resolveServiceUrl(
    import.meta.env.VITE_DASHBOARD_URL as string | undefined,
    'dashboard',
  );
  const { profileLoading, lacksLoginPermission } = useRequireAppAccess(
    LOGS_PERMISSIONS.login,
    { portalLoginSlug: 'dashboard.login', portalUrl: dashboardOrigin },
  );

  if (profileLoading) {
    return <AuthLoadingScreen message={t('auth.initializing')} />;
  }

  if (lacksLoginPermission) {
    return (
      <AuthLoadingScreen
        message={t('signingOutNoPermission')}
      />
    );
  }

  return <AppWithLayout />;
}

export default function App() {
  const { t } = useTranslation('auth');
  const { isOidcLoading, isOidcSignedIn, beginSignIn } = useOidcSession();

  useEffect(() => {
    if (!isOidcLoading && !isOidcSignedIn) {
      beginSignIn();
    }
  }, [isOidcLoading, isOidcSignedIn, beginSignIn]);

  if (isOidcLoading) {
    return <AuthLoadingScreen message={t('auth.initializing')} />;
  }

  if (!isOidcSignedIn) {
    return <AuthLoadingScreen message={t('auth.redirecting')} />;
  }

  return <AppAfterProfile />;
}
