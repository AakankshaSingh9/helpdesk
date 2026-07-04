// A tiny form helper backed by a Zod schema. Keeps reactive field values, tracks
// which fields have been touched, and exposes per-field error messages so views
// stay declarative. Validation runs on blur (once touched) and on submit.

import { reactive } from 'vue'
import type { ZodType } from 'zod'

export function useZodForm<T extends Record<string, unknown>>(schema: ZodType<T>, initial: T) {
  const values = reactive({ ...initial }) as T
  const errors = reactive({}) as Partial<Record<keyof T, string>>
  const touched = reactive({}) as Partial<Record<keyof T, boolean>>

  /** Validate everything. Returns parsed data on success, or null on failure. */
  function validate(): T | null {
    const result = schema.safeParse({ ...values })

    for (const key of Object.keys(values) as (keyof T)[]) delete errors[key]

    if (result.success) return result.data

    for (const issue of result.error.issues) {
      const key = issue.path[0] as keyof T | undefined
      // Keep the first message per field — that's what the user acts on.
      if (key !== undefined && errors[key] === undefined) errors[key] = issue.message
    }
    return null
  }

  /** Re-validate a single field, but only surface its error once touched. */
  function validateField(field: keyof T): void {
    touched[field] = true
    const result = schema.safeParse({ ...values })
    if (result.success) {
      delete errors[field]
      return
    }
    const issue = result.error.issues.find((i) => i.path[0] === field)
    if (issue) errors[field] = issue.message
    else delete errors[field]
  }

  return { values, errors, touched, validate, validateField }
}
