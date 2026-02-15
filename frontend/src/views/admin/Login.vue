<script setup>
import { ref, reactive, onMounted, onUnmounted, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../../stores/auth'
import { useI18n } from 'vue-i18n'

const router = useRouter()
const authStore = useAuthStore()
const { t } = useI18n()

const form = reactive({
  email: '',
  password: ''
})

const isLoading = ref(false)
const error = ref('')
const emailError = ref('')
const mouseX = ref(0)
const mouseY = ref(0)

const updateMouse = (e) => {
    mouseX.value = e.clientX
    mouseY.value = e.clientY
}

onMounted(() => {
    window.addEventListener('mousemove', updateMouse)
})

onUnmounted(() => {
    window.removeEventListener('mousemove', updateMouse)
})

const validateEmail = () => {
    if (!form.email) {
        emailError.value = t('admin.login.error_email_required')
        return false
    }
    const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/
    if (!re.test(form.email)) {
        emailError.value = t('admin.login.error_email_format')
        return false
    }
    emailError.value = ''
    return true
}

const handleLogin = async () => {
  if (!validateEmail()) return

  isLoading.value = true
  error.value = ''

  try {
    await authStore.loginAdmin(form.email, form.password)
    router.push('/admin') 
  } catch (e) {
    error.value = t('admin.login.error_credentials')
  } finally {
    isLoading.value = false
  }
}

watch(() => form.email, () => {
    if (emailError.value) validateEmail()
})
</script>

<template>
  <div class="fixed inset-0 w-full bg-dark-900">
    
    <!-- Background Layers (Fixed) -->
    <div class="absolute inset-0 overflow-hidden">
        <!-- Layer 1: Base Dots (Visible) -->
        <div class="absolute inset-0 opacity-20" 
             style="background-image: radial-gradient(#DC2626 1px, transparent 1px); background-size: 30px 30px;">
        </div>
        
        <!-- Layer 2: Bright Dots (Masked by Mouse) -->
        <div class="absolute inset-0 opacity-60 pointer-events-none" 
             :style="{ 
                backgroundImage: 'radial-gradient(#DC2626 1.5px, transparent 1.5px)', 
                backgroundSize: '30px 30px',
                maskImage: `radial-gradient(300px circle at ${mouseX}px ${mouseY}px, black, transparent)`,
                webkitMaskImage: `radial-gradient(300px circle at ${mouseX}px ${mouseY}px, black, transparent)`
             }">
        </div>
        
        <!-- Ambient Effects -->
        <div class="absolute bottom-[-20%] right-[-10%] w-[500px] h-[500px] bg-red-600/10 rounded-full blur-[100px] pointer-events-none"></div>
        <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[800px] h-[800px] bg-red-500/5 rounded-full blur-[150px] pointer-events-none"></div>
    </div>

    <!-- Content Scroller -->
    <div class="absolute inset-0 pt-20 overflow-y-auto overflow-x-hidden overscroll-y-none no-scrollbar">
        <div class="min-h-full flex flex-col items-center p-4">
            
            <!-- Spacer Top -->
            <div class="flex-1"></div>

            <!-- Login Card -->
            <div 
              class="w-full max-w-sm bg-dark-800/80 backdrop-blur-2xl border border-white/10 rounded-2xl p-8 shadow-[0_0_50px_rgba(220,38,38,0.15)] relative z-10"
              v-motion
              :initial="{ opacity: 0, scale: 0.95 }"
              :enter="{ opacity: 1, scale: 1, transition: { duration: 500 } }"
            >
                <!-- Header -->
                <div class="text-center mb-10">
                    <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-gradient-to-br from-red-500 to-red-700 shadow-[0_0_20px_rgba(220,38,38,0.4)] mb-6">
                         <svg class="w-8 h-8 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                         </svg>
                    </div>
                    <h1 class="text-3xl font-kanit font-bold text-white tracking-wide mb-2">
                        {{ t('admin.login.title') }}
                    </h1>
                    <p class="text-gray-400 text-sm">
                        {{ t('admin.login.subtitle') }}
                    </p>
                </div>

                <!-- Form -->
                <form @submit.prevent="handleLogin" class="space-y-6" novalidate>
                    
                    <!-- Email -->
                    <div class="space-y-2">
                        <label class="text-xs uppercase tracking-widest text-red-500 font-bold ml-1">{{ t('admin.login.email_label') }}</label>
                        <div class="relative group">
                            <input 
                              v-model="form.email" 
                              type="email"
                              @blur="validateEmail"
                              placeholder="admin@atlas.ru"
                              class="w-full bg-dark-900/50 border rounded-lg px-4 py-3 text-white placeholder-gray-600 focus:outline-none transition-all duration-300 font-mono text-lg tracking-wide"
                              :class="emailError ? 'border-red-500/50 focus:border-red-500 focus:ring-1 focus:ring-red-500' : 'border-white/10 focus:border-red-500 focus:ring-1 focus:ring-red-500 group-hover:border-white/20'"
                            >
                        </div>
                        <!-- Email Error (Smooth) -->
                        <div class="h-4 overflow-hidden relative">
                             <transition 
                                enter-active-class="transition ease-out duration-300" 
                                enter-from-class="transform -translate-y-2 opacity-0" 
                                enter-to-class="transform translate-y-0 opacity-100"
                                leave-active-class="transition ease-in duration-200" 
                                leave-from-class="transform translate-y-0 opacity-100" 
                                leave-to-class="transform -translate-y-2 opacity-0"
                             >
                                <p v-if="emailError" class="text-red-400 text-xs font-medium absolute top-0 left-1 flex items-center gap-1">
                                     <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                                     {{ emailError }}
                                </p>
                             </transition>
                        </div>
                    </div>

                    <!-- Password -->
                    <div class="space-y-2">
                        <label class="text-xs uppercase tracking-widest text-red-500 font-bold ml-1">{{ t('admin.login.password_label') }}</label>
                        <div class="relative group">
                            <input 
                              v-model="form.password" 
                              type="password"
                              placeholder="••••••"
                              class="w-full bg-dark-900/50 border border-white/10 rounded-lg px-4 py-3 text-white placeholder-gray-600 focus:border-red-500 focus:ring-1 focus:ring-red-500 focus:outline-none transition-all duration-300 font-mono text-lg tracking-wide group-hover:border-white/20"
                            >
                        </div>
                    </div>

                    <button 
                        type="submit" 
                        :disabled="isLoading"
                        class="w-full py-4 rounded-lg bg-gradient-to-r from-red-500 to-red-700 text-white font-bold uppercase tracking-widest hover:brightness-110 active:scale-[0.98] transition-all duration-300 shadow-[0_0_20px_rgba(220,38,38,0.3)] disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2"
                    >
                        <span v-if="isLoading" class="w-5 h-5 border-2 border-black/30 border-t-black rounded-full animate-spin"></span>
                        <span v-else>{{ t('admin.login.submit') }}</span>
                    </button>
                    
                    <!-- General Error -->
                    <p v-if="error" class="text-red-500 text-sm font-medium text-center animate-pulse">{{ error }}</p>
                </form>
                
                
            </div>

            <!-- Spacer Bottom -->
            <div class="flex-1"></div>

            <!-- Simple Footer -->
            <div class="w-full text-center mt-4 pb-6">
               <p class="text-[10px] uppercase tracking-widest text-gray-600">
                   {{ t('footer.rights') }}
               </p>
            </div>
        </div>
    </div>

  </div>
</template>
