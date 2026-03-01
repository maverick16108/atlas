<script setup>
import { ref, computed } from 'vue'
import { useAuthStore } from '../../stores/auth'
import { useTheme } from '../../composables/useTheme'
import { Switch } from '@headlessui/vue'
import axios from 'axios'
import { MoonIcon, SunIcon } from '@heroicons/vue/24/solid'

const authStore = useAuthStore()
const { theme, toggleTheme } = useTheme()
const user = computed(() => authStore.clientUser)

const isUploading = ref(false)
const uploadSuccess = ref(false)
const uploadError = ref('')
const avatarPreview = ref(null)
const isDragging = ref(false)

const avatarUrl = computed(() => {
    if (avatarPreview.value) return avatarPreview.value
    if (user.value?.avatar) return user.value.avatar
    const bg = user.value?.is_gpb ? '3B82F6' : '4ADE80'
    return `https://ui-avatars.com/api/?name=${encodeURIComponent(user.value?.name || 'U')}&background=${bg}&color=fff`
})

const hasCustomAvatar = computed(() => {
    return user.value?.avatar && !user.value.avatar.includes('ui-avatars.com')
})

const processFile = (file) => {
    if (!file) return

    if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) {
        uploadError.value = 'Допустимые форматы: JPG, PNG, WebP'
        return
    }
    if (file.size > 2 * 1024 * 1024) {
        uploadError.value = 'Максимальный размер файла: 2 МБ'
        return
    }

    uploadError.value = ''
    avatarPreview.value = URL.createObjectURL(file)
    uploadAvatar(file)
}

const handleAvatarSelect = (event) => {
    processFile(event.target.files[0])
}

const handleDrop = (event) => {
    isDragging.value = false
    const file = event.dataTransfer?.files?.[0]
    processFile(file)
}

const handleDragOver = (event) => {
    isDragging.value = true
}

const handleDragLeave = () => {
    isDragging.value = false
}

const uploadAvatar = async (file) => {
    isUploading.value = true
    uploadSuccess.value = false

    const formData = new FormData()
    formData.append('avatar', file)

    try {
        const response = await axios.post('/api/client/avatar', formData, {
            headers: { 'Content-Type': 'multipart/form-data' }
        })

        if (response.data.avatar) {
            authStore.updateClientUser({ avatar: response.data.avatar })
        }
        uploadSuccess.value = true
        setTimeout(() => { uploadSuccess.value = false }, 3000)
    } catch (e) {
        uploadError.value = e.response?.data?.message || 'Ошибка загрузки'
        avatarPreview.value = null
    } finally {
        isUploading.value = false
    }
}

const removeAvatar = async () => {
    isUploading.value = true
    uploadError.value = ''
    try {
        await axios.delete('/api/client/avatar')
        authStore.updateClientUser({ avatar: null })
        avatarPreview.value = null
    } catch (err) {
        uploadError.value = 'Ошибка удаления аватара'
    } finally {
        isUploading.value = false
    }
}
</script>

<template>
  <div class="max-w-5xl mx-auto">
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 items-start">
          
          <!-- Left Column (Settings & Info) -->
          <div class="order-2 lg:order-1 lg:col-span-2 space-y-8">
              <!-- Info card (read-only) -->
              <div class="bg-white dark:bg-stone-900/80 backdrop-blur-sm rounded-xl p-8 border border-gray-200 dark:border-white/5 shadow-sm">
                  <h3 class="text-lg font-kanit font-bold text-gray-900 dark:text-gray-100 mb-6 border-b border-gray-200 dark:border-white/5 pb-4">Данные участника</h3>
                  
                  <div class="space-y-5">
                      <div class="space-y-1.5">
                          <label class="text-[10px] uppercase tracking-widest text-gray-500 dark:text-gray-400 font-bold">Наименование</label>
                          <div class="w-full bg-gray-100 dark:bg-stone-800/50 border border-gray-200 dark:border-white/5 rounded-lg px-4 py-3 text-gray-900 dark:text-gray-100 cursor-default">
                              {{ user?.name || '—' }}
                          </div>
                      </div>

                      <div class="space-y-1.5">
                          <label class="text-[10px] uppercase tracking-widest text-gray-500 dark:text-gray-400 font-bold">Телефон для авторизации</label>
                          <div class="w-full bg-gray-100 dark:bg-stone-800/50 border border-gray-200 dark:border-white/5 rounded-lg px-4 py-3 text-gray-900 dark:text-gray-100 font-mono cursor-default">
                              {{ user?.auth_phone || user?.phone || '—' }}
                          </div>
                          <p class="text-xs text-gray-400 dark:text-gray-500">Номер используется для входа в систему и не может быть изменён самостоятельно.</p>
                      </div>

                      <div v-if="user?.email" class="space-y-1.5">
                          <label class="text-[10px] uppercase tracking-widest text-gray-500 dark:text-gray-400 font-bold">Email (контактный)</label>
                          <div class="w-full bg-gray-100 dark:bg-stone-800/50 border border-gray-200 dark:border-white/5 rounded-lg px-4 py-3 text-gray-900 dark:text-gray-100 cursor-default">
                              {{ user?.email }}
                          </div>
                      </div>

                      <div v-if="user?.delivery_basis && Number(user.delivery_basis) > 0" class="space-y-1.5">
                          <label class="text-[10px] uppercase tracking-widest text-gray-500 dark:text-gray-400 font-bold">Базис поставки (%)</label>
                          <div class="w-full bg-gray-100 dark:bg-stone-800/50 border border-gray-200 dark:border-white/5 rounded-lg px-4 py-3 text-gold-500 font-mono font-bold cursor-default">
                              {{ Number(user.delivery_basis) }}%
                          </div>
                      </div>
                  </div>
              </div>

              <!-- Appearance Settings -->
              <div class="bg-white dark:bg-stone-900/80 backdrop-blur-sm rounded-xl p-8 border border-gray-200 dark:border-white/5 shadow-sm">
                  <h3 class="text-lg font-kanit font-bold text-gray-900 dark:text-gray-100 mb-6 border-b border-gray-200 dark:border-white/5 pb-4">Внешний вид</h3>
                  
                  <div class="flex items-center justify-between">
                      <div>
                          <div class="font-bold text-gray-900 dark:text-gray-100">Тема оформления</div>
                          <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Выберите светлую или темную тему интерфейса</div>
                      </div>
                      
                      <Switch
                          :model-value="theme === 'dark'"
                          @update:model-value="toggleTheme"
                          :class="theme === 'dark' ? 'bg-gold-500' : 'bg-gray-200'"
                          class="relative inline-flex h-8 w-14 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus-visible:ring-2 focus-visible:ring-gold-500 focus-visible:ring-opacity-75"
                      >
                          <span class="sr-only">Use setting</span>
                          <span
                              aria-hidden="true"
                              :class="theme === 'dark' ? 'translate-x-6' : 'translate-x-0'"
                              class="pointer-events-none inline-block h-7 w-7 transform rounded-full bg-white shadow-lg ring-0 transition duration-200 ease-in-out flex items-center justify-center"
                          >
                                <MoonIcon v-if="theme === 'dark'" class="w-4 h-4 text-gold-600" />
                                <SunIcon v-else class="w-4 h-4 text-orange-400" />
                          </span>
                      </Switch>
                  </div>
              </div>
          </div>

          <!-- Right Column (Avatar & Name) -->
          <div class="order-1 lg:order-2 lg:col-span-1">
              <div class="bg-white dark:bg-stone-900/80 backdrop-blur-sm rounded-xl p-8 border border-gray-200 dark:border-white/5 shadow-sm text-center">
                  <div class="flex flex-col items-center">
                      <!-- Avatar with upload + drag-and-drop -->
                      <div class="relative group flex-shrink-0 mb-6"
                           @drop.prevent="handleDrop"
                           @dragover.prevent="handleDragOver"
                           @dragleave.prevent="handleDragLeave">
                          <div class="relative w-32 h-32 mx-auto cursor-pointer" @click="$refs.fileInput.click()">
                              <div class="absolute inset-0 rounded-full blur-lg opacity-20 group-hover:opacity-30 transition-opacity"
                                   :class="isDragging ? 'opacity-40 bg-gold-500' : user?.is_gpb ? 'bg-blue-500' : 'bg-emerald-500'"></div>
                              <img :src="avatarUrl" class="relative w-full h-full rounded-full border-4 object-cover transition-all"
                                   :class="[
                                       isDragging ? 'border-gold-500/60 scale-105' : user?.is_gpb ? 'border-blue-500/30' : 'border-emerald-500/30'
                                   ]" />
                              
                              <!-- Hover / drag overlay -->
                              <div class="absolute inset-0 flex items-center justify-center rounded-full bg-black/60 transition-opacity duration-200"
                                   :class="isDragging ? 'opacity-100' : 'opacity-0 group-hover:opacity-100'">
                                  <div v-if="isUploading" class="w-6 h-6 border-2 border-white border-t-transparent rounded-full animate-spin"></div>
                                  <div v-else class="flex flex-col items-center gap-1">
                                      <svg class="w-6 h-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" />
                                      </svg>
                                      <span class="text-white text-[10px] font-bold uppercase tracking-wider">{{ isDragging ? 'Отпустите' : 'Изменить' }}</span>
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

                          <!-- Success indicator -->
                          <transition enter-active-class="transition ease-out duration-200" enter-from-class="opacity-0 scale-75" enter-to-class="opacity-100 scale-100"
                                      leave-active-class="transition ease-in duration-150" leave-from-class="opacity-100" leave-to-class="opacity-0">
                              <div v-if="uploadSuccess" class="absolute bottom-0 right-2 z-20 w-8 h-8 bg-green-500 rounded-full flex items-center justify-center border-4 border-white dark:border-stone-900">
                                  <svg class="w-4 h-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" /></svg>
                              </div>
                          </transition>
                      </div>

                      <!-- Hidden file input -->
                      <input
                          ref="fileInput"
                          type="file"
                          accept="image/jpeg,image/png,image/webp"
                          class="hidden"
                          @change="handleAvatarSelect"
                      />

                      <!-- Upload error -->
                      <p v-if="uploadError" class="text-xs text-red-500 mb-3">{{ uploadError }}</p>

                      <!-- Name + hint -->
                      <div class="w-full">
                          <h3 class="text-xl font-bold text-gray-900 dark:text-gray-100 mb-2 leading-tight">{{ user?.name || 'Участник' }}</h3>
                          <p v-if="user?.is_gpb" class="text-[11px] text-blue-400 uppercase tracking-widest font-bold mb-5">ГПБ</p>
                          <p v-else class="text-[11px] text-emerald-500 uppercase tracking-widest font-bold mb-5">{{ user?.is_accredited ? 'Аккредитован' : 'КЛИЕНТ' }}</p>
                          <div class="bg-gray-50 dark:bg-white/5 rounded-lg p-4 w-full">
                              <p class="text-[11px] text-gray-500 dark:text-gray-400 leading-relaxed text-center">
                                  Нажмите на фотографию или перетащите файл, чтобы загрузить новый аватар профиля.
                              </p>
                          </div>
                      </div>
                  </div>
              </div>
          </div>

      </div>
  </div>
</template>
