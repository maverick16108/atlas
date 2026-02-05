<script setup>
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'

const router = useRouter()
const authStore = useAuthStore()

const user = computed(() => authStore.user)
const userRole = computed(() => authStore.userRole)

const logout = () => {
    authStore.logout()
}

// Menu Items based on Role
const menuItems = computed(() => {
    if (userRole.value === 'admin' || userRole.value === 'moderator') {
        return [
            { label: 'Dashboard', icon: 'ChartBarIcon', to: '/admin' },
            { label: 'Users', icon: 'UsersIcon', to: '/admin/users' },
            { label: 'Transactions', icon: 'CurrencyDollarIcon', to: '/admin/transactions' },
            { label: 'Settings', icon: 'CogIcon', to: '/admin/settings' },
        ]
    } else {
        return [
            { label: 'Portfolio', icon: 'ChartPieIcon', to: '/client' },
            { label: 'Market', icon: 'TrendingUpIcon', to: '/client/market' },
            { label: 'History', icon: 'ClockIcon', to: '/client/history' },
            { label: 'Profile', icon: 'UserIcon', to: '/client/profile' },
        ]
    }
})

// Simple Icon component stub (replacing HeroIcons for simplicity if not set up, 
// but relying on dependency list having HeroIcons).
// Dependencies had @heroicons/vue. Good.
</script>

<template>
  <div class="min-h-screen bg-dark-900 flex">
    
    <!-- Sidebar -->
    <aside class="w-64 bg-dark-800 border-r border-white/5 flex flex-col fixed inset-y-0 left-0 z-50">
      <!-- Brand -->
      <div class="h-20 flex items-center px-8 border-b border-white/5">
         <img src="/logo.png" class="w-8 h-8 mr-3 invert opacity-80" />
         <span class="text-white font-kanit font-bold text-xl tracking-widest">ATLAS</span>
      </div>

      <!-- Navigation -->
      <nav class="flex-1 px-4 py-6 space-y-2">
         <div v-if="userRole" class="px-4 mb-4">
             <span class="text-xs uppercase tracking-widest text-gold-500 font-bold opacity-70">
                 {{ userRole === 'admin' ? 'Administration' : 'Client Panel' }}
             </span>
         </div>
         
         <template v-for="item in menuItems" :key="item.to">
             <router-link 
               :to="item.to" 
               class="flex items-center px-4 py-3 rounded-lg text-gray-400 hover:bg-white/5 hover:text-white transition-colors group"
               active-class="bg-gold-500/10 text-gold-500 border border-gold-500/20"
             >
                <!-- Icon Placeholder if HeroIcons import complex -->
                <div class="w-5 h-5 mr-3 bg-current opacity-50 group-hover:opacity-100 rounded-sm"></div> 
                <span class="font-kanit tracking-wide">{{ item.label }}</span>
             </router-link>
         </template>
      </nav>

      <!-- User Footer -->
      <div class="p-4 border-t border-white/5">
          <div class="flex items-center p-3 rounded-lg bg-white/5 mb-3">
              <img :src="user?.avatar" class="w-10 h-10 rounded-full mr-3 border border-white/10" />
              <div class="overflow-hidden">
                  <div class="text-sm font-bold text-white truncate">{{ user?.name }}</div>
                  <div class="text-xs text-gray-400 truncate">{{ user?.email }}</div>
              </div>
          </div>
          <button @click="logout" class="w-full py-2 text-xs text-red-400 hover:text-red-300 uppercase tracking-widest font-bold border border-red-500/20 rounded hover:bg-red-500/10 transition-colors">
              Log Out
          </button>
      </div>
    </aside>

    <!-- Main Content -->
    <main class="flex-1 ml-64 p-8">
        <!-- Top Bar (Mobile support omitted for brevity, focusing on structure) -->
         <header class="mb-8 flex justify-between items-center">
             <h2 class="text-2xl font-kanit font-bold text-white">Dashboard</h2>
             <div class="flex items-center gap-4">
                 <button class="w-10 h-10 rounded-full bg-dark-800 border border-white/10 flex items-center justify-center text-gray-400 hover:text-white">
                     <span class="text-xl">🔔</span>
                 </button>
             </div>
         </header>

         <!-- View Content -->
         <div class="bg-dark-800/50 backdrop-blur-sm rounded-2xl border border-white/5 p-6 min-h-[500px]">
             <router-view></router-view>
         </div>
    </main>
  </div>
</template>
