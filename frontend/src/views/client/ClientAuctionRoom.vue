<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import axios from 'axios'
import echo from '@/echo.js'
import { useConnectionStatus } from '@/composables/useConnectionStatus.js'

const { isConnected, statusText } = useConnectionStatus()

const route = useRoute()
const router = useRouter()

const auctionId = route.params.id
const auction = ref(null)
const myOffers = ref([])
const myBids = ref([])
const allBids = ref([])
const myStatus = ref('none')
const myWinningBars = ref(0)
const highestBid = ref(null)
const isLoading = ref(true)
const error = ref(null)
const previousBidsCount = ref(0)
const newBidsFlash = ref(false)

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

const statusOptions = {
    draft: { label: 'Черновик', color: 'bg-gray-500/20 text-gray-400 border-gray-500/30', icon: '○' },
    collecting_offers: { label: 'Сбор предложений', color: 'bg-cyan-500/20 text-cyan-400 border-cyan-500/30', icon: '📋' },
    scheduled: { label: 'Запланирован', color: 'bg-blue-500/20 text-blue-400 border-blue-500/30', icon: '📅' },
    active: { label: 'Активный', color: 'bg-green-500/20 text-green-400 border-green-500/30', icon: '🔴' },
    gpb_right: { label: 'Право ГПБ', color: 'bg-purple-500/20 text-purple-400 border-purple-500/30', icon: '🏛' },
    commission: { label: 'Работа комиссии', color: 'bg-yellow-500/20 text-yellow-400 border-yellow-500/30', icon: '⚖' },
    completed: { label: 'Завершён', color: 'bg-emerald-500/20 text-emerald-400 border-emerald-500/30', icon: '✅' },
    paused: { label: 'Приостановлен', color: 'bg-orange-500/20 text-orange-400 border-orange-500/30', icon: '⏸' },
    cancelled: { label: 'Отменён', color: 'bg-red-500/20 text-red-400 border-red-500/30', icon: '❌' },
}

const getStatusInfo = (status) => statusOptions[status] || statusOptions.draft

const fetchAuction = async () => {
    try {
        const response = await axios.get(`/api/client/auctions/${auctionId}`)
        const oldBidsCount = allBids.value.length
        auction.value = response.data.auction
        myOffers.value = response.data.my_offers || []
        myBids.value = response.data.my_bids || []
        allBids.value = response.data.all_bids || []
        myStatus.value = response.data.my_status || 'none'
        myWinningBars.value = response.data.my_winning_bars || 0
        highestBid.value = response.data.highest_bid || null

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

// Trading countdown (end_at)
const countdownSeconds = computed(() => {
    if (!auction.value) return -1
    const endRaw = auction.value.end_at
    if (!endRaw) return -1
    const endMs = new Date(endRaw).getTime()
    const diff = Math.floor((endMs - timerNow.value) / 1000)
    return diff > 0 ? diff : 0
})

const countdownFormatted = computed(() => {
    const s = countdownSeconds.value
    if (s < 0) return null
    const hours = Math.floor(s / 3600)
    const mins = Math.floor((s % 3600) / 60)
    const secs = s % 60
    if (hours > 0) return `${hours}ч ${String(mins).padStart(2, '0')}м ${String(secs).padStart(2, '0')}с`
    return `${String(mins).padStart(2, '0')}:${String(secs).padStart(2, '0')}`
})

// GPB countdown (gpb_started_at + gpb_minutes)
const gpbCountdownSeconds = computed(() => {
    if (!auction.value?.gpb_started_at) return -1
    const gpbMinutes = auction.value.gpb_minutes || 30
    const endMs = new Date(auction.value.gpb_started_at).getTime() + gpbMinutes * 60 * 1000
    const diff = Math.floor((endMs - timerNow.value) / 1000)
    return diff > 0 ? diff : 0
})

const gpbCountdownFormatted = computed(() => {
    const s = gpbCountdownSeconds.value
    if (s < 0) return null
    const hours = Math.floor(s / 3600)
    const mins = Math.floor((s % 3600) / 60)
    const secs = s % 60
    if (hours > 0) return `${hours}ч ${String(mins).padStart(2, '0')}м ${String(secs).padStart(2, '0')}с`
    return `${String(mins).padStart(2, '0')}:${String(secs).padStart(2, '0')}`
})

const isCountdownCritical = computed(() => countdownSeconds.value >= 0 && countdownSeconds.value <= 60)
const isCountdownWarning = computed(() => countdownSeconds.value >= 0 && countdownSeconds.value <= 300)
const isGpbCritical = computed(() => gpbCountdownSeconds.value >= 0 && gpbCountdownSeconds.value <= 60)
const isGpbWarning = computed(() => gpbCountdownSeconds.value >= 0 && gpbCountdownSeconds.value <= 300)

// Bid allocation computed
const winningBids = computed(() => allBids.value.filter(b => b.status === 'winning' || b.status === 'partial'))
const losingBids = computed(() => allBids.value.filter(b => b.status === 'losing'))

const lotSummary = computed(() => {
    const totalBars = auction.value?.bar_count || 0
    const barWeight = auction.value?.bar_weight || 0
    const lotBars = winningBids.value.reduce((s, b) => s + (b.fulfilled || 0), 0)
    const lotWeight = lotBars * barWeight
    const lotTotal = winningBids.value.reduce((s, b) => s + (b.fulfilled || 0) * barWeight * Number(b.amount), 0)
    return { totalBars, barWeight, lotBars, lotWeight, lotTotal }
})

const startTimer = () => {
    timerInterval = setInterval(() => {
        timerNow.value = Date.now()
    }, 1000)
}

// Min price per kg (derived from total lot min_price)
const minPricePerKg = computed(() => {
    if (!auction.value) return null
    const minPrice = Number(auction.value.min_price) || 0
    const barCount = Number(auction.value.bar_count) || 1
    const barWeight = Number(auction.value.bar_weight) || 1
    if (minPrice <= 0) return null
    return Number((minPrice / barCount / barWeight).toFixed(2))
})

// Min allowed bid amount (only min price per kg)
const minBidAmount = computed(() => {
    if (!auction.value) return null
    return minPricePerKg.value
})

// Bid total preview
const bidTotalPreview = computed(() => {
    if (!bidForm.value.amount || !bidForm.value.bar_count || !auction.value) return null
    const total = Number(bidForm.value.amount) * Number(bidForm.value.bar_count) * (Number(auction.value.bar_weight) || 1)
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

    isSubmittingOffer.value = true
    try {
        await axios.post(`/api/client/auctions/${auctionId}/offer`, {
            volume: offerForm.value.volume,
            price: offerForm.value.price,
            comment: offerForm.value.comment || null,
        })
        successMessage.value = 'Предложение отправлено!'
        offerForm.value = { volume: 1, price: '', comment: '' }
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
        formErrors.value.amount = `Минимум: ₽ ${formatPrice(minBidAmount.value)}/кг`
        return
    }

    isSubmittingBid.value = true
    try {
        const response = await axios.post(`/api/client/auctions/${auctionId}/bid`, {
            bar_count: bidForm.value.bar_count,
            amount: bidForm.value.amount,
        })
        successMessage.value = response.data.message || 'Ставка принята!'
        bidForm.value.amount = ''
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

const formatPrice = (val) => {
    if (!val) return '—'
    return Number(val).toLocaleString('ru-RU', { minimumFractionDigits: 2 })
}

const formatDate = (iso) => {
    if (!iso) return '—'
    return new Date(iso).toLocaleString('ru-RU')
}

// Lot total weight
const totalWeight = computed(() => {
    if (!auction.value) return 0
    return ((auction.value.bar_count || 0) * (auction.value.bar_weight || 0)).toFixed(3)
})

onMounted(async () => {
    await fetchAuction()
    startTimer()

    // Subscribe to real-time updates via WebSocket
    echo.channel(`auction.${auctionId}`)
        .listen('.bid.placed', () => {
            fetchAuction()
        })
        .listen('.offer.placed', () => {
            fetchAuction()
        })
})

onUnmounted(() => {
    if (timerInterval) clearInterval(timerInterval)
    echo.leaveChannel(`auction.${auctionId}`)
})
</script>

<template>
  <div class="space-y-6">
    <!-- Back button + connection status -->
    <div class="flex items-center justify-between">
        <button @click="router.push('/client/auctions')" class="flex items-center gap-2 text-gray-500 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white transition-colors text-sm group">
            <svg class="w-5 h-5 group-hover:-translate-x-1 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
            </svg>
            Назад к аукционам
        </button>
        <div class="flex items-center gap-1.5 text-xs" :class="isConnected ? 'text-emerald-500' : 'text-gray-400 dark:text-gray-500'">
            <span class="relative flex h-2 w-2">
                <span v-if="isConnected" class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                <span class="relative inline-flex rounded-full h-2 w-2" :class="isConnected ? 'bg-emerald-500' : 'bg-gray-400'"></span>
            </span>
            {{ statusText }}
        </div>
    </div>

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
      <!-- Header -->
      <div class="bg-white dark:bg-dark-800/80 backdrop-blur-sm rounded-xl border border-gray-200 dark:border-white/5 p-6 shadow-sm">
          <div class="flex items-start justify-between gap-4 flex-wrap">
              <div>
                  <div class="flex items-center gap-3 mb-2 flex-wrap">
                      <span class="px-3 py-1 rounded text-xs font-bold uppercase tracking-wider border"
                            :class="getStatusInfo(auction.status).color">
                          {{ getStatusInfo(auction.status).label }}
                      </span>
                      <!-- Winning/Losing badge -->
                      <span v-if="myStatus === 'winning'" class="px-3 py-1 rounded text-xs font-bold uppercase tracking-wider bg-green-500/20 text-green-400 border border-green-500/30 animate-pulse shadow-[0_0_15px_rgba(34,197,94,0.3)]">
                          🏆 Вы ведёте
                      </span>
                      <span v-else-if="myStatus === 'losing'" class="px-3 py-1 rounded text-xs font-bold uppercase tracking-wider bg-red-500/20 text-red-400 border border-red-500/30">
                          Перебиты
                      </span>
                      <span v-else-if="myStatus === 'partial'" class="px-3 py-1 rounded text-xs font-bold uppercase tracking-wider bg-yellow-500/20 text-yellow-400 border border-yellow-500/30">
                          Частично
                      </span>
                  </div>
                  <h1 class="text-2xl font-kanit font-bold text-gray-900 dark:text-white">{{ auction.title }}</h1>
                  <p v-if="auction.description" class="text-gray-500 dark:text-gray-400 text-sm mt-2 max-w-2xl">{{ auction.description }}</p>
              </div>

              <!-- Countdowns -->
              <div class="flex flex-col gap-3">
                  <!-- Trading countdown -->
                  <div v-if="auction.status === 'active' && countdownFormatted" 
                       class="px-6 py-4 rounded-xl border text-center min-w-[180px]"
                       :class="isCountdownCritical ? 'border-red-500/40 bg-red-500/10' : isCountdownWarning ? 'border-yellow-500/30 bg-yellow-500/10' : 'border-gray-200 dark:border-white/10 bg-gray-50 dark:bg-dark-900/40'">
                      <p class="text-[10px] uppercase tracking-widest text-gray-500 font-bold mb-1">До окончания торгов</p>
                      <p class="font-mono text-3xl font-black tracking-wider"
                         :class="isCountdownCritical ? 'text-red-400 animate-pulse' : isCountdownWarning ? 'text-yellow-400' : 'text-gray-900 dark:text-white'">
                          {{ countdownFormatted }}
                      </p>
                  </div>

                  <!-- GPB countdown -->
                  <div v-if="auction.status === 'gpb_right' && gpbCountdownFormatted"
                       class="px-6 py-4 rounded-xl border text-center min-w-[180px]"
                       :class="isGpbCritical ? 'border-red-500/40 bg-red-500/10' : isGpbWarning ? 'border-yellow-500/30 bg-yellow-500/10' : 'border-purple-500/30 bg-purple-500/10'">
                      <p class="text-[10px] uppercase tracking-widest text-gray-500 font-bold mb-1">Право ГПБ</p>
                      <p class="font-mono text-3xl font-black tracking-wider"
                         :class="isGpbCritical ? 'text-red-400 animate-pulse' : isGpbWarning ? 'text-yellow-400' : 'text-purple-400'">
                          {{ gpbCountdownFormatted }}
                      </p>
                  </div>

                  <!-- Completed / Cancelled badge -->
                  <div v-else-if="['completed','commission','cancelled'].includes(auction.status)"
                       class="px-6 py-4 rounded-xl border border-emerald-500/30 bg-emerald-500/10 text-center min-w-[180px]">
                      <p class="text-sm font-bold text-emerald-400 uppercase tracking-wider">Завершено</p>
                  </div>
              </div>
          </div>

          <!-- Lot Info Bar -->
          <div class="mt-5 grid grid-cols-2 md:grid-cols-5 gap-4">
              <div class="bg-gray-50 dark:bg-dark-900/50 rounded-lg px-4 py-3 border border-gray-200 dark:border-white/5">
                  <p class="text-[10px] uppercase tracking-widest text-gray-500 font-bold mb-1">Слитков</p>
                  <p class="text-lg font-bold text-gray-900 dark:text-white font-mono">{{ auction.bar_count || '—' }}</p>
              </div>
              <div class="bg-gray-50 dark:bg-dark-900/50 rounded-lg px-4 py-3 border border-gray-200 dark:border-white/5">
                  <p class="text-[10px] uppercase tracking-widest text-gray-500 font-bold mb-1">Вес слитка</p>
                  <p class="text-lg font-bold text-gray-900 dark:text-white font-mono">{{ auction.bar_weight || '—' }} кг</p>
              </div>
              <div class="bg-gray-50 dark:bg-dark-900/50 rounded-lg px-4 py-3 border border-gray-200 dark:border-white/5">
                  <p class="text-[10px] uppercase tracking-widest text-gray-500 font-bold mb-1">Общий вес</p>
                  <p class="text-lg font-bold text-gold-400 font-mono">{{ totalWeight }} кг</p>
              </div>
              <div class="bg-gray-50 dark:bg-dark-900/50 rounded-lg px-4 py-3 border border-gray-200 dark:border-white/5">
                  <p class="text-[10px] uppercase tracking-widest text-gray-500 font-bold mb-1">Мин. цена</p>
                  <p class="text-lg font-bold text-gray-900 dark:text-white font-mono"><span class="font-sans text-sm">₽</span> {{ formatPrice(auction.min_price) }}</p>
              </div>
              <div class="bg-gray-50 dark:bg-dark-900/50 rounded-lg px-4 py-3 border border-gray-200 dark:border-white/5">
                  <p class="text-[10px] uppercase tracking-widest text-gray-500 font-bold mb-1">Шаг</p>
                  <p class="text-lg font-bold text-gray-900 dark:text-white font-mono"><span class="font-sans text-sm">₽</span> {{ formatPrice(auction.min_step) }}</p>
              </div>
          </div>
      </div>

      <!-- Success message -->
      <transition enter-active-class="transition ease-out duration-300" enter-from-class="opacity-0 -translate-y-2" enter-to-class="opacity-100 translate-y-0"
                  leave-active-class="transition ease-in duration-200" leave-from-class="opacity-100" leave-to-class="opacity-0">
          <div v-if="successMessage" class="bg-green-500/10 border border-green-500/30 rounded-lg px-5 py-3 flex items-center gap-3">
              <svg class="w-5 h-5 text-green-400 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
              </svg>
              <span class="text-sm text-green-400 font-bold">{{ successMessage }}</span>
          </div>
      </transition>

      <!-- New bids flash -->
      <transition enter-active-class="transition ease-out duration-300" enter-from-class="opacity-0 -translate-y-2" enter-to-class="opacity-100 translate-y-0"
                  leave-active-class="transition ease-in duration-200" leave-from-class="opacity-100" leave-to-class="opacity-0">
          <div v-if="newBidsFlash" class="bg-blue-500/10 border border-blue-500/30 rounded-lg px-5 py-3 flex items-center gap-3">
              <svg class="w-5 h-5 text-blue-400 flex-shrink-0 animate-bounce" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
              </svg>
              <span class="text-sm text-blue-400 font-bold">Новые ставки! Данные обновлены.</span>
          </div>
      </transition>

      <!-- Error message -->
      <div v-if="formErrors.general" class="bg-red-500/10 border border-red-500/30 rounded-lg px-5 py-3 flex items-center gap-3">
          <svg class="w-5 h-5 text-red-400 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
          </svg>
          <span class="text-sm text-red-400 font-bold">{{ formErrors.general }}</span>
      </div>

      <!-- ======== COLLECTING OFFERS SECTION ======== -->
      <div v-if="auction.status === 'collecting_offers'" class="grid grid-cols-1 lg:grid-cols-2 gap-6">
          <!-- Submit Offer Form -->
          <div class="bg-white dark:bg-dark-800/80 backdrop-blur-sm rounded-xl border border-cyan-500/20 p-6 shadow-sm">
              <h3 class="text-lg font-kanit font-bold text-gray-900 dark:text-white mb-5 flex items-center gap-2">
                  <span class="w-2 h-2 rounded-full bg-cyan-500 animate-pulse"></span>
                  Подать предложение
              </h3>
              <form @submit.prevent="submitOffer" class="space-y-4">
                  <div class="space-y-1">
                      <label class="text-[10px] uppercase tracking-widest text-gray-500 font-bold">Количество слитков</label>
                      <input v-model.number="offerForm.volume" type="number" min="1" :max="auction.bar_count"
                             class="w-full bg-gray-50 dark:bg-dark-900 border border-gray-200 dark:border-white/10 rounded-lg px-4 py-3 text-gray-900 dark:text-white font-mono focus:border-cyan-500 focus:outline-none focus:ring-1 focus:ring-cyan-500 transition-all"
                             :class="formErrors.volume ? 'border-red-500/50' : ''" />
                      <p v-if="formErrors.volume" class="text-red-400 text-xs">{{ formErrors.volume }}</p>
                  </div>
                  <div class="space-y-1">
                      <label class="text-[10px] uppercase tracking-widest text-gray-500 font-bold">Цена за кг (₽)</label>
                      <input v-model.number="offerForm.price" type="number" step="0.01" min="0.01" placeholder="0.00"
                             class="w-full bg-gray-50 dark:bg-dark-900 border border-gray-200 dark:border-white/10 rounded-lg px-4 py-3 text-gray-900 dark:text-white font-mono focus:border-cyan-500 focus:outline-none focus:ring-1 focus:ring-cyan-500 transition-all"
                             :class="formErrors.price ? 'border-red-500/50' : ''" />
                      <p v-if="formErrors.price" class="text-red-400 text-xs">{{ formErrors.price }}</p>
                  </div>
                  <div class="space-y-1">
                      <label class="text-[10px] uppercase tracking-widest text-gray-500 font-bold">Комментарий</label>
                      <textarea v-model="offerForm.comment" rows="2" placeholder="Необязательно"
                                class="w-full bg-gray-50 dark:bg-dark-900 border border-gray-200 dark:border-white/10 rounded-lg px-4 py-3 text-gray-900 dark:text-white text-sm focus:border-cyan-500 focus:outline-none focus:ring-1 focus:ring-cyan-500 transition-all resize-none"></textarea>
                  </div>
                  <button type="submit" :disabled="isSubmittingOffer"
                          class="w-full py-3 bg-cyan-500 hover:bg-cyan-400 text-black font-bold uppercase tracking-widest rounded-lg transition-all shadow-lg shadow-cyan-500/20 disabled:opacity-50 disabled:cursor-not-allowed">
                      {{ isSubmittingOffer ? 'Отправка...' : 'Отправить предложение' }}
                  </button>
              </form>
          </div>

          <!-- My Offers List -->
          <div class="bg-white dark:bg-dark-800/80 backdrop-blur-sm rounded-xl border border-gray-200 dark:border-white/5 p-6 shadow-sm">
              <h3 class="text-lg font-kanit font-bold text-gray-900 dark:text-white mb-5">Мои предложения</h3>
              <div v-if="myOffers.length === 0" class="text-center py-8 text-gray-500">
                  <p class="text-sm">Вы ещё не отправляли предложений</p>
              </div>
              <div v-else class="space-y-3">
                  <div v-for="offer in myOffers" :key="offer.id" class="bg-gray-50 dark:bg-dark-900/50 rounded-lg p-4 border border-gray-200 dark:border-white/5">
                      <div class="flex justify-between items-center">
                          <div>
                              <span class="text-sm text-gray-900 dark:text-white font-bold font-mono">{{ Number(offer.volume).toLocaleString() }} слитков</span>
                              <span class="text-gray-500 mx-2">×</span>
                              <span class="text-sm text-cyan-400 font-bold font-mono"><span class="font-sans">₽</span>&nbsp;{{ formatPrice(offer.price) }}/кг</span>
                          </div>
                          <span class="text-xs text-gray-500 font-mono">{{ formatDate(offer.created_at) }}</span>
                      </div>
                      <p v-if="offer.comment" class="text-xs text-gray-400 mt-2">{{ offer.comment }}</p>
                  </div>
              </div>
          </div>
      </div>

      <!-- ======== ACTIVE TRADING SECTION ======== -->
      <div v-if="auction.status === 'active'" class="grid grid-cols-1 lg:grid-cols-3 gap-6">
          <!-- Bid Form -->
          <div class="bg-white dark:bg-dark-800/80 backdrop-blur-sm rounded-xl border p-6 lg:col-span-1 transition-all duration-500 shadow-sm"
               :class="myStatus === 'winning' ? 'border-green-500/30 shadow-[0_0_25px_rgba(34,197,94,0.15)]' : myStatus === 'losing' ? 'border-red-500/30 shadow-[0_0_25px_rgba(239,68,68,0.1)]' : 'border-gold-500/20'">
              <h3 class="text-lg font-kanit font-bold text-gray-900 dark:text-white mb-5 flex items-center gap-2">
                  <span class="w-2 h-2 rounded-full animate-pulse" :class="myStatus === 'winning' ? 'bg-green-500' : 'bg-gold-500'"></span>
                  Сделать ставку
              </h3>
              <form @submit.prevent="placeBid" class="space-y-4">
                  <div class="space-y-1">
                      <label class="text-[10px] uppercase tracking-widest text-gray-500 font-bold">Количество слитков</label>
                      <input v-model.number="bidForm.bar_count" type="number" min="1" :max="auction.bar_count"
                             class="w-full bg-gray-50 dark:bg-dark-900 border border-gray-200 dark:border-white/10 rounded-lg px-4 py-3 text-gray-900 dark:text-white font-mono text-lg focus:border-gold-500 focus:outline-none focus:ring-1 focus:ring-gold-500 transition-all"
                             :class="formErrors.bar_count ? 'border-red-500/50' : ''" />
                      <p v-if="formErrors.bar_count" class="text-red-400 text-xs">{{ Array.isArray(formErrors.bar_count) ? formErrors.bar_count[0] : formErrors.bar_count }}</p>
                  </div>
                  <div class="space-y-1">
                      <label class="text-[10px] uppercase tracking-widest text-gray-500 font-bold">Цена за кг (₽)</label>
                      <input v-model.number="bidForm.amount" type="number" step="0.01" min="0.01" :placeholder="minBidAmount ? `от ₽ ${formatPrice(minBidAmount)}` : '0.00'"
                             class="w-full bg-gray-50 dark:bg-dark-900 border border-gray-200 dark:border-white/10 rounded-lg px-4 py-3 text-gray-900 dark:text-white font-mono text-lg focus:border-gold-500 focus:outline-none focus:ring-1 focus:ring-gold-500 transition-all"
                             :class="formErrors.amount ? 'border-red-500/50' : ''" />
                      <p v-if="formErrors.amount" class="text-red-400 text-xs">{{ Array.isArray(formErrors.amount) ? formErrors.amount[0] : formErrors.amount }}</p>
                      <div class="flex flex-col gap-0.5 mt-1">
                          <p v-if="minBidAmount" class="text-xs text-gray-500 dark:text-gray-400 font-medium">
                              Мин. цена: <span class="font-mono">₽ {{ formatPrice(minBidAmount) }}/кг</span>
                          </p>
                      </div>
                  </div>

                  <!-- Bid total preview -->
                  <div v-if="bidTotalPreview" class="bg-gray-50 dark:bg-dark-900/60 rounded-lg px-4 py-3 border border-gray-200 dark:border-white/5">
                      <p class="text-[10px] uppercase tracking-widest text-gray-500 font-bold mb-1">Сумма ставки</p>
                      <p class="text-xl font-mono font-black text-gold-400">
                          <span class="font-sans text-sm">₽</span>&nbsp;{{ formatPrice(bidTotalPreview) }}
                      </p>
                      <p class="text-[10px] text-gray-500 mt-0.5">
                          {{ bidForm.bar_count }} сл. × {{ auction.bar_weight }} кг × ₽ {{ formatPrice(bidForm.amount) }}
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
                  <div class="space-y-2 max-h-[200px] overflow-auto">
                      <div v-for="bid in myBids" :key="bid.id" class="flex justify-between items-center py-2 px-3 rounded bg-gray-50 dark:bg-dark-900/40 border border-gray-200 dark:border-white/5">
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
          <div class="bg-white dark:bg-dark-800/80 backdrop-blur-sm rounded-xl border border-gray-200 dark:border-white/5 p-6 lg:col-span-2 shadow-sm">
              <h3 class="text-lg font-kanit font-bold text-gray-900 dark:text-white mb-4">Ход торгов</h3>

              <!-- Lot Summary Bar -->
              <div v-if="allBids.length > 0" class="flex items-center gap-5 px-4 py-3 rounded-lg border border-gray-200 dark:border-white/10 bg-gray-50 dark:bg-dark-900/40 mb-4 text-sm flex-wrap">
                  <div class="flex items-center gap-1.5">
                      <span class="text-[10px] uppercase tracking-widest text-gray-500 font-bold">Лот</span>
                      <span class="text-gray-900 dark:text-white font-bold">{{ lotSummary.totalBars }}×{{ lotSummary.barWeight }}кг</span>
                  </div>
                  <div class="flex items-center gap-1.5">
                      <span class="text-[10px] uppercase tracking-widest text-gray-500 font-bold">Распр.</span>
                      <span class="font-bold" :class="lotSummary.lotBars >= lotSummary.totalBars ? 'text-emerald-400' : 'text-yellow-400'">{{ lotSummary.lotBars }}/{{ lotSummary.totalBars }}</span>
                  </div>
                  <div class="flex items-center gap-1.5">
                      <span class="text-[10px] uppercase tracking-widest text-gray-500 font-bold">Вес</span>
                      <span class="text-gray-900 dark:text-white font-bold">{{ lotSummary.lotWeight.toFixed(3) }} кг</span>
                  </div>
                  <div class="flex items-center gap-1.5 ml-auto">
                      <span class="text-[10px] uppercase tracking-widest text-gray-500 font-bold">Сумма</span>
                      <span class="text-emerald-400 font-bold font-mono"><span class="font-sans">₽</span>&nbsp;{{ formatPrice(lotSummary.lotTotal) }}</span>
                  </div>
              </div>

              <div v-if="allBids.length === 0" class="text-center py-12 text-gray-500">
                  <p class="text-sm">Ставок пока нет</p>
              </div>
              <div v-else class="overflow-auto rounded-lg border border-gray-200 dark:border-white/10 bg-gray-50 dark:bg-dark-900/30 max-h-[500px]">
                  <table class="w-full text-left border-collapse relative">
                      <thead class="sticky top-0 bg-gray-100 dark:bg-dark-900 z-10">
                          <tr class="border-b border-gray-200 dark:border-white/10 text-xs text-gray-500 uppercase tracking-widest font-bold">
                              <th class="px-4 py-3 bg-gray-100 dark:bg-dark-900 w-8">#</th>
                              <th class="px-4 py-3 bg-gray-100 dark:bg-dark-900">Участник</th>
                              <th class="px-4 py-3 text-right bg-gray-100 dark:bg-dark-900">Слитков</th>
                              <th class="px-4 py-3 text-right bg-gray-100 dark:bg-dark-900">Цена/кг</th>
                              <th class="px-4 py-3 text-right bg-gray-100 dark:bg-dark-900">Цена/слиток</th>
                              <th class="px-4 py-3 text-right bg-gray-100 dark:bg-dark-900">Сумма</th>
                              <th class="px-4 py-3 text-right bg-gray-100 dark:bg-dark-900">Дата</th>
                          </tr>
                      </thead>
                      <tbody>
                          <!-- Winning Section -->
                          <tr v-if="winningBids.length > 0">
                              <td colspan="7" class="px-4 py-2 bg-emerald-500/10 border-b border-emerald-500/20">
                                  <span class="text-[10px] uppercase tracking-widest text-emerald-400 font-bold flex items-center gap-1.5">
                                      <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                                      В лоте ({{ lotSummary.lotBars }} слитков)
                                  </span>
                              </td>
                          </tr>
                          <tr v-for="(bid, idx) in winningBids" :key="'w-'+bid.id" 
                              class="border-b transition-colors"
                              :class="[
                                  bid.partial ? 'bg-yellow-500/5 border-yellow-500/10 hover:bg-yellow-500/10' : 'bg-emerald-500/5 border-emerald-500/10 hover:bg-emerald-500/10',
                                  bid.is_mine ? 'ring-1 ring-inset ring-gold-500/20' : ''
                              ]">
                              <td class="px-4 py-3 text-sm text-gray-500 font-mono">{{ idx + 1 }}</td>
                              <td class="px-4 py-3">
                                  <span class="text-sm font-bold" :class="bid.is_mine ? 'text-gold-400' : 'text-gray-900 dark:text-white'">{{ bid.participant_label }}</span>
                              </td>
                              <td class="px-4 py-3 text-right font-mono text-sm font-bold" :class="bid.partial ? 'text-yellow-400' : 'text-gray-900 dark:text-white'">
                                  {{ bid.fulfilled }}
                                  <span v-if="bid.partial" class="text-[10px] text-yellow-500 ml-1">(частич.)</span>
                              </td>
                              <td class="px-4 py-3 text-right font-mono text-sm text-emerald-400 font-bold"><span class="font-sans">₽</span>&nbsp;{{ formatPrice(bid.amount) }}</td>
                              <td class="px-4 py-3 text-right font-mono text-sm text-emerald-400 font-bold"><span class="font-sans">₽</span>&nbsp;{{ formatPrice(Number(bid.amount) * (auction.bar_weight || 1)) }}</td>
                              <td class="px-4 py-3 text-right font-mono text-sm text-gray-900 dark:text-white"><span class="font-sans">₽</span>&nbsp;{{ formatPrice((bid.fulfilled || 0) * (auction.bar_weight || 1) * Number(bid.amount)) }}</td>
                              <td class="px-4 py-3 text-right font-mono text-xs text-gray-500">{{ formatDate(bid.created_at) }}</td>
                          </tr>

                          <!-- Losing Section -->
                          <tr v-if="losingBids.length > 0">
                              <td colspan="7" class="px-4 py-2 bg-red-500/10 border-b border-red-500/20 border-t border-t-gray-200 dark:border-t-white/10">
                                  <span class="text-[10px] uppercase tracking-widest text-red-400 font-bold flex items-center gap-1.5">
                                      <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                                      Не попали ({{ losingBids.reduce((sum, b) => sum + b.bar_count, 0) }})
                                  </span>
                              </td>
                          </tr>
                          <tr v-for="(bid, idx) in losingBids" :key="'l-'+bid.id" 
                              class="border-b border-gray-100 dark:border-white/5 transition-colors opacity-60"
                              :class="bid.is_mine ? 'ring-1 ring-inset ring-gold-500/20 opacity-80' : 'hover:bg-gray-50 dark:hover:bg-white/5'">
                              <td class="px-4 py-3 text-sm text-gray-600 font-mono">{{ winningBids.length + idx + 1 }}</td>
                              <td class="px-4 py-3">
                                  <span class="text-sm font-bold" :class="bid.is_mine ? 'text-gold-400/70' : 'text-gray-400'">{{ bid.participant_label }}</span>
                              </td>
                              <td class="px-4 py-3 text-right font-mono text-sm text-gray-400">{{ bid.bar_count }}</td>
                              <td class="px-4 py-3 text-right font-mono text-sm text-gray-500"><span class="font-sans">₽</span>&nbsp;{{ formatPrice(bid.amount) }}</td>
                              <td class="px-4 py-3 text-right font-mono text-sm text-gray-500"><span class="font-sans">₽</span>&nbsp;{{ formatPrice(Number(bid.amount) * (auction.bar_weight || 1)) }}</td>
                              <td class="px-4 py-3 text-right font-mono text-sm text-gray-600">—</td>
                              <td class="px-4 py-3 text-right font-mono text-xs text-gray-600">{{ formatDate(bid.created_at) }}</td>
                          </tr>
                      </tbody>
                  </table>
              </div>
          </div>
      </div>

      <!-- ======== GPB / COMMISSION / NON-ACTIVE SECTION ======== -->
      <div v-if="['gpb_right','commission','completed','paused','cancelled'].includes(auction.status)" class="grid grid-cols-1 lg:grid-cols-2 gap-6">
          <!-- My Status Card -->
          <div class="bg-white dark:bg-dark-800/80 backdrop-blur-sm rounded-xl border p-6 transition-all duration-500 shadow-sm"
               :class="myStatus === 'winning' ? 'border-green-500/30 shadow-[0_0_25px_rgba(34,197,94,0.15)]' : myStatus === 'losing' ? 'border-red-500/30' : 'border-gray-200 dark:border-white/5'">
              <h3 class="text-lg font-kanit font-bold text-gray-900 dark:text-white mb-4">Мой результат</h3>
              
              <div v-if="myStatus === 'winning'" class="bg-green-500/10 border border-green-500/30 rounded-lg p-5 text-center">
                  <p class="text-3xl mb-2">🏆</p>
                  <p class="text-lg font-bold text-green-400">Вы выигрываете</p>
                  <p class="text-sm text-gray-400 mt-1">{{ myWinningBars }} слитков ({{ (myWinningBars * (auction.bar_weight || 0)).toFixed(3) }} кг)</p>
              </div>
              <div v-else-if="myStatus === 'partial'" class="bg-yellow-500/10 border border-yellow-500/30 rounded-lg p-5 text-center">
                  <p class="text-3xl mb-2">⚡</p>
                  <p class="text-lg font-bold text-yellow-400">Частичная победа</p>
                  <p class="text-sm text-gray-400 mt-1">{{ myWinningBars }} слитков из запрошенных</p>
              </div>
              <div v-else-if="myStatus === 'losing'" class="bg-red-500/10 border border-red-500/30 rounded-lg p-5 text-center">
                  <p class="text-3xl mb-2">📉</p>
                  <p class="text-lg font-bold text-red-400">Вы перебиты</p>
                  <p class="text-sm text-gray-400 mt-1">Ваши ставки не вошли в лот</p>
              </div>
              <div v-else class="bg-gray-50 dark:bg-dark-900/50 border border-gray-200 dark:border-white/5 rounded-lg p-5 text-center">
                  <p class="text-3xl mb-2">📊</p>
                  <p class="text-lg font-bold text-gray-400">Нет ставок</p>
                  <p class="text-sm text-gray-500 mt-1">Вы не размещали ставок в этом аукционе</p>
              </div>

              <!-- GPB info block -->
              <div v-if="auction.status === 'gpb_right'" class="mt-5 bg-purple-500/10 border border-purple-500/30 rounded-lg p-4">
                  <div class="flex items-center gap-2 mb-2">
                      <span class="text-lg">🏛</span>
                      <span class="text-sm font-bold text-purple-400 uppercase tracking-wider">Право ГПБ</span>
                  </div>
                  <p class="text-xs text-gray-400">ГПБ рассматривает результаты торгов и может реализовать своё право приоритетной покупки.</p>
              </div>

              <!-- My bids summary -->
              <div v-if="myBids.length > 0" class="mt-5 space-y-2">
                  <h4 class="text-xs uppercase tracking-widest text-gray-500 font-bold">Мои ставки</h4>
                  <div v-for="bid in myBids" :key="bid.id" class="flex justify-between items-center py-2 px-3 rounded bg-gray-50 dark:bg-dark-900/40 border border-gray-200 dark:border-white/5">
                      <div>
                          <span class="text-sm text-gray-900 dark:text-white font-mono font-bold">{{ bid.bar_count }} сл.</span>
                          <span class="text-gray-500 mx-1">·</span>
                          <span class="text-sm text-gold-400 font-mono font-bold"><span class="font-sans">₽</span>&nbsp;{{ formatPrice(bid.amount) }}/кг</span>
                      </div>
                      <span class="text-[10px] text-gray-500 font-mono">{{ formatDate(bid.created_at) }}</span>
                  </div>
              </div>
          </div>

          <!-- All Bids for completed/GPB -->
          <div class="bg-white dark:bg-dark-800/80 backdrop-blur-sm rounded-xl border border-gray-200 dark:border-white/5 p-6 shadow-sm">
              <h3 class="text-lg font-kanit font-bold text-gray-900 dark:text-white mb-4">Результаты торгов</h3>

              <!-- Lot Summary Bar -->
              <div v-if="allBids.length > 0" class="flex items-center gap-5 px-4 py-3 rounded-lg border border-gray-200 dark:border-white/10 bg-gray-50 dark:bg-dark-900/40 mb-4 text-sm flex-wrap">
                  <div class="flex items-center gap-1.5">
                      <span class="text-[10px] uppercase tracking-widest text-gray-500 font-bold">Лот</span>
                      <span class="text-gray-900 dark:text-white font-bold">{{ lotSummary.totalBars }}×{{ lotSummary.barWeight }}кг</span>
                  </div>
                  <div class="flex items-center gap-1.5">
                      <span class="text-[10px] uppercase tracking-widest text-gray-500 font-bold">Распр.</span>
                      <span class="font-bold" :class="lotSummary.lotBars >= lotSummary.totalBars ? 'text-emerald-400' : 'text-yellow-400'">{{ lotSummary.lotBars }}/{{ lotSummary.totalBars }}</span>
                  </div>
                  <div class="flex items-center gap-1.5">
                      <span class="text-[10px] uppercase tracking-widest text-gray-500 font-bold">Вес</span>
                      <span class="text-gray-900 dark:text-white font-bold">{{ lotSummary.lotWeight.toFixed(3) }} кг</span>
                  </div>
                  <div class="flex items-center gap-1.5 ml-auto">
                      <span class="text-[10px] uppercase tracking-widest text-gray-500 font-bold">Сумма</span>
                      <span class="text-emerald-400 font-bold font-mono"><span class="font-sans">₽</span>&nbsp;{{ formatPrice(lotSummary.lotTotal) }}</span>
                  </div>
              </div>

              <div v-if="allBids.length === 0" class="text-center py-12 text-gray-500">
                  <p class="text-sm">Ставок нет</p>
              </div>
              <div v-else class="overflow-auto rounded-lg border border-gray-200 dark:border-white/10 bg-gray-50 dark:bg-dark-900/30 max-h-[500px]">
                  <table class="w-full text-left border-collapse relative">
                      <thead class="sticky top-0 bg-gray-100 dark:bg-dark-900 z-10">
                          <tr class="border-b border-gray-200 dark:border-white/10 text-xs text-gray-500 uppercase tracking-widest font-bold">
                              <th class="px-4 py-3 bg-gray-100 dark:bg-dark-900 w-8">#</th>
                              <th class="px-4 py-3 bg-gray-100 dark:bg-dark-900">Участник</th>
                              <th class="px-4 py-3 text-right bg-gray-100 dark:bg-dark-900">Слитков</th>
                              <th class="px-4 py-3 text-right bg-gray-100 dark:bg-dark-900">Цена/кг</th>
                              <th class="px-4 py-3 text-right bg-gray-100 dark:bg-dark-900">Цена/слиток</th>
                              <th class="px-4 py-3 text-right bg-gray-100 dark:bg-dark-900">Сумма</th>
                              <th class="px-4 py-3 text-right bg-gray-100 dark:bg-dark-900">Дата</th>
                          </tr>
                      </thead>
                      <tbody>
                          <tr v-if="winningBids.length > 0">
                              <td colspan="7" class="px-4 py-2 bg-emerald-500/10 border-b border-emerald-500/20">
                                  <span class="text-[10px] uppercase tracking-widest text-emerald-400 font-bold flex items-center gap-1.5">
                                      <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                                      В лоте ({{ lotSummary.lotBars }} слитков)
                                  </span>
                              </td>
                          </tr>
                          <tr v-for="(bid, idx) in winningBids" :key="'w-'+bid.id" 
                              class="border-b transition-colors"
                              :class="[
                                  bid.partial ? 'bg-yellow-500/5 border-yellow-500/10' : 'bg-emerald-500/5 border-emerald-500/10',
                                  bid.is_mine ? 'ring-1 ring-inset ring-gold-500/20' : ''
                              ]">
                              <td class="px-4 py-3 text-sm text-gray-500 font-mono">{{ idx + 1 }}</td>
                              <td class="px-4 py-3"><span class="text-sm font-bold" :class="bid.is_mine ? 'text-gold-400' : 'text-gray-900 dark:text-white'">{{ bid.participant_label }}</span></td>
                              <td class="px-4 py-3 text-right font-mono text-sm font-bold" :class="bid.partial ? 'text-yellow-400' : 'text-gray-900 dark:text-white'">
                                  {{ bid.fulfilled }}
                                  <span v-if="bid.partial" class="text-[10px] text-yellow-500 ml-1">(частич.)</span>
                              </td>
                              <td class="px-4 py-3 text-right font-mono text-sm text-emerald-400 font-bold"><span class="font-sans">₽</span>&nbsp;{{ formatPrice(bid.amount) }}</td>
                              <td class="px-4 py-3 text-right font-mono text-sm text-emerald-400 font-bold"><span class="font-sans">₽</span>&nbsp;{{ formatPrice(Number(bid.amount) * (auction.bar_weight || 1)) }}</td>
                              <td class="px-4 py-3 text-right font-mono text-sm text-gray-900 dark:text-white"><span class="font-sans">₽</span>&nbsp;{{ formatPrice((bid.fulfilled || 0) * (auction.bar_weight || 1) * Number(bid.amount)) }}</td>
                              <td class="px-4 py-3 text-right font-mono text-xs text-gray-500">{{ formatDate(bid.created_at) }}</td>
                          </tr>
                          <tr v-if="losingBids.length > 0">
                              <td colspan="7" class="px-4 py-2 bg-red-500/10 border-b border-red-500/20 border-t border-t-gray-200 dark:border-t-white/10">
                                  <span class="text-[10px] uppercase tracking-widest text-red-400 font-bold flex items-center gap-1.5">
                                      <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                                      Не попали ({{ losingBids.reduce((sum, b) => sum + b.bar_count, 0) }})
                                  </span>
                              </td>
                          </tr>
                          <tr v-for="(bid, idx) in losingBids" :key="'l-'+bid.id" 
                              class="border-b border-gray-100 dark:border-white/5 opacity-60"
                              :class="bid.is_mine ? 'ring-1 ring-inset ring-gold-500/20 opacity-80' : ''">
                              <td class="px-4 py-3 text-sm text-gray-600 font-mono">{{ winningBids.length + idx + 1 }}</td>
                              <td class="px-4 py-3"><span class="text-sm font-bold" :class="bid.is_mine ? 'text-gold-400/70' : 'text-gray-400'">{{ bid.participant_label }}</span></td>
                              <td class="px-4 py-3 text-right font-mono text-sm text-gray-400">{{ bid.bar_count }}</td>
                              <td class="px-4 py-3 text-right font-mono text-sm text-gray-500"><span class="font-sans">₽</span>&nbsp;{{ formatPrice(bid.amount) }}</td>
                              <td class="px-4 py-3 text-right font-mono text-sm text-gray-500"><span class="font-sans">₽</span>&nbsp;{{ formatPrice(Number(bid.amount) * (auction.bar_weight || 1)) }}</td>
                              <td class="px-4 py-3 text-right font-mono text-sm text-gray-600">—</td>
                              <td class="px-4 py-3 text-right font-mono text-xs text-gray-600">{{ formatDate(bid.created_at) }}</td>
                          </tr>
                      </tbody>
                  </table>
              </div>
          </div>
      </div>

      <!-- My Offers for non-collecting statuses -->
      <div v-if="auction.status !== 'collecting_offers' && myOffers.length > 0" class="bg-white dark:bg-dark-800/80 backdrop-blur-sm rounded-xl border border-gray-200 dark:border-white/5 p-6 shadow-sm">
          <h3 class="text-lg font-kanit font-bold text-gray-900 dark:text-white mb-4">Мои предложения</h3>
          <div class="space-y-2">
              <div v-for="offer in myOffers" :key="offer.id" class="flex justify-between items-center py-2 px-3 rounded bg-gray-50 dark:bg-dark-900/40 border border-gray-200 dark:border-white/5">
                  <div>
                      <span class="text-sm text-gray-900 dark:text-white font-mono font-bold">{{ Number(offer.volume).toLocaleString() }} сл.</span>
                      <span class="text-gray-500 mx-2">×</span>
                      <span class="text-sm text-cyan-400 font-mono font-bold"><span class="font-sans">₽</span>&nbsp;{{ formatPrice(offer.price) }}/кг</span>
                  </div>
                  <span class="text-xs text-gray-500 font-mono">{{ formatDate(offer.created_at) }}</span>
              </div>
          </div>
      </div>
    </template>
  </div>
</template>
