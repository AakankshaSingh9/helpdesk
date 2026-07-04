import { createRouter, createWebHistory, type RouteRecordRaw } from 'vue-router'
import { auth } from '../stores/auth'
import LoginView from '../views/LoginView.vue'
import HomeView from '../views/HomeView.vue'

const routes: RouteRecordRaw[] = [
  { path: '/', redirect: '/home' },
  { path: '/login', name: 'login', component: LoginView, meta: { guestOnly: true } },
  { path: '/home', name: 'home', component: HomeView, meta: { requiresAuth: true } },
  { path: '/:pathMatch(.*)*', redirect: '/home' },
]

const router = createRouter({
  history: createWebHistory(),
  routes,
})

// Auth guard. `auth.state.ready` is guaranteed true here because main.ts loads
// the user before mounting the app.
router.beforeEach((to) => {
  if (to.meta.requiresAuth && !auth.isAuthenticated) {
    return { name: 'login', query: { redirect: to.fullPath } }
  }
  if (to.meta.guestOnly && auth.isAuthenticated) {
    return { name: 'home' }
  }
  return true
})

export default router
