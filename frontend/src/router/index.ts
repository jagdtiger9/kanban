import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import { isGlobalAdmin } from '../utils/roles'

const router = createRouter({
    history: createWebHistory(),
    routes: [
        {
            path: '/',
            component: () => import('../views/ShellView.vue'),
            children: [
                { path: '', name: 'board', component: () => import('../views/BoardHomeView.vue') },
                { path: 'settings', name: 'settings', component: () => import('../views/SettingsView.vue') },
                {
                    path: 'admin/users',
                    name: 'adminUsers',
                    component: () => import('../views/AdminUsersView.vue'),
                    meta: { requiresGlobalAdmin: true },
                },
                { path: 'projects/:id', name: 'project', component: () => import('../views/ProjectView.vue') },
                {
                    path: ':pathMatch(.*)*',
                    name: 'notFound',
                    component: () => import('../views/NotFoundView.vue'),
                },
            ],
        },
        { path: '/login', name: 'login', component: () => import('../views/LoginView.vue') },
        { path: '/register', name: 'register', component: () => import('../views/RegisterView.vue') },
        { path: '/confirm/:token', name: 'confirm', component: () => import('../views/ConfirmView.vue') },
    ],
})

router.beforeEach(async (to) => {
    const auth = useAuthStore()
    if (!auth.initialized) {
        await auth.fetchMe()
    }
    if (!auth.isAuth && to.name !== 'login' && to.name !== 'register' && to.name !== 'confirm') {
        return { name: 'login' }
    }
    if (auth.isAuth && (to.name === 'login' || to.name === 'register' || to.name === 'confirm')) {
        return { name: 'board' }
    }
    if (to.meta.requiresGlobalAdmin && !isGlobalAdmin(auth.user)) {
        return { name: 'board' }
    }
})

export default router
