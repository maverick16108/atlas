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
        path: '/admin/moderators',
        name: 'AdminModerators',
        component: () => import('../views/admin/Moderators.vue'),
        meta: { layout: 'AdminLayout', role: 'super_admin', title: 'meta_admin.moderators' }
    },
    {
        path: '/admin/activity-log',
        name: 'AdminActivityLog',
        component: () => import('../views/admin/ActivityLog.vue'),
        meta: { layout: 'AdminLayout', role: 'super_admin', title: 'Журнал' }
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
]

const router = createRouter({
    history: createWebHistory(),
    routes,
    scrollBehavior(to, from, savedPosition) {
        if (savedPosition) {
            return savedPosition
        }
        return { top: 0 }
    }
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
            // Check if Admin Route
            if (['admin', 'super_admin'].includes(to.meta.role)) {
                return next('/admin/login')
            }
            return next('/login')
        }

        // Role Check
        if (to.meta.role === 'super_admin' && userRole !== 'super_admin') {
            return next('/admin') // Back to dashboard if trying to access super routes
        }

        if (to.meta.role === 'admin' && !['admin', 'moderator', 'super_admin'].includes(userRole)) {
            return next('/admin/login') // Redirect unauthorized admin access to admin login
        }

        if (to.meta.role === 'client' && ['admin', 'super_admin'].includes(userRole)) {
            // Admin can view everything, allow or redirect? Usually allow.
        }
    }

    // Guest Routes (Login) - only redirect clients who are already logged in
    if (to.path === '/login' && isAuthenticated) {
        // Admins can view /login page (they might want to see client flow or switch accounts)
        if (['admin', 'moderator', 'super_admin'].includes(userRole)) {
            // Allow access to /login page
        } else {
            // Clients already logged in - redirect to their dashboard
            return next('/client')
        }
    }

    // Admin login - redirect if already authenticated as admin
    if (to.path === '/admin/login' && isAuthenticated) {
        if (['admin', 'moderator', 'super_admin'].includes(userRole)) {
            return next('/admin')
        }
    }

    // Redirect /admin root to dashboard explicitly if needed, though component handles it
    // But importantly: if user is logged in as client and tries to go to /admin/login, 
    // they should be allowed to see the login page to potentially switch accounts or correct their mistake.
    // The previous logic allowed this.

    next()
})

export default router
