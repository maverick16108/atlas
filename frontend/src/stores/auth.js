import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import axios from 'axios'

export const useAuthStore = defineStore('auth', () => {
    const router = useRouter()

    // ===== Dual Session State =====
    const adminUser = ref(JSON.parse(localStorage.getItem('admin_user')) || null)
    const adminToken = ref(localStorage.getItem('admin_token') || null)
    const clientUser = ref(JSON.parse(localStorage.getItem('client_user')) || null)
    const clientToken = ref(localStorage.getItem('client_token') || null)

    // Migration: clean up old unified keys if they exist
    if (localStorage.getItem('user') || localStorage.getItem('token')) {
        localStorage.removeItem('user')
        localStorage.removeItem('token')
    }

    // ===== Computed Helpers =====
    const isAdminAuthenticated = computed(() => !!adminToken.value)
    const isClientAuthenticated = computed(() => !!clientToken.value)
    const isAuthenticated = computed(() => isAdminAuthenticated.value || isClientAuthenticated.value)

    const adminRole = computed(() => adminUser.value?.role)
    const clientRole = computed(() => clientUser.value?.role || 'client')

    // Legacy compatibility: `user` and `userRole` based on whichever session exists
    // (used by AppLayout.vue and router for generic checks)
    const user = computed(() => adminUser.value || clientUser.value)
    const userRole = computed(() => adminUser.value?.role || clientUser.value?.role || null)

    // ===== Axios Interceptor =====
    // Dynamically set Authorization header based on request URL
    axios.interceptors.request.use((config) => {
        const url = config.url || ''
        let token = null

        if (url.includes('/api/client/')) {
            token = clientToken.value
        } else if (url.includes('/api/auth/otp/')) {
            // OTP endpoints don't need auth, but if token exists use client
            token = clientToken.value
        } else {
            // Admin endpoints and auth/login
            token = adminToken.value
        }

        if (token) {
            config.headers.Authorization = `Bearer ${token}`
        } else {
            delete config.headers.Authorization
        }

        return config
    })

    // Handle 401 responses — auto-logout on token expiry
    axios.interceptors.response.use(
        (response) => response,
        (error) => {
            if (error.response?.status === 401) {
                const url = error.config?.url || ''
                // Don't redirect on login/otp endpoints
                if (!url.includes('/api/auth/')) {
                    if (url.includes('/api/client/')) {
                        clientUser.value = null
                        clientToken.value = null
                        localStorage.removeItem('client_user')
                        localStorage.removeItem('client_token')
                        window.location.href = '/login'
                    } else {
                        adminUser.value = null
                        adminToken.value = null
                        localStorage.removeItem('admin_user')
                        localStorage.removeItem('admin_token')
                        window.location.href = '/admin/login'
                    }
                }
            }
            return Promise.reject(error)
        }
    )

    // Remove any old global default header
    delete axios.defaults.headers.common['Authorization']

    // ===== ADMIN LOGIN: Email + Password =====
    const loginAdmin = async (email, password) => {
        try {
            const response = await axios.post('/api/auth/login', { email, password })
            const { token: tokenData, user: userData, role } = response.data

            saveAdminUser(userData, tokenData)
            return role
        } catch (error) {
            console.error('Admin Login Failed:', error)

            if (!error.response) {
                console.warn('Backend unavailable, using mock data (Changes will NOT persist)')
            }
            throw error
        }
    }

    // ===== CLIENT LOGIN STEP 1: Send SMS =====
    const requestOtp = async (phone) => {
        try {
            await axios.post('/api/auth/otp/request', { phone })
            return true
        } catch (error) {
            console.error('OTP Request Failed:', error)
            throw error
        }
    }

    // ===== CLIENT LOGIN STEP 2: Verify OTP =====
    const verifyOtp = async (phone, otp) => {
        try {
            const response = await axios.post('/api/auth/otp/verify', { phone, code: otp })
            const { user, token, role } = response.data

            // Add avatar if missing
            if (!user.avatar) {
                user.avatar = `https://ui-avatars.com/api/?name=${encodeURIComponent(user.name || 'Client')}&background=4ADE80&color=fff`
            }

            saveClientUser(user, token)
            return role || 'client'
        } catch (error) {
            console.error('OTP Verify Failed:', error)
            if (error.response && error.response.status === 422) {
                throw new Error('Invalid Code')
            }
            throw error
        }
    }

    // ===== Save Helpers =====
    const saveAdminUser = (userData, tokenData) => {
        adminUser.value = userData
        adminToken.value = tokenData
        localStorage.setItem('admin_user', JSON.stringify(userData))
        localStorage.setItem('admin_token', tokenData)
    }

    const saveClientUser = (userData, tokenData) => {
        clientUser.value = userData
        clientToken.value = tokenData
        localStorage.setItem('client_user', JSON.stringify(userData))
        localStorage.setItem('client_token', tokenData)
    }

    const updateClientUser = (updates) => {
        if (clientUser.value) {
            clientUser.value = { ...clientUser.value, ...updates }
            localStorage.setItem('client_user', JSON.stringify(clientUser.value))
        }
    }

    // ===== Logout =====
    const logout = (context) => {
        // Auto-detect context if not provided
        if (!context) {
            context = adminUser.value ? 'admin' : 'client'
        }

        if (context === 'admin') {
            adminUser.value = null
            adminToken.value = null
            localStorage.removeItem('admin_user')
            localStorage.removeItem('admin_token')
            router.push('/admin/login')
        } else {
            clientUser.value = null
            clientToken.value = null
            localStorage.removeItem('client_user')
            localStorage.removeItem('client_token')
            router.push('/login')
        }
    }

    return {
        // Dual state
        adminUser,
        adminToken,
        clientUser,
        clientToken,

        // Computed
        isAdminAuthenticated,
        isClientAuthenticated,
        isAuthenticated,
        adminRole,
        clientRole,

        // Legacy compatibility
        user,
        userRole,

        // Actions
        loginAdmin,
        requestOtp,
        verifyOtp,
        logout,
        updateClientUser
    }
})
