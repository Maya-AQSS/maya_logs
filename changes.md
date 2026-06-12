# changes.md — refactor/unify-cross-app (maya_logs)

Registro de cambios funcionales observables introducidos al adoptar los
paquetes compartidos 0.16.0 de maya_platform (PLAN-UNIFY-CROSS-APP §7).
Los refactors que preservan comportamiento NO se registran aquí.

---

## [UNIFY-LOGS-1] Render JSON uniforme para excepciones no-AuthorizationException

- **Fecha**: 2026-06-12
- **Severidad**: LOW
- **Qué cambió**: las excepciones en rutas `api/*` distintas de
  `AuthorizationException` pasan del render JSON por defecto de Laravel al
  envelope uniforme de `Maya\Http\Exceptions\JsonExceptionRenderer`
  (`{"message": "..."}`, más `{"errors": {...}}` en ValidationException).
  Diferencias observables menores: (1) el mensaje genérico de un 500 en
  producción pasa de `"Server Error"` (Laravel) a `"Internal Server Error"`
  (Symfony statusTexts); (2) los 5xx en producción ya no exponen el mensaje
  crudo de la excepción (antes dependía del handler por defecto). El renderable
  propio de logs para `AuthorizationException` (shape `{error:{code,message}}`)
  se CONSERVA registrándolo ANTES del catch-all: Laravel evalúa los renderables
  en orden de registro y gana la primera respuesta no-null, por lo que el
  "override registrándolo después" indicado en el plan no es ejecutable — el
  orden invertido logra la misma semántica y queda documentado en
  `backend/bootstrap/app.php`.
- **Por qué**: unificación del render de errores API en las 5 apps Maya
  (shared-http-laravel 0.16.0).
- **Endpoint(s) afectado(s)**: todos los `api/*` cuando lanzan excepciones
  distintas de AuthorizationException.
- **Impacto en cliente**: bajo — el frontend ya consumía `{message}` para
  validación/404; solo cambian textos genéricos de 5xx.
- **Decidido por**: agente unify-cross-app (fase logs), según PLAN §7 cambio
  previsto nº 2.
