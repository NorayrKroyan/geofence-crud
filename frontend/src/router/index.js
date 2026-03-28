import { createRouter, createWebHistory } from 'vue-router'
import GeofencesPage from '../pages/GeofencesPage.vue'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    {
      path: '/',
      redirect: { name: 'geofences' },
    },
    {
      path: '/geofences',
      name: 'geofences',
      component: GeofencesPage,
    },
    {
      path: '/:pathMatch(.*)*',
      redirect: { name: 'geofences' },
    },
  ],
})

export default router