<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import axios from 'axios'
import ModernNumberInput from '../../components/ui/ModernNumberInput.vue'
import echo from '@/echo.js'
import { useConnectionStatus } from '@/composables/useConnectionStatus.js'

const { isConnected, statusText } = useConnectionStatus()

const route = useRoute()
const router = useRouter()

const auctionId = computed(() => route.params.id)
const auction = ref(null)
const myOffers = ref([])
const allOffers = ref([])
const myBids = ref([])
const allBids = ref([])
const myStatus = ref('none')
const myWinningBars = ref(0)
const highestBid = ref(null)
const isLoading = ref(true)
const error = ref(null)
const isGpb = ref(false)
const gpbReport = ref(null)
const previousBidsCount = ref(0)
const newBidsFlash = ref(false)
const goBack = () => router.push('/client/auctions')

// Forms
const offerForm = ref({ volume: 1, price: '', comment: '' })
const bidForm = ref({ bar_count: 1, amount: '' })
const isSubmittingOffer = ref(false)
const isSubmittingBid = ref(false)
const formErrors = ref({})
const successMessage = ref('')

// Timer
const timerNow = ref(Date.now())
let timerInterval = null

const startTimer = () => {
    timerInterval = setInterval(() => { timerNow.value = Date.now() }, 1000)
}

// Countdown for active auction (until end_at)
const countdownFormatted = computed(() => {
    if (!auction.value || auction.value.status !== 'active' || !auction.value.end_at) return null
    const diff = new Date(auction.value.end_at).getTime() - timerNow.value
    if (diff <= 0) return '00:00:00'
    return formatDuration(diff)
})
const isCountdownWarning = computed(() => {
    if (!auction.value?.end_at) return false
    const diff = new Date(auction.value.end_at).getTime() - timerNow.value
    return diff > 0 && diff <= 300000 // 5 min
})
const isCountdownCritical = computed(() => {
    if (!auction.value?.end_at) return false
    const diff = new Date(auction.value.end_at).getTime() - timerNow.value
    return diff > 0 && diff <= 60000 // 1 min
})

// Countdown for GPB right (until gpb_started_at + gpb_minutes)
const gpbCountdownFormatted = computed(() => {
    if (!auction.value || auction.value.status !== 'gpb_right' || !auction.value.gpb_started_at) return null
    const gpbEnd = new Date(auction.value.gpb_started_at).getTime() + (auction.value.gpb_minutes || 30) * 60000
    const diff = gpbEnd - timerNow.value
    if (diff <= 0) return '00:00'
    return formatDuration(diff)
})
const isGpbCritical = computed(() => {
    if (!auction.value?.gpb_started_at) return false
    const gpbEnd = new Date(auction.value.gpb_started_at).getTime() + (auction.value.gpb_minutes || 30) * 60000
    return (gpbEnd - timerNow.value) > 0 && (gpbEnd - timerNow.value) <= 60000
})

// Countdown for scheduled auction (until start_at)
const scheduledCountdownFormatted = computed(() => {
    if (!auction.value || auction.value.status !== 'scheduled' || !auction.value.start_at) return null
    const diff = new Date(auction.value.start_at).getTime() - timerNow.value
    if (diff <= 0) return '00:00:00'
    return formatDuration(diff)
})

// Last minute critical effect for scheduled countdown
const isScheduledCritical = computed(() => {
    if (!auction.value?.start_at || auction.value.status !== 'scheduled') return false
    const diff = new Date(auction.value.start_at).getTime() - timerNow.value
    return diff > 0 && diff <= 60000
})

// Format time duration
const formatDuration = (ms) => {
    const totalSec = Math.floor(ms / 1000)
    const days = Math.floor(totalSec / 86400)
    const hours = Math.floor((totalSec % 86400) / 3600)
    const mins = Math.floor((totalSec % 3600) / 60)
    const secs = totalSec % 60
    const pad = (n) => String(n).padStart(2, '0')
    if (days > 0) return `${days}д ${pad(hours)}:${pad(mins)}:${pad(secs)}`
    return `${pad(hours)}:${pad(mins)}:${pad(secs)}`
}

// Min bid amount (for bidding form validation)
const minBidAmount = computed(() => {
    if (!auction.value) return 0
    const highest = highestBid.value?.amount
    const minPrice = Number(auction.value.min_price || 0)
    const step = Number(auction.value.min_step || 0)
    if (highest) return Number(highest) + step
    return minPrice
})

const statusOptions = {
    draft: { label: 'Черновик', color: 'bg-gray-500/20 text-gray-400', icon: '○' },
    collecting_offers: { label: 'Сбор предложений', color: 'bg-cyan-500/20 text-cyan-400', icon: '📋' },
    scheduled: { label: 'Запланирован', color: 'bg-purple-500/20 text-purple-400', icon: '📅' },
    active: { label: 'Активный', color: 'bg-amber-500/20 text-amber-400', icon: '🔴' },
    gpb_right: { label: 'Право ГПБ', color: 'bg-blue-500/20 text-blue-400', icon: '🏛' },
    commission: { label: 'Работа комиссии', color: 'bg-orange-500/20 text-orange-400', icon: '⚖' },
    completed: { label: 'Завершён', color: 'bg-emerald-500/20 text-emerald-400', icon: '✅' },
    paused: { label: 'Приостановлен', color: 'bg-amber-800/20 text-amber-700', icon: '⏸' },
    cancelled: { label: 'Отменён', color: 'bg-red-500/20 text-red-400', icon: '❌' },
}

const getStatusInfo = (status) => statusOptions[status] || statusOptions.draft

const fetchAuction = async () => {
    try {
        const response = await axios.get(`/api/client/auctions/${auctionId.value}`)
        const oldBidsCount = allBids.value.length
        auction.value = response.data.auction
        myOffers.value = response.data.my_offers || []
        allOffers.value = response.data.all_offers || []
        if (myOffers.value.length > 0) {
            offerForm.value.price = myOffers.value[0].price
        } else if (!offerForm.value.price && auction.value.min_price) {
            offerForm.value.price = auction.value.min_price
        }
        
        // Default to max available bars if volume is not set by user
        if (offerForm.value.volume === 1) {
            offerForm.value.volume = auction.value.bar_count
        }
        
        myBids.value = response.data.my_bids || []
        allBids.value = response.data.all_bids || []
        myStatus.value = response.data.my_status || 'none'
        myWinningBars.value = response.data.my_winning_bars || 0
        highestBid.value = response.data.highest_bid || null
        isGpb.value = response.data.is_gpb || false
        gpbReport.value = response.data.gpb_report || null

        // Flash notification on new bids
        if (oldBidsCount > 0 && allBids.value.length > oldBidsCount) {
            newBidsFlash.value = true
            setTimeout(() => { newBidsFlash.value = false }, 3000)
        }
        previousBidsCount.value = allBids.value.length
    } catch (e) {
        if (e.response?.status === 403) {
            error.value = 'Вы не являетесь участником этого аукциона'
        } else {
            error.value = 'Не удалось загрузить аукцион'
        }
        console.error(e)
    } finally {
        isLoading.value = false
    }
}

// Bid allocation computed
const winningBids = computed(() => allBids.value.filter(b => b.status === 'winning' || b.status === 'partial'))
const losingBids = computed(() => allBids.value.filter(b => b.status === 'losing'))
const myWinningSum = computed(() => {
    const barWeight = auction.value?.bar_weight || 0
    return allBids.value
        .filter(b => b.is_mine && b.fulfilled > 0)
        .reduce((sum, b) => sum + (b.fulfilled * (barWeight * 1000) * Number(b.amount)), 0)
})

const lotSummary = computed(() => {
    const totalBars = auction.value?.bar_count || 0
    const barWeight = auction.value?.bar_weight || 0
    const lotBars = winningBids.value.reduce((s, b) => s + (b.fulfilled || 0), 0)
    const lotWeight = lotBars * barWeight
    const lotTotal = winningBids.value.reduce((s, b) => s + (b.fulfilled || 0) * (barWeight * 1000 || 1) * Number(b.amount), 0)
    return { totalBars, barWeight, lotBars, lotWeight, lotTotal }
})

// Min price per gram
const minPricePerGram = computed(() => {
    if (!auction.value) return null
    const minPrice = Number(auction.value.min_price) || 0
    if (minPrice <= 0) return null
    return minPrice
})


// Bid total preview
const bidTotalPreview = computed(() => {
    if (!bidForm.value.amount || !bidForm.value.bar_count || !auction.value) return null
    const total = Number(bidForm.value.amount) * Number(bidForm.value.bar_count) * (Number(auction.value.bar_weight) * 1000 || 1)
    return total
})

// Submit offer
const submitOffer = async () => {
    formErrors.value = {}
    successMessage.value = ''

    if (!offerForm.value.volume || offerForm.value.volume < 1) {
        formErrors.value.volume = 'Минимум 1 слиток'
        return
    }
    if (!offerForm.value.price || offerForm.value.price <= 0) {
        formErrors.value.price = 'Укажите цену'
        return
    }
    if (Number(offerForm.value.price) < Number(auction.value.min_price)) {
        formErrors.value.price = `Не меньше минимальной (${formatPrice(auction.value.min_price)} ₽/г)`
        return
    }

    isSubmittingOffer.value = true
    try {
        await axios.post(`/api/client/auctions/${auctionId.value}/offer`, {
            volume: offerForm.value.volume,
            price: offerForm.value.price,
            comment: offerForm.value.comment || null,
        })
        successMessage.value = 'Предложение отправлено!'
        offerForm.value.volume = auction.value.bar_count
        offerForm.value.comment = ''
        await fetchAuction()
        setTimeout(() => { successMessage.value = '' }, 3000)
    } catch (e) {
        if (e.response?.data?.errors) {
            formErrors.value = e.response.data.errors
        } else {
            formErrors.value.general = e.response?.data?.message || 'Ошибка отправки'
        }
    } finally {
        isSubmittingOffer.value = false
    }
}

// Place bid
const placeBid = async () => {
    formErrors.value = {}
    successMessage.value = ''

    if (!bidForm.value.bar_count || bidForm.value.bar_count < 1) {
        formErrors.value.bar_count = 'Минимум 1 слиток'
        return
    }
    if (!bidForm.value.amount || bidForm.value.amount <= 0) {
        formErrors.value.amount = 'Укажите цену'
        return
    }

    // Frontend min_step validation
    if (minBidAmount.value && Number(bidForm.value.amount) < minBidAmount.value) {
        formErrors.value.amount = `Минимум: ₽ ${formatPrice(minBidAmount.value)}/г`
        return
    }

    isSubmittingBid.value = true
    try {
        const response = await axios.post(`/api/client/auctions/${auctionId.value}/bid`, {
            bar_count: bidForm.value.bar_count,
            amount: bidForm.value.amount,
        })
        successMessage.value = response.data.message || 'Ставка принята!'
        await fetchAuction()
        setTimeout(() => { successMessage.value = '' }, 3000)
    } catch (e) {
        if (e.response?.data?.errors) {
            formErrors.value = e.response.data.errors
        } else {
            formErrors.value.general = e.response?.data?.message || 'Ошибка подачи ставки'
        }
    } finally {
        isSubmittingBid.value = false
    }
}

// ===== GPB Priority Purchase =====
const gpbForm = ref({ bar_count: 1 })
const isSubmittingGpb = ref(false)

// Winning bids sorted by price ASC (cheapest first) — for GPB to take
const gpbAvailableBids = computed(() => {
    return [...winningBids.value].sort((a, b) => Number(a.amount) - Number(b.amount))
})

// Max bars GPB can take
const gpbMaxBars = computed(() => lotSummary.value.lotBars || 0)

// Live preview: which bids GPB takes at current bar_count
const gpbPreview = computed(() => {
    const want = gpbForm.value.bar_count || 0
    const barWeight = auction.value?.bar_weight || 0
    let remaining = want
    const taken = []
    for (const bid of gpbAvailableBids.value) {
        if (remaining <= 0) break
        const take = Math.min(bid.fulfilled, remaining)
        taken.push({
            ...bid,
            gpbTake: take,
            gpbPricePerGram: Number(bid.amount),
            gpbPricePerBar: Number(bid.amount) * (barWeight * 1000 || 1),
            gpbSum: take * (barWeight * 1000 || 1) * Number(bid.amount)
        })
        remaining -= take
    }
    const totalBars = taken.reduce((s, b) => s + b.gpbTake, 0)
    const totalWeight = totalBars * barWeight
    const totalSum = taken.reduce((s, b) => s + b.gpbSum, 0)
    const minPrice = taken.length > 0 ? Math.min(...taken.map(b => b.gpbPricePerGram)) : 0
    return { taken, totalBars, totalWeight, totalSum, minPrice }
})

// Submit GPB purchase
const submitGpbPurchase = async () => {
    formErrors.value = {}
    successMessage.value = ''

    if (!gpbForm.value.bar_count || gpbForm.value.bar_count < 1) {
        formErrors.value.bar_count = 'Минимум 1 слиток'
        return
    }
    if (gpbForm.value.bar_count > gpbMaxBars.value) {
        formErrors.value.bar_count = `Максимум ${gpbMaxBars.value} слитков`
        return
    }

    isSubmittingGpb.value = true
    try {
        // GPB sends bar_count + minimum price from taken bids
        const response = await axios.post(`/api/client/auctions/${auctionId.value}/bid`, {
            bar_count: gpbForm.value.bar_count,
            amount: gpbPreview.value.minPrice,
        })
        successMessage.value = response.data.message || 'Слитки забраны!'
        await fetchAuction()
        setTimeout(() => { successMessage.value = '' }, 3000)
    } catch (e) {
        if (e.response?.data?.errors) {
            formErrors.value = e.response.data.errors
        } else {
            formErrors.value.general = e.response?.data?.message || 'Ошибка'
        }
    } finally {
        isSubmittingGpb.value = false
    }
}

const formatPrice = (val) => {
    if (!val) return '—'
    return Number(val).toLocaleString('ru-RU', { minimumFractionDigits: 2 })
}

const formatDate = (iso) => {
    if (!iso) return '—'
    return new Date(iso).toLocaleString('ru-RU', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' })
}

// Lot total weight
const totalWeight = computed(() => {
    if (!auction.value) return 0
    return ((auction.value.bar_count || 0) * (auction.value.bar_weight || 0)).toFixed(3)
})

let currentChannel = null

const subscribeWebSocket = () => {
    if (currentChannel) {
        echo.leaveChannel(`auction.${currentChannel}`)
    }
    currentChannel = auctionId.value
    echo.channel(`auction.${currentChannel}`)
        .listen('.bid.placed', () => {
            fetchAuction()
        })
        .listen('.offer.placed', () => {
            fetchAuction()
        })
        .listen('.auction.updated', () => {
            fetchAuction()
        })
}

const handleEsc = (e) => {
    if (e.key === 'Escape' && !['INPUT', 'TEXTAREA'].includes(e.target?.tagName)) {
        goBack()
    }
}

onMounted(async () => {
    document.addEventListener('keydown', handleEsc)
    await fetchAuction()
    startTimer()
    subscribeWebSocket()
})

// Re-fetch and re-subscribe when navigating between auctions
watch(auctionId, async (newId, oldId) => {
    if (newId && newId !== oldId) {
        isLoading.value = true
        await fetchAuction()
        subscribeWebSocket()
    }
})

onUnmounted(() => {
    document.removeEventListener('keydown', handleEsc)
    if (timerInterval) clearInterval(timerInterval)
    if (currentChannel) {
        echo.leaveChannel(`auction.${currentChannel}`)
    }
})
</script>

<template>
  <div class="space-y-6">
    

    <!-- Loading -->
    <div v-if="isLoading" class="flex items-center justify-center py-20">
        <div class="w-8 h-8 border-2 border-gold-500 border-t-transparent rounded-full animate-spin"></div>
    </div>

    <!-- Error -->
    <div v-else-if="error" class="bg-red-500/10 border border-red-500/30 rounded-xl p-8 text-center">
        <svg class="w-12 h-12 text-red-400 mx-auto mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z" />
        </svg>
        <p class="text-red-400 font-bold">{{ error }}</p>
    </div>

    <!-- Auction Content -->
    <template v-else-if="auction">
      <!-- Header Bar (Back button & Connection Status) -->
      <div class="flex items-center gap-4 mb-4 flex-shrink-0">
        <button @click="goBack" class="group flex items-center gap-2 px-4 py-2 rounded-lg bg-white dark:bg-white/5 border border-gray-200 dark:border-white/10 hover:bg-gray-50 dark:hover:bg-white/10 hover:border-gray-300 dark:hover:border-white/20 hover:shadow-[0_0_15px_rgba(0,0,0,0.05)] dark:hover:shadow-[0_0_15px_rgba(255,255,255,0.05)] shadow-sm dark:shadow-none transition-all duration-300 active:scale-95 text-xs font-bold uppercase tracking-widest text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white">
          <svg class="w-4 h-4 transition-transform group-hover:-translate-x-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
          Аукционы
        </button>
        <div class="flex-1"></div>
        <div class="flex items-center gap-2 px-3 py-1.5 rounded-full backdrop-blur-sm text-[10px] font-bold uppercase tracking-widest transition-all duration-500 shadow-sm dark:shadow-none bg-white dark:bg-dark-800/80 border"
             :class="isConnected
               ? 'border-emerald-200 dark:border-emerald-500/20 text-emerald-600 dark:text-emerald-500'
               : 'border-red-200 dark:border-red-500/30 text-red-500 dark:text-red-400 animate-pulse shadow-[0_0_12px_rgba(239,68,68,0.2)]'"
        >
          <span class="relative flex h-2 w-2">
            <span v-if="isConnected" class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
            <span v-else class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75"></span>
            <span class="relative inline-flex rounded-full h-2 w-2" :class="isConnected ? 'bg-emerald-500' : 'bg-red-500'"></span>
          </span>
          {{ isConnected ? 'На связи' : 'Нет связи' }}
        </div>
      </div>

      <!-- Auction Info Card - Glamorous & Compact -->
      <div class="mb-6 relative rounded-2xl overflow-hidden border border-gray-200 dark:border-white/10 shadow-md dark:shadow-none group flex-shrink-0 bg-white dark:bg-[#0f1115]">
        <!-- Background with subtle animated gradient -->
        <div class="absolute inset-0 bg-gradient-to-br from-gold-50/80 via-white to-blue-50/30 dark:from-blue-900/10 dark:via-transparent dark:to-red-900/10"></div>
        <div class="absolute top-0 right-0 -mt-20 -mr-20 w-80 h-80 bg-gold-400/10 blur-3xl rounded-full pointer-events-none dark:hidden"></div>
        <div class="absolute bottom-0 left-0 -mb-20 -ml-20 w-80 h-80 bg-blue-400/10 blur-3xl rounded-full pointer-events-none dark:hidden"></div>
        <div class="absolute inset-0 bg-[url('/img/grid.svg')] opacity-[0.03] dark:opacity-[0.03]"></div>

        <!-- Content Container -->
        <div class="relative z-10">
          <!-- Main Row: Title, Status, Timer -->
          <div class="flex flex-col md:flex-row items-stretch md:items-center justify-between pl-6 pr-6 py-4 gap-4">
            <!-- Left: Title & ID -->
            <div class="flex items-start md:items-center gap-3 md:gap-5 min-w-0 flex-1">
              <div class="flex flex-col items-center justify-center w-11 h-11 md:w-14 md:h-14 rounded-lg md:rounded-xl bg-gray-50 dark:bg-white/5 border border-gray-200 dark:border-white/10 shadow-inner flex-shrink-0 mt-0.5 md:mt-0">
                 <span class="text-[8px] md:text-[10px] font-bold text-gray-500 dark:text-gray-400 uppercase tracking-widest leading-none mb-0.5">№</span>
                 <span class="text-base md:text-xl font-mono font-bold text-gray-900 dark:text-white leading-none">{{ auction.id }}</span>
              </div>
              <div class="min-w-0 flex-1">
                <h1 class="text-xl md:text-2xl font-bold text-gray-900 dark:text-white font-kanit tracking-wide leading-tight md:truncate" :title="auction.title">{{ auction.title }}</h1>
                <p v-if="auction.description" class="text-[11px] md:text-xs text-gray-500 mt-1 md:mt-0.5 md:truncate">{{ auction.description }}</p>
              </div>
            </div>

            <!-- Right: Live Controls (Status + Timer) -->
            <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-4 w-full md:w-auto">
              <!-- Live Status & Timer Container -->
              <div class="relative group/status flex items-center overflow-hidden rounded-lg border transition-all duration-300 w-full"
                   :class="[
                     auction.status === 'active' ? 'border-amber-200 dark:border-amber-500/30 bg-white dark:bg-[#161920] shadow-[0_0_15px_rgba(245,158,11,0.05)] dark:shadow-none' :
                     auction.status === 'gpb_right' ? 'border-blue-200 dark:border-blue-500/30 bg-white dark:bg-[#161920]' :
                     auction.status === 'scheduled' ? 'border-purple-200 dark:border-purple-500/30 bg-white dark:bg-[#161920]' :
                     auction.status === 'collecting_offers' ? 'border-cyan-200 dark:border-cyan-500/30 bg-cyan-50/100 dark:bg-cyan-950/40' :
                     auction.status === 'completed' ? 'border-emerald-200 dark:border-emerald-500/30 bg-emerald-50/100 dark:bg-emerald-950/40' :
                     auction.status === 'commission' ? 'border-orange-200 dark:border-orange-500/30 bg-orange-50/100 dark:bg-orange-950/40' :
                     auction.status === 'paused' ? 'border-amber-200 dark:border-amber-800/30 bg-amber-50/100 dark:bg-amber-950/40' :
                     auction.status === 'cancelled' ? 'border-red-200 dark:border-red-500/30 bg-red-50/100 dark:bg-red-950/40' :
                     'bg-gray-50/100 dark:bg-white/5 border-gray-200 dark:border-white/10'
                   ]">
                 <!-- Animated Glow Background for active states (behind everything) -->
                 <div v-if="['active', 'gpb_right', 'scheduled'].includes(auction.status)" class="absolute inset-0 bg-gradient-to-r from-transparent via-gray-900/5 dark:via-white/5 to-transparent -translate-x-full animate-[shimmer_2s_infinite]"></div>

                 <!-- Status Section -->
                 <div class="px-4 py-2 flex items-center justify-start gap-2.5 w-full md:w-auto"
                      :class="[
                        ['active', 'gpb_right'].includes(auction.status) ? 'bg-gray-50 dark:bg-white/5 rounded-l-lg' :
                        auction.status === 'scheduled' ? 'bg-purple-50 dark:bg-white/5 rounded-l-lg' :
                        'rounded-lg'
                      ]">
                    <div class="relative flex h-2.5 w-2.5">
                      <span v-if="['active', 'gpb_right', 'scheduled'].includes(auction.status)" class="animate-ping absolute inline-flex h-full w-full rounded-full opacity-75" :class="[
                        auction.status === 'gpb_right' ? 'bg-blue-400 dark:bg-blue-500' :
                        auction.status === 'scheduled' ? 'bg-purple-400' :
                        'bg-amber-400 dark:bg-amber-500'
                      ]"></span>
                      <span class="relative inline-flex rounded-full h-2.5 w-2.5" :class="getStatusInfo(auction.status).color.split(' ')[0].replace('/20', '')"></span>
                    </div>
                    <div class="flex flex-col leading-none">
                      <span class="text-[9px] uppercase font-bold tracking-widest text-gray-500 dark:text-gray-400 mb-0.5">Статус</span>
                      <span class="text-xs font-bold whitespace-nowrap" :class="[
                        auction.status === 'active' ? 'text-amber-600 dark:text-amber-400 drop-shadow-sm' :
                        auction.status === 'gpb_right' ? 'text-blue-600 dark:text-blue-500 drop-shadow-sm' :
                        auction.status === 'scheduled' ? 'text-purple-600 dark:text-purple-400 drop-shadow-sm' :
                        auction.status === 'collecting_offers' ? 'text-cyan-600 dark:text-cyan-400 drop-shadow-sm' :
                        auction.status === 'completed' ? 'text-emerald-600 dark:text-emerald-400 drop-shadow-sm' :
                        'text-gray-900 dark:text-white'
                      ]">{{ getStatusInfo(auction.status).label }}</span>
                    </div>
                      <!-- Show partial / winning status inline if exists -->
                      <span v-if="['active', 'gpb_right'].includes(auction.status) && myStatus !== 'none' && !(isGpb && auction.status === 'gpb_right')" class="ml-2 pl-2 border-l border-gray-200 dark:border-white/10 flex flex-col leading-none">

                         <span v-if="myStatus === 'winning'" class="text-xs font-bold text-green-600 dark:text-green-400 flex items-center gap-1"><span class="text-[10px]">🏆</span> Вы ведёте</span>
                         <span v-else-if="myStatus === 'losing'" class="text-xs font-bold text-red-600 dark:text-red-400">Перебиты</span>
                      </span>
                 </div>

                 <!-- Active / GPB / Scheduled Countdown — inset button -->
                 <div v-if="auction.status === 'active' && countdownFormatted"
                      class="px-5 py-2 flex flex-col items-center justify-center min-w-[160px] rounded-r-lg border-l backdrop-blur-sm"
                      :class="isCountdownCritical ? 'bg-red-50 dark:bg-red-950/40 border-red-200 dark:border-red-500/30' : 'bg-amber-50 dark:bg-amber-950/40 border-amber-200 dark:border-amber-500/30'">
                     <span class="font-mono text-xl font-black tracking-widest leading-none"
                           :class="isCountdownCritical ? 'text-red-500 dark:text-red-400 animate-pulse' : isCountdownWarning ? 'text-yellow-600 dark:text-yellow-400' : 'text-amber-600 dark:text-amber-400'">
                         {{ countdownFormatted }}
                     </span>
                     <span class="text-[9px] uppercase font-bold tracking-widest text-gray-500 mt-1">До конца торгов</span>
                 </div>
                 <div v-else-if="auction.status === 'gpb_right' && gpbCountdownFormatted"
                      class="px-5 py-2 flex flex-col items-center justify-center min-w-[160px] rounded-r-lg border-l backdrop-blur-sm"
                      :class="isGpbCritical ? 'bg-red-50 dark:bg-red-950/40 border-red-200 dark:border-red-500/30' : 'bg-blue-50 dark:bg-blue-950/40 border-blue-200 dark:border-blue-500/30'">
                     <span class="font-mono text-xl font-black tracking-widest leading-none"
                           :class="isGpbCritical ? 'text-red-500 dark:text-red-400 animate-pulse' : 'text-blue-600 dark:text-blue-400'">
                         {{ gpbCountdownFormatted }}
                     </span>
                     <span class="text-[9px] uppercase font-bold tracking-widest text-gray-500 mt-1">До конца права ГПБ</span>
                 </div>
                 <div v-else-if="auction.status === 'scheduled' && scheduledCountdownFormatted"
                      class="px-5 py-2 flex flex-col items-center justify-center min-w-[160px] rounded-r-lg border-l backdrop-blur-sm transition-all duration-300"
                      :class="[
                        isScheduledCritical ? 'bg-purple-200 dark:bg-purple-900 border-purple-400 dark:border-purple-500' : 'bg-purple-100 dark:bg-purple-950/40 border-purple-300 dark:border-purple-500/30'
                      ]">
                     <span class="font-mono text-xl font-black tracking-widest leading-none transition-all duration-300"
                           :class="[
                             isScheduledCritical ? 'text-purple-600 dark:text-purple-300 animate-pulse' : 'text-purple-600 dark:text-purple-400'
                           ]">
                         {{ scheduledCountdownFormatted }}
                     </span>
                     <span class="text-[9px] uppercase font-bold tracking-widest mt-1"
                           :class="isScheduledCritical ? 'text-purple-600 dark:text-purple-300' : 'text-purple-500/70 dark:text-gray-500'">До начала</span>
                 </div>
                 <div v-else-if="auction.status === 'cancelled'"
                      class="px-5 py-2 flex flex-col items-center justify-center min-w-[120px] rounded-r-lg border-l bg-red-50 dark:bg-red-950/40 border-red-200 dark:border-red-500/30 backdrop-blur-sm">
                     <span class="text-xs font-bold text-red-500 dark:text-red-400 uppercase tracking-wider">Отменён</span>
                 </div>

              </div>
            </div>
          </div>
          
          <!-- Stats Strip - Glassmorphism -->
          <div class="flex flex-col md:flex-row md:flex-wrap items-start md:items-center border-t border-gray-100 dark:border-white/5 bg-gray-50 dark:bg-white/[0.02] backdrop-blur-sm px-6 py-4 gap-y-4 gap-x-8 text-sm min-h-[44px]">
             <!-- Lot -->
             <div class="flex items-center gap-2 min-w-[160px] flex-shrink-0">
               <span class="text-gray-500 text-[10px] md:text-xs uppercase font-bold tracking-wider">Лот</span>
               <span class="text-gray-900 dark:text-white font-bold text-xs md:text-sm">{{ auction.bar_count }} <span class="text-gray-500 font-normal">×</span> {{ auction.bar_weight }} <span class="text-gray-500 font-normal">кг</span> <span class="text-gray-500 font-normal">=</span> {{ totalWeight }} <span class="text-gray-500 font-normal">кг</span></span>
             </div>
             <!-- Min Price -->
             <div class="flex items-center gap-2 flex-shrink-0">
               <span class="text-gray-500 text-[10px] md:text-xs uppercase font-bold tracking-wider">Мин. цена</span>
               <span class="text-emerald-600 dark:text-emerald-400 font-bold font-mono text-xs md:text-sm">{{ formatPrice(auction.min_price) }} <span class="text-emerald-600/60 dark:text-emerald-500/60 font-sans font-normal text-[10px] md:text-xs">₽/г</span></span>
             </div>
             <!-- Step -->
             <div class="flex items-center gap-2 flex-shrink-0">
               <span class="text-gray-500 text-[10px] md:text-xs uppercase font-bold tracking-wider">Шаг</span>
               <span class="text-gray-900 dark:text-white font-bold font-mono text-xs md:text-sm">{{ formatPrice(auction.min_step) }} <span class="text-gray-500 font-sans font-normal text-[10px] md:text-xs">₽</span></span>
               <span class="text-gray-600 text-[10px] md:text-xs">({{ auction.step_time }} мин)</span>
             </div>
             <!-- Gpb -->
             <div class="flex items-center gap-2 flex-shrink-0">
               <span class="text-gray-500 text-[10px] md:text-xs uppercase font-bold tracking-wider">Право ГПБ</span>
               <span class="text-gray-900 dark:text-white font-bold text-xs md:text-sm">{{ auction.gpb_minutes || 30 }} <span class="text-gray-500 font-normal text-[10px] md:text-xs">мин</span></span>
             </div>
             <!-- Spacer (Desktop only) -->
             <div class="hidden lg:block flex-1"></div>
             <!-- Date -->
             <div class="flex items-center gap-2 text-xs md:text-sm text-gray-500 dark:text-gray-400 font-medium md:my-auto w-full lg:w-auto mt-2 md:mt-0 pt-3 md:pt-0 border-t md:border-none border-gray-200 dark:border-white/10">
               <svg class="w-3.5 h-3.5 md:w-4 md:h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
               <span>{{ formatDate(auction.start_at) }} — {{ formatDate(auction.end_at) }}</span>
             </div>
          </div>
        </div>
      </div>

      <!-- Success message -->
      <transition enter-active-class="transition ease-out duration-300" enter-from-class="opacity-0 -translate-y-2" enter-to-class="opacity-100 translate-y-0"
                  leave-active-class="transition ease-in duration-200" leave-from-class="opacity-100" leave-to-class="opacity-0">
          <div v-if="successMessage" class="bg-green-500/10 border border-green-500/30 rounded-lg px-5 py-3 flex items-center gap-3">
              <svg class="w-5 h-5 text-green-600 dark:text-green-400 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
              </svg>
              <span class="text-sm text-green-700 dark:text-green-400 font-bold">{{ successMessage }}</span>
          </div>
      </transition>


      <!-- Error message -->
      <div v-if="formErrors.general" class="bg-red-500/10 border border-red-500/30 rounded-lg px-5 py-3 flex items-center gap-3">
          <svg class="w-5 h-5 text-red-600 dark:text-red-400 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
          </svg>
          <span class="text-sm text-red-700 dark:text-red-400 font-bold">{{ formErrors.general }}</span>
      </div>

      <!-- ======== COLLECTING OFFERS SECTION ======== -->
      <div v-if="auction.status === 'collecting_offers'">
          <!-- ===== GPB: All Offers Table (like admin) ===== -->
          <div v-if="isGpb" class="bg-white dark:bg-dark-800/80 backdrop-blur-sm rounded-2xl border border-blue-300 dark:border-blue-500/30 shadow-sm dark:shadow-none p-6">
              <div class="flex items-center justify-between mb-5">
                  <h3 class="text-lg font-kanit font-bold text-gray-900 dark:text-white flex items-center gap-2">
                      <span class="text-xl">📋</span>
                      Поступившие предложения
                      <span v-if="allOffers.length > 0" class="ml-2 px-2 py-0.5 bg-blue-500/10 text-blue-500 text-xs font-bold rounded-full">{{ allOffers.length }}</span>
                  </h3>
              </div>

              <div v-if="allOffers.length === 0" class="text-center py-12">
                  <div class="w-16 h-16 mx-auto mb-4 rounded-2xl bg-gray-50 dark:bg-white/5 border border-gray-200 dark:border-white/10 flex items-center justify-center">
                      <span class="text-2xl">📭</span>
                  </div>
                  <p class="text-sm text-gray-500 dark:text-gray-400 font-medium">Предложений пока нет</p>
                  <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">Здесь будут отображаться предложения участников</p>
              </div>

              <div v-else class="overflow-x-auto overflow-y-hidden rounded-lg border border-gray-200 dark:border-white/10 bg-gray-50 dark:bg-dark-900/30">
                  <table class="w-full text-left border-collapse relative">
                      <thead class="sticky top-0 bg-gray-50 dark:bg-dark-900 z-10">
                          <tr class="border-b border-gray-200 dark:border-white/10 text-xs text-gray-500 uppercase tracking-widest font-bold">
                              <th class="px-4 py-3 bg-gray-50 dark:bg-dark-900 w-8">#</th>
                              <th class="px-4 py-3 bg-gray-50 dark:bg-dark-900">Участник</th>
                              <th class="px-4 py-3 text-center bg-gray-50 dark:bg-dark-900">Слитков</th>
                              <th class="px-4 py-3 text-right bg-gray-50 dark:bg-dark-900">Цена/грамм</th>
                              <th class="px-4 py-3 text-right bg-gray-50 dark:bg-dark-900">Сумма</th>
                              <th class="px-4 py-3 text-right bg-gray-50 dark:bg-dark-900">Дата</th>
                          </tr>
                      </thead>
                      <tbody>
                          <tr v-for="(offer, idx) in allOffers" :key="offer.id"
                              class="border-b border-gray-100 dark:border-white/5 transition-colors bg-white hover:bg-gray-50 dark:bg-transparent dark:hover:bg-white/5">
                              <td class="px-4 py-3 text-sm text-gray-500 font-mono">{{ idx + 1 }}</td>
                              <td class="px-4 py-3">
                                  <span class="text-sm font-bold text-gray-900 dark:text-white">{{ offer.participant }}</span>
                              </td>
                              <td class="px-4 py-3 text-center font-mono text-sm font-bold text-gray-900 dark:text-white">{{ offer.volume }}</td>
                              <td class="px-4 py-3 text-right font-mono text-sm text-cyan-400 font-bold"><span class="font-sans">₽</span>&nbsp;{{ formatPrice(offer.price) }}</td>
                              <td class="px-4 py-3 text-right font-mono text-sm text-gray-900 dark:text-white"><span class="font-sans">₽</span>&nbsp;{{ formatPrice(Number(offer.price) * Number(offer.volume) * (auction.bar_weight * 1000 || 1)) }}</td>
                              <td class="px-4 py-3 text-right font-mono text-xs text-gray-500">{{ formatDate(offer.created_at) }}</td>
                          </tr>
                      </tbody>
                  </table>
              </div>
          </div>

          <!-- ===== Regular participant: Offer Form + My Offers ===== -->
          <div v-else class="grid grid-cols-1 lg:grid-cols-2 gap-6">
              <!-- Submit Offer Form -->
              <div class="bg-white dark:bg-dark-800/80 backdrop-blur-sm rounded-2xl border border-cyan-500/20 shadow-sm dark:shadow-none p-6">
                  <h3 class="text-lg font-kanit font-bold text-gray-900 dark:text-white mb-5 flex items-center gap-2">
                      <span class="w-2 h-2 rounded-full bg-cyan-500 animate-pulse"></span>
                      Подать предложение
                  </h3>
                  <form @submit.prevent="submitOffer" class="space-y-4">
                      <div class="space-y-1">
                          <label class="text-[10px] uppercase tracking-widest text-gray-500 font-bold">Количество слитков</label>
                          <div class="flex items-center gap-2">
                              <button type="button" tabindex="-1" @click="offerForm.volume = Math.max((offerForm.volume || 1) - 1, 1)"
                                  class="w-14 shrink-0 h-12 flex items-center justify-center rounded-lg bg-gray-100 dark:bg-dark-900 border border-gray-200 dark:border-white/10 text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-white/10 hover:border-gray-300 dark:hover:border-white/20 active:bg-cyan-50 dark:active:bg-cyan-900/40 active:border-cyan-500/30 transition-colors">
                                  <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M20 12H4" /></svg>
                              </button>
                              <input v-model.number="offerForm.volume" type="number" min="1" :max="auction.bar_count"
                                 class="flex-1 min-w-0 h-12 bg-gray-50 dark:bg-dark-900 border border-gray-200 dark:border-white/10 rounded-lg px-3 py-2 appearance-none [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none [-moz-appearance:textfield] text-gray-900 dark:text-white font-mono text-lg text-center focus:border-cyan-500 focus:outline-none focus:ring-1 focus:ring-cyan-500 transition-all"
                                 :class="formErrors.volume ? 'border-red-500/50' : ''" />
                              <button type="button" tabindex="-1" @click="offerForm.volume = Math.min((offerForm.volume || 1) + 1, auction.bar_count || 999)"
                                  class="w-14 shrink-0 h-12 flex items-center justify-center rounded-lg bg-gray-100 dark:bg-dark-900 border border-gray-200 dark:border-white/10 text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-white/10 hover:border-gray-300 dark:hover:border-white/20 active:bg-cyan-50 dark:active:bg-cyan-900/40 active:border-cyan-500/30 transition-colors">
                                  <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" /></svg>
                              </button>
                          </div>
                          <p v-if="formErrors.volume" class="text-red-400 text-xs">{{ formErrors.volume }}</p>
                      </div>
                      <div class="space-y-1">
                          <label class="text-[10px] uppercase tracking-widest text-gray-500 font-bold">Цена за грамм (₽)</label>
                          <div class="flex items-center gap-2">
                              <button type="button" tabindex="-1" @click="offerForm.price = parseFloat(((Number(offerForm.price) || Number(auction.min_price) || 0) - Number(auction.min_step || 1)).toFixed(2)); if (offerForm.price < Number(auction.min_price)) offerForm.price = Number(auction.min_price)"
                                  class="w-14 shrink-0 h-12 flex items-center justify-center rounded-lg bg-gray-100 dark:bg-dark-900 border border-gray-200 dark:border-white/10 text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-white/10 hover:border-gray-300 dark:hover:border-white/20 active:bg-cyan-50 dark:active:bg-cyan-900/40 active:border-cyan-500/30 transition-colors">
                                  <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M20 12H4" /></svg>
                              </button>
                              <input v-model.number="offerForm.price" type="number" step="0.01" min="0.01" :placeholder="auction.min_price ? `от ₽ ${Number(auction.min_price).toLocaleString('ru-RU')}` : '0.00'"
                                 class="flex-1 min-w-0 h-12 bg-gray-50 dark:bg-dark-900 border border-gray-200 dark:border-white/10 rounded-lg px-3 py-2 appearance-none [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none [-moz-appearance:textfield] text-gray-900 dark:text-white font-mono text-lg text-center focus:border-cyan-500 focus:outline-none focus:ring-1 focus:ring-cyan-500 transition-all"
                                 :class="formErrors.price ? 'border-red-500/50' : ''" />
                              <button type="button" tabindex="-1" @click="offerForm.price = parseFloat(((Number(offerForm.price) || Number(auction.min_price) || 0) + Number(auction.min_step || 1)).toFixed(2))"
                                  class="w-14 shrink-0 h-12 flex items-center justify-center rounded-lg bg-gray-100 dark:bg-dark-900 border border-gray-200 dark:border-white/10 text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-white/10 hover:border-gray-300 dark:hover:border-white/20 active:bg-cyan-50 dark:active:bg-cyan-900/40 active:border-cyan-500/30 transition-colors">
                                  <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" /></svg>
                              </button>
                          </div>
                          <p v-if="formErrors.price" class="text-red-400 text-xs">{{ formErrors.price }}</p>
                      </div>
                      <div class="space-y-1">
                          <label class="text-[10px] uppercase tracking-widest text-gray-500 font-bold">Комментарий</label>
                          <textarea v-model="offerForm.comment" rows="2" placeholder="Необязательно" maxlength="200"
                                    class="w-full bg-gray-50 dark:bg-dark-900 border border-gray-200 dark:border-white/10 rounded-lg px-4 py-3 text-gray-900 dark:text-white text-sm focus:border-cyan-500 focus:outline-none focus:ring-1 focus:ring-cyan-500 transition-all resize-none"></textarea>
                          <div class="flex justify-end text-xs text-gray-400 mt-1">
                              {{ offerForm.comment ? offerForm.comment.length : 0 }} / 200
                          </div>
                      </div>
                      <button type="submit" :disabled="isSubmittingOffer"
                              class="w-full py-3 bg-cyan-500 hover:bg-cyan-400 text-black font-bold uppercase tracking-widest rounded-lg transition-all shadow-lg shadow-cyan-500/20 disabled:opacity-50 disabled:cursor-not-allowed">
                          {{ isSubmittingOffer ? 'Отправка...' : 'Отправить предложение' }}
                      </button>
                  </form>
              </div>

              <!-- My Offers List -->
              <div class="md:bg-white md:dark:bg-dark-800/80 md:backdrop-blur-sm md:rounded-2xl border-0 md:border md:border-gray-200 md:dark:border-white/10 md:shadow-sm dark:shadow-none py-6 md:p-6 -mx-2 md:mx-0 px-2 md:px-0">
                  <h3 class="text-lg font-kanit font-bold text-gray-900 dark:text-white mb-5">Мои предложения</h3>
                  <div v-if="myOffers.length === 0" class="text-center py-8 text-gray-500">
                      <p class="text-sm">Вы ещё не отправляли предложений</p>
                  </div>
                  <div v-else class="space-y-3">
                      <div v-for="offer in myOffers" :key="offer.id" class="bg-white dark:bg-dark-900/50 rounded-lg p-4 shadow-sm dark:shadow-none border border-gray-100 dark:border-white/5">
                          <div class="flex justify-between items-center">
                              <div>
                                  <span class="text-sm text-gray-900 dark:text-white font-bold font-mono">{{ Number(offer.volume).toLocaleString() }} слитков</span>
                                  <span class="text-gray-500 mx-2">×</span>
                                  <span class="text-sm text-cyan-400 font-bold font-mono"><span class="font-sans">₽</span>&nbsp;{{ formatPrice(offer.price) }}/г</span>
                              </div>
                              <span class="text-xs text-gray-500 font-mono">{{ formatDate(offer.created_at) }}</span>
                          </div>
                          <p v-if="offer.comment" class="text-xs text-gray-400 mt-2">{{ offer.comment }}</p>
                      </div>
                  </div>
              </div>
          </div>
      </div>

      <!-- ======== ACTIVE TRADING SECTION ======== -->
      <div v-if="auction.status === 'active' || (auction.status === 'gpb_right' && isGpb)" class="grid grid-cols-1 lg:grid-cols-3 gap-6">
          <!-- ===== GPB Priority Purchase Card ===== -->
          <div v-if="isGpb && auction.status === 'gpb_right'" class="bg-white dark:bg-dark-800/80 backdrop-blur-sm rounded-2xl border border-blue-300 dark:border-blue-500/30 p-6 lg:col-span-1 transition-all duration-500 shadow-[0_0_25px_rgba(59,130,246,0.1)]">
              <h3 class="text-lg font-kanit font-bold text-gray-900 dark:text-white mb-1 flex items-center gap-2">
                  <span class="text-xl">🏛</span>
                  Приоритетная покупка
              </h3>
              <p class="text-xs text-gray-500 dark:text-gray-400 mb-5">Выберите количество слитков для выкупа</p>

              <div class="space-y-4">
                  <!-- Bar count input -->
                  <div class="space-y-1">
                      <div class="flex items-center justify-between">
                          <label class="text-[10px] uppercase tracking-widest text-gray-500 font-bold">Количество слитков</label>
                          <span class="text-[10px] text-gray-400 font-mono">макс. {{ gpbMaxBars }}</span>
                      </div>
                      <div class="flex items-center gap-2">
                          <button type="button" tabindex="-1" @click="gpbForm.bar_count = Math.max((gpbForm.bar_count || 1) - 1, 1)"
                              class="w-14 shrink-0 h-12 flex items-center justify-center rounded-lg bg-gray-100 dark:bg-dark-900 border border-gray-200 dark:border-white/10 text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-white/10 hover:border-gray-300 dark:hover:border-white/20 active:bg-blue-50 dark:active:bg-blue-900/40 active:border-blue-500/30 transition-colors">
                              <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M20 12H4" /></svg>
                          </button>
                          <input v-model.number="gpbForm.bar_count" type="number" min="1" :max="gpbMaxBars"
                             class="flex-1 min-w-0 h-12 bg-gray-50 dark:bg-dark-900 border border-gray-200 dark:border-white/10 rounded-lg px-3 py-2 appearance-none [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none [-moz-appearance:textfield] text-gray-900 dark:text-white font-mono text-lg text-center focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 transition-all"
                             :class="formErrors.bar_count ? 'border-red-500/50' : ''" />
                          <button type="button" tabindex="-1" @click="gpbForm.bar_count = Math.min((gpbForm.bar_count || 1) + 1, gpbMaxBars || 999)"
                              class="w-14 shrink-0 h-12 flex items-center justify-center rounded-lg bg-gray-100 dark:bg-dark-900 border border-gray-200 dark:border-white/10 text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-white/10 hover:border-gray-300 dark:hover:border-white/20 active:bg-blue-50 dark:active:bg-blue-900/40 active:border-blue-500/30 transition-colors">
                              <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" /></svg>
                          </button>
                      </div>
                      <p v-if="formErrors.bar_count" class="text-red-400 text-xs">{{ Array.isArray(formErrors.bar_count) ? formErrors.bar_count[0] : formErrors.bar_count }}</p>
                      <!-- Slider -->
                      <input type="range" min="1" :max="gpbMaxBars || 1" v-model.number="gpbForm.bar_count"
                             class="w-full h-1.5 bg-gray-200 dark:bg-white/10 rounded-full appearance-none cursor-pointer accent-blue-500 mt-2" />
                  </div>

                  <!-- Live preview of taken bids -->
                  <div v-if="gpbPreview.taken.length > 0" class="rounded-lg border border-gray-200 dark:border-white/10 overflow-hidden bg-gray-50 dark:bg-dark-900/40">
                      <div class="px-3 py-2 border-b border-gray-200 dark:border-white/10 bg-blue-50/50 dark:bg-blue-500/5">
                          <p class="text-[10px] uppercase tracking-widest text-blue-600 dark:text-blue-400 font-bold">Забираемые ставки</p>
                      </div>
                      <div class="divide-y divide-gray-100 dark:divide-white/5 max-h-[240px] overflow-auto">
                          <div v-for="(item, idx) in gpbPreview.taken" :key="idx" class="px-3 py-2.5 flex items-center justify-between gap-2 text-sm">
                              <div class="flex items-center gap-2 min-w-0">
                                  <span class="text-gray-400 font-mono text-xs w-4 shrink-0">{{ idx + 1 }}</span>
                                  <span class="text-gray-900 dark:text-white font-medium truncate">{{ item.participant_label.replace('Участник', 'Уч.') }}</span>
                              </div>
                              <div class="flex items-center gap-3 shrink-0">
                                  <span class="font-mono text-xs text-blue-600 dark:text-blue-400 font-bold">{{ item.gpbTake }} сл.</span>
                                  <span class="font-mono text-xs text-gray-500">₽ {{ formatPrice(item.gpbPricePerGram) }}/г</span>
                                  <span class="font-mono text-xs text-gray-900 dark:text-white font-bold min-w-[90px] text-right">₽ {{ formatPrice(item.gpbSum) }}</span>
                              </div>
                          </div>
                      </div>
                  </div>

                  <!-- Totals -->
                  <div v-if="gpbPreview.totalBars > 0" class="bg-blue-50 dark:bg-blue-500/10 rounded-lg px-4 py-3 border border-blue-200 dark:border-blue-500/20 space-y-1.5">
                      <div class="flex justify-between items-center">
                          <span class="text-[10px] uppercase tracking-widest text-blue-600/70 dark:text-blue-400/70 font-bold">Слитков</span>
                          <span class="font-mono text-sm font-bold text-blue-700 dark:text-blue-300">{{ gpbPreview.totalBars }}</span>
                      </div>
                      <div class="flex justify-between items-center">
                          <span class="text-[10px] uppercase tracking-widest text-blue-600/70 dark:text-blue-400/70 font-bold">Вес</span>
                          <span class="font-mono text-sm font-bold text-blue-700 dark:text-blue-300">{{ gpbPreview.totalWeight.toFixed(3) }} кг</span>
                      </div>
                      <div class="border-t border-blue-200 dark:border-blue-500/20 pt-1.5 flex justify-between items-center">
                          <span class="text-[10px] uppercase tracking-widest text-blue-600 dark:text-blue-400 font-bold">Итого</span>
                          <span class="font-mono text-lg font-black text-blue-700 dark:text-blue-300"><span class="font-sans text-xs">₽</span>&nbsp;{{ formatPrice(gpbPreview.totalSum) }}</span>
                      </div>
                  </div>

                  <!-- Submit -->
                  <button @click="submitGpbPurchase" :disabled="isSubmittingGpb || gpbPreview.totalBars === 0"
                          class="w-full py-4 bg-blue-600 hover:bg-blue-500 text-white font-bold uppercase tracking-widest rounded-lg transition-all shadow-lg shadow-blue-500/30 text-base disabled:opacity-50 disabled:cursor-not-allowed active:scale-[0.99]">
                      {{ isSubmittingGpb ? 'Обработка...' : '🏛 Забрать слитки' }}
                  </button>
              </div>

          </div>


          <!-- ===== GPB user during active trading: read-only view (no bid form) ===== -->
          <div v-else-if="isGpb && auction.status === 'active'" class="bg-white dark:bg-dark-800/80 backdrop-blur-sm rounded-2xl border border-blue-300 dark:border-blue-500/30 p-6 lg:col-span-1">
              <h3 class="text-lg font-kanit font-bold text-gray-900 dark:text-white mb-2 flex items-center gap-2">
                  <span class="text-xl">👁</span>
                  Наблюдение за торгами
              </h3>
              <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">Как участник ГПБ, вы не можете делать ставки во время основных торгов. Ваше право покупки активируется после их завершения.</p>
              <div class="bg-blue-50 dark:bg-blue-500/10 rounded-lg px-4 py-3 border border-blue-200 dark:border-blue-500/20">
                  <div class="flex items-center gap-2">
                      <span class="w-2 h-2 rounded-full bg-blue-500 animate-pulse"></span>
                      <span class="text-xs text-blue-700 dark:text-blue-300 font-bold uppercase tracking-widest">Ожидание права ГПБ</span>
                  </div>
              </div>
          </div>

          <!-- ===== Regular Bid Form (non-GPB during active) ===== -->
          <div v-else class="bg-white dark:bg-dark-800/80 backdrop-blur-sm rounded-2xl border p-6 lg:col-span-1 transition-all duration-500"
               :class="myStatus === 'winning' ? 'border-green-500/30 shadow-[0_0_25px_rgba(34,197,94,0.15)]' : myStatus === 'losing' ? 'border-red-500/30 shadow-[0_0_25px_rgba(239,68,68,0.1)]' : 'border-gold-500/20'">
              <h3 class="text-lg font-kanit font-bold text-gray-900 dark:text-white mb-5 flex items-center gap-2">
                  <span class="w-2 h-2 rounded-full animate-pulse" :class="myStatus === 'winning' ? 'bg-green-500' : 'bg-gold-500'"></span>
                  Сделать ставку
              </h3>
              <form @submit.prevent="placeBid" class="space-y-4">
                  <div class="space-y-1">
                      <label class="text-[10px] uppercase tracking-widest text-gray-500 font-bold">Количество слитков</label>
                      <div class="flex items-center gap-2">
                          <button type="button" tabindex="-1" @click="bidForm.bar_count = Math.max((bidForm.bar_count || 1) - 1, 1)"
                              class="w-14 shrink-0 h-12 flex items-center justify-center rounded-lg bg-gray-100 dark:bg-dark-900 border border-gray-200 dark:border-white/10 text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-white/10 hover:border-gray-300 dark:hover:border-white/20 active:bg-gold-50 dark:active:bg-gold-900/40 active:border-gold-500/30 transition-colors">
                              <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M20 12H4" /></svg>
                          </button>
                          <input v-model.number="bidForm.bar_count" type="number" min="1" :max="auction.bar_count"
                             class="flex-1 min-w-0 h-12 bg-gray-50 dark:bg-dark-900 border border-gray-200 dark:border-white/10 rounded-lg px-3 py-2 appearance-none [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none [-moz-appearance:textfield] text-gray-900 dark:text-white font-mono text-lg text-center focus:border-gold-500 focus:outline-none focus:ring-1 focus:ring-gold-500 transition-all"
                             :class="formErrors.bar_count ? 'border-red-500/50' : ''" />
                          <button type="button" tabindex="-1" @click="bidForm.bar_count = Math.min((bidForm.bar_count || 1) + 1, auction.bar_count || 999)"
                              class="w-14 shrink-0 h-12 flex items-center justify-center rounded-lg bg-gray-100 dark:bg-dark-900 border border-gray-200 dark:border-white/10 text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-white/10 hover:border-gray-300 dark:hover:border-white/20 active:bg-gold-50 dark:active:bg-gold-900/40 active:border-gold-500/30 transition-colors">
                              <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" /></svg>
                          </button>
                      </div>
                      <p v-if="formErrors.bar_count" class="text-red-400 text-xs">{{ Array.isArray(formErrors.bar_count) ? formErrors.bar_count[0] : formErrors.bar_count }}</p>
                  </div>
                  <div class="space-y-1">
                      <label class="text-[10px] uppercase tracking-widest text-gray-500 font-bold">Цена за грамм (₽)</label>
                      <div class="flex items-center gap-2">
                          <button type="button" tabindex="-1" @click="bidForm.amount = parseFloat(((Number(bidForm.amount) || Number(minBidAmount) || 0) - Number(auction.min_step || 0.01)).toFixed(2)); if (bidForm.amount < Number(minBidAmount)) bidForm.amount = Number(minBidAmount)"
                              class="w-14 shrink-0 h-12 flex items-center justify-center rounded-lg bg-gray-100 dark:bg-dark-900 border border-gray-200 dark:border-white/10 text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-white/10 hover:border-gray-300 dark:hover:border-white/20 active:bg-gold-50 dark:active:bg-gold-900/40 active:border-gold-500/30 transition-colors">
                              <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M20 12H4" /></svg>
                          </button>
                          <input v-model.number="bidForm.amount" type="number" step="0.01" min="0.01" :placeholder="minBidAmount ? `от ₽ ${formatPrice(minBidAmount)}` : '0.00'"
                             class="flex-1 min-w-0 h-12 bg-gray-50 dark:bg-dark-900 border border-gray-200 dark:border-white/10 rounded-lg px-3 py-2 appearance-none [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none [-moz-appearance:textfield] text-gray-900 dark:text-white font-mono text-lg text-center focus:border-gold-500 focus:outline-none focus:ring-1 focus:ring-gold-500 transition-all"
                             :class="formErrors.amount ? 'border-red-500/50' : ''" />
                          <button type="button" tabindex="-1" @click="bidForm.amount = parseFloat(((Number(bidForm.amount) || Number(minBidAmount) || 0) + Number(auction.min_step || 0.01)).toFixed(2))"
                              class="w-14 shrink-0 h-12 flex items-center justify-center rounded-lg bg-gray-100 dark:bg-dark-900 border border-gray-200 dark:border-white/10 text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-white/10 hover:border-gray-300 dark:hover:border-white/20 active:bg-gold-50 dark:active:bg-gold-900/40 active:border-gold-500/30 transition-colors">
                              <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" /></svg>
                          </button>
                      </div>
                      <p v-if="formErrors.amount" class="text-red-400 text-xs">{{ Array.isArray(formErrors.amount) ? formErrors.amount[0] : formErrors.amount }}</p>
                      <div class="flex flex-col gap-0.5 mt-1">
                          <p v-if="minBidAmount" class="text-xs text-gray-500 dark:text-gray-400 font-medium">
                              Мин. цена: <span class="font-mono">₽ {{ formatPrice(minBidAmount) }}/г</span>
                          </p>
                      </div>
                  </div>

                  <!-- Bid total preview -->
                  <div v-if="bidTotalPreview" class="bg-gray-50 dark:bg-dark-900/60 rounded-lg px-4 py-3 border border-gray-100 dark:border-white/5">
                      <p class="text-[10px] uppercase tracking-widest text-gray-500 font-bold mb-1">Сумма ставки</p>
                      <p class="text-xl font-mono font-black text-gold-400">
                          <span class="font-sans text-sm">₽</span>&nbsp;{{ formatPrice(bidTotalPreview) }}
                      </p>
                      <p class="text-[10px] text-gray-500 mt-0.5">
                          {{ bidForm.bar_count }} сл. × {{ auction.bar_weight * 1000 }} г × ₽ {{ formatPrice(bidForm.amount) }}
                      </p>
                  </div>

                  <button type="submit" :disabled="isSubmittingBid"
                          class="w-full py-4 bg-gold-500 hover:bg-gold-400 text-black font-bold uppercase tracking-widest rounded-lg transition-all shadow-lg shadow-gold-500/30 text-base disabled:opacity-50 disabled:cursor-not-allowed active:scale-[0.99]">
                      {{ isSubmittingBid ? 'Отправка...' : '⚡ Подать ставку' }}
                  </button>
              </form>

              <!-- My Bids -->
              <div v-if="myBids.length > 0" class="mt-6 border-t border-gray-200 dark:border-white/5 pt-4">
                  <h4 class="text-xs uppercase tracking-widest text-gray-500 font-bold mb-3">Мои ставки</h4>
                  <div class="space-y-2">
                      <div v-for="bid in myBids" :key="bid.id" class="flex justify-between items-center py-2 px-3 rounded bg-gray-50 dark:bg-dark-900/40 border border-gray-100 dark:border-white/5">
                          <div>
                              <span class="text-sm text-gray-900 dark:text-white font-mono font-bold">{{ bid.bar_count }} сл.</span>
                              <span class="text-gray-500 mx-1">·</span>
                              <span class="text-sm text-gold-400 font-mono font-bold"><span class="font-sans">₽</span>&nbsp;{{ formatPrice(bid.amount) }}</span>
                          </div>
                          <span class="text-[10px] text-gray-500 font-mono">{{ formatDate(bid.created_at) }}</span>
                      </div>
                  </div>
              </div>
          </div>

          <!-- All Bids Table -->
          <div class="bg-white dark:bg-dark-800/80 backdrop-blur-sm rounded-none lg:rounded-2xl border-y lg:border border-gray-200 dark:border-white/10 shadow-sm dark:shadow-none py-6 lg:p-6 lg:col-span-2 -mx-4 lg:mx-0">
              <h3 class="text-lg font-kanit font-bold text-gray-900 dark:text-white mb-4 px-4 lg:px-0">Ход торгов</h3>

              <div v-if="allBids.length === 0" class="text-center py-12 text-gray-500">
                  <p class="text-sm">Ставок пока нет</p>
              </div>
              <div v-else class="overflow-x-auto overflow-y-hidden rounded-none lg:rounded-lg border-y lg:border border-gray-200 dark:border-white/10 bg-gray-50 dark:bg-dark-900/30">
                  <table class="w-full text-left border-collapse relative">
                      <thead class="sticky top-0 bg-gray-50 dark:bg-dark-900 z-10">
                          <tr class="border-b border-gray-200 dark:border-white/10 text-xs text-gray-500 uppercase tracking-widest font-bold">
                              <th class="px-4 py-3 bg-gray-50 dark:bg-dark-900 w-8">#</th>
                              <th class="px-4 py-3 bg-gray-50 dark:bg-dark-900">Участник</th>
                              <th class="px-4 py-3 text-center bg-gray-50 dark:bg-dark-900 w-24">Слитков</th>
                              <th class="px-4 py-3 text-right bg-gray-50 dark:bg-dark-900">Цена/грамм</th>
                              <th class="px-4 py-3 text-right bg-gray-50 dark:bg-dark-900">Цена/слиток</th>
                              <th class="px-4 py-3 text-right bg-gray-50 dark:bg-dark-900">Сумма</th>
                              <th class="px-4 py-3 text-right bg-gray-50 dark:bg-dark-900">Дата</th>
                          </tr>
                      </thead>
                      <tbody>
                          <!-- Winning Section -->
                          <tr v-if="lotSummary.lotBars > 0">
                              <td colspan="2" class="px-4 py-2 bg-emerald-50/50 dark:bg-emerald-500/10 border-b border-emerald-500/20">
                                  <span class="text-xs uppercase tracking-widest text-emerald-600 dark:text-emerald-400 font-bold flex items-center gap-1.5">
                                      <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                      Остаток участникам
                                  </span>
                              </td>
                              <td class="px-4 py-2 text-center bg-emerald-50/50 dark:bg-emerald-500/10 border-b border-emerald-500/20 font-mono text-sm font-bold text-emerald-600 dark:text-emerald-400">{{ lotSummary.lotBars }}</td>
                              <td class="px-4 py-2 bg-emerald-50/50 dark:bg-emerald-500/10 border-b border-emerald-500/20"></td>
                              <td class="px-4 py-2 bg-emerald-50/50 dark:bg-emerald-500/10 border-b border-emerald-500/20"></td>
                              <td class="px-4 py-2 text-right bg-emerald-50/50 dark:bg-emerald-500/10 border-b border-emerald-500/20 font-mono text-sm font-bold text-emerald-600 dark:text-emerald-400">
                                  <span class="font-sans">₽</span>&nbsp;{{ formatPrice(lotSummary.lotTotal) }}
                              </td>
                              <td class="px-4 py-2 bg-emerald-50/50 dark:bg-emerald-500/10 border-b border-emerald-500/20"></td>
                          </tr>
                          <tr v-for="(bid, idx) in winningBids" :key="'w-'+bid.id" 
                              class="border-b border-gray-100 dark:border-white/5 transition-colors"
                              :class="[
                                bid.is_mine ? 'bg-yellow-50/70 hover:bg-yellow-100/70 dark:bg-amber-900/10 dark:hover:bg-amber-900/20' : 'bg-white hover:bg-gray-50 dark:bg-transparent dark:hover:bg-white/5',
                                bid.is_mine ? 'ring-1 ring-inset ring-gold-500/20' : ''
                              ]">
                              <td class="px-4 py-3 text-sm text-gray-500 font-mono">{{ idx + 1 }}</td>
                              <td class="px-4 py-3">
                                  <span class="text-sm font-bold" :class="bid.is_mine ? 'text-gold-400' : 'text-gray-900 dark:text-white'">{{ bid.participant_label }}</span>
                              </td>
                              <td class="px-4 py-3 text-center font-mono text-sm font-bold" :class="bid.partial ? 'text-yellow-500' : 'text-gray-900 dark:text-white'">
                                  {{ bid.fulfilled }}
                                  <span v-if="bid.partial" class="text-[10px] text-yellow-500 ml-1 inline-block">(частич.)</span>
                              </td>
                              <td class="px-4 py-3 text-right font-mono text-sm text-emerald-400 font-bold"><span class="font-sans">₽</span>&nbsp;{{ formatPrice(bid.amount) }}</td>
                              <td class="px-4 py-3 text-right font-mono text-sm text-emerald-400 font-bold"><span class="font-sans">₽</span>&nbsp;{{ formatPrice(Number(bid.amount) * (auction.bar_weight * 1000 || 1)) }}</td>
                              <td class="px-4 py-3 text-right font-mono text-sm text-gray-900 dark:text-white"><span class="font-sans">₽</span>&nbsp;{{ formatPrice((bid.fulfilled || 0) * (auction.bar_weight * 1000 || 1) * Number(bid.amount)) }}</td>
                              <td class="px-4 py-3 text-right font-mono text-xs text-gray-500">{{ formatDate(bid.created_at) }}</td>
                          </tr>

                          <!-- Losing Section -->
                          <tr v-if="losingBids.length > 0">
                              <td colspan="7" class="px-4 py-2 bg-red-500/10 border-b border-red-500/20 border-t border-t-gray-200 dark:border-t-white/10">
                                  <span class="text-[10px] uppercase tracking-widest text-red-400 font-bold flex items-center gap-1.5">
                                      <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                                      Не попали
                                  </span>
                              </td>
                          </tr>
                          <tr v-for="(bid, idx) in losingBids" :key="'l-'+bid.id" 
                              class="border-b border-gray-100 dark:border-white/5 transition-colors"
                              :class="[
                                bid.is_mine ? 'bg-yellow-50/50 hover:bg-yellow-100/50 ring-1 ring-inset ring-amber-500/20 dark:bg-amber-900/10 dark:opacity-80' : 'bg-gray-50 hover:bg-gray-100 dark:bg-transparent dark:hover:bg-white/5 dark:opacity-60'
                              ]">
                              <td class="px-4 py-3 text-sm text-gray-600 font-mono">{{ winningBids.length + idx + 1 }}</td>
                              <td class="px-4 py-3">
                                  <span class="text-sm font-bold" :class="bid.is_mine ? 'text-gold-400/70' : 'text-gray-400'">{{ bid.participant_label }}</span>
                              </td>
                              <td class="px-4 py-3 text-center font-mono text-sm text-gray-400">{{ bid.bar_count }}</td>
                              <td class="px-4 py-3 text-right font-mono text-sm text-gray-500"><span class="font-sans">₽</span>&nbsp;{{ formatPrice(bid.amount) }}</td>
                              <td class="px-4 py-3 text-right font-mono text-sm text-gray-500"><span class="font-sans">₽</span>&nbsp;{{ formatPrice(Number(bid.amount) * (auction.bar_weight * 1000 || 1)) }}</td>
                              <td class="px-4 py-3 text-right font-mono text-sm text-gray-600">—</td>
                              <td class="px-4 py-3 text-right font-mono text-xs text-gray-600">{{ formatDate(bid.created_at) }}</td>
                          </tr>
                      </tbody>
                  </table>
              </div>
          </div>
      </div>

      <!-- ======== GPB / COMMISSION / NON-ACTIVE SECTION ======== -->
      <div v-if="(isGpb && auction.status === 'active') || (['gpb_right','commission','completed','paused','cancelled'].includes(auction.status) && !(isGpb && auction.status === 'gpb_right'))" class="space-y-6">
          <div class="grid grid-cols-1 gap-6">
          <!-- My Status Card (not for GPB participants) -->
          <div v-if="!isGpb" class="h-full flex flex-col justify-center bg-white dark:bg-dark-800/80 backdrop-blur-sm rounded-2xl border p-6 transition-all duration-500"
               :class="myStatus === 'winning' ? 'border-green-500/30 shadow-[0_0_25px_rgba(34,197,94,0.15)]' : myStatus === 'losing' ? 'border-red-500/30' : 'border-gray-200 dark:border-white/10'">
              <div class="flex flex-col xl:flex-row items-stretch gap-4 h-full">
                  <div class="flex-1 w-full relative">
                      <div v-if="myStatus === 'winning'" class="bg-green-500/10 border border-green-500/30 rounded-lg p-3 xl:p-4 flex items-center gap-4 h-full">
                          <div class="text-2xl bg-green-500/20 w-10 h-10 flex items-center justify-center rounded-full shrink-0">🏆</div>
                          <div>
                              <p class="text-[11px] xl:text-sm font-bold text-green-500 uppercase tracking-widest leading-none mb-1">Вы выигрываете</p>
                              <p class="text-[11px] xl:text-sm text-gray-500 dark:text-gray-400 font-mono">{{ myWinningBars }} слитков на сумму <span class="font-sans">₽</span>&nbsp;{{ formatPrice(myWinningSum) }}</p>
                          </div>
                      </div>
                      <div v-else-if="myStatus === 'partial'" class="relative overflow-hidden bg-gradient-to-br from-yellow-100 to-orange-100 dark:from-yellow-900/40 dark:to-orange-900/40 border border-yellow-400 dark:border-yellow-500/50 rounded-xl p-3 xl:p-4 flex items-center gap-4 h-full shadow-[0_4px_20px_-4px_rgba(250,204,21,0.2)] group">
                          <div class="absolute inset-0 bg-[url('https://www.transparenttextures.com/patterns/cubes.png')] opacity-[0.03] dark:opacity-10 mix-blend-overlay pointer-events-none"></div>
                          <div class="absolute -right-4 -top-4 w-24 h-24 bg-yellow-400/20 dark:bg-yellow-400/10 blur-2xl rounded-full group-hover:bg-yellow-400/30 transition-all duration-500"></div>
                          <div class="relative z-10 text-2xl bg-gradient-to-br from-yellow-400 to-orange-400 text-white w-10 h-10 flex items-center justify-center rounded-xl shrink-0 shadow-sm border border-white/20">⚡</div>
                          <div class="relative z-10">
                              <p class="text-[11px] xl:text-sm font-black bg-gradient-to-r from-yellow-600 to-orange-500 dark:from-yellow-400 dark:to-orange-400 text-transparent bg-clip-text uppercase tracking-widest leading-none mb-1">Частичная победа</p>
                              <p class="text-[11px] xl:text-sm text-yellow-800/70 dark:text-yellow-200/60 font-medium font-mono"><span class="font-bold text-yellow-700 dark:text-yellow-400">{{ myWinningBars }}</span> слитков на сумму <span class="font-sans">₽</span>&nbsp;{{ formatPrice(myWinningSum) }}</p>
                          </div>
                      </div>

                      <div v-else-if="myStatus === 'losing'" class="bg-red-500/10 border border-red-500/30 rounded-lg p-3 xl:p-4 flex items-center gap-4 h-full">
                          <div class="text-xl bg-red-500/20 w-10 h-10 flex items-center justify-center rounded-full shrink-0">📉</div>
                          <div>
                              <p class="text-[11px] xl:text-sm font-bold text-red-500 uppercase tracking-widest leading-none mb-1">Вы перебиты</p>
                              <p class="text-[11px] xl:text-sm text-gray-500 dark:text-gray-400">Ставки не вошли в лот</p>
                          </div>
                      </div>
                      <div v-else class="relative overflow-hidden bg-gradient-to-br from-gray-50/50 to-slate-100/50 dark:from-dark-900/80 dark:to-slate-900/60 backdrop-blur-md border border-gray-200/80 dark:border-white/10 rounded-xl p-3 xl:p-4 flex items-center gap-4 h-full group transition-all duration-500 hover:border-gray-300/80 dark:hover:border-white/20 hover:shadow-[0_8px_30px_rgb(0,0,0,0.04)] dark:hover:shadow-[0_8px_30px_rgba(0,0,0,0.2)]">
                          <!-- Decorative background blobs -->
                          <div class="absolute -right-6 -top-6 w-32 h-32 bg-indigo-500/5 dark:bg-indigo-500/10 blur-3xl rounded-full group-hover:bg-indigo-500/10 dark:group-hover:bg-indigo-500/20 transition-all duration-500"></div>
                          <div class="absolute -left-6 -bottom-6 w-24 h-24 bg-sky-500/5 dark:bg-sky-500/10 blur-2xl rounded-full group-hover:bg-sky-500/10 dark:group-hover:bg-sky-500/20 transition-all duration-500"></div>
                          
                          <!-- Glamorous Icon Container -->
                          <div class="relative w-11 h-11 flex items-center justify-center rounded-xl shrink-0 group-hover:scale-105 transition-transform duration-500">
                             <!-- Glowing shadow behind the icon -->
                             <div class="absolute inset-0 bg-gradient-to-tr from-indigo-500/20 to-sky-400/20 dark:from-indigo-400/30 dark:to-sky-400/30 blur-xl rounded-xl opacity-0 group-hover:opacity-100 transition-opacity duration-500"></div>
                             
                             <!-- Glassmorphism icon surface -->
                             <div class="absolute inset-0 bg-gradient-to-br from-white/90 to-white/50 dark:from-gray-700/80 dark:to-gray-800/60 backdrop-blur-md border border-white/80 dark:border-white/10 rounded-xl shadow-[0_4px_15px_rgba(0,0,0,0.06)] dark:shadow-[0_4px_15px_rgba(0,0,0,0.3)]"></div>
                             
                             <!-- Subtle inner highlight -->
                             <div class="absolute inset-0 rounded-xl bg-gradient-to-b from-white/60 to-transparent dark:from-white/5 opacity-50"></div>
                             
                             <!-- The Icon (Eye) -->
                             <svg class="relative z-10 w-5 h-5 text-slate-500 dark:text-slate-300 drop-shadow-sm group-hover:text-indigo-500 dark:group-hover:text-indigo-400 transition-colors duration-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                             </svg>
                          </div>
                          
                          <div class="relative z-10">
                              <p class="text-[11px] xl:text-sm font-bold bg-gradient-to-r from-slate-600 to-slate-400 dark:from-slate-300 dark:to-slate-400 text-transparent bg-clip-text uppercase tracking-widest leading-none mb-1 group-hover:from-indigo-600 group-hover:to-sky-500 dark:group-hover:from-indigo-400 dark:group-hover:to-sky-300 transition-all duration-500">Наблюдатель</p>
                              <p class="text-[11px] xl:text-sm text-slate-500/80 dark:text-slate-400/80 font-medium">Вы не участвовали в торгах</p>
                          </div>
                      </div>
                      
                      <!-- Absolute divider on extra large screens -->
                      <div v-if="auction.status === 'gpb_right'" class="hidden xl:block absolute right-0 top-1/2 -translate-y-1/2 w-px h-10 bg-gray-200 dark:bg-white/10 -mr-2"></div>
                  </div>

                  <!-- GPB info block -->
                  <div v-if="auction.status === 'gpb_right'" class="flex-1 w-full bg-blue-50/50 dark:bg-blue-500/10 border border-blue-200 dark:border-blue-500/20 rounded-xl p-4 flex flex-col justify-center h-full shadow-[0_2px_15px_-3px_rgba(59,130,246,0.1)]">
                      <div class="flex items-center gap-2 mb-1.5">
                          <span class="text-base xl:text-lg">🏛</span>
                          <span class="text-[11px] xl:text-sm font-black text-blue-600 dark:text-blue-400 uppercase tracking-wider">Право ГПБ</span>
                      </div>
                      <p class="text-[10px] xl:text-xs text-blue-800/70 dark:text-blue-200/60 leading-tight">ГПБ рассматривает результаты торгов и может реализовать своё право приоритетной покупки.</p>
                  </div>
              </div>

          </div>
          </div>

          <!-- All Bids for completed/GPB (Hidden for GPB during active phase) -->
          <div v-show="!(isGpb && auction.status === 'active')" class="bg-white dark:bg-dark-800/80 backdrop-blur-sm rounded-none md:rounded-2xl border-y md:border border-gray-200 dark:border-white/10 shadow-sm dark:shadow-none py-6 md:p-6 -mx-4 md:mx-0 lg:col-span-2">
              <h3 class="text-lg font-kanit font-bold text-gray-900 dark:text-white mb-4 px-4 md:px-0">Результаты торгов</h3>

              <!-- GPB Purchase Report (matching admin format) -->
              <div v-if="gpbReport" class="mb-4 overflow-auto rounded-none md:rounded-lg border-y md:border-x-0 md:border-y-0 md:border border-blue-300 dark:border-blue-500/20 bg-gray-50 dark:bg-dark-900/30">
                  <table class="w-full text-left border-collapse">
                      <thead>
                          <tr class="border-b border-gray-200 dark:border-white/10 text-xs text-gray-500 uppercase tracking-widest font-bold">
                              <th class="px-4 py-3 bg-gray-50 dark:bg-dark-900 w-8">#</th>
                              <th class="px-4 py-3 bg-gray-50 dark:bg-dark-900">Участник</th>
                              <th class="px-4 py-3 text-center bg-gray-50 dark:bg-dark-900 w-24">Слитков</th>
                              <th class="px-4 py-3 text-right bg-gray-50 dark:bg-dark-900">Цена/г</th>
                              <th class="px-4 py-3 text-right bg-gray-50 dark:bg-dark-900">Цена/слиток</th>
                              <th class="px-4 py-3 text-right bg-gray-50 dark:bg-dark-900">Сумма</th>
                              <th class="px-4 py-3 text-right bg-gray-50 dark:bg-dark-900">Дата</th>
                          </tr>
                      </thead>
                      <tbody>
                          <!-- GPB Purchase Header -->
                          <tr>
                              <td colspan="2" class="px-4 py-2 bg-blue-50 dark:bg-blue-500/10 border-b border-blue-200 dark:border-blue-500/20">
                                  <span class="text-xs uppercase tracking-widest text-blue-600 dark:text-blue-400 font-bold flex items-center gap-1.5">
                                      🏛 Выкуп ГПБ ({{ gpbReport.total_bars }} слитков)
                                  </span>
                              </td>
                              <td class="px-4 py-2 text-center bg-blue-50 dark:bg-blue-500/10 border-b border-blue-200 dark:border-blue-500/20 font-mono text-sm font-bold text-blue-600 dark:text-blue-400">{{ gpbReport.total_bars }}</td>
                              <td class="px-4 py-2 bg-blue-50 dark:bg-blue-500/10 border-b border-blue-200 dark:border-blue-500/20"></td>
                              <td class="px-4 py-2 bg-blue-50 dark:bg-blue-500/10 border-b border-blue-200 dark:border-blue-500/20"></td>
                              <td class="px-4 py-2 text-right bg-blue-50 dark:bg-blue-500/10 border-b border-blue-200 dark:border-blue-500/20 font-mono text-sm font-bold text-blue-600 dark:text-blue-400">
                                  <span class="font-sans">₽</span>&nbsp;{{ formatPrice(gpbReport.total_sum) }}
                              </td>
                              <td class="px-4 py-2 bg-blue-50 dark:bg-blue-500/10 border-b border-blue-200 dark:border-blue-500/20"></td>
                          </tr>
                          <!-- GPB Buyer Row -->
                          <tr class="border-b border-blue-100 dark:border-blue-500/10 bg-blue-50/30 dark:bg-blue-500/5">
                              <td class="px-4 py-3 text-sm text-gray-500 font-mono">1</td>
                              <td class="px-4 py-3">
                                  <span class="text-sm font-bold text-blue-600 dark:text-blue-400">{{ gpbReport.gpb_user_name }}</span>
                                  <span class="text-[10px] text-blue-500 bg-blue-100 dark:bg-blue-500/20 px-1.5 py-0.5 rounded ml-1.5 font-bold uppercase">ГПБ</span>
                              </td>
                              <td class="px-4 py-3 text-center font-mono text-sm font-bold text-blue-600 dark:text-blue-400">{{ gpbReport.total_bars }}</td>
                              <td class="px-4 py-3"></td>
                              <td class="px-4 py-3"></td>
                              <td class="px-4 py-3 text-right font-mono text-sm font-bold text-blue-600 dark:text-blue-400"><span class="font-sans">₽</span>&nbsp;{{ formatPrice(gpbReport.total_sum) }}</td>
                              <td class="px-4 py-3 text-right font-mono text-xs text-gray-500">{{ formatDate(gpbReport.created_at) }}</td>
                          </tr>
                          <!-- Taken Bids Details (indented, from whom) -->
                          <tr v-for="(item, idx) in gpbReport.taken_bids" :key="'gpb-'+idx" class="border-b border-gray-100 dark:border-white/5 text-gray-500 dark:text-gray-500">
                              <td class="px-4 py-2 font-mono text-xs"></td>
                              <td class="px-4 py-2 text-xs pl-8">← {{ item.participant_label }}</td>
                              <td class="px-4 py-2 text-center font-mono text-xs">{{ item.bar_count }}</td>
                              <td class="px-4 py-2 text-right font-mono text-xs"><span class="font-sans">₽</span>&nbsp;{{ formatPrice(item.price_per_gram) }}</td>
                              <td class="px-4 py-2 text-right font-mono text-xs"><span class="font-sans">₽</span>&nbsp;{{ formatPrice(item.price_per_bar) }}</td>
                              <td class="px-4 py-2 text-right font-mono text-xs"><span class="font-sans">₽</span>&nbsp;{{ formatPrice(item.sum) }}</td>
                              <td class="px-4 py-2 text-right font-mono text-xs">{{ formatDate(item.created_at) }}</td>
                          </tr>

                          <!-- Remaining for Participants Header -->
                          <tr v-if="lotSummary.lotBars > 0">
                              <td colspan="2" class="px-4 py-2 bg-emerald-50/50 dark:bg-emerald-500/10 border-b border-emerald-500/20">
                                  <span class="text-xs uppercase tracking-widest text-emerald-600 dark:text-emerald-400 font-bold flex items-center gap-1.5">
                                      <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                      Остаток участникам ({{ lotSummary.lotBars }} слитков)
                                  </span>
                              </td>
                              <td class="px-4 py-2 text-center bg-emerald-50/50 dark:bg-emerald-500/10 border-b border-emerald-500/20 font-mono text-sm font-bold text-emerald-600 dark:text-emerald-400">{{ lotSummary.lotBars }}</td>
                              <td class="px-4 py-2 bg-emerald-50/50 dark:bg-emerald-500/10 border-b border-emerald-500/20"></td>
                              <td class="px-4 py-2 bg-emerald-50/50 dark:bg-emerald-500/10 border-b border-emerald-500/20"></td>
                              <td class="px-4 py-2 text-right bg-emerald-50/50 dark:bg-emerald-500/10 border-b border-emerald-500/20 font-mono text-sm font-bold text-emerald-600 dark:text-emerald-400">
                                  <span class="font-sans">₽</span>&nbsp;{{ formatPrice(lotSummary.lotTotal) }}
                              </td>
                              <td class="px-4 py-2 bg-emerald-50/50 dark:bg-emerald-500/10 border-b border-emerald-500/20"></td>
                          </tr>
                          <tr v-for="(bid, idx) in winningBids" :key="'w-'+bid.id"
                              class="border-b border-gray-100 dark:border-white/5 transition-colors"
                              :class="[
                                bid.is_mine ? 'bg-yellow-50/70 dark:bg-amber-900/10' : 'bg-white dark:bg-transparent',
                                bid.is_mine ? 'ring-1 ring-inset ring-gold-500/20' : ''
                              ]">
                              <td class="px-4 py-3 text-sm text-gray-500 font-mono">{{ idx + 1 }}</td>
                              <td class="px-4 py-3"><span class="text-sm font-bold" :class="bid.is_mine ? 'text-gold-400' : 'text-gray-900 dark:text-white'">{{ bid.participant_label }}</span></td>
                              <td class="px-4 py-3 text-center font-mono text-sm font-bold" :class="bid.partial ? 'text-yellow-500' : 'text-gray-900 dark:text-white'">
                                  {{ bid.fulfilled }}
                                  <span v-if="bid.partial" class="text-[10px] text-yellow-500 ml-1 inline-block">(частич.)</span>
                              </td>
                              <td class="px-4 py-3 text-right font-mono text-sm text-emerald-400 font-bold"><span class="font-sans">₽</span>&nbsp;{{ formatPrice(bid.amount) }}</td>
                              <td class="px-4 py-3 text-right font-mono text-sm text-emerald-400 font-bold"><span class="font-sans">₽</span>&nbsp;{{ formatPrice(Number(bid.amount) * (auction.bar_weight * 1000 || 1)) }}</td>
                              <td class="px-4 py-3 text-right font-mono text-sm text-gray-900 dark:text-white"><span class="font-sans">₽</span>&nbsp;{{ formatPrice((bid.fulfilled || 0) * (auction.bar_weight * 1000 || 1) * Number(bid.amount)) }}</td>
                              <td class="px-4 py-3 text-right font-mono text-xs text-gray-500">{{ formatDate(bid.created_at) }}</td>
                          </tr>

                          <!-- Losing Section -->
                          <tr v-if="losingBids.length > 0">
                              <td colspan="2" class="px-4 py-2 bg-red-50/80 dark:bg-red-500/10 border-b border-red-500/20 border-t border-t-gray-100 dark:border-t-white/10">
                                  <span class="text-xs uppercase tracking-widest text-red-500 dark:text-red-400 font-bold flex items-center gap-1.5">
                                      <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                      Не попали ({{ losingBids.length }})
                                  </span>
                              </td>
                              <td class="px-4 py-2 text-center bg-red-50/80 dark:bg-red-500/10 border-b border-red-500/20 border-t border-t-gray-100 dark:border-t-white/10 font-mono text-sm font-bold text-red-500 dark:text-red-400">{{ losingBids.reduce((sum, b) => sum + b.bar_count, 0) }}</td>
                              <td class="px-4 py-2 bg-red-50/80 dark:bg-red-500/10 border-b border-red-500/20 border-t border-t-gray-100 dark:border-t-white/10"></td>
                              <td class="px-4 py-2 bg-red-50/80 dark:bg-red-500/10 border-b border-red-500/20 border-t border-t-gray-100 dark:border-t-white/10"></td>
                              <td class="px-4 py-2 bg-red-50/80 dark:bg-red-500/10 border-b border-red-500/20 border-t border-t-gray-100 dark:border-t-white/10"></td>
                              <td class="px-4 py-2 bg-red-50/80 dark:bg-red-500/10 border-b border-red-500/20 border-t border-t-gray-100 dark:border-t-white/10"></td>
                          </tr>
                          <tr v-for="(bid, idx) in losingBids" :key="'gl-'+bid.id"
                              class="border-b border-gray-100 dark:border-white/5"
                              :class="bid.is_mine ? 'bg-yellow-50/50 ring-1 ring-inset ring-amber-500/20 dark:bg-amber-900/10 dark:opacity-80' : 'bg-gray-50 dark:bg-transparent dark:opacity-60'">
                              <td class="px-4 py-3 text-sm text-gray-600 font-mono">{{ winningBids.length + idx + 1 }}</td>
                              <td class="px-4 py-3">
                                  <span class="text-sm font-bold" :class="bid.is_mine ? 'text-gold-400/70' : 'text-gray-400'">{{ bid.participant_label }}</span>
                                  <span v-if="bid.lost_to_gpb" class="text-[10px] text-blue-400 bg-blue-100 dark:bg-blue-500/20 px-1 py-0.5 rounded ml-1 font-bold">ГПБ</span>
                              </td>
                              <td class="px-4 py-3 text-center font-mono text-sm text-gray-400">{{ bid.bar_count }}</td>
                              <td class="px-4 py-3 text-right font-mono text-sm text-gray-500"><span class="font-sans">₽</span>&nbsp;{{ formatPrice(bid.amount) }}</td>
                              <td class="px-4 py-3 text-right font-mono text-sm text-gray-500"><span class="font-sans">₽</span>&nbsp;{{ formatPrice(Number(bid.amount) * (auction.bar_weight * 1000 || 1)) }}</td>
                              <td class="px-4 py-3 text-right font-mono text-sm text-gray-600">—</td>
                              <td class="px-4 py-3 text-right font-mono text-xs text-gray-600">{{ formatDate(bid.created_at) }}</td>
                          </tr>
                      </tbody>
                  </table>
              </div>

              <!-- Regular results table (when no GPB) -->
              <div v-if="!gpbReport && allBids.length === 0" class="text-center py-12 text-gray-500 px-4">
                  <p class="text-sm">Ставок нет</p>
              </div>
              <div v-if="!gpbReport && allBids.length > 0" class="overflow-x-auto overflow-y-hidden rounded-none md:rounded-lg border-y md:border-x-0 md:border-y-0 md:border border-gray-200 dark:border-white/10 bg-gray-50 dark:bg-dark-900/30">
                  <table class="w-full text-left border-collapse relative">
                      <thead class="sticky top-0 bg-gray-50 dark:bg-dark-900 z-10">
                          <tr class="border-b border-gray-200 dark:border-white/10 text-xs text-gray-500 uppercase tracking-widest font-bold">
                              <th class="px-4 py-3 bg-gray-50 dark:bg-dark-900 w-8">#</th>
                              <th class="px-4 py-3 bg-gray-50 dark:bg-dark-900">Участник</th>
                              <th class="px-4 py-3 text-center bg-gray-50 dark:bg-dark-900 w-24">Слитков</th>
                              <th class="px-4 py-3 text-right bg-gray-50 dark:bg-dark-900">Цена/грамм</th>
                              <th class="px-4 py-3 text-right bg-gray-50 dark:bg-dark-900">Цена/слиток</th>
                              <th class="px-4 py-3 text-right bg-gray-50 dark:bg-dark-900">Сумма</th>
                              <th class="px-4 py-3 text-right bg-gray-50 dark:bg-dark-900">Дата</th>
                          </tr>
                      </thead>
                      <tbody>
                          <tr v-if="lotSummary.lotBars > 0">
                              <td colspan="2" class="px-4 py-2 bg-emerald-50/50 dark:bg-emerald-500/10 border-b border-emerald-500/20">
                                  <span class="text-xs uppercase tracking-widest text-emerald-600 dark:text-emerald-400 font-bold flex items-center gap-1.5">
                                      <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                      Остаток участникам
                                  </span>
                              </td>
                              <td class="px-4 py-2 text-center bg-emerald-50/50 dark:bg-emerald-500/10 border-b border-emerald-500/20 font-mono text-sm font-bold text-emerald-600 dark:text-emerald-400">{{ lotSummary.lotBars }}</td>
                              <td class="px-4 py-2 bg-emerald-50/50 dark:bg-emerald-500/10 border-b border-emerald-500/20"></td>
                              <td class="px-4 py-2 bg-emerald-50/50 dark:bg-emerald-500/10 border-b border-emerald-500/20"></td>
                              <td class="px-4 py-2 text-right bg-emerald-50/50 dark:bg-emerald-500/10 border-b border-emerald-500/20 font-mono text-sm font-bold text-emerald-600 dark:text-emerald-400">
                                  <span class="font-sans">₽</span>&nbsp;{{ formatPrice(lotSummary.lotTotal) }}
                              </td>
                              <td class="px-4 py-2 bg-emerald-50/50 dark:bg-emerald-500/10 border-b border-emerald-500/20"></td>
                          </tr>
                          <tr v-for="(bid, idx) in winningBids" :key="'w-'+bid.id"
                              class="border-b border-gray-100 dark:border-white/5 transition-colors"
                              :class="[
                                bid.is_mine ? 'bg-yellow-50/70 dark:bg-amber-900/10' : 'bg-white dark:bg-transparent',
                                bid.is_mine ? 'ring-1 ring-inset ring-gold-500/20' : ''
                              ]">
                              <td class="px-4 py-3 text-sm text-gray-500 font-mono">{{ idx + 1 }}</td>
                              <td class="px-4 py-3"><span class="text-sm font-bold" :class="bid.is_mine ? 'text-gold-400' : 'text-gray-900 dark:text-white'">{{ bid.participant_label }}</span></td>
                              <td class="px-4 py-3 text-center font-mono text-sm font-bold" :class="bid.partial ? 'text-yellow-500' : 'text-gray-900 dark:text-white'">
                                  {{ bid.fulfilled }}
                                  <span v-if="bid.partial" class="text-[10px] text-yellow-500 ml-1 inline-block">(частич.)</span>
                              </td>
                              <td class="px-4 py-3 text-right font-mono text-sm text-emerald-400 font-bold"><span class="font-sans">₽</span>&nbsp;{{ formatPrice(bid.amount) }}</td>
                              <td class="px-4 py-3 text-right font-mono text-sm text-emerald-400 font-bold"><span class="font-sans">₽</span>&nbsp;{{ formatPrice(Number(bid.amount) * (auction.bar_weight * 1000 || 1)) }}</td>
                              <td class="px-4 py-3 text-right font-mono text-sm text-gray-900 dark:text-white"><span class="font-sans">₽</span>&nbsp;{{ formatPrice((bid.fulfilled || 0) * (auction.bar_weight * 1000 || 1) * Number(bid.amount)) }}</td>
                              <td class="px-4 py-3 text-right font-mono text-xs text-gray-500">{{ formatDate(bid.created_at) }}</td>
                          </tr>
                          <tr v-if="losingBids.length > 0">
                              <td colspan="2" class="px-4 py-2 bg-red-50/80 dark:bg-red-500/10 border-b border-red-500/20 border-t border-t-gray-100 dark:border-t-white/10">
                                  <span class="text-xs uppercase tracking-widest text-red-500 dark:text-red-400 font-bold flex items-center gap-1.5">
                                      <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                      Не попали
                                  </span>
                              </td>
                              <td class="px-4 py-2 text-center bg-red-50/80 dark:bg-red-500/10 border-b border-red-500/20 border-t border-t-gray-100 dark:border-t-white/10 font-mono text-sm font-bold text-red-500 dark:text-red-400">{{ losingBids.reduce((sum, b) => sum + b.bar_count, 0) }}</td>
                              <td class="px-4 py-2 bg-red-50/80 dark:bg-red-500/10 border-b border-red-500/20 border-t border-t-gray-100 dark:border-t-white/10"></td>
                              <td class="px-4 py-2 bg-red-50/80 dark:bg-red-500/10 border-b border-red-500/20 border-t border-t-gray-100 dark:border-t-white/10"></td>
                              <td class="px-4 py-2 text-right bg-red-50/80 dark:bg-red-500/10 border-b border-red-500/20 border-t border-t-gray-100 dark:border-t-white/10 font-mono text-sm font-bold text-red-500 dark:text-red-400">
                                  
                              </td>
                              <td class="px-4 py-2 bg-red-50/80 dark:bg-red-500/10 border-b border-red-500/20 border-t border-t-gray-100 dark:border-t-white/10"></td>
                          </tr>
                          <tr v-for="(bid, idx) in losingBids" :key="'l-'+bid.id" 
                              class="border-b border-gray-100 dark:border-white/5"
                              :class="bid.is_mine ? 'bg-yellow-50/50 ring-1 ring-inset ring-amber-500/20 dark:bg-amber-900/10 dark:opacity-80' : 'bg-gray-50 dark:bg-transparent dark:opacity-60'">
                              <td class="px-4 py-3 text-sm text-gray-600 font-mono">{{ winningBids.length + idx + 1 }}</td>
                              <td class="px-4 py-3"><span class="text-sm font-bold" :class="bid.is_mine ? 'text-gold-400/70' : 'text-gray-400'">{{ bid.participant_label }}</span></td>
                              <td class="px-4 py-3 text-center font-mono text-sm text-gray-400">{{ bid.bar_count }}</td>
                              <td class="px-4 py-3 text-right font-mono text-sm text-gray-500"><span class="font-sans">₽</span>&nbsp;{{ formatPrice(bid.amount) }}</td>
                              <td class="px-4 py-3 text-right font-mono text-sm text-gray-500"><span class="font-sans">₽</span>&nbsp;{{ formatPrice(Number(bid.amount) * (auction.bar_weight * 1000 || 1)) }}</td>
                              <td class="px-4 py-3 text-right font-mono text-sm text-gray-600">—</td>
                              <td class="px-4 py-3 text-right font-mono text-xs text-gray-600">{{ formatDate(bid.created_at) }}</td>
                          </tr>
                      </tbody>
                  </table>
              </div>
          </div>
      </div>

      <!-- My Offers for non-collecting statuses -->
      <div v-if="auction.status === 'scheduled' && myOffers.length > 0" class="md:bg-white md:dark:bg-dark-800/80 md:backdrop-blur-sm md:rounded-2xl border-0 md:border md:border-gray-200 md:dark:border-white/10 md:shadow-sm dark:shadow-none py-6 md:p-6 -mx-2 md:mx-0 px-2 md:px-0 mt-6 md:mt-0">
          <h3 class="text-lg font-kanit font-bold text-gray-900 dark:text-white mb-4">Мои предложения</h3>
          <div class="space-y-2">
              <div v-for="offer in myOffers" :key="offer.id" class="flex justify-between items-center py-3 px-4 rounded-lg bg-white dark:bg-dark-900/50 shadow-sm dark:shadow-none border border-gray-100 dark:border-white/5">
                  <div>
                      <span class="text-sm text-gray-900 dark:text-white font-mono font-bold">{{ Number(offer.volume).toLocaleString() }} сл.</span>
                      <span class="text-gray-500 mx-2">×</span>
                      <span class="text-sm text-cyan-400 font-mono font-bold"><span class="font-sans">₽</span>&nbsp;{{ formatPrice(offer.price) }}/г</span>
                  </div>
                  <span class="text-xs text-gray-500 font-mono">{{ formatDate(offer.created_at) }}</span>
              </div>
          </div>
      </div>
    </template>
  </div>
</template>
