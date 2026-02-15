import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import axios from 'axios'

export const useAuthStore = defineStore('auth', () => {
    // Persistent state
    const user = ref(JSON.parse(localStorage.getItem('user')) || null)
    const token = ref(localStorage.getItem('token') || null)
    const router = useRouter()

    const isAuthenticated = computed(() => !!token.value)
    const userRole = computed(() => user.value?.role)

    // Setup Axios defaults if token exists
    if (token.value) {
        axios.defaults.headers.common['Authorization'] = `Bearer ${token.value}`
    }

    // ADMIN LOGIN: Email + Password
    const loginAdmin = async (email, password) => {
        try {
            const response = await axios.post('/api/auth/login', { email, password })
            const { token: tokenData, user: userData, role } = response.data

            saveUser(userData, tokenData)
            return role
        } catch (error) {
            console.error('Admin Login Failed:', error)

            // Fallback for Development if Backend is offline (remove in production)
            if (!error.response) {
                console.warn('Backend unavailable, using mock data (Changes will NOT persist)')
                // ... keep mock logic if desired, or just throw
            }
            throw error
        }
    }

    // CLIENT LOGIN STEP 1: Send SMS
    const requestOtp = async (phone) => {
        try {
            await axios.post('/api/auth/otp/request', { phone })
            return true
        } catch (error) {
            console.error('OTP Request Failed:', error)
            throw error // Let component handle UI error
        }
    }

    // CLIENT LOGIN STEP 2: Verify OTP
    const verifyOtp = async (phone, otp) => {
        // BYPASS: Simulated Success for Development
        if (otp === '0000') {
            const mockUser = {
                id: 12345,
                name: 'Test Client',
                phone: phone,
                role: 'client',
                avatar: 'https://ui-avatars.com/api/?name=TC&background=4ADE80&color=fff'
            }
            saveUser(mockUser, 'mock-client-token')
            return 'client'
        }

        try {
            const response = await axios.post('/api/auth/otp/verify', { phone, code: otp })
            const { user, token, role } = response.data

            // Add avatar if missing (UI polish)
            if (!user.avatar) {
                user.avatar = `https://ui-avatars.com/api/?name=${user.name || 'Client'}&background=4ADE80&color=fff`
            }

            saveUser(user, token)
            return role || 'client'
        } catch (error) {
            console.error('OTP Verify Failed:', error)
            if (error.response && error.response.status === 422) {
                throw new Error('Invalid Code')
            }
            throw error
        }
    }

    const saveUser = (userData, tokenData) => {
        user.value = userData
        token.value = tokenData
        localStorage.setItem('user', JSON.stringify(userData))
        localStorage.setItem('token', tokenData)
        axios.defaults.headers.common['Authorization'] = `Bearer ${tokenData}`
    }

    const logout = () => {
        const isClient = userRole.value === 'client'

        // Call API logout if possible (skipping for now to avoid complexity)
        // axios.post('/api/auth/logout').catch(() => {})

        user.value = null
        token.value = null
        localStorage.removeItem('user')
        localStorage.removeItem('token')
        delete axios.defaults.headers.common['Authorization']

        // Redirect logic
        if (isClient) {
            router.push('/login')
        } else {
            router.push('/admin/login')
        }
    }

    return {
        user,
        token,
        isAuthenticated,
        userRole,
        loginAdmin,
        requestOtp,
        verifyOtp,
        logout
    }
})
