<script setup>
import { computed } from 'vue'
import { useAuthStore } from '../../stores/auth'

const authStore = useAuthStore()
const user = computed(() => authStore.user)
</script>

<template>
  <div class="max-w-4xl mx-auto space-y-8">
      <div class="flex items-center justify-between">
          <h2 class="text-2xl font-kanit font-bold text-white">Настройки профиля</h2>
          <span 
            class="px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wider border"
            :class="user?.is_accredited ? 'bg-green-500/10 text-green-400 border-green-500/30' : 'bg-gray-800 text-gray-400 border-gray-700'"
          >
              {{ user?.is_accredited ? 'Аккаунт верифицирован' : 'Требуется верификация' }}
          </span>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
          <!-- Left Column: Avatar & Basic Info -->
          <div class="md:col-span-1 space-y-6">
              <div class="bg-dark-800/80 backdrop-blur-sm rounded-xl p-6 border border-white/5 text-center">
                  <div class="relative w-32 h-32 mx-auto mb-4">
                      <div class="absolute inset-0 bg-gold-500 rounded-full blur-lg opacity-20"></div>
                      <img :src="user?.avatar" class="relative w-full h-full rounded-full border-2 border-gold-500/30 p-1" />
                      <button class="absolute bottom-0 right-0 bg-dark-900 border border-white/10 rounded-full p-2 hover:bg-gold-500 hover:text-black transition-colors">
                          <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                      </button>
                  </div>
                  <h3 class="text-xl font-bold text-white">{{ user?.name || 'Клиент' }}</h3>
                  <p class="text-gray-500 text-sm">Участник с 2024 года</p>
              </div>
          </div>

          <!-- Right Column: Form -->
          <div class="md:col-span-2 space-y-6">
              <div class="bg-dark-800/80 backdrop-blur-sm rounded-xl p-8 border border-white/5">
                  <h3 class="text-lg font-kanit font-bold text-white mb-6 border-b border-white/5 pb-4">Личные данные</h3>
                  
                  <div class="grid grid-cols-1 gap-6">
                      <div class="space-y-2">
                          <label class="text-xs uppercase tracking-widest text-gray-500 font-bold">ФИО / Наименование</label>
                          <input type="text" :value="user?.name" class="w-full bg-dark-900 border border-white/10 rounded-lg px-4 py-3 text-white focus:border-gold-500 focus:outline-none transition-colors" />
                      </div>

                      <div class="space-y-2">
                          <label class="text-xs uppercase tracking-widest text-gray-500 font-bold">Номер телефона</label>
                          <input type="text" :value="user?.phone" disabled class="w-full bg-dark-900/50 border border-white/5 rounded-lg px-4 py-3 text-gray-400 cursor-not-allowed" />
                          <p class="text-xs text-gray-600">Номер телефона используется для входа и не может быть изменен самостоятельно.</p>
                      </div>

                      <div class="space-y-2">
                          <label class="text-xs uppercase tracking-widest text-gray-500 font-bold">Email (для уведомлений)</label>
                          <input type="email" :value="user?.email" class="w-full bg-dark-900 border border-white/10 rounded-lg px-4 py-3 text-white focus:border-gold-500 focus:outline-none transition-colors" />
                      </div>
                  </div>

                  <div class="mt-8 flex justify-end">
                      <button class="px-8 py-3 bg-gold-500 hover:bg-gold-400 text-black font-bold uppercase tracking-widest rounded-lg transition-colors shadow-[0_0_20px_rgba(212,175,55,0.2)]">
                          Сохранить
                      </button>
                  </div>
              </div>

               <!-- Security -->
              <div class="bg-dark-800/80 backdrop-blur-sm rounded-xl p-8 border border-white/5">
                  <h3 class="text-lg font-kanit font-bold text-white mb-4">Безопасность</h3>
                  <div class="flex items-center justify-between">
                      <div>
                          <p class="text-white font-medium">Двухфакторная аутентификация</p>
                          <p class="text-sm text-gray-500">Дополнительная защита при входе</p>
                      </div>
                      <button class="w-12 h-6 bg-dark-900 rounded-full border border-white/10 relative">
                          <span class="absolute left-1 top-1 w-4 h-4 bg-gray-500 rounded-full transition-all"></span>
                      </button>
                  </div>
              </div>
          </div>
      </div>
  </div>
</template>
