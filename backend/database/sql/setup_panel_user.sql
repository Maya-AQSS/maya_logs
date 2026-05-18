-- ============================================================================
-- ⚠️  ADVERTENCIA — CONTRASEÑA PLACEHOLDER
-- ============================================================================
-- El literal 'your_secure_password' es SOLO un ejemplo. NO lo uses en
-- producción ni en entornos compartidos. Si ejecutas este script sin
-- sustituir la contraseña, el usuario de BD quedará con credenciales triviales.
--
-- Antes de ejecutar:
--   1. Genera una contraseña fuerte (p. ej. gestor de secretos, o en shell:
--      openssl rand -base64 32).
--   2. Sustituye 'your_secure_password' en la línea CREATE USER por ese valor.
--   3. Replica la misma contraseña en .env como DB_PANEL_PASSWORD (nunca en git).
--
-- Ejecutar como superusuario (sail/postgres).
-- ============================================================================

CREATE USER panel_user WITH PASSWORD 'your_secure_password';

-- logs: solo lectura (gestionada por n8n)
GRANT SELECT ON logs TO panel_user;

-- applications: solo lectura
GRANT SELECT ON applications TO panel_user;

-- archived_logs: lectura/escritura, sin DELETE por diseño
GRANT SELECT, INSERT, UPDATE ON archived_logs TO panel_user;
REVOKE DELETE, TRUNCATE ON archived_logs FROM panel_user;

-- comments: lectura/escritura + DELETE en cascada al borrar un error code
-- (ErrorCode::booted() elimina comentarios antes del DELETE en error_codes)
GRANT SELECT, INSERT, UPDATE, DELETE ON comments TO panel_user;

-- error_codes: CRUD completo (ErrorCodeController::destroy)
GRANT SELECT, INSERT, UPDATE, DELETE ON error_codes TO panel_user;

-- users: solo lectura (vista FDW sobre Odoo — no se puede insertar)
GRANT SELECT ON users TO panel_user;

-- Sequences necesarias para INSERT
GRANT USAGE, SELECT ON SEQUENCE archived_logs_id_seq TO panel_user;
GRANT USAGE, SELECT ON SEQUENCE comments_id_seq TO panel_user;
GRANT USAGE, SELECT ON SEQUENCE error_codes_id_seq TO panel_user;


-- NOTA: el borrado de archived_logs usa soft delete (UPDATE deleted_at); no requiere
-- GRANT DELETE aquí. Solo haría falta si el panel pasara a borrado físico (p. ej. forceDelete):
-- GRANT DELETE ON archived_logs TO panel_user;
