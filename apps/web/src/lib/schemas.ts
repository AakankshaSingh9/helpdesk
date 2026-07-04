// Zod schemas for the app's forms. Keeping them in one place lets views and the
// useZodForm composable share a single source of truth for validation + types.

import { z } from 'zod'

export const loginSchema = z.object({
  email: z.string().trim().min(1, 'Email is required').pipe(z.email('Enter a valid email address')),
  password: z.string().min(1, 'Password is required'),
})

export type LoginForm = z.infer<typeof loginSchema>
