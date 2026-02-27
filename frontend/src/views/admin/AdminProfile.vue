<script setup>
import { computed } from 'vue'
import { useAuthStore } from '../../stores/auth'
import { useTheme } from '../../composables/useTheme'
import { Switch } from '@headlessui/vue'
import { MoonIcon, SunIcon } from '@heroicons/vue/24/solid'

const authStore = useAuthStore()
const { theme, toggleTheme } = useTheme()
const user = computed(() => authStore.adminUser)

const staffAvatar = computed(() => {
    if (!user.value?.name) return ''
    return `https://ui-avatars.com/api/?name=${encodeURIComponent(user.value.name)}&background=dc2626&color=fff`
})

const roleName = computed(() => {
    const roles = {
        super_admin: 'Суперадминистратор',
        admin: 'Администратор',
        moderator: 'Модератор',
    }
    return roles[user.value?.role] || user.value?.role || '—'
})
</script>

<template>
  <div class="max-w-5xl mx-auto">
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 items-start">
          
          <!-- Left Column (Info) -->
          <div class="lg:col-span-2 space-y-8">
              <!-- Info card (read-only) -->
              <div class="bg-white dark:bg-dark-800/80 backdrop-blur-sm rounded-xl p-8 border border-gray-200 dark:border-white/5 shadow-sm">
                  <h3 class="text-lg font-kanit font-bold text-gray-900 dark:text-white mb-6 border-b border-gray-200 dark:border-white/5 pb-4">Данные сотрудника</h3>
                  
                  <div class="space-y-5">
                      <div class="space-y-1.5">
                          <label class="text-[10px] uppercase tracking-widest text-gray-500 dark:text-gray-400 font-bold">Имя</label>
                          <div class="w-full bg-gray-100 dark:bg-dark-900/50 border border-gray-200 dark:border-white/5 rounded-lg px-4 py-3 text-gray-900 dark:text-gray-100 cursor-default">
                              {{ user?.name || '—' }}
                          </div>
                      </div>

                      <div class="space-y-1.5">
                          <label class="text-[10px] uppercase tracking-widest text-gray-500 dark:text-gray-400 font-bold">Email</label>
                          <div class="w-full bg-gray-100 dark:bg-dark-900/50 border border-gray-200 dark:border-white/5 rounded-lg px-4 py-3 text-gray-900 dark:text-gray-100 font-mono cursor-default">
                              {{ user?.email || '—' }}
                          </div>
                      </div>

                      <div class="space-y-1.5">
                          <label class="text-[10px] uppercase tracking-widest text-gray-500 dark:text-gray-400 font-bold">Роль</label>
                          <div class="w-full bg-gray-100 dark:bg-dark-900/50 border border-gray-200 dark:border-white/5 rounded-lg px-4 py-3 cursor-default">
                              <span class="text-red-500 dark:text-red-400 font-bold">{{ roleName }}</span>
                          </div>
                      </div>
                  </div>
              </div>

              <!-- Appearance Settings -->
              <div class="bg-white dark:bg-dark-800/80 backdrop-blur-sm rounded-xl p-8 border border-gray-200 dark:border-white/5 shadow-sm">
                  <h3 class="text-lg font-kanit font-bold text-gray-900 dark:text-white mb-6 border-b border-gray-200 dark:border-white/5 pb-4">Внешний вид</h3>
                  
                  <div class="flex items-center justify-between">
                      <div>
                          <div class="font-bold text-gray-900 dark:text-gray-100">Тема оформления</div>
                          <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Выберите светлую или тёмную тему интерфейса</div>
                      </div>
                      
                      <Switch
                          :model-value="theme === 'dark'"
                          @update:model-value="toggleTheme"
                          :class="theme === 'dark' ? 'bg-red-500' : 'bg-gray-200'"
                          class="relative inline-flex h-8 w-14 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus-visible:ring-2 focus-visible:ring-red-500 focus-visible:ring-opacity-75"
                      >
                          <span class="sr-only">Переключить тему</span>
                          <span
                              aria-hidden="true"
                              :class="theme === 'dark' ? 'translate-x-6' : 'translate-x-0'"
                              class="pointer-events-none inline-block h-7 w-7 transform rounded-full bg-white shadow-lg ring-0 transition duration-200 ease-in-out flex items-center justify-center"
                          >
                                <MoonIcon v-if="theme === 'dark'" class="w-4 h-4 text-red-600" />
                                <SunIcon v-else class="w-4 h-4 text-orange-400" />
                          </span>
                      </Switch>
                  </div>
              </div>
          </div>

          <!-- Right Column (Avatar & Name) -->
          <div class="lg:col-span-1">
              <div class="bg-white dark:bg-dark-800/80 backdrop-blur-sm rounded-xl p-8 border border-gray-200 dark:border-white/5 shadow-sm text-center">
                  <div class="flex flex-col items-center">
                      <!-- Avatar -->
                      <div class="relative flex-shrink-0 mb-6">
                          <div class="relative w-32 h-32 mx-auto">
                              <div class="absolute inset-0 rounded-full blur-lg opacity-20 bg-red-500"></div>
                              <img :src="staffAvatar" class="relative w-full h-full rounded-full border-4 border-red-500/30 object-cover" />
                          </div>
                      </div>

                      <!-- Name + Role -->
                      <div class="w-full">
                          <h3 class="text-xl font-bold text-gray-900 dark:text-gray-100 mb-2 leading-tight">{{ user?.name || 'Сотрудник' }}</h3>
                          <p class="text-[11px] text-red-500 dark:text-red-400 uppercase tracking-widest font-bold mb-5">{{ roleName }}</p>
                          <div class="bg-gray-50 dark:bg-white/5 rounded-lg p-4 w-full">
                              <p class="text-[11px] text-gray-500 dark:text-gray-400 leading-relaxed text-center">
                                  Для изменения данных обратитесь к суперадминистратору системы.
                              </p>
                          </div>
                      </div>
                  </div>
              </div>
          </div>

      </div>
  </div>
</template>
