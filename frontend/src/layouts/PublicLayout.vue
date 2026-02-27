<script setup>
import { ref, provide, computed, watch, nextTick } from 'vue'
import BaseButton from '../components/ui/BaseButton.vue'
import BaseModal from '../components/ui/BaseModal.vue'
import StandardModal from '../components/ui/StandardModal.vue'
import BaseInput from '../components/ui/BaseInput.vue'
import { useRouter, useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import axios from 'axios'

import LegalModal from '../components/ui/LegalModal.vue'
import GoldenMesh from '../components/effects/GoldenMesh.vue'

const router = useRouter()
const route = useRoute()
const { t, locale, tm } = useI18n()

const showAccreditation = computed(() => {
    return !['Login', 'AdminLogin'].includes(route.name)
})

const isAdminLogin = computed(() => {
    return route.name === 'AdminLogin'
})

const isModalOpen = ref(false)
const isLegalModalOpen = ref(false)
const legalTitle = ref('')
const legalContent = ref('')
const email = ref('')
const name = ref('')
const phone = ref('')
const orgInput = ref(null)
const isLoading = ref(false)
const successMessage = ref('')
const errors = ref({})

const submitApplication = async () => {
    errors.value = {}
    successMessage.value = ''
    
    if (!name.value) errors.value.name = 'Обязательное поле'
    if (!phone.value) errors.value.phone = 'Обязательное поле'
    if (!email.value) errors.value.email = 'Обязательное поле'
    
    if (Object.keys(errors.value).length > 0) return

    isLoading.value = true
    try {
        await axios.post('/api/auth/register', {
            name: name.value,
            email: email.value,
            phone: phone.value
        })
        
        successMessage.value = 'Заявка успешно отправлена.'
        // Clear form
        name.value = ''
        email.value = ''
        phone.value = ''
        
        // Auto close after delay? Or just let user read
    } catch (e) {
        console.error(e)
        if (e.response && e.response.status === 422) {
             const backendErrors = e.response.data.errors
             if (backendErrors.email) errors.value.email = backendErrors.email[0]
             if (backendErrors.phone) errors.value.phone = backendErrors.phone[0]
             if (backendErrors.name) errors.value.name = backendErrors.name[0]
        } else {
             errors.value.general = 'Ошибка отправки заявки. Попробуйте позже.'
        }
    } finally {
        isLoading.value = false
    }
}

const openModal = async () => {
    isModalOpen.value = true
    successMessage.value = ''
    await nextTick()
    orgInput.value?.focus()
}
const closeModal = () => isModalOpen.value = false

const openLegalModal = (type) => {
    legalTitle.value = t(`legal.${type}_title`)
    legalContent.value = t(`legal.${type}_content`)
    isLegalModalOpen.value = true
}

// Provide the openModal function to child components
provide('openAccreditationModal', openModal)

const toggleLanguage = () => {
  locale.value = locale.value === 'ru' ? 'en' : 'ru'
}

// Dynamic Title logic
watch(
  [() => route.name, () => locale.value],
  () => {
    const baseTitle = locale.value === 'ru' ? 'Атлас Майнинг' : 'Atlas Mining'
    const pageTitle = route.meta.title ? t(route.meta.title) : ''
    // Specific check for Login since it might not be in meta yet or dynamic
    if (route.name === 'Login') {
       document.title = `${t('login.title')} | ${baseTitle}`
    } else if (route.name === 'AdminLogin') {
       document.title = `${t('login.staff_login')} | ${baseTitle}`
    } else {
       document.title = baseTitle
    }
  },
  { immediate: true }
)
</script>

<template>
  <div class="min-h-screen bg-dark-900 text-white selection:bg-gold-500 selection:text-white relative" :class="[showAccreditation ? 'overflow-x-hidden' : 'h-screen overflow-hidden']">
    <!-- Background Effect -->
    <GoldenMesh v-if="showAccreditation" />

    <!-- Navbar -->
    <nav 
      class="fixed top-0 left-0 right-0 z-50 transition-all duration-300 backdrop-blur-xl bg-dark-900/60"
      :class="isAdminLogin ? 'border-b border-red-500/20 shadow-[0_4px_20px_rgba(220,38,38,0.15)]' : 'border-b border-gold-500/20 shadow-[0_4px_20px_rgba(212,175,55,0.15)]'"
    >
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-20 items-center">
          <!-- Logo -->
          <div class="flex-shrink-0 flex items-center cursor-pointer space-x-3 group relative select-none" @click="router.push('/')">
            <div class="animate-pulse-gold rounded-md">
                 <img src="/logo.png" alt="Atlas Mining" class="h-10 w-auto rounded-md" />
            </div>
            <div class="hidden sm:flex flex-col items-center overflow-hidden">
               <span class="font-sans font-black text-2xl leading-none tracking-[0.22em] text-white uppercase transform scale-y-[0.75] origin-bottom ml-[3px] text-glow-gold" style="font-weight: 900; -webkit-text-stroke: 1.5px white;">{{ t('logo.line1') }}</span>
               <div class="font-montserrat font-light text-sm leading-none tracking-[0.45em] text-gold-400 mt-1 uppercase ml-[2px] flex">
                  <span 
                    v-for="(char, i) in t('logo.line2').split('')" 
                    :key="i"
                    class="animate-[letter-flash_8s_infinite]"
                    :style="{ animationDelay: `${i * 50}ms` }"
                  >{{ char }}</span>
               </div>
            </div>
          </div>
          
          <!-- Actions -->
          <div class="flex items-center space-x-4">
            <button @click="toggleLanguage" class="text-gray-400 hover:text-white font-medium transition-colors select-none">
              {{ locale === 'ru' ? 'EN' : 'RU' }}
            </button>
            <BaseButton v-if="showAccreditation" to="/login" variant="glass" size="sm">
              {{ t('nav.login') }}
            </BaseButton>
            <BaseButton v-if="showAccreditation" variant="primary" size="sm" class="hidden sm:inline-flex" @click="openModal">
              {{ t('nav.accreditation') }}
            </BaseButton>
          </div>
        </div>
      </div>
    </nav>

    <!-- Content -->
    <main :class="[showAccreditation ? 'pt-20' : 'h-screen overflow-hidden']" class="relative z-10">
      <slot />
    </main>
    
    <!-- Footer -->
    <footer v-if="showAccreditation" class="relative bg-dark-800 pt-20 pb-10 overflow-hidden border-t border-white/5 z-10">
      <!-- Background Glow -->
      <div class="absolute bottom-0 left-1/2 -translate-x-1/2 w-[800px] h-[500px] bg-gold-600/5 rounded-full blur-[128px] pointer-events-none"></div>
      
      <div class="max-w-7xl mx-auto px-4 relative z-10">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-12 lg:gap-8 mb-16 items-start">
          
          <!-- Column 1: Brand -->
          <div class="space-y-6">
             <div class="flex items-center space-x-3 group relative cursor-pointer" @click="router.push('/')">
                <div class="animate-pulse-gold rounded-md">
                     <img src="/logo.png" alt="Atlas Mining" class="h-10 w-auto rounded-md" />
                </div>
                <div class="hidden sm:flex flex-col items-center overflow-hidden">
                   <span class="font-sans font-black text-2xl leading-none tracking-[0.22em] text-white uppercase transform scale-y-[0.75] origin-bottom ml-[3px] text-glow-gold" style="font-weight: 900; -webkit-text-stroke: 1.5px white;">{{ t('logo.line1') }}</span>
                   <div class="font-montserrat font-light text-sm leading-none tracking-[0.45em] text-gold-400 mt-1 uppercase ml-[2px] flex">
                      <span 
                        v-for="(char, i) in t('logo.line2').split('')" 
                        :key="i"
                        class="animate-[letter-flash_8s_infinite]"
                        :style="{ animationDelay: `${i * 50}ms` }"
                      >{{ char }}</span>
                   </div>
                </div>
             </div>
             <p class="text-gray-400 text-sm leading-relaxed max-w-xs">
               {{ t('hero.subtitle').replace(/<[^>]*>?/gm, '') }}
             </p>
             <div class="flex space-x-4">
               <!-- Social placeholders could go here -->
             </div>
          </div>

          <!-- Column 2: Accreditation CTA (Block 3D Gold) -->
          <div class="flex justify-center w-full">
             <div v-if="showAccreditation" @click="openModal" class="w-full max-w-[260px] relative group cursor-pointer select-none">
                <!-- Aura -->
                <div class="absolute -inset-0.5 bg-gold-400 blur-xl opacity-20 group-hover:opacity-60 transition duration-700 animate-pulse-slow"></div>
                
                <!-- Ingot Body (Block Effect) -->
                <div class="relative bg-gradient-to-b from-[#FFF5C3] via-[#D4AF37] to-[#B8860B] rounded-xl p-[1px] shadow-[0_10px_30px_-5px_rgba(212,175,55,0.4)] transform transition-transform duration-300 hover:-translate-y-2 hover:shadow-[0_25px_50px_-10px_rgba(212,175,55,0.5)]">
                    
                    <!-- Inner Face -->
                    <div class="relative bg-gradient-to-br from-[#F5D061] via-[#E1B32A] to-[#C59B25] rounded-[11px] p-5 flex flex-col items-center text-center gap-3 overflow-hidden">
                         
                         <!-- Thickness/Depth Simulation -->
                         <div class="absolute inset-0 rounded-[11px] shadow-[inset_0_-4px_0_rgba(0,0,0,0.15)] pointer-events-none z-0"></div>

                         <!-- Shine Effect -->
                         <div class="shine-effect absolute inset-0 bg-gradient-to-r from-transparent via-white/40 to-transparent pointer-events-none z-10"></div>

                         <!-- Icon -->
                         <div class="relative z-20 w-10 h-10 rounded-lg bg-dark-900/10 border border-dark-900/5 flex items-center justify-center text-dark-900 shadow-inner group-hover:scale-110 transition-transform duration-500">
                              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" /></svg>
                         </div>

                         <!-- Text -->
                         <div class="relative z-20">
                             <h3 class="text-sm font-black text-dark-900 uppercase tracking-wide leading-none mb-1 drop-shadow-sm">{{ t('form.title') }}</h3>
                             <p class="text-dark-900/70 text-[10px] font-bold leading-tight line-clamp-2">{{ t('form.subtitle') }}</p>
                         </div>
     
                         <!-- Button -->
                         <button class="relative z-20 w-full bg-dark-900 text-[#F5D061] hover:text-white hover:bg-black px-4 py-2.5 rounded-lg font-bold uppercase tracking-wider text-[10px] transition-all shadow-md hover:shadow-lg border border-white/5 mt-1 pointer-events-none">
                             {{ t('form.submit') }}
                         </button>
                    </div>
                </div>
             </div>
          </div>
          
          <!-- Column 3: Contacts -->
          <div class="lg:text-right space-y-4">
            <h4 class="text-lg font-kanit font-bold text-gold-400 uppercase tracking-widest mb-6 select-none">{{ t('footer.contacts') }}</h4>
            
            <div class="space-y-4 text-gray-300">
              <a href="https://yandex.ru/maps/?text=121099,+г.+Москва,+Смоленская+пл.,+д.+5" target="_blank" class="group flex items-center lg:justify-end space-x-3 cursor-pointer hover:text-white transition-colors">
                 <span class="w-8 h-8 rounded-full bg-gold-500/10 flex items-center justify-center text-gold-400 group-hover:bg-gold-500 group-hover:text-dark-900 transition-all duration-300">
                   <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z" /></svg>
                 </span>
                 <span class="text-sm border-b border-transparent group-hover:border-gold-400">{{ t('footer.address') }}</span>
              </a>
              
              <div class="group flex items-center lg:justify-end space-x-3">
                 <a :href="`tel:${t('footer.phone')}`" class="w-8 h-8 rounded-full bg-gold-500/10 flex items-center justify-center text-gold-400 group-hover:bg-gold-500 group-hover:text-dark-900 transition-all duration-300 cursor-pointer">
                   <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 0 0 2.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 0 1-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 0 0-1.091-.852H4.5A2.25 2.25 0 0 0 2.25 4.5v2.25Z" /></svg>
                 </a>
                 <a :href="`tel:${t('footer.phone')}`" class="text-sm hover:text-gold-400 transition-colors">{{ t('footer.phone') }}</a>
              </div>

              <div class="group flex items-center lg:justify-end space-x-3">
                 <a :href="`mailto:${t('footer.email')}`" class="w-8 h-8 rounded-full bg-gold-500/10 flex items-center justify-center text-gold-400 group-hover:bg-gold-500 group-hover:text-dark-900 transition-all duration-300 cursor-pointer">
                   <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75" /></svg>
                 </a>
                 <a :href="`mailto:${t('footer.email')}`" class="text-sm hover:text-gold-400 transition-colors">{{ t('footer.email') }}</a>
              </div>
            </div>
          </div>

        </div>
        
        <!-- Bottom Bar -->
        <div class="border-t border-white/5 pt-8 flex flex-col md:flex-row justify-between items-center text-xs text-gray-500">
           <p>{{ t('footer.rights') }}</p>
           <div class="flex space-x-6 mt-4 md:mt-0">
              <button @click="openLegalModal('privacy')" class="hover:text-gold-400 transition-colors">{{ t('footer.privacy') }}</button>
              <button @click="openLegalModal('terms')" class="hover:text-gold-400 transition-colors">{{ t('footer.terms') }}</button>
           </div>
        </div>
      </div>
    </footer>

    <!-- Global Accreditation Modal -->
    <StandardModal 
        :is-open="isModalOpen" 
        :title="t('form.title')" 
        theme="gold"
        force-dark
        max-width="max-w-[550px]"
        @close="closeModal"
    >
        <div class="space-y-6">
            <p class="text-gray-400 text-sm">
                {{ successMessage ? 'Спасибо за запрос! Мы свяжемся с вами в ближайшее время.' : t('form.subtitle') }}
            </p>
            
            <div v-if="successMessage" class="bg-green-500/10 border border-green-500/20 text-green-400 p-4 rounded-lg text-sm text-center">
                {{ successMessage }}
            </div>

            <div v-else class="space-y-6">
                <!-- Org Name -->
                <div class="space-y-1">
                    <div class="flex justify-between items-center">
                        <label class="text-xs uppercase tracking-widest text-gold-500 font-bold ml-1">{{ t('form.org_label') }}</label>
                        <span v-if="errors.name" class="text-red-500 text-[10px] font-bold uppercase tracking-wide">{{ errors.name }}</span>
                    </div>
                    <input 
                        ref="orgInput"
                        v-model="name"
                        type="text" 
                        autocomplete="off"
                        class="w-full bg-dark-900/50 border border-white/10 rounded-lg px-4 py-3 text-white placeholder-gray-600 focus:outline-none focus:border-gold-500 focus:ring-1 focus:ring-gold-500 transition-all duration-300 font-mono text-lg tracking-wide hover:border-white/20"
                        :class="errors.name ? 'border-red-500/50 focus:border-red-500 focus:ring-1 focus:ring-red-500' : ''"
                        :placeholder="t('form.org_placeholder')" 
                    />
                </div>

                <!-- Phone -->
                <div class="space-y-1">
                     <div class="flex justify-between items-center">
                        <label class="text-xs uppercase tracking-widest text-gold-500 font-bold ml-1">{{ t('form.phone_label') }}</label>
                        <span v-if="errors.phone" class="text-red-500 text-[10px] font-bold uppercase tracking-wide">{{ errors.phone }}</span>
                    </div>
                     <input 
                        v-model="phone"
                        type="text" 
                        autocomplete="off"
                        class="w-full bg-dark-900/50 border border-white/10 rounded-lg px-4 py-3 text-white placeholder-gray-600 focus:outline-none focus:border-gold-500 focus:ring-1 focus:ring-gold-500 transition-all duration-300 font-mono text-lg tracking-wide hover:border-white/20"
                        :class="errors.phone ? 'border-red-500/50 focus:border-red-500 focus:ring-1 focus:ring-red-500' : ''"
                        placeholder="+7 (999) 000-00-00" 
                     />
                </div>

                <!-- Email -->
                <div class="space-y-1">
                    <div class="flex justify-between items-center">
                        <label class="text-xs uppercase tracking-widest text-gold-500 font-bold ml-1">{{ t('form.email_label') }}</label>
                        <span v-if="errors.email" class="text-red-500 text-[10px] font-bold uppercase tracking-wide">{{ errors.email }}</span>
                    </div>
                    <input 
                        v-model="email"
                        type="email" 
                        autocomplete="off"
                        class="w-full bg-dark-900/50 border border-white/10 rounded-lg px-4 py-3 text-white placeholder-gray-600 focus:outline-none focus:border-gold-500 focus:ring-1 focus:ring-gold-500 transition-all duration-300 font-mono text-lg tracking-wide hover:border-white/20"
                        :class="errors.email ? 'border-red-500/50 focus:border-red-500 focus:ring-1 focus:ring-red-500' : ''"
                        placeholder="corp@atlas.gold" 
                    />
                </div>

                <div v-if="errors.general" class="text-red-500 text-xs text-center">
                    {{ errors.general }}
                </div>

                <BaseButton 
                    block 
                    variant="primary" 
                    class="mt-6 !py-4 shadow-[0_0_20px_rgba(212,175,55,0.3)] hover:shadow-[0_0_30px_rgba(212,175,55,0.5)]" 
                    :loading="isLoading"
                    @click="submitApplication"
                >
                    {{ t('form.submit') }}
                </BaseButton>
            </div>
        </div>
    </StandardModal>

    <!-- Legal Info Modal -->
    <LegalModal 
        :is-open="isLegalModalOpen" 
        :title="legalTitle" 
        :content="legalContent" 
        @close="isLegalModalOpen = false" 
    />
  </div>
</template>

<style scoped>
@keyframes shine {
    0% { transform: translateX(-200%) skewX(-15deg); }
    100% { transform: translateX(200%) skewX(-15deg); }
}
.shine-effect {
    animation: shine 3s infinite;
}
.group:hover .shine-effect {
    animation-duration: 1.5s;
}
</style>
