import type { ReactNode } from 'react';
import { UserProfileProvider as SharedUserProfileProvider } from '@maya/shared-profile-react';
import { fetchMe } from '../../api/auth';
import type { MeProfile } from '../../types/users';

export function UserProfileProvider({ children }: { children: ReactNode }) {
  return (
    <SharedUserProfileProvider<MeProfile> fetchProfile={fetchMe}>
      {children}
    </SharedUserProfileProvider>
  );
}
