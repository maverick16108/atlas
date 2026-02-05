<script setup>
import { computed } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import { useI18n } from 'vue-i18n'
// import GoldenMesh from '../components/effects/GoldenMesh.vue'

const router = useRouter()
const route = useRoute()
const authStore = useAuthStore()
const { t } = useI18n()

const user = computed(() => authStore.user)
const navigation = computed(() => [
    { name: t('admin.nav.overview'), path: '/admin', icon: 'M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z' },
    { name: t('admin.nav.users'), path: '/admin/users', icon: 'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z' },
    { name: t('admin.nav.auctions'), path: '/admin/auctions', icon: 'M13 10V3L4 14h7v7l9-11h-7z' },
    { name: t('admin.nav.finance'), path: '/admin/finance', icon: 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z' },
])

const handleLogout = () => {
    authStore.logout()
    router.push('/login')
}
</script>

<template>
  <div class="h-screen w-full bg-dark-900 text-white flex overflow-hidden">
    <!-- Grid Background -->
    <div class="fixed inset-0 bg-[url('/img/grid.svg')] bg-center [mask-image:linear-gradient(180deg,white,rgba(255,255,255,0))] opacity-10 pointer-events-none"></div>
    
    <!-- Sidebar -->
    <aside class="w-64 bg-dark-800/90 backdrop-blur-xl border-r border-red-500/10 flex flex-col z-20 shadow-[4px_0_30px_rgba(0,0,0,0.5)]">
        <!-- Logo Area -->
        <div class="h-20 flex items-center justify-center border-b border-white/5">
            <div class="flex items-center gap-3">
                 <div class="relative w-8 h-8">
                  <div class="absolute inset-0 bg-red-500 rounded-lg blur opacity-20 animate-pulse-slow"></div>
                  <div class="relative w-full h-full border border-red-500/30 bg-red-500/10 backdrop-blur-md flex items-center justify-center rounded-lg">
                    <span class="text-red-400 font-bold font-oswald text-sm">S</span>
                  </div>
                </div>
                <h1 class="text-lg font-kanit font-bold tracking-wider text-white">ATLAS <span class="text-red-500">STAFF</span></h1>
            </div>
        </div>

        <!-- Navigation -->
        <nav class="flex-1 py-8 px-4 space-y-2">
            <template v-for="item in navigation" :key="item.path">
                <router-link 
                    :to="item.path"
                    class="flex items-center gap-4 px-4 py-3 rounded-lg transition-all duration-300 group"
                    :class="route.path === item.path ? 'bg-red-500/10 text-white shadow-[0_0_15px_rgba(239,68,68,0.2)] border border-red-500/30' : 'text-gray-400 hover:bg-white/5 hover:text-white'"
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
            <div class="flex items-center gap-3 px-2 py-3 bg-dark-900/50 rounded-xl border border-white/5 hover:border-red-500/20 transition-colors cursor-pointer group">
                <img :src="user?.avatar" alt="Avatar" class="w-10 h-10 rounded-full border border-red-500/30 shadow-sm" />
                <div class="flex-1 overflow-hidden">
                    <p class="text-sm font-bold text-white truncate">{{ user?.name || 'Admin' }}</p>
                    <p class="text-xs text-red-500 truncate">{{ user?.role?.toUpperCase() || 'STAFF' }}</p>
                </div>
                <button @click.prevent="handleLogout" class="text-gray-500 hover:text-red-400 transition-colors p-2">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                    </svg>
                </button>
            </div>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="flex-1 flex flex-col relative z-10 overflow-hidden bg-dark-900">
        <!-- Topbar -->
        <header class="h-20 min-h-[5rem] px-8 flex items-center justify-between border-b border-white/5 bg-dark-900/50 backdrop-blur-sm sticky top-0 z-30">
            <div>
                 <h2 class="text-2xl font-kanit font-bold text-white tracking-wide uppercase">
                     {{ navigation.find(i => i.path === route.path)?.name || 'Admin Panel' }}
                 </h2>
            </div>
            
            <div class="flex items-center gap-6">
                <div class="flex items-center gap-2 px-4 py-2 bg-red-500/10 rounded-full border border-red-500/20">
                    <span class="w-2 h-2 bg-green-500 rounded-full animate-pulse"></span>
                    <span class="text-xs text-red-400 font-bold tracking-wider">SYSTEM ONLINE</span>
                </div>
            </div>
        </header>

        <!-- View Content -->
        <div class="flex-1 overflow-y-auto p-8 custom-scrollbar">
             <router-view v-slot="{ Component }">
                <transition 
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
