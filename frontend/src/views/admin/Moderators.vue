<script setup>
import { ref, computed, watch, onMounted, onUnmounted, nextTick } from 'vue'
import { onBeforeRouteLeave, useRouter } from 'vue-router'
import { XMarkIcon } from '@heroicons/vue/24/outline' 
import StandardModal from '../../components/ui/StandardModal.vue'
import axios from 'axios'

// --- State ---
const moderators = ref([]) // Displayed list (accumulated)
const totalModerators = ref(0)
const searchQuery = ref('')
const sortKey = ref('name')
const sortOrder = ref('asc') // asc, desc
const page = ref(1)
const perPage = 50
const isLoading = ref(false)
const hasMore = ref(true)
const loadingType = ref('initial') // initial, search, scroll

// --- Edit State ---
const isEditing = ref(false)
const editingId = ref(null)

// --- Logic ---
const showModal = ref(false)
const showConfirmModal = ref(false)
const deleteId = ref(null)
const selectedMod = ref(null) // For delete modal name display
const newMod = ref({ name: '', email: '', password: '' })
const errors = ref({ name: '', email: '', password: '' })
const showPassword = ref(false)

// --- API Interactions ---

// Load Data
const loadMore = async (reset = false) => {
    if (!reset && (isLoading.value || !hasMore.value)) return
    
    // Determine loading type
    if (reset) {
        if (moderators.value.length > 0) loadingType.value = 'search'
        else loadingType.value = 'initial'
        page.value = 1
        hasMore.value = true
    } else {
        loadingType.value = 'scroll'
    }
    
    isLoading.value = true
    
    try {
        const response = await axios.get('/api/moderators', {
            params: {
                page: page.value,
                per_page: perPage,
                search: searchQuery.value,
                sort_key: sortKey.value,
                sort_order: sortOrder.value
            }
        })
        
        const data = response.data.data
        const meta = response.data.meta || response.data // Handle Laravel pagination (sometimes top level)
        // Check standard Laravel Paginated Resource structure
        // Usually: { data: [...], links: {...}, meta: {...} } OR just { data: [...], ... }
        
        // Map backend fields to frontend model
        const mappedData = data.map(u => ({
            id: u.id,
            name: u.name,
            email: u.email,
            last_login: u.last_login_at ? new Date(u.last_login_at).toLocaleString('ru-RU', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' }) : '-',
            joined: new Date(u.created_at).toLocaleDateString('ru-RU'),
            status: 'active' // Field not in DB
        }))

        if (reset) {
            moderators.value = mappedData
        } else {
            moderators.value.push(...mappedData)
        }
        
        // Check if more pages exist
        if (response.data.next_page_url) {
            page.value++
            hasMore.value = true
        } else {
            hasMore.value = false
        }

    } catch (e) {
        console.error('Failed to load moderators:', e)
    } finally {
        isLoading.value = false
        loadingType.value = 'initial'
    }
}

// Reset List
const resetList = () => {
    loadMore(true)
}

// Accessors for Template
const sortBy = (key) => {
    // Map frontend keys to backend keys
    let backendKey = key
    if (key === 'joined') backendKey = 'created_at'
    if (key === 'last_login') backendKey = 'last_login_at'
    
    if (sortKey.value === backendKey) {
        sortOrder.value = sortOrder.value === 'asc' ? 'desc' : 'asc'
    } else {
        sortKey.value = backendKey
        sortOrder.value = 'asc'
    }
    resetList()
}

watch([searchQuery], () => {
    // Debounce could be added here, but for now direct call
    resetList()
})

const saveModerator = async () => {
    // Reset Errors
    errors.value = { name: '', email: '', password: '' }
    let isValid = true

    // Validate Name
    if (!newMod.value.name) {
        errors.value.name = 'Имя обязательно'
        isValid = false
    } else if (newMod.value.name.length < 2) {
        errors.value.name = 'Минимум 2 символа'
        isValid = false
    }

    // Validate Email
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/
    if (!newMod.value.email) {
        errors.value.email = 'Email обязателен'
        isValid = false
    } else if (!emailRegex.test(newMod.value.email)) {
        errors.value.email = 'Некорректный формат Email'
        isValid = false
    }

    // Validate Password
    const pass = newMod.value.password || ''
    if (!isEditing.value || (isEditing.value && pass)) {
        if (!pass) {
            errors.value.password = 'Пароль обязателен'
            isValid = false
        } else if (pass.length < 8) {
            errors.value.password = 'Минимум 8 символов'
            isValid = false
        } else if (!/[A-Z]/.test(pass)) {
            errors.value.password = 'Нужна заглавная буква'
            isValid = false
        } else if (!/\d/.test(pass)) {
            errors.value.password = 'Нужна хотя бы одна цифра'
            isValid = false
        } else if (!/[!@#$%^&*(),.?":{}|<>]/.test(pass)) {
            errors.value.password = 'Нужен спецсимвол (!@#...)'
            isValid = false
        }
    }

    if (!isValid) return

    try {
        if (isEditing.value) {
            await axios.put(`/api/moderators/${editingId.value}`, {
                name: newMod.value.name,
                email: newMod.value.email,
                password: newMod.value.password || undefined
            })
        } else {
            await axios.post('/api/moderators', {
                name: newMod.value.name,
                email: newMod.value.email,
                password: newMod.value.password
            })
        }
        closeModal(true) // Force close on success
        resetList()
    } catch (e) {
        if (e.response && e.response.status === 422) {
            const backendErrors = e.response.data.errors
            if (backendErrors.email) errors.value.email = backendErrors.email[0]
            if (backendErrors.password) errors.value.password = backendErrors.password[0]
            if (backendErrors.name) errors.value.name = backendErrors.name[0]
        } else {
            console.error('Save failed:', e)
            alert('Ошибка сохранения: ' + (e.response?.data?.message || e.message))
        }
    }
}

const initiateDelete = (id) => {
    deleteId.value = id
    selectedMod.value = moderators.value.find(m => m.id === id)
    showConfirmModal.value = true
}

const confirmDelete = async () => {
    if (deleteId.value) {
        try {
            await axios.delete(`/api/moderators/${deleteId.value}`)
            resetList()
        } catch (e) {
            console.error('Delete failed:', e)
        }
    }
    deleteId.value = null
    selectedMod.value = null
    showConfirmModal.value = false
}

// --- Interaction ---
const nameInputRef = ref(null)

const showUnsavedModal = ref(false)
const initialModState = ref(null)
const router = useRouter()
const pendingRoute = ref(null)

const confirmDiscardChanges = () => {
    showUnsavedModal.value = false
    showModal.value = false
    showConfirmModal.value = false
    deleteId.value = null
    selectedMod.value = null
    isEditing.value = false
    editingId.value = null
    
    if (pendingRoute.value) {
        router.push(pendingRoute.value)
        pendingRoute.value = null
    }
}

const openModal = async () => {
    newMod.value = { name: '', email: '', password: '' }
    errors.value = { name: '', email: '', password: '' }
    showPassword.value = false
    
    initialModState.value = JSON.parse(JSON.stringify(newMod.value))
    
    showModal.value = true
    isEditing.value = false
    await nextTick()
    if (nameInputRef.value) nameInputRef.value.focus()
}

const openEdit = async (mod) => {
    isEditing.value = true
    editingId.value = mod.id
    newMod.value = { 
        name: mod.name, 
        email: mod.email, 
        password: '' 
    }
    errors.value = { name: '', email: '', password: '' }
    showPassword.value = false
    
    initialModState.value = JSON.parse(JSON.stringify(newMod.value))
    
    showModal.value = true
    await nextTick()
    if (nameInputRef.value) nameInputRef.value.focus()
}

const closeModal = (force = false) => {
    if (typeof force !== 'boolean') force = false

    if (!force && showModal.value && JSON.stringify(newMod.value) !== JSON.stringify(initialModState.value)) {
        showUnsavedModal.value = true
        return
    }

    showModal.value = false
    showConfirmModal.value = false
    showUnsavedModal.value = false
    isEditing.value = false
    editingId.value = null
    selectedMod.value = null
}

// Intersection Observer
const observerTarget = ref(null)
const searchInputRef = ref(null)

const clearSearchByEsc = (e) => {
    searchQuery.value = ''
    resetList()
    e.preventDefault()
    if (document.activeElement === searchInputRef.value) {
        searchInputRef.value.blur()
    }
}

const handleGlobalKeydown = (e) => {
    if (e.key === 'Escape') {
        if (showUnsavedModal.value) {
            showUnsavedModal.value = false
            return
        }
        if (showModal.value || showConfirmModal.value) {
            closeModal()
            return
        }
        if (searchQuery.value) {
            clearSearchByEsc(e)
            return
        }
    }
    
    if (e.key === 'Insert' && !showModal.value && !showConfirmModal.value) {
        openModal()
        e.preventDefault()
        return
    }
    
    if (showModal.value || showConfirmModal.value || isEditing.value) return
    if (e.ctrlKey || e.metaKey || e.altKey || e.shiftKey) return
    if (['INPUT', 'TEXTAREA'].includes(document.activeElement.tagName)) return
    
    if (e.key.length === 1) {
        if (searchInputRef.value && document.activeElement !== searchInputRef.value) {
            searchInputRef.value.focus()
        }
    }
}

const handleEnterKey = (e) => {
    if (e.key !== 'Enter') return
    if (showConfirmModal.value) {
        confirmDelete()
        e.preventDefault()
    } else if (showModal.value) {
        saveModerator()
        e.preventDefault()
    }
}

onMounted(() => {
    resetList()
    window.addEventListener('keydown', handleGlobalKeydown)
    window.addEventListener('keydown', handleEnterKey)
    
    const observer = new IntersectionObserver((entries) => {
        if (entries[0].isIntersecting && !isLoading.value && hasMore.value) {
            loadMore()
        }
    }, { threshold: 0.1 })
    
    if (observerTarget.value) {
        observer.observe(observerTarget.value)
    }
    
    watch(observerTarget, (el) => {
        if (el) observer.observe(el)
    })
})

onUnmounted(() => {
    window.removeEventListener('keydown', handleGlobalKeydown)
    window.removeEventListener('keydown', handleEnterKey)
})

const getInitials = (name) => {
    if (!name) return '??'
    return name
        .split(' ')
        .map(word => word.charAt(0))
        .join('')
        .toUpperCase()
        .slice(0, 2)
}

onBeforeRouteLeave((to, from, next) => {
    if (showModal.value && JSON.stringify(newMod.value) !== JSON.stringify(initialModState.value)) {
        showUnsavedModal.value = true
        pendingRoute.value = to
        next(false)
    } else {
        next()
    }
})
</script>

<template>
  <div class="bg-dark-800/80 backdrop-blur-sm rounded-xl border border-white/5 overflow-hidden shadow-xl h-[calc(100vh-9rem)] flex flex-col">
      <div class="p-6 border-b border-white/5 flex flex-col md:flex-row gap-4 justify-between items-center bg-dark-900/40">
          <div class="flex flex-col sm:flex-row gap-4 flex-1 w-full md:w-auto">
              <div class="relative w-full sm:w-auto flex-1 min-w-[200px]">
                    <!-- Search Bar -->
                    <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                    <input 
                        ref="searchInputRef"
                        v-model="searchQuery"
                        type="text" 
                        placeholder="Поиск по таблице (начните вводить)..." 
                        class="w-full bg-dark-900 border border-white/10 rounded-lg pl-10 pr-4 py-2 text-sm text-white focus:border-red-500 focus:outline-none focus:ring-1 focus:ring-red-500 transition-all placeholder-white/20" 
                        @keydown.esc="clearSearchByEsc"
                    />
              </div>
          </div>

          <button 
            @click="openModal"
            class="flex items-center justify-center gap-2 px-4 py-2 bg-transparent hover:bg-red-500/10 text-red-400 hover:text-red-300 rounded-lg transition-colors font-bold text-sm border border-red-500/50 hover:border-red-400 w-full md:w-auto"
          >
              <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
              </svg>
              Добавить
          </button>
      </div>

      <div class="overflow-x-auto relative flex-1 flex flex-col overflow-y-scroll scrollbar-none">
          <table class="w-full text-left text-sm text-gray-400 table-fixed">
              <thead class="bg-dark-900 text-xs uppercase font-bold text-white tracking-wider sticky top-0 z-20 shadow-md border-b border-white/5">
                  <tr>
                      <th class="px-6 py-4 cursor-pointer hover:text-red-400 transition-colors group select-none w-1/3" @click="sortBy('name')">
                          <div class="flex items-center gap-2">
                              Модератор
                              <svg v-if="sortKey === 'name'" class="w-4 h-4 transition-transform flex-shrink-0" :class="sortOrder === 'asc' ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                              <svg v-else class="w-4 h-4 opacity-0 group-hover:opacity-50 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                          </div>
                      </th>
                      <th class="px-6 py-4 cursor-pointer hover:text-red-400 transition-colors group select-none w-1/4" @click="sortBy('email')">
                          <div class="flex items-center gap-2">
                              Email
                              <svg v-if="sortKey === 'email'" class="w-4 h-4 transition-transform flex-shrink-0" :class="sortOrder === 'asc' ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                              <svg v-else class="w-4 h-4 opacity-0 group-hover:opacity-50 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                          </div>
                      </th>
                      <th class="px-6 py-4 cursor-pointer hover:text-red-400 transition-colors group select-none w-1/6" @click="sortBy('last_login')">
                          <div class="flex items-center gap-2">
                              Последний вход
                              <svg v-if="sortKey === 'last_login_at'" class="w-4 h-4 transition-transform flex-shrink-0" :class="sortOrder === 'asc' ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                              <svg v-else class="w-4 h-4 opacity-0 group-hover:opacity-50 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                          </div>
                      </th>
                      <th class="px-6 py-4 cursor-pointer hover:text-red-400 transition-colors group select-none w-1/6" @click="sortBy('joined')">
                          <div class="flex items-center gap-2">
                              Добавлен
                              <svg v-if="sortKey === 'created_at'" class="w-4 h-4 transition-transform flex-shrink-0" :class="sortOrder === 'asc' ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                              <svg v-else class="w-4 h-4 opacity-0 group-hover:opacity-50 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                          </div>
                      </th>
                      <th class="px-6 py-4 text-right w-24">Действия</th>
                  </tr>
              </thead>
              <tbody class="transition-opacity duration-300" :class="{ 'opacity-50': isLoading && loadingType === 'search' }">
                  <tr v-for="mod in moderators" :key="mod.id" class="border-b border-white/5 hover:bg-white/5 transition-colors group">
                      <td class="px-6 py-4">
                          <div class="flex items-center gap-3">
                              <!-- Avatar Style: Red Circle + 2 Initials -->
                              <div class="w-10 h-10 rounded-full bg-red-600 flex items-center justify-center text-sm font-bold text-white border border-red-500 shadow-[0_0_10px_rgba(220,38,38,0.3)]">
                                  {{ getInitials(mod.name) }}
                              </div>
                              <span class="font-bold text-white text-base">{{ mod.name }}</span>
                          </div>
                      </td>
                      <td class="px-6 py-4 font-mono text-xs">{{ mod.email }}</td>
                      <td class="px-6 py-4 text-gray-300 font-mono text-xs">
                          {{ mod.last_login }}
                      </td>
                      <td class="px-6 py-4">
                          {{ mod.joined }}
                      </td>
                      <td class="px-6 py-4 text-right flex items-center justify-end gap-2">
                          <button 
                            @click="openEdit(mod)"
                            class="text-gray-500 hover:text-blue-400 transition-colors p-2 rounded-lg hover:bg-blue-500/10"
                            title="Изменить"
                          >
                              <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                              </svg>
                          </button>
                          <button 
                            @click="initiateDelete(mod.id)"
                            class="text-gray-500 hover:text-red-500 transition-colors p-2 rounded-lg hover:bg-red-500/10"
                            title="Удалить"
                          >
                              <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                              </svg>
                          </button>
                      </td>
                  </tr>
                  <!-- Skeletons (Initial & Scroll State) -->
                  <tr v-if="isLoading && (loadingType === 'initial' || loadingType === 'scroll')" v-for="i in 5" :key="`skeleton-${i}`" class="animate-pulse">
                      <td class="px-6 py-4">
                          <div class="flex items-center gap-3">
                              <div class="w-10 h-10 rounded-full bg-white/10"></div>
                              <div class="h-4 w-32 bg-white/10 rounded"></div>
                          </div>
                      </td>
                      <td class="px-6 py-4">
                          <div class="h-4 w-40 bg-white/10 rounded"></div>
                      </td>
                      <td class="px-6 py-4">
                          <div class="h-4 w-24 bg-white/10 rounded"></div>
                      </td>
                      <td class="px-6 py-4">
                          <div class="h-4 w-20 bg-white/10 rounded"></div>
                      </td>
                      <td class="px-6 py-4">
                          <div class="flex justify-end gap-2">
                              <div class="w-8 h-8 rounded bg-white/10"></div>
                              <div class="w-8 h-8 rounded bg-white/10"></div>
                          </div>
                      </td>
                  </tr>
              </tbody>
          </table>
          
          <!-- No Results Message -->
          <div v-if="!isLoading && moderators.length === 0" class="flex flex-col items-center justify-center py-12 text-gray-500 animate-in fade-in duration-500">
               <svg class="w-12 h-12 mb-3 text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                   <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
               </svg>
               <p class="text-sm font-medium">Ничего не найдено</p>
               <p class="text-xs mt-1">Попробуйте изменить параметры поиска</p>
          </div>

          
          <!-- Infinite Scroll Trigger -->
          <div ref="observerTarget" class="py-4 text-center text-gray-500 text-xs uppercase tracking-widest font-bold h-4">
          </div>
      </div>

      <!-- Add/Edit Moderator Modal (Standardized) -->
      <StandardModal 
          :is-open="showModal" 
          :title="isEditing ? 'Редактировать' : 'Новый Модератор'"
          theme="red"
          max-width="max-w-lg"
          :close-on-escape="!showUnsavedModal && !showConfirmModal"
          @close="closeModal"
      >
          <!-- Content -->
          <div class="space-y-6">
              <!-- Name -->
              <div class="space-y-1">
                   <div class="flex justify-between items-center">
                       <label class="text-xs uppercase tracking-widest text-gray-500 font-bold ml-1">Имя</label>
                       <span v-if="errors.name" class="text-red-500 text-[10px] font-bold uppercase tracking-wide">{{ errors.name }}</span>
                   </div>
                   <input 
                      ref="nameInputRef"
                      v-model="newMod.name" 
                      type="text" 
                      autocomplete="off"
                      class="w-full bg-dark-900/50 border border-white/10 rounded-lg px-4 py-3 text-white placeholder-gray-600 focus:outline-none transition-all duration-300 font-mono text-lg tracking-wide hover:border-white/20"
                      :class="errors.name ? 'border-red-500/50 focus:border-red-500 focus:ring-1 focus:ring-red-500' : 'focus:border-red-500 focus:ring-1 focus:ring-red-500'"
                      placeholder="Иван Петров" 
                   />
                   <p class="text-[10px] text-gray-500 ml-1">Полное имя сотрудника для отображения в системе.</p>
              </div>

              <!-- Email -->
              <div class="space-y-1">
                   <div class="flex justify-between items-center">
                       <label class="text-xs uppercase tracking-widest text-gray-500 font-bold ml-1">Email (Логин)</label>
                       <span v-if="errors.email" class="text-red-500 text-[10px] font-bold uppercase tracking-wide">{{ errors.email }}</span>
                   </div>
                   <input 
                      v-model="newMod.email" 
                      type="email" 
                      autocomplete="off"
                      class="w-full bg-dark-900/50 border border-white/10 rounded-lg px-4 py-3 text-white placeholder-gray-600 focus:outline-none transition-all duration-300 font-mono text-lg tracking-wide hover:border-white/20"
                      :class="errors.email ? 'border-red-500/50 focus:border-red-500 focus:ring-1 focus:ring-red-500' : 'focus:border-red-500 focus:ring-1 focus:ring-red-500'"
                      placeholder="mod@atlas.ru" 
                   />
                   <p class="text-[10px] text-gray-500 ml-1">Используется для входа в панель администратора.</p>
              </div>

              <!-- Password -->
              <div class="space-y-1">
                   <div class="flex justify-between items-center">
                       <label class="text-xs uppercase tracking-widest text-gray-500 font-bold ml-1">Пароль</label>
                       <span v-if="errors.password" class="text-red-500 text-[10px] font-bold uppercase tracking-wide">{{ errors.password }}</span>
                   </div>
                   <div class="relative">
                       <input 
                          v-model="newMod.password" 
                          :type="showPassword ? 'text' : 'password'"
                          autocomplete="new-password"
                          class="w-full bg-dark-900/50 border border-white/10 rounded-lg px-4 py-3 text-white placeholder-gray-600 focus:outline-none transition-all duration-300 font-mono text-lg tracking-wide hover:border-white/20 pr-10"
                          :class="errors.password ? 'border-red-500/50 focus:border-red-500 focus:ring-1 focus:ring-red-500' : 'focus:border-red-500 focus:ring-1 focus:ring-red-500'"
                          placeholder="••••••" 
                       />
                       <button 
                         @click="showPassword = !showPassword"
                         class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 hover:text-white transition-colors focus:outline-none"
                         tabindex="-1"
                       >
                           <svg v-if="showPassword" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                               <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
                           </svg>
                           <svg v-else class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                   <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                   <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                               </svg>
                           </button>
                       </div>
                       <p class="text-[10px] text-gray-500 ml-1">
                           {{ isEditing ? 'Оставьте пустым, чтобы сохранить текущий пароль.' : 'Мин. 8 символов, заглавная, цифра и спецсимвол.' }}
                       </p>
                  </div>
                  
                  <!-- Actions -->
                  <div class="pt-4 flex gap-3">
                      <button @click="closeModal" class="flex-1 py-3 rounded-lg text-xs font-bold uppercase tracking-widest text-gray-400 hover:text-white bg-white/5 hover:bg-white/10 transition-colors border border-transparent hover:border-white/10">
                          Закрыть
                      </button>
                      <button @click="saveModerator" class="flex-1 py-3 bg-red-600 hover:bg-red-500 text-white rounded-lg font-bold text-xs uppercase tracking-widest shadow-lg shadow-red-500/20 transition-all transform active:scale-95 border border-red-500/50">
                          {{ isEditing ? 'Сохранить' : 'Создать' }}
                      </button>
                  </div>
              </div>
      </StandardModal>
      
      <!-- Unsaved Changes Confirmation Modal -->
      <StandardModal 
          :is-open="showUnsavedModal" 
          theme="red"
          z-index-class="z-[200]"
          backdrop-z-index-class="z-[150]"
          :backdrop-blur="false"
          @close="showUnsavedModal = false"
      >
          <div class="text-center pt-2">
              <h3 class="text-xl font-kanit font-bold text-white tracking-wide uppercase mb-6">Закрыть<span class="text-2xl">?</span></h3>
              <p class="text-gray-400 text-sm mb-2 font-light">
                  Внесенные изменения
              </p>
              <p class="text-red-400 text-sm font-semibold mb-6 tracking-wide">
                  будут потеряны безвозвратно
              </p>
              
              <div class="flex gap-3">
                  <button @click="showUnsavedModal = false" class="flex-1 py-3 rounded-lg text-xs font-bold uppercase tracking-widest text-gray-400 hover:text-white bg-white/5 hover:bg-white/10 transition-colors border border-transparent hover:border-white/10">
                      Отмена
                  </button>
                  <button @click="confirmDiscardChanges" class="flex-1 py-3 bg-red-600 hover:bg-red-500 text-white rounded-lg font-bold text-xs uppercase tracking-widest shadow-lg shadow-red-500/20 transition-all transform active:scale-95 border border-red-500/50">
                      Закрыть
                  </button>
              </div>
          </div>
      </StandardModal>
      
      <!-- Delete Confirmation Modal (Standardized) -->
      <StandardModal 
          :is-open="showConfirmModal" 
          theme="red"
          @close="closeModal"
      >
          <!-- Using template #header to override default left-aligned header -->
          
          <div class="text-center pt-2">
              <h3 class="text-xl font-kanit font-bold text-white tracking-wide uppercase mb-6">Удалить<span class="text-2xl">?</span></h3>
              <p class="text-gray-400 text-sm mb-2 font-light">
                  Доступ для модератора
              </p>
              <p class="text-white font-bold text-lg mb-2">
                  {{ selectedMod?.name }}
              </p>
              <p class="text-red-400 text-sm font-semibold mb-6 tracking-wide">
                  будет закрыт немедленно
              </p>
              
              <div class="flex gap-3">
                  <button @click="closeModal" class="flex-1 py-3 rounded-lg text-xs font-bold uppercase tracking-widest text-gray-400 hover:text-white bg-white/5 hover:bg-white/10 transition-colors border border-transparent hover:border-white/10">
                      Отмена
                  </button>
                  <button @click="confirmDelete" class="flex-1 py-3 bg-red-600 hover:bg-red-500 text-white rounded-lg font-bold text-xs uppercase tracking-widest shadow-lg shadow-red-500/20 transition-all transform active:scale-95 border border-red-500/50">
                      Удалить
                  </button>
              </div>
          </div>
      </StandardModal>

  </div>
</template>
