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
    draft: 'Черновик', collecting_offers: 'Сбор предложений', scheduled: 'Запланирован',
    active: 'Активный', gpb_right: 'Право ГПБ', commission: 'Работа комиссии',
    completed: 'Завершён', paused: 'Приостановлен', cancelled: 'Отменён',
}

const statusBadgeColors = {
    draft: 'bg-gray-500/20 text-gray-400', collecting_offers: 'bg-cyan-500/20 text-cyan-400',
    scheduled: 'bg-purple-500/20 text-purple-400', active: 'bg-amber-500/20 text-amber-400',
    gpb_right: 'bg-blue-500/20 text-blue-400', commission: 'bg-orange-500/20 text-orange-400',
    completed: 'bg-emerald-500/20 text-emerald-400', paused: 'bg-amber-800/20 text-amber-700',
    cancelled: 'bg-red-500/20 text-red-400',
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
    { label: 'Активные аукционы', value: stats.value.active_auctions || 0, icon: '🔴' },
    { label: 'Всего аукционов', value: stats.value.total_auctions || 0, icon: '📊' },
    { label: 'Побед', value: stats.value.won_auctions || 0, icon: '🏆', accent: true },
    { label: 'Ставок', value: stats.value.total_bids || 0, icon: '⚡' },
    { label: 'Предложений', value: stats.value.total_offers || 0, icon: '📋' },
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
                   class="relative overflow-hidden rounded-2xl border p-5 transition-all duration-300 group hover:-translate-y-1 hover:shadow-lg"
                   :class="card.accent
                       ? 'border-gold-500/30 bg-gradient-to-br from-gold-500/15 to-dark-900/90 hover:border-gold-500/50 hover:shadow-[0_0_20px_rgba(212,175,55,0.2)]'
                       : 'border-white/10 bg-gradient-to-br from-white/5 to-dark-900/80 hover:border-white/20 hover:shadow-[0_0_15px_rgba(255,255,255,0.05)]'
                   ">
                   <div class="absolute -right-4 -top-4 w-20 h-20 blur-2xl rounded-full transition-all duration-500"
                        :class="card.accent ? 'bg-gold-500/20 group-hover:bg-gold-500/30' : 'bg-white/5 group-hover:bg-white/10'"></div>
                   <div class="flex items-center justify-between mb-3 relative z-10">
                       <span class="text-2xl filter drop-shadow-sm">{{ card.icon }}</span>
                   </div>
                   <p class="text-3xl font-mono font-black mb-1 relative z-10"
                      :class="card.accent ? 'text-gold-400' : 'text-white'">{{ card.value }}</p>
                   <p class="text-[10px] font-bold uppercase tracking-widest relative z-10 text-gray-500">{{ card.label }}</p>
              </div>
          </div>

          <!-- Recent Auctions -->
          <div class="bg-dark-800/80 backdrop-blur-sm rounded-2xl border border-white/10 p-6">
              <div class="flex items-center justify-between mb-5">
                  <h2 class="text-lg font-kanit font-bold text-white tracking-wide uppercase">Последние аукционы</h2>
                  <button @click="router.push('/client/auctions')" class="text-xs text-gold-500 hover:text-gold-400 font-bold uppercase tracking-wider transition-colors">
                      Все аукционы →
                  </button>
              </div>

              <div v-if="recentAuctions.length === 0" class="text-center py-10 text-gray-500">
                  <p class="text-sm">У вас пока нет аукционов</p>
              </div>

              <div v-else class="space-y-3">
                  <div v-for="auction in recentAuctions" :key="auction.id"
                       @click="router.push(`/client/auctions/${auction.id}`)"
                       class="flex flex-col md:flex-row md:items-center justify-between gap-4 p-4 rounded-xl bg-dark-900/50 border border-white/5 cursor-pointer hover:bg-dark-900/80 hover:border-white/15 transition-all group">
                       <div class="flex-1 min-w-0">
                           <div class="flex items-center gap-2 mb-1 flex-wrap">
                               <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider"
                                     :class="statusBadgeColors[auction.status] || 'bg-gray-500/20 text-gray-400'">
                                   {{ statusLabels[auction.status] || auction.status }}
                               </span>
                               <span v-if="auction.is_winning" class="px-2 py-0.5 rounded text-[10px] font-bold bg-green-500/20 text-green-400">🏆</span>
                           </div>
                           <h3 class="text-sm font-bold text-white group-hover:text-gold-400 transition-colors truncate">{{ auction.title }}</h3>
                           <div class="flex items-center gap-3 mt-1 text-xs text-gray-500">
                               <span>{{ formatDate(auction.start_at) }}</span>
                               <span v-if="auction.bar_count && auction.bar_weight" class="font-mono">{{ auction.bar_count }} сл. · {{ (auction.bar_count * auction.bar_weight).toFixed(1) }} кг</span>
                           </div>
                       </div>
                       <div class="flex flex-row md:flex-col items-center md:items-end justify-between md:justify-center gap-2 md:gap-1 flex-shrink-0">
                           <span v-if="getCountdown(auction)" class="font-mono text-base font-black text-white px-3 py-1 rounded-lg bg-dark-900 border border-white/10 shadow-inner">
                               {{ getCountdown(auction) }}
                           </span>
                           <span v-if="auction.my_best_bid" class="text-xs text-gold-400 font-mono font-bold">
                               <span class="font-sans">₽</span>&nbsp;{{ formatPrice(auction.my_best_bid) }}/кг
                           </span>
                       </div>
                  </div>
              </div>
          </div>
      </template>
  </div>
</template>
