<script setup>
import { ref, reactive } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../../stores/auth'

const router = useRouter()
const authStore = useAuthStore()

const form = reactive({
  email: '',
  password: ''
})

const isLoading = ref(false)
const error = ref('')

const handleLogin = async () => {
  isLoading.value = true
  error.value = ''

  try {
    const role = await authStore.loginAdmin(form.email, form.password)
    router.push('/admin') // Success
  } catch (e) {
    error.value = 'Invalid Credentials'
  } finally {
    isLoading.value = false
  }
}
</script>

<template>
  <div class="min-h-screen flex items-center justify-center bg-[#050505] relative overflow-hidden px-4 border-t-4 border-gold-500">
    
    <!-- Background Effects (Darker for Admin) -->
    <div class="absolute inset-0 bg-[url('/img/pattern.png')] opacity-5"></div>
    <div class="absolute top-[-20%] left-[-10%] w-[600px] h-[600px] bg-gold-500/5 rounded-full blur-[120px] pointer-events-none"></div>

    <!-- Admin Login Card -->
    <div 
      class="w-full max-w-md bg-dark-900 border border-gold-500/20 rounded-sm p-10 shadow-[0_0_50px_rgba(212,175,55,0.1)] relative z-10"
      v-motion
      :initial="{ opacity: 0, y: 10 }"
      :enter="{ opacity: 1, y: 0, transition: { duration: 600 } }"
    >
        <!-- Header -->
        <div class="text-center mb-10">
            <div class="inline-flex items-center justify-center w-12 h-12 border border-gold-500/50 rounded-sm mb-4">
                 <span class="text-gold-500 font-bold text-xl">A</span>
            </div>
            <h1 class="text-xl font-kanit font-bold text-white tracking-[0.2em] uppercase mb-1">Atlas Administration</h1>
            <p class="text-gray-500 text-xs">Staff Access Only</p>
        </div>

        <!-- Form -->
        <form @submit.prevent="handleLogin" class="space-y-6">
            
            <div class="space-y-1">
                <label class="text-[10px] uppercase tracking-widest text-gray-500 font-bold ml-1">Email</label>
                <input 
                  v-model="form.email" 
                  type="email" 
                  required
                  class="w-full bg-black border border-white/10 rounded-sm px-4 py-3 text-white placeholder-gray-700 focus:border-gold-500 focus:outline-none transition-colors"
                >
            </div>

             <div class="space-y-1">
                <label class="text-[10px] uppercase tracking-widest text-gray-500 font-bold ml-1">Password</label>
                <input 
                  v-model="form.password" 
                  type="password" 
                  required
                  class="w-full bg-black border border-white/10 rounded-sm px-4 py-3 text-white placeholder-gray-700 focus:border-gold-500 focus:outline-none transition-colors"
                >
            </div>

            <!-- Error -->
            <p v-if="error" class="text-red-500 text-xs text-center font-mono">{{ error }}</p>

            <button 
                type="submit" 
                :disabled="isLoading"
                class="w-full py-4 rounded-sm bg-gold-600 text-black font-bold uppercase tracking-widest hover:bg-gold-500 transition-colors disabled:opacity-50"
            >
                <span v-if="isLoading">Authenticating...</span>
                <span v-else>Access Panel</span>
            </button>
        </form>
        
        <div class="mt-8 text-center">
            <router-link to="/login" class="text-[10px] text-gray-600 hover:text-white transition-colors uppercase tracking-widest">
                ← Return to Client Login
            </router-link>
        </div>
    </div>

  </div>
</template>
