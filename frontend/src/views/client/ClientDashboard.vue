<script setup>
import { ref } from 'vue'
import GoldChart from '../../components/GoldChart.vue'

const stats = [
    { name: 'Активные аукционы', value: '3', label: 'Участвую', icon: 'M13 10V3L4 14h7v7l9-11h-7z' },
    { name: 'Выигранные лоты', value: '12', label: 'Всего', icon: 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z' },
    { name: 'Баланс', value: '₽ 1,250,000', label: 'Доступно', icon: 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z' },
]

const recentActivity = ref([
    { id: 1, action: 'Ставка на Лот #124', time: '5 мин. назад', amount: '₽ 1,500,000', status: 'active' },
    { id: 2, action: 'Пополнение баланса', time: '2 часа назад', amount: '+ ₽ 500,000', status: 'success' },
    { id: 3, action: 'Аукцион #119 завершен', time: 'Вчера', amount: 'Проигрыш', status: 'failed' },
])
</script>

<template>
  <div class="space-y-8">
      <!-- Welcome Banner -->
      <div class="relative bg-gradient-to-r from-gold-600/20 to-dark-800 rounded-2xl p-8 border border-gold-500/10 overflow-hidden">
          <div class="absolute inset-0 bg-[url('/img/pattern.png')] opacity-5"></div>
          <h1 class="text-3xl font-kanit font-bold text-white mb-2">Добро пожаловать в Atlas Mining</h1>
          <p class="text-gray-400 max-w-2xl">Ваша панель управления инвестициями в добычу золота. Следите за аукционами и управляйте активами в реальном времени.</p>
      </div>

      <!-- Stats Grid -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
          <div v-for="stat in stats" :key="stat.name" class="bg-dark-800/80 backdrop-blur-sm p-6 rounded-xl border border-white/5 hover:border-gold-500/30 transition-all duration-300 group">
              <div class="flex justify-between items-start mb-4">
                  <div class="p-3 rounded-lg bg-gold-500/10 group-hover:bg-gold-500/20 transition-colors">
                      <svg class="w-6 h-6 text-gold-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="stat.icon" />
                      </svg>
                  </div>
                  <span class="text-xs text-gray-500 bg-dark-900 px-2 py-1 rounded-full border border-white/5">{{ stat.label }}</span>
              </div>
              <h3 class="text-3xl font-kanit font-bold text-white mb-1">{{ stat.value }}</h3>
              <p class="text-sm text-gray-400">{{ stat.name }}</p>
          </div>
      </div>

      <!-- Charts & Activity -->
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
          <!-- Main Chart -->
          <div class="lg:col-span-2 bg-dark-800/50 rounded-xl border border-white/5 p-6 backdrop-blur-sm">
              <div class="flex justify-between items-center mb-6">
                  <h3 class="text-lg font-kanit font-bold text-white">Курс Золота (Live)</h3>
                  <button class="text-xs text-gold-500 hover:text-white transition-colors">Подробнее</button>
              </div>
              <div class="h-[300px]">
                  <GoldChart />
              </div>
          </div>

          <!-- Activity List -->
          <div class="bg-dark-800/50 rounded-xl border border-white/5 p-6 backdrop-blur-sm">
              <h3 class="text-lg font-kanit font-bold text-white mb-6">Последняя активность</h3>
              <div class="space-y-4">
                  <div v-for="item in recentActivity" :key="item.id" class="flex items-center justify-between p-3 rounded-lg hover:bg-white/5 transition-colors border border-transparent hover:border-white/5">
                      <div>
                          <p class="text-sm font-bold text-white">{{ item.action }}</p>
                          <p class="text-xs text-gray-500">{{ item.time }}</p>
                      </div>
                      <span class="text-xs font-mono font-bold" 
                        :class="{
                            'text-gold-400': item.status === 'active',
                            'text-green-400': item.status === 'success',
                            'text-red-400': item.status === 'failed'
                        }">
                          {{ item.amount }}
                      </span>
                  </div>
              </div>
              <button class="w-full mt-6 py-2 rounded-lg border border-white/10 text-xs text-gray-400 hover:text-white hover:border-gold-500/30 transition-all">
                  Показать всю историю
              </button>
          </div>
      </div>
  </div>
</template>
