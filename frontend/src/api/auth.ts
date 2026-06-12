import { createStandardProfileApi } from '@ceedcv-maya/shared-profile-react';
import { apiFetchJson, apiGetJson } from './http';

export type { MeProfile } from '../types/users';

const profileApi = createStandardProfileApi({ apiGetJson, apiFetchJson });

export const { fetchMe, updateMyLocale, StandardUserProfileProvider } = profileApi;
