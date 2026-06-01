import { useCallback, useState } from 'react';
import DOMPurify from 'dompurify';
import { Alert, Button, Card } from '@ceedcv-maya/shared-ui-react';
import { MayaEditor } from '@ceedcv-maya/shared-editor-react';
import { useTranslation } from 'react-i18next';
import {
  createComment,
  deleteComment,
  fetchComments,
  updateComment,
  type CommentableKind,
} from '../../api/comments';
import type { Comment } from '../../types/logs';
import { useUserProfile } from '../../features/user-profile';
import { LOGS_PERMISSIONS } from '../../permissions';
import { ConfirmDialog } from '@ceedcv-maya/shared-ui-react';
import { createDataHook, createMutationHook } from '@ceedcv-maya/shared-auth-react';

type CommentThreadProps = {
  commentableType: CommentableKind;
  commentableId: number;
};

const useCommentsQuery = createDataHook<
  { type: CommentableKind; id: number },
  Comment[]
>({
  queryKey: ({ type, id }) => ['comments', type, id],
  fetcher: ({ type, id }) => fetchComments(type, id),
  defaultOptions: { staleTime: 0 },
});

type CreateVars = { type: CommentableKind; id: number; content: string };
const useCreateComment = createMutationHook<CreateVars, Comment>({
  mutationFn: ({ type, id, content }) => createComment(type, id, { content }),
  invalidates: ({ type, id }) => [['comments', type, id]],
});

type UpdateVars = { type: CommentableKind; id: number; commentId: number; content: string };
const useUpdateComment = createMutationHook<UpdateVars, Comment>({
  mutationFn: ({ commentId, content }) => updateComment(commentId, { content }),
  invalidates: ({ type, id }) => [['comments', type, id]],
});

type DeleteVars = { type: CommentableKind; id: number; commentId: number };
const useDeleteComment = createMutationHook<DeleteVars, void>({
  mutationFn: ({ commentId }) => deleteComment(commentId),
  invalidates: ({ type, id }) => [['comments', type, id]],
});

function formatTimestamp(value: string | null): string {
  if (!value) return '';
  const d = new Date(value);
  if (Number.isNaN(d.getTime())) return '';
  const pad = (n: number) => String(n).padStart(2, '0');
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())} ${pad(d.getHours())}:${pad(d.getMinutes())}`;
}

/**
 * Effective text length of an HTML fragment — counts visible characters
 * only (strips tags + decodes basic entities). Used for the min-length
 * check on submit, since MayaEditor emits `<p></p>` for an empty doc.
 */
function htmlTextLength(html: string): number {
  const text = html
    .replace(/<[^>]+>/g, '')
    .replace(/&nbsp;/g, ' ')
    .replace(/&lt;/g, '<')
    .replace(/&gt;/g, '>')
    .replace(/&amp;/g, '&')
    .trim();
  return text.length;
}

export function CommentThread({ commentableType, commentableId }: CommentThreadProps) {
  const { t } = useTranslation('comments');
  const { hasPermission } = useUserProfile();

  const canCreate =
    commentableType === 'archived-logs'
      ? hasPermission(LOGS_PERMISSIONS.archivedLogsCommentCreate)
      : commentableType === 'error-codes'
        ? hasPermission(LOGS_PERMISSIONS.errorCodeCommentCreate)
        : false;

  const commentsQuery = useCommentsQuery({ type: commentableType, id: commentableId });
  const createMutation = useCreateComment();
  const updateMutation = useUpdateComment();
  const deleteMutation = useDeleteComment();

  const [newContent, setNewContent] = useState('');
  const [createError, setCreateError] = useState<string | null>(null);

  const [editingId, setEditingId] = useState<number | null>(null);
  const [editingContent, setEditingContent] = useState('');
  const [editingError, setEditingError] = useState<string | null>(null);

  const [deleteTargetId, setDeleteTargetId] = useState<number | null>(null);
  const [deleteError, setDeleteError] = useState<string | null>(null);

  const creating = createMutation.isPending;
  const editingBusy = updateMutation.isPending;
  const deleteBusy = deleteMutation.isPending;

  const comments = commentsQuery.data ?? [];
  const loadErrorMessage =
    commentsQuery.isError && commentsQuery.error
      ? commentsQuery.error instanceof Error
        ? commentsQuery.error.message
        : String(commentsQuery.error)
      : null;

  const onCreate = useCallback(() => {
    const content = newContent.trim();
    if (htmlTextLength(content) < 3) {
      setCreateError(t('minLength'));
      return;
    }
    setCreateError(null);
    createMutation.mutate(
      { type: commentableType, id: commentableId, content },
      {
        onSuccess: () => setNewContent(''),
        onError: (e) => setCreateError(e instanceof Error ? e.message : String(e)),
      },
    );
  }, [commentableType, commentableId, newContent, t, createMutation]);

  const onStartEdit = useCallback((comment: Comment) => {
    setEditingId(comment.id);
    setEditingContent(comment.content);
    setEditingError(null);
  }, []);

  const onCancelEdit = useCallback(() => {
    setEditingId(null);
    setEditingContent('');
    setEditingError(null);
  }, []);

  const onUpdate = useCallback(() => {
    if (editingId == null) return;
    const content = editingContent.trim();
    if (htmlTextLength(content) < 3) {
      setEditingError(t('minLength'));
      return;
    }
    setEditingError(null);
    updateMutation.mutate(
      {
        type: commentableType,
        id: commentableId,
        commentId: editingId,
        content,
      },
      {
        onSuccess: () => {
          setEditingId(null);
          setEditingContent('');
        },
        onError: (e) => setEditingError(e instanceof Error ? e.message : String(e)),
      },
    );
  }, [commentableType, commentableId, editingId, editingContent, t, updateMutation]);

  const onConfirmDelete = useCallback(() => {
    if (deleteTargetId == null) return;
    setDeleteError(null);
    deleteMutation.mutate(
      { type: commentableType, id: commentableId, commentId: deleteTargetId },
      {
        onSuccess: () => setDeleteTargetId(null),
        onError: (e) => setDeleteError(e instanceof Error ? e.message : String(e)),
      },
    );
  }, [commentableType, commentableId, deleteTargetId, deleteMutation]);

  return (
    <div className="space-y-4">
      {canCreate && (
      <Card padding="md" radius="xl" className="space-y-3">
        <label
          htmlFor={`new-comment-${commentableType}-${commentableId}`}
          className="block text-sm font-medium text-text-secondary dark:text-text-dark-secondary"
        >
          {t('newComment')}
        </label>
        <div id={`new-comment-${commentableType}-${commentableId}`}>
          <MayaEditor
            mode="lite"
            initialContent={newContent}
            editable={!creating}
            onChange={setNewContent}
            placeholder={t('placeholder')}
          />
        </div>
        {createError && (
          <p
            role="alert"
            className="rounded-lg border border-danger-light bg-danger-light/30 px-3 py-2 text-sm text-danger-dark dark:border-danger/40 dark:bg-danger/10 dark:text-danger"
          >
            {createError}
          </p>
        )}
        <div className="flex justify-end">
          <Button variant="primary" size="sm" onClick={onCreate} disabled={creating} loading={creating}>
            {creating ? t('busy') : t('actions.save')}
          </Button>
        </div>
      </Card>
      )}

      {loadErrorMessage && (
        <Alert tone="danger" className="mt-4">{t('listLoadError', { message: loadErrorMessage })}
        </Alert>
      )}

      {deleteError && (
        <Alert tone="danger" className="mt-4">{deleteError}</Alert>
      )}

      <div className="space-y-3">
        {commentsQuery.isLoading && (
          <Card padding="lg" radius="xl" className="border-dashed text-center text-sm text-text-secondary dark:text-text-dark-secondary">
            {t('status.loading')}
          </Card>
        )}

        {!commentsQuery.isLoading && comments.length === 0 && (
          <Card padding="lg" radius="xl" className="border-dashed text-center text-sm text-text-secondary dark:text-text-dark-secondary">
            {t('empty')}
          </Card>
        )}

        {comments.map((comment) => {
          const isEditing = editingId === comment.id;
          return (
            <Card key={comment.id} padding="md" radius="xl" asChild>
              <article>
              <div className="flex items-start justify-between gap-4">
                <div>
                  <p className="text-sm font-semibold text-text-primary dark:text-text-dark-primary">
                    {comment.user?.name ?? t('unknownUser')}
                  </p>
                  <p className="text-xs text-text-secondary dark:text-text-dark-secondary">
                    {formatTimestamp(comment.created_at)}
                  </p>
                </div>
                {!isEditing && (comment.can_edit || comment.can_delete) && (
                  <div className="flex gap-2">
                    {comment.can_edit && (
                      <Button variant="ghost" size="xs" onClick={() => onStartEdit(comment)}>
                        {t('actions.edit')}
                      </Button>
                    )}
                    {comment.can_delete && (
                      <Button variant="danger" size="xs" onClick={() => setDeleteTargetId(comment.id)}>
                        {t('actions.delete')}
                      </Button>
                    )}
                  </div>
                )}
              </div>

              {isEditing ? (
                <div className="mt-3 space-y-3">
                  <MayaEditor
                    mode="lite"
                    initialContent={editingContent}
                    editable={!editingBusy}
                    onChange={setEditingContent}
                  />
                  {editingError && (
                    <p
                      role="alert"
                      className="rounded-lg border border-danger-light bg-danger-light/30 px-3 py-2 text-sm text-danger-dark dark:border-danger/40 dark:bg-danger/10 dark:text-danger"
                    >
                      {editingError}
                    </p>
                  )}
                  <div className="flex gap-2">
                    <Button
                      variant="primary"
                      size="sm"
                      onClick={onUpdate}
                      disabled={editingBusy}
                      loading={editingBusy}
                    >
                      {editingBusy ? t('busy') : t('actions.refresh')}
                    </Button>
                    <Button variant="secondary" size="sm" onClick={onCancelEdit} disabled={editingBusy}>
                      {t('actions.cancel')}
                    </Button>
                  </div>
                </div>
              ) : (
                <div
                  className="rte-content mt-3 text-sm text-text-primary dark:text-text-dark-primary"
                  dangerouslySetInnerHTML={{ __html: DOMPurify.sanitize(comment.content) }}
                />
              )}
              </article>
            </Card>
          );
        })}
      </div>

      <ConfirmDialog
        open={deleteTargetId !== null}
        title={t('confirmDelete.title')}
        description={t('confirmDelete.message')}
        confirmLabel={t('actions.delete')}
        variant="danger"
        loading={deleteBusy}
        onConfirm={onConfirmDelete}
        onCancel={() => !deleteBusy && setDeleteTargetId(null)}
      />
    </div>
  );
}
