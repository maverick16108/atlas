<script setup>
import { ref } from 'vue'

const auctions = ref([
    { id: 124, title: 'Золотой слиток 1кг (999.9)', startPrice: '₽ 5,000,000', currentBid: '₽ 5,200,000', status: 'active', participants: 12 },
    { id: 125, title: 'Самородок "Восток" (950.0)', startPrice: '₽ 800,000', currentBid: '₽ 850,000', status: 'upcoming', participants: 45 },
    { id: 119, title: 'Слиток 500г (999.9)', startPrice: '₽ 2,500,000', currentBid: '₽ 2,600,000', status: 'closed', participants: 8 },
])

const showCreateModal = ref(false)
</script>

<template>
  <div class="bg-dark-800/80 backdrop-blur-sm rounded-xl border border-white/5 overflow-hidden">
      <div class="p-6 border-b border-white/5 flex justify-between items-center">
          <h2 class="text-xl font-kanit font-bold text-white">Управление аукционами</h2>
          <button 
            @click="showCreateModal = true"
            class="px-4 py-2 bg-gold-500 text-black font-bold uppercase tracking-wider rounded-lg hover:bg-gold-400 transition-colors flex items-center gap-2"
          >
              <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" /></svg>
              Создать лот
          </button>
      </div>

       <div class="overflow-x-auto">
          <table class="w-full text-left text-sm text-gray-400">
              <thead class="bg-white/5 text-xs uppercase font-bold text-white">
                  <tr>
                      <th class="px-6 py-4">ID</th>
                      <th class="px-6 py-4">Лот</th>
                      <th class="px-6 py-4">Ставка</th>
                      <th class="px-6 py-4">Участники</th>
                      <th class="px-6 py-4">Статус</th>
                      <th class="px-6 py-4 text-right">Действия</th>
                  </tr>
              </thead>
              <tbody class="divide-y divide-white/5">
                  <tr v-for="lot in auctions" :key="lot.id" class="hover:bg-white/5 transition-colors">
                      <td class="px-6 py-4 font-mono">#{{ lot.id }}</td>
                      <td class="px-6 py-4 font-bold text-white">{{ lot.title }}</td>
                      <td class="px-6 py-4 text-gold-400 font-mono">{{ lot.currentBid }}</td>
                      <td class="px-6 py-4">{{ lot.participants }}</td>
                       <td class="px-6 py-4">
                          <span class="px-2 py-1 rounded text-xs font-bold uppercase" 
                             :class="{
                                 'bg-green-500/20 text-green-400': lot.status === 'active',
                                 'bg-blue-500/20 text-blue-400': lot.status === 'upcoming',
                                 'bg-gray-700 text-gray-400': lot.status === 'closed'
                             }">
                              {{ lot.status }}
                          </span>
                      </td>
                      <td class="px-6 py-4 text-right">
                          <button class="text-gray-400 hover:text-white transition-colors">
                              <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" /></svg>
                          </button>
                      </td>
                  </tr>
              </tbody>
          </table>
      </div>
  </div>
</template>
