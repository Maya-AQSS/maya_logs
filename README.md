# Log Management Dashboard

Panel de administración web para la gestión centralizada de logs de error multi-aplicación.

Permite visualizar errores en tiempo real (SSE), filtrarlos por severidad, origen y fecha, y archivarlos en un histórico permanente con hilos de comentarios enriquecidos.

## Stack

- **Backend** → Laravel 12 API (PHP 8.4) + JWT RS256 (Keycloak)
- **Frontend** → React 19 + Vite 8 + TypeScript 5.9 + Tailwind 4 + React Router 7
- **Mensajería** → RabbitMQ (AMQP consumer `logs:consume`)
- **DB** → PostgreSQL 17 (compartida vía `maya_infra/`)
- **IdP** → Keycloak (compartido vía `maya_infra/`)
- **Orquestación** → Docker Compose + Traefik

## Estructura del repo

```text
maya_logs/
├── backend/              # Laravel 12 API (expone :8002)
│   ├── app/ routes/ database/ ...
│   └── docker/           # Dockerfile + entrypoint PHP
├── frontend/             # React 19 + Vite (expone :5176)
│   ├── src/ public/
│   ├── Dockerfile.dev
│   └── docker-entrypoint.sh
├── docker-compose.yml    # backend + worker + frontend
├── Makefile              # comandos de desarrollo
└── up.sh                 # orquestación inicial
```

## Prerequisitos

- Docker Engine 20.10+
- Docker Compose v2+
- Repo `maya_infra/` clonado al mismo nivel que este proyecto

## Infraestructura compartida

La base de datos PostgreSQL, Keycloak y RabbitMQ **no** están en este proyecto — viven en el repo `maya_infra/`, compartido por todo el ecosistema Maya. El script `up.sh` los levanta automáticamente si no están corriendo.

### Clonar maya_infra

```bash
git clone <url-repo-infra> ../maya_infra
```

Resultado esperado:

```text
~/desarrollo/
├── maya_infra/              ← repo infra (Traefik, Postgres, Keycloak, RabbitMQ)
├── maya_authorization/
├── maya_dms/
├── maya_logs/               ← este proyecto
└── maya_dashboard/
```

Si tienes infra en otra ubicación, usa la variable de entorno:

```bash
MAYA_INFRA_DIR=/ruta/absoluta/a/maya_infra ./up.sh
```

## Instalación

```bash
git clone <repository-url>
cd maya_logs
./up.sh --build
```

El script `up.sh` se encarga de todo automáticamente:

- Copia `backend/.env.example` → `backend/.env` si no existe
- Copia `frontend/.env.example` → `frontend/.env` si no existe
- Levanta la infra compartida (Traefik, PostgreSQL, Keycloak, RabbitMQ)
- Construye y levanta los contenedores (`backend`, `worker`, `frontend`)
- Genera `APP_KEY` si es un `.env` nuevo
- Ejecuta migraciones y seeders si la BD está vacía

> Solo necesitas editar `backend/.env` y `frontend/.env` si quieres cambiar valores por defecto.

Alternativa con `make`:

```bash
make install
```

## Arranque diario

```bash
make up        # levanta backend + worker + frontend
make down      # para todo
make restart   # reinicia
make logs      # sigue logs de todos los servicios
```

## URLs de acceso

| Servicio | URL (vía Traefik) | URL directa |
| --- | --- | --- |
| Frontend React | <http://logs.localhost> | <http://localhost:5176> |
| Backend API | <http://logs-api.localhost> | <http://localhost:8002> |
| Keycloak | <https://keycloak.maya.test> | <http://localhost:8180> |
| Traefik dashboard | <http://localhost:8888> | — |

### Credenciales por defecto

| Servicio | Usuario | Contraseña |
| --- | --- | --- |
| PostgreSQL | `log_mgmt_user` | `secret` |
| Keycloak Admin | `admin` | `admin` |

## Comandos `make`

```bash
make install          # setup inicial (build + up + migrate + seed + npm install)
make up               # levanta servicios
make down             # para servicios
make restart          # reinicia
make logs             # sigue logs

make shell-backend    # bash dentro del contenedor backend
make shell-frontend   # sh dentro del contenedor frontend
make shell-worker     # bash dentro del worker AMQP

make migrate          # artisan migrate
make migrate-fresh    # artisan migrate:fresh --seed
make seed             # artisan db:seed

make test             # tests backend (sqlite :memory:)
make test-backend     # tests backend con coverage mínimo 80%
make test-frontend    # tests frontend (vitest)

make lint             # pint (backend) + eslint (frontend)

make worker           # ejecuta el consumer AMQP manualmente
make key-generate     # regenera APP_KEY
make route-list       # lista las rutas API
```

## Solución de problemas

### Infra no arranca / red maya_network no existe

```bash
cd ../maya_infra && ./ensure-running.sh
```

### Reset completo de la BD

```bash
make down
# (opcional) destruir volume de postgres en maya_infra si hay que recrear la BD
make up
make migrate-fresh
```

### Backend no conecta a la BD

El `backend/.env` se crea automáticamente con los valores correctos. Si necesitas verificar:

```env
DB_HOST=maya_infra_postgres   # NO 127.0.0.1, NO pgsql
DB_DATABASE=log_mgmt_db
DB_USERNAME=log_mgmt_user
```

### Frontend no resuelve imports `@maya/shared-*`

Los paquetes compartidos viven en `../maya_infra/packages/` y se montan como bind mount en `/maya_infra/packages` dentro del contenedor. Si Vite falla al resolverlos:

```bash
make down && make up        # fuerza re-bind
docker compose exec frontend sh -c 'ls /maya_infra/packages'
```

## Arquitectura

```text
maya_infra/
  Traefik (:80, :8888) ──── enruta *.localhost
  PostgreSQL (:5432)    ──── BD compartida (log_mgmt_db)
  Keycloak (:8180)      ──── IdP compartido del ecosistema
  RabbitMQ (:5672)      ──── broker compartido

maya_logs/
  backend/  (:8002) ──→ PostgreSQL · Keycloak (JWT JWKS) · RabbitMQ
  worker/           ──→ consumer AMQP (logs:consume)
  frontend/ (:5176) ──→ React 19 + Vite · Keycloak (OIDC) · API
```

Todos los servicios comparten la red Docker `maya_network`.
