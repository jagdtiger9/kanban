import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '../stores/auth'

const router = createRouter({
    history: createWebHistory(),
    routes: [
        {
            path: '/',
            component: () => import('../views/ShellView.vue'),
            children: [
                { path: '', name: 'board', component: () => import('../views/BoardHomeView.vue') },
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
    ],
})

router.beforeEach(async (to) => {
    const auth = useAuthStore()
    if (!auth.initialized) {
        await auth.fetchMe()
    }
    if (!auth.isAuth && to.name !== 'login' && to.name !== 'register') {
        return { name: 'login' }
    }
    if (auth.isAuth && (to.name === 'login' || to.name === 'register')) {
        return { name: 'board' }
    }
})

export default router
