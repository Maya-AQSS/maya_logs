import type { ApiEnvelope } from '@maya/shared-auth-react';
import type { Comment } from '../types/logs';
import { apiFetchJson, apiGetJson } from './http';

export type { Comment } from '../types/logs';

export type CommentableKind = 'archived-logs' | 'error-codes';

export type CommentPayload = {
  content: string;
};

/** GET /api/v1/{kind}/{id}/comments — todos los comentarios del recurso, orden `latest`. */
export async function fetchComments(kind: CommentableKind, resourceId: number): Promise<Comment[]> {
  const body = await apiGetJson<ApiEnvelope<Comment[]>>(`${kind}/${resourceId}/comments`);
  return body.data;
}

/** POST /api/v1/{kind}/{id}/comments — crea un comentario (HTML saneado por Purifier). */
export async function createComment(
  kind: CommentableKind,
  resourceId: number,
  payload: CommentPayload,
): Promise<Comment> {
  const body = await apiFetchJson<ApiEnvelope<Comment>>(`${kind}/${resourceId}/comments`, {
    method: 'POST',
    body: payload,
  });
  return body.data;
}

/** PATCH /api/v1/comments/{id}. */
export async function updateComment(id: number, payload: CommentPayload): Promise<Comment> {
  const body = await apiFetchJson<ApiEnvelope<Comment>>(`comments/${id}`, {
    method: 'PATCH',
    body: payload,
  });
  return body.data;
}

/** DELETE /api/v1/comments/{id}. */
export async function deleteComment(id: number): Promise<void> {
  await apiFetchJson<void>(`comments/${id}`, { method: 'DELETE' });
}
