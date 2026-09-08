import { createRouter, createWebHistory } from 'vue-router'
import { getToken } from '../services/http'

export const router = createRouter({
  history: createWebHistory(),
  routes: [
    {
      path: '/login',
      name: 'login',
      component: () => import('../views/LoginView.vue'),
      meta: { public: true },
    },
    {
      path: '/',
      name: 'workspaces',
      component: () => import('../views/WorkspacesView.vue'),
    },
    {
      path: '/w/:ws',
      name: 'sites',
      component: () => import('../views/SitesView.vue'),
      props: true,
    },
    {
      path: '/w/:ws/s/:site',
      name: 'pages',
      component: () => import('../views/PagesView.vue'),
      props: true,
    },
    {
      path: '/w/:ws/s/:site/p/:page',
      name: 'builder',
      component: () => import('../views/BuilderView.vue'),
      props: true,
    },
  ],
})

router.beforeEach((to) => {
  if (to.meta.public) {
    return true
  }
  if (!getToken()) {
    return { name: 'login' }
  }
  return true
})
