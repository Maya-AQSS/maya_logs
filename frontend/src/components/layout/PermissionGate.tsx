import type { ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import { SkeletonPage } from '@ceedcv-maya/shared-ui-react';
import { useUserProfile } from '../../features/user-profile';

type PermissionGateProps = {
  permission: string;
  children: ReactNode;
};

export function PermissionGate({ permission, children }: PermissionGateProps) {
  const { t } = useTranslation('auth');
  const { hasPermission, loading } = useUserProfile();

  if (loading) {
    return <SkeletonPage />;
  }

  if (!hasPermission(permission)) {
    return (
      <div
        role="alert"
        className="px-4 py-6 sm:px-6 lg:px-8 rounded-lg border border-ui-border bg-ui-card dark:border-ui-dark-border dark:bg-ui-dark-card text-center"
      >
        <p className="text-sm font-medium text-text-primary dark:text-text-dark-primary">
          {t('auth.unauthorizedMessage')}
        </p>
        <p className="mt-2 text-xs text-text-muted dark:text-text-dark-muted">
          {t('auth.unauthorizedPermission', {
            permission,
            defaultValue: `Permiso requerido: ${permission}`,
          })}
        </p>
      </div>
    );
  }

  return <>{children}</>;
}
