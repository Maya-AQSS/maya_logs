import { z } from 'zod'

/**
 * Form-level schema for `ArchivedLogDetailPage` edit form.
 *
 * Backend FormRequest:
 *   maya_logs/backend/app/Http/Requests/Api/UpdateArchivedLogRequest.php
 */
export const archivedLogEditSchema = z.object({
  description: z.string().max(5000).optional().default(''),
  // Allow empty string; if filled, must be a valid URL.
  url_tutorial: z
    .string()
    .optional()
    .default('')
    .refine((v) => v === '' || /^https?:\/\/.+/i.test(v), 'URL inválida'),
})

export type ArchivedLogEditInput = z.infer<typeof archivedLogEditSchema>

export const emptyArchivedLogEdit: ArchivedLogEditInput = {
  description: '',
  url_tutorial: '',
}
