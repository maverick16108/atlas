<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import axios from 'axios'

import { Line } from 'vue-chartjs'
import { Chart as ChartJS, CategoryScale, LinearScale, PointElement, LineElement, Title, Tooltip, Filler } from 'chart.js'
ChartJS.register(CategoryScale, LinearScale, PointElement, LineElement, Title, Tooltip, Filler)


const router = useRouter()
const stats = ref({})
const recentAuctions = ref([])
const isLoading = ref(true)
const timerNow = ref(Date.now())
let timerInterval = null


const chartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: { display: false },
        tooltip: {
            mode: 'index',
            intersect: false,
            callbacks: {
                label: function(context) {
                    let label = context.dataset.label || '';
                    if (label) {
                        label += ': ';
                    }
                    if (context.parsed.y !== null) {
                        label += new Intl.NumberFormat('ru-RU', { style: 'currency', currency: 'RUB' }).format(context.parsed.y) + '/г';
                    }
                    return label;
                }
            }
        }
    },
    scales: {
        x: {
            grid: { display: false, drawBorder: false },
            ticks: { maxTicksLimit: 8, font: { family: "'Kanit', sans-serif" } }
        },
        y: {
            grid: { color: 'rgba(150, 150, 150, 0.1)' },
            border: { dash: [4, 4] },
            ticks: { font: { family: "'Kanit', sans-serif" } }
        }
    },
    interaction: {
        mode: 'nearest',
        axis: 'x',
        intersect: false
    }
}

const chartData = computed(() => {
    if (!stats.value?.price_history || stats.value.price_history.length === 0) {
        return null;
    }
    const history = stats.value.price_history;
    return {
        labels: history.map(h => {
             return new Date(h.date).toLocaleDateString('ru-RU', { day: '2-digit', month: 'short' });
        }),
        datasets: [{
            label: 'Цена',
            data: history.map(h => h.price),
            borderColor: '#d4af37',
            backgroundColor: 'rgba(212, 175, 55, 0.15)',
            borderWidth: 2,
            pointBackgroundColor: '#fff',
            pointBorderColor: '#d4af37',
            pointBorderWidth: 2,
            pointRadius: 4,
            pointHoverRadius: 6,
            fill: true,
            tension: 0.4
        }]
    }
})

const statusLabels = {
    draft: 'Черновик', collecting_offers: 'Сбор предложений', scheduled: 'Запланирован',
    active: 'Активный', gpb_right: 'Право ГПБ', commission: 'Работа комиссии',
    completed: 'Завершён', paused: 'Приостановлен', cancelled: 'Отменён',
}

const statusBadgeColors = {
    draft: 'bg-gray-100 text-gray-600 dark:bg-gray-500/20 dark:text-gray-400',
    collecting_offers: 'bg-cyan-100 text-cyan-700 dark:bg-cyan-500/20 dark:text-cyan-400',
    scheduled: 'bg-purple-100 text-purple-700 dark:bg-purple-500/20 dark:text-purple-400',
    active: 'bg-amber-100 text-amber-700 dark:bg-amber-500/20 dark:text-amber-400',
    gpb_right: 'bg-blue-100 text-blue-700 dark:bg-blue-500/20 dark:text-blue-400',
    commission: 'bg-orange-100 text-orange-700 dark:bg-orange-500/20 dark:text-orange-400',
    completed: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-400',
    paused: 'bg-amber-800/10 text-amber-800 dark:bg-amber-800/20 dark:text-amber-700',
    cancelled: 'bg-red-100 text-red-700 dark:bg-red-500/20 dark:text-red-400',
}

const fetchData = async () => {
    isLoading.value = true
    try {
        const [statsRes, auctionsRes] = await Promise.all([
            axios.get('/api/client/stats'),
            axios.get('/api/client/auctions'),
        ])
        stats.value = statsRes.data
        recentAuctions.value = auctionsRes.data.slice(0, 6)
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

import { PlayCircleIcon, ChartBarSquareIcon, TrophyIcon, DocumentTextIcon } from '@heroicons/vue/24/outline'

// ...
const statCards = computed(() => [
    { label: 'Сбор предложений', value: stats.value.collecting_auctions || 0, icon: DocumentTextIcon, route: '/client/auctions?filter=collecting', accent: true },
    { label: 'Активные аукционы', value: stats.value.active_auctions || 0, icon: PlayCircleIcon, route: '/client/auctions?filter=active' },
    { label: 'Всего аукционов', value: stats.value.total_auctions || 0, icon: ChartBarSquareIcon, route: '/client/auctions' },
    { label: 'Побед', value: stats.value.won_auctions || 0, icon: TrophyIcon, route: '/client/auctions?filter=completed' },
])
import echo from '@/echo.js'

// Silent refresh — no loading spinner
const refreshData = async () => {
    try {
        const [statsRes, auctionsRes] = await Promise.all([
            axios.get('/api/client/stats'),
            axios.get('/api/client/auctions'),
        ])
        stats.value = statsRes.data
        recentAuctions.value = auctionsRes.data.slice(0, 6)
    } catch (e) {
        console.error('Dashboard refresh failed:', e)
    }
}

onMounted(() => {
    fetchData()
    timerInterval = setInterval(() => { timerNow.value = Date.now() }, 1000)
    // Real-time updates via WebSocket
    echo.channel('auctions')
        .listen('.auction.updated', () => refreshData())
        .listen('.bid.placed', () => refreshData())
})

onUnmounted(() => {
    if (timerInterval) clearInterval(timerInterval)
    echo.leaveChannel('auctions')
})
</script>

<template>
  <div class="flex flex-col gap-6 lg:gap-8">
      <!-- Stats Grid -->
      <div v-if="isLoading" class="flex items-center justify-center py-16">
          <div class="w-8 h-8 border-2 border-gold-500 border-t-transparent rounded-full animate-spin"></div>
      </div>

      <template v-else>
          <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
              <div v-for="card in statCards" :key="card.label"
                   @click="router.push(card.route)"
                   class="relative overflow-hidden rounded-2xl border p-5 transition-all duration-300 group hover:-translate-y-1 hover:shadow-lg cursor-pointer"
                   :class="card.accent
                       ? 'border-gold-500/30 bg-gradient-to-br from-gold-50 to-white dark:from-gold-500/15 dark:to-dark-900/90 hover:border-gold-500/50 hover:shadow-[0_0_20px_rgba(212,175,55,0.2)]'
                       : 'border-gray-200 dark:border-white/10 bg-gradient-to-br from-gray-50 to-white dark:from-white/5 dark:to-dark-900/80 hover:border-gray-300 dark:hover:border-white/20 hover:shadow-[0_0_15px_rgba(0,0,0,0.05)] dark:hover:shadow-[0_0_15px_rgba(255,255,255,0.05)]'
                   ">
                   <div class="absolute -right-4 -top-4 w-20 h-20 blur-2xl rounded-full transition-all duration-500"
                        :class="card.accent ? 'bg-gold-500/20 group-hover:bg-gold-500/30' : 'bg-gray-200 dark:bg-white/5 group-hover:bg-gray-300 dark:group-hover:bg-white/10'"></div>
                   <div class="flex items-center justify-between mb-3 relative z-10">
                       <component :is="card.icon" class="w-7 h-7 filter drop-shadow-md" :class="card.accent ? 'text-gold-500 dark:text-gold-400 group-hover:text-gold-600 dark:group-hover:text-gold-300' : 'text-gray-400 dark:text-gray-400 group-hover:text-gray-600 dark:group-hover:text-white transition-colors'" />
                   </div>
                   <p class="text-3xl font-mono font-black mb-1 relative z-10"
                      :class="card.accent ? 'text-gold-600 dark:text-gold-400' : 'text-gray-900 dark:text-white'">{{ card.value }}</p>
                   <p class="text-[10px] font-bold uppercase tracking-widest relative z-10 text-gray-500 group-hover:text-gray-900 dark:group-hover:text-gray-300 transition-colors">{{ card.label }}</p>
              </div>
          </div>


          <!-- Price Trend Chart -->
          <div v-if="chartData" class="hidden md:block bg-white/80 dark:bg-dark-800/80 backdrop-blur-sm rounded-2xl border border-gray-200 dark:border-white/10 p-6 shadow-sm dark:shadow-none">
              <div class="flex flex-wrap items-center justify-between gap-4 mb-5">
                  <h2 class="text-lg font-kanit font-bold text-gray-900 dark:text-white tracking-wide uppercase">Динамика цен</h2>
                  <div class="text-xs text-gray-500 font-medium bg-gray-100 dark:bg-white/5 px-2 py-1 rounded">Цена за грамм (₽)</div>
              </div>
              <div class="h-64 w-full relative">
                  <Line :data="chartData" :options="chartOptions" />
              </div>
          </div>
          <!-- Recent Auctions -->
          <!-- Recent Auctions -->
          <div class="bg-transparent md:bg-white/80 md:dark:bg-dark-800/80 md:backdrop-blur-sm md:rounded-2xl md:border md:border-gray-200 md:dark:border-white/10 md:p-6 md:shadow-sm">
              <div class="flex flex-wrap items-center justify-between gap-4 mb-5">
                  <h2 class="text-lg font-kanit font-bold text-gray-900 dark:text-white tracking-wide uppercase">Последние аукционы</h2>
                  <button @click="router.push('/client/auctions')" class="text-xs text-gold-600 hover:text-gold-500 dark:text-gold-500 dark:hover:text-gold-400 font-bold uppercase tracking-wider transition-colors whitespace-nowrap flex items-center shrink-0">
                      Все аукционы &rarr;
                  </button>
              </div>

              <div v-if="recentAuctions.length === 0" class="text-center py-10 text-gray-500">
                  <p class="text-sm">У вас пока нет аукционов</p>
              </div>

              <div v-else class="space-y-3">
                  <div v-for="auction in recentAuctions" :key="auction.id"
                       @click="router.push(`/client/auctions/${auction.id}`)"
                       class="relative flex flex-row items-center justify-between gap-3 sm:gap-4 p-4 rounded-xl bg-gray-50/80 dark:bg-dark-800/80 border border-gray-200 dark:border-white/10 cursor-pointer overflow-hidden transition-all duration-300 group hover:-translate-y-0.5"
                       :class="['active', 'commission', 'collecting_offers'].includes(auction.status) ? 'hover:border-gold-500/40 hover:shadow-[0_4px_20px_rgba(212,175,55,0.15)] hover:bg-gradient-to-r hover:from-gold-50 dark:hover:from-gold-500/10 hover:to-white dark:hover:to-dark-900/80' : 'hover:border-gray-300 dark:hover:border-white/20 hover:bg-white dark:hover:bg-white/5'">
                       
                       <!-- Glow effect for active auctions inside the card -->
                       <div v-if="['active', 'commission', 'collecting_offers'].includes(auction.status)" class="absolute -inset-1 opacity-0 group-hover:opacity-100 transition-opacity duration-500 pointer-events-none bg-gradient-to-r from-gold-500/10 dark:from-gold-500/20 via-transparent to-transparent blur-xl"></div>

                       <div class="flex-1 min-w-0 relative z-10 flex flex-col justify-center">
                           <div class="flex items-center gap-2 mb-1.5 flex-wrap ">
                               <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider shadow-sm"
                                     :class="statusBadgeColors[auction.status] || 'bg-gray-200 dark:bg-gray-500/20 text-gray-600 dark:text-gray-400'">
                                   {{ statusLabels[auction.status] || auction.status }}
                               </span>
                               <span v-if="auction.is_winning" class="px-2 py-0.5 rounded text-[10px] font-bold bg-green-100 text-green-700 dark:bg-green-500/20 dark:text-green-400 border border-green-200 dark:border-green-500/30 flex items-center gap-1 shadow-[0_0_10px_rgba(34,197,94,0.1)] dark:shadow-[0_0_10px_rgba(34,197,94,0.2)]">🏆 {{ ['commission', 'completed'].includes(auction.status) ? 'Вы выиграли' : 'Вы выигрываете' }}</span>
                           </div>
                           <h3 class="text-sm font-bold text-gray-900 dark:text-white group-hover:text-gold-600 dark:group-hover:text-gold-400 transition-colors truncate">
                               <span v-if="auction.id" class="text-gray-400 dark:text-gray-500 group-hover:text-gold-400 dark:group-hover:text-gold-500/50 mr-1 transition-colors">№{{ auction.id }}</span>
                               {{ auction.title }}
                           </h3>
                           <div class="flex items-center gap-3 mt-1.5 text-[11px] font-medium text-gray-500">
                               <span class="flex items-center gap-1">
                                   <svg class="w-3.5 h-3.5 opacity-70" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                   {{ formatDate(auction.start_at) }}
                               </span>
                               <span v-if="auction.bar_count && auction.bar_weight" class="font-mono bg-gray-100 dark:bg-dark-800 px-1.5 py-0.5 rounded border border-gray-200 dark:border-white/5">{{ auction.bar_count }} сл. · {{ (auction.bar_count * auction.bar_weight).toFixed(1) }} кг</span>
                           </div>
                       </div>
                       
                       <div class="flex flex-col items-end flex-shrink-0 relative z-10 w-auto">
                           
                           <div class="flex flex-col items-end gap-1">
                               <span v-if="getCountdown(auction)" class="font-mono text-base font-black text-gray-900 dark:text-white px-3 py-1 rounded-lg bg-white dark:bg-dark-900 border border-gold-500/30 shadow-[0_2px_10px_rgba(212,175,55,0.1)] dark:shadow-[0_0_15px_rgba(212,175,55,0.1)] group-hover:border-gold-500/50 group-hover:shadow-[0_4px_15px_rgba(212,175,55,0.2)] dark:group-hover:shadow-[0_0_20px_rgba(212,175,55,0.2)] transition-all">
                                   {{ getCountdown(auction) }}
                               </span>
                               <span v-if="auction.my_best_bid" class="text-base text-gold-600 dark:text-gold-400 font-mono font-bold group-hover:text-gold-500 dark:group-hover:text-gold-300 transition-colors">
                                   <span class="font-sans opacity-70 text-sm">₽</span>&nbsp;{{ formatPrice(auction.my_best_bid) }}<span class="opacity-50 font-sans font-normal text-xs">/г</span>
                               </span>
                           </div>
                       </div>
                  </div>
              </div>
          </div>
      </template>
  </div>
</template>
