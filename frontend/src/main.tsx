import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import 'react-grid-layout/css/styles.css';
import 'react-resizable/css/styles.css';
import './index.css';
import './i18n';
import { MayaProviders } from '@ceedcv-maya/shared-layout-react';
import App from './App.tsx';
import { fetchMe } from './api/auth';
import { oidcAuthService } from './auth/oidcAdapter';

createRoot(document.getElementById('root')!).render(
  <StrictMode>
    <MayaProviders
      authService={oidcAuthService}
      serviceSlug="logs"
      fetchProfile={fetchMe}
      withErrorBoundary={false}
    >
      <App />
    </MayaProviders>
  </StrictMode>,
);
