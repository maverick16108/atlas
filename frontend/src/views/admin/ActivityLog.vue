<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue'
import axios from 'axios'

// --- State ---
const logs = ref([])
const searchQuery = ref('')
const sortKey = ref('created_at')
const sortOrder = ref('desc')
const page = ref(1)
const perPage = 50
const isLoading = ref(false)
const hasMore = ref(true)
const loadingType = ref('initial')

// --- Filters ---
const filterEntityType = ref('')
const filterAction = ref('')
const filterUserRole = ref('')

const entityTypeOptions = [
    { value: '', label: 'Все сущности' },
    { value: 'auction', label: 'Аукционы' },
    { value: 'user', label: 'Участники' },
    { value: 'moderator', label: 'Модераторы' },
    { value: 'bid', label: 'Ставки' },
]

const actionOptions = [
    { value: '', label: 'Все действия' },
    { value: 'created', label: 'Создание' },
    { value: 'updated', label: 'Изменение' },
    { value: 'deleted', label: 'Удаление' },
]

const userRoleOptions = [
    { value: '', label: 'Все пользователи' },
    { value: 'system', label: 'Система' },
    { value: 'super_admin', label: 'Супер Админ' },
    { value: 'moderator', label: 'Модераторы' },
    { value: 'client', label: 'Участники' },
]

// --- Expanded row ---
const expandedLogId = ref(null)

const toggleExpand = (logId) => {
    expandedLogId.value = expandedLogId.value === logId ? null : logId
}

// --- API ---
const loadMore = async (reset = false) => {
    if (!reset && (isLoading.value || !hasMore.value)) return

    if (reset) {
        if (logs.value.length > 0) loadingType.value = 'search'
        else loadingType.value = 'initial'
        page.value = 1
        hasMore.value = true
    } else {
        loadingType.value = 'scroll'
    }

    isLoading.value = true

    try {
        const response = await axios.get('/api/activity-logs', {
            params: {
                page: page.value,
                per_page: perPage,
                search: searchQuery.value || undefined,
                entity_type: filterEntityType.value || undefined,
                action: filterAction.value || undefined,
                user_role: filterUserRole.value || undefined,
                sort_key: sortKey.value,
                sort_order: sortOrder.value,
            }
        })

        const data = response.data.data

        const mappedData = data.map(log => ({
            id: log.id,
            userName: log.user?.name || 'Система',
            userRole: log.user?.role || '',
            action: log.action,
            entityType: log.entity_type,
            entityId: log.entity_id,
            entityName: log.entity_name || `#${log.entity_id}`,
            changes: log.changes,
            metadata: log.metadata,
            createdAt: new Date(log.created_at).toLocaleString('ru-RU', {
                day: '2-digit', month: '2-digit', year: 'numeric',
                hour: '2-digit', minute: '2-digit', second: '2-digit'
            }),
        }))

        if (reset) {
            logs.value = mappedData
        } else {
            logs.value.push(...mappedData)
        }

        if (response.data.next_page_url) {
            page.value++
            hasMore.value = true
        } else {
            hasMore.value = false
        }
    } catch (e) {
        console.error('Failed to load activity logs:', e)
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
        sortOrder.value = key === 'created_at' ? 'desc' : 'asc'
    }
    resetList()
}

watch([searchQuery, filterEntityType, filterAction, filterUserRole], () => {
    resetList()
})

// --- Labels ---
const actionLabel = (action) => {
    const map = { created: 'Создание', updated: 'Изменение', deleted: 'Удаление' }
    return map[action] || action
}

const actionColor = (action) => {
    const map = {
        created: 'bg-emerald-500/10 text-emerald-400 border-emerald-500/30',
        updated: 'bg-blue-500/10 text-blue-400 border-blue-500/30',
        deleted: 'bg-red-500/10 text-red-400 border-red-500/30',
    }
    return map[action] || 'bg-white/5 text-gray-400 border-white/10'
}

const entityTypeLabel = (type) => {
    const map = { auction: 'Аукцион', user: 'Участник', moderator: 'Модератор', bid: 'Ставка' }
    return map[type] || type
}

const entityTypeColor = (type) => {
    const map = {
        auction: 'text-amber-400',
        user: 'text-cyan-400',
        moderator: 'text-purple-400',
        bid: 'text-emerald-400',
    }
    return map[type] || 'text-gray-400'
}

const entityTypeIcon = (type) => {
    const map = {
        auction: 'M13 10V3L4 14h7v7l9-11h-7z',
        user: 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z',
        moderator: 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z',
        bid: 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
    }
    return map[type] || 'M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z'
}

// --- Field labels ---
const fieldLabel = (field) => {
    const map = {
        title: 'Название',
        name: 'Наименование',
        email: 'Email',
        phone: 'Телефон',
        auth_phone: 'Телефон авторизации',
        status: 'Статус',
        start_at: 'Начало',
        end_at: 'Окончание',
        min_step: 'Мин. шаг',
        step_time: 'Время шага',
        min_price: 'Мин. цена',
        description: 'Описание',
        is_accredited: 'Аккредитация',
        is_gpb: 'ГПБ',
        password: 'Пароль',
        timezone: 'Часовой пояс',
        bar_count: 'Кол-во слитков',
        bar_weight: 'Вес слитка',
        gpb_minutes: 'Право ГПБ, мин',
        gpb_started_at: 'Начало права ГПБ',
        participant_ids: 'Участники',
        inn: 'ИНН',
        kpp: 'КПП',
        address: 'Адрес',
    }
    return map[field] || field
}

const statusLabels = {
    draft: 'Черновик',
    active: 'Активный',
    gpb_right: 'Право ГПБ',
    commission: 'Работа комиссии',
    completed: 'Завершён',
    cancelled: 'Отменён',
}

const formatValue = (value) => {
    if (value === null || value === undefined) return '—'
    if (typeof value === 'boolean') return value ? 'Да' : 'Нет'
    if (Array.isArray(value)) return value.length > 0 ? `[${value.join(', ')}]` : '—'
    if (typeof value === 'string' && statusLabels[value]) return statusLabels[value]
    if (typeof value === 'string' && value.length > 80) return value.substring(0, 80) + '...'
    return String(value)
}

const hasChanges = (log) => {
    return log.changes && Object.keys(log.changes).length > 0
}

const hasMetadata = (log) => {
    return log.metadata && Object.keys(log.metadata).length > 0
}

// --- Keyboard & Scroll ---
const searchInputRef = ref(null)
const observerTarget = ref(null)

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
        if (expandedLogId.value !== null) {
            expandedLogId.value = null
            return
        }
        if (searchQuery.value) {
            clearSearchByEsc(e)
            return
        }
    }

    if (e.ctrlKey || e.metaKey || e.altKey || e.shiftKey) return
    if (['INPUT', 'TEXTAREA', 'SELECT'].includes(document.activeElement.tagName)) return

    if (e.key.length === 1) {
        if (searchInputRef.value && document.activeElement !== searchInputRef.value) {
            searchInputRef.value.focus()
        }
    }
}

onMounted(() => {
    resetList()
    window.addEventListener('keydown', handleGlobalKeydown)
    
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
})
</script>

<template>
  <div class="bg-dark-800/80 backdrop-blur-sm rounded-xl border border-white/5 overflow-hidden shadow-xl h-[calc(100vh-9rem)] flex flex-col">
      <!-- Header with Search & Filters -->
      <div class="p-6 border-b border-white/5 flex flex-col gap-4 bg-dark-900/40">
          <div class="flex flex-col md:flex-row gap-4 justify-between items-center">
              <!-- Search -->
              <div class="relative w-full md:w-auto flex-1 min-w-[200px]">
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

              <!-- Filters -->
              <div class="flex gap-3 w-full md:w-auto">
                  <select 
                      v-model="filterEntityType"
                      class="bg-dark-900 border border-white/10 rounded-lg px-3 py-2 text-sm text-white focus:border-red-500 focus:outline-none focus:ring-1 focus:ring-red-500 transition-all appearance-none cursor-pointer"
                  >
                      <option v-for="opt in entityTypeOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                  </select>

                  <select 
                      v-model="filterUserRole"
                      class="bg-dark-900 border border-white/10 rounded-lg px-3 py-2 text-sm text-white focus:border-red-500 focus:outline-none focus:ring-1 focus:ring-red-500 transition-all appearance-none cursor-pointer"
                  >
                      <option v-for="opt in userRoleOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                  </select>

                  <select 
                      v-model="filterAction"
                      class="bg-dark-900 border border-white/10 rounded-lg px-3 py-2 text-sm text-white focus:border-red-500 focus:outline-none focus:ring-1 focus:ring-red-500 transition-all appearance-none cursor-pointer"
                  >
                      <option v-for="opt in actionOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                  </select>
              </div>
          </div>
      </div>

      <!-- Table -->
      <div class="overflow-x-auto relative flex-1 flex flex-col overflow-y-scroll scrollbar-none">
          <table class="w-full text-left text-sm text-gray-400 table-fixed">
              <thead class="bg-dark-900 text-xs uppercase font-bold text-white tracking-wider sticky top-0 z-20 shadow-md border-b border-white/5">
                  <tr>
                      <th class="px-6 py-4 cursor-pointer hover:text-red-400 transition-colors group select-none w-[180px]" @click="sortBy('created_at')">
                          <div class="flex items-center gap-2">
                              Дата / Время
                              <svg v-if="sortKey === 'created_at'" class="w-4 h-4 transition-transform flex-shrink-0" :class="sortOrder === 'asc' ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                              <svg v-else class="w-4 h-4 opacity-0 group-hover:opacity-50 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                          </div>
                      </th>
                      <th class="px-6 py-4 cursor-pointer hover:text-red-400 transition-colors group select-none w-[220px]" @click="sortBy('user_name')">
                          <div class="flex items-center gap-2">
                              Пользователь
                              <svg v-if="sortKey === 'user_name'" class="w-4 h-4 transition-transform flex-shrink-0" :class="sortOrder === 'asc' ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                              <svg v-else class="w-4 h-4 opacity-0 group-hover:opacity-50 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                          </div>
                      </th>
                      <th class="px-6 py-4 cursor-pointer hover:text-red-400 transition-colors group select-none w-[130px]" @click="sortBy('action')">
                          <div class="flex items-center gap-2">
                              Действие
                              <svg v-if="sortKey === 'action'" class="w-4 h-4 transition-transform flex-shrink-0" :class="sortOrder === 'asc' ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                              <svg v-else class="w-4 h-4 opacity-0 group-hover:opacity-50 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                          </div>
                      </th>
                      <th class="px-6 py-4 cursor-pointer hover:text-red-400 transition-colors group select-none w-[140px]" @click="sortBy('entity_type')">
                          <div class="flex items-center gap-2">
                              Сущность
                              <svg v-if="sortKey === 'entity_type'" class="w-4 h-4 transition-transform flex-shrink-0" :class="sortOrder === 'asc' ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                              <svg v-else class="w-4 h-4 opacity-0 group-hover:opacity-50 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                          </div>
                      </th>
                      <th class="px-6 py-4 cursor-pointer hover:text-red-400 transition-colors group select-none" @click="sortBy('entity_name')">
                          <div class="flex items-center gap-2">
                              Объект
                              <svg v-if="sortKey === 'entity_name'" class="w-4 h-4 transition-transform flex-shrink-0" :class="sortOrder === 'asc' ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                              <svg v-else class="w-4 h-4 opacity-0 group-hover:opacity-50 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                          </div>
                      </th>
                      <th class="px-6 py-4 w-[100px] text-center">Детали</th>
                  </tr>
              </thead>
              <tbody class="transition-opacity duration-300" :class="{ 'opacity-50': isLoading && loadingType === 'search' }">
                  <template v-for="log in logs" :key="log.id">
                      <tr 
                          class="border-b border-white/5 transition-colors group"
                          :class="[
                              expandedLogId === log.id ? 'bg-white/[0.03]' : 'hover:bg-white/5',
                              (hasChanges(log) || hasMetadata(log)) ? 'cursor-pointer' : ''
                          ]"
                          @click="(hasChanges(log) || hasMetadata(log)) && toggleExpand(log.id)"
                      >
                          <!-- Date/Time -->
                          <td class="px-6 py-4 font-mono text-xs text-gray-300 whitespace-nowrap">
                              {{ log.createdAt }}
                          </td>

                          <!-- User -->
                          <td class="px-6 py-4">
                              <div class="flex items-center gap-2">
                                  <div class="w-7 h-7 rounded-full flex items-center justify-center text-[10px] font-bold text-white border shrink-0"
                                       :class="log.userRole === 'super_admin' ? 'bg-red-600 border-red-500' : log.userRole === 'moderator' ? 'bg-purple-600 border-purple-500' : 'bg-cyan-600 border-cyan-500'">
                                      {{ log.userName.split(' ').map(w => w[0]).join('').toUpperCase().slice(0, 2) }}
                                  </div>
                                  <span class="text-white text-sm font-medium truncate">{{ log.userName }}</span>
                              </div>
                          </td>

                          <!-- Action badge -->
                          <td class="px-6 py-4">
                              <span class="inline-flex items-center px-2.5 py-1 rounded text-[10px] font-bold uppercase tracking-wider border"
                                    :class="actionColor(log.action)">
                                  {{ actionLabel(log.action) }}
                              </span>
                          </td>

                          <!-- Entity type -->
                          <td class="px-6 py-4">
                              <div class="flex items-center gap-2">
                                  <svg class="w-4 h-4 shrink-0" :class="entityTypeColor(log.entityType)" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="entityTypeIcon(log.entityType)" />
                                  </svg>
                                  <span class="text-sm" :class="entityTypeColor(log.entityType)">{{ entityTypeLabel(log.entityType) }}</span>
                              </div>
                          </td>

                          <!-- Entity name -->
                          <td class="px-6 py-4 text-white font-medium text-sm truncate">
                              {{ log.entityName }}
                          </td>

                          <!-- Expand button -->
                          <td class="px-6 py-4 text-center">
                              <button v-if="hasChanges(log) || hasMetadata(log)"
                                      class="text-gray-500 hover:text-white transition-colors p-1.5 rounded-lg hover:bg-white/10"
                                      @click.stop="toggleExpand(log.id)">
                                  <svg class="w-5 h-5 transition-transform duration-200" :class="expandedLogId === log.id ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                  </svg>
                              </button>
                              <span v-else class="text-gray-600">—</span>
                          </td>
                      </tr>

                      <!-- Expanded row: Changes diff -->
                      <tr v-if="expandedLogId === log.id && (hasChanges(log) || hasMetadata(log))">
                          <td colspan="6" class="px-6 py-0">
                              <div class="py-4 pl-4 border-l-2 border-red-500/30 ml-2 space-y-2 animate-in slide-in-from-top-2 duration-200">
                                  
                                  <!-- Changes -->
                                  <template v-if="hasChanges(log)">
                                      <div v-for="(change, field) in log.changes" :key="field" 
                                           class="flex items-start gap-3 py-1.5 px-3 rounded-lg bg-white/[0.02] hover:bg-white/[0.04] transition-colors">
                                          <span class="text-xs text-gray-500 font-bold uppercase tracking-wider min-w-[140px] pt-0.5 shrink-0">{{ fieldLabel(field) }}</span>
                                          <div class="flex items-center gap-2 flex-wrap min-w-0">
                                              <span class="text-red-400/80 line-through text-sm font-mono break-all">{{ formatValue(change.old) }}</span>
                                              <svg class="w-4 h-4 text-gray-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3" />
                                              </svg>
                                              <span class="text-emerald-400 text-sm font-mono font-semibold break-all">{{ formatValue(change.new) }}</span>
                                          </div>
                                      </div>
                                  </template>

                                  <!-- Metadata (for bids) -->
                                  <template v-if="hasMetadata(log) && !hasChanges(log)">
                                      <div v-for="(value, key) in log.metadata" :key="key"
                                           class="flex items-center gap-3 py-1.5 px-3 rounded-lg bg-white/[0.02]">
                                          <span class="text-xs text-gray-500 font-bold uppercase tracking-wider min-w-[140px] shrink-0">{{ fieldLabel(key) }}</span>
                                          <span class="text-white text-sm font-mono">{{ formatValue(value) }}</span>
                                      </div>
                                  </template>
                              </div>
                          </td>
                      </tr>
                  </template>

                  <!-- Skeletons -->
                  <tr v-if="isLoading && (loadingType === 'initial' || loadingType === 'scroll')" v-for="i in 8" :key="`skeleton-${i}`" class="animate-pulse">
                      <td class="px-6 py-4"><div class="h-4 w-32 bg-white/10 rounded"></div></td>
                      <td class="px-6 py-4"><div class="flex items-center gap-2"><div class="w-7 h-7 rounded-full bg-white/10"></div><div class="h-4 w-24 bg-white/10 rounded"></div></div></td>
                      <td class="px-6 py-4"><div class="h-5 w-20 bg-white/10 rounded"></div></td>
                      <td class="px-6 py-4"><div class="h-4 w-20 bg-white/10 rounded"></div></td>
                      <td class="px-6 py-4"><div class="h-4 w-36 bg-white/10 rounded"></div></td>
                      <td class="px-6 py-4"><div class="h-4 w-6 bg-white/10 rounded mx-auto"></div></td>
                  </tr>
              </tbody>
          </table>
          
          <!-- No Results -->
          <div v-if="!isLoading && logs.length === 0" class="flex flex-col items-center justify-center py-16 text-gray-500 animate-in fade-in duration-500">
               <svg class="w-16 h-16 mb-4 text-gray-700" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                   <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
               </svg>
               <p class="text-sm font-medium">Записей не найдено</p>
               <p class="text-xs mt-1">Действия будут появляться здесь автоматически</p>
          </div>

          <!-- Infinite Scroll Trigger -->
          <div ref="observerTarget" class="py-4 text-center text-gray-500 text-xs uppercase tracking-widest font-bold h-4">
          </div>
      </div>
  </div>
</template>
