<script setup>
import { ref, watch, onMounted, onUnmounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import StandardModal from '../../components/ui/StandardModal.vue'
import axios from 'axios'

// --- State ---
const auctions = ref([])
const searchQuery = ref('')
const filterStatus = ref('')
const sortKey = ref('start_at')
const sortOrder = ref('desc')
const page = ref(1)
const perPage = 50
const isLoading = ref(false)
const hasMore = ref(true)
const loadingType = ref('initial')
let searchTimeout = null
let abortController = null

// --- Delete ---
const showConfirmModal = ref(false)
const deleteId = ref(null)
const selectedAuction = ref(null)

const router = useRouter()

// --- Status Options ---
const statusOptions = [
    { value: 'draft', label: 'Черновик', color: 'bg-gray-500/20 text-gray-400' },
    { value: 'collecting_offers', label: 'Сбор предложений', color: 'bg-cyan-500/20 text-cyan-400' },
    { value: 'scheduled', label: 'Запланирован', color: 'bg-purple-500/20 text-purple-400' },
    { value: 'active', label: 'Активный', color: 'bg-amber-500/20 text-amber-400' },
    { value: 'gpb_right', label: 'Право ГПБ', color: 'bg-blue-500/20 text-blue-400' },
    { value: 'commission', label: 'Работа комиссии', color: 'bg-orange-500/20 text-orange-400' },
    { value: 'completed', label: 'Завершён', color: 'bg-emerald-500/20 text-emerald-400' },
    { value: 'paused', label: 'Приостановлен', color: 'bg-amber-800/20 text-amber-700' },
    { value: 'cancelled', label: 'Отменён', color: 'bg-red-500/20 text-red-400' },
]

const getStatusClass = (status) => {
    return statusOptions.find(s => s.value === status)?.color || 'bg-gray-500/20 text-gray-400'
}

const getStatusLabel = (status) => {
    return statusOptions.find(s => s.value === status)?.label || status
}

const formatDate = (iso) => {
    if (!iso) return '—'
    const d = new Date(iso)
    return d.toLocaleDateString('ru-RU', { day: '2-digit', month: '2-digit', year: 'numeric' }) + ', ' + d.toLocaleTimeString('ru-RU', { hour: '2-digit', minute: '2-digit' })
}

// --- API: Load Auctions ---
const loadMore = async (reset = false) => {
    if (isLoading.value && !reset) return

    // Отменяем предыдущий запрос при reset (новый поиск/фильтр)
    if (reset && abortController) {
        abortController.abort()
    }
    abortController = new AbortController()
    const currentController = abortController

    if (reset) {
        page.value = 1
        hasMore.value = true
        auctions.value = []
        loadingType.value = searchQuery.value || filterStatus.value ? 'search' : 'initial'
    } else {
        loadingType.value = 'scroll'
    }

    isLoading.value = true

    try {
        const params = {
            page: page.value,
            per_page: perPage,
            sort: sortKey.value,
            order: sortOrder.value,
        }
        if (searchQuery.value) params.search = searchQuery.value
        if (filterStatus.value) params.status = filterStatus.value

        const response = await axios.get('/api/auctions', { params, signal: currentController.signal })
        const data = response.data.data || response.data

        // Игнорируем ответ, если запрос был отменён (новый запрос уже ушёл)
        if (currentController.signal.aborted) return

        if (reset) {
            auctions.value = data
        } else {
            auctions.value.push(...data)
        }

        if (data.length >= perPage) {
            page.value++
            hasMore.value = true
        } else {
            hasMore.value = false
        }

    } catch (e) {
        if (e.name === 'CanceledError' || e.name === 'AbortError') return
        console.error('Failed to load auctions:', e)
    } finally {
        if (!currentController.signal.aborted) {
            isLoading.value = false
            loadingType.value = 'initial'
        }
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

// --- Create Draft & Navigate ---
const isCreating = ref(false)
const createDraftAndNavigate = async () => {
    if (isCreating.value) return
    isCreating.value = true
    try {
        const response = await axios.post('/api/auctions', { status: 'draft' })
        const newId = response.data.id
        router.push({ name: 'AdminAuctionDetail', params: { id: newId }, query: { new: '1' } })
    } catch (e) {
        console.error('Failed to create draft:', e)
        alert('Ошибка создания аукциона: ' + (e.response?.data?.message || e.message))
    } finally {
        isCreating.value = false
    }
}

// --- Delete ---
const initiateDelete = (id) => {
    deleteId.value = id
    selectedAuction.value = auctions.value.find(a => a.id === id)
    showConfirmModal.value = true
}

const confirmDelete = async () => {
    if (deleteId.value) {
        try {
            await axios.delete(`/api/auctions/${deleteId.value}`)
            resetList()
        } catch (e) {
            console.error('Delete failed:', e)
        }
    }
    deleteId.value = null
    selectedAuction.value = null
    showConfirmModal.value = false
}

// --- Keyboard Shortcuts ---
const searchInputRef = ref(null)

const clearSearchByEsc = (e) => {
    searchQuery.value = ''
    resetList()
    e.preventDefault()
    if (document.activeElement === searchInputRef.value) {
        searchInputRef.value.blur()
    }
}

const handleGlobalKeydown = async (e) => {
    if (showConfirmModal.value) return

    if (e.key === 'Escape') {
        if (searchQuery.value) {
            clearSearchByEsc(e)
            return
        }
    }
    if (e.key === 'Insert') {
        createDraftAndNavigate()
        e.preventDefault()
        return
    }
    if (e.ctrlKey || e.metaKey || e.altKey || e.shiftKey) return
    if (['INPUT', 'TEXTAREA', 'SELECT'].includes(document.activeElement.tagName)) return

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
    }
}

// --- Intersection Observer ---
const observerTarget = ref(null)

watch(searchQuery, () => {
    clearTimeout(searchTimeout)
    searchTimeout = setTimeout(() => {
        resetList()
    }, 300)
})

watch(filterStatus, () => {
    resetList()
})

onMounted(() => {
    const route = useRoute()

    // Handle Search/Filter from Query
    const hasSearchFromUrl = !!route.query.search || !!route.query.status
    if (route.query.search) {
        searchQuery.value = route.query.search
    }

    if (route.query.status) {
        filterStatus.value = route.query.status
    }

    // Если search/status заданы из URL, watcher сам вызовет resetList
    if (!hasSearchFromUrl) {
        resetList()
    }

    // Handle Actions
    if (route.query.action === 'create') {
        createDraftAndNavigate()
    }

    if (route.query.sort === 'newest') {
        sortBy('created_at')
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
    clearTimeout(searchTimeout)
    if (abortController) abortController.abort()
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
                        placeholder="Поиск по таблице..." 
                        class="w-full bg-dark-900 border border-white/10 rounded-lg pl-10 pr-4 py-2 text-sm text-white focus:border-red-500 focus:outline-none focus:ring-1 focus:ring-red-500 transition-all placeholder-white/20" 
                        @keydown.esc="clearSearchByEsc"
                    />
              </div>

              <!-- Status Filter -->
              <div class="relative w-full sm:w-56 flex-shrink-0">
                  <select 
                      v-model="filterStatus"
                      class="no-arrow w-full bg-dark-900 border border-white/10 rounded-lg pl-4 pr-10 py-2 text-sm text-white focus:border-red-500 focus:outline-none focus:ring-1 focus:ring-red-500 transition-all cursor-pointer hover:border-white/20"
                  >
                      <option value="">Все статусы</option>
                      <option v-for="option in statusOptions" :key="option.value" :value="option.value">
                          {{ option.label }}
                      </option>
                  </select>
                  <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-3 text-gray-500">
                      <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                      </svg>
                  </div>
              </div>
          </div>

          <button 
            @click="createDraftAndNavigate()"
            :disabled="isCreating"
            class="flex items-center justify-center gap-2 px-4 py-2 bg-transparent hover:bg-red-500/10 text-red-400 hover:text-red-300 rounded-lg transition-colors font-bold text-sm border border-red-500/50 hover:border-red-400 w-full md:w-auto disabled:opacity-50 disabled:cursor-not-allowed"
          >
              <svg v-if="!isCreating" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
              </svg>
              <svg v-else class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
              </svg>
              {{ isCreating ? 'Создание...' : 'Добавить' }}
          </button>
      </div>

      <div class="overflow-x-auto relative flex-1 flex flex-col overflow-y-scroll scrollbar-none">
          <table class="w-full text-left text-sm text-gray-400 min-w-max">
              <thead class="bg-dark-900 text-xs uppercase font-bold text-white tracking-wider sticky top-0 z-20 shadow-md border-b border-white/5">
                  <tr>
                      <th class="px-6 py-4 cursor-pointer hover:text-red-400 transition-colors group select-none w-16" @click="sortBy('id')">
                          <div class="flex items-center gap-2">
                              №
                              <svg v-if="sortKey === 'id'" class="w-4 h-4 transition-transform flex-shrink-0" :class="sortOrder === 'asc' ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                              <svg v-else class="w-4 h-4 opacity-0 group-hover:opacity-50 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                          </div>
                      </th>
                      <th class="px-6 py-4 cursor-pointer hover:text-red-400 transition-colors group select-none" @click="sortBy('title')">
                          <div class="flex items-center gap-2">
                              Название
                              <svg v-if="sortKey === 'title'" class="w-4 h-4 transition-transform flex-shrink-0" :class="sortOrder === 'asc' ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                              <svg v-else class="w-4 h-4 opacity-0 group-hover:opacity-50 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                          </div>
                      </th>
                      <th class="px-6 py-4 select-none w-[160px]">Лот</th>
                      <th class="px-6 py-4 cursor-pointer hover:text-red-400 transition-colors group select-none w-[160px]" @click="sortBy('status')">
                          <div class="flex items-center gap-2">
                              Статус
                              <svg v-if="sortKey === 'status'" class="w-4 h-4 transition-transform flex-shrink-0" :class="sortOrder === 'asc' ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                              <svg v-else class="w-4 h-4 opacity-0 group-hover:opacity-50 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                          </div>
                      </th>
                      <th class="px-6 py-4 cursor-pointer hover:text-red-400 transition-colors group select-none w-[140px]" @click="sortBy('start_at')">
                          <div class="flex items-center gap-2">
                              Начало
                              <svg v-if="sortKey === 'start_at'" class="w-4 h-4 transition-transform flex-shrink-0" :class="sortOrder === 'asc' ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                              <svg v-else class="w-4 h-4 opacity-0 group-hover:opacity-50 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                          </div>
                      </th>
                      <th class="px-6 py-4 cursor-pointer hover:text-red-400 transition-colors group select-none w-[140px]" @click="sortBy('end_at')">
                          <div class="flex items-center gap-2">
                              Окончание
                              <svg v-if="sortKey === 'end_at'" class="w-4 h-4 transition-transform flex-shrink-0" :class="sortOrder === 'asc' ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                              <svg v-else class="w-4 h-4 opacity-0 group-hover:opacity-50 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                          </div>
                      </th>
                      <th class="px-6 py-4 text-right w-28">Действия</th>
                  </tr>
              </thead>
              <tbody class="transition-opacity duration-300" :class="{ 'opacity-50': isLoading && loadingType === 'search' }">
                  <tr v-for="auction in auctions" :key="auction.id" class="border-b border-white/5 hover:bg-white/5 transition-colors group cursor-pointer" @click="router.push(`/admin/auctions/${auction.id}`)">
                      <td class="px-6 py-4 font-mono text-xs text-gray-500">{{ auction.id }}</td>
                      <td class="px-6 py-4">
                          <div class="flex flex-col">
                              <span class="font-bold text-white text-base">{{ auction.title }}</span>
                              <div class="flex items-center gap-3 mt-0.5">
                                  <span class="text-xs text-gold-400/70 font-mono"><span class="font-sans">₽</span>&nbsp;{{ auction.min_price ? Number(auction.min_price).toLocaleString('ru-RU') : '—' }}/кг</span>
                                  <span v-if="auction.auction_participants_count > 0" class="text-xs text-gray-500">
                                      <svg class="w-3 h-3 inline mr-0.5 -mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" /></svg>
                                      {{ auction.auction_participants_count }}
                                  </span>
                              </div>
                          </div>
                      </td>
                      <td class="px-6 py-4">
                          <div v-if="auction.bar_count && auction.bar_weight" class="flex flex-col whitespace-nowrap">
                              <span class="text-sm text-white font-mono font-bold">{{ auction.bar_count }} × {{ auction.bar_weight }} кг</span>
                              <span class="text-xs text-gray-500 font-mono">= {{ (auction.bar_count * auction.bar_weight).toFixed(1) }} кг</span>
                          </div>
                          <span v-else class="text-xs text-gray-600">—</span>
                      </td>
                      <td class="px-6 py-4">
                          <span class="px-2 py-1 rounded text-xs font-bold uppercase whitespace-nowrap" :class="getStatusClass(auction.status)">
                              {{ getStatusLabel(auction.status) }}
                          </span>
                      </td>
                      <td class="px-6 py-4 font-mono text-xs">{{ formatDate(auction.start_at) }}</td>
                      <td class="px-6 py-4 font-mono text-xs">{{ formatDate(auction.end_at) }}</td>
                      <td class="px-6 py-4 text-right" @click.stop>
                          <div class="flex items-center justify-end gap-1">
                          <button 
                            @click="router.push(`/admin/auctions/${auction.id}`)"
                            class="text-gray-500 hover:text-blue-400 transition-colors p-2 rounded-lg hover:bg-blue-500/10"
                            title="Открыть"
                          >
                              <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                              </svg>
                          </button>
                          <button 
                            @click="initiateDelete(auction.id)"
                            class="text-gray-500 hover:text-red-500 transition-colors p-2 rounded-lg hover:bg-red-500/10"
                            title="Удалить"
                          >
                              <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                              </svg>
                          </button>
                          </div>
                      </td>
                  </tr>
                  <!-- Skeletons -->
                  <tr v-if="isLoading && (loadingType === 'initial' || loadingType === 'scroll' || auctions.length === 0)" v-for="i in 5" :key="`skeleton-${i}`" class="animate-pulse">
                      <td class="px-6 py-4"><div class="h-4 w-8 bg-white/10 rounded"></div></td>
                      <td class="px-6 py-4">
                          <div class="flex flex-col gap-2">
                              <div class="h-4 w-48 bg-white/10 rounded"></div>
                              <div class="h-3 w-24 bg-white/10 rounded"></div>
                          </div>
                      </td>
                      <td class="px-6 py-4">
                          <div class="flex flex-col gap-1">
                              <div class="h-4 w-20 bg-white/10 rounded"></div>
                              <div class="h-3 w-16 bg-white/10 rounded"></div>
                          </div>
                      </td>
                      <td class="px-6 py-4"><div class="h-6 w-20 bg-white/10 rounded"></div></td>
                      <td class="px-6 py-4"><div class="h-4 w-24 bg-white/10 rounded"></div></td>
                      <td class="px-6 py-4"><div class="h-4 w-24 bg-white/10 rounded"></div></td>
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
          <div v-if="!isLoading && auctions.length === 0" class="flex flex-col items-center justify-center py-12 text-gray-500 animate-in fade-in duration-500">
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

      <!-- Delete Confirmation Modal -->
      <StandardModal 
          :is-open="showConfirmModal" 
          theme="red"
          z-index-class="z-[200]"
          backdrop-z-index-class="z-[150]"
          :backdrop-blur="false"
          @close="showConfirmModal = false"
      >
          <div class="text-center pt-2">
              <h3 class="text-xl font-kanit font-bold text-white tracking-wide uppercase mb-6">Удалить<span class="text-2xl">?</span></h3>
              <p class="text-gray-400 text-sm mb-2 font-light">
                  Аукцион
              </p>
              <p class="text-white font-bold text-lg mb-2">
                  {{ selectedAuction?.title }}
              </p>
              <p class="text-red-400 text-sm font-semibold mb-6 tracking-wide">
                  будет удалён безвозвратно
              </p>
              
              <div class="flex gap-3">
                  <button @click="showConfirmModal = false" class="flex-1 py-3 rounded-lg text-xs font-bold uppercase tracking-widest text-gray-400 hover:text-white bg-white/5 hover:bg-white/10 transition-colors border border-transparent hover:border-white/10">
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
