import { bootstrapRealtime as sharedBootstrapRealtime } from '@ceedcv-maya/shared-realtime-react'
import { getBearerToken } from '../api/http'

/**
 * Inicializa Echo/Reverb con el slug 'logs' (deriva `logs-reverb` y `logs-api`
 * via peerOrigin). No-op si falta VITE_REVERB_APP_KEY — igual que antes.
 */
export function bootstrapRealtime(): void {
  sharedBootstrapRealtime('logs', getBearerToken)
}
