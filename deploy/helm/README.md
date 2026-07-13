# maya-logs — Chart Helm

Despliega `maya_logs` (backend Laravel + frontend React + worker AMQP `logs:consume`
+ reverb WS) en el K3s interno de CEEDCV.

## Componentes

| Componente | Réplicas | Imagen | Puerto |
|---|---|---|---|
| `maya-logs-backend` | 2 | `*-backend` + nginx sidecar | 8000 |
| `maya-logs-frontend` | 2 | `*-frontend` | 8080 |
| `maya-logs-worker` | 1 | `*-backend` (CONTAINER_ROLE=worker → `logs:consume`) | — |
| `maya-logs-reverb` | 1 | `*-backend` (CONTAINER_ROLE=reverb) | 8080 (WS) |
| `maya-logs-*-migrate` | Job pre-upgrade | `*-backend` | — |

## Uso (desde WSL en la red interna)

```bash
# 1) Construir y publicar imágenes en el registry interno (IaC: registry.ceedcv.es)
cd ../../..  # raíz del repo
DOCKER_BUILDKIT=1 docker buildx build \
  -f backend/Dockerfile.prod \
  -t registry.ceedcv.es/maya/maya-logs-backend:$(git rev-parse --short HEAD) \
  backend
docker buildx build \
  -f frontend/Dockerfile.prod \
  --build-arg VITE_API_URL=https://logs-api.ceedcv.es/api/v1 \
  --build-arg VITE_KEYCLOAK_URL=https://keycloak.ceedcv.es \
  --build-arg VITE_REVERB_HOST=logs-reverb.ceedcv.es \
  -t registry.ceedcv.es/maya/maya-logs-frontend:$(git rev-parse --short HEAD) \
  frontend
docker push registry.ceedcv.es/maya/maya-logs-backend:$(git rev-parse --short HEAD)
docker push registry.ceedcv.es/maya/maya-logs-frontend:$(git rev-parse --short HEAD)

# 2) Cargar los secretos en Vault (según IaC — vault.enabled=true por defecto).
#    El Vault Agent Injector los renderiza en /vault/secrets/config y el
#    entrypoint hace `source`. Ruta KV: secret/maya-logs.
vault kv put secret/maya-logs \
  APP_KEY="base64:<...>" \
  DB_PASSWORD="<...>" \
  DB_PANEL_PASSWORD="<...>" \
  REDIS_PASSWORD="<...>" \
  RABBITMQ_PASSWORD="<...>" \
  FDW_NOTIFICATION_RULES_USERNAME="<...>" \
  FDW_NOTIFICATION_RULES_PASSWORD="<...>" \
  FDW_USER_PERMISSIONS_USERNAME="<...>" \
  FDW_USER_PERMISSIONS_PASSWORD="<...>" \
  KEYCLOAK_CLIENT_SECRET="<...>" \
  REVERB_APP_KEY="$(openssl rand -hex 16)" \
  REVERB_APP_SECRET="$(openssl rand -hex 32)"

#    Autorizar la ServiceAccount `default` del namespace maya-logs en el rol
#    `vault-k8s-role` (una vez por app):
#      vault write auth/kubernetes/role/vault-k8s-role \
#        bound_service_account_names=default \
#        bound_service_account_namespaces=maya-logs,maya-iam,maya-sync,... \
#        policies=maya-app ttl=1h

# 3) Registry pull secret (una vez por namespace)
kubectl -n maya-logs create secret docker-registry regcred \
  --docker-server=registry.ceedcv.es --docker-username=<u> --docker-password=<t>

# 4) Desplegar
helm upgrade --install maya-logs . \
  -n maya-logs --create-namespace \
  -f values.yaml \
  --set image.tag=$(git -C ../.. rev-parse --short HEAD) \
  --atomic --wait --timeout 10m
```

> **Fallback sin Vault:** `--set vault.enabled=false` reactiva el Secret k8s
> clásico. En ese modo, crear el Secret con `kubectl create secret generic
> maya-logs-secret --from-literal=...` y pasar `--set secret.externalName=maya-logs-secret`.

## Verificación

```bash
helm lint .
helm template maya-logs . -f values.yaml --set image.tag=test | less
```

## Notas

- **Job de migración** corre como hook `pre-install,pre-upgrade` con
  `hook-delete-policy: before-hook-creation,hook-succeeded`. `helm rollback` NO
  revierte el esquema — migraciones forward-only + backup pre-deploy.
- **`LOG_STACK="daily"`** (sin `rabbit`) tanto para API como worker: maya_logs
  ES el sumidero del bus rabbit del ecosistema; emitir por rabbit desde aquí
  produce un loop infinito sobre la propia cola.
- **NetworkPolicy** deny-all + allow east-west desde `maya-dashboard`
  (widgets agregados). Egress permitido a la red interna (Patroni, Keycloak,
  RabbitMQ, Redis) y a todos los namespaces del clúster (autz, infra…).
- **readOnlyRootFilesystem** activo. `storage/framework`, `bootstrap/cache`,
  `storage/logs` y `/tmp` se montan como `emptyDir`.
- **FDW**: el chart materializa los FDW de
  `v_notification_rules` (maya_dashboard) y `v_portal_user_permissions`
  (maya_authorization) sobre la VIP Patroni `.252`. Las credenciales son
  Secrets independientes (`FDW_*_PASSWORD`).
- **Secretos vía Vault** (`vault.enabled=true`, según IaC): el Agent Injector
  monta `secret/data/maya-logs` en `/vault/secrets/config` y el entrypoint hace
  `source`. Prerrequisitos: `vault kv put secret/maya-logs ...` y autorizar la
  SA `default` de este namespace en `vault-k8s-role`.
- **TLS** vía cert-manager: `ingress.annotations."cert-manager.io/cluster-issuer"`
  = `maya-internal-ca` (ClusterIssuer definido en el repo IaC). Los Secrets TLS
  por host se emiten solos.
- **DNS este-oeste (según IaC)**: Redis → `redis-cache-maya-app.maya-infra`,
  RabbitMQ → `rabbitmq-maya-app.maya-sync`, Keycloak → `keycloak-maya-app.maya-iam`.
- Cuando exista el library chart `maya-common` en el registry OCI interno, se
  migrará a `dependencies:` (este chart se quedará como ejemplo autocontenido).
