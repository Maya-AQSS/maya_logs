import { z } from 'zod'

/**
 * Form-level schema mirroring `StoreErrorCodeRequest` /
 * `UpdateErrorCodeRequest`. `application_id` and `line` are strings at form
 * level (HTML select / number input) and the consuming pages coerce
 * to number | null before the mutation.
 *
 * Backend FormRequests:
 *   maya_logs/backend/app/Http/Requests/Api/StoreErrorCodeRequest.php
 *   maya_logs/backend/app/Http/Requests/Api/UpdateErrorCodeRequest.php
 */
export const errorCodeFormSchema = z.object({
  application_id: z.string().min(1, 'Selecciona una aplicación'),
  code: z.string().min(1, 'Requerido').max(50),
  name: z.string().min(1, 'Requerido').max(200),
  file: z.string().max(255).optional().default(''),
  // Empty string allowed; if filled, must be a positive integer.
  line: z
    .string()
    .optional()
    .default('')
    .refine((v) => v === '' || /^\d+$/.test(v), 'Solo números enteros')
    .refine((v) => v === '' || Number(v) >= 1, 'Mínimo 1'),
  description: z.string().max(5000).optional().default(''),
})

export type ErrorCodeFormInput = z.infer<typeof errorCodeFormSchema>

export const emptyErrorCodeForm: ErrorCodeFormInput = {
  application_id: '',
  code: '',
  name: '',
  file: '',
  line: '',
  description: '',
}
