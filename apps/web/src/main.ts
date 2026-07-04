import { createApp } from 'vue'
import './style.css'
import App from './App.vue'
import router from './router'
import { auth } from './stores/auth'

// Resolve "am I already logged in?" (from an existing session cookie) before
// mounting, so the router guard can decide synchronously and we don't flash the
// login page for an authenticated user.
auth.loadUser().finally(() => {
  createApp(App).use(router).mount('#app')
})
