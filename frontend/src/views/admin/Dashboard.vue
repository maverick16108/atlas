<script setup>
const stats = [
    { name: 'Всего пользователей', value: '1,248', change: '+12%', type: 'up' },
    { name: 'Ожидают аккредитации', value: '45', change: 'Требует внимания', type: 'warn' },
    { name: 'Активные аукционы', value: '3', change: 'Идет торг', type: 'neutral' },
    { name: 'Объем продаж (мес)', value: '₽ 840M', change: '+5%', type: 'up' },
]
</script>

<template>
  <div class="space-y-8">
      <!-- Stats Grid -->
      <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
          <div v-for="stat in stats" :key="stat.name" class="bg-dark-800/80 backdrop-blur-sm p-6 rounded-xl border border-white/5 relative overflow-hidden group hover:border-red-500/30 transition-all">
              <div class="absolute top-0 right-0 p-4 opacity-10 group-hover:opacity-20 transition-opacity">
                   <svg class="w-16 h-16 text-white" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-6h2v6zm0-8h-2V7h2v2z"/></svg>
              </div>
              <p class="text-sm text-gray-400 font-medium uppercase tracking-wider">{{ stat.name }}</p>
              <h3 class="text-3xl font-oswald font-bold text-white mt-1 mb-2">{{ stat.value }}</h3>
              <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold" 
                :class="{
                    'bg-green-500/20 text-green-400': stat.type === 'up',
                    'bg-red-500/20 text-red-400': stat.type === 'down',
                    'bg-yellow-500/20 text-yellow-400': stat.type === 'warn',
                    'bg-gray-700 text-gray-300': stat.type === 'neutral'
                }">
                  {{ stat.change }}
              </span>
          </div>
      </div>

      <!-- Quick Actions -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
          <div class="bg-dark-800/80 backdrop-blur-sm rounded-xl border border-white/5 p-6">
              <h3 class="text-xl font-kanit font-bold text-white mb-4">Быстрые действия</h3>
              <div class="grid grid-cols-2 gap-4">
                  <button class="p-4 bg-white/5 hover:bg-red-500 hover:text-white rounded-lg border border-white/10 transition-all flex flex-col items-center justify-center gap-2 group">
                      <svg class="w-8 h-8 text-gray-400 group-hover:text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" /></svg>
                      <span class="font-bold text-sm">Новый пользователь</span>
                  </button>
                  <button class="p-4 bg-white/5 hover:bg-gold-500 hover:text-black rounded-lg border border-white/10 transition-all flex flex-col items-center justify-center gap-2 group">
                       <svg class="w-8 h-8 text-gray-400 group-hover:text-black" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" /></svg>
                      <span class="font-bold text-sm">Создать Лот</span>
                  </button>
              </div>
          </div>
          
           <!-- System Status -->
           <div class="bg-dark-800/80 backdrop-blur-sm rounded-xl border border-white/5 p-6">
              <h3 class="text-xl font-kanit font-bold text-white mb-4">Статус системы</h3>
              <div class="space-y-4">
                  <div class="flex justify-between items-center pb-2 border-b border-white/5">
                      <span class="text-gray-400">Database</span>
                      <span class="text-green-400 font-bold flex items-center gap-2"><span class="w-2 h-2 bg-green-500 rounded-full"></span> Online</span>
                  </div>
                   <div class="flex justify-between items-center pb-2 border-b border-white/5">
                      <span class="text-gray-400">SMS Gateway</span>
                      <span class="text-green-400 font-bold flex items-center gap-2"><span class="w-2 h-2 bg-green-500 rounded-full"></span> Connected</span>
                  </div>
                  <div class="flex justify-between items-center pb-2 border-b border-white/5">
                      <span class="text-gray-400">WebSocket Server</span>
                      <span class="text-yellow-400 font-bold flex items-center gap-2"><span class="w-2 h-2 bg-yellow-500 rounded-full"></span> Warning (High Load)</span>
                  </div>
              </div>
          </div>
      </div>
  </div>
</template>
