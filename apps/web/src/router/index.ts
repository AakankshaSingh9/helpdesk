import { createRouter, createWebHistory, type RouteRecordRaw } from 'vue-router'
import { auth } from '../stores/auth'
import LoginView from '../views/LoginView.vue'
import HomeView from '../views/HomeView.vue'
import UsersView from '../views/UsersView.vue'
import TicketsView from '../views/TicketsView.vue'
import TicketView from '../views/TicketView.vue'
import DashboardView from '../views/DashboardView.vue'

const routes: RouteRecordRaw[] = [
  { path: '/', redirect: '/home' },
  { path: '/login', name: 'login', component: LoginView, meta: { guestOnly: true } },
  { path: '/home', name: 'home', component: HomeView, meta: { requiresAuth: true } },
  {
    path: '/users',
    name: 'users',
    component: UsersView,
    meta: { requiresAdmin: true },
  },
  { path: '/dashboard', name: 'dashboard', component: DashboardView, meta: { requiresAuth: true } },
  { path: '/tickets', name: 'tickets', component: TicketsView, meta: { requiresAuth: true } },
  {
    path: '/tickets/:id',
    name: 'ticket',
    component: TicketView,
    meta: { requiresAuth: true },
    props: true,
  },
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
  if (to.meta.requiresAdmin && !auth.isAdmin) {
    return { name: 'home' }
  }
  if (to.meta.guestOnly && auth.isAuthenticated) {
    return { name: 'dashboard' }
  }
  return true
})

export default router
