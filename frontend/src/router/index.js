import { createRouter, createWebHistory } from 'vue-router'
import GeofencesPage from '../pages/GeofencesPage.vue'
import DeviceHistoryPage from '../pages/DeviceHistoryPage.vue'

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
      path: '/device-history',
      name: 'device-history',
      component: DeviceHistoryPage,
    },
    {
      path: '/:pathMatch(.*)*',
      redirect: { name: 'geofences' },
    },
  ],
})

export default router