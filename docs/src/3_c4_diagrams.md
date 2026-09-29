# 📐 Documentación Visual — Log Management Dashboard

**Proyecto:** Panel de Administración y Gestión de Logs Multi-Aplicación
**Fecha:** 2026-03-03 · **Actualizado:** 2026-03-14 (stack React 19 + Vite + Laravel 12 API)
**Skill:** System Architect — C4 Model + Flujos
**Estado:** FASE 4 Completada

---

## 1. C4 Level 1 — System Context

> Cómo el sistema encaja en el mundo, interactuando con usuarios y sistemas externos.

```mermaid
C4Context
    title System Context — Log Management Dashboard

    Person(admin, "Administrador", "Usuario que revisa,\ngestiona y archiva logs de error.\nSe autentica en sistema externo.")

    System(dashboard, "Log Management Dashboard", "Panel web SSR para visualizar logs en\ntiempo real, filtrarlos, archivarlos\ncon comentarios enriquecidos y gestionar\nel catálogo de códigos de error.")

    System_Ext(auth_ext, "Sistema de Autenticación Externa", "API externa que gestiona usuarios.\nEl panel no tiene pantalla de login propia.\nEn desarrollo: mock de sesión en Laravel.")

    System_Ext(n8n, "n8n (Automatización)", "Orquesta flujos de datos.\nInserta logs en PostgreSQL\ndesde múltiples aplicaciones.")

    System_Ext(apps, "Aplicaciones Monitorizadas", "Servicios que generan errores\n(api-gateway, workers, etc.).\nNo interactúan directamente con el panel.")

    System_Ext(postgres, "PostgreSQL", "Base de datos compartida.\nContiene tabla logs (ingesta de n8n)\ny tablas archived_logs, comments, error_codes\ny users (gestionadas por el panel).")

    Rel(admin, auth_ext, "Se autentica", "HTTPS")
    Rel(auth_ext, dashboard, "Redirige con sesión activa", "HTTPS / cookie")
    Rel(admin, dashboard, "Usa el panel", "HTTPS / Navegador")
    Rel(apps, n8n, "Envían eventos de error", "HTTP / Webhook")
    Rel(n8n, postgres, "Inserta logs", "SQL / TCP")
    Rel(dashboard, postgres, "Lee logs activos,\nescribe archivados, comentarios y error_codes", "SQL / TCP")
```

---

## 2. C4 Level 2 — Container Diagram

> Las aplicaciones y bases de datos que componen el sistema.

```mermaid
C4Container
    title Container Diagram — Log Management Dashboard

    Person(admin, "Administrador", "Usuario del panel.")

    System_Ext(auth_ext, "Auth Externa", "Gestiona usuarios y sesión.")
    System_Ext(n8n, "n8n", "Inserta logs en PostgreSQL.")

    System_Boundary(dashboard, "Log Management Dashboard") {
        Container(laravel, "Backend API", "Laravel 12 API (PHP 8.4)", "Expone API REST para CRUD de logs,\narchivos, comentarios, error codes.\nAutenticación JWT (Keycloak).\nProporciona endpoint SSE para actualizaciones.")

        Container(frontend, "Frontend SPA", "React 19 + Vite + TypeScript", "Interfaz de usuario interactiva.\nComponentes React para dashboard, lista de logs,\ndetalle y archivo con comentarios enriquecidos.\nConecta a API Backend + SSE para tiempo real.")

        Container(sse_worker, "SSE Worker", "Laravel Artisan Command\n(Supervisor)", "Escucha pg_notify de PostgreSQL\ny emite eventos SSE al navegador\ncuando hay nuevos logs.")
    }

    SystemDb_Ext(postgres, "PostgreSQL", "BD compartida.\nTablas: logs, archived_logs,\ncomments, error_codes, users.")

    Rel(admin, auth_ext, "Autentica", "HTTPS")
    Rel(auth_ext, frontend, "Redirige con sesión\n(OAuth2/OIDC)", "HTTPS")
    Rel(admin, frontend, "Navega el panel", "HTTPS / Navegador")
    Rel(frontend, laravel, "Llamadas API REST\n(JWT Bearer)", "HTTPS / JSON")
    Rel(frontend, laravel, "Conexión SSE (Dashboard)", "EventSource / HTTPS")
    Rel(sse_worker, laravel, "Publica actualizaciones SSE", "Proceso Artisan")
    Rel(laravel, postgres, "Lee y escribe\n(Eloquent ORM)", "TCP / SQL")
    Rel(sse_worker, postgres, "Escucha NOTIFY en tabla logs", "TCP / pg_notify")
    Rel(n8n, postgres, "INSERT en logs", "TCP / SQL")
```

---

## 3. Flujo 1 — Archivado de un Log

> Secuencia desde que el admin decide archivar hasta que el log desaparece de la vista activa.

```mermaid
sequenceDiagram
    actor Admin
    participant FE as React LogDetail
    participant API as API Backend
    participant BE as Laravel (Action)
    participant DB as PostgreSQL

    Admin->>LW: wire:click "Guardar en Histórico"
    LW->>LW: Muestra modal de confirmación (Alpine.js x-show)
    Admin->>LW: Confirma acción
    LW->>BE: Livewire Action: archiveLog(id)
    BE->>BE: Valida sesión activa (middleware auth)
    BE->>BE: Sanitiza HTML del comentario (HTMLPurifier)
    BE->>DB: BEGIN TRANSACTION
    DB-->>BE: OK
    BE->>DB: INSERT INTO archived_logs (application_id, archived_by_id, error_code_id, severity, message, ...)
    BE->>DB: INSERT INTO comments (archived_log_id, user_id, content)
    BE->>DB: DELETE FROM logs WHERE id = {id}
    BE->>DB: COMMIT
    DB-->>BE: OK
    BE-->>LW: Livewire re-render (log desaparece de la lista)
    LW-->>Admin: Flash message "Log archivado correctamente"
    Note over LW,BE: El contador del dashboard se actualiza\nvía SSE en < 2s
```

---

## 4. Flujo 2 — Actualización en Tiempo Real (SSE + Livewire)

> Cómo un nuevo log insertado por n8n llega al dashboard del administrador.

```mermaid
sequenceDiagram
    participant N8N as n8n
    participant DB as PostgreSQL
    participant Worker as SSE Worker (Artisan)
    participant BE as Laravel (SSE endpoint)
    participant JS as Alpine.js (cliente)
    actor Admin

    N8N->>DB: INSERT INTO logs (severity, application_id, message, ...)
    DB->>Worker: NOTIFY 'logs_channel' (pg_notify)
    Worker->>BE: Emite evento al stream SSE abierto
    BE->>DB: SELECT COUNT(*) GROUP BY severity (query agregada)
    DB-->>BE: Contadores actualizados
    BE-->>JS: SSE event: { type: "log_counts", data: {...} }
    JS->>JS: $wire.dispatch('refreshCounts', data)
    JS-->>Admin: Cards del dashboard actualizadas en < 2s
```

---

## 5. Flujo 3 — Autenticación Externa + Mock de Desarrollo

> El panel no gestiona login. El usuario llega con sesión activa del sistema externo.

```mermaid
sequenceDiagram
    actor Admin
    participant Ext as Auth Externa
    participant LW as Laravel Panel

    Admin->>Ext: Login en sistema externo
    Ext-->>Admin: Token / Cookie de sesión
    Admin->>LW: Accede al panel con cookie
    LW->>LW: Middleware verifica sesión externa
    LW-->>Admin: Acceso concedido → Dashboard

    Note over LW: En DESARROLLO: el middleware devuelve\nun usuario mockeado sin validar auth real.\n"$user = User::find(1);" en AuthMock middleware.

    Admin->>LW: Pulsa "Cerrar sesión"
    LW->>LW: Invalida sesión local
    LW-->>Admin: Redirige a portal externo
```

---

## 6. Diagrama de Base de Datos (Entity-Relationship)

> Schema completo de PostgreSQL actualizado. La tabla `logs` es de solo lectura para el panel (la gestiona n8n).

```mermaid
erDiagram
    applications {
        bigserial   id          PK
        varchar     name        "UK - nombre único"
        text        description
        timestamptz created_at
    }

    users {
        bigserial   id              PK
        varchar     name
        varchar     email           "UK"
        varchar     external_id     "ID auth externo"
        timestamptz created_at
        timestamptz updated_at
    }

    error_codes {
        bigserial   id              PK
        varchar     code            "UNIQUE con application_id"
        bigint      application_id  FK
        varchar     name
        text        description
        severity    severity        "enum: Critical/High/Medium/Low"
        timestamptz created_at
        timestamptz updated_at
    }

    logs {
        bigserial   id                      PK
        bigint      error_code_id           FK "nullable - si está catalogado"
        bigint      application_id          FK
        severity    severity                "enum"
        text        message
        varchar     file                    "Fichero donde ocurre"
        integer     line                    "Línea del fichero"
        jsonb       metadata                "Stack trace, contexto"
        bigint      matched_archived_log_id FK "nullable - issue conocido"
        boolean     resolved                "false por defecto"
        timestamptz created_at
    }

    archived_logs {
        bigserial   id                  PK
        bigint      application_id      FK
        bigint      archived_by_id      FK
        bigint      error_code_id       FK "nullable - copia del log original"
        severity    severity
        text        message
        jsonb       metadata
        text        description         "Editable por admin"
        varchar     url_tutorial        "nullable"
        timestamptz original_created_at
        timestamptz archived_at
        timestamptz updated_at
    }

    comments {
        bigserial   id              PK
        text        comentable_type   "Enum: 'archived_log' o 'error_code'"
        bigint      comentable_id FK
        bigint      user_id         FK
        text        content         "HTML sanitizado"
        timestamptz created_at
        timestamptz updated_at
    }

    applications ||--o{ error_codes : "tiene"
    applications ||--o{ logs : "genera"
    applications ||--o{ archived_logs : "tiene"
    users ||--o{ archived_logs : "archiva"
    users ||--o{ comments : "escribe comentarios en logs"
    users ||--o{ comments : "escribe comentarios en error codes"
    error_codes ||--o{ logs : "cataloga"
    error_codes ||--o{ error_code_comments : "tiene"
    archived_logs ||--o{ comments : "tiene"
    archived_logs ||--o{ logs : "enlaza logs futuros"
```

> **Notas de diseño:**
>
> - `users` contiene los usuarios del panel (sincronizados o mockeados desde auth externa). Sin contraseña local.
> - `logs` usa **DELETE físico** en dos situaciones:
>   1. **Al archivar** (acción del admin): se hace INSERT en `archived_logs` y DELETE inmediato del log original dentro de la misma transacción.
>   2. **Script de purga** (tarea programada — diaria/semanal/mensual): elimina los logs con `resolved = true` más antiguos de N días que no fueron archivados. Se implementa como un `php artisan logs:purge --days=30` registrado en `routes/console.php` con `->daily()`.
> - `logs.resolved` (boolean): estado que el admin puede activar sin archivar. El script de purga usa este flag + `created_at` como criterio de eliminación.
> - **No hay soft delete en `logs`**: la tabla puede recibir miles de logs de múltiples apps y debe mantenerse compacta. El histórico duradero vive en `archived_logs`.
> - `archived_logs` almacena una **copia desnormalizada completa** (`severity`, `message`, `metadata`, `error_code_id`, etc.) para que el histórico sea autónomo una vez que el log original se elimine.
> - `archived_logs.url_tutorial` y `description` son editables solo desde la vista de Histórico.
> - El enum de severidad se declara una vez y se reutiliza en `logs` y `archived_logs`:
>
>   ```sql
>   CREATE TYPE severity AS ENUM ('critical', 'high', 'medium', 'low', 'other');
>   ```
>
>   En las migraciones Laravel, usar `$table->enum('severity', ['critical','high','medium','low','other'])`
>   o un cast personalizado con `use HasCasts` + `SeverityEnum::class`.

### Índices recomendados

> Definir en las migraciones Laravel con `Schema::table('logs', ...)` tras crear las columnas.

```php
// logs — rendimiento de los listados principales
$table->index('error_code_id');                               // búsquedas por catálogo
$table->index(['application_id', 'created_at']);              // listado principal (desc)
$table->index(['severity', 'resolved']);                      // filtros combinados
$table->index('matched_archived_log_id');                     // join de enlace a issue

// error_codes — integridad de catálogo
$table->unique(['code', 'application_id']);                   // unicidad de negocio
```

### Matching de logs con issues conocidos (asistido desde la vista de detalle)

> **El log insertado por n8n no tiene contexto** para saber si ya existe un issue archivado similar; ese conocimiento lo tiene el administrador cuando abre la vista de detalle. Por tanto, el campo `matched_archived_log_id` **se rellena manualmente o con sugerencia asistida desde `LogDetail`**, no en el momento del INSERT.

**Flujo en la vista de detalle (`/logs/{id}`):**

1. El admin abre un log activo.
2. Si el log tiene `error_code_id`, el componente Livewire consulta en segundo plano los `archived_logs` con la misma `application_id` + `error_code_id` y los presenta como sugerencias.
3. El admin selecciona el issue conocido (o ignora las sugerencias).
4. El Livewire Action actualiza `logs.matched_archived_log_id`.

```php
// En el componente Livewire LogDetail
public function loadSuggestedMatches(): void
{
    if ($this->log->error_code_id) {
        $this->suggestedMatches = ArchivedLog::where('application_id', $this->log->application_id)
            ->where('error_code_id', $this->log->error_code_id)
            ->latest('archived_at')
            ->limit(5)
            ->get();
    }
}

public function linkToArchivedLog(int $archivedLogId): void
{
    $this->log->update(['matched_archived_log_id' => $archivedLogId]);
    $this->dispatch('matchLinked');
}
```

> `matched_archived_log_id` puede seguir siendo `null` si el admin no encuentra un issue relacionado. Es un campo informativo, no un requisito para archivar.

---

## 7. Mapa de Rutas — Laravel Web Routes

```mermaid
graph TD
    A["/ (redirect → /dashboard)"] --> B["/dashboard\n@livewire DashboardCards"]
    B --> C["/logs\n@livewire LogsTable\n?type=&app=&from=&to=&q="]
    C --> D["/logs/{id}\n@livewire LogDetail\nVer · Guardar · Solucionado"]
    D -->|"Guardar en histórico"| E["/historico"]
    B --> E
    E --> F["/historico\n@livewire ArchivedLogsTable\n?type=&app=&from=&to=&q="]
    F --> G["/historico/{id}\n@livewire ArchivedLogDetail\n+ CommentSection"]
    B --> H["/error-codes\n@livewire ErrorCodesTable\n?app=&q="]
    H --> I["Modal crear error code\n@livewire ErrorCodeForm"]
    H --> J["/error-codes/{id}\n@livewire ErrorCodeForm (editar)\n+ ErrorCodeCommentSection"]
```

> **Navegación global:** `x-nav` (componente Blade) con enlaces a Dashboard / Logs / Histórico / Error Codes / Cerrar Sesión.
