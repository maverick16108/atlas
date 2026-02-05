<script setup>
import { ref } from 'vue'

const users = ref([
    { id: 1, name: 'ООО "Золотой Век"', email: 'contact@goldage.ru', phone: '+7 (995) 123-45-67', role: 'client', is_accredited: true, joined: '12.01.2024' },
    { id: 2, name: 'Иван Петров (Физ)', email: 'ivan@mail.ru', phone: '+7 (916) 555-01-02', role: 'client', is_accredited: false, joined: '05.02.2024' },
    { id: 3, name: 'АО "ИнвестГрупп"', email: 'info@invest.com', phone: '+7 (495) 000-00-00', role: 'client', is_accredited: false, joined: 'Вчера' },
    { id: 4, name: 'Администратор системы', email: 'admin@atlas.ru', phone: '-', role: 'admin', is_accredited: true, joined: '-' },
])

const toggleAccreditation = (user) => {
    user.is_accredited = !user.is_accredited
    // TODO: API Call
}
</script>

<template>
  <div class="bg-dark-800/80 backdrop-blur-sm rounded-xl border border-white/5 overflow-hidden">
      <div class="p-6 border-b border-white/5 flex justify-between items-center">
          <h2 class="text-xl font-kanit font-bold text-white">Управление пользователями</h2>
          <div class="flex gap-2">
              <input type="text" placeholder="Поиск..." class="bg-dark-900 border border-white/10 rounded-lg px-3 py-2 text-sm text-white focus:border-red-500 focus:outline-none" />
          </div>
      </div>

      <div class="overflow-x-auto">
          <table class="w-full text-left text-sm text-gray-400">
              <thead class="bg-white/5 text-xs uppercase font-bold text-white">
                  <tr>
                      <th class="px-6 py-4">Пользователь</th>
                      <th class="px-6 py-4">Роль</th>
                      <th class="px-6 py-4">Статус</th>
                      <th class="px-6 py-4">Регистрация</th>
                      <th class="px-6 py-4 text-right">Действия</th>
                  </tr>
              </thead>
              <tbody class="divide-y divide-white/5">
                  <tr v-for="user in users" :key="user.id" class="hover:bg-white/5 transition-colors">
                      <td class="px-6 py-4">
                          <div class="flex items-center gap-3">
                              <div class="w-8 h-8 rounded-full bg-dark-900 flex items-center justify-center text-xs font-bold text-white border border-white/10">
                                  {{ user.name.charAt(0) }}
                              </div>
                              <div>
                                  <p class="font-bold text-white">{{ user.name }}</p>
                                  <p class="text-xs">{{ user.email }} • {{ user.phone }}</p>
                              </div>
                          </div>
                      </td>
                      <td class="px-6 py-4">
                          <span class="px-2 py-1 rounded text-xs font-bold uppercase" 
                             :class="user.role === 'admin' ? 'bg-red-500/20 text-red-400' : 'bg-blue-500/20 text-blue-400'">
                              {{ user.role }}
                          </span>
                      </td>
                      <td class="px-6 py-4">
                          <span class="px-2 py-1 rounded text-xs font-bold uppercase" 
                             :class="user.is_accredited ? 'bg-green-500/20 text-green-400' : 'bg-yellow-500/20 text-yellow-400'">
                              {{ user.is_accredited ? 'Аккредитован' : 'Ожидает' }}
                          </span>
                      </td>
                      <td class="px-6 py-4">
                          {{ user.joined }}
                      </td>
                      <td class="px-6 py-4 text-right">
                          <button 
                            @click="toggleAccreditation(user)"
                            class="text-xs font-bold uppercase tracking-wider px-3 py-1 rounded transition-colors"
                            :class="user.is_accredited ? 'bg-red-500/10 text-red-400 hover:bg-red-500 hover:text-white' : 'bg-green-500/10 text-green-400 hover:bg-green-500 hover:text-black'"
                          >
                              {{ user.is_accredited ? 'Отозвать' : 'Одобрить' }}
                          </button>
                      </td>
                  </tr>
              </tbody>
          </table>
      </div>
  </div>
</template>
