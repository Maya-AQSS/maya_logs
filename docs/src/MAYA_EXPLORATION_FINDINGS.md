# Maya Authorization Exploration: Complete Findings

## 1. Project Structure

**Location**: `/home/ggarrido/development/Desarrollo CEEDCV/`

Projects identified:
- `maya_authorization/` — Centralized IdP + PDP microservice (Laravel 13 + React 19)
- `maya_dashboard/` — Main dashboard application (React 19 + Vite)
- Infrastructure: Docker Compose with Traefik, Keycloak, PostgreSQL 17, Redis 7

**Architecture**: Maya authorization is the central hub; maya_dashboard and other apps authenticate via Keycloak and query authorization via X-App-Key machine-to-machine headers.

---

## 2. Applications Data Model

**Model File**: `/home/ggarrido/development/Desarrollo CEEDCV/maya_authorization/backend/app/Models/Application.php`

**Fields**:
- `id` (integer, primary key)
- `name` (string)
- `slug` (string, unique)
- `description` (text, nullable)
- `traefik_url` (string) — **the forward/redirect URL for the application**
- `api_key_hash` (string) — SHA-256 hash of the API key
- `allowed_origins` (json, nullable) — CORS allowed origins
- `is_active` (boolean)
- `created_at`, `updated_at` (timestamps)
- `deleted_at` (timestamp, soft deletes)

**Relations**:
- `permissions()` — hasMany Permission
- `userRoles()` — hasMany UserRole

**Helper Methods**:
- `regenerateApiKey()` — generates new SHA-256 hashed API key, returns plain key once
- `verifyApiKey(plainKey)` — validates plain key against stored hash

**Schema Location**: Migrations created by `2025_01_01_000001_create_applications_table.php` (confirmed via migration execution order)

**CRITICAL GAP**: No `is_favorite` field exists in the model, but the frontend mapper expects it (see section 5).

---

## 3. Applications API Endpoints

**Authentication**: All endpoints use X-App-Key header (machine-to-machine auth via middleware `verify.app.key`)

**Base URL**: `https://authorization-api.maya.test/api/v1` (local via Traefik)

### Accessible to External Apps (M2M)

**GET `/api/v1/auth/user/{userId}`**
- Returns user permissions for a specific app (requires `?app={slug}` query param)
- Controller: `AuthorizationController::userPermissions()` (line 32)
- Response: `UserPermissionsResource`

**GET `/api/v1/auth/user/{userId}/apps`**
- Returns all applications accessible to user
- Controller: `AuthorizationController::accessibleApps()` (line 35)
- Response: Collection of `ApplicationResource`
- **This is the endpoint maya_dashboard uses** (confirmed in `toolsApi.js` line 34)

**POST `/api/v1/auth/check`** (rate-limited: 60 req/min per app key)
- Boolean permission check: `{ "userId": "...", "permission": "...", "app": "..." }`
- Controller: `AuthorizationController::check()` (line 38)
- Response: `{ "hasPermission": boolean }`

**POST `/api/v1/auth/token/validate`**
- Validates Keycloak token and returns user + permissions
- Controller: `AuthorizationController::validateToken()` (line 41)
- Response: User object with permission claims

### Admin SPA Endpoints (Bearer token auth via Keycloak)

**GET `/api/v1/applications`**
- Paginated list of all applications (admin only)
- Controller: `ApplicationController::index()`
- Filters: `search`, `is_active`

**POST `/api/v1/applications/{application}/regenerate-key`**
- Regenerates API key for an application
- Controller: `ApplicationController::regenerateApiKey()`

**GET `/api/v1/users/{userId}/apps`**
- **DOES NOT EXIST** — no endpoint to get apps for a specific user via admin API

**No "me" endpoint exists** to retrieve current authenticated user profile.

---

## 4. Forward (fwd) Mechanism

**"fwd" does NOT exist as a separate mechanism.**

Instead, the `traefik_url` field on the Application model serves as the forward/redirect URL:
- Stored in applications table
- Returned in ApplicationResource serialization
- Expected by maya_dashboard UI (mapped to `documentationUrl` in toolMapper.js line 22)

**Current flow**:
1. maya_dashboard calls `GET /api/v1/auth/user/{userId}/apps` (X-App-Key auth)
2. Maya_authorization returns ApplicationResource with `traefik_url` field
3. Maya_dashboard stores it as `documentationUrl` in Tool object
4. When user clicks an app tile, frontend performs direct redirect to `traefik_url`

**No additional "fwd" endpoint needed** — the URL is already included in the application response.

---

## 5. Existing Favorites Concept

**Status**: PARTIAL IMPLEMENTATION (incomplete)

**What exists in frontend**:
- `maya_dashboard/src/features/tools/data/toolsData.js` — Mock data includes `is_favorite` boolean on each tool (lines 7, 18, 29, 40, 51, 62, 73, 84, 95, 110, 118)
- `maya_dashboard/src/features/tools/api/toolMapper.js` line 21 — Mapper expects `is_favorite: Boolean(tool.is_favorite)` from API response
- i18n strings for "favorites" exist in translation files

**What is MISSING in backend**:
- **No `is_favorite` field** in Application model
- **No database migration** to add `is_favorite` column to applications table
- **No ApplicationResource serialization** of `is_favorite` (ApplicationResource.php does not include it)
- **No API endpoint** to toggle/save favorites (GET only, no POST/PUT/DELETE for favorites)
- **No persistence layer** — frontend mapper expects it but backend doesn't provide it

**Database gap**: No dedicated user_favorite_applications junction table or is_favorite column on user_applications table.

---

## 6. User Profile Endpoints

**Status**: INCOMPLETE

**Existing endpoints**:
- `GET /api/v1/users/{userId}` (admin only, Bearer token) — Returns user details via `UserController::show()` (line 45-52)
- `GET /api/v1/users/{userId}/roles` (admin only) — Returns user's assigned roles
- `GET /api/v1/users/{userId}/permissions/{appSlug}` (admin only) — Returns user permissions for specific app (line 70)

**Missing endpoints**:
- **No "me" or "/current" endpoint** to return authenticated user's own profile
- No endpoint that returns current user + accessible applications combined
- No endpoint returning user preferences (favorites, language, etc.)

**Problem**: Maya_dashboard must call separate endpoints for user data and app list; no single endpoint provides current user profile with assigned apps.

---

## 7. Authentication Flow

**Flow**:
1. **SPA Admin Panel** (React in maya_authorization):
   - User logs in via Keycloak realm `maya`
   - Keycloak issues JWT bearer token
   - Token sent in `Authorization: Bearer <token>` header
   - Middleware `auth.keycloak` validates JWT and extracts claims
   - File: `maya_authorization/backend/app/Http/Middleware/VerifyKeycloakToken.php`

2. **External Apps** (maya_dashboard):
   - Each app has a Keycloak client ID + secret
   - Apps authenticate with Keycloak separately (OIDC flow)
   - Apps query maya_authorization using X-App-Key header (machine-to-machine)
   - Middleware `verify.app.key` validates API key hash
   - File: `maya_authorization/backend/app/Http/Middleware/VerifyAppApiKey.php`

3. **User + App Token Validation**:
   - Keycloak token is validated via JWKS endpoint
   - Claims extracted: `sub` (user ID), `preferred_username`, `roles`, etc.
   - Maya_authorization cross-references user ID with PostgreSQL user table (via postgres_fdw from Keycloak)

---

## 8. API Client in maya_dashboard

**Primary API Service**: `/home/ggarrido/development/Desarrollo CEEDCV/maya_dashboard/src/features/tools/api/toolsApi.js`

**Configuration**:
- Line 1-5: Imports and setup
- Line 25-32: Environment variables — `VITE_API_URL`, `VITE_APP_KEY`
- Line 34: Calls `GET /api/v1/auth/user/{userId}/apps`
- Line 45: Adds `X-App-Key` header with VITE_APP_KEY value
- Returns Tools array mapped via `toolMapper.js`

**Mapper File**: `/home/ggarrido/development/Desarrollo CEEDCV/maya_dashboard/src/features/tools/api/toolMapper.js`

**Key transformations**:
- Line 21: `isFavorite: Boolean(tool.is_favorite)` — **EXPECTS is_favorite from API but currently undefined**
- Line 22: `documentationUrl: tool.traefik_url || tool.documentation_url` — Maps traefik_url as the forward URL
- Line 23: `lastUsedAt: validateIsoDate(tool.last_used_at || tool.updated_at)` — Uses updated_at as fallback

**Authentication**: Keycloak auth configured in `/home/ggarrido/development/Desarrollo CEEDCV/maya_dashboard/src/app/authService.js` — uses @maya/shared-auth-react with realm `maya`.

---

## Summary: What Exists vs. What Needs Building

| Feature | Status | Details |
|---------|--------|---------|
| **Applications CRUD** | Complete | Model, API endpoints, repository layer all exist |
| **List accessible apps** | Complete | GET /api/v1/auth/user/{userId}/apps returns ApplicationResource |
| **Application forward URL** | Complete | traefik_url field provides the URL (no "fwd" endpoint needed) |
| **Favorites persistence** | Missing | No DB column, no ApplicationResource field, no endpoint |
| **User "me" endpoint** | Missing | No endpoint to get current user profile |
| **User profile with apps** | Missing | No combined endpoint (requires separate calls) |
| **Permission checks** | Complete | POST /api/v1/auth/check endpoint exists |
| **Token validation** | Complete | POST /api/v1/auth/token/validate endpoint exists |

---

## Next Steps for Development

### To Build Favorites Feature:
1. Create migration to add `is_favorite` column to applications or user_favorite_applications junction table
2. Add `is_favorite` to Application model
3. Update ApplicationResource to serialize is_favorite
4. Create endpoint POST/DELETE `/api/v1/applications/{id}/favorite` to toggle favorite status
5. Update maya_dashboard mapper (already expects the field)

### To Build "Me" Endpoint:
1. Create UserController::me() endpoint (Bearer token auth)
2. Return current user + accessible applications combined
3. Update maya_dashboard to use single endpoint instead of two separate calls

### Current Working Integration:
- Maya_dashboard successfully calls `GET /api/v1/auth/user/{userId}/apps` with X-App-Key
- Applications are returned with traefik_url for direct navigation
- Only missing piece is is_favorite persistence
