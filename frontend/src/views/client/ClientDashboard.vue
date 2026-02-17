<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import axios from 'axios'

const router = useRouter()
const stats = ref({})
const recentAuctions = ref([])
const isLoading = ref(true)
const timerNow = ref(Date.now())
let timerInterval = null

const statusLabels = {
    draft: 'Черновик',
    collecting_offers: 'Сбор предложений',
    scheduled: 'Запланирован',
    active: 'Активный',
    gpb_right: 'Право ГПБ',
    commission: 'Работа комиссии',
    completed: 'Завершён',
    paused: 'Приостановлен',
    cancelled: 'Отменён',
}

const statusColors = {
    draft: 'text-gray-400',
    collecting_offers: 'text-cyan-400',
    scheduled: 'text-blue-400',
    active: 'text-green-400',
    gpb_right: 'text-purple-400',
    commission: 'text-yellow-400',
    completed: 'text-emerald-400',
    paused: 'text-orange-400',
    cancelled: 'text-red-400',
}

const statusBadgeColors = {
    draft: 'bg-gray-100 text-gray-500 border-gray-200 dark:bg-gray-500/20 dark:text-gray-400 dark:border-white/5',
    collecting_offers: 'bg-cyan-100 text-cyan-600 border-cyan-200 dark:bg-cyan-500/20 dark:text-cyan-400 dark:border-white/5',
    scheduled: 'bg-blue-100 text-blue-600 border-blue-200 dark:bg-blue-500/20 dark:text-blue-400 dark:border-white/5',
    active: 'bg-green-100 text-green-600 border-green-200 dark:bg-green-500/20 dark:text-green-400 dark:border-white/5',
    gpb_right: 'bg-purple-100 text-purple-600 border-purple-200 dark:bg-purple-500/20 dark:text-purple-400 dark:border-white/5',
    commission: 'bg-yellow-100 text-yellow-600 border-yellow-200 dark:bg-yellow-500/20 dark:text-yellow-400 dark:border-white/5',
    completed: 'bg-emerald-100 text-emerald-600 border-emerald-200 dark:bg-emerald-500/20 dark:text-emerald-400 dark:border-white/5',
    paused: 'bg-orange-100 text-orange-600 border-orange-200 dark:bg-orange-500/20 dark:text-orange-400 dark:border-white/5',
    cancelled: 'bg-red-100 text-red-600 border-red-200 dark:bg-red-500/20 dark:text-red-400 dark:border-white/5',
}

const fetchData = async () => {
    isLoading.value = true
    try {
        const [statsRes, auctionsRes] = await Promise.all([
            axios.get('/api/client/stats'),
            axios.get('/api/client/auctions'),
        ])
        stats.value = statsRes.data
        recentAuctions.value = auctionsRes.data.slice(0, 5)
    } catch (e) {
        console.error('Failed to load dashboard:', e)
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
    if (diff <= 0) return null
    const hours = Math.floor(diff / 3600)
    const mins = Math.floor((diff % 3600) / 60)
    const secs = diff % 60
    if (hours > 0) return `${hours}ч ${String(mins).padStart(2, '0')}м`
    return `${String(mins).padStart(2, '0')}:${String(secs).padStart(2, '0')}`
}

const statCards = computed(() => [
    { 
        label: 'Активные аукционы', 
        value: stats.value.active_auctions || 0, 
        icon: '🔴', 
        text: 'text-green-600 dark:text-green-400',
        color: 'dark:from-green-500/20 dark:to-green-500/5 dark:border-green-500/20 border-green-200' 
    },
    { 
        label: 'Всего аукционов', 
        value: stats.value.total_auctions || 0, 
        icon: '📊', 
        text: 'text-blue-600 dark:text-blue-400',
        color: 'dark:from-blue-500/20 dark:to-blue-500/5 dark:border-blue-500/20 border-blue-200' 
    },
    { 
        label: 'Побед', 
        value: stats.value.won_auctions || 0, 
        icon: '🏆', 
        text: 'text-yellow-600 dark:text-gold-400',
        color: 'dark:from-gold-500/20 dark:to-gold-500/5 dark:border-gold-500/20 border-yellow-200' 
    },
    { 
        label: 'Ставок', 
        value: stats.value.total_bids || 0, 
        icon: '⚡', 
        text: 'text-purple-600 dark:text-purple-400',
        color: 'dark:from-purple-500/20 dark:to-purple-500/5 dark:border-purple-500/20 border-purple-200' 
    },
    { 
        label: 'Предложений', 
        value: stats.value.total_offers || 0, 
        icon: '📋', 
        text: 'text-cyan-600 dark:text-cyan-400',
        color: 'dark:from-cyan-500/20 dark:to-cyan-500/5 dark:border-cyan-500/20 border-cyan-200' 
    },
])

onMounted(() => {
    fetchData()
    timerInterval = setInterval(() => { timerNow.value = Date.now() }, 1000)
})

onUnmounted(() => {
    if (timerInterval) clearInterval(timerInterval)
})
</script>

<template>
  <div class="space-y-8">
      <!-- Stats Grid -->
      <div v-if="isLoading" class="flex items-center justify-center py-16">
          <div class="w-8 h-8 border-2 border-gold-500 border-t-transparent rounded-full animate-spin"></div>
      </div>

      <template v-else>
          <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-4">
              <div v-for="card in statCards" :key="card.label"
                   class="relative overflow-hidden rounded-xl border p-5 transition-all duration-300 group hover:-translate-y-1 hover:shadow-lg"
                   :class="[
                       // Light: white card, Dark: dark background (MUST override bg-white)
                       'bg-white dark:bg-dark-900 border-gray-200 dark:border-white/5 shadow-sm',
                       // Dark mode gradient overlay on top of dark bg
                       'dark:bg-gradient-to-br dark:backdrop-blur-sm',
                       card.color
                   ]">
                   
                   <!-- Background Decoration for Light Mode -->
                   <div class="absolute -right-6 -top-6 w-24 h-24 rounded-full opacity-5 bg-current" :class="card.text"></div>
                   
                   <div class="flex items-center justify-between mb-3 relative z-10">
                       <span class="text-2xl filter drop-shadow-sm">{{ card.icon }}</span>
                   </div>
                   <p class="text-3xl font-mono font-black mb-1 relative z-10" :class="card.text || 'text-gray-900 dark:text-gray-100'">{{ card.value }}</p>
                   <p class="text-xs font-bold uppercase tracking-wider relative z-10 text-gray-500 dark:text-gray-400">{{ card.label }}</p>
              </div>
          </div>

          <!-- Recent Auctions -->
          <div class="bg-white dark:bg-stone-900/80 backdrop-blur-sm rounded-xl border border-gray-200 dark:border-white/5 p-6 shadow-sm">
              <div class="flex items-center justify-between mb-5">
                  <h2 class="text-lg font-kanit font-bold text-gray-900 dark:text-gray-100">Последние аукционы</h2>
                  <button @click="router.push('/client/auctions')" class="text-xs text-gold-500 hover:text-gold-600 font-bold uppercase tracking-wider transition-colors">
                      Все аукционы →
                  </button>
              </div>

              <div v-if="recentAuctions.length === 0" class="text-center py-10 text-gray-500 dark:text-gray-400">
                  <p class="text-sm">У вас пока нет аукционов</p>
              </div>

              <div v-else class="space-y-3">
                  <div v-for="auction in recentAuctions" :key="auction.id"
                       @click="router.push(`/client/auctions/${auction.id}`)"
                       class="flex flex-col md:flex-row md:items-center justify-between gap-4 p-4 rounded-lg bg-gray-50 dark:bg-stone-800/50 border border-gray-200 dark:border-white/5 cursor-pointer hover:bg-gray-100 dark:hover:bg-stone-700/50 hover:border-gold-500/30 transition-all group shadow-sm hover:shadow">
                       <div class="flex-1 min-w-0">
                           <div class="flex items-center gap-2 mb-1 flex-wrap">
                               <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider border"
                                     :class="statusBadgeColors[auction.status] || 'bg-gray-100 text-gray-500 border-gray-200 dark:bg-gray-500/20 dark:text-gray-400 dark:border-white/5'">
                                   {{ statusLabels[auction.status] || auction.status }}
                               </span>
                               <span v-if="auction.is_winning" class="px-2 py-0.5 rounded text-[10px] font-bold bg-green-100 text-green-600 border border-green-200 dark:bg-green-500/20 dark:text-green-400 dark:border-white/5">🏆</span>
                           </div>
                           <h3 class="text-sm font-bold text-gray-900 dark:text-gray-100 group-hover:text-gold-600 dark:group-hover:text-gold-400 transition-colors truncate">{{ auction.title }}</h3>
                           <div class="flex items-center gap-3 mt-1 text-xs text-gray-500 dark:text-gray-400">
                               <span>{{ formatDate(auction.start_at) }}</span>
                               <span v-if="auction.bar_count && auction.bar_weight" class="font-mono">{{ auction.bar_count }} сл. · {{ (auction.bar_count * auction.bar_weight).toFixed(1) }} кг</span>
                           </div>
                       </div>
                       <div class="flex flex-row md:flex-col items-center md:items-end justify-between md:justify-center gap-2 md:gap-1 flex-shrink-0">
                           <span v-if="getCountdown(auction)" class="font-mono text-base font-black text-gray-900 dark:text-white px-3 py-1 rounded-lg bg-gray-100 dark:bg-stone-800 border border-gray-200 dark:border-white/5 shadow-inner">
                               {{ getCountdown(auction) }}
                           </span>
                           <span v-if="auction.my_best_bid" class="text-xs text-gold-600 dark:text-gold-400 font-mono font-bold">
                               <span class="font-sans">₽</span>&nbsp;{{ formatPrice(auction.my_best_bid) }}/кг
                           </span>
                       </div>
                  </div>
              </div>
          </div>
      </template>
  </div>
</template>
