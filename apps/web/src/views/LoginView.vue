<script setup lang="ts">
import { ref } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { Lock } from 'lucide-vue-next'
import { auth } from '../stores/auth'
import { ApiError } from '../lib/api'
import { loginSchema } from '../lib/schemas'
import { useZodForm } from '../composables/useZodForm'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import {
  Card,
  CardHeader,
  CardTitle,
  CardDescription,
  CardContent,
} from '@/components/ui/card'

const router = useRouter()
const route = useRoute()

const { values, errors, touched, validate, validateField } = useZodForm(loginSchema, {
  email: '',
  password: '',
})

// Once a field has been blurred (touched), keep its error in sync as the user
// types — so a shown error clears the moment the value becomes valid.
function onInput(field: 'email' | 'password') {
  if (touched[field]) validateField(field)
}

const formError = ref<string | null>(null)
const submitting = ref(false)

async function onSubmit() {
  formError.value = null
  const data = validate()
  if (!data) return

  submitting.value = true
  try {
    await auth.login(data.email, data.password)
    const redirect = (route.query.redirect as string) || '/home'
    await router.replace(redirect)
  } catch (e) {
    if (e instanceof ApiError && e.status === 422) {
      // Fortify returns 422 with field errors on bad credentials.
      formError.value = e.errors?.email?.[0] ?? 'These credentials do not match our records.'
    } else if (e instanceof ApiError && e.status === 429) {
      formError.value = 'Too many attempts. Please wait a moment and try again.'
    } else {
      formError.value = 'Something went wrong. Is the API reachable?'
    }
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <main class="grid min-h-screen place-items-center bg-app p-6">
    <Card class="w-full max-w-sm">
      <CardHeader class="text-center">
        <div
          class="mx-auto mb-2 grid size-11 place-items-center rounded-lg bg-primary/10 text-primary"
          aria-hidden="true"
        >
          <Lock :size="18" :stroke-width="1.5" />
        </div>
        <CardTitle class="text-2xl">Welcome back</CardTitle>
        <CardDescription>Sign in to your AI Helpdesk workspace</CardDescription>
      </CardHeader>

      <CardContent>
        <form class="flex flex-col gap-5" novalidate @submit.prevent="onSubmit">
          <div class="flex flex-col gap-2">
            <Label for="email">Email</Label>
            <Input
              id="email"
              v-model="values.email"
              type="email"
              autocomplete="username"
              placeholder="you@example.com"
              :aria-invalid="!!errors.email"
              @blur="validateField('email')"
              @input="onInput('email')"
            />
            <p v-if="errors.email" class="text-sm text-destructive">{{ errors.email }}</p>
          </div>

          <div class="flex flex-col gap-2">
            <Label for="password">Password</Label>
            <Input
              id="password"
              v-model="values.password"
              type="password"
              autocomplete="current-password"
              placeholder="••••••••"
              :aria-invalid="!!errors.password"
              @blur="validateField('password')"
              @input="onInput('password')"
            />
            <p v-if="errors.password" class="text-sm text-destructive">{{ errors.password }}</p>
          </div>

          <p
            v-if="formError"
            class="rounded-md border border-destructive/20 bg-destructive/10 px-3 py-2.5 text-sm text-destructive"
            role="alert"
          >
            {{ formError }}
          </p>

          <Button type="submit" class="mt-1 w-full" :disabled="submitting">
            {{ submitting ? 'Signing in…' : 'Sign in' }}
          </Button>
        </form>
      </CardContent>
    </Card>
  </main>
</template>
