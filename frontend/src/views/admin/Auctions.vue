```
<script setup>
import { ref, computed, watch, onMounted, onUnmounted, nextTick } from 'vue'
import { onBeforeRouteLeave, useRouter, useRoute } from 'vue-router'
import StandardModal from '../../components/ui/StandardModal.vue'
import axios from 'axios'
import echo from '@/echo.js'
import { useConnectionStatus } from '@/composables/useConnectionStatus.js'

const { isConnected, statusText } = useConnectionStatus()

// --- State ---
const auctions = ref([])
const searchQuery = ref('')
const filterStatus = ref('')
const sortKey = ref('start_at')
const sortOrder = ref('desc')
const page = ref(1)
const perPage = 50
const isLoading = ref(false)
const hasMore = ref(true)
const loadingType = ref('initial')

// --- Edit State ---
const isEditing = ref(false)
const editingId = ref(null)

// --- Logic ---
const showModal = ref(false)
const showConfirmModal = ref(false)
const deleteId = ref(null)
const selectedAuction = ref(null)
const initialAuctionState = ref(null) // To track changes
const newAuction = ref({ 
    title: '', 
    status: 'draft', 
    min_step: 50,
    step_time: 5,
    timezone: 'Europe/Moscow',
    min_price: 11000,
    description: '',
    bar_count: 1,
    bar_weight: 12.4,
    gpb_minutes: 30,
    start_date: '', 
    start_time: '10:00',
    end_date: '', 
    end_time: '18:00' 
})

// --- Participants ---
const allParticipants = ref([])
const selectedParticipantIds = ref([])
const selectParticipants = ref(false)
const participantSearch = ref('')
const inviteAll = ref(true)
const isSendingInvitations = ref(false)
const errors = ref({})
const activeTab = ref('general') // 'general' | 'participants' | 'offers' | 'trading' | 'gpb' | 'results'
const showUnsavedModal = ref(false)
const showInviteModal = ref(false)
const participantSearchRef = ref(null)

// --- Initial Offers ---
const initialOffers = ref([])
const isLoadingOffers = ref(false)
const offersCountCache = ref(0)
const offersDisplayCount = computed(() => initialOffers.value.length || offersCountCache.value)

const fetchInitialOffers = async (silent = false) => {
    if (!editingId.value) return
    if (!silent) isLoadingOffers.value = true
    try {
        const response = await axios.get(`/api/auctions/${editingId.value}/initial-offers`)
        initialOffers.value = response.data
    } catch (e) {
        console.error('Failed to load offers:', e)
    } finally {
        isLoadingOffers.value = false
    }
}

// --- Bids (Trading) ---
const auctionBids = ref([])
const isLoadingBids = ref(false)
const bidsCountCache = ref(0)
const bidsDisplayCount = computed(() => auctionBids.value.length || bidsCountCache.value)

const fetchBids = async (silent = false) => {
    if (!editingId.value) return
    if (!silent) isLoadingBids.value = true
    try {
        const response = await axios.get(`/api/auctions/${editingId.value}/bids`)
        auctionBids.value = response.data.bids || []
    } catch (e) {
        console.error('Failed to load bids:', e)
    } finally {
        isLoadingBids.value = false
    }
}

// --- Fallback polling (only when WebSocket is disconnected) ---
let fallbackInterval = null
const startFallbackPolling = () => {
    stopFallbackPolling()
    fallbackInterval = setInterval(() => {
        if (!showModal.value || !editingId.value) return
        const status = newAuction.value.status
        if (status === 'collecting_offers') fetchInitialOffers(true)
        if (['active', 'gpb_right'].includes(status)) fetchBids(true)
    }, 5000)
}
const stopFallbackPolling = () => {
    if (fallbackInterval) {
        clearInterval(fallbackInterval)
        fallbackInterval = null
    }
}

// Auto-toggle fallback polling based on WebSocket connection
watch(isConnected, (connected) => {
    if (connected) {
        stopFallbackPolling()
    } else if (showModal.value) {
        startFallbackPolling()
    }
})

// Computed: allocate bids into winning / losing
const allocatedBids = computed(() => {
    const totalBars = newAuction.value.bar_count || 0
    const barWeight = newAuction.value.bar_weight || 0
    const sorted = [...auctionBids.value].sort((a, b) => {
        if (Number(b.amount) !== Number(a.amount)) return Number(b.amount) - Number(a.amount)
        return new Date(a.created_at) - new Date(b.created_at)
    })

    let remaining = totalBars
    const winning = []
    const losing = []

    for (const bid of sorted) {
        if (remaining <= 0) {
            losing.push({ ...bid, fulfilled: 0, partial: false })
        } else if (bid.bar_count <= remaining) {
            winning.push({ ...bid, fulfilled: bid.bar_count, partial: false })
            remaining -= bid.bar_count
        } else {
            // Partial: split into winning part and losing remainder
            winning.push({ ...bid, fulfilled: remaining, partial: true })
            const remainder = bid.bar_count - remaining
            losing.push({ ...bid, fulfilled: 0, partial: false, bar_count: remainder, isRemainder: true })
            remaining = 0
        }
    }

    const lotTotal = winning.reduce((sum, b) => sum + b.fulfilled * barWeight * Number(b.amount), 0)
    const lotBars = winning.reduce((sum, b) => sum + b.fulfilled, 0)
    const lotWeight = lotBars * barWeight

    return { winning, losing, lotTotal, lotBars, lotWeight, totalBars, barWeight }
})

// Check if any bid has user with delivery_basis set
const hasBasisBids = computed(() => {
    return auctionBids.value.some(b => b.user?.delivery_basis != null && Number(b.user.delivery_basis) > 0)
})

// Computed: GPB right — auto-detect GPB participant bids and show buyout
const gpbAllocatedBids = computed(() => {
    const totalBars = newAuction.value.bar_count || 0
    const barWeight = newAuction.value.bar_weight || 0

    // Separate GPB bids from regular bids
    const gpbBids = auctionBids.value.filter(b => b.user?.is_gpb)
    const regularBids = auctionBids.value.filter(b => !b.user?.is_gpb)

    // Total bars GPB wants to buy (sum of all GPB bids' bar_count)
    const gpbWantBars = gpbBids.reduce((s, b) => s + (b.bar_count || 0), 0)

    // Sort regular bids by price ASC (cheapest first) for GPB buyout
    const regularAsc = [...regularBids].sort((a, b) => {
        if (Number(a.amount) !== Number(b.amount)) return Number(a.amount) - Number(b.amount)
        return new Date(a.created_at) - new Date(b.created_at)
    })

    // Phase 1: GPB buys from cheapest regular bids
    let gpbRemaining = Math.min(gpbWantBars, totalBars)
    const gpbBought = [] // bars bought by GPB at regular participants' prices
    const bidGpbUsed = new Map() // regular bid.id -> bars consumed by GPB

    for (const bid of regularAsc) {
        if (gpbRemaining <= 0) break
        const take = Math.min(bid.bar_count, gpbRemaining)
        gpbBought.push({
            ...bid,
            fulfilled: take,
            originalBars: bid.bar_count,
            pricePerKg: Number(bid.amount),
        })
        bidGpbUsed.set(bid.id, (bidGpbUsed.get(bid.id) || 0) + take)
        gpbRemaining -= take
    }

    const gpbTotalBars = gpbBought.reduce((s, b) => s + b.fulfilled, 0)
    const gpbTotal = gpbBought.reduce((s, b) => s + b.fulfilled * barWeight * b.pricePerKg, 0)

    // GPB participant info (first GPB bidder for display)
    const gpbUser = gpbBids.length > 0 ? gpbBids[0].user : null

    // Phase 2: remaining bars go to regular participants (sorted by price DESC)
    const regularDesc = [...regularBids].sort((a, b) => {
        if (Number(b.amount) !== Number(a.amount)) return Number(b.amount) - Number(a.amount)
        return new Date(a.created_at) - new Date(b.created_at)
    })

    const remainingBars = totalBars - gpbTotalBars
    let pRemaining = remainingBars
    const participantWinning = []
    const participantLosing = []

    for (const bid of regularDesc) {
        const gpbUsed = bidGpbUsed.get(bid.id) || 0
        const availableBars = bid.bar_count - gpbUsed
        if (availableBars <= 0) {
            participantLosing.push({ ...bid, fulfilled: 0, bar_count: bid.bar_count, partial: false, lostToGpb: true })
            continue
        }
        if (pRemaining <= 0) {
            participantLosing.push({ ...bid, fulfilled: 0, bar_count: availableBars, partial: false })
        } else if (availableBars <= pRemaining) {
            participantWinning.push({ ...bid, fulfilled: availableBars, bar_count: availableBars, partial: false })
            pRemaining -= availableBars
        } else {
            participantWinning.push({ ...bid, fulfilled: pRemaining, bar_count: availableBars, partial: true })
            const leftover = availableBars - pRemaining
            participantLosing.push({ ...bid, fulfilled: 0, bar_count: leftover, partial: false, isRemainder: true })
            pRemaining = 0
        }
    }

    const participantTotal = participantWinning.reduce((s, b) => s + b.fulfilled * barWeight * Number(b.amount), 0)
    const participantBars = participantWinning.reduce((s, b) => s + b.fulfilled, 0)
    const grandTotal = gpbTotal + participantTotal

    return {
        gpbBids, gpbUser, gpbWantBars,
        gpbBought, gpbTotalBars, gpbTotal,
        participantWinning, participantLosing, participantTotal, participantBars,
        grandTotal, totalBars, barWeight,
        hasGpbBids: gpbBids.length > 0,
    }
})

// --- Countdown Timer ---
const timerNow = ref(Date.now())
let timerInterval = null

// Trading countdown (active status -> end_at)
const tradingCountdownSeconds = computed(() => {
    const endRaw = newAuction.value.end_date && newAuction.value.end_time
        ? `${newAuction.value.end_date}T${newAuction.value.end_time}:00`
        : null
    if (!endRaw) return -1
    const endMs = new Date(endRaw).getTime()
    const diff = Math.floor((endMs - timerNow.value) / 1000)
    return diff > 0 ? diff : 0
})

const tradingCountdownFormatted = computed(() => {
    const s = tradingCountdownSeconds.value
    if (s < 0) return null
    const hours = Math.floor(s / 3600)
    const mins = Math.floor((s % 3600) / 60)
    const secs = s % 60
    if (hours > 0) return `${hours}ч ${String(mins).padStart(2, '0')}м ${String(secs).padStart(2, '0')}с`
    return `${String(mins).padStart(2, '0')}:${String(secs).padStart(2, '0')}`
})

const tradingFinished = computed(() => {
    return ['gpb_right', 'commission', 'completed', 'cancelled'].includes(newAuction.value.status)
})

// GPB countdown (gpb_right status -> gpb_started_at + gpb_minutes)
const gpbCountdownSeconds = computed(() => {
    const currentAuction = auctions.value.find(a => a.id === editingId.value)
    const gpbStart = currentAuction?.gpb_started_at
    if (!gpbStart) return -1
    const gpbMinutes = newAuction.value.gpb_minutes || 30
    const endMs = new Date(gpbStart).getTime() + gpbMinutes * 60 * 1000
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

const gpbFinished = computed(() => {
    return ['commission', 'completed', 'cancelled'].includes(newAuction.value.status)
})

// Combined for backward compat (used in startCountdown logic)
const countdownSeconds = computed(() => {
    if (newAuction.value.status === 'active') return tradingCountdownSeconds.value
    if (newAuction.value.status === 'gpb_right') return gpbCountdownSeconds.value
    return -1
})

const countdownFormatted = computed(() => {
    if (newAuction.value.status === 'active') return tradingCountdownFormatted.value
    if (newAuction.value.status === 'gpb_right') return gpbCountdownFormatted.value
    return null
})

const startCountdown = () => {
    stopCountdown()
    timerNow.value = Date.now()
    timerInterval = setInterval(async () => {
        timerNow.value = Date.now()
        // Auto-transition when timer hits 0
        if (countdownSeconds.value === 0) {
            stopCountdown()
            
            if (newAuction.value.status === 'active' && editingId.value) {
                // Call backend to transition to gpb_right
                try {
                    const response = await axios.post(`/api/auctions/${editingId.value}/transition-gpb`)
                    const updated = response.data
                    
                    // Update form in-place
                    newAuction.value.status = 'gpb_right'
                    
                    // Update gpb_started_at in auctions list for timer
                    const idx = auctions.value.findIndex(a => a.id === editingId.value)
                    if (idx !== -1) {
                        auctions.value[idx].gpb_started_at = updated.gpb_started_at
                        auctions.value[idx].status = 'gpb_right'
                    }
                    
                    // Restart timer for GPB countdown
                    startCountdown()
                } catch (e) {
                    console.error('Failed to transition to GPB:', e)
                }
            }
            
            if (newAuction.value.status === 'gpb_right' && editingId.value) {
                // Transition to commission when GPB time expires
                try {
                    await axios.put(`/api/auctions/${editingId.value}`, { status: 'commission' })
                    
                    // Update form in-place
                    newAuction.value.status = 'commission'
                    
                    // Update in auctions list
                    const idx = auctions.value.findIndex(a => a.id === editingId.value)
                    if (idx !== -1) {
                        auctions.value[idx].status = 'commission'
                    }
                } catch (e) {
                    console.error('Failed to transition to commission:', e)
                }
            }
            
            // Refresh the list in background
            resetList()
        }
    }, 1000)
}

const stopCountdown = () => {
    if (timerInterval) {
        clearInterval(timerInterval)
        timerInterval = null
    }
}

// Status options
const statusOptions = [
    { value: 'draft', label: 'Черновик', color: 'bg-gray-500/20 text-gray-400' },
    { value: 'collecting_offers', label: 'Сбор предложений', color: 'bg-cyan-500/20 text-cyan-400' },
    { value: 'scheduled', label: 'Запланирован', color: 'bg-purple-500/20 text-purple-400' },
    { value: 'active', label: 'Активный', color: 'bg-amber-500/20 text-amber-400' },

    { value: 'gpb_right', label: 'Право ГПБ', color: 'bg-blue-500/20 text-blue-400' },
    { value: 'commission', label: 'Работа комиссии', color: 'bg-orange-500/20 text-orange-400' },
    { value: 'completed', label: 'Завершён', color: 'bg-emerald-500/20 text-emerald-400' },
    { value: 'paused', label: 'Приостановлен', color: 'bg-amber-800/20 text-amber-700' },
    { value: 'cancelled', label: 'Отменён', color: 'bg-red-500/20 text-red-400' },
]

const timezoneOptions = [
    { value: 'Europe/Moscow', label: 'Москва (UTC+3)' },
    { value: 'Europe/Kaliningrad', label: 'Калининград (UTC+2)' },
    { value: 'Europe/Samara', label: 'Самара (UTC+4)' },
    { value: 'Asia/Yekaterinburg', label: 'Екатеринбург (UTC+5)' },
    { value: 'Asia/Novosibirsk', label: 'Новосибирск (UTC+7)' },
    { value: 'Asia/Vladivostok', label: 'Владивосток (UTC+10)' },
]

const getStatusClass = (status) => {
    return statusOptions.find(s => s.value === status)?.color || 'bg-gray-500/20 text-gray-400'
}

const getStatusLabel = (status) => {
    return statusOptions.find(s => s.value === status)?.label || status
}

const filteredParticipants = computed(() => {
    if (!participantSearch.value) return allParticipants.value
    const q = participantSearch.value.toLowerCase()
    return allParticipants.value.filter(p =>
        p.name?.toLowerCase().includes(q) ||
        (p.inn && p.inn.includes(q)) ||
        (p.phone && p.phone.includes(q)) ||
        (p.email && p.email.toLowerCase().includes(q))
    )
})

const toggleParticipant = (orgId) => {
    const idx = selectedParticipantIds.value.indexOf(orgId)
    if (idx === -1) {
        selectedParticipantIds.value.push(orgId)
    } else {
        selectedParticipantIds.value.splice(idx, 1)
    }
}

const fetchParticipants = async () => {
    try {
        const response = await axios.get('/api/participants-list')
        allParticipants.value = response.data
    } catch (e) {
        console.error('Failed to load participants:', e)
    }
}

const sendInvitations = async (auctionId) => {
    showInviteModal.value = true
}

const confirmSendInvitations = async () => {
    showInviteModal.value = false
    isSendingInvitations.value = true
    try {
        const response = await axios.post(`/api/auctions/${editingId.value}/send-invitations`)
        alert(response.data.message)
    } catch (e) {
        console.error('Failed to send invitations:', e)
        alert('Ошибка при отправке приглашений')
    } finally {
        isSendingInvitations.value = false
    }
}

const advanceStatus = () => {
    const statusOrder = [
        'draft',
        'collecting_offers',
        'scheduled',
        'active',
        'gpb_right',
        'commission',
        'completed'
    ]
    
    const currentIndex = statusOrder.indexOf(newAuction.value.status)
    if (currentIndex !== -1 && currentIndex < statusOrder.length - 1) {
        const next = statusOrder[currentIndex + 1]
        if (!blockedStatuses.value.includes(next)) {
            newAuction.value.status = next
        }
    }
}

// Blocked statuses based on time
const blockedStatuses = computed(() => {
    const blocked = []
    const now = Date.now()
    
    // Check if end_at has passed
    if (newAuction.value.end_date && newAuction.value.end_time) {
        const endMs = new Date(`${newAuction.value.end_date}T${newAuction.value.end_time}:00`).getTime()
        if (now >= endMs) {
            blocked.push('collecting_offers', 'scheduled', 'active')
        }
    }
    
    // Check if GPB time has passed
    const currentAuction = auctions.value.find(a => a.id === editingId.value)
    if (currentAuction?.gpb_started_at) {
        const gpbMinutes = newAuction.value.gpb_minutes || 30
        const gpbEndMs = new Date(currentAuction.gpb_started_at).getTime() + gpbMinutes * 60 * 1000
        if (now >= gpbEndMs) {
            blocked.push('collecting_offers', 'scheduled', 'active', 'gpb_right')
        }
    }
    
    return [...new Set(blocked)]
})

const getStatusDotClass = (status) => {
    const map = {
        'draft': 'bg-gray-500',
        'collecting_offers': 'bg-cyan-500',
        'scheduled': 'bg-purple-500',
        'active': 'bg-amber-500',

        'gpb_right': 'bg-blue-500',
        'commission': 'bg-orange-500',
        'completed': 'bg-emerald-400',
        'paused': 'bg-amber-800',
        'cancelled': 'bg-red-500'
    }
    return map[status] || 'bg-gray-400'
}

// --- API Interactions ---
const loadMore = async (reset = false) => {
    if (!reset && (isLoading.value || !hasMore.value)) return
    
    if (reset) {
        if (auctions.value.length > 0) loadingType.value = 'search'
        else loadingType.value = 'initial'
        page.value = 1
        hasMore.value = true
    } else {
        loadingType.value = 'scroll'
    }
    
    isLoading.value = true

    
    try {
        const response = await axios.get('/api/auctions', {
            params: {
                page: page.value,
                per_page: perPage,
                search: searchQuery.value,
                status: filterStatus.value,
                sort_key: sortKey.value,
                sort_order: sortOrder.value
            }
        })
        
        const data = response.data.data
        
        const mappedData = data.map(a => ({
            id: a.id,
            title: a.title,
            status: a.status,
            start_at: a.start_at ? a.start_at.replace(/^(\d{4})-(\d{2})-(\d{2})[T ](\d{2}):(\d{2}).*$/, '$3.$2.$1, $4:$5') : '-',
            end_at: a.end_at ? a.end_at.replace(/^(\d{4})-(\d{2})-(\d{2})[T ](\d{2}):(\d{2}).*$/, '$3.$2.$1, $4:$5') : '-',
            created_at: new Date(a.created_at).toLocaleDateString('ru-RU'),
            raw_start_at: a.start_at,
            raw_end_at: a.end_at,
            min_price: a.min_price,
            timezone: a.timezone,
            min_step: a.min_step,
            step_time: a.step_time,
            description: a.description,
            bar_count: a.bar_count,
            bar_weight: a.bar_weight,
            gpb_minutes: a.gpb_minutes,
            gpb_started_at: a.gpb_started_at,
            raw_end_at: a.end_at,
            auction_participants_count: a.auction_participants_count || 0,
            participants: a.participants || [],
        }))

        if (reset) {
            auctions.value = mappedData
        } else {
            auctions.value.push(...mappedData)
        }
        
        if (response.data.next_page_url) {
            page.value++
            hasMore.value = true
        } else {
            hasMore.value = false
        }

    } catch (e) {
        console.error('Failed to load auctions:', e)
    } finally {
        isLoading.value = false
        loadingType.value = 'initial'
    }
}

const resetList = () => {
    loadMore(true)
}

const sortBy = (key) => {
    if (sortKey.value === key) {
        sortOrder.value = sortOrder.value === 'asc' ? 'desc' : 'asc'
    } else {
        sortKey.value = key
        sortOrder.value = 'asc'
    }
    resetList()
}



const saveAuction = async () => {
    errors.value = {}
    let isValid = true

    // Field Verification
    if (!newAuction.value.title?.trim()) {
        errors.value.title = 'Название обязательно'
        isValid = false
    }

    // Check Min Price > 0
    if (newAuction.value.min_price === '' || newAuction.value.min_price === null || newAuction.value.min_price <= 0) {
        errors.value.min_price = 'Значение должно быть > 0'
        isValid = false
    }

    if (newAuction.value.min_step === '' || newAuction.value.min_step === null || newAuction.value.min_step <= 0) {
        errors.value.min_step = 'Значение должно быть > 0'
        isValid = false
    }

    if (!newAuction.value.bar_count || newAuction.value.bar_count < 1) {
        errors.value.bar_count = 'Минимум 1'
        isValid = false
    }

    if (!newAuction.value.bar_weight || newAuction.value.bar_weight <= 0) {
        errors.value.bar_weight = 'Значение должно быть > 0'
        isValid = false
    }

    if (!newAuction.value.step_time || newAuction.value.step_time < 1 || newAuction.value.step_time > 1440) {
        errors.value.step_time = 'От 1 до 1440 мин'
        isValid = false
    }

    if (!newAuction.value.timezone) {
        errors.value.timezone = 'Выберите часовой пояс'
        isValid = false
    }

    // Date/Time Verification
    if (!newAuction.value.start_date) {
        errors.value.start_at = 'Укажите дату начала'
        isValid = false
    }
    
    if (!newAuction.value.end_date) {
        errors.value.end_at = 'Укажите дату окончания'
        isValid = false
    }

    if (!isValid) {
        activeTab.value = 'general' // Auto-switch to main tab to show errors
        return
    }

    // Combine date and time into ISO strings
    let startAt = null
    let endAt = null
    
    // Always require full datetime for start and end if dates are provided
    const startTime = newAuction.value.start_time || '00:00'
    startAt = `${newAuction.value.start_date}T${startTime}:00`
    
    const endTime = newAuction.value.end_time || '23:59'
    endAt = `${newAuction.value.end_date}T${endTime}:00`

    // Validate that end is not before start and minimum 15 minutes
    const startDate = new Date(startAt)
    const endDate = new Date(endAt)
    const diffMinutes = (endDate - startDate) / (1000 * 60)
    
    if (endDate <= startDate) {
        errors.value.end_at = 'Окончание должно быть позже начала'
        isValid = false
    } else if (diffMinutes < 15) {
        errors.value.end_at = 'Минимальная длительность — 15 минут'
        isValid = false
    }

    if (!isValid) {
        activeTab.value = 'general' // Auto-switch to main tab to show date errors
        return
    }

    try {
        const payload = {
            title: newAuction.value.title,
            status: newAuction.value.status,
            min_step: newAuction.value.min_step,
            step_time: newAuction.value.step_time,
            timezone: newAuction.value.timezone,
            min_price: newAuction.value.min_price,
            description: newAuction.value.description || null,
            bar_count: newAuction.value.bar_count,
            bar_weight: newAuction.value.bar_weight,
            gpb_minutes: newAuction.value.gpb_minutes,
            start_at: startAt,
            end_at: endAt,
            participant_ids: inviteAll.value ? [] : selectedParticipantIds.value,
        }

        if (isEditing.value) {
            await axios.put(`/api/auctions/${editingId.value}`, payload)
        } else {
            await axios.post('/api/auctions', payload)
        }
        closeModal(true) // Force close on success
        resetList()
    } catch (e) {
        if (e.response && e.response.status === 422) {
            const backendErrors = e.response.data.errors
            if (backendErrors.title) errors.value.title = backendErrors.title[0]
            if (backendErrors.start_at) errors.value.start_at = backendErrors.start_at[0]
            if (backendErrors.end_at) errors.value.end_at = backendErrors.end_at[0]
            if (backendErrors.status) errors.value.status = backendErrors.status[0]
            if (backendErrors.participant_ids) alert('Ошибка участников: ' + backendErrors.participant_ids[0])
            // Add other backend errors mapping if needed
        } else {
            console.error('Save failed:', e)
            alert('Ошибка сохранения: ' + (e.response?.data?.message || e.message))
        }
    }
}

const initiateDelete = (id) => {
    deleteId.value = id
    selectedAuction.value = auctions.value.find(a => a.id === id)
    showConfirmModal.value = true
}

const confirmDelete = async () => {
    if (deleteId.value) {
        try {
            await axios.delete(`/api/auctions/${deleteId.value}`)
            resetList()
        } catch (e) {
            console.error('Delete failed:', e)
        }
    }
    deleteId.value = null
    selectedAuction.value = null
    showConfirmModal.value = false
}

// --- Interaction ---
// --- Helper for State Snapshot ---
const getAuctionState = () => {
    return {
        ...newAuction.value,
        participantIds: [...selectedParticipantIds.value].sort()
    }
}

const openModal = async (auction = null) => {
    isEditing.value = !!auction
    if (auction) {
        const startParsed = parseDateTimeFromISO(auction.raw_start_at)
        const endParsed = parseDateTimeFromISO(auction.raw_end_at)

        newAuction.value = { 
            title: auction.title, 
            status: auction.status,
            min_step: auction.min_step || 10000,
            step_time: auction.step_time || 5,
            timezone: auction.timezone || 'Europe/Moscow',
            min_price: auction.min_price || 0,
            description: auction.description || '',
            bar_count: auction.bar_count,
            bar_weight: auction.bar_weight,
            start_date: startParsed.date,
            start_time: startParsed.time || '10:00',
            end_date: endParsed.date,
            end_time: endParsed.time || '18:00',
        }
        // Clear old data so computed uses cache values instantly
        initialOffers.value = []
        auctionBids.value = []
        offersCountCache.value = auction.initial_offers_count || 0
        bidsCountCache.value = auction.bids_count || 0
        // Load participants
        if (auction.participants && auction.participants.length > 0) {
            selectParticipants.value = true
            selectedParticipantIds.value = auction.participants.map(p => p.id)
            inviteAll.value = false
        } else {
            selectParticipants.value = false
            selectedParticipantIds.value = []
            inviteAll.value = true
        }
        participantSearch.value = ''
        activeTab.value = 'general'
        // Load offers and bids on modal open
        fetchInitialOffers()
        fetchBids()
    } else {
        // Default values for new auction
        const tomorrow = new Date()
        tomorrow.setDate(tomorrow.getDate() + 1)
        const dateString = tomorrow.toISOString().split('T')[0]

        newAuction.value = {
            title: '',
            status: 'draft',
            description: '',
            start_date: dateString,
            start_time: '10:00',
            end_date: dateString,
            end_time: '13:00',
            timezone: 'Europe/Moscow',
            min_price: 0,
            min_step: 10000,
            step_time: 5,
            bar_count: 1,
            bar_weight: 12.4,
            gpb_minutes: 30,
            is_active: true
        }
        selectedParticipantIds.value = []
        selectParticipants.value = false
        participantSearch.value = ''
        inviteAll.value = true
        activeTab.value = 'general'
        // initialOffers.value = [] // Reset offers (assuming initialOffers is defined elsewhere)
    }
    
    // Set Initial State
    initialAuctionState.value = JSON.stringify(getAuctionState())
    
    errors.value = {}
    showModal.value = true
    fetchParticipants()
    // Start countdown timer for active/gpb auctions
    if (newAuction.value.status === 'active' || newAuction.value.status === 'gpb_right') {
        startCountdown()
    }
    await nextTick()
    if (titleInputRef.value) titleInputRef.value.focus()
}

// Auto-shift End Date if Start Date > End Date
watch(() => newAuction.value.start_date, (newStart, oldStart) => {
    if (newStart && newAuction.value.end_date) {
        if (newStart > newAuction.value.end_date) {
            newAuction.value.end_date = newStart
        }
    }
})

const parseDateTimeFromISO = (isoString) => {
    if (!isoString) return { date: '', time: '' }
    // Parse directly from string to avoid timezone conversion
    // Expected formats: "2026-02-16T03:00:00.000000Z" or "2026-02-16T03:00:00"
    const match = isoString.match(/^(\d{4}-\d{2}-\d{2})[T ](\d{2}:\d{2})/)
    if (match) {
        return { date: match[1], time: match[2] }
    }
    // Fallback: try to parse date only
    const dateMatch = isoString.match(/^(\d{4}-\d{2}-\d{2})/)
    return { date: dateMatch ? dateMatch[1] : '', time: '' }
}

const openEdit = async (auction) => {
    isEditing.value = true
    editingId.value = auction.id
    
    const startParsed = parseDateTimeFromISO(auction.raw_start_at)
    const endParsed = parseDateTimeFromISO(auction.raw_end_at)
    
    newAuction.value = { 
        title: auction.title, 
        status: auction.status,
        min_step: auction.min_step || 10000,
        step_time: auction.step_time || 5,
        timezone: auction.timezone || 'Europe/Moscow',
        min_price: auction.min_price || 0,
        description: auction.description || '',
        bar_count: auction.bar_count,
        bar_weight: auction.bar_weight,
        gpb_minutes: auction.gpb_minutes ?? 30,
        start_date: startParsed.date,
        start_time: startParsed.time || '10:00',
        end_date: endParsed.date,
        end_time: endParsed.time || '18:00',
    }
    // Clear old data so computed uses cache values instantly
    initialOffers.value = []
    auctionBids.value = []
    offersCountCache.value = auction.initial_offers_count || 0
    bidsCountCache.value = auction.bids_count || 0
    // Load participants
    if (auction.participants && auction.participants.length > 0) {
        selectParticipants.value = true
        selectedParticipantIds.value = auction.participants.map(p => p.id)
        inviteAll.value = false
    } else {
        selectParticipants.value = false
        selectedParticipantIds.value = []
        inviteAll.value = true
    }
    participantSearch.value = ''
    activeTab.value = 'general'
    // Load offers and bids on modal open
    fetchInitialOffers()
    fetchBids()
    // Subscribe to real-time updates via WebSocket
    echo.channel(`auction.${auction.id}`)
        .listen('.bid.placed', () => {
            fetchBids(true)
        })
        .listen('.offer.placed', () => {
            fetchInitialOffers(true)
        })
    // Start fallback polling if WebSocket is not connected
    if (!isConnected.value) {
        startFallbackPolling()
    }
    
    // Set Initial State
    initialAuctionState.value = JSON.stringify(getAuctionState())
    
    errors.value = {}
    showModal.value = true
    // Start countdown timer for active/gpb auctions
    if (newAuction.value.status === 'active' || newAuction.value.status === 'gpb_right') {
        startCountdown()
    }
    await fetchParticipants()
    await nextTick()
    if (titleInputRef.value) titleInputRef.value.focus()
}

const closeModal = (force = false) => {
    if (typeof force !== 'boolean') force = false

    if (!force && showModal.value && JSON.stringify(getAuctionState()) !== initialAuctionState.value) {
        showUnsavedModal.value = true
        return
    }

    stopCountdown()
    stopFallbackPolling()
    // Unsubscribe from real-time channel
    if (editingId.value) {
        echo.leaveChannel(`auction.${editingId.value}`)
    }
    showModal.value = false
    showConfirmModal.value = false
    showUnsavedModal.value = false
    isEditing.value = false
    editingId.value = null
    selectedAuction.value = null
    stopCountdown()
}

const router = useRouter()
const pendingRoute = ref(null)

const confirmDiscardChanges = () => {
    showUnsavedModal.value = false
    showModal.value = false
    showConfirmModal.value = false
    isEditing.value = false
    editingId.value = null
    selectedAuction.value = null
    
    if (pendingRoute.value) {
        router.push(pendingRoute.value)
        pendingRoute.value = null
    }
}

// Intersection Observer
const observerTarget = ref(null)
const searchInputRef = ref(null)

const clearSearchByEsc = (e) => {
    searchQuery.value = ''
    resetList()
    e.preventDefault()
    if (document.activeElement === searchInputRef.value) {
        searchInputRef.value.blur()
    }
}

const handleGlobalKeydown = async (e) => {
    // If not in modal, normal behavior
    if (!showModal.value && !showConfirmModal.value && !isEditing.value) {
        if (e.key === 'Escape') {
            if (searchQuery.value) {
                clearSearchByEsc(e)
                return
            }
        }
        if (e.key === 'Insert') {
            openModal()
            e.preventDefault()
            return
        }
        if (e.ctrlKey || e.metaKey || e.altKey || e.shiftKey) return
        if (['INPUT', 'TEXTAREA', 'SELECT'].includes(document.activeElement.tagName)) return
        
        if (e.key.length === 1) {
            if (searchInputRef.value && document.activeElement !== searchInputRef.value) {
                searchInputRef.value.focus()
            }
        }
        return
    }

    // Modal is open
    if (showModal.value) {
        // Esc handling for unsaved changes trap
        if (e.key === 'Escape') {
            // If invite modal is open, let StandardModal handle it (closeInviteModal)
            if (showInviteModal.value) return 

            // If unsaved modal is open, let it handle close
            if (showUnsavedModal.value) return

            // If changes exist, manual trigger close to show unsaved modal
            if (JSON.stringify(getAuctionState()) !== initialAuctionState.value) {
                e.preventDefault()
                e.stopPropagation()
                showUnsavedModal.value = true
                return
            }
            // If no changes, standard modal behavior closes it
            return 
        }

        // Auto-focus participant search when typing on participants tab
        if (activeTab.value === 'participants' && e.key.length === 1 && !e.ctrlKey && !e.metaKey && !e.altKey) {
            if (['INPUT', 'TEXTAREA', 'SELECT'].includes(document.activeElement.tagName)) return
            
            if (participantSearchRef.value) {
                participantSearchRef.value.focus()
                
                // If the key event happens on body, relying on focus() to catch it in input might be racey depending on browser.
                // Best practice: focus() then let the event bubble?
                // Or prevent default and append manually?
                // The most robust way for "start typing" is often:
                // e.preventDefault()
                // input.value += char
                // But Vue v-model needs update.
                
                // Let's try NOT preventing default first, usually focus() is enough if event propagates.
                // If we preventDefault, we lose the char.
                // So removing preventDefault.
            }
        }
    }
}

const handleEnterKey = (e) => {
    if (e.key !== 'Enter') return
    
    // Prioritize top-most modals
    if (showUnsavedModal.value) {
        confirmDiscardChanges()
        e.preventDefault()
        return
    }
    
    if (showInviteModal.value) {
        confirmSendInvitations()
        e.preventDefault()
        return
    }

    if (showConfirmModal.value) {
        confirmDelete()
        e.preventDefault()
    } else if (showModal.value) {
        // Prevent saving if focused in textarea to allow newlines (if desired? usually Ctrl+Enter to submit in textareas, simply Enter for inputs)
        // But here we just call saveAuction if not in textarea
        if (document.activeElement.tagName === 'TEXTAREA') return
        saveAuction()
        e.preventDefault()
    }
}

watch([searchQuery, filterStatus], () => {
    resetList()
})

onMounted(() => {
    const route = useRoute()

    // Handle Search/Filter from Query
    if (route.query.search) {
        searchQuery.value = route.query.search
    }
    
    if (route.query.status) {
        filterStatus.value = route.query.status
    }
    
    resetList()
    fetchParticipants()
    
    // Handle Actions
    if (route.query.action === 'create') {
        openModal()
    }

    if (route.query.sort === 'newest') {
        sortBy('created_at') // effective descending
    }

    window.addEventListener('keydown', handleGlobalKeydown)
    window.addEventListener('keydown', handleEnterKey)
    
    const observer = new IntersectionObserver((entries) => {
        if (entries[0].isIntersecting && !isLoading.value && hasMore.value) {
            loadMore()
        }
    }, { threshold: 0.1 })
    
    if (observerTarget.value) {
        observer.observe(observerTarget.value)
    }
    
    watch(observerTarget, (el) => {
        if (el) observer.observe(el)
    })
})

onUnmounted(() => {
    window.removeEventListener('keydown', handleGlobalKeydown)
    window.removeEventListener('keydown', handleEnterKey)
    stopCountdown()
    stopFallbackPolling()
})

onBeforeRouteLeave((to, from, next) => {
    if (showModal.value && JSON.stringify(getAuctionState()) !== initialAuctionState.value) {
        showUnsavedModal.value = true
        pendingRoute.value = to
        next(false)
    } else {
        next()
    }
})
</script>

<template>
  <div class="bg-dark-800/80 backdrop-blur-sm rounded-xl border border-white/5 overflow-hidden shadow-xl h-[calc(100vh-9rem)] flex flex-col">
      <div class="p-6 border-b border-white/5 flex flex-col md:flex-row gap-4 justify-between items-center bg-dark-900/40">
          <div class="flex flex-col sm:flex-row gap-4 flex-1 w-full md:w-auto">
              <div class="relative w-full sm:w-auto flex-1 min-w-[200px]">
                    <!-- Search Bar -->
                    <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                    <input 
                        ref="searchInputRef"
                        v-model="searchQuery"
                        type="text" 
                        placeholder="Поиск по таблице..." 
                        class="w-full bg-dark-900 border border-white/10 rounded-lg pl-10 pr-4 py-2 text-sm text-white focus:border-red-500 focus:outline-none focus:ring-1 focus:ring-red-500 transition-all placeholder-white/20" 
                        @keydown.esc="clearSearchByEsc"
                    />
              </div>

              <!-- Status Filter -->
              <div class="relative w-full sm:w-56 flex-shrink-0">
                  <select 
                      v-model="filterStatus"
                      class="no-arrow w-full bg-dark-900 border border-white/10 rounded-lg pl-4 pr-10 py-2 text-sm text-white focus:border-red-500 focus:outline-none focus:ring-1 focus:ring-red-500 transition-all cursor-pointer hover:border-white/20"
                  >
                      <option value="">Все статусы</option>
                      <option v-for="option in statusOptions" :key="option.value" :value="option.value">
                          {{ option.label }}
                      </option>
                  </select>
                  <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-3 text-gray-500">
                      <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                      </svg>
                  </div>
              </div>
          </div>

          <button 
            @click="openModal()"
            class="flex items-center justify-center gap-2 px-4 py-2 bg-transparent hover:bg-red-500/10 text-red-400 hover:text-red-300 rounded-lg transition-colors font-bold text-sm border border-red-500/50 hover:border-red-400 w-full md:w-auto"
          >
              <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
              </svg>
              Добавить
          </button>
      </div>

      <div class="overflow-x-auto relative flex-1 flex flex-col overflow-y-scroll scrollbar-none">
          <table class="w-full text-left text-sm text-gray-400 table-fixed">
              <thead class="bg-dark-900 text-xs uppercase font-bold text-white tracking-wider sticky top-0 z-20 shadow-md border-b border-white/5">
                  <tr>
                      <th class="px-6 py-4 cursor-pointer hover:text-red-400 transition-colors group select-none w-16" @click="sortBy('id')">
                          <div class="flex items-center gap-2">
                              №
                              <svg v-if="sortKey === 'id'" class="w-4 h-4 transition-transform flex-shrink-0" :class="sortOrder === 'asc' ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                              <svg v-else class="w-4 h-4 opacity-0 group-hover:opacity-50 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                          </div>
                      </th>
                      <th class="px-6 py-4 cursor-pointer hover:text-red-400 transition-colors group select-none" @click="sortBy('title')">
                          <div class="flex items-center gap-2">
                              Название
                              <svg v-if="sortKey === 'title'" class="w-4 h-4 transition-transform flex-shrink-0" :class="sortOrder === 'asc' ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                              <svg v-else class="w-4 h-4 opacity-0 group-hover:opacity-50 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                          </div>
                      </th>
                      <th class="px-6 py-4 cursor-pointer hover:text-red-400 transition-colors group select-none w-1/6" @click="sortBy('status')">
                          <div class="flex items-center gap-2">
                              Статус
                              <svg v-if="sortKey === 'status'" class="w-4 h-4 transition-transform flex-shrink-0" :class="sortOrder === 'asc' ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                              <svg v-else class="w-4 h-4 opacity-0 group-hover:opacity-50 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                          </div>
                      </th>
                      <th class="px-6 py-4 cursor-pointer hover:text-red-400 transition-colors group select-none w-1/6" @click="sortBy('start_at')">
                          <div class="flex items-center gap-2">
                              Начало
                              <svg v-if="sortKey === 'start_at'" class="w-4 h-4 transition-transform flex-shrink-0" :class="sortOrder === 'asc' ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                              <svg v-else class="w-4 h-4 opacity-0 group-hover:opacity-50 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                          </div>
                      </th>
                      <th class="px-6 py-4 cursor-pointer hover:text-red-400 transition-colors group select-none w-1/6" @click="sortBy('end_at')">
                          <div class="flex items-center gap-2">
                              Окончание
                              <svg v-if="sortKey === 'end_at'" class="w-4 h-4 transition-transform flex-shrink-0" :class="sortOrder === 'asc' ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                              <svg v-else class="w-4 h-4 opacity-0 group-hover:opacity-50 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                          </div>
                      </th>
                      <th class="px-6 py-4 text-right w-32">Действия</th>
                  </tr>
              </thead>
              <tbody class="transition-opacity duration-300" :class="{ 'opacity-50': isLoading && loadingType === 'search' }">
                  <tr v-for="auction in auctions" :key="auction.id" class="border-b border-white/5 hover:bg-white/5 transition-colors group">
                      <td class="px-6 py-4 font-mono text-xs text-gray-500">{{ auction.id }}</td>
                      <td class="px-6 py-4">
                          <div class="flex flex-col">
                              <span class="font-bold text-white text-base">{{ auction.title }}</span>
                              <div class="flex items-center gap-3 mt-0.5">
                                  <span class="text-xs text-gray-500 font-mono"><span class="font-sans">₽</span>&nbsp;{{ auction.min_price ? Number(auction.min_price).toLocaleString('ru-RU') : '—' }}</span>
                                  <span v-if="auction.auction_participants_count > 0" class="text-xs text-gray-500">
                                      <svg class="w-3 h-3 inline mr-0.5 -mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" /></svg>
                                      {{ auction.auction_participants_count }}
                                  </span>
                              </div>
                          </div>
                      </td>
                      <td class="px-6 py-4">
                          <span class="px-2 py-1 rounded text-xs font-bold uppercase" :class="getStatusClass(auction.status)">
                              {{ getStatusLabel(auction.status) }}
                          </span>
                      </td>
                      <td class="px-6 py-4 font-mono text-xs">{{ auction.start_at }}</td>
                      <td class="px-6 py-4 font-mono text-xs">{{ auction.end_at }}</td>
                      <td class="px-6 py-4 text-right flex items-center justify-end gap-1">

                          <button 
                            @click="router.push(`/admin/auctions/${auction.id}`)"
                            class="text-gray-500 hover:text-blue-400 transition-colors p-2 rounded-lg hover:bg-blue-500/10"
                            title="Открыть"
                          >
                              <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                              </svg>
                          </button>
                          <button 
                            @click="initiateDelete(auction.id)"
                            class="text-gray-500 hover:text-red-500 transition-colors p-2 rounded-lg hover:bg-red-500/10"
                            title="Удалить"
                          >
                              <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                              </svg>
                          </button>
                      </td>
                  </tr>
                  <!-- Skeletons -->
                  <tr v-if="isLoading && (loadingType === 'initial' || loadingType === 'scroll')" v-for="i in 5" :key="`skeleton-${i}`" class="animate-pulse">
                      <td class="px-6 py-4">
                          <div class="flex flex-col gap-2">
                              <div class="h-4 w-48 bg-white/10 rounded"></div>
                              <div class="h-3 w-24 bg-white/10 rounded"></div>
                          </div>
                      </td>
                      <td class="px-6 py-4">
                          <div class="h-6 w-20 bg-white/10 rounded"></div>
                      </td>
                      <td class="px-6 py-4">
                          <div class="h-4 w-28 bg-white/10 rounded"></div>
                      </td>
                      <td class="px-6 py-4">
                          <div class="h-4 w-28 bg-white/10 rounded"></div>
                      </td>
                      <td class="px-6 py-4">
                          <div class="flex justify-end gap-2">
                              <div class="w-8 h-8 rounded bg-white/10"></div>
                              <div class="w-8 h-8 rounded bg-white/10"></div>
                          </div>
                      </td>
                  </tr>
              </tbody>
          </table>
          
          <!-- No Results Message -->
          <div v-if="!isLoading && auctions.length === 0" class="flex flex-col items-center justify-center py-12 text-gray-500 animate-in fade-in duration-500">
               <svg class="w-12 h-12 mb-3 text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                   <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
               </svg>
               <p class="text-sm font-medium">Ничего не найдено</p>
               <p class="text-xs mt-1">Попробуйте изменить параметры поиска</p>
          </div>

          
          <!-- Infinite Scroll Trigger -->
          <div ref="observerTarget" class="py-4 text-center text-gray-500 text-xs uppercase tracking-widest font-bold h-4">
          </div>
      </div>

      <!-- Delete Confirmation Modal -->
      <StandardModal 
          :is-open="showConfirmModal" 
          theme="red"
          z-index-class="z-[200]"
          backdrop-z-index-class="z-[150]"
          :backdrop-blur="false"
          @close="showConfirmModal = false"
      >
          <div class="text-center pt-2">
              <h3 class="text-xl font-kanit font-bold text-white tracking-wide uppercase mb-6">Удалить<span class="text-2xl">?</span></h3>
              <p class="text-gray-400 text-sm mb-2 font-light">
                  Аукцион
              </p>
              <p class="text-white font-bold text-lg mb-2">
                  {{ selectedAuction?.title }}
              </p>
              <p class="text-red-400 text-sm font-semibold mb-6 tracking-wide">
                  будет удалён безвозвратно
              </p>
              
              <div class="flex gap-3">
                  <button @click="showConfirmModal = false" class="flex-1 py-3 rounded-lg text-xs font-bold uppercase tracking-widest text-gray-400 hover:text-white bg-white/5 hover:bg-white/10 transition-colors border border-transparent hover:border-white/10">
                      Отмена
                  </button>
                  <button @click="confirmDelete" class="flex-1 py-3 bg-red-600 hover:bg-red-500 text-white rounded-lg font-bold text-xs uppercase tracking-widest shadow-lg shadow-red-500/20 transition-all transform active:scale-95 border border-red-500/50">
                      Удалить
                  </button>
              </div>
          </div>
      </StandardModal>
  </div>
</template>

<style scoped>
.no-arrow {
    -webkit-appearance: none;
    -moz-appearance: none;
    appearance: none;
    background-image: none;
}
.no-arrow::-ms-expand {
    display: none;
}
</style>
