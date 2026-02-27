<script setup>
import { ref, computed } from 'vue'
import { useAuthStore } from '../../stores/auth'
import { useTheme } from '../../composables/useTheme'
import { Switch } from '@headlessui/vue'
import { MoonIcon, SunIcon } from '@heroicons/vue/24/solid'
import axios from 'axios'

const authStore = useAuthStore()
const { theme, toggleTheme } = useTheme()
const user = computed(() => authStore.adminUser)

const fileInput = ref(null)
const isUploading = ref(false)
const uploadError = ref('')

const staffAvatar = computed(() => {
    if (user.value?.avatar && !user.value.avatar.includes('ui-avatars.com')) {
        return user.value.avatar
    }
    if (!user.value?.name) return ''
    return `https://ui-avatars.com/api/?name=${encodeURIComponent(user.value.name)}&background=dc2626&color=fff`
})

const hasCustomAvatar = computed(() => {
    return user.value?.avatar && !user.value.avatar.includes('ui-avatars.com')
})

const roleName = computed(() => {
    const roles = {
        super_admin: 'Суперадминистратор',
        admin: 'Администратор',
        moderator: 'Модератор',
    }
    return roles[user.value?.role] || user.value?.role || '—'
})

const triggerFileInput = () => {
    fileInput.value?.click()
}

const handleFileChange = async (e) => {
    const file = e.target.files?.[0]
    if (!file) return

    // Validate file type
    if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) {
        uploadError.value = 'Допустимые форматы: JPG, PNG, WebP'
        return
    }

    // Validate file size (max 2MB)
    if (file.size > 2 * 1024 * 1024) {
        uploadError.value = 'Максимальный размер файла: 2 МБ'
        return
    }

    uploadError.value = ''
    isUploading.value = true

    try {
        const formData = new FormData()
        formData.append('avatar', file)

        const { data } = await axios.post('/api/admin/avatar', formData, {
            headers: { 'Content-Type': 'multipart/form-data' }
        })

        // Update the store
        if (authStore.adminUser) {
            authStore.adminUser.avatar = data.avatar
            localStorage.setItem('admin_user', JSON.stringify(authStore.adminUser))
        }
    } catch (err) {
        uploadError.value = err.response?.data?.message || 'Ошибка загрузки'
    } finally {
        isUploading.value = false
        // Reset file input
        if (fileInput.value) fileInput.value.value = ''
    }
}

const removeAvatar = async () => {
    isUploading.value = true
    uploadError.value = ''
    try {
        await axios.delete('/api/admin/avatar')
        if (authStore.adminUser) {
            authStore.adminUser.avatar = null
            localStorage.setItem('admin_user', JSON.stringify(authStore.adminUser))
        }
    } catch (err) {
        // If delete endpoint doesn't exist, just clear locally
        if (authStore.adminUser) {
            authStore.adminUser.avatar = null
            localStorage.setItem('admin_user', JSON.stringify(authStore.adminUser))
        }
    } finally {
        isUploading.value = false
    }
}
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
                      <!-- Avatar with upload -->
                      <div class="relative flex-shrink-0 mb-6">
                          <div class="relative w-32 h-32 mx-auto group cursor-pointer" @click="triggerFileInput">
                              <div class="absolute inset-0 rounded-full blur-lg opacity-20 bg-red-500"></div>
                              <img :src="staffAvatar" class="relative w-full h-full rounded-full border-4 border-red-500/30 object-cover" />
                              
                              <!-- Hover overlay -->
                              <div class="absolute inset-0 rounded-full bg-black/50 opacity-0 group-hover:opacity-100 transition-opacity duration-200 flex items-center justify-center">
                                  <div v-if="isUploading" class="flex flex-col items-center gap-1">
                                      <svg class="w-6 h-6 text-white animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                  </div>
                                  <div v-else class="flex flex-col items-center gap-1">
                                      <svg class="w-6 h-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                      <span class="text-white text-[10px] font-bold uppercase tracking-wider">Изменить</span>
                                  </div>
                              </div>
                          </div>
                          
                          <!-- Remove button (only when custom avatar) -->
                          <button
                              v-if="hasCustomAvatar && !isUploading"
                              @click.stop="removeAvatar"
                              class="absolute -bottom-1 -right-1 w-7 h-7 bg-red-500 hover:bg-red-600 text-white rounded-full flex items-center justify-center shadow-md transition-colors z-10"
                              title="Удалить фото"
                          >
                              <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                          </button>
                      </div>

                      <!-- Hidden file input -->
                      <input
                          ref="fileInput"
                          type="file"
                          accept="image/jpeg,image/png,image/webp"
                          class="hidden"
                          @change="handleFileChange"
                      />

                      <!-- Upload error -->
                      <p v-if="uploadError" class="text-xs text-red-500 mb-3">{{ uploadError }}</p>

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
