<?php

namespace App\Observers;

use App\Observers\Concerns\NormalizesAuditTemporalPayload;
use Illuminate\Database\Eloquent\Model;
use Maya\Messaging\Publishers\AuditPublisher;

/**
 * Plantilla CRUD → maya.audit para modelos del panel.
 *
 * Cada observer concreto define tipo de entidad, claves temporales y actor;
 * la publicación es compartida y automática.
 */
abstract class AbstractAuditableModelObserver
{
    use NormalizesAuditTemporalPayload;

    /**
     * Propiedad nativa de Laravel (disponible desde v8.0).
     * Asegura que los eventos (created, updated, deleted) solo se disparen
     * en este observer una vez que la transacción de base de datos se haya confirmado (commit).
     * Evita publicaciones fantasma si ocurre un rollback.
     */
    public bool $afterCommit = true;

    public function __construct(
        protected readonly AuditPublisher $publisher,
    ) {}

    /**
     * Define el tipo de entidad para el enrutamiento del mensaje de auditoría (ej. 'error_code').
     */
    abstract protected function auditEntityType(): string;

    /**
     * Define qué campos del modelo representan marcas temporales (timestamps)
     * que deben ser normalizadas a formato ISO-8601.
     *
     * @return list<string>
     */
    abstract protected function auditTemporalKeys(): array;

    /**
     * Resuelve el identificador del usuario (actor) responsable de la acción.
     */
    abstract protected function resolveAuditUserId(Model $model): string;

    /**
     * Procesa y publica el evento de auditoría tras la creación de un nuevo registro.
     * Toma todos los atributos actuales del modelo como 'nuevo valor'.
     */
    protected function auditAfterCreate(string $action, Model $model): void
    {
        $this->publishAudit(
            $action,
            $model,
            null,
            $model->getAttributes(),
        );
    }

    /**
     * Procesa y publica el evento de auditoría tras la modificación de un registro.
     * Calcula las diferencias entre el estado original y el nuevo.
     */
    protected function auditAfterUpdate(string $action, Model $model): void
    {
        [$previous, $new] = $this->auditUpdateDiff($model);

        $this->publishAudit(
            $action,
            $model,
            $previous,
            $new,
        );
    }

    /**
     * Procesa y publica el evento de auditoría tras la eliminación de un registro.
     * Toma los atributos previos al borrado como 'valor anterior'.
     */
    protected function auditAfterDelete(string $action, Model $model): void
    {
        $this->publishAudit(
            $action,
            $model,
            $model->getAttributes(),
            null,
        );
    }

    /**
     * Calcula la diferencia exacta entre el estado anterior y el actual del modelo,
     * devolviendo únicamente los campos que han cambiado.
     * 
     * @return array{0: ?array<string, mixed>, 1: ?array<string, mixed>}
     */
    protected function auditUpdateDiff(Model $model): array
    {
        $previous = array_intersect_key($model->getOriginal(), $model->getChanges());
        $new = $model->getChanges();

        return [
            $previous !== [] ? $previous : null,
            $new !== [] ? $new : null,
        ];
    }

    /**
     * Obtiene el slug identificativo de esta aplicación desde la configuración,
     * para adjuntarlo como origen en el mensaje de auditoría.
     */
    protected function messagingAppSlug(): string
    {
        return (string) config('messaging.app');
    }

    /**
     * Extrae el ID del usuario directamente desde el token JWT inyectado en la petición HTTP.
     * Ideal para arquitecturas sin estado. Retorna un valor de fallback si no hay petición HTTP activa.
     */
    protected function jwtSubjectFromRequest(?string $fallback = 'system'): string
    {
        $jwtUser = request()->attributes->get('jwt_user');
        if (! is_array($jwtUser)) {
            return $fallback ?? 'system';
        }

        $id = $jwtUser['id'] ?? null;

        if (is_string($id) && $id !== '') {
            return $id;
        }

        return $fallback ?? 'system';
    }

    /**
     * Ensambla el payload final y lo envía al AuditPublisher de la infraestructura (maya_infra).
     * Aplica la normalización temporal ISO-8601 a las fechas antes de enviarlas.
     *
     * @param  array<string, mixed>|null  $previousValue
     * @param  array<string, mixed>|null  $newValue
     */
    protected function publishAudit(
        string $action,
        Model $model,
        ?array $previousValue,
        ?array $newValue,
    ): void {
        $this->publisher->publish(
            applicationSlug: $this->messagingAppSlug(),
            entityType: $this->auditEntityType(),
            entityId: (string) $model->getKey(),
            action: $action,
            userId: $this->resolveAuditUserId($model),
            previousValue: $this->normalizeAuditTemporalPayload($previousValue, $this->auditTemporalKeys()),
            newValue: $this->normalizeAuditTemporalPayload($newValue, $this->auditTemporalKeys()),
        );
    }
}
