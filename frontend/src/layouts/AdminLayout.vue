<script setup>
import { computed, ref, onMounted, onUnmounted, watch, nextTick } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import { useTheme } from '../composables/useTheme'
import StandardModal from '../components/ui/StandardModal.vue'
import axios from 'axios'

const router = useRouter()
const route = useRoute()
const authStore = useAuthStore()
const { theme } = useTheme()

const user = computed(() => authStore.adminUser)

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

    nav.push({
        name: 'Профиль',
        path: '/admin/profile',
        icon: 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z'
    })
    
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
    authStore.logout('admin')
}

// ===== Notifications =====
const showNotifications = ref(false)
const notifications = ref([])
const unreadCount = ref(0)
const isLoadingNotifications = ref(false)
let pollInterval = null

const typeIcons = {
    auction_invite: { icon: '📨', color: 'text-blue-400 bg-blue-500/10' },
    bid_outbid: { icon: '⚡', color: 'text-red-400 bg-red-500/10' },
    auction_started: { icon: '🔴', color: 'text-green-400 bg-green-500/10' },
    auction_ended: { icon: '🏁', color: 'text-emerald-400 bg-emerald-500/10' },
    offer_accepted: { icon: '✅', color: 'text-cyan-400 bg-cyan-500/10' },
    status_change: { icon: '🔄', color: 'text-yellow-400 bg-yellow-500/10' },
    new_bid: { icon: '💰', color: 'text-amber-400 bg-amber-500/10' },
    system: { icon: '⚙️', color: 'text-gray-400 bg-gray-500/10' },
}

const getTypeInfo = (type) => typeIcons[type] || { icon: '🔔', color: 'text-gray-400 bg-white/5' }

const fetchUnreadCount = async () => {
    try {
        const res = await axios.get('/api/admin/notifications/unread-count')
        unreadCount.value = res.data.count
    } catch (e) { /* silently fail */ }
}

const fetchNotifications = async () => {
    isLoadingNotifications.value = true
    try {
        const res = await axios.get('/api/admin/notifications')
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
        await axios.post(`/api/admin/notifications/${notification.id}/read`)
        notification.read_at = new Date().toISOString()
        unreadCount.value = Math.max(0, unreadCount.value - 1)
    } catch (e) { /* silently fail */ }
}

const markAllAsRead = async () => {
    try {
        await axios.post('/api/admin/notifications/read-all')
        notifications.value.forEach(n => { n.read_at = n.read_at || new Date().toISOString() })
        unreadCount.value = 0
    } catch (e) { /* silently fail */ }
}

const handleNotificationClick = async (notification) => {
    await markAsRead(notification)
    if (notification.auction_id) {
        router.push(`/admin/auctions/${notification.auction_id}`)
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
})

onUnmounted(() => {
    if (pollInterval) clearInterval(pollInterval)
})
</script>
    
    <template>
      <div class="h-screen w-full bg-gray-100 dark:bg-dark-900 text-gray-900 dark:text-white flex overflow-hidden admin-dark-scroll transition-colors duration-300">
        <!-- Grid Background -->
        <div class="fixed inset-0 bg-[url('/img/grid.svg')] bg-center [mask-image:linear-gradient(180deg,white,rgba(255,255,255,0))] opacity-0 dark:opacity-10 pointer-events-none"></div>
        
    <!-- Sidebar -->
    <aside class="w-64 bg-white dark:bg-dark-800/80 backdrop-blur-xl border-r border-gray-200 dark:border-red-500/10 flex flex-col z-[60] relative shadow-lg dark:shadow-[4px_0_30px_rgba(0,0,0,0.5)] transition-colors duration-300">
        <!-- Logo Area -->
        <div class="h-20 flex items-center justify-center border-b border-gray-200 dark:border-white/5 relative overflow-hidden">
            <router-link to="/admin" class="flex items-center gap-3 group hover:opacity-90 transition-opacity">
                 <img src="/logo.png" alt="Atlas" class="w-8 h-8 rounded shadow-[0_0_20px_rgba(239,68,68,0.6)] object-cover group-hover:scale-105 transition-transform duration-300" />
                <h1 class="text-lg font-kanit font-bold tracking-wider text-gray-900 dark:text-white whitespace-nowrap">АТЛАС <span class="text-red-500 group-hover:text-red-400 transition-colors">АДМИН</span></h1>
            </router-link>
        </div>

        <!-- Navigation -->
        <nav class="flex-1 py-8 px-4 space-y-2">
            <template v-for="item in navigation" :key="item.path">
                <router-link 
                    :to="item.path"
                    class="flex items-center gap-4 px-4 py-3 rounded-lg transition-all duration-300 group focus:outline-none"
                    :class="route.path === item.path ? 'bg-red-500/10 dark:bg-red-500/20 text-red-600 dark:text-white shadow-sm dark:shadow-[0_0_15px_rgba(239,68,68,0.3)] border border-red-500/30 dark:border-red-500/50' : 'text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-white/5 hover:text-gray-900 dark:hover:text-white border border-transparent'"
                >
                    <svg class="w-5 h-5 transition-colors" :class="route.path === item.path ? 'text-red-500 dark:text-red-400' : 'text-gray-400 dark:text-gray-500 group-hover:text-red-500 dark:group-hover:text-red-400'" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="item.icon" />
                    </svg>
                    <span class="font-medium tracking-wide text-sm">{{ item.name }}</span>
                </router-link>
            </template>
        </nav>

        <!-- Logout (Bottom Sidebar) -->
        <div class="p-4 border-t border-gray-200 dark:border-white/5">
            <button @click="handleLogout" class="w-full flex items-center justify-start gap-2 px-4 py-2.5 rounded-xl text-gray-500 dark:text-gray-500 hover:text-red-500 dark:hover:text-red-400 hover:bg-red-50 dark:hover:bg-red-500/10 border border-transparent hover:border-red-200 dark:hover:border-red-500/20 transition-all text-sm font-medium">
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
        <header class="h-20 min-h-[5rem] px-8 flex items-center justify-between border-b border-gray-200 dark:border-white/10 bg-white/90 dark:bg-dark-800/90 backdrop-blur-md sticky top-0 z-30 shadow-sm dark:shadow-lg dark:shadow-black/20 transition-colors duration-300">
            <div class="flex items-center gap-4">
                 <!-- Decorative accent -->
                 <div class="h-8 w-1 bg-gradient-to-b from-red-500 to-red-600 rounded-full shadow-[0_0_10px_rgba(239,68,68,0.5)]"></div>
                 <h2 class="text-2xl font-kanit font-bold text-gray-900 dark:text-white tracking-wide uppercase drop-shadow-md">
                     {{ pageTitle }}
                 </h2>
            </div>
            
            <div class="flex items-center gap-6">
                <!-- Notifications Bell -->
                <div class="relative" ref="notificationRef">
                    <button @click="toggleNotifications" class="relative outline-none focus:outline-none focus:ring-0 text-gray-500 hover:text-gray-900 dark:hover:text-white transition-colors p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-white/5 active:scale-95">
                        <div v-if="unreadCount > 0" class="absolute top-0.5 right-0.5 rounded-full">
                            <span class="absolute inset-0 inline-flex w-full h-full rounded-full bg-red-500 opacity-75 animate-ping"></span>
                            <span class="relative flex items-center justify-center min-w-[18px] h-[18px] px-1 bg-red-500 rounded-full text-[10px] font-bold text-white border-2 border-dark-800 shadow-[0_0_8px_rgba(239,68,68,0.4)]">
                                {{ unreadCount > 9 ? '9+' : unreadCount }}
                            </span>
                        </div>
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0" /></svg>
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
                        <div v-if="showNotifications" class="absolute right-0 top-full mt-2 w-[360px] md:w-[420px] max-h-[520px] bg-white dark:bg-dark-800 border border-gray-200 dark:border-white/10 rounded-xl shadow-2xl dark:shadow-black/50 overflow-hidden flex flex-col z-50">
                            <!-- Header -->
                            <div class="px-5 py-4 border-b border-gray-100 dark:border-white/5 flex items-center justify-between flex-shrink-0 bg-gray-50 dark:bg-dark-900/50">
                                <div class="flex items-center gap-2">
                                    <h3 class="text-sm font-bold text-gray-900 dark:text-white uppercase tracking-wider">Уведомления</h3>
                                    <span v-if="unreadCount > 0" class="px-2 py-0.5 bg-red-500/10 text-red-400 text-[10px] font-bold rounded-full">{{ unreadCount }}</span>
                                </div>
                                <button v-if="unreadCount > 0" @click="markAllAsRead" class="text-xs text-red-500 dark:text-red-400 hover:text-red-600 dark:hover:text-red-300 font-medium transition-colors">
                                    Прочитать все
                                </button>
                            </div>

                            <!-- List -->
                            <div class="flex-1 overflow-y-auto custom-scrollbar">
                                <!-- Loading -->
                                <div v-if="isLoadingNotifications" class="p-5 space-y-3">
                                    <div v-for="i in 3" :key="i" class="flex gap-3 animate-pulse">
                                        <div class="w-9 h-9 rounded-lg bg-white/5 flex-shrink-0"></div>
                                        <div class="flex-1 space-y-2">
                                            <div class="h-3 w-3/4 bg-white/5 rounded"></div>
                                            <div class="h-2.5 w-full bg-white/5 rounded"></div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Empty -->
                                <div v-else-if="notifications.length === 0" class="py-12 text-center">
                                    <div class="w-12 h-12 mx-auto mb-3 rounded-full bg-gray-100 dark:bg-white/5 flex items-center justify-center">
                                        <svg class="w-6 h-6 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0" /></svg>
                                    </div>
                                    <p class="text-sm text-gray-600 dark:text-gray-300 font-medium">Нет уведомлений</p>
                                    <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">Здесь появятся ваши уведомления</p>
                                </div>

                                <!-- Items -->
                                <div v-else>
                                    <div v-for="notification in notifications" :key="notification.id"
                                         @click="handleNotificationClick(notification)"
                                         class="px-5 py-3.5 border-b border-gray-100 dark:border-white/5 hover:bg-gray-50 dark:hover:bg-white/5 cursor-pointer transition-colors group flex gap-3"
                                         :class="!notification.read_at ? 'bg-red-50 dark:bg-red-500/5' : ''">
                                        <!-- Icon -->
                                        <div class="w-9 h-9 rounded-lg flex items-center justify-center flex-shrink-0 text-base"
                                             :class="getTypeInfo(notification.type).color">
                                            {{ getTypeInfo(notification.type).icon }}
                                        </div>
                                        <!-- Content -->
                                        <div class="flex-1 min-w-0">
                                            <div class="flex items-start justify-between gap-2">
                                                <p class="text-sm font-bold truncate" :class="!notification.read_at ? 'text-gray-900 dark:text-white' : 'text-gray-500 dark:text-gray-400'">
                                                    {{ notification.title }}
                                                </p>
                                                <span v-if="!notification.read_at" class="w-2 h-2 rounded-full bg-red-500 flex-shrink-0 mt-1.5 shadow-[0_0_6px_rgba(239,68,68,0.5)]"></span>
                                            </div>
                                            <p class="text-xs text-gray-500 mt-0.5 line-clamp-2">{{ notification.message }}</p>
                                            <p class="text-[10px] text-gray-600 mt-1 font-mono">{{ timeAgo(notification.created_at) }}</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </transition>

                </div>
            </div>
        </header>

        <!-- View Content -->
        <div class="flex-1 overflow-y-scroll p-8 custom-scrollbar transition-colors duration-300">
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
            <h3 class="text-xl font-kanit font-bold text-gray-900 dark:text-white tracking-wide uppercase mb-6">Выйти<span class="text-2xl">?</span></h3>
            <p class="text-gray-500 dark:text-gray-400 text-sm mb-2 font-light">
                Сессия
            </p>
            <p class="text-gray-900 dark:text-white font-bold text-lg mb-2">
                {{ user?.name || 'Admin' }}
            </p>
            <p class="text-red-500 dark:text-red-400 text-sm font-semibold mb-6 tracking-wide">
                будет завершена
            </p>
            
            <div class="flex gap-3">
                <button @click="showLogoutModal = false" class="flex-1 py-3 rounded-lg text-xs font-bold uppercase tracking-widest text-gray-500 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white bg-gray-100 dark:bg-white/5 hover:bg-gray-200 dark:hover:bg-white/10 transition-colors border border-transparent hover:border-gray-300 dark:hover:border-white/10">
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

:root:not(.dark) .custom-scrollbar::-webkit-scrollbar-track {
  background: rgba(0, 0, 0, 0.02);
}
:root:not(.dark) .custom-scrollbar::-webkit-scrollbar-thumb {
  background: rgba(239, 68, 68, 0.2);
}
:root:not(.dark) .custom-scrollbar::-webkit-scrollbar-thumb:hover {
  background: rgba(239, 68, 68, 0.4);
}
</style>
