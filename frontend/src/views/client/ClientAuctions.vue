<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import axios from 'axios'

const router = useRouter()
const auctions = ref([])
const isLoading = ref(true)
const activeFilter = ref('all')
const timerNow = ref(Date.now())
let timerInterval = null

const statusMap = {
    draft: { label: 'Черновик', color: 'bg-gray-100 text-gray-500 border-gray-200 dark:bg-gray-500/20 dark:text-gray-400 dark:border-gray-500/30' },
    collecting_offers: { label: 'Сбор предложений', color: 'bg-cyan-100 text-cyan-600 border-cyan-200 dark:bg-cyan-500/20 dark:text-cyan-400 dark:border-cyan-500/30' },
    scheduled: { label: 'Запланирован', color: 'bg-blue-100 text-blue-600 border-blue-200 dark:bg-blue-500/20 dark:text-blue-400 dark:border-blue-500/30' },
    active: { label: 'Активный', color: 'bg-green-100 text-green-600 border-green-200 dark:bg-green-500/20 dark:text-green-400 dark:border-green-500/30' },
    gpb_right: { label: 'Право ГПБ', color: 'bg-purple-100 text-purple-600 border-purple-200 dark:bg-purple-500/20 dark:text-purple-400 dark:border-purple-500/30' },
    commission: { label: 'Работа комиссии', color: 'bg-yellow-100 text-yellow-600 border-yellow-200 dark:bg-yellow-500/20 dark:text-yellow-400 dark:border-yellow-500/30' },
    completed: { label: 'Завершён', color: 'bg-emerald-100 text-emerald-600 border-emerald-200 dark:bg-emerald-500/20 dark:text-emerald-400 dark:border-emerald-500/30' },
    paused: { label: 'Приостановлен', color: 'bg-orange-100 text-orange-600 border-orange-200 dark:bg-orange-500/20 dark:text-orange-400 dark:border-orange-500/30' },
    cancelled: { label: 'Отменён', color: 'bg-red-100 text-red-600 border-red-200 dark:bg-red-500/20 dark:text-red-400 dark:border-red-500/30' },
}

const filters = [
    { id: 'all', name: 'Все аукционы' },
    { id: 'collecting', name: 'Сбор предложений' },
    { id: 'active', name: 'Активные' },
    { id: 'gpb', name: 'Право ГПБ' },
    { id: 'completed', name: 'Завершённые' },
]

const fetchAuctions = async () => {
    isLoading.value = true
    try {
        const params = {}
        if (activeFilter.value !== 'all') params.filter = activeFilter.value
        const response = await axios.get('/api/client/auctions', { params })
        auctions.value = response.data
    } catch (e) {
        console.error('Failed to load auctions:', e)
    } finally {
        isLoading.value = false
    }
}

const formatPrice = (val) => {
    if (!val) return '—'
    return Number(val).toLocaleString('ru-RU', { minimumFractionDigits: 2 })
}

const formatDate = (iso) => {
    if (!iso) return '—'
    return new Date(iso).toLocaleString('ru-RU', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' })
}

const getCountdown = (auction) => {
    if (!auction.end_at) return null
    if (!['active', 'collecting_offers', 'scheduled'].includes(auction.status)) return null
    const endMs = new Date(auction.end_at).getTime()
    const diff = Math.floor((endMs - timerNow.value) / 1000)
    if (diff <= 0) return '00:00'
    const hours = Math.floor(diff / 3600)
    const mins = Math.floor((diff % 3600) / 60)
    const secs = diff % 60
    if (hours > 0) return `${hours}ч ${String(mins).padStart(2, '0')}м`
    return `${String(mins).padStart(2, '0')}:${String(secs).padStart(2, '0')}`
}

const getGpbCountdown = (auction) => {
    if (auction.status !== 'gpb_right' || !auction.gpb_started_at) return null
    const gpbMinutes = auction.gpb_minutes || 30
    const endMs = new Date(auction.gpb_started_at).getTime() + gpbMinutes * 60 * 1000
    const diff = Math.floor((endMs - timerNow.value) / 1000)
    if (diff <= 0) return '00:00'
    const mins = Math.floor(diff / 60)
    const secs = diff % 60
    return `${String(mins).padStart(2, '0')}:${String(secs).padStart(2, '0')}`
}

const isCountdownCritical = (auction) => {
    if (!auction.end_at) return false
    const endMs = new Date(auction.end_at).getTime()
    const diff = Math.floor((endMs - timerNow.value) / 1000)
    return diff >= 0 && diff <= 60
}

onMounted(() => {
    fetchAuctions()
    timerInterval = setInterval(() => { timerNow.value = Date.now() }, 1000)
})

onUnmounted(() => {
    if (timerInterval) clearInterval(timerInterval)
})
</script>

<template>
  <div class="space-y-6">
      <!-- Filters -->
      <div class="flex flex-wrap items-center gap-3">
          <button v-for="f in filters" :key="f.id"
                  @click="activeFilter = f.id; fetchAuctions()"
                  class="px-4 py-2 rounded-lg text-sm font-bold uppercase tracking-wider border transition-all"
                  :class="activeFilter === f.id ? 'client-filter-active' : 'client-filter'">
              {{ f.name }}
          </button>
      </div>

      <!-- Loading -->
      <div v-if="isLoading" class="flex items-center justify-center py-20">
          <div class="w-8 h-8 border-2 border-gold-500 border-t-transparent rounded-full animate-spin"></div>
      </div>

      <!-- Empty state -->
      <div v-else-if="auctions.length === 0" class="client-card backdrop-blur-sm p-12 text-center">
          <svg class="w-16 h-16 mx-auto text-gray-600 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
          </svg>
          <p class="text-gray-500 font-medium">Нет аукционов в данной категории</p>
      </div>

      <!-- Auction Cards -->
      <div v-else class="grid grid-cols-1 gap-4">
          <div v-for="auction in auctions" :key="auction.id"
               @click="router.push(`/client/auctions/${auction.id}`)"
               class="bg-white dark:bg-stone-900/90 backdrop-blur-sm rounded-xl border p-5 cursor-pointer transition-all hover:border-gold-500/30 hover:bg-gray-50 dark:hover:bg-stone-800 group shadow-sm hover:shadow-md"
               :class="auction.is_winning ? 'border-green-200 bg-green-50/50 dark:border-green-500/20 dark:bg-green-500/5' : 'border-gray-200 dark:border-white/5'">
              <div class="flex items-start justify-between gap-4 flex-wrap">
                  <div class="flex-1 min-w-0">
                      <div class="flex items-center gap-3 mb-2 flex-wrap">
                          <span class="px-2.5 py-1 rounded text-[10px] font-bold uppercase tracking-wider border"
                                :class="statusMap[auction.status]?.color || 'bg-gray-100 text-gray-400 border-gray-200 dark:bg-gray-500/20 dark:text-gray-400 dark:border-gray-500/30'">
                              {{ statusMap[auction.status]?.label || auction.status }}
                          </span>
                          <span v-if="auction.is_winning" class="px-2.5 py-1 rounded text-[10px] font-bold uppercase tracking-wider bg-green-100 text-green-600 border border-green-200 dark:bg-green-500/20 dark:text-green-400 dark:border-green-500/30">
                              🏆 Ведёте
                          </span>
                      </div>
                      <h3 class="text-lg font-kanit font-bold text-gray-900 dark:text-gray-100 group-hover:text-gold-600 dark:group-hover:text-gold-400 transition-colors truncate">{{ auction.title }}</h3>
                      <p v-if="auction.description" class="text-xs text-gray-500 mt-1 line-clamp-1">{{ auction.description }}</p>
                  </div>

                  <!-- Countdown on card -->
                  <div class="flex flex-col items-end gap-2 flex-shrink-0">
                      <div v-if="getCountdown(auction)"
                           class="px-4 py-2 rounded-lg border font-mono text-lg font-black"
                           :class="isCountdownCritical(auction) ? 'text-red-600 dark:text-red-400 border-red-200 dark:border-red-500/30 bg-red-50 dark:bg-red-500/10 animate-pulse' : 'text-gray-900 dark:text-white border-gray-200 dark:border-white/10 bg-gray-50 dark:bg-dark-900/40'">
                          {{ getCountdown(auction) }}
                      </div>
                      <div v-else-if="getGpbCountdown(auction)"
                           class="px-4 py-2 rounded-lg border border-purple-500/30 bg-purple-500/10 font-mono text-lg font-black text-purple-400">
                          🏛 {{ getGpbCountdown(auction) }}
                      </div>
                  </div>
              </div>

              <!-- Details bar -->
              <div class="flex items-center gap-6 mt-4 text-xs text-gray-500">
                  <div class="flex items-center gap-1.5">
                      <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                      {{ formatDate(auction.start_at) }}
                  </div>
                  <div v-if="auction.bar_count && auction.bar_weight" class="flex items-center gap-1.5 font-mono">
                      <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" /></svg>
                      {{ auction.bar_count }} сл. · {{ (auction.bar_count * auction.bar_weight).toFixed(1) }} кг
                  </div>
                  <div v-if="auction.min_price" class="flex items-center gap-1.5 font-mono">
                      <span class="font-sans">₽</span>&nbsp;{{ formatPrice(auction.min_price) }}/кг
                  </div>
                  <div v-if="auction.my_bids_count" class="text-gold-400 font-bold">
                      {{ auction.my_bids_count }} ставок
                  </div>
                  <div v-if="auction.my_offers_count" class="text-cyan-400 font-bold">
                      {{ auction.my_offers_count }} предл.
                  </div>
              </div>
          </div>
      </div>
  </div>
</template>
