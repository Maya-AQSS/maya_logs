import { PermissionGate as SharedPermissionGate } from '@ceedcv-maya/shared-profile-react';
import type { ReactNode } from 'react';
import { useTranslation } from 'react-i18next';

type PermissionGateProps = {
  permission: string;
  children: ReactNode;
};

/**
 * Wrapper sobre el PermissionGate compartido (mode="block": skeleton durante
 * la carga + alerta de denegado) que inyecta los textos i18n de la app.
 */
export function PermissionGate({ permission, children }: PermissionGateProps) {
  const { t } = useTranslation('auth');

  return (
    <SharedPermissionGate
      permission={permission}
      mode="block"
      deniedMessage={t('auth.unauthorizedMessage')}
      deniedHint={t('auth.unauthorizedPermission', {
        permission,
        defaultValue: `Permiso requerido: ${permission}`,
      })}
    >
      {children}
    </SharedPermissionGate>
  );
}
