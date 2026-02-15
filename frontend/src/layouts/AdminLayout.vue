<script setup>
import { computed, ref } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import StandardModal from '../components/ui/StandardModal.vue'
// import GoldenMesh from '../components/effects/GoldenMesh.vue'

const router = useRouter()
const route = useRoute()
const authStore = useAuthStore()

const user = computed(() => authStore.user)

// Staff avatar with red background for all admin panel users
const staffAvatar = computed(() => {
    if (!user.value?.name) return ''
    return `https://ui-avatars.com/api/?name=${encodeURIComponent(user.value.name)}&background=dc2626&color=fff`
})
const navigation = computed(() => {
    const nav = [
        { name: 'Обзор', path: '/admin', icon: 'M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z' },
        { name: 'Участники', path: '/admin/users', icon: 'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z' },
        { name: 'Аукционы', path: '/admin/auctions', icon: 'M13 10V3L4 14h7v7l9-11h-7z' },
    ]
    
    const pageTitle = computed(() => {
        const item = navigation.value.find(i => i.path === route.path)
        return item?.headerTitle || item?.name || route.meta.title || 'Admin Panel'
    })

    if (user.value?.role === 'super_admin') {
        nav.splice(1, 0, { 
            name: 'Модераторы', 
            headerTitle: 'УПРАВЛЕНИЕ МОДЕРАТОРАМИ',
            path: '/admin/moderators', 
            icon: 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z' 
        })
        nav.push({ 
            name: 'Журнал', 
            headerTitle: 'ЖУРНАЛ ДЕЙСТВИЙ',
            path: '/admin/activity-log', 
            icon: 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z' 
        })
    }
    
    return nav
})

const pageTitle = computed(() => {
    const item = navigation.value.find(i => i.path === route.path)
    return item?.headerTitle || item?.name || route.meta.title || 'Admin Panel'
})

const showLogoutModal = ref(false)

const handleLogout = () => {
    showLogoutModal.value = true
}

const confirmLogout = () => {
    showLogoutModal.value = false
    authStore.logout()
}
</script>
    
    <template>
      <div class="h-screen w-full bg-dark-900 text-white flex overflow-hidden">
        <!-- Grid Background -->
        <div class="fixed inset-0 bg-[url('/img/grid.svg')] bg-center [mask-image:linear-gradient(180deg,white,rgba(255,255,255,0))] opacity-10 pointer-events-none"></div>
        
    <!-- Sidebar -->
    <aside class="w-64 bg-dark-800/80 backdrop-blur-xl border-r border-red-500/10 flex flex-col z-[60] relative shadow-[4px_0_30px_rgba(0,0,0,0.5)]">
        <!-- Logo Area (from Client Panel but Red + Image) -->
        <div class="h-20 flex items-center justify-center border-b border-white/5 relative overflow-hidden">
            <router-link to="/admin" class="flex items-center gap-3 group hover:opacity-90 transition-opacity">
                 <img src="/logo.png" alt="Atlas" class="w-8 h-8 rounded shadow-[0_0_20px_rgba(239,68,68,0.6)] object-cover group-hover:scale-105 transition-transform duration-300" />
                <h1 class="text-lg font-kanit font-bold tracking-wider text-white">АТЛАС <span class="text-red-500 group-hover:text-red-400 transition-colors">АДМИН</span></h1>
            </router-link>
        </div>

        <!-- Navigation -->
        <nav class="flex-1 py-8 px-4 space-y-2">
            <template v-for="item in navigation" :key="item.path">
                <router-link 
                    :to="item.path"
                    class="flex items-center gap-4 px-4 py-3 rounded-lg transition-all duration-300 group focus:outline-none"
                    :class="route.path === item.path ? 'bg-red-500/20 text-white shadow-[0_0_15px_rgba(239,68,68,0.3)] border border-red-500/50' : 'text-gray-400 hover:bg-white/5 hover:text-white border border-transparent'"
                >
                    <svg class="w-5 h-5 transition-colors" :class="route.path === item.path ? 'text-red-400' : 'text-gray-500 group-hover:text-red-400'" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="item.icon" />
                    </svg>
                    <span class="font-medium tracking-wide text-sm">{{ item.name }}</span>
                </router-link>
            </template>
        </nav>

        <!-- User Profile (Bottom Sidebar) -->
        <div class="p-4 border-t border-white/5">
            <div @click="handleLogout" class="flex items-center gap-3 px-2 py-3 bg-dark-900/50 rounded-xl border border-white/5 hover:border-red-500/20 transition-colors cursor-pointer group">
                <img :src="staffAvatar" alt="Avatar" class="w-10 h-10 rounded-full border border-red-500/30 shadow-sm" />
                <div class="flex-1 overflow-hidden">
                    <p class="text-sm font-bold text-white truncate">{{ user?.name || 'Admin' }}</p>
                    <p class="text-xs text-red-500 truncate">{{ (user?.role === 'super_admin' ? 'ADMIN' : user?.role?.toUpperCase()) || 'STAFF' }}</p>
                </div>
                <div class="text-gray-500 group-hover:text-red-400 transition-colors p-2">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                    </svg>
                </div>
            </div>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="flex-1 flex flex-col relative z-10 overflow-hidden bg-dark-900">
        <!-- Topbar -->
        <header class="h-20 min-h-[5rem] px-8 flex items-center justify-between border-b border-white/10 bg-dark-800/90 backdrop-blur-md sticky top-0 z-30 shadow-lg shadow-black/20">
            <div class="flex items-center gap-4">
                 <!-- Decorative accent -->
                 <div class="h-8 w-1 bg-gradient-to-b from-red-500 to-red-600 rounded-full shadow-[0_0_10px_rgba(239,68,68,0.5)]"></div>
                 <h2 class="text-2xl font-kanit font-bold text-white tracking-wide uppercase drop-shadow-md">
                     {{ pageTitle }}
                 </h2>
            </div>
            
            <div class="flex items-center gap-6">
                <div class="flex items-center gap-2 px-4 py-2 bg-red-500/10 rounded-full border border-red-500/20 shadow-[inset_0_0_10px_rgba(239,68,68,0.1)]">
                    <span class="w-2 h-2 bg-green-500 rounded-full animate-pulse shadow-[0_0_8px_rgba(34,197,94,0.6)]"></span>
                    <span class="text-xs text-red-400 font-bold tracking-wider">СИСТЕМА АКТИВНА</span>
                </div>
            </div>
        </header>

        <!-- View Content -->
        <div class="flex-1 overflow-y-scroll p-8 custom-scrollbar">
             <router-view v-slot="{ Component }">
                <transition 
                    mode="out-in"
                    enter-active-class="transition ease-out duration-300" 
                    enter-from-class="opacity-0 translate-y-4" 
                    enter-to-class="opacity-100 translate-y-0" 
                    leave-active-class="transition ease-in duration-200" 
                    leave-from-class="opacity-100 translate-y-0" 
                    leave-to-class="opacity-0 -translate-y-4"
                >
                    <component :is="Component" />
                </transition>
             </router-view>
        </div>
    </main>

    <!-- Logout Confirmation Modal -->
    <StandardModal 
        :is-open="showLogoutModal" 
        theme="red"
        z-index-class="z-[200]"
        backdrop-z-index-class="z-[150]"
        :backdrop-blur="false"
        @close="showLogoutModal = false"
    >
        <div class="text-center pt-2">
            <h3 class="text-xl font-kanit font-bold text-white tracking-wide uppercase mb-6">Выйти<span class="text-2xl">?</span></h3>
            <p class="text-gray-400 text-sm mb-2 font-light">
                Сессия
            </p>
            <p class="text-white font-bold text-lg mb-2">
                {{ user?.name || 'Admin' }}
            </p>
            <p class="text-red-400 text-sm font-semibold mb-6 tracking-wide">
                будет завершена
            </p>
            
            <div class="flex gap-3">
                <button @click="showLogoutModal = false" class="flex-1 py-3 rounded-lg text-xs font-bold uppercase tracking-widest text-gray-400 hover:text-white bg-white/5 hover:bg-white/10 transition-colors border border-transparent hover:border-white/10">
                    Отмена
                </button>
                <button @click="confirmLogout" class="flex-1 py-3 bg-red-600 hover:bg-red-500 text-white rounded-lg font-bold text-xs uppercase tracking-widest shadow-lg shadow-red-500/20 transition-all transform active:scale-95 border border-red-500/50">
                    Выйти
                </button>
            </div>
        </div>
    </StandardModal>
  </div>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar {
  width: 6px;
}
.custom-scrollbar::-webkit-scrollbar-track {
  background: rgba(255, 255, 255, 0.02);
}
.custom-scrollbar::-webkit-scrollbar-thumb {
  background: rgba(239, 68, 68, 0.3);
  border-radius: 10px;
}
.custom-scrollbar::-webkit-scrollbar-thumb:hover {
  background: rgba(239, 68, 68, 0.6);
}
</style>
