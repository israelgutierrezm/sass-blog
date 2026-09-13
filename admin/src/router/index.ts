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
    {
      path: '/w/:ws/s/:site/collections',
      name: 'collections',
      component: () => import('../views/CollectionsView.vue'),
      props: true,
    },
    {
      path: '/w/:ws/s/:site/c/:collection/entries',
      name: 'entries',
      component: () => import('../views/EntriesView.vue'),
      props: true,
    },
    {
      path: '/w/:ws/s/:site/c/:collection/entries/new',
      name: 'entry-new',
      component: () => import('../views/EntryEditorView.vue'),
      props: true,
    },
    {
      path: '/w/:ws/s/:site/c/:collection/entries/:entry',
      name: 'entry-edit',
      component: () => import('../views/EntryEditorView.vue'),
      props: true,
    },
    {
      path: '/w/:ws/s/:site/authors',
      name: 'authors',
      component: () => import('../views/AuthorsView.vue'),
      props: true,
    },
    {
      path: '/w/:ws/s/:site/media',
      name: 'media',
      component: () => import('../views/MediaLibraryView.vue'),
      props: true,
    },
    {
      path: '/w/:ws/s/:site/redirects',
      name: 'redirects',
      component: () => import('../views/RedirectsView.vue'),
      props: true,
    },
    {
      path: '/w/:ws/s/:site/menus',
      name: 'menus',
      component: () => import('../views/MenusView.vue'),
      props: true,
    },
    {
      path: '/w/:ws/s/:site/menus/:menu',
      name: 'menu-edit',
      component: () => import('../views/MenuEditorView.vue'),
      props: true,
    },
    {
      path: '/w/:ws/s/:site/deployments',
      name: 'deployments',
      component: () => import('../views/DeploymentsView.vue'),
      props: true,
    },
    {
      path: '/w/:ws/s/:site/domains',
      name: 'domains',
      component: () => import('../views/DomainsView.vue'),
      props: true,
    },
    {
      path: '/w/:ws/s/:site/c/:collection/categories',
      name: 'categories',
      component: () => import('../views/CategoriesView.vue'),
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
