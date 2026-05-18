import { useCallback, useState } from 'react';
import DOMPurify from 'dompurify';
import { Alert, Button } from '@maya/shared-ui-react';
import { useTranslation } from 'react-i18next';
import {
  createComment,
  deleteComment,
  fetchComments,
  updateComment,
  type CommentableKind,
} from '../../api/comments';
import type { Comment } from '../../types/logs';
import { ConfirmDialog } from '@maya/shared-ui-react';
import { createDataHook, createMutationHook } from '@maya/shared-auth-react';

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

function plainTextToHtml(text: string): string {
  const escaped = text
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;');
  const paragraphs = escaped
    .split(/\n{2,}/)
    .map((p) => `<p>${p.replace(/\n/g, '<br>')}</p>`)
    .join('');
  return paragraphs;
}

export function CommentThread({ commentableType, commentableId }: CommentThreadProps) {
  const { t } = useTranslation('comments');

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
    if (content.length < 3) {
      setCreateError(t('minLength'));
      return;
    }
    setCreateError(null);
    createMutation.mutate(
      { type: commentableType, id: commentableId, content: plainTextToHtml(content) },
      {
        onSuccess: () => setNewContent(''),
        onError: (e) => setCreateError(e instanceof Error ? e.message : String(e)),
      },
    );
  }, [commentableType, commentableId, newContent, t, createMutation]);

  const onStartEdit = useCallback((comment: Comment) => {
    setEditingId(comment.id);
    const stripped = comment.content
      .replace(/<br\s*\/?\s*>/gi, '\n')
      .replace(/<\/p>\s*<p[^>]*>/gi, '\n\n')
      .replace(/<\/?p[^>]*>/gi, '')
      .replace(/<[^>]+>/g, '')
      .replace(/&lt;/g, '<')
      .replace(/&gt;/g, '>')
      .replace(/&amp;/g, '&')
      .trim();
    setEditingContent(stripped);
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
    if (content.length < 3) {
      setEditingError(t('minLength'));
      return;
    }
    setEditingError(null);
    updateMutation.mutate(
      {
        type: commentableType,
        id: commentableId,
        commentId: editingId,
        content: plainTextToHtml(content),
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
      <div className="space-y-3 rounded-xl border border-ui-border bg-ui-card p-4 shadow-sm dark:border-ui-dark-border dark:bg-ui-dark-card">
        <label
          htmlFor={`new-comment-${commentableType}-${commentableId}`}
          className="block text-sm font-medium text-text-secondary dark:text-text-dark-secondary"
        >
          {t('newComment')}
        </label>
        <textarea
          id={`new-comment-${commentableType}-${commentableId}`}
          value={newContent}
          onChange={(e) => setNewContent(e.target.value)}
          disabled={creating}
          rows={4}
          placeholder={t('placeholder')}
          className="w-full rounded-lg border border-ui-border bg-ui-body px-3 py-2 text-sm text-text-primary outline-none focus:border-odoo-purple dark:border-ui-dark-border dark:bg-ui-dark-bg dark:text-text-dark-primary dark:focus:border-odoo-dark-purple disabled:cursor-not-allowed disabled:opacity-60"
        />
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
            {creating ? t('busy') : t('save')}
          </Button>
        </div>
      </div>

      {loadErrorMessage && (
        <Alert tone="danger" className="mt-4">{t('listLoadError', { message: loadErrorMessage })}
        </Alert>
      )}

      {deleteError && (
        <Alert tone="danger" className="mt-4">{deleteError}</Alert>
      )}

      <div className="space-y-3">
        {commentsQuery.isLoading && (
          <p className="rounded-xl border border-dashed border-ui-border bg-ui-card px-4 py-6 text-center text-sm text-text-secondary dark:border-ui-dark-border dark:bg-ui-dark-card dark:text-text-dark-secondary">
            {t('loading')}
          </p>
        )}

        {!commentsQuery.isLoading && comments.length === 0 && (
          <p className="rounded-xl border border-dashed border-ui-border bg-ui-card px-4 py-6 text-center text-sm text-text-secondary dark:border-ui-dark-border dark:bg-ui-dark-card dark:text-text-dark-secondary">
            {t('empty')}
          </p>
        )}

        {comments.map((comment) => {
          const isEditing = editingId === comment.id;
          return (
            <article
              key={comment.id}
              className="rounded-xl border border-ui-border bg-ui-card p-4 shadow-sm dark:border-ui-dark-border dark:bg-ui-dark-card"
            >
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
                        {t('edit')}
                      </Button>
                    )}
                    {comment.can_delete && (
                      <Button variant="danger" size="xs" onClick={() => setDeleteTargetId(comment.id)}>
                        {t('delete')}
                      </Button>
                    )}
                  </div>
                )}
              </div>

              {isEditing ? (
                <div className="mt-3 space-y-3">
                  <textarea
                    value={editingContent}
                    onChange={(e) => setEditingContent(e.target.value)}
                    disabled={editingBusy}
                    rows={4}
                    className="w-full rounded-lg border border-ui-border bg-ui-body px-3 py-2 text-sm text-text-primary outline-none focus:border-odoo-purple dark:border-ui-dark-border dark:bg-ui-dark-bg dark:text-text-dark-primary dark:focus:border-odoo-dark-purple disabled:cursor-not-allowed disabled:opacity-60"
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
                      {editingBusy ? t('busy') : t('update')}
                    </Button>
                    <Button variant="secondary" size="sm" onClick={onCancelEdit} disabled={editingBusy}>
                      {t('cancel')}
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
          );
        })}
      </div>

      <ConfirmDialog
        open={deleteTargetId !== null}
        title={t('confirmDelete.title')}
        description={t('confirmDelete.message')}
        confirmLabel={t('confirmDelete.confirmLabel')}
        variant="danger"
        loading={deleteBusy}
        onConfirm={onConfirmDelete}
        onCancel={() => !deleteBusy && setDeleteTargetId(null)}
      />
    </div>
  );
}
