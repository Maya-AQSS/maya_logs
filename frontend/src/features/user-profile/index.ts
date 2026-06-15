/**
 * Re-exporta los símbolos del paquete compartido `@ceedcv-maya/shared-profile-react`.
 * El perfil de logs es el `StandardMeProfile` (idéntico 4/4 apps), así que el
 * provider pre-cableado con `fetchMe` viene de `createStandardProfileApi`
 * (instanciado en ../../api/auth).
 */
import {
  profileDisplayInitials,
  type UserProfileContextValue,
  useUserProfile as useSharedUserProfile,
} from '@ceedcv-maya/shared-profile-react';
import type { MeProfile } from '../../types/users';

export { StandardUserProfileProvider as UserProfileProvider } from '../../api/auth';

export function useUserProfile(): UserProfileContextValue<MeProfile> {
  return useSharedUserProfile<MeProfile>();
}

export type { UserProfileContextValue };
export { profileDisplayInitials };
