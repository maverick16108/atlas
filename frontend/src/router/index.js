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
        path: '/admin/auctions/:id',
        name: 'AdminAuctionDetail',
        component: () => import('../views/admin/AuctionDetail.vue'),
        meta: { layout: 'AdminLayout', role: 'admin', title: 'Аукцион' }
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
        path: '/client/auctions/:id',
        name: 'ClientAuctionRoom',
        component: () => import('../views/client/ClientAuctionRoom.vue'),
        meta: { layout: 'ClientLayout', role: 'client', title: 'Аукцион' }
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

    const requiredRole = to.meta.role

    // No role required — public route
    if (!requiredRole) {
        // Guest Routes: redirect if already authenticated
        if (to.path === '/login' && authStore.isClientAuthenticated) {
            return next('/client')
        }
        if (to.path === '/admin/login' && authStore.isAdminAuthenticated) {
            return next('/admin')
        }
        return next()
    }

    // Admin Routes (role: 'admin' or 'super_admin')
    if (['admin', 'super_admin'].includes(requiredRole)) {
        if (!authStore.isAdminAuthenticated) {
            return next('/admin/login')
        }

        // Super admin only routes
        if (requiredRole === 'super_admin' && authStore.adminRole !== 'super_admin') {
            return next('/admin')
        }

        // Check admin has valid admin role
        if (!['admin', 'moderator', 'super_admin'].includes(authStore.adminRole)) {
            return next('/admin/login')
        }

        return next()
    }

    // Client Routes (role: 'client')
    if (requiredRole === 'client') {
        if (!authStore.isClientAuthenticated) {
            return next('/login')
        }
        return next()
    }

    next()
})

export default router
