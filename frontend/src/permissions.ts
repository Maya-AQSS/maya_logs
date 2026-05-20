/** Slugs de permisos de TraCEED (maya-logs), alineados con maya_authorization. */
export const LOGS_PERMISSIONS = {
  login: 'logs.login',
  index: 'logs.index',
  show: 'logs.show',
  update: 'logs.update',
  dashboardUpdate: 'logs.dashboard.update',
  archivedLogsIndex: 'archived-logs.index',
  archivedLogsShow: 'archived-logs.show',
  archivedLogsCreate: 'archived-logs.create',
  archivedLogsUpdate: 'archived-logs.update',
  archivedLogsDelete: 'archived-logs.delete',
  archivedLogsCommentCreate: 'archived-logs.comment.create',
  archivedLogsCommentDelete: 'archived-logs.comment.delete',
} as const;
