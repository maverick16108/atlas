<script setup>
import { ref, onMounted, onUnmounted, nextTick, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import { useI18n } from 'vue-i18n'

const router = useRouter()
const authStore = useAuthStore()
const { t } = useI18n()

const step = ref(1) // 1: Phone, 2: OTP
const phone = ref('')
const otpDigits = ref(['', '', '', ''])
const isLoading = ref(false)
const error = ref('')
const phoneInputRef = ref(null)
const otpInputRefs = ref([])
const mouseX = ref(0)
const mouseY = ref(0)

const updateMouse = (e) => {
    mouseX.value = e.clientX
    mouseY.value = e.clientY
}

onMounted(() => {
    window.addEventListener('mousemove', updateMouse)
    focusPhone()
})

onUnmounted(() => {
    window.removeEventListener('mousemove', updateMouse)
})

const focusPhone = () => {
    if (step.value === 1 && phoneInputRef.value) {
        nextTick(() => phoneInputRef.value.focus())
    }
}

const focusOtp = () => {
    if (step.value === 2) {
        // Retry a few times to ensure DOM is ready and animation doesn't block focus
        setTimeout(() => {
             if (otpInputRefs.value[0]) otpInputRefs.value[0].focus()
        }, 100)
        setTimeout(() => {
             if (otpInputRefs.value[0]) otpInputRefs.value[0].focus()
        }, 300)
    }
}

watch(step, (newVal) => {
    if (newVal === 1) focusPhone()
    if (newVal === 2) focusOtp()
})

// WebOTP Logic
if ('OTPCredential' in window) {
  window.addEventListener('DOMContentLoaded', async () => {
    try {
      const content = await navigator.credentials.get({
        otp: { transport: ['sms'] },
        signal: new AbortController().signal
      });
      if (content && content.code) {
           fillOtp(content.code)
      }
    } catch (err) {
      console.log(err);
    }
  });
}

const fillOtp = (code) => {
    if (code.length === 4) {
        otpDigits.value = code.split('')
        handleVerify()
    }
}

const handlePhoneInput = (event) => {
    let value = event.target.value.replace(/\D/g, '') // Strip non-digits
    
    // Auto-fix start (RU +7)
    if (value.startsWith('9')) {
        value = '79' + value.substring(1)
    } else if (value.startsWith('8')) {
        value = '7' + value.substring(1)
    }

    // Cap at 11 digits (7 + 10 digits)
    if (value.length > 11) value = value.substring(0, 11)
    
    // Format
    let formatted = ''
    if (value.length > 0) {
        formatted = '+' + value.substring(0, 1) // +7
    }
    if (value.length > 1) {
        formatted += ' (' + value.substring(1, 4) // +7 (999
    }
    if (value.length > 4) {
        formatted += ') ' + value.substring(4, 7) // +7 (999) 000
    }
    if (value.length > 7) {
        formatted += '-' + value.substring(7, 9) // +7 (999) 000-00
    }
    if (value.length > 9) {
        formatted += '-' + value.substring(9, 11) // +7 (999) 000-00-00
    }
    
    phone.value = formatted
}

const handleOtpInput = (index, event) => {
    const value = event.target.value
    if (!/^\d*$/.test(value)) {
        otpDigits.value[index] = ''
        return
    }

    if (value.length === 1) {
        if (index < 3) {
            otpDigits.value[index] = value
            nextTick(() => otpInputRefs.value[index + 1]?.focus())
        }
    }
    // Handle Auto-Submit if full
    if (otpDigits.value.join('').length === 4) {
        handleVerify()
    }
}

const handleOtpPaste = (event) => {
    const paste = event.clipboardData.getData('text')
    if (paste.length === 4 && /^\d+$/.test(paste)) {
        otpDigits.value = paste.split('')
        handleVerify()
        event.target.blur()
    }
}

const handleOtpKeydown = (index, event) => {
    if (event.key === 'Backspace' && !otpDigits.value[index] && index > 0) {
        otpInputRefs.value[index - 1].focus()
    }
}

const handleSendCode = async () => {
  // Validate format: +7 (XXX) XXX-XX-XX -> Length 18
  if (!phone.value || phone.value.length < 18) {
      error.value = t('login.error_phone')
      return
  }
  isLoading.value = true
  error.value = ''
  
  try {
      await authStore.requestOtp(phone.value)
      step.value = 2
  } catch (e) {
      error.value = t('login.error_failed_send')
  } finally {
      isLoading.value = false
  }
}

const handleVerify = async () => {
  const code = otpDigits.value.join('')
  if (code.length !== 4) {
      error.value = t('login.error_code')
      return
  }
  isLoading.value = true
  error.value = ''

  try {
      await authStore.verifyOtp(phone.value, code)
      router.push('/client')
  } catch (e) {
      // Styling change: removed (0000) suffix
      error.value = t('login.error_invalid_code')
      otpDigits.value = ['', '', '', '']
      focusOtp()
  } finally {
      isLoading.value = false
  }
}

</script>

<template>
  <div class="h-screen w-full flex flex-col items-center justify-center bg-dark-900 relative overflow-hidden">
    
    <!-- Layout: Interactive Background Texture (Dots) -->
    <!-- Layer 1: Base Dots (Visible) -->
    <div class="absolute inset-0 opacity-20" 
         style="background-image: radial-gradient(#D4AF37 1px, transparent 1px); background-size: 30px 30px;">
    </div>
    
    <!-- Layer 2: Bright Dots (Masked by Mouse) -->
    <div class="absolute inset-0 opacity-60 pointer-events-none" 
         :style="{ 
            backgroundImage: 'radial-gradient(#D4AF37 1.5px, transparent 1.5px)', 
            backgroundSize: '30px 30px',
            maskImage: `radial-gradient(300px circle at ${mouseX}px ${mouseY}px, black, transparent)`,
            webkitMaskImage: `radial-gradient(300px circle at ${mouseX}px ${mouseY}px, black, transparent)`
         }">
    </div>
    
    <!-- Background Effects (Ambient only, Top-Left Removed as requested) -->
    <!-- Only keeping bottom right and center ambient -->
    <div class="absolute bottom-[-20%] right-[-10%] w-[500px] h-[500px] bg-gold-600/10 rounded-full blur-[100px] pointer-events-none"></div>
    <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[800px] h-[800px] bg-gold-500/5 rounded-full blur-[150px] pointer-events-none"></div>

    <!-- Login Card -->
    <div 
      class="w-full max-w-sm bg-dark-800/80 backdrop-blur-2xl border border-white/10 rounded-2xl p-8 shadow-[0_0_50px_rgba(212,175,55,0.15)] relative z-10"
      v-motion
      :initial="{ opacity: 0, scale: 0.95 }"
      :enter="{ opacity: 1, scale: 1, transition: { duration: 500 } }"
    >
        <!-- Header -->
        <div class="text-center mb-10">
            <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-gradient-to-br from-gold-400 to-gold-600 shadow-[0_0_20px_rgba(212,175,55,0.4)] mb-6">
                 <svg class="w-8 h-8 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                 </svg>
            </div>
            <h1 class="text-3xl font-kanit font-bold text-white tracking-wide mb-2">
                {{ step === 1 ? t('login.title') : t('login.verify_title') }}
            </h1>
            <p class="text-gray-400 text-sm">
                {{ step === 1 ? t('login.subtitle') : t('login.code_sent', { phone: phone }) }}
            </p>
        </div>

        <!-- Step 1: Phone -->
        <form v-if="step === 1" @submit.prevent="handleSendCode" class="space-y-6">
            <div class="space-y-2">
                <label class="text-xs uppercase tracking-widest text-gold-500 font-bold ml-1">{{ t('login.phone_label') }}</label>
                <div class="relative group">
                    <input 
                      ref="phoneInputRef"
                      v-model="phone" 
                      @input="handlePhoneInput"
                      tabindex="1"
                      type="tel"
                      required
                      placeholder="+7 (999) 000-00-00"
                      class="w-full bg-dark-900/50 border border-white/10 rounded-lg px-4 py-3 text-white placeholder-gray-600 focus:border-gold-500 focus:ring-1 focus:ring-gold-500 focus:outline-none transition-all duration-300 font-mono text-lg tracking-widest group-hover:border-white/20"
                    >
                </div>
            </div>

            <button 
                type="submit" 
                :disabled="isLoading"
                class="w-full py-4 rounded-lg bg-gradient-to-r from-gold-400 to-gold-600 text-black font-bold uppercase tracking-widest hover:brightness-110 active:scale-[0.98] transition-all duration-300 shadow-[0_0_20px_rgba(212,175,55,0.3)] disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2"
            >
                <span v-if="isLoading" class="w-5 h-5 border-2 border-black/30 border-t-black rounded-full animate-spin"></span>
                <span v-else>{{ t('login.get_code') }}</span>
            </button>
        </form>

        <!-- Step 2: OTP (4 Cells) -->
        <form v-else @submit.prevent="handleVerify" class="space-y-6">
            <div class="flex justify-center gap-4 mb-4">
                <template v-for="(digit, index) in 4" :key="index">
                   <div class="relative group">
                      <input 
                         :ref="el => { if (el) otpInputRefs[index] = el }"
                         v-model="otpDigits[index]"
                         type="text" 
                         inputmode="numeric"
                         maxlength="1"
                         autocomplete="one-time-code"
                         @input="event => handleOtpInput(index, event)"
                         @keydown="event => handleOtpKeydown(index, event)"
                         @paste="handleOtpPaste"
                         class="w-14 h-16 bg-dark-900/50 border border-white/10 rounded-lg text-white text-center font-mono text-3xl focus:border-gold-500 focus:scale-105 focus:ring-1 focus:ring-gold-500 focus:outline-none transition-all duration-200 group-hover:border-white/20 shadow-inner"
                      >
                   </div>
                </template>
            </div>

             <button 
                type="submit" 
                :disabled="isLoading"
                class="w-full py-4 rounded-lg bg-gradient-to-r from-gold-400 to-gold-600 text-black font-bold uppercase tracking-widest hover:brightness-110 active:scale-[0.98] transition-all duration-300 shadow-[0_0_20px_rgba(212,175,55,0.3)] disabled:opacity-50 flex items-center justify-center"
            >
                <span v-if="isLoading" class="w-5 h-5 border-2 border-black/30 border-t-black rounded-full animate-spin"></span>
                <span v-else>{{ t('login.enter') }}</span>
            </button>
            
            <div class="text-center">
                <button @click="step = 1" type="button" class="text-xs text-gray-500 hover:text-gold-500 transition-colors">
                    {{ t('login.change_phone') }}
                </button>
            </div>
        </form>

        <!-- Error (Styled simply as requested) -->
        <p v-if="error" class="text-red-500 text-sm font-medium text-center mt-6 animate-pulse">{{ error }}</p>
    </div>

    <!-- Simple Footer -->
    <div class="absolute bottom-6 w-full text-center">
       <p class="text-[10px] uppercase tracking-widest text-gray-600">
           {{ t('footer.rights') }}
       </p>
    </div>

  </div>
</template>
