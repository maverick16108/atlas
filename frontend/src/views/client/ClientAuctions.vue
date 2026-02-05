<script setup>
import { ref } from 'vue'

const activeTab = ref('all')
const tabs = [
    { id: 'all', name: 'Все лоты' },
    { id: 'active', name: 'Я участвую' },
    { id: 'won', name: 'Выигранные' },
]

const auctions = ref([
    { id: 1, title: 'Лот #124: Золотой слиток 1кг', purity: '999.9', currentBid: '₽ 5,200,000', myBid: '₽ 5,150,000', status: 'active', timeLeft: '02:14:50', image: '/img/gold-bar-hero.png' },
    { id: 2, title: 'Лот #125: Самородок "Восток"', purity: '950.0', currentBid: '₽ 850,000', myBid: null, status: 'upcoming', timeLeft: '12:00:00', image: '/img/nugget.png' },
    { id: 3, title: 'Лот #119: Слиток 500г', purity: '999.9', currentBid: '₽ 2,600,000', myBid: '₽ 2,600,000', status: 'won', timeLeft: 'Завершен', image: '/img/gold-bars.png' },
])
</script>

<template>
  <div class="space-y-6">
      
      <!-- Filters -->
      <div class="flex items-center justify-between">
          <h2 class="text-2xl font-kanit font-bold text-white">Аукционы</h2>
          <div class="flex p-1 bg-dark-800 rounded-lg border border-white/5">
              <button 
                v-for="tab in tabs" 
                :key="tab.id"
                @click="activeTab = tab.id"
                class="px-4 py-2 rounded-md text-sm font-medium transition-all duration-300"
                :class="activeTab === tab.id ? 'bg-gold-500 text-black shadow-lg shadow-gold-500/20' : 'text-gray-400 hover:text-white'"
              >
                  {{ tab.name }}
              </button>
          </div>
      </div>

      <!-- Grid -->
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
          <div 
            v-for="lot in auctions" 
            :key="lot.id" 
            class="group bg-dark-800/80 backdrop-blur-sm rounded-xl border border-white/10 overflow-hidden hover:border-gold-500/40 transition-all duration-300 hover:shadow-[0_0_30px_rgba(212,175,55,0.1)] flex flex-col"
          >
              <!-- Image -->
              <div class="h-48 bg-dark-900 relative overflow-hidden">
                  <div class="absolute inset-0 bg-gold-500/5 group-hover:bg-gold-500/10 transition-colors z-10"></div>
                  <!-- Placeholder/Image -->
                  <div class="w-full h-full flex items-center justify-center text-gold-500/20">
                       <svg class="w-16 h-16" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2L2 7l10 5 10-5-10-5zm0 9l2.5-1.25L12 8.5l-2.5 1.25L12 11zm0 2.5l-5-2.5-5 2.5L12 22l10-8.5-5-2.5-5 2.5z"/></svg>
                  </div>
                  <!-- Badges -->
                  <div class="absolute top-3 left-3 z-20 flex gap-2">
                       <span class="px-2 py-1 text-[10px] font-bold uppercase tracking-wider bg-black/60 backdrop-blur text-white rounded border border-white/10">{{ lot.purity }}</span>
                       <span v-if="lot.status === 'won'" class="px-2 py-1 text-[10px] font-bold uppercase tracking-wider bg-gold-500 text-black rounded shadow-lg shadow-gold-500/50">Выигран</span>
                  </div>
              </div>

              <!-- Content -->
              <div class="p-5 flex-1 flex flex-col">
                  <div class="flex justify-between items-start mb-2">
                      <h3 class="text-lg font-bold text-white group-hover:text-gold-400 transition-colors">{{ lot.title }}</h3>
                  </div>
                  
                  <div class="space-y-3 mt-auto">
                      <div class="flex justify-between text-sm py-2 border-b border-white/5">
                          <span class="text-gray-400">Текущая ставка</span>
                          <span class="font-mono font-bold text-white">{{ lot.currentBid }}</span>
                      </div>
                      <div class="flex justify-between text-sm py-2 border-b border-white/5" v-if="lot.myBid">
                          <span class="text-gray-400">Моя ставка</span>
                          <span class="font-mono font-bold text-gold-400">{{ lot.myBid }}</span>
                      </div>
                      
                      <div class="flex items-center gap-2 text-xs text-gray-500 mt-2">
                          <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                          <span>{{ lot.timeLeft }}</span>
                      </div>
                  </div>

                  <!-- Actions -->
                  <button class="w-full mt-4 py-3 bg-white/5 border border-white/10 rounded-lg text-sm font-bold uppercase tracking-widest hover:bg-gold-500 hover:text-black hover:border-gold-500 transition-all duration-300">
                      {{ lot.status === 'won' ? 'Оформить доставку' : 'Сделать ставку' }}
                  </button>
              </div>
          </div>
      </div>
  </div>
</template>
