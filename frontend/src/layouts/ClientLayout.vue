<script setup>
import { computed } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import { useI18n } from 'vue-i18n'
import GoldenMesh from '../components/effects/GoldenMesh.vue'

const router = useRouter()
const route = useRoute()
const authStore = useAuthStore()
const { t } = useI18n()

const user = computed(() => authStore.user)
const navigation = computed(() => [
    { name: t('dashboard.nav.overview'), path: '/client', icon: 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6' },
    { name: t('dashboard.nav.auctions'), path: '/client/auctions', icon: 'M13 10V3L4 14h7v7l9-11h-7z' },
    { name: t('dashboard.nav.settings'), path: '/client/profile', icon: 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z' },
])

const handleLogout = () => {
    authStore.logout()
    router.push('/login')
}
</script>

<template>
  <div class="h-screen w-full bg-dark-900 text-white flex overflow-hidden">
    <GoldenMesh />
    
    <!-- Sidebar -->
    <aside class="w-64 bg-dark-800/80 backdrop-blur-xl border-r border-gold-500/10 flex flex-col z-20 shadow-[4px_0_30px_rgba(0,0,0,0.5)]">
        <!-- Logo Area -->
        <div class="h-20 flex items-center justify-center border-b border-white/5">
            <div class="flex items-center gap-3">
                 <img src="/logo.png" alt="Atlas" class="w-8 h-8 rounded shadow-[0_0_20px_rgba(212,175,55,0.6)] object-cover" />
                <h1 class="text-lg font-kanit font-bold tracking-wider text-white">ATLAS <span class="text-gold-500">CABINET</span></h1>
            </div>
        </div>

        <!-- Navigation -->
        <nav class="flex-1 py-8 px-4 space-y-2">
            <template v-for="item in navigation" :key="item.path">
                <router-link 
                    :to="item.path"
                    class="flex items-center gap-4 px-4 py-3 rounded-lg transition-all duration-300 group"
                    :class="route.path === item.path ? 'bg-gold-500/20 text-white shadow-[0_0_15px_rgba(212,175,55,0.2)] border border-gold-500/30' : 'text-gray-400 hover:bg-white/5 hover:text-white'"
                >
                    <svg class="w-5 h-5 transition-colors" :class="route.path === item.path ? 'text-gold-400' : 'text-gray-500 group-hover:text-gold-400'" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="item.icon" />
                    </svg>
                    <span class="font-medium tracking-wide text-sm">{{ item.name }}</span>
                </router-link>
            </template>
        </nav>

        <!-- User Profile (Bottom Sidebar) -->
        <div class="p-4 border-t border-white/5">
            <div class="flex items-center gap-3 px-2 py-3 bg-dark-900/50 rounded-xl border border-white/5 hover:border-gold-500/20 transition-colors cursor-pointer group">
                <img :src="user?.avatar" alt="Avatar" class="w-10 h-10 rounded-full border border-gold-500/30 shadow-sm" />
                <div class="flex-1 overflow-hidden">
                    <p class="text-sm font-bold text-white truncate">{{ user?.name || 'Клиент' }}</p>
                    <p class="text-xs text-gold-500 truncate">{{ user?.is_accredited ? 'Аккредитован' : 'Гость' }}</p>
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
    <main class="flex-1 flex flex-col relative z-10 overflow-hidden">
        <!-- Topbar (Optional, often dashboard title or notices) -->
        <header class="h-20 min-h-[5rem] px-8 flex items-center justify-between border-b border-gold-500/10 bg-dark-900/50 backdrop-blur-sm sticky top-0 z-30">
            <div>
                 <h2 class="text-2xl font-kanit font-bold text-white tracking-wide uppercase">
                     {{ navigation.find(i => i.path === route.path)?.name || 'Дашборд' }}
                 </h2>
                 <p class="text-xs text-gray-500 mt-1">
                     {{ new Date().toLocaleDateString('ru-RU', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' }) }}
                 </p>
            </div>
            
            <div class="flex items-center gap-6">
                <!-- Notifications Bell -->
                <button class="relative text-gray-400 hover:text-white transition-colors">
                    <span class="absolute top-0 right-0 w-2 h-2 bg-red-500 rounded-full border border-dark-900 animate-pulse"></span>
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                    </svg>
                </button>
                
                <!-- Wallet/Balance (Mock) -->
                <div class="flex items-center gap-2 px-4 py-2 bg-dark-800 rounded-full border border-gold-500/20">
                    <span class="text-xs text-gray-400">Баланс:</span>
                    <span class="font-mono text-gold-400 font-bold">₽ 0.00</span>
                </div>
            </div>
        </header>

        <!-- View Content with Scroll -->
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
  background: rgba(212, 175, 55, 0.3);
  border-radius: 10px;
}
.custom-scrollbar::-webkit-scrollbar-thumb:hover {
  background: rgba(212, 175, 55, 0.6);
}
</style>
