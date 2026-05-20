import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';
import type { NavItem } from '@maya/shared-layout-react';
import {
  AppsIcon,
  FolderIcon,
  HomeIcon,
  SearchIcon,
} from '@maya/shared-layout-react';
import { useUserProfile } from '../../features/user-profile';
import { LOGS_PERMISSIONS } from '../../permissions';

export function useNavItems(): NavItem[] {
  const { t } = useTranslation('nav');
  const { hasPermission } = useUserProfile();

  return useMemo<NavItem[]>(() => {
    const items: NavItem[] = [
      { id: 'dashboard', label: t('dashboard'), icon: HomeIcon, path: '/dashboard' },
      { id: 'logs', label: t('logs'), icon: SearchIcon, path: '/logs' },
      { id: 'archived-logs', label: t('archivedLogs'), icon: FolderIcon, path: '/archived-logs' },
      { id: 'error-codes', label: t('errorCodes'), icon: AppsIcon, path: '/error-codes' },
    ];

    return items.filter((item) => {
      if (item.id === 'logs') {
        return hasPermission(LOGS_PERMISSIONS.index);
      }
      if (item.id === 'archived-logs') {
        return hasPermission(LOGS_PERMISSIONS.archivedLogsIndex);
      }
      if (item.id === 'error-codes') {
        return hasPermission(LOGS_PERMISSIONS.errorCodeIndex);
      }
      return true;
    });
  }, [hasPermission, t]);
}
