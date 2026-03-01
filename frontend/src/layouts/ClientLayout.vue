<script setup>
import { ref, computed, onMounted, onUnmounted, watch, nextTick } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import { useTheme } from '../composables/useTheme'
import { useI18n } from 'vue-i18n'
import StandardModal from '../components/ui/StandardModal.vue'
import axios from 'axios'
import echo from '@/echo.js'
import { Bars3Icon, XMarkIcon, BellIcon } from '@heroicons/vue/24/outline'

const router = useRouter()
const route = useRoute()
const authStore = useAuthStore()
const { t } = useI18n()
const { theme } = useTheme()

const user = computed(() => authStore.clientUser)
const navigation = computed(() => [
    { name: t('dashboard.nav.overview'), path: '/client', icon: 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6' },
    { name: t('dashboard.nav.auctions'), path: '/client/auctions', icon: 'M13 10V3L4 14h7v7l9-11h-7z' },
    { name: 'Профиль', path: '/client/profile', icon: 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z' },
])

const showLogoutModal = ref(false)
const isSidebarOpen = ref(false)

// ===== Auto-hide header on mobile scroll =====
const scrollContentRef = ref(null)
const isHeaderHidden = ref(false)
let lastScrollTop = 0
let ticking = false

const onContentScroll = () => {
    if (ticking) return
    ticking = true
    requestAnimationFrame(() => {
        if (!scrollContentRef.value) { ticking = false; return }
        // Only on mobile (< 1024px)
        if (window.innerWidth >= 1024) { isHeaderHidden.value = false; ticking = false; return }
        
        const st = scrollContentRef.value.scrollTop
        const diff = st - lastScrollTop
        
        // Dead-zone: ignore tiny movements (< 5px) to filter scroll noise
        if (Math.abs(diff) < 5) { ticking = false; return }
        
        if (diff > 0 && st > 60) {
            // Scrolling down & past header height — hide
            isHeaderHidden.value = true
        } else if (diff < 0) {
            // Scrolling up — show
            isHeaderHidden.value = false
        }
        
        lastScrollTop = st
        ticking = false
    })
}

const handleLogout = () => {
    showLogoutModal.value = true
}

const confirmLogout = () => {
    showLogoutModal.value = false
    authStore.logout('client')
}

// ===== Notifications =====
const showNotifications = ref(false)
const notifications = ref([])
const unreadCount = ref(0)
const isLoadingNotifications = ref(false)
let pollInterval = null

const typeIcons = {
    auction_invite: { icon: '📨', color: 'text-blue-400 bg-blue-500/10' },
    auction_reminder: { icon: '⏰', color: 'text-amber-400 bg-amber-500/10' },
    auction_completed: { icon: '🏁', color: 'text-emerald-400 bg-emerald-500/10' },
    bid_outbid: { icon: '⚡', color: 'text-red-400 bg-red-500/10' },
    auction_started: { icon: '🔴', color: 'text-green-400 bg-green-500/10' },
    auction_ended: { icon: '🏁', color: 'text-emerald-400 bg-emerald-500/10' },
    offer_accepted: { icon: '✅', color: 'text-cyan-400 bg-cyan-500/10' },
    status_change: { icon: '🔄', color: 'text-yellow-400 bg-yellow-500/10' },
}

const getTypeInfo = (type) => typeIcons[type] || { icon: '🔔', color: 'text-gray-400 dark:text-gray-500 bg-gray-100 dark:bg-stone-800/50' }

const fetchUnreadCount = async () => {
    try {
        const res = await axios.get('/api/client/notifications/unread-count')
        unreadCount.value = res.data.count
    } catch (e) { /* silently fail */ }
}

const fetchNotifications = async () => {
    isLoadingNotifications.value = true
    try {
        const res = await axios.get('/api/client/notifications')
        notifications.value = res.data
    } catch (e) { /* silently fail */ }
    finally { isLoadingNotifications.value = false }
}

const toggleNotifications = async () => {
    showNotifications.value = !showNotifications.value
    if (showNotifications.value) {
        await fetchNotifications()
    }
}

const closeNotifications = () => { showNotifications.value = false }

const notificationRef = ref(null)
const onDocumentClick = (e) => {
    if (notificationRef.value && !notificationRef.value.contains(e.target)) {
        closeNotifications()
    }
}
const onDocumentKeydown = (e) => {
    if (e.key === 'Escape') closeNotifications()
}
watch(showNotifications, (val) => {
    if (val) {
        nextTick(() => {
            document.addEventListener('click', onDocumentClick)
            document.addEventListener('keydown', onDocumentKeydown)
        })
    } else {
        document.removeEventListener('click', onDocumentClick)
        document.removeEventListener('keydown', onDocumentKeydown)
    }
})
onUnmounted(() => {
    document.removeEventListener('click', onDocumentClick)
    document.removeEventListener('keydown', onDocumentKeydown)
})

const markAsRead = async (notification) => {
    if (notification.read_at) return
    try {
        await axios.post(`/api/client/notifications/${notification.id}/read`)
        notification.read_at = new Date().toISOString()
        unreadCount.value = Math.max(0, unreadCount.value - 1)
    } catch (e) { /* silently fail */ }
}

const markAllAsRead = async () => {
    try {
        await axios.post('/api/client/notifications/read-all')
        notifications.value.forEach(n => { n.read_at = n.read_at || new Date().toISOString() })
        unreadCount.value = 0
    } catch (e) { /* silently fail */ }
}

const handleNotificationClick = async (notification) => {
    await markAsRead(notification)
    if (notification.auction_id) {
        router.push(`/client/auctions/${notification.auction_id}`)
        closeNotifications()
    }
}

const timeAgo = (iso) => {
    if (!iso) return ''
    const diff = Date.now() - new Date(iso).getTime()
    const mins = Math.floor(diff / 60000)
    if (mins < 1) return 'только что'
    if (mins < 60) return `${mins} мин назад`
    const hours = Math.floor(mins / 60)
    if (hours < 24) return `${hours} ч назад`
    const days = Math.floor(hours / 24)
    return `${days} дн назад`
}

onMounted(() => {
    fetchUnreadCount()
    pollInterval = setInterval(fetchUnreadCount, 30000)

    // Attach scroll listener
    if (scrollContentRef.value) {
        scrollContentRef.value.addEventListener('scroll', onContentScroll, { passive: true })
    }

    // Sync profile from DB (updates is_gpb, name, etc. if changed by admin)
    axios.get('/api/client/me').then(res => {
        if (res.data?.id) {
            const freshUser = res.data
            // Regenerate avatar with correct GPB color if no custom avatar uploaded
            if (!freshUser.avatar || freshUser.avatar.includes('ui-avatars.com')) {
                const bg = freshUser.is_gpb ? '3B82F6' : '4ADE80'
                freshUser.avatar = `https://ui-avatars.com/api/?name=${encodeURIComponent(freshUser.name || 'Client')}&background=${bg}&color=fff`
            }
            authStore.updateClientUser(freshUser)
        }
    }).catch(() => {})

    // Real-time notifications via WebSocket
    const userId = user.value?.id
    if (userId) {
        echo.channel(`notifications.user.${userId}`)
            .listen('.new.notification', (data) => {
                // Prepend new notification to the list
                notifications.value.unshift(data)
                unreadCount.value++
            })
    }
})

onUnmounted(() => {
    if (pollInterval) clearInterval(pollInterval)
    if (scrollContentRef.value) {
        scrollContentRef.value.removeEventListener('scroll', onContentScroll)
    }
    const userId = user.value?.id
    if (userId) {
        echo.leaveChannel(`notifications.user.${userId}`)
    }
})
</script>

<template>
  <div class="h-screen w-full bg-gray-100 dark:bg-dark-900 text-gray-900 dark:text-gray-100 flex overflow-hidden transition-colors duration-300">
    
    <!-- Mobile Sidebar Backdrop -->
    <div v-if="isSidebarOpen" @click="isSidebarOpen = false" class="fixed inset-0 z-40 bg-black/50 backdrop-blur-sm lg:hidden"></div>

    <!-- Sidebar -->
    <aside 
        class="fixed lg:static inset-y-0 left-0 z-50 w-64 client-sidebar backdrop-blur-xl flex flex-col shadow-[4px_0_30px_rgba(0,0,0,0.05)] dark:shadow-[4px_0_30px_rgba(0,0,0,0.5)] transform transition-transform duration-300 lg:transform-none"
        :class="isSidebarOpen ? 'translate-x-0' : '-translate-x-full'"
    >
        <!-- Logo Area -->
        <div class="h-20 flex items-center justify-start pl-14 lg:justify-center lg:pl-0 border-b border-gray-200 dark:border-white/5 relative">
             <button @click="isSidebarOpen = false" class="absolute left-4 lg:hidden p-2 text-gray-400 dark:text-gray-500 hover:text-gray-800 dark:hover:text-white">
                <XMarkIcon class="w-6 h-6" />
            </button>
            <div class="flex items-center gap-2 lg:gap-3">
                 <img src="/logo.png" alt="Atlas" class="w-7 h-7 lg:w-8 lg:h-8 rounded shadow-lg shadow-gold-500/30 object-cover" />
                <h1 class="text-base lg:text-lg font-kanit font-bold tracking-wider text-gray-900 dark:text-white whitespace-nowrap">ATLAS <span class="text-gold-500">АУКЦИОН</span></h1>
            </div>
        </div>

        <!-- Navigation -->
        <nav class="flex-1 py-8 px-4 space-y-2 overflow-y-auto custom-scrollbar">
            <template v-for="item in navigation" :key="item.path">
                <router-link 
                    :to="item.path"
                    @click="isSidebarOpen = false"
                    class="flex items-center gap-4 px-4 py-3 rounded-lg transition-all duration-300 group outline-none border"
                    :class="route.path === item.path ? 'client-nav-item-active' : 'client-nav-item'"
                >
                    <svg class="w-5 h-5 transition-colors" :class="route.path === item.path ? 'text-gold-500' : 'text-gray-400 dark:text-gray-500 group-hover:text-gold-500'" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="item.icon" />
                    </svg>
                    <span class="font-medium tracking-wide text-sm">{{ item.name }}</span>
                </router-link>
            </template>
        </nav>

        <!-- Logout (Bottom Sidebar) -->
        <div class="p-4 border-t border-gray-200 dark:border-white/5 bg-gray-50/50 dark:bg-white/5">
            <button @click="handleLogout" class="w-full flex items-center justify-start gap-2 px-3 py-2.5 rounded-xl text-gray-500 dark:text-gray-400 hover:text-red-500 dark:hover:text-red-400 hover:bg-red-50 dark:hover:bg-red-500/10 border border-transparent hover:border-red-200 dark:hover:border-red-500/20 transition-all text-sm font-medium">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                </svg>
                Выйти
            </button>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="flex-1 flex flex-col relative z-10 overflow-hidden bg-gray-100 dark:bg-dark-900 transition-colors duration-300">
        <!-- Topbar -->
        <header class="h-20 min-h-[5rem] px-4 md:px-8 flex items-center justify-between client-header z-30 transition-[margin] duration-300 ease-in-out"
                :class="isHeaderHidden ? '-mt-20 lg:mt-0' : 'mt-0'">
            <div class="flex items-center gap-4">
                <!-- Mobile Menu Button -->
                <button @click="isSidebarOpen = true" class="lg:hidden p-2 -ml-2 text-gray-400 dark:text-gray-500 hover:text-gray-800 dark:hover:text-white rounded-lg hover:bg-gray-200 dark:hover:bg-white/5 transition-colors">
                    <Bars3Icon class="w-6 h-6" />
                </button>

                <!-- Decorative accent -->
                <div class="h-8 w-1 bg-gradient-to-b from-gold-500 to-gold-600 rounded-full shadow-[0_0_10px_rgba(212,175,55,0.5)]"></div>
                <h2 class="text-2xl font-kanit font-bold text-gray-900 dark:text-white tracking-wide uppercase drop-shadow-md">
                    {{ navigation.find(i => i.path === route.path)?.name || route.meta.title || route.name || 'Дашборд' }}
                </h2>
            </div>
            
            <div class="flex items-center gap-4 md:gap-6">
                <!-- Notifications Bell -->
                <div class="relative" ref="notificationRef">
                    <button @click="toggleNotifications" class="relative outline-none focus:outline-none focus:ring-0 text-gray-400 dark:text-gray-500 hover:text-gray-800 dark:hover:text-white transition-colors p-2 rounded-lg hover:bg-gray-200 dark:hover:bg-white/5">
                        <div v-if="unreadCount > 0" class="absolute top-1 right-1 rounded-full">
                            <span class="absolute inset-0 inline-flex w-full h-full rounded-full bg-red-400 opacity-75 animate-ping"></span>
                            <span class="relative flex items-center justify-center min-w-[18px] h-[18px] px-1 bg-red-500 rounded-full text-[10px] font-bold text-white border-2 border-white dark:border-stone-900">
                                {{ unreadCount > 9 ? '9+' : unreadCount }}
                            </span>
                        </div>
                        <BellIcon class="w-6 h-6" />
                    </button>

                    <!-- Notification Dropdown -->
                    <transition 
                        enter-active-class="transition ease-out duration-200"
                        enter-from-class="opacity-0 scale-95 -translate-y-1"
                        enter-to-class="opacity-100 scale-100 translate-y-0"
                        leave-active-class="transition ease-in duration-150"
                        leave-from-class="opacity-100 scale-100"
                        leave-to-class="opacity-0 scale-95 -translate-y-1"
                    >
                        <div v-if="showNotifications" class="absolute right-0 top-full mt-2 w-[320px] md:w-[400px] max-h-[520px] bg-white dark:bg-stone-900 border border-gray-200 dark:border-white/5 rounded-xl shadow-2xl overflow-hidden flex flex-col z-50">
                            <!-- Header -->
                            <div class="px-5 py-4 border-b border-gray-200 dark:border-white/5 flex items-center justify-between flex-shrink-0 bg-gray-50 dark:bg-stone-800/50">
                                <div class="flex items-center gap-2">
                                    <h3 class="text-sm font-bold text-gray-900 dark:text-gray-100 uppercase tracking-wider">Уведомления</h3>
                                    <span v-if="unreadCount > 0" class="px-2 py-0.5 bg-red-500/10 text-red-500 text-[10px] font-bold rounded-full">{{ unreadCount }}</span>
                                </div>
                                <button v-if="unreadCount > 0" @click="markAllAsRead" class="text-xs text-gold-500 hover:text-gold-600 font-medium transition-colors">
                                    Прочитать все
                                </button>
                            </div>

                            <!-- List -->
                            <div class="flex-1 overflow-y-auto custom-scrollbar">
                                <!-- Loading -->
                                <div v-if="isLoadingNotifications" class="p-5 space-y-3">
                                    <div v-for="i in 3" :key="i" class="flex gap-3 animate-pulse">
                                        <div class="w-9 h-9 rounded-lg bg-gray-100 dark:bg-stone-800 flex-shrink-0"></div>
                                        <div class="flex-1 space-y-2">
                                            <div class="h-3 w-3/4 bg-gray-100 dark:bg-stone-800 rounded"></div>
                                            <div class="h-2.5 w-full bg-gray-100 dark:bg-stone-800 rounded"></div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Empty -->
                                <div v-else-if="notifications.length === 0" class="py-12 text-center">
                                    <div class="w-12 h-12 mx-auto mb-3 rounded-full bg-gray-100 dark:bg-stone-800/50 flex items-center justify-center">
                                        <BellIcon class="w-6 h-6 text-gray-400 dark:text-gray-500" />
                                    </div>
                                    <p class="text-sm text-gray-600 dark:text-gray-300 font-medium">Нет уведомлений</p>
                                    <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">Здесь появятся ваши уведомления</p>
                                </div>

                                <!-- Items -->
                                <div v-else>
                                    <div v-for="notification in notifications" :key="notification.id"
                                         @click="handleNotificationClick(notification)"
                                         class="px-5 py-3.5 border-b border-gray-100 dark:border-white/5 hover:bg-gray-50 dark:hover:bg-stone-800/50 cursor-pointer transition-colors group flex gap-3"
                                         :class="!notification.read_at ? 'bg-gold-500/5' : ''">
                                        <!-- Icon -->
                                        <div class="w-9 h-9 rounded-lg flex items-center justify-center flex-shrink-0 text-base"
                                             :class="getTypeInfo(notification.type).color">
                                            {{ getTypeInfo(notification.type).icon }}
                                        </div>
                                        <!-- Content -->
                                        <div class="flex-1 min-w-0">
                                            <div class="flex items-start justify-between gap-2">
                                                <p class="text-sm font-bold text-gray-900 dark:text-gray-100 truncate" :class="!notification.read_at ? '' : 'text-gray-500 dark:text-gray-400'">
                                                    {{ notification.title }}
                                                </p>
                                                <span v-if="!notification.read_at" class="w-2 h-2 rounded-full bg-gold-500 flex-shrink-0 mt-1.5"></span>
                                            </div>
                                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5 line-clamp-2">{{ notification.message }}</p>
                                            <p class="text-[10px] text-gray-400 dark:text-gray-500 mt-1 font-mono">{{ timeAgo(notification.created_at) }}</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </transition>

                </div>
            </div>
        </header>

        <!-- View Content with Scroll -->
        <div ref="scrollContentRef" class="flex-1 overflow-y-auto p-4 md:p-8 custom-scrollbar bg-gray-100 dark:bg-dark-900">
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

    <!-- Logout Confirmation Modal -->
    <StandardModal 
        :is-open="showLogoutModal" 
        theme="gold"
        z-index-class="z-[200]"
        backdrop-z-index-class="z-[150]"
        :backdrop-blur="false"
        @close="showLogoutModal = false"
    >
        <div class="text-center pt-2">
            <h3 class="text-xl font-kanit font-bold text-gray-900 dark:text-gray-100 tracking-wide uppercase mb-6">Выйти<span class="text-2xl">?</span></h3>
            <p class="text-gray-500 dark:text-gray-400 text-sm mb-2 font-light">
                Сессия
            </p>
            <p class="text-gray-900 dark:text-gray-100 font-bold text-lg mb-2">
                {{ user?.name || 'Клиент' }}
            </p>
            <p class="text-gold-500 text-sm font-semibold mb-6 tracking-wide">
                будет завершена
            </p>
            
            <div class="flex gap-3">
                <button @click="showLogoutModal = false" class="flex-1 py-3 rounded-lg text-xs font-bold uppercase tracking-widest client-btn-secondary">
                    Отмена
                </button>
                <button @click="confirmLogout" class="flex-1 py-3 bg-gold-600 hover:bg-gold-500 text-white rounded-lg font-bold text-xs uppercase tracking-widest shadow-lg shadow-gold-500/20 transition-all transform active:scale-95 border border-gold-500/50">
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
  background: transparent;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
  background: rgba(156, 163, 175, 0.3);
  border-radius: 10px;
}
.custom-scrollbar::-webkit-scrollbar-thumb:hover {
  background: rgba(156, 163, 175, 0.5);
}
.dark .custom-scrollbar::-webkit-scrollbar-thumb {
  background: rgba(212, 175, 55, 0.2);
}
.dark .custom-scrollbar::-webkit-scrollbar-thumb:hover {
  background: rgba(212, 175, 55, 0.4);
}

.line-clamp-2 {
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
</style>
