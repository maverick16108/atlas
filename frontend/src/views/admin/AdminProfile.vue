<script setup>
import { computed } from 'vue'
import { useAuthStore } from '../../stores/auth'

const authStore = useAuthStore()
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
              <div class="bg-dark-800/80 backdrop-blur-sm rounded-xl p-8 border border-white/5">
                  <h3 class="text-lg font-kanit font-bold text-white mb-6 border-b border-white/5 pb-4">Данные сотрудника</h3>
                  
                  <div class="space-y-5">
                      <div class="space-y-1.5">
                          <label class="text-[10px] uppercase tracking-widest text-gray-400 font-bold">Имя</label>
                          <div class="w-full bg-dark-900/50 border border-white/5 rounded-lg px-4 py-3 text-gray-100 cursor-default">
                              {{ user?.name || '—' }}
                          </div>
                      </div>

                      <div class="space-y-1.5">
                          <label class="text-[10px] uppercase tracking-widest text-gray-400 font-bold">Email</label>
                          <div class="w-full bg-dark-900/50 border border-white/5 rounded-lg px-4 py-3 text-gray-100 font-mono cursor-default">
                              {{ user?.email || '—' }}
                          </div>
                      </div>

                      <div class="space-y-1.5">
                          <label class="text-[10px] uppercase tracking-widest text-gray-400 font-bold">Роль</label>
                          <div class="w-full bg-dark-900/50 border border-white/5 rounded-lg px-4 py-3 cursor-default">
                              <span class="text-red-400 font-bold">{{ roleName }}</span>
                          </div>
                      </div>
                  </div>
              </div>
          </div>

          <!-- Right Column (Avatar & Name) -->
          <div class="lg:col-span-1">
              <div class="bg-dark-800/80 backdrop-blur-sm rounded-xl p-8 border border-white/5 text-center">
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
                          <h3 class="text-xl font-bold text-gray-100 mb-2 leading-tight">{{ user?.name || 'Сотрудник' }}</h3>
                          <p class="text-[11px] text-red-400 uppercase tracking-widest font-bold mb-5">{{ roleName }}</p>
                          <div class="bg-white/5 rounded-lg p-4 w-full">
                              <p class="text-[11px] text-gray-400 leading-relaxed text-center">
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
