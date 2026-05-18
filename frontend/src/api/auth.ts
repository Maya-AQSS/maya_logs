import { createProfileApi } from '@maya/shared-profile-react';
import { apiFetchJson, apiGetJson } from './http';
import type { MeProfile } from '../types/users';

export type { MeProfile } from '../types/users';

const profileApi = createProfileApi<MeProfile>({ apiFetchJson, apiGetJson });

export const { fetchMe, updateMyLocale } = profileApi;
