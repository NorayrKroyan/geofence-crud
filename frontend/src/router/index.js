import { createRouter, createWebHistory } from 'vue-router'

const routes = [
  {
    path: '/',
    redirect: '/geofences',
  },
  {
    path: '/geofences',
    name: 'geofences',
    component: () => import('../pages/GeofencesPage.vue'),
  },
]

const router = createRouter({
  history: createWebHistory(),
  routes,
})

export default router
