import { createRouter, createWebHistory } from 'vue-router'

const routes = [
    {
        path: '/',
        name: 'Home',
        component: () => import('../views/Home.vue'), // Lazy load
        meta: { layout: 'PublicLayout' }
    },
    {
        path: '/login',
        name: 'Login',
        component: () => import('../views/Login.vue'),
        meta: { layout: 'PublicLayout' }
    },
    {
        path: '/admin/login',
        name: 'AdminLogin',
        component: () => import('../views/admin/Login.vue'),
        meta: { layout: 'PublicLayout' }
    },
    {
        path: '/admin',
        name: 'AdminDashboard',
        component: () => import('../views/admin/Dashboard.vue'),
        meta: { layout: 'AdminLayout', role: 'admin', title: 'meta_admin.dashboard' }
    },
    {
        path: '/admin/users',
        name: 'AdminUsers',
        component: () => import('../views/admin/Users.vue'),
        meta: { layout: 'AdminLayout', role: 'admin', title: 'meta_admin.users' }
    },
    {
        path: '/admin/auctions',
        name: 'AdminAuctions',
        component: () => import('../views/admin/Auctions.vue'),
        meta: { layout: 'AdminLayout', role: 'admin', title: 'meta_admin.auctions' }
    },
    {
        path: '/client',
        name: 'ClientDashboard',
        component: () => import('../views/client/ClientDashboard.vue'),
        meta: { layout: 'ClientLayout', role: 'client', title: 'meta.dashboard' }
    },
    {
        path: '/client/auctions',
        name: 'ClientAuctions',
        component: () => import('../views/client/ClientAuctions.vue'),
        meta: { layout: 'ClientLayout', role: 'client', title: 'meta.auctions' }
    },
    {
        path: '/client/profile',
        name: 'ClientProfile',
        component: () => import('../views/client/ClientProfile.vue'),
        meta: { layout: 'ClientLayout', role: 'client', title: 'meta.profile' }
    },
    {
        path: '/client/finance',
        redirect: '/client' // Pending Finance View
    }
]

const router = createRouter({
    history: createWebHistory(),
    routes
})

// Navigation Guard
router.beforeEach(async (to, from, next) => {
    // Import store inside guard to avoid Pinia initialization errors
    const { useAuthStore } = await import('../stores/auth')
    const authStore = useAuthStore()

    const isAuthenticated = authStore.isAuthenticated
    const userRole = authStore.userRole

    // Protected Routes
    if (to.meta.role) {
        if (!isAuthenticated) {
            return next('/login')
        }

        // Role Check
        if (to.meta.role === 'admin' && (userRole !== 'admin' && userRole !== 'moderator')) {
            return next('/client') // Redirect unauthorized admin access to client
        }

        if (to.meta.role === 'client' && userRole === 'admin') {
            // Admin can view everything, allow or redirect? Usually allow.
        }
    }

    // Guest Routes (Login)
    if (to.path === '/login' && isAuthenticated) {
        if (userRole === 'admin' || userRole === 'moderator') {
            return next('/admin')
        } else {
            return next('/client')
        }
    }

    next()
})

export default router
