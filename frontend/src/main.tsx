import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import { BrowserRouter } from 'react-router-dom';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import 'react-grid-layout/css/styles.css';
import 'react-resizable/css/styles.css';
import './index.css';
import './i18n';
import App from './App.tsx';
import { AuthProvider } from '@ceedcv-maya/shared-auth-react';
import { NotificationProvider } from '@ceedcv-maya/shared-sidebar-react';
import { oidcAuthService } from './auth/oidcAdapter';
import { UserProfileProvider } from './features/user-profile';
import { bootstrapRealtime } from './lib/realtimeBootstrap';

bootstrapRealtime();

window.addEventListener('unhandledrejection', (event) => {
  console.error('Unhandled promise rejection:', event.reason);
});

const queryClient = new QueryClient({
  defaultOptions: {
    queries: { staleTime: 60_000, retry: 1 },
  },
});

createRoot(document.getElementById('root')!).render(
  <StrictMode>
    <QueryClientProvider client={queryClient}>
      <AuthProvider keycloak={oidcAuthService.keycloak} enableLogging={import.meta.env.DEV}>
        <BrowserRouter>
          <UserProfileProvider>
            <NotificationProvider>
              <App />
            </NotificationProvider>
          </UserProfileProvider>
        </BrowserRouter>
      </AuthProvider>
    </QueryClientProvider>
  </StrictMode>,
);
