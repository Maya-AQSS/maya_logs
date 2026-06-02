import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import tailwindcss from '@tailwindcss/vite';
import { createRequire } from 'node:module';
import { fileURLToPath } from 'node:url';
import path from 'node:path';

const _require = createRequire(import.meta.url);
const appRoot = fileURLToPath(new URL('.', import.meta.url));

// Dev override: si MAYA_DEV_OVERRIDE_DIR está set, los paquetes @ceedcv-maya/shared-*
// se resuelven desde el monorepo en disco en lugar de node_modules. Vite los carga
// vía resolve.alias (no requiere bind mount sobre node_modules — funciona limpio).
const _sharedOverrideDir = process.env.MAYA_DEV_OVERRIDE_DIR
const _sharedPackageAliases: Record<string, string> = _sharedOverrideDir
  ? Object.fromEntries(
      [
        'shared-auth-react', 'shared-dashboard-react', 'shared-editor-react',
        'shared-hooks-react', 'shared-i18n-react', 'shared-layout-react',
        'shared-profile-react', 'shared-realtime-react', 'shared-sidebar-react',
        'shared-styles', 'shared-ui-react',
      ].map((pkg) => [`@ceedcv-maya/${pkg}`, path.resolve(_sharedOverrideDir!, pkg, 'src')])
    )
  : {}

import { existsSync, symlinkSync, mkdirSync } from 'node:fs'
function _ensureSharedNodeModulesSymlink(): void {
  if (!_sharedOverrideDir) return
  const consumerNodeModules = path.join(appRoot, 'node_modules')
  const sharedPackages = [
    'shared-auth-react', 'shared-dashboard-react', 'shared-editor-react',
    'shared-hooks-react', 'shared-i18n-react', 'shared-layout-react',
    'shared-profile-react', 'shared-realtime-react', 'shared-sidebar-react',
    'shared-styles', 'shared-ui-react',
  ]
  for (const pkg of sharedPackages) {
    const pkgDir = path.join(_sharedOverrideDir, pkg)
    if (!existsSync(pkgDir)) continue
    const linkPath = path.join(pkgDir, 'node_modules')
    if (existsSync(linkPath)) continue
    try {
      mkdirSync(path.dirname(linkPath), { recursive: true })
      symlinkSync(consumerNodeModules, linkPath, 'dir')
    } catch (e) {
      console.warn(`[Vite] Failed to symlink ${pkg} node_modules:`, (e as Error).message)
    }
  }
}
_ensureSharedNodeModulesSymlink()


export default defineConfig({
  plugins: [react(), tailwindcss()],
  server: {
    host: '0.0.0.0',
    allowedHosts: true,
    hmr: { clientPort: 443, protocol: 'wss' },
    watch: {
      usePolling: true,
    },
  },
  optimizeDeps: {
    include: [
      'keycloak-js',
      'axios',
      'html-parse-stringify',
      'void-elements',
      'use-sync-external-store',
      'use-sync-external-store/shim',
    ],
    exclude: [
      '@ceedcv-maya/shared-auth-react',
      '@ceedcv-maya/shared-editor-react',
      '@ceedcv-maya/shared-hooks-react',
      '@ceedcv-maya/shared-i18n-react',
      '@ceedcv-maya/shared-layout-react',
      '@ceedcv-maya/shared-profile-react',
      '@ceedcv-maya/shared-sidebar-react',
      '@ceedcv-maya/shared-ui-react',
    ],
  },
  resolve: {
    dedupe: ['react', 'react-dom', 'react-router-dom'],
    alias: {
      '@tanstack/react-query': _require.resolve('@tanstack/react-query', { paths: [appRoot] }),
      ..._sharedPackageAliases,
    },
  },
});
