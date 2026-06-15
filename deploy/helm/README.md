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
# 1) Construir y publicar imágenes
cd ../../..  # raíz del repo
DOCKER_BUILDKIT=1 docker buildx build \
  -f backend/Dockerfile.prod \
  -t gitea.ceedcv.es/maya/maya-logs-backend:$(git rev-parse --short HEAD) \
  backend
docker buildx build \
  -f frontend/Dockerfile.prod \
  --build-arg VITE_API_URL=https://logs-api.ceedcv.es/api/v1 \
  --build-arg VITE_KEYCLOAK_URL=https://keycloak.ceedcv.es \
  --build-arg VITE_REVERB_HOST=logs-reverb.ceedcv.es \
  -t gitea.ceedcv.es/maya/maya-logs-frontend:$(git rev-parse --short HEAD) \
  frontend
docker push gitea.ceedcv.es/maya/maya-logs-backend:$(git rev-parse --short HEAD)
docker push gitea.ceedcv.es/maya/maya-logs-frontend:$(git rev-parse --short HEAD)

# 2) Crear el Secret real (fuera del chart)
kubectl -n maya-logs create secret generic maya-logs-secret \
  --from-literal=APP_KEY="base64:<...>" \
  --from-literal=DB_PASSWORD="<...>" \
  --from-literal=DB_PANEL_PASSWORD="<...>" \
  --from-literal=REDIS_PASSWORD="<...>" \
  --from-literal=RABBITMQ_PASSWORD="<...>" \
  --from-literal=FDW_NOTIFICATION_RULES_PASSWORD="<...>" \
  --from-literal=FDW_USER_PERMISSIONS_PASSWORD="<...>" \
  --from-literal=KEYCLOAK_CLIENT_SECRET="<...>" \
  --from-literal=REVERB_APP_KEY="$(openssl rand -hex 16)" \
  --from-literal=REVERB_APP_SECRET="$(openssl rand -hex 32)"

# 3) Desplegar
helm upgrade --install maya-logs . \
  -n maya-logs --create-namespace \
  -f values.yaml \
  --set image.tag=$(git -C ../.. rev-parse --short HEAD) \
  --set secret.externalName=maya-logs-secret \
  --atomic --wait --timeout 10m
```

## Verificación

```bash
helm lint .
helm template maya-logs . -f values.yaml \
  --set image.tag=test --set secret.externalName=maya-logs-secret | less
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
- **TLS** vía Secret por host. Si hay `cert-manager` con `ClusterIssuer` de la
  CA interna, añadir la anotación en `ingress.annotations` y los Secrets se
  emitirán solos.
- Cuando exista el library chart `maya-common` en el registry OCI de Gitea, se
  migrará a `dependencies:` (este chart se quedará como ejemplo autocontenido).
