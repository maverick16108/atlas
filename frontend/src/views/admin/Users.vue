<script setup>
import { ref, computed, watch, onMounted, onUnmounted, nextTick } from 'vue'
import { onBeforeRouteLeave, useRouter, useRoute } from 'vue-router'
import StandardModal from '../../components/ui/StandardModal.vue'
import axios from 'axios'

// --- State ---
const users = ref([])
const totalUsers = ref(0)
const searchQuery = ref('')
const filterAccreditation = ref('all')
const sortKey = ref('created_at')
const sortOrder = ref('desc')
const page = ref(1)
const perPage = 50
const isLoading = ref(false)
const hasMore = ref(true)
const loadingType = ref('initial') // initial, search, scroll

// --- Logic ---
const showModal = ref(false)
const showConfirmModal = ref(false)
const deleteId = ref(null)
const selectedUser = ref(null)
const newUser = ref({ name: '', email: '', phone: '', auth_phone: '', is_accredited: false, is_gpb: false })
const errors = ref({ name: '', email: '', phone: '', auth_phone: '' })
const showPassword = ref(false)
const isEditing = ref(false)
const editingId = ref(null)

// --- API Interactions ---
const loadMore = async (reset = false) => {
    if (!reset && (isLoading.value || !hasMore.value)) return
    
    if (reset) {
        if (users.value.length > 0) loadingType.value = 'search'
        else loadingType.value = 'initial'
        page.value = 1
        hasMore.value = true
    } else {
        loadingType.value = 'scroll'
    }
    
    isLoading.value = true
    
    try {
        const params = {
            page: page.value,
            per_page: perPage,
            search: searchQuery.value,
            sort_key: sortKey.value,
            sort_order: sortOrder.value
        }

        if (filterAccreditation.value === 'accredited') {
            params.is_accredited = true
        } else if (filterAccreditation.value === 'pending') {
            params.is_accredited = false
        }

        const response = await axios.get('/api/users', { params })
        
        const data = response.data.data
        const mappedData = data.map(u => ({
            id: u.id,
            name: u.name,
            email: u.email,
            phone: u.phone,
            auth_phone: u.auth_phone,
            role: u.role,
            is_accredited: u.is_accredited,
            is_gpb: u.is_gpb,
            joined: new Date(u.created_at).toLocaleString('ru-RU', { 
                day: '2-digit', 
                month: '2-digit', 
                year: 'numeric', 
                hour: '2-digit', 
                minute: '2-digit' 
            }),
        }))

        if (reset) {
            users.value = mappedData
        } else {
            users.value.push(...mappedData)
        }
        
        if (response.data.next_page_url) {
            page.value++
            hasMore.value = true
        } else {
            hasMore.value = false
        }

    } catch (e) {
        console.error('Failed to load users:', e)
    } finally {
        isLoading.value = false
        loadingType.value = 'initial'
    }
}

const resetList = () => {
    loadMore(true)
}

const sortBy = (key) => {
    if (sortKey.value === key) {
        sortOrder.value = sortOrder.value === 'asc' ? 'desc' : 'asc'
    } else {
        sortKey.value = key
        sortOrder.value = 'asc'
    }
    resetList()
}

watch([searchQuery, filterAccreditation], () => {
    resetList()
})

const toggleAccreditation = async (user) => {
    try {
        await axios.post(`/api/users/${user.id}/toggle-accreditation`)
        user.is_accredited = !user.is_accredited
    } catch (e) {
        console.error('Failed to toggle accreditation:', e)
        alert('Ошибка обновления статуса')
    }
}

const saveUser = async () => {
    errors.value = { name: '', email: '', phone: '', auth_phone: '' }
    let isValid = true

    if (!newUser.value.name) {
        errors.value.name = 'Имя обязательно'
        isValid = false
    }
    if (!newUser.value.email) {
        errors.value.email = 'Email обязателен'
        isValid = false
    }
    if (!newUser.value.phone) {
        errors.value.phone = 'Телефон обязателен'
        isValid = false
    }
    
    // Auth phone validation
    if (newUser.value.is_accredited) {
        if (!newUser.value.auth_phone) {
            errors.value.auth_phone = 'Обязательно для аккредитации'
            isValid = false
        } else if (newUser.value.auth_phone.length < 18) {
            errors.value.auth_phone = 'Неверный формат'
            isValid = false
        }
    }

    if (!isValid) return

    try {
        if (isEditing.value) {
            const response = await axios.put(`/api/users/${editingId.value}`, {
                name: newUser.value.name,
                email: newUser.value.email,
                phone: newUser.value.phone,
                auth_phone: newUser.value.auth_phone,
                is_accredited: newUser.value.is_accredited,
                is_gpb: newUser.value.is_gpb
            })
            // Update user in local array without reloading to preserve sort order
            const idx = users.value.findIndex(u => u.id === editingId.value)
            if (idx !== -1) {
                const updated = response.data
                users.value[idx] = {
                    ...users.value[idx],
                    name: updated.name,
                    email: updated.email,
                    phone: updated.phone,
                    auth_phone: updated.auth_phone,
                    is_accredited: updated.is_accredited,
                    is_gpb: updated.is_gpb,
                }
            }
        } else {
            await axios.post('/api/users', {
                name: newUser.value.name,
                email: newUser.value.email,
                phone: newUser.value.phone,
                auth_phone: newUser.value.auth_phone,
                is_accredited: newUser.value.is_accredited,
                is_gpb: newUser.value.is_gpb
            })
            resetList()
        }
        closeModal(true)
    } catch (e) {
        if (e.response && e.response.status === 422) {
            const backendErrors = e.response.data.errors
            if (backendErrors.email) errors.value.email = backendErrors.email[0]
            if (backendErrors.name) errors.value.name = backendErrors.name[0]
            if (backendErrors.phone) errors.value.phone = backendErrors.phone[0]
            if (backendErrors.auth_phone) errors.value.auth_phone = backendErrors.auth_phone[0]
        } else {
            console.error('Save failed:', e)
            alert('Ошибка сохранения: ' + (e.response?.data?.message || e.message))
        }
    }
}

const initiateDelete = (id) => {
    deleteId.value = id
    selectedUser.value = users.value.find(u => u.id === id)
    showConfirmModal.value = true
}

const confirmDelete = async () => {
    if (deleteId.value) {
        try {
            await axios.delete(`/api/users/${deleteId.value}`)
            resetList()
        } catch (e) {
            console.error('Delete failed:', e)
        }
    }
    closeModal()
}

// --- Interaction ---
const nameInputRef = ref(null)

const showUnsavedModal = ref(false)
const initialUserState = ref(null)
const router = useRouter()
const pendingRoute = ref(null)

const confirmDiscardChanges = () => {
    showUnsavedModal.value = false
    showModal.value = false
    showConfirmModal.value = false
    deleteId.value = null
    selectedUser.value = null
    isEditing.value = false
    editingId.value = null
    
    if (pendingRoute.value) {
        router.push(pendingRoute.value)
        pendingRoute.value = null
    }
}

const openModal = async () => {
    newUser.value = { name: '', email: '', phone: '', auth_phone: '', is_accredited: false, is_gpb: false }
    errors.value = { name: '', email: '', phone: '', auth_phone: '' }
    showPassword.value = false
    
    initialUserState.value = JSON.parse(JSON.stringify(newUser.value))
    
    showModal.value = true
    isEditing.value = false
    await nextTick()
    if (nameInputRef.value) nameInputRef.value.focus()
}

const openEdit = async (user) => {
    isEditing.value = true
    editingId.value = user.id
    selectedUser.value = user
    newUser.value = { 
        name: user.name, 
        email: user.email, 
        phone: user.phone,
        auth_phone: user.auth_phone || '',
        is_accredited: !!user.is_accredited,
        is_gpb: !!user.is_gpb
    }
    errors.value = { name: '', email: '', phone: '', auth_phone: '' }
    showPassword.value = false
    
    initialUserState.value = JSON.parse(JSON.stringify(newUser.value))
    
    showModal.value = true
    await nextTick()
    if (nameInputRef.value) nameInputRef.value.focus()
}

const closeModal = (force = false) => {
    // Handle Event object from @click or @close
    if (typeof force !== 'boolean') force = false

    if (!force && showModal.value && JSON.stringify(newUser.value) !== JSON.stringify(initialUserState.value)) {
        showUnsavedModal.value = true
        return
    }

    showModal.value = false
    showConfirmModal.value = false
    showUnsavedModal.value = false
    deleteId.value = null
    selectedUser.value = null
    isEditing.value = false
    editingId.value = null
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
    
    if (showModal.value || showConfirmModal.value) return
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
        saveUser()
        e.preventDefault()
    }
}

onMounted(async () => {
    // Check for query params
    const route = useRoute()
    
    if (route.query.filter === 'pending') {
        filterAccreditation.value = 'pending'
    } else if (route.query.filter === 'accredited') {
        filterAccreditation.value = 'accredited'
    }
    
    // Initial fetch handled by resetList or direct call if we want optimization (but watcher will trigger resetList anyway)
    // Actually, watcher triggers on mount? No, refs are set before mount usually, but let's be safe.
    // If we set value here, watcher might trigger.
    
    // Let's just call loadMore or resetList
    resetList()

    if (route.query.action === 'create') {
        openModal()
    }

    if (route.query.sort === 'newest') {
        sortBy('created_at') // effective descending
    }

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

onBeforeRouteLeave((to, from, next) => {
    if (showModal.value && JSON.stringify(newUser.value) !== JSON.stringify(initialUserState.value)) {
        showUnsavedModal.value = true
        pendingRoute.value = to
        next(false)
    } else {
        next()
    }
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

const formatPhone = (value) => {
    value = value.replace(/\D/g, '') // Strip non-digits
    
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
    return formatted
}

const handleAuthPhoneInput = (event) => {
    newUser.value.auth_phone = formatPhone(event.target.value)
}
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
                        placeholder="Поиск участников..." 
                        class="w-full bg-dark-900 border border-white/10 rounded-lg pl-10 pr-4 py-2 text-sm text-white focus:border-red-500 focus:outline-none focus:ring-1 focus:ring-red-500 transition-all placeholder-white/20" 
                        @keydown.esc="clearSearchByEsc"
                    />
              </div>

              <!-- Accreditation Filter -->
              <div class="relative w-full sm:w-56 flex-shrink-0">
                  <select 
                      v-model="filterAccreditation"
                      class="no-arrow w-full bg-dark-900 border border-white/10 rounded-lg pl-4 pr-10 py-2 text-sm text-white focus:border-red-500 focus:outline-none focus:ring-1 focus:ring-red-500 transition-all cursor-pointer hover:border-white/20"
                  >
                      <option value="all">Все участники</option>
                      <option value="accredited">Аккредитованные</option>
                      <option value="pending">На модерации</option>
                  </select>
                  <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-3 text-gray-500">
                      <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                      </svg>
                  </div>
              </div>
          </div>
          
          <button 
            @click="openModal"
            class="flex items-center justify-center gap-2 px-4 py-2 bg-transparent hover:bg-red-500/10 text-red-400 hover:text-red-300 rounded-lg transition-colors font-bold text-sm border border-red-500/50 hover:border-red-400 w-full md:w-auto"
          >
              <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
              </svg>
              Добавить участника
          </button>
      </div>

      <div class="overflow-x-auto relative flex-1 flex flex-col overflow-y-scroll scrollbar-none">
          <table class="w-full text-left text-sm text-gray-400 table-fixed">
              <thead class="bg-dark-900 text-xs uppercase font-bold text-white tracking-wider sticky top-0 z-20 shadow-md border-b border-white/5">
                  <tr>
                      <th class="px-6 py-4 cursor-pointer hover:text-red-400 transition-colors group select-none w-1/3" @click="sortBy('name')">
                          <div class="flex items-center gap-2">
                              Участник
                              <svg v-if="sortKey === 'name'" class="w-4 h-4 transition-transform flex-shrink-0" :class="sortOrder === 'asc' ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                              <svg v-else class="w-4 h-4 opacity-0 group-hover:opacity-50 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                          </div>
                      </th>
                      <th class="px-6 py-4 cursor-pointer hover:text-red-400 transition-colors group select-none w-1/5" @click="sortBy('email')">
                          <div class="flex items-center gap-2">
                              Контакты
                              <svg v-if="sortKey === 'email'" class="w-4 h-4 transition-transform flex-shrink-0" :class="sortOrder === 'asc' ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                              <svg v-else class="w-4 h-4 opacity-0 group-hover:opacity-50 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                          </div>
                      </th>
                      <th class="px-6 py-4 cursor-pointer hover:text-red-400 transition-colors group select-none w-1/5" @click="sortBy('auth_phone')">
                          <div class="flex items-center gap-2">
                              Телефон (вход)
                              <svg v-if="sortKey === 'auth_phone'" class="w-4 h-4 transition-transform flex-shrink-0" :class="sortOrder === 'asc' ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                              <svg v-else class="w-4 h-4 opacity-0 group-hover:opacity-50 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                          </div>
                      </th>
                      <th class="px-6 py-4 cursor-pointer hover:text-red-400 transition-colors group select-none w-1/6" @click="sortBy('is_accredited')">
                          <div class="flex items-center gap-2">
                              Статус
                              <svg v-if="sortKey === 'is_accredited'" class="w-4 h-4 transition-transform flex-shrink-0" :class="sortOrder === 'asc' ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                              <svg v-else class="w-4 h-4 opacity-0 group-hover:opacity-50 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                          </div>
                      </th>
                      <th class="px-6 py-4 cursor-pointer hover:text-red-400 transition-colors group select-none w-1/6" @click="sortBy('is_gpb')">
                          <div class="flex items-center gap-2">
                              ГПБ
                              <svg v-if="sortKey === 'is_gpb'" class="w-4 h-4 transition-transform flex-shrink-0" :class="sortOrder === 'asc' ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                              <svg v-else class="w-4 h-4 opacity-0 group-hover:opacity-50 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                          </div>
                      </th>
                      <th class="px-6 py-4 cursor-pointer hover:text-red-400 transition-colors group select-none w-1/6" @click="sortBy('created_at')">
                          <div class="flex items-center gap-2">
                              Регистрация
                              <svg v-if="sortKey === 'created_at'" class="w-4 h-4 transition-transform flex-shrink-0" :class="sortOrder === 'asc' ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                              <svg v-else class="w-4 h-4 opacity-0 group-hover:opacity-50 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                          </div>
                      </th>
                      <th class="px-6 py-4 text-right w-32">Действия</th>
                  </tr>
              </thead>
              <tbody class="transition-opacity duration-300" :class="{ 'opacity-50': isLoading && loadingType === 'search' }">
                  <tr v-for="user in users" :key="user.id" class="border-b border-white/5 hover:bg-white/5 transition-colors group">
                      <td class="px-6 py-4">
                          <div class="flex items-center gap-3">
                              <!-- Avatar Style: Blue Circle + 2 Initials for Clients -->
                              <div class="w-10 h-10 rounded-full bg-blue-600 flex items-center justify-center text-sm font-bold text-white border border-blue-500 shadow-[0_0_10px_rgba(37,99,235,0.3)]">
                                  {{ getInitials(user.name) }}
                              </div>
                              <span class="font-bold text-white text-base">{{ user.name }}</span>
                          </div>
                      </td>
                      <td class="px-6 py-4 font-mono text-xs overflow-hidden">
                          <div class="truncate" :title="user.email">{{ user.email }}</div>
                          <div class="text-gray-500 mt-1 truncate">{{ user.phone }}</div>
                      </td>
                      <td class="px-6 py-4 font-mono text-xs text-gold-400">
                          {{ user.auth_phone || '-' }}
                      </td>
                      <td class="px-6 py-4">
                          <span class="px-2 py-1 rounded text-xs font-bold uppercase transition-colors select-none block text-center w-fit" 
                             :class="user.is_accredited ? 'bg-green-500/20 text-green-400 border border-green-500/20' : 'bg-red-500/20 text-red-400 border border-red-500/20'"
                          >
                              {{ user.is_accredited ? 'Аккредитован' : 'Нет доступа' }}
                          </span>
                      </td>
                      <td class="px-6 py-4">
                          <span v-if="user.is_gpb" class="px-2 py-1 rounded text-xs font-bold uppercase transition-colors select-none block text-center w-fit bg-blue-500/20 text-blue-400 border border-blue-500/20">
                              ГПБ
                          </span>
                          <span v-else class="text-gray-600 text-xs font-mono block text-center w-fit">
                              -
                          </span>
                      </td>
                      <td class="px-6 py-4 text-gray-300 font-mono text-xs">
                          {{ user.joined }}
                      </td>
                      <td class="px-6 py-4 text-right flex items-center justify-end gap-2">
                          <button 
                            @click="openEdit(user)"
                            class="text-gray-500 hover:text-blue-400 transition-colors p-2 rounded-lg hover:bg-blue-500/10"
                            title="Изменить"
                          >
                              <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                              </svg>
                          </button>
                          <button 
                            @click="initiateDelete(user.id)"
                            class="text-gray-500 hover:text-red-500 transition-colors p-2 rounded-lg hover:bg-red-500/10"
                            title="Удалить"
                          >
                              <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                              </svg>
                          </button>
                      </td>
                  </tr>
                  
                  <!-- Skeletons -->
                  <tr v-if="isLoading && (loadingType === 'initial' || loadingType === 'scroll')" v-for="i in 5" :key="`skeleton-${i}`" class="animate-pulse">
                      <td class="px-6 py-4">
                          <div class="flex items-center gap-3">
                              <div class="w-10 h-10 rounded-full bg-white/10"></div>
                              <div class="h-4 w-32 bg-white/10 rounded"></div>
                          </div>
                      </td>
                      <td class="px-6 py-4">
                          <div class="h-8 w-40 bg-white/10 rounded"></div>
                      </td>
                      <td class="px-6 py-4">
                          <div class="h-6 w-24 bg-white/10 rounded"></div>
                      </td>
                      <td class="px-6 py-4">
                          <div class="h-6 w-20 bg-white/10 rounded"></div>
                      </td>
                      <td class="px-6 py-4">
                          <div class="h-4 w-12 bg-white/10 rounded"></div>
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
          
          <!-- No Results -->
          <div v-if="!isLoading && users.length === 0" class="flex flex-col items-center justify-center py-12 text-gray-500 animate-in fade-in duration-500">
               <svg class="w-12 h-12 mb-3 text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                   <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
               </svg>
               <p class="text-sm font-medium">Ничего не найдено</p>
          </div>

          <!-- Infinite Scroll Trigger -->
          <div ref="observerTarget" class="py-4 text-center text-gray-500 text-xs uppercase tracking-widest font-bold h-4">
          </div>
      </div>

      <!-- Add/Edit Modal -->
      <StandardModal 
          :is-open="showModal" 
          :title="isEditing ? 'Редактировать' : 'Новый Участник'"
          theme="red"
          max-width="max-w-lg"
          :close-on-escape="!showUnsavedModal && !showConfirmModal"
          @close="closeModal"
      >
          <div class="space-y-6">
              <!-- Name -->
              <div class="space-y-1">
                   <div class="flex justify-between items-center">
                       <label class="text-xs uppercase tracking-widest text-gray-500 font-bold ml-1">Название / Имя</label>
                       <span v-if="errors.name" class="text-red-500 text-[10px] font-bold uppercase tracking-wide">{{ errors.name }}</span>
                   </div>
                   <input 
                      ref="nameInputRef"
                      v-model="newUser.name" 
                      type="text" 
                      class="w-full bg-dark-900/50 border border-white/10 rounded-lg px-4 py-3 text-white placeholder-gray-600 focus:outline-none transition-all duration-300 font-mono text-lg tracking-wide hover:border-white/20"
                      :class="errors.name ? 'border-red-500/50 focus:border-red-500 focus:ring-1 focus:ring-red-500' : 'focus:border-red-500 focus:ring-1 focus:ring-red-500'"
                      placeholder="ООО Ромашка" 
                   />
              </div>

              <!-- Email -->
              <div class="space-y-1">
                   <div class="flex justify-between items-center">
                       <label class="text-xs uppercase tracking-widest text-gray-500 font-bold ml-1">Email (Контактный)</label>
                       <span v-if="errors.email" class="text-red-500 text-[10px] font-bold uppercase tracking-wide">{{ errors.email }}</span>
                   </div>
                   <input 
                      v-model="newUser.email" 
                      type="email" 
                      class="w-full bg-dark-900/50 border border-white/10 rounded-lg px-4 py-3 text-white placeholder-gray-600 focus:outline-none transition-all duration-300 font-mono text-lg tracking-wide hover:border-white/20"
                      :class="errors.email ? 'border-red-500/50 focus:border-red-500 focus:ring-1 focus:ring-red-500' : 'focus:border-red-500 focus:ring-1 focus:ring-red-500'"
                      placeholder="client@mail.ru" 
                   />
              </div>

               <!-- Phone -->
              <div class="space-y-1">
                   <div class="flex justify-between items-center">
                       <label class="text-xs uppercase tracking-widest text-gray-500 font-bold ml-1">Телефон (Контактный)</label>
                       <span v-if="errors.phone" class="text-red-500 text-[10px] font-bold uppercase tracking-wide">{{ errors.phone }}</span>
                   </div>
                   <input 
                      v-model="newUser.phone" 
                      type="text" 
                      class="w-full bg-dark-900/50 border border-white/10 rounded-lg px-4 py-3 text-white placeholder-gray-600 focus:outline-none transition-all duration-300 font-mono text-lg tracking-wide hover:border-white/20"
                      :class="errors.phone ? 'border-red-500/50 focus:border-red-500 focus:ring-1 focus:ring-red-500' : 'focus:border-red-500 focus:ring-1 focus:ring-red-500'"
                      placeholder="+7 (999) 000-00-00" 
                   />
              </div>

              <!-- Auth Phone -->
              <div class="space-y-1">
                   <div class="flex justify-between items-center">
                       <label class="text-xs uppercase tracking-widest text-gold-500 font-bold ml-1">Телефон для Авторизации</label>
                       <span v-if="errors.auth_phone" class="text-red-500 text-[10px] font-bold uppercase tracking-wide">{{ errors.auth_phone }}</span>
                   </div>
                   <input 
                      v-model="newUser.auth_phone" 
                      @input="handleAuthPhoneInput"
                      type="text" 
                      maxlength="18"
                      class="w-full bg-dark-900/50 border border-white/10 rounded-lg px-4 py-3 text-white placeholder-gray-600 focus:outline-none transition-all duration-300 font-mono text-lg tracking-wide hover:border-white/20"
                      :class="errors.auth_phone ? 'border-red-500/50 focus:border-red-500 focus:ring-1 focus:ring-red-500' : 'focus:border-red-500 focus:ring-1 focus:ring-red-500'"
                      placeholder="+7 (999) 000-00-00" 
                   />
              </div>

              <!-- Status Toggle -->
              <div class="space-y-1 pt-2">
                  <label class="text-xs uppercase tracking-widest text-gray-500 font-bold ml-1">Статус Аккредитации</label>
                  <div class="flex items-center gap-4 bg-dark-900/30 p-4 rounded-lg border border-white/5">
                      <div class="relative inline-flex items-center cursor-pointer" @click="newUser.is_accredited = !newUser.is_accredited">
                          <div class="w-12 h-6 rounded-full transition-colors duration-300 ease-in-out" :class="newUser.is_accredited ? 'bg-green-500' : 'bg-gray-600'"></div>
                          <div class="absolute left-1 w-4 h-4 bg-white rounded-full transition-transform duration-300 ease-in-out transform" :class="newUser.is_accredited ? 'translate-x-6' : 'translate-x-0'"></div>
                      </div>
                      <span class="text-sm font-bold uppercase tracking-wide" :class="newUser.is_accredited ? 'text-green-400' : 'text-gray-500'">
                          {{ newUser.is_accredited ? 'Аккредитован' : 'Нет доступа' }}
                      </span>
                  </div>
                   <p class="text-[10px] text-gray-500 ml-1">
                      {{ newUser.is_accredited ? 'Участник может входить в систему.' : 'Доступ к личному кабинету закрыт.' }}
                   </p>
              </div>

              <!-- GPB Toggle -->
              <div class="space-y-1 pt-2">
                  <label class="text-xs uppercase tracking-widest text-gray-500 font-bold ml-1">Сотрудник ГПБ</label>
                  <div class="flex items-center gap-4 bg-dark-900/30 p-4 rounded-lg border border-white/5">
                      <div class="relative inline-flex items-center cursor-pointer" @click="newUser.is_gpb = !newUser.is_gpb">
                          <div class="w-12 h-6 rounded-full transition-colors duration-300 ease-in-out" :class="newUser.is_gpb ? 'bg-blue-600' : 'bg-gray-600'"></div>
                          <div class="absolute left-1 w-4 h-4 bg-white rounded-full transition-transform duration-300 ease-in-out transform" :class="newUser.is_gpb ? 'translate-x-6' : 'translate-x-0'"></div>
                      </div>
                      <span class="text-sm font-bold uppercase tracking-wide" :class="newUser.is_gpb ? 'text-blue-400' : 'text-gray-500'">
                          {{ newUser.is_gpb ? 'Да' : 'Нет' }}
                      </span>
                  </div>
                   <p class="text-[10px] text-gray-500 ml-1">
                      {{ newUser.is_gpb ? 'Участник имеет расширенный функционал ГПБ.' : 'Обычный участник.' }}
                   </p>
              </div>

                  
                  <!-- Actions -->
                  <div class="pt-4 flex gap-3">
                      <button @click="closeModal" class="flex-1 py-3 rounded-lg text-xs font-bold uppercase tracking-widest text-gray-400 hover:text-white bg-white/5 hover:bg-white/10 transition-colors border border-transparent hover:border-white/10">
                          Закрыть
                      </button>
                      <button @click="saveUser" class="flex-1 py-3 bg-red-600 hover:bg-red-500 text-white rounded-lg font-bold text-xs uppercase tracking-widest shadow-lg shadow-red-500/20 transition-all transform active:scale-95 border border-red-500/50">
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
      
      <!-- Delete Confirmation Modal -->
      <StandardModal 
          :is-open="showConfirmModal" 
          theme="red"
          @close="closeModal"
      >
          <div class="text-center pt-2">
              <h3 class="text-xl font-kanit font-bold text-white tracking-wide uppercase mb-6">Удалить<span class="text-2xl">?</span></h3>
              <p class="text-gray-400 text-sm mb-2 font-light">
                  Доступ для участника
              </p>
              <p class="text-white font-bold text-lg mb-2">
                  {{ selectedUser?.name }}
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

<style scoped>
.no-arrow {
    -webkit-appearance: none;
    -moz-appearance: none;
    appearance: none;
    background-image: none;
}
.no-arrow::-ms-expand {
    display: none;
}
</style>
