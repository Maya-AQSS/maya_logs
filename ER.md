# ER — maya_logs

## Diagrama

```mermaid
erDiagram
    APPLICATIONS ||--o{ LOGS : "1:N"
    APPLICATIONS ||--o{ ERROR_CODES : "1:N"
    APPLICATIONS ||--o{ ARCHIVED_LOGS : "1:N"
    ERROR_CODES ||--o{ LOGS : "1:N"
    ERROR_CODES ||--o{ COMMENTS : "comentable=ErrorCode"
    ARCHIVED_LOGS ||--o{ COMMENTS : "comentable=ArchivedLog"
    USERS ||--o{ COMMENTS : "user_id→comments.user_id"
    USERS ||--o{ ARCHIVED_LOGS : "archived_by_id→archived_logs.archived_by_id"
    TEAMS ||--o{ TEAM_MEMBERS : "1:N"
    USERS ||--o{ TEAM_MEMBERS : "1:N"
    USERS ||--o{ USER_STUDY_TYPES : "1:N"
    USERS ||--o{ USER_STUDIES : "1:N"
    USERS ||--o{ USER_COURSE_MODULES : "1:N"
    USERS ||--o{ USER_RESOLVED_PERMISSIONS : "1:N"

    APPLICATIONS {
        bigint id PK
        string name
        string slug UK
        text description "nullable"
        boolean is_active
        timestamp created_at
    }

    ERROR_CODES {
        bigint id PK
        string code
        bigint application_id "sin FK física (FDW)"
        string name
        text description "nullable"
        string file "nullable"
        integer line "nullable"
        timestamp created_at
        timestamp updated_at
    }

    LOGS {
        bigint id PK
        bigint error_code_id FK "nullable"
        bigint application_id "sin FK física (FDW)"
        enum severity "critical|high|medium|low|other"
        text message
        string file "nullable"
        integer line "nullable"
        jsonb metadata "nullable"
        boolean resolved
        timestamptz created_at
    }

    ARCHIVED_LOGS {
        bigint id PK
        bigint application_id "sin FK física (FDW)"
        string archived_by_id "varchar(255), UUID usuario Odoo, sin FK (FDW)"
        bigint error_code_id FK "nullable"
        enum severity "critical|high|medium|low|other"
        text message
        jsonb metadata "nullable"
        text description "nullable"
        string url_tutorial "nullable"
        timestamptz original_created_at
        timestamptz archived_at
        timestamptz updated_at "nullable"
    }

    COMMENTS {
        bigint id PK
        string commentable_type "ArchivedLog|ErrorCode"
        bigint commentable_id
        string user_id "varchar(255), UUID usuario Odoo, sin FK (FDW)"
        longText content
        timestamp created_at
        timestamp updated_at
    }

    "%% FDW Odoo (read-only) — shared-profile-laravel"
    USERS {
        varchar(255) id PK "UUID Keycloak"
        varchar(255) name "display_name de Odoo"
        varchar(255) email UK
        varchar(150) first_name
        varchar(150) last_name
        varchar(150) username
        varchar(64) employee_id
        varchar(32) dni
        varchar(64) employee_type
        boolean is_active
    }

    TEAMS {
        varchar(255) id PK "UUID-shaped"
        varchar(255) name
        text description "nullable"
        varchar(255) owner_id
        boolean is_department
        timestamp created_at
        timestamp updated_at
        timestamp deleted_at "nullable"
    }

    TEAM_MEMBERS {
        varchar(255) id PK "UUID-shaped"
        varchar(255) team_id FK
        varchar(255) user_id FK
        varchar(100) role "nullable"
        timestamp created_at
        timestamp updated_at
    }

    USER_STUDY_TYPES {
        varchar(255) id PK "md5 hash"
        varchar(255) user_id FK
        varchar(255) study_type_id "compañía en Odoo"
    }

    USER_STUDIES {
        varchar(255) id PK "md5 hash"
        varchar(255) user_id FK
        varchar(255) study_id
    }

    USER_COURSE_MODULES {
        varchar(255) id PK "md5 hash"
        varchar(255) user_id FK
        varchar(255) course_module_id
    }

    "%% FDW maya_auth (read-only)"
    USER_RESOLVED_PERMISSIONS {
        varchar(255) user_id "FK Odoo UUID"
        varchar(191) permission_slug
    }

    "%% Framework/Sistema"
    CACHE {
        string key PK
        mediumText value
        integer expiration IDX
    }

    CACHE_LOCKS {
        string key PK
        string owner
        integer expiration IDX
    }
```

## Clasificación de tablas

| Entidad | Mecanismo | Fuente | Evidencia |
|---------|-----------|--------|-----------|
| applications | FDW maya_auth (read-only) | maya_auth.applications | backend/database/migrations/2026_03_16_135232_create_applications_table.php:47-57 |
| error_codes | FÍSICA (propia) | maya_logs local | backend/database/migrations/2026_03_16_135332_create_error_codes_table.php:9-24 |
| logs | FÍSICA (propia) | maya_logs local | backend/database/migrations/2026_03_16_135340_create_logs_table.php:29-51 |
| archived_logs | FÍSICA (propia) | maya_logs local | backend/database/migrations/2026_03_16_135730_create_archived_logs_table.php:9-29 |
| comments | FÍSICA (propia) | maya_logs local | backend/database/migrations/2026_03_16_140633_create_comments_table.php:9-27 |
| users | FDW Odoo (read-only) | odoo.public.v_app_users | backend/vendor/ceedcv-maya/shared-profile-laravel/database/migrations/users/2026_05_19_000001_create_users_foreign_table.php:40-144 |
| teams | FDW Odoo (read-only) | odoo.public.v_dms_teams | backend/vendor/ceedcv-maya/shared-profile-laravel/database/migrations/teams/2026_05_18_000001_create_teams_foreign_table.php:42-119 |
| team_members | FDW Odoo (read-only) | odoo.public.v_dms_team_members | AppServiceProvider.php:102 (loadMigrationsFrom::teams) |
| user_study_types | FDW Odoo (read-only) | odoo.public.res_company_users_rel + res_users (vista) | backend/vendor/ceedcv-maya/shared-profile-laravel/database/migrations/academic-assignments/2026_05_18_000003_create_user_study_types_foreign_table.php:35-143 |
| user_studies | FDW Odoo (read-only) | odoo.public (vista computed) | AppServiceProvider.php:101 (loadMigrationsFrom::academicAssignments) |
| user_course_modules | FDW Odoo (read-only) | odoo.public (vista computed) | AppServiceProvider.php:101 (loadMigrationsFrom::academicAssignments) |
| user_resolved_permissions | FDW maya_auth (read-only) | maya_auth.v_logs_user_permissions | backend/vendor/ceedcv-maya/shared-profile-laravel/database/migrations/user-permissions/2026_05_18_000010_create_user_resolved_permissions_view.php:74-124 |
| cache | Framework/Sistema | Laravel cache | backend/database/migrations/0001_01_01_000001_create_cache_table.php:14-17 |
| cache_locks | Framework/Sistema | Laravel cache locks | backend/database/migrations/0001_01_01_000001_create_cache_table.php:20-23 |

### Tablas de framework/sistema

- **cache**: almacén temporal clave→valor (mediumText), expiration index
- **cache_locks**: locks distribuidos para cache

## Discrepancias

Ninguna. `logs` y `error_codes` no tienen FK física hacia `applications` (que es vista sobre FDW maya_auth) — es correcto, guardan solo el id. `comments.user_id` y `archived_logs.archived_by_id` no tienen FK física hacia `users` (FDW Odoo) — es correcto, contienen UUID del usuario como string. `comments` es polymorphic (commentable_type + commentable_id) sobre ArchivedLog y ErrorCode según migración:27 y modelo:40-46.
