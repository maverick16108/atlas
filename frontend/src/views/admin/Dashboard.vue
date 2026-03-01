<script setup>
import { ref, onMounted, onUnmounted } from 'vue'
import axios from 'axios'
import { useRouter } from 'vue-router'
import { useConnectionStatus } from '@/composables/useConnectionStatus.js'
import echo from '@/echo.js'
import {
  Chart as ChartJS,
  CategoryScale,
  LinearScale,
  PointElement,
  LineElement,
  Title,
  Tooltip,
  Legend,
  Filler
} from 'chart.js'
import { Line } from 'vue-chartjs'

ChartJS.register(
  CategoryScale,
  LinearScale,
  PointElement,
  LineElement,
  Title,
  Tooltip,
  Legend,
  Filler
)

const router = useRouter()
const { isConnected, statusText } = useConnectionStatus()

const stats = ref([
    { name: 'Участники', value: '—', change: 'Загрузка...', type: 'neutral', action: () => router.push({ path: '/admin/users', query: { sort: 'newest' } }) },
    { name: 'Ожидают аккредитации', value: '—', change: '—', type: 'neutral', action: () => router.push({ path: '/admin/users', query: { filter: 'pending' } }) },
    { name: 'Активные аукционы', value: '—', change: '—', type: 'neutral', action: () => router.push({ path: '/admin/auctions', query: { status: 'active' } }) },
    { name: 'Работа комиссии', value: '—', change: '—', type: 'neutral', action: () => router.push({ path: '/admin/auctions', query: { status: 'commission' } }) },
    { name: 'Завершенные аукционы', value: '—', change: '—', type: 'neutral', action: () => router.push({ path: '/admin/auctions', query: { status: 'completed' } }) },
])

const chartData = ref({
  labels: [],
  datasets: []
})

const chartOptions = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: {
      display: false
    },
    tooltip: {
      mode: 'index',
      intersect: false,
      backgroundColor: 'rgba(0, 0, 0, 0.8)',
      titleColor: '#fff',
      bodyColor: '#fff',
      borderColor: 'rgba(255, 255, 255, 0.1)',
      borderWidth: 1,
      padding: 10,
      displayColors: false
    }
  },
  scales: {
    x: {
      grid: {
        color: 'rgba(255, 255, 255, 0.05)',
        drawBorder: false
      },
      ticks: {
        color: '#9ca3af',
        font: {
          family: "'Inter', sans-serif",
          size: 11
        }
      }
    },
    y: {
      grid: {
        color: 'rgba(255, 255, 255, 0.05)',
        drawBorder: false
      },
      ticks: {
        color: '#9ca3af',
        font: {
          family: "'Inter', sans-serif",
          size: 11
        },
        stepSize: 1
      },
      beginAtZero: true
    }
  },
  interaction: {
    intersect: false,
    mode: 'index',
  },
}

const isLoading = ref(true)

const fetchStats = async () => {
    try {
        const response = await axios.get('/api/dashboard/stats')
        // Map response data to local stats structure while preserving actions
        const apiStats = response.data.stats
        
        // Update values but keep actions
        if (apiStats[0]) {
            stats.value[0].name = 'Участники' // Force rename if API returns old name
            stats.value[0].value = apiStats[0].value
            stats.value[0].change = apiStats[0].change
            stats.value[0].type = apiStats[0].type
        }
        if (apiStats[1]) {
            stats.value[1].value = apiStats[1].value
            stats.value[1].change = apiStats[1].change
            stats.value[1].type = apiStats[1].type
        }
        if (apiStats[2]) {
            stats.value[2].value = apiStats[2].value
            stats.value[2].change = apiStats[2].change
            stats.value[2].type = apiStats[2].type
        }
        if (apiStats[3]) {
            stats.value[3].value = apiStats[3].value
            stats.value[3].change = apiStats[3].change
            stats.value[3].type = apiStats[3].type
        }
        if (apiStats[4]) {
            stats.value[4].value = apiStats[4].value
            stats.value[4].change = apiStats[4].change
            stats.value[4].type = apiStats[4].type
        }
        
        const chartResponse = response.data.chart
        chartData.value = {
            labels: chartResponse.map(item => item.date),
            datasets: [
                {
                    label: 'Аукционы',
                    data: chartResponse.map(item => item.count),
                    borderColor: '#10b981', // Emerald 500
                    backgroundColor: (context) => {
                        const ctx = context.chart.ctx;
                        const gradient = ctx.createLinearGradient(0, 0, 0, 300);
                        gradient.addColorStop(0, 'rgba(16, 185, 129, 0.2)');
                        gradient.addColorStop(1, 'rgba(16, 185, 129, 0)');
                        return gradient;
                    },
                    borderWidth: 2,
                    pointBackgroundColor: '#10b981',
                    pointBorderColor: '#fff',
                    pointHoverBackgroundColor: '#fff',
                    pointHoverBorderColor: '#10b981',
                    fill: true,
                    tension: 0.4
                }
            ]
        }
    } catch (e) {
        console.error('Failed to load dashboard stats:', e)
    } finally {
        isLoading.value = false
    }
}

onMounted(() => {
    fetchStats()

    // Real-time: refresh stats on any auction/bid change
    echo.channel('auctions')
        .listen('.bid.placed', () => fetchStats())
        .listen('.auction.updated', () => fetchStats())

    // Real-time: refresh stats on user changes (accreditation requests etc.)
    echo.channel('admin')
        .listen('.stats.updated', () => fetchStats())
})

onUnmounted(() => {
    echo.leaveChannel('auctions')
    echo.leaveChannel('admin')
})
</script>

<template>
  <div class="space-y-8">
      
      <!-- Stats Grid -->
      <div class="grid grid-cols-1 md:grid-cols-5 gap-6">
          <div v-for="(stat, index) in stats" :key="stat.name" 
               @click="stat.action && stat.action()"
               class="bg-white dark:bg-dark-800/80 shadow-sm dark:shadow-none backdrop-blur-sm p-6 rounded-xl border border-gray-200 dark:border-white/5 relative overflow-hidden group hover:border-red-500/30 dark:hover:border-emerald-500/30 transition-all duration-300 cursor-pointer active:scale-95 hover:scale-[1.02] hover:shadow-lg hover:shadow-red-500/5 dark:hover:shadow-emerald-500/10"
               :class="{'animate-pulse': isLoading}"
               :style="`animation-delay: ${index * 100}ms`">
              
              <!-- Background Glow -->
              <div class="absolute -right-6 -top-6 w-24 h-24 bg-emerald-500/10 rounded-full blur-2xl group-hover:bg-emerald-500/20 transition-all duration-500"></div>

              <p class="text-xs text-gray-500 dark:text-gray-400 font-bold uppercase tracking-wider mb-1">{{ stat.name }}</p>
              <div class="flex items-end justify-between">
                  <h3 class="text-3xl font-kanit font-bold text-gray-900 dark:text-white tracking-tight">{{ stat.value }}</h3>
                  <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wide mb-1" 
                    :class="{
                        'bg-emerald-100 dark:bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border border-emerald-300 dark:border-emerald-500/20': stat.type === 'up',
                        'bg-red-100 dark:bg-red-500/10 text-red-700 dark:text-red-400 border border-red-300 dark:border-red-500/20': stat.type === 'down',
                        'bg-yellow-100 dark:bg-yellow-500/10 text-yellow-700 dark:text-yellow-400 border border-yellow-300 dark:border-yellow-500/20': stat.type === 'warn',
                        'bg-gray-100 dark:bg-white/5 text-gray-700 dark:text-gray-400 border border-gray-300 dark:border-white/5': stat.type === 'neutral'
                    }">
                      {{ stat.change }}
                  </span>
              </div>
          </div>
      </div>

      <!-- Main Content Grid -->
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
          
          <!-- Chart Section -->
          <div class="lg:col-span-2 bg-white dark:bg-dark-800/80 shadow-sm dark:shadow-none backdrop-blur-sm rounded-xl border border-gray-200 dark:border-white/5 p-6 relative overflow-hidden group hover:border-gray-300 dark:hover:border-white/10 transition-colors h-full flex flex-col">
              <div class="flex justify-between items-center mb-6 shrink-0">
                  <div>
                      <h3 class="text-lg font-kanit font-bold text-gray-900 dark:text-white">Активность аукционов</h3>
                      <p class="text-xs text-gray-500 mt-1">Динамика создания новых аукционов за 30 дней</p>
                  </div>
                  <div class="flex gap-2">
                       <span class="w-3 h-3 rounded-full bg-emerald-500 animate-pulse"></span>
                  </div>
              </div>
              
              <div class="flex-1 min-h-[300px] w-full relative">
                  <Line v-if="!isLoading && chartData.datasets.length > 0" :data="chartData" :options="chartOptions" />
                  <div v-else class="absolute inset-0 flex items-center justify-center text-gray-500 text-sm">
                      <span v-if="isLoading">Загрузка данных...</span>
                      <span v-else>Нет данных для отображения</span>
                  </div>
              </div>
          </div>

          <!-- Quick Actions & Status -->
          <div class="flex flex-col gap-6 h-full">
              <!-- Quick Actions -->
              <div class="bg-white dark:bg-dark-800/80 shadow-sm dark:shadow-none backdrop-blur-sm rounded-xl border border-gray-200 dark:border-white/5 p-6 shrink-0">
                  <h3 class="text-lg font-kanit font-bold text-gray-900 dark:text-white mb-4">Быстрые действия</h3>
                  <div class="grid grid-cols-2 gap-3">
                      <router-link :to="{ path: '/admin/users', query: { action: 'create' } }" class="p-4 bg-gray-50 dark:bg-white/5 hover:bg-emerald-600 hover:text-white rounded-lg border border-gray-200 dark:border-white/10 transition-all duration-300 flex flex-col items-center justify-center gap-2 group text-gray-500 dark:text-gray-300 active:scale-95 active:bg-emerald-700 hover:scale-[1.02] hover:shadow-lg">
                          <svg class="w-6 h-6 opacity-70 group-hover:opacity-100 transition-opacity" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" /></svg>
                          <span class="font-bold text-xs uppercase tracking-wide text-center">Новый участник</span>
                      </router-link>
                      <router-link :to="{ path: '/admin/auctions', query: { action: 'create' } }" class="p-4 bg-gray-50 dark:bg-white/5 hover:bg-blue-600 hover:text-white rounded-lg border border-gray-200 dark:border-white/10 transition-all duration-300 flex flex-col items-center justify-center gap-2 group text-gray-500 dark:text-gray-300 active:scale-95 active:bg-blue-700 hover:scale-[1.02] hover:shadow-lg">
                           <svg class="w-6 h-6 opacity-70 group-hover:opacity-100 transition-opacity" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" /></svg>
                          <span class="font-bold text-xs uppercase tracking-wide text-center">Создать Аукцион</span>
                      </router-link>
                  </div>
              </div>
              
               <!-- System Status -->
               <div class="bg-white dark:bg-dark-800/80 shadow-sm dark:shadow-none backdrop-blur-sm rounded-xl border border-gray-200 dark:border-white/5 p-6 flex-1 flex flex-col justify-between">
                  <h3 class="text-lg font-kanit font-bold text-gray-900 dark:text-white mb-4 shrink-0">Статус системы</h3>
                  <div class="space-y-4 text-sm flex-1 flex flex-col justify-between">
                      <div class="flex justify-between items-center pb-3 border-b border-gray-100 dark:border-white/5">
                          <span class="text-gray-500 dark:text-gray-400">База данных</span>
                          <span class="text-emerald-500 dark:text-emerald-400 font-bold flex items-center gap-2 text-xs uppercase tracking-wider"><span class="w-2 h-2 bg-emerald-500 rounded-full shadow-[0_0_10px_rgba(16,185,129,0.5)]"></span>Онлайн</span>
                      </div>
                       <div class="flex justify-between items-center pb-3 border-b border-gray-100 dark:border-white/5">
                          <span class="text-gray-500 dark:text-gray-400">SMS Шлюз</span>
                          <span class="text-emerald-500 dark:text-emerald-400 font-bold flex items-center gap-2 text-xs uppercase tracking-wider"><span class="w-2 h-2 bg-emerald-500 rounded-full shadow-[0_0_10px_rgba(16,185,129,0.5)]"></span>Готов</span>
                      </div>
                      <div class="flex justify-between items-center">
                          <span class="text-gray-500 dark:text-gray-400">WebSocket</span>
                          <span class="font-bold flex items-center gap-2 text-xs uppercase tracking-wider" :class="isConnected ? 'text-emerald-400' : 'text-red-400'">
                            <span class="w-2 h-2 rounded-full" :class="isConnected ? 'bg-emerald-500 shadow-[0_0_10px_rgba(16,185,129,0.5)]' : 'bg-red-500 shadow-[0_0_10px_rgba(239,68,68,0.5)] animate-pulse'"></span>
                            {{ statusText }}
                          </span>
                      </div>
                  </div>
              </div>
          </div>
      </div>
  </div>
</template>
