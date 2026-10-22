# 🏗️ Arquitectura y Riesgos — Log Management Dashboard

⚠️ **DOCUMENTO HISTÓRICO / PARCIALMENTE OBSOLETO**

Este documento describe decisiones arquitectónicas consideradas en 2026-03-14 pero **NO implementadas**.
El proyecto mantiene **React 19 + Vite + TypeScript** como frontend (ver README.md). Las referencias a
"Livewire 3" y "Laravel SSR reactivo" (líneas 46, 49–55) son **históricas** y deben ignorarse para
desarrollo y despliegue en producción. El documento se mantiene como referencia de qué se descartó y por qué.

---

**Proyecto:** Panel de Administración y Gestión de Logs Multi-Aplicación
**Fecha:** 2026-03-03
**Skill:** System Architect — C4 Model + STRIDE + NFRs
**Estado:** FASE 2 Completada (pero con stack diferente al documentado aquí)

---

## 1. Requisitos No Funcionales (NFRs)

### 1.1 Escalabilidad

| NFR | Descripción | Decisión |
| --- | --- | --- |
| NFR-ESC-01 | Monocliente con un único usuario administrador. No se requiere escalado horizontal de frontend. | El backend se ejecuta como proceso único PHP-FPM (Laravel). Sin necesidad de balanceador de carga. |
| NFR-ESC-02 | Volumen de logs: múltiples aplicaciones. Puede crecer a miles de registros por día. | PostgreSQL con índices en `(severity, created_at, application_id)`. Paginación obligatoria en todas las queries. |
| NFR-ESC-03 | El histórico es de escritura única y lectura frecuente. | Tabla `archived_logs` separada de `logs`. Sin TTL. Candidata a particionado por año si crece. |

### 1.2 Disponibilidad (SLA)

| NFR | Descripción | Decisión |
| --- | --- | --- |
| NFR-DIS-01 | Sistema interno de administración. SLA objetivo: **99.5%** (uso en horario laboral). | No requiere configuración HA compleja. Un proceso de reinicio automático (systemd + PHP-FPM/Supervisor) es suficiente. |
| NFR-DIS-02 | La caída del panel no afecta a la ingesta de logs (n8n → Postgres es independiente). | Desacoplamiento garantizado por diseño. Riesgo bajo. |

### 1.3 Latencia

| NFR | Descripción | Decisión |
| --- | --- | --- |
| NFR-LAT-01 | Dashboard debe cargar en < 1 s. Las cards solo cuentan registros agrupados (queries agregadas). | Usar `COUNT(*) GROUP BY severity` con índice. Cachear resultado en memoria por 5-10 s antes de broadcast SSE. |
| NFR-LAT-02 | Listado tabular con filtros: respuesta < 500 ms. | Índices compuestos en columnas de filtro. LIMIT/OFFSET o cursor-based pagination. |
| NFR-LAT-03 | SSE: evento push en < 2 s desde inserción en DB por n8n. | Polling interno ligero (1-2 s) sobre tabla `logs` o trigger NOTIFY/LISTEN de PostgreSQL. |

### 1.4 Observabilidad

| NFR | Descripción | Decisión |
| --- | --- | --- |
| NFR-OBS-01 | El propio sistema gestiona logs de otras apps; sus propios errores internos se escriben en fichero local. | Logs de aplicación en `/var/log/log-dashboard/app.log` con rotación. No se auto-reportan en Postgres para evitar recursión. |
| NFR-OBS-02 | Auditoría de acciones del administrador (archivado, comentarios). | Campo `archived_by`, `archived_at`, `updated_at` en tabla `archived_logs`. |

---

## 2. Decisiones Tecnológicas Justificadas

> **⚠️ Actualizado 2026-03-14:** Stack cambiado de Laravel + React 19 (SPA) a **Laravel 12 + Livewire 3** (SSR reactivo). Sin SPA independiente.

| Componente | Tecnología Elegida | Alternativa Descartada | Justificación |
| --- | --- | --- | --- |
| Backend | **Laravel 12 (PHP)** | — | Framework maduro con ORM Eloquent. Controladores web clásicos. SSE via `StreamedResponse`. |
| Frontend dinámico | **Livewire 3** | React 19 + Vite, Vue 3 | Componentes reactivos server-side sin necesidad de SPA ni build JS separado. Ideal para herramienta interna. Reduce complejidad operacional enormemente. |
| Microinteracciones | **Alpine.js** (incluido con Livewire) | React hooks | Microinteracciones en cliente (modales, toggles). Sin bundle JS complejo. |
| Estructura proyecto | **Monolito Laravel: Blade + Livewire** | SPA separada, Inertia.js | Un único proceso, un despliegue. Sin CORS. Sin doble repositorio. Las vistas son Blade con componentes Livewire embebidos. |
| API Contract | **Livewire Actions (wire:click, wire:model)** | REST puro, GraphQL | Con Livewire no se necesita una REST API para el frontend. El SSE sigue siendo un endpoint HTTP independiente. |
| Tiempo real | **SSE via `StreamedResponse` de Laravel** | WebSockets (Reverb/Pusher) | Comunicación unidireccional (servidor → cliente). Compatible con Livewire: el cliente JS actualiza el componente al recibir el evento. |
| Rich Text Editor | **TipTap 2 + Alpine.js bridge** | Quill.js, CKEditor | TipTap es headless y funciona con Livewire mediante un componente Alpine que sincroniza el contenido con `wire:model`. Soporte de imágenes y formato enriquecido. |
| Base de datos | **PostgreSQL** (existente) | — | Ya existe el flujo n8n → Postgres. Eloquent ORM soporta PostgreSQL de forma nativa. |
| Notificaciones DB | **pg_notify / LISTEN** via worker Laravel | Polling puro | Reduce latencia SSE. Proceso Artisan Command gestionado por Supervisor. |
| Autenticación | **Sesión externa (API) + mock de sesión** | Laravel Sanctum SPA, Passport | La pantalla de Login queda FUERA del alcance. El usuario llega autenticado desde un sistema externo. En desarrollo se simula con un mock de usuario en sesión. No hay endpoint de login en este panel. |
| i18n | **Laravel Lang (resources/lang/)** | Stubs en blade | Textos UI en archivos de idioma. Soporte base para `es` y estructura preparada para `va`. |

---

## 3. Modelado de Amenazas — STRIDE

### Componentes críticos analizados

1. **Backend Laravel** (controladores + Livewire Actions + SSE)
2. **Base de Datos PostgreSQL** (logs activos + histórico + error_codes)
3. **Frontend Blade/Livewire** (panel admin, SSR)
4. **Editor Rich Text** (comentarios con imágenes — TipTap 2 + Alpine.js)

---

### 3.1 Componente: API Backend

| STRIDE | Amenaza | Riesgo | Mitigación |
| --- | --- | --- | --- |
| **S** Spoofing | Un agente externo suplanta al administrador y accede al panel. | ALTO | Autenticación con sesión firmada (JWT/cookie httpOnly). Aunque es un único usuario, se requiere login. |
| **T** Tampering | Modificación de un log activo antes de archivarlo. | MEDIO | Los logs activos son solo lectura desde la API (solo n8n puede insertar). Endpoints de escritura solo para `archived_logs`. |
| **R** Repudiation | No hay trazabilidad de quién archivó o comentó. | BAJO | Campos `archived_by` y `created_at` en todas las acciones de escritura. |
| **I** Information Disclosure | Endpoint SSE expuesto sin autenticación filtra logs en tiempo real. | ALTO | El stream SSE debe requerir sesión válida antes de abrirse. |
| **D** Denial of Service | Peticiones masivas al endpoint de listado con filtros pesados. | BAJO | Monocliente + red interna. Rate limiting básico como precaución. |
| **E** Elevation of Privilege | No aplica (único rol administrador). | N/A | — |

---

### 3.2 Componente: Base de Datos PostgreSQL

| STRIDE | Amenaza | Riesgo | Mitigación |
| --- | --- | --- | --- |
| **S** Spoofing | Otro servicio usa las credenciales de DB del panel. | MEDIO | Usuario de DB dedicado con permisos mínimos (SELECT en `logs`, CRUD en `archived_logs`). |
| **T** Tampering | Borrado accidental o malicioso del histórico. | ALTO | El usuario de DB del panel no tiene permiso DELETE sobre `archived_logs`. Solo INSERT/UPDATE. |
| **R** Repudiation | Sin log de cambios en DB. | BAJO | Timestamps en todas las tablas (`created_at`, `updated_at`). |
| **I** Information Disclosure | Credenciales de DB en código fuente. | ALTO | Credenciales en variables de entorno (`.env`, nunca en repositorio). |
| **D** Denial of Service | Query sin LIMIT satura la DB. | MEDIO | ORM/query builder con LIMIT máximo hardcoded (ej. 500 filas por petición). |
| **E** Elevation of Privilege | Escalado de permisos vía SQL injection. | MEDIO | Uso exclusivo de queries parametrizadas / ORM. Nunca concatenar strings SQL. |

---

### 3.3 Componente: Editor Rich Text (Comentarios)

| STRIDE | Amenaza | Riesgo | Mitigación |
| --- | --- | --- | --- |
| **S** Spoofing | No aplica (editor local del admin). | N/A | — |
| **T** Tampering | XSS embebido en HTML del comentario guardado. | ALTO | Sanitizar HTML en el backend antes de persistir (ej. `DOMPurify` server-side). Nunca renderizar HTML crudo sin sanitizar. |
| **R** Repudiation | — | N/A | — |
| **I** Information Disclosure | Imágenes subidas expuestas sin control. | MEDIO | Si las imágenes se suben a storage, requerir token de acceso firmado. Alternativa: almacenar como base64 en la propia entrada (más simple pero limita tamaño). |
| **D** Denial of Service | Upload de imágenes enormes agota almacenamiento. | MEDIO | Límite de tamaño por upload (ej. 2 MB) y por comentario (ej. 10 MB total). |
| **E** Elevation of Privilege | Archivo malicioso disfrazado de imagen. | MEDIO | Validar MIME type real (magic bytes) en el backend, no solo la extensión. |

---

## 4. Resumen de Riesgos por Severidad

| Severidad | Amenazas |
| --- | --- |
| 🔴 ALTO | SSE sin autenticación · Borrado de histórico · Credenciales en repo · XSS en comentarios |
| 🟡 MEDIO | Suplantación de servicio DB · SQL Injection · Imágenes sin control · DoS por queries |
| 🟢 BAJO | Repudio de acciones · DoS en red interna |

---

## 5. Restricciones Técnicas Identificadas

1. **La estructura de la tabla `logs` en Postgres ya existe** — el schema se debe descubrir/documentar antes del desarrollo (campo `severity` enum, FK `application_id`, campo `message`, timestamps). La tabla `applications` es nueva y debe crearse con migración.
2. **El flujo n8n no se modifica** — la ingesta de logs es ajena al alcance de este proyecto.
3. **Sin pantalla de login en este panel** — la autenticación viene de un sistema externo. Para desarrollo, mock de usuario en sesión Laravel.
4. **Sin despliegue cloud** — la app corre en infraestructura local.
5. **Sin SPA / sin build Vite separado** — el frontend es Blade + Livewire servido directamente por Laravel.
6. **Tabla `error_codes` es nueva** — debe crearse con migración. Clave compuesta `(code + application_id)` (FK a tabla `applications`). CRUD completo en este panel.
7. **Comentarios son exclusivos del Histórico y Error Codes** — no aparecen en la vista de logs activos.
8. **Filtros → GET, Orden → POST** — los filtros y página se pasan como query params en la URL (persistibles/compartibles). La ordenación de columnas se envía como formulario POST y no persiste en URL.
