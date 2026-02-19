<script setup>
import { ref, computed, watch, onMounted, onUnmounted, nextTick } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import StandardModal from '../../components/ui/StandardModal.vue'
import axios from 'axios'
import echo from '@/echo.js'
import { useConnectionStatus } from '@/composables/useConnectionStatus.js'

const route = useRoute()
const router = useRouter()
const { isConnected, statusText } = useConnectionStatus()

const auctionId = computed(() => route.params.id)

// --- Core State ---
const auction = ref(null)
const isLoadingPage = ref(true)
const activeTab = ref('offers')
const errors = ref({})
const isSaving = ref(false)

// --- Edit Modal ---
const showEditModal = ref(false)
const editForm = ref({})
const initialEditState = ref(null)
const showUnsavedModal = ref(false)

// --- Participants ---
const allParticipants = ref([])
const selectedParticipantIds = ref([])
const participantSearch = ref('')
const inviteAll = ref(true)
const isSendingInvitations = ref(false)
const showInviteModal = ref(false)
const participantSearchRef = ref(null)
const editActiveTab = ref('general')

// --- Offers ---
const initialOffers = ref([])
const isLoadingOffers = ref(false)
const offersDisplayCount = computed(() => initialOffers.value.length)

const fetchInitialOffers = async (silent = false) => {
    if (!auctionId.value) return
    if (!silent) isLoadingOffers.value = true
    if (!silent) await new Promise(r => setTimeout(r, 500))
    try {
        const r = await axios.get(`/api/auctions/${auctionId.value}/initial-offers`)
        initialOffers.value = r.data
    } catch (e) { console.error('Failed to load offers:', e) }
    finally { isLoadingOffers.value = false }
}

// --- Bids ---
const auctionBids = ref([])
const isLoadingBids = ref(false)
const bidsDisplayCount = computed(() => auctionBids.value.length)

const fetchBids = async (silent = false) => {
    if (!auctionId.value) return
    if (!silent) isLoadingBids.value = true
    if (!silent) await new Promise(r => setTimeout(r, 500))
    try {
        const r = await axios.get(`/api/auctions/${auctionId.value}/bids`)
        auctionBids.value = r.data.bids || []
    } catch (e) { console.error('Failed to load bids:', e) }
    finally { isLoadingBids.value = false }
}

// --- Fallback polling ---
let fallbackInterval = null
const startFallbackPolling = () => {
    stopFallbackPolling()
    fallbackInterval = setInterval(() => {
        if (!auction.value) return
        const status = auction.value.status
        if (status === 'collecting_offers') fetchInitialOffers(true)
        if (['active', 'gpb_right'].includes(status)) fetchBids(true)
    }, 5000)
}
const stopFallbackPolling = () => {
    if (fallbackInterval) { clearInterval(fallbackInterval); fallbackInterval = null }
}

watch(isConnected, (connected) => {
    if (connected) stopFallbackPolling()
    else if (auction.value) startFallbackPolling()
})

// --- Allocated Bids ---
const allocatedBids = computed(() => {
    const totalBars = auction.value?.bar_count || 0
    const barWeight = auction.value?.bar_weight || 0
    const sorted = [...auctionBids.value].sort((a, b) => {
        if (Number(b.amount) !== Number(a.amount)) return Number(b.amount) - Number(a.amount)
        return new Date(a.created_at) - new Date(b.created_at)
    })
    let remaining = totalBars
    const winning = [], losing = []
    for (const bid of sorted) {
        if (remaining <= 0) { losing.push({ ...bid, fulfilled: 0, partial: false }) }
        else if (bid.bar_count <= remaining) { winning.push({ ...bid, fulfilled: bid.bar_count, partial: false }); remaining -= bid.bar_count }
        else {
            winning.push({ ...bid, fulfilled: remaining, partial: true })
            losing.push({ ...bid, fulfilled: 0, partial: false, bar_count: bid.bar_count - remaining, isRemainder: true })
            remaining = 0
        }
    }
    // Updated calculation: Price is per Gram. Weight is Kg -> * 1000
    const lotTotal = winning.reduce((s, b) => s + b.fulfilled * (barWeight * 1000) * Number(b.amount), 0)
    const lotBars = winning.reduce((s, b) => s + b.fulfilled, 0)
    return { winning, losing, lotTotal, lotBars, lotWeight: lotBars * barWeight, totalBars, barWeight }
})

const hasBasisBids = computed(() => auctionBids.value.some(b => b.user?.delivery_basis != null && Number(b.user.delivery_basis) > 0))

// --- GPB Allocated Bids ---
const gpbAllocatedBids = computed(() => {
    const totalBars = auction.value?.bar_count || 0
    const barWeight = auction.value?.bar_weight || 0
    const gpbBids = auctionBids.value.filter(b => b.user?.is_gpb)
    const regularBids = auctionBids.value.filter(b => !b.user?.is_gpb)
    const gpbWantBars = gpbBids.reduce((s, b) => s + (b.bar_count || 0), 0)
    const regularAsc = [...regularBids].sort((a, b) => {
        if (Number(a.amount) !== Number(b.amount)) return Number(a.amount) - Number(b.amount)
        return new Date(a.created_at) - new Date(b.created_at)
    })
    let gpbRemaining = Math.min(gpbWantBars, totalBars)
    const gpbBought = []
    const bidGpbUsed = new Map()
    for (const bid of regularAsc) {
        if (gpbRemaining <= 0) break
        const take = Math.min(bid.bar_count, gpbRemaining)
        gpbBought.push({ ...bid, fulfilled: take, originalBars: bid.bar_count, pricePerGram: Number(bid.amount) })
        bidGpbUsed.set(bid.id, (bidGpbUsed.get(bid.id) || 0) + take)
        gpbRemaining -= take
    }
    const gpbTotalBars = gpbBought.reduce((s, b) => s + b.fulfilled, 0)
    // Updated calculation: Price is per Gram
    const gpbTotal = gpbBought.reduce((s, b) => s + b.fulfilled * (barWeight * 1000) * b.pricePerGram, 0)
    const gpbUser = gpbBids.length > 0 ? gpbBids[0].user : null
    const regularDesc = [...regularBids].sort((a, b) => {
        if (Number(b.amount) !== Number(a.amount)) return Number(b.amount) - Number(a.amount)
        return new Date(a.created_at) - new Date(b.created_at)
    })
    let pRemaining = totalBars - gpbTotalBars
    const participantWinning = [], participantLosing = []
    for (const bid of regularDesc) {
        const gpbUsed = bidGpbUsed.get(bid.id) || 0
        const avail = bid.bar_count - gpbUsed
        if (avail <= 0) { participantLosing.push({ ...bid, fulfilled: 0, bar_count: bid.bar_count, partial: false, lostToGpb: true }); continue }
        if (pRemaining <= 0) { participantLosing.push({ ...bid, fulfilled: 0, bar_count: avail, partial: false }) }
        else if (avail <= pRemaining) { participantWinning.push({ ...bid, fulfilled: avail, bar_count: avail, partial: false }); pRemaining -= avail }
        else {
            participantWinning.push({ ...bid, fulfilled: pRemaining, bar_count: avail, partial: true })
            participantLosing.push({ ...bid, fulfilled: 0, bar_count: avail - pRemaining, partial: false, isRemainder: true })
            pRemaining = 0
        }
    }
    // Updated calculation: Price is per Gram
    const participantTotal = participantWinning.reduce((s, b) => s + b.fulfilled * (barWeight * 1000) * Number(b.amount), 0)
    const participantBars = participantWinning.reduce((s, b) => s + b.fulfilled, 0)
    return {
        gpbBids, gpbUser, gpbWantBars, gpbBought, gpbTotalBars, gpbTotal,
        participantWinning, participantLosing, participantTotal, participantBars,
        grandTotal: gpbTotal + participantTotal, totalBars, barWeight, hasGpbBids: gpbBids.length > 0,
    }
})

// --- Countdown Timer ---
const timerNow = ref(Date.now())
let timerInterval = null

const tradingCountdownSeconds = computed(() => {
    if (!auction.value?.end_at) return -1
    const endMs = new Date(auction.value.end_at.replace(' ', 'T')).getTime()
    const diff = Math.floor((endMs - timerNow.value) / 1000)
    return diff > 0 ? diff : 0
})

const tradingCountdownFormatted = computed(() => {
    const s = tradingCountdownSeconds.value
    if (s < 0) return null
    const h = Math.floor(s / 3600), m = Math.floor((s % 3600) / 60), sec = s % 60
    if (h > 0) return `${h}ч ${String(m).padStart(2, '0')}м ${String(sec).padStart(2, '0')}с`
    return `${String(m).padStart(2, '0')}:${String(sec).padStart(2, '0')}`
})

const tradingFinished = computed(() => ['gpb_right', 'commission', 'completed', 'cancelled'].includes(auction.value?.status))

const gpbCountdownSeconds = computed(() => {
    if (!auction.value?.gpb_started_at) return -1
    const gpbMins = auction.value.gpb_minutes || 30
    const endMs = new Date(auction.value.gpb_started_at.replace(' ', 'T')).getTime() + gpbMins * 60 * 1000
    const diff = Math.floor((endMs - timerNow.value) / 1000)
    return diff > 0 ? diff : 0
})

const gpbCountdownFormatted = computed(() => {
    const s = gpbCountdownSeconds.value
    if (s < 0) return null
    const h = Math.floor(s / 3600), m = Math.floor((s % 3600) / 60), sec = s % 60
    if (h > 0) return `${h}ч ${String(m).padStart(2, '0')}м ${String(sec).padStart(2, '0')}с`
    return `${String(m).padStart(2, '0')}:${String(sec).padStart(2, '0')}`
})

const gpbFinished = computed(() => ['commission', 'completed', 'cancelled'].includes(auction.value?.status))

const scheduledCountdownSeconds = computed(() => {
    if (!auction.value?.start_at) return -1
    const startMs = new Date(auction.value.start_at.replace(' ', 'T')).getTime()
    const diff = Math.floor((startMs - timerNow.value) / 1000)
    return diff > 0 ? diff : 0
})

const scheduledCountdownFormatted = computed(() => {
    const s = scheduledCountdownSeconds.value
    if (s < 0) return null
    const d = Math.floor(s / 86400)
    const h = Math.floor((s % 86400) / 3600)
    const m = Math.floor((s % 3600) / 60)
    const sec = s % 60

    const parts = []
    if (d > 0) parts.push(`${d}д`)
    if (d > 0 || h > 0) parts.push(`${h}ч`)   
    parts.push(`${String(m).padStart(2, '0')}м`)
    parts.push(`${String(sec).padStart(2, '0')}с`)
    
    return parts.join(' ')
})



const countdownSeconds = computed(() => {
    if (auction.value?.status === 'scheduled') return scheduledCountdownSeconds.value
    if (auction.value?.status === 'active') return tradingCountdownSeconds.value
    if (auction.value?.status === 'gpb_right') return gpbCountdownSeconds.value
    return -1
})

const startCountdown = () => {
    stopCountdown()
    timerNow.value = Date.now()
    timerInterval = setInterval(async () => {
        timerNow.value = Date.now()
        if (countdownSeconds.value === 0) {
            stopCountdown()
            if (auction.value?.status === 'active') {
                try {
                    const r = await axios.post(`/api/auctions/${auctionId.value}/transition-gpb`)
                    auction.value.status = 'gpb_right'
                    auction.value.gpb_started_at = r.data.gpb_started_at
                    startCountdown()
                } catch (e) { console.error('Failed to transition to GPB:', e) }
            }
            if (auction.value?.status === 'gpb_right') {
                try {
                    await axios.put(`/api/auctions/${auctionId.value}`, { status: 'commission' })
                    auction.value.status = 'commission'
                } catch (e) { console.error('Failed to transition to commission:', e) }
            }
        }
    }, 1000)
}

const stopCountdown = () => { if (timerInterval) { clearInterval(timerInterval); timerInterval = null } }

// --- Status/Timezone options ---
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

const getStatusClass = (status) => statusOptions.find(s => s.value === status)?.color || 'bg-gray-500/20 text-gray-400'
const getStatusLabel = (status) => statusOptions.find(s => s.value === status)?.label || status
const getStatusDotClass = (status) => {
    const map = { 'draft': 'bg-gray-500', 'collecting_offers': 'bg-cyan-500', 'scheduled': 'bg-purple-500', 'active': 'bg-amber-500', 'gpb_right': 'bg-blue-500', 'commission': 'bg-orange-500', 'completed': 'bg-emerald-400', 'paused': 'bg-amber-800', 'cancelled': 'bg-red-500' }
    return map[status] || 'bg-gray-400'
}

const getTimezoneLabel = (tz) => timezoneOptions.find(t => t.value === tz)?.label || tz

const blockedStatuses = computed(() => {
    const blocked = []
    const now = Date.now()
    if (auction.value?.end_at) {
        if (now >= new Date(auction.value.end_at).getTime()) blocked.push('collecting_offers', 'scheduled', 'active')
    }
    if (auction.value?.gpb_started_at) {
        const gpbMins = auction.value.gpb_minutes || 30
        if (now >= new Date(auction.value.gpb_started_at).getTime() + gpbMins * 60 * 1000) blocked.push('collecting_offers', 'scheduled', 'active', 'gpb_right')
    }
    return [...new Set(blocked)]
})

// --- Participants ---
const filteredParticipants = computed(() => {
    if (!participantSearch.value) return allParticipants.value
    const q = participantSearch.value.toLowerCase()
    return allParticipants.value.filter(p =>
        p.name?.toLowerCase().includes(q) || (p.inn && p.inn.includes(q)) || (p.phone && p.phone.includes(q)) || (p.email && p.email.toLowerCase().includes(q))
    )
})

const toggleParticipant = (id) => {
    const idx = selectedParticipantIds.value.indexOf(id)
    if (idx === -1) selectedParticipantIds.value.push(id)
    else selectedParticipantIds.value.splice(idx, 1)
}

const isLoadingParticipants = ref(false)
const fetchParticipants = async () => {
    isLoadingParticipants.value = true
    await new Promise(r => setTimeout(r, 500))
    try { allParticipants.value = (await axios.get('/api/participants-list')).data }
    catch (e) { console.error('Failed to load participants:', e) }
    finally { isLoadingParticipants.value = false }
}

const advanceStatus = () => {
    const order = ['draft', 'collecting_offers', 'scheduled', 'active', 'gpb_right', 'commission', 'completed']
    const idx = order.indexOf(editForm.value.status)
    if (idx === -1 || idx >= order.length - 1) return
    // Find next non-blocked status
    for (let i = idx + 1; i < order.length; i++) {
        if (!blockedStatuses.value.includes(order[i])) {
            editForm.value.status = order[i]
            return
        }
    }
}

// --- Fetch Auction ---
const parseDateTimeFromISO = (iso) => {
    if (!iso) return { date: '', time: '' }
    const m = iso.match(/^(\d{4}-\d{2}-\d{2})[T ](\d{2}:\d{2})/)
    if (m) return { date: m[1], time: m[2] }
    const d = iso.match(/^(\d{4}-\d{2}-\d{2})/)
    return { date: d ? d[1] : '', time: '' }
}

const formatDate = (iso) => {
    if (!iso) return '—'
    const d = new Date(iso)
    return d.toLocaleDateString('ru-RU') + ', ' + d.toLocaleTimeString('ru-RU', { hour: '2-digit', minute: '2-digit' })
}

const getDefaultTab = (status) => {
    const map = { draft: 'offers', collecting_offers: 'offers', scheduled: 'offers', active: 'trading', gpb_right: 'gpb', commission: 'results', completed: 'results', paused: 'trading', cancelled: 'results' }
    return map[status] || 'offers'
}

const fetchAuction = async () => {
    try {
        const r = await axios.get(`/api/auctions/${auctionId.value}`)
        const a = r.data
        auction.value = a
        // Initialize participant IDs from auction data
        if (a.participants) {
            selectedParticipantIds.value = a.participants.map(p => p.id)
        }
        inviteAll.value = a.invite_all ?? true
        if (!activeTab._initialized) {
            activeTab.value = getDefaultTab(a.status)
            activeTab._initialized = true
        }
    } catch (e) {
        if (e.response?.status === 404) { router.push('/admin/auctions'); return }
        console.error('Failed to load auction:', e)
    } finally {
        isLoadingPage.value = false
    }
}

const fetchAuctionData = async () => {
    isLoadingPage.value = true
    await new Promise(r => setTimeout(r, 500))
    try {
        const { data } = await axios.get(`/api/auctions/${auctionId.value}`)
        auction.value = data
    } catch (e) {
        if (e.response?.status === 404) { router.push('/admin/auctions'); return }
        console.error('Failed to load auction data:', e)
    } finally {
        isLoadingPage.value = false
    }
}

// --- Edit Modal ---
const openEditModal = () => {
    if (!auction.value) return
    const s = parseDateTimeFromISO(auction.value.start_at)
    const e = parseDateTimeFromISO(auction.value.end_at)
    editForm.value = {
        title: auction.value.title,
        status: auction.value.status,
        description: auction.value.description || '',
        bar_count: auction.value.bar_count,
        bar_weight: auction.value.bar_weight,
        min_price: auction.value.min_price || 0,
        min_step: auction.value.min_step || 10000,
        step_time: auction.value.step_time || 5,
        timezone: auction.value.timezone || 'Europe/Moscow',
        gpb_minutes: auction.value.gpb_minutes ?? 30,
        start_date: s.date, start_time: s.time || '10:00',
        end_date: e.date, end_time: e.time || '18:00',
    }
    if (auction.value.participants?.length > 0) {
        selectedParticipantIds.value = auction.value.participants.map(p => p.id)
        inviteAll.value = false
    } else {
        selectedParticipantIds.value = []
        inviteAll.value = true
    }
    participantSearch.value = ''
    editActiveTab.value = 'general'
    errors.value = {}
    initialEditState.value = JSON.stringify({ ...editForm.value, pids: [...selectedParticipantIds.value].sort() })
    showEditModal.value = true
    fetchParticipants()
}

const closeEditModal = (force = false) => {
    if (!force && showEditModal.value) {
        const current = JSON.stringify({ ...editForm.value, pids: [...selectedParticipantIds.value].sort() })
        if (current !== initialEditState.value) { showUnsavedModal.value = true; return }
    }
    showEditModal.value = false
    showUnsavedModal.value = false
    errors.value = {}
}

const confirmDiscardChanges = () => {
    showUnsavedModal.value = false
    showEditModal.value = false
}

// Auto-shift End Date
watch(() => editForm.value.start_date, (n) => {
    if (n && editForm.value.end_date && n > editForm.value.end_date) editForm.value.end_date = n
})

const saveAuction = async () => {
    errors.value = {}
    let valid = true
    const f = editForm.value
    if (!f.title?.trim()) { errors.value.title = 'Название обязательно'; valid = false }
    if (f.min_price === '' || f.min_price === null || f.min_price <= 0) { errors.value.min_price = 'Значение должно быть > 0'; valid = false }
    if (f.min_step === '' || f.min_step === null || f.min_step <= 0) { errors.value.min_step = 'Значение должно быть > 0'; valid = false }
    if (!f.bar_count || f.bar_count < 1) { errors.value.bar_count = 'Минимум 1'; valid = false }
    if (!f.bar_weight || f.bar_weight <= 0) { errors.value.bar_weight = 'Значение должно быть > 0'; valid = false }
    if (!f.step_time || f.step_time < 1 || f.step_time > 1440) { errors.value.step_time = 'От 1 до 1440 мин'; valid = false }
    if (!f.timezone) { errors.value.timezone = 'Выберите часовой пояс'; valid = false }
    if (!f.start_date) { errors.value.start_at = 'Укажите дату начала'; valid = false }
    if (!f.end_date) { errors.value.end_at = 'Укажите дату окончания'; valid = false }
    if (!valid) { editActiveTab.value = 'general'; return }

    const startAt = `${f.start_date}T${f.start_time || '00:00'}:00`
    const endAt = `${f.end_date}T${f.end_time || '23:59'}:00`
    const startD = new Date(startAt), endD = new Date(endAt)
    if (endD <= startD) { errors.value.end_at = 'Окончание должно быть позже начала'; valid = false }
    else if ((endD - startD) / 60000 < 15) { errors.value.end_at = 'Минимальная длительность — 15 минут'; valid = false }
    if (!valid) { editActiveTab.value = 'general'; return }

    isSaving.value = true
    try {
        await axios.put(`/api/auctions/${auctionId.value}`, {
            title: f.title, status: f.status, min_step: f.min_step, step_time: f.step_time,
            timezone: f.timezone, min_price: f.min_price, description: f.description || null,
            bar_count: f.bar_count, bar_weight: f.bar_weight, gpb_minutes: f.gpb_minutes,
            start_at: startAt, end_at: endAt,
            invite_all: inviteAll.value,
            participant_ids: inviteAll.value ? [] : selectedParticipantIds.value,
        })
        closeEditModal(true)
        await fetchAuction()
    } catch (e) {
        if (e.response?.status === 422) {
            const be = e.response.data.errors
            if (be.title) errors.value.title = be.title[0]
            if (be.start_at) errors.value.start_at = be.start_at[0]
            if (be.end_at) errors.value.end_at = be.end_at[0]
            if (be.status) errors.value.status = be.status[0]
        } else { alert('Ошибка сохранения: ' + (e.response?.data?.message || e.message)) }
    } finally { isSaving.value = false }
}

const sendInvitations = () => { showInviteModal.value = true }
const confirmSendInvitations = async () => {
    showInviteModal.value = false
    isSendingInvitations.value = true
    try {
        const r = await axios.post(`/api/auctions/${auctionId.value}/send-invitations`)
        alert(r.data.message)
    } catch (e) { alert('Ошибка при отправке приглашений') }
    finally { isSendingInvitations.value = false }
}

const goBack = () => router.push('/admin/auctions')

// --- Lifecycle ---
onMounted(async () => {
    await fetchAuction()
    fetchInitialOffers()
    fetchBids()
    fetchParticipants()

    echo.channel(`auction.${auctionId.value}`)
        .listen('.bid.placed', () => fetchBids(true))
        .listen('.offer.placed', () => fetchInitialOffers(true))

    if (!isConnected.value) startFallbackPolling()

    if (['scheduled', 'active', 'gpb_right'].includes(auction.value?.status)) startCountdown()
})

onUnmounted(() => {
    stopCountdown()
    stopFallbackPolling()
    if (auctionId.value) echo.leaveChannel(`auction.${auctionId.value}`)
})
</script>

<template>
  <div class="flex flex-col h-full">
    <!-- Loading Skeleton -->
    <div v-if="isLoadingPage" class="flex flex-col h-full overflow-hidden relative">
        <!-- Back button skeleton -->
        <div class="flex items-center gap-4 mb-4 flex-shrink-0">
           <div class="w-28 h-9 bg-white/5 rounded-lg skeleton-shimmer"></div>
           <div class="flex-1"></div>
           <div class="w-20 h-7 bg-white/5 rounded-full skeleton-shimmer"></div>
        </div>
        <!-- Header card skeleton -->
        <div class="mb-4 rounded-xl overflow-hidden border border-white/10 flex-shrink-0">
          <div class="bg-[#0f1115] px-6 py-4">
            <div class="flex items-center gap-4">
              <div class="w-12 h-12 bg-white/5 rounded-lg skeleton-shimmer"></div>
              <div class="flex-1 flex flex-col gap-2">
                <div class="h-6 w-56 bg-white/5 rounded skeleton-shimmer"></div>
                <div class="h-3 w-36 bg-white/[0.03] rounded skeleton-shimmer"></div>
              </div>
              <div class="h-10 w-36 bg-white/5 rounded-lg skeleton-shimmer"></div>
              <div class="h-10 w-10 bg-white/5 rounded-lg skeleton-shimmer"></div>
            </div>
          </div>
          <!-- Stats strip skeleton -->
          <div class="flex items-center gap-8 border-t border-white/5 bg-white/[0.02] px-6 py-3">
            <div class="h-5 w-32 bg-white/5 rounded skeleton-shimmer"></div>
            <div class="h-5 w-20 bg-white/5 rounded skeleton-shimmer"></div>
            <div class="h-5 w-28 bg-white/5 rounded skeleton-shimmer"></div>
            <div class="h-5 w-24 bg-white/5 rounded skeleton-shimmer"></div>
            <div class="flex-1"></div>
            <div class="h-4 w-40 bg-white/[0.03] rounded skeleton-shimmer"></div>
          </div>
        </div>
        <!-- Tabs skeleton -->
        <div class="flex border-b border-white/10 mb-4 flex-shrink-0 gap-2">
          <div class="h-8 w-32 bg-white/5 rounded skeleton-shimmer"></div>
          <div class="h-8 w-24 bg-white/5 rounded skeleton-shimmer"></div>
          <div class="h-8 w-28 bg-white/5 rounded skeleton-shimmer"></div>
          <div class="h-8 w-20 bg-white/5 rounded skeleton-shimmer"></div>
        </div>
        <!-- Table skeleton -->
        <div class="flex-1 rounded-lg border border-white/10 bg-dark-900/30 overflow-hidden">
          <div class="flex items-center gap-4 px-4 py-3 border-b border-white/10">
            <div class="h-3 w-24 bg-white/5 rounded skeleton-shimmer"></div>
            <div class="flex-1"></div>
            <div class="h-3 w-20 bg-white/5 rounded skeleton-shimmer"></div>
            <div class="h-3 w-16 bg-white/5 rounded skeleton-shimmer"></div>
            <div class="h-3 w-16 bg-white/5 rounded skeleton-shimmer"></div>
          </div>
          <div v-for="i in 6" :key="i" class="flex items-center gap-4 px-4 py-3.5 border-b border-white/5">
            <div class="h-4 rounded skeleton-shimmer" :style="{ width: (60 + i * 15) + 'px', background: 'rgba(255,255,255,0.04)' }"></div>
            <div class="flex-1"></div>
            <div class="h-4 w-16 bg-white/[0.04] rounded skeleton-shimmer"></div>
            <div class="h-4 w-20 bg-white/[0.04] rounded skeleton-shimmer"></div>
            <div class="h-4 w-14 bg-white/[0.03] rounded skeleton-shimmer"></div>
          </div>
        </div>
    </div>

    <!-- Ready Content -->
    <div v-else class="flex flex-col h-full overflow-hidden relative">
      <!-- Header Bar -->
      <div class="flex items-center gap-4 mb-4 flex-shrink-0">
        <button @click="goBack" class="group flex items-center gap-2 px-4 py-2 rounded-lg bg-white/5 border border-white/10 hover:bg-white/10 hover:border-white/20 hover:shadow-[0_0_15px_rgba(255,255,255,0.05)] transition-all duration-300 active:scale-95 text-xs font-bold uppercase tracking-widest text-gray-400 hover:text-white">
          <svg class="w-4 h-4 transition-transform group-hover:-translate-x-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
          Аукционы
        </button>
        <div class="flex-1"></div>
        <div class="flex items-center gap-2 px-3 py-1.5 rounded-full backdrop-blur-sm text-[10px] font-bold uppercase tracking-widest transition-all duration-500"
             :class="isConnected
               ? 'bg-emerald-500/10 border border-emerald-500/20 text-emerald-500'
               : 'bg-red-500/10 border border-red-500/30 text-red-400 animate-pulse shadow-[0_0_12px_rgba(239,68,68,0.2)]'"
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
      <div class="mb-4 relative rounded-xl overflow-hidden border border-white/10 group flex-shrink-0">
        <!-- Background with subtle animated gradient -->
        <div class="absolute inset-0 bg-[#0f1115]"></div>
        <div class="absolute inset-0 bg-gradient-to-br from-blue-900/10 via-transparent to-red-900/10"></div>
        <div class="absolute inset-0 bg-[url('/img/grid.svg')] opacity-[0.03]"></div>

        <!-- Content Container -->
        <div class="relative z-10">
          <!-- Main Row: Title, Status, Timer, Edit -->
          <div class="flex flex-col md:flex-row items-stretch md:items-center justify-between pl-6 pr-6 py-4 gap-4">
            <!-- Left: Title & ID -->
            <div class="flex items-center gap-5 min-w-0 flex-1">
              <div class="flex flex-col items-center justify-center w-14 h-14 rounded-xl bg-white/5 border border-white/10 shadow-inner flex-shrink-0">
                 <span class="text-[10px] font-bold text-gray-400 uppercase tracking-widest leading-none mb-0.5">№</span>
                 <span class="text-xl font-mono font-bold text-white leading-none">{{ auction.id }}</span>
              </div>
              <div class="min-w-0 flex-1">
                <h1 class="text-2xl font-bold text-white font-kanit tracking-wide truncate leading-tight" :title="auction.title">{{ auction.title }}</h1>
                <p v-if="auction.description" class="text-xs text-gray-500 truncate mt-0.5">{{ auction.description }}</p>
              </div>
            </div>

            <!-- Right: Live Controls (Status + Timer + Edit) -->
            <div class="flex items-center gap-4 self-end md:self-auto">
              <!-- Live Status & Timer Container -->
              <!-- Live Status & Timer Container -->
              <div class="relative group/status flex items-center overflow-hidden rounded-lg border transition-all duration-300"
                   :class="[
                     auction.status === 'active' ? 'border-amber-500/30 shadow-[0_0_15px_rgba(245,158,11,0.1)]' :
                     auction.status === 'gpb_right' ? 'border-blue-500/30 shadow-[0_0_15px_rgba(59,130,246,0.1)]' :
                     auction.status === 'scheduled' ? 'border-purple-500/30 shadow-[0_0_15px_rgba(168,85,247,0.1)]' :
                     auction.status === 'collecting_offers' ? 'border-cyan-500/30 shadow-[0_0_15px_rgba(6,182,212,0.1)]' :
                     auction.status === 'completed' ? 'border-emerald-500/30 shadow-[0_0_15px_rgba(52,211,153,0.1)]' :
                     'bg-white/5 border-white/10'
                   ]">
                 <!-- Animated Glow Background for active states (behind everything) -->
                 <div v-if="['active', 'gpb_right', 'scheduled'].includes(auction.status)" class="absolute inset-0 bg-gradient-to-r from-transparent via-white/5 to-transparent -translate-x-full animate-[shimmer_2s_infinite]"></div>

                 <!-- Status Section -->
                 <div class="px-4 py-2 flex items-center gap-2.5 backdrop-blur-sm"
                      :class="[
                        ['active', 'gpb_right', 'scheduled'].includes(auction.status) ? 'bg-white/5 rounded-l-lg' :
                        auction.status === 'collecting_offers' ? 'bg-cyan-950/40 rounded-lg' :
                        auction.status === 'completed' ? 'bg-emerald-950/40 rounded-lg' :
                        auction.status === 'commission' ? 'bg-orange-950/40 rounded-lg' :
                        'rounded-lg'
                      ]">
                    <div class="relative flex h-2.5 w-2.5">
                      <span v-if="['active', 'gpb_right', 'scheduled'].includes(auction.status)" class="animate-ping absolute inline-flex h-full w-full rounded-full opacity-75" :class="[
                        auction.status === 'gpb_right' ? 'bg-blue-500' :
                        auction.status === 'scheduled' ? 'bg-purple-400' :
                        'bg-amber-500'
                      ]"></span>
                      <span class="relative inline-flex rounded-full h-2.5 w-2.5" :class="getStatusDotClass(auction.status)"></span>
                    </div>
                    <div class="flex flex-col leading-none">
                      <span class="text-[9px] uppercase font-bold tracking-widest text-gray-400 mb-0.5">Статус</span>
                      <span class="text-xs font-bold whitespace-nowrap" :class="[
                        auction.status === 'active' ? 'text-amber-400 shadow-amber-500/50 drop-shadow-sm' :
                        auction.status === 'gpb_right' ? 'text-blue-500 shadow-blue-500/50 drop-shadow-sm' :
                        auction.status === 'scheduled' ? 'text-purple-400 shadow-purple-500/50 drop-shadow-sm' :
                        auction.status === 'collecting_offers' ? 'text-cyan-400 shadow-cyan-500/50 drop-shadow-sm' :
                        auction.status === 'completed' ? 'text-emerald-400 shadow-emerald-500/50 drop-shadow-sm' :
                        'text-white'
                      ]">{{ getStatusLabel(auction.status) }}</span>
                    </div>
                 </div>

                 <!-- Active / GPB / Scheduled Countdown — inset button -->
                 <div v-if="['active', 'gpb_right', 'scheduled'].includes(auction.status) && countdownSeconds > 0"
                      class="px-5 py-2 flex flex-col items-center justify-center min-w-[160px] rounded-lg border-l backdrop-blur-sm"
                      :class="[
                        auction.status === 'active' ? 'bg-amber-950/40 border-amber-500/30' :
                        auction.status === 'gpb_right' ? 'bg-blue-950/40 border-blue-500/30' :
                        'bg-purple-950/40 border-purple-500/30'
                      ]">
                    <span class="font-mono text-xl font-black tracking-widest leading-none"
                          :class="[
                            auction.status === 'active' ? 'text-amber-400' :
                            auction.status === 'gpb_right' ? 'text-blue-400' :
                            'text-purple-400'
                          ]">
                      {{
                        auction.status === 'active' ? tradingCountdownFormatted :
                        auction.status === 'gpb_right' ? gpbCountdownFormatted :
                        scheduledCountdownFormatted
                      }}
                    </span>
                    <span class="text-[9px] uppercase font-bold tracking-widest text-gray-500 mt-1">
                      {{
                        auction.status === 'gpb_right' ? 'До конца права ГПБ' :
                        auction.status === 'scheduled' ? 'До начала торгов' :
                        'До конца торгов'
                      }}
                    </span>
                 </div>
              </div>

               <button @click="openEditModal" class="h-10 w-10 md:w-auto md:px-4 flex items-center justify-center gap-2 rounded-lg text-xs font-bold uppercase tracking-widest text-white bg-white/5 hover:bg-white/10 border border-white/10 hover:border-white/20 transition-all active:scale-95 group/btn">
                <svg class="w-4 h-4 text-gray-400 group-hover/btn:text-white transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                <span class="hidden md:inline">Изменить</span>
              </button>
            </div>
          </div>

          <!-- Stats Strip - Glassmorphism -->
          <div class="flex flex-wrap items-center border-t border-white/5 bg-white/[0.02] backdrop-blur-sm px-6 py-3 gap-x-8 gap-y-3 text-sm min-h-[44px]">
             <!-- Lot -->
             <div class="flex items-center gap-2 min-w-[160px]">
               <span class="text-gray-500 text-xs uppercase font-bold tracking-wider">Лот</span>
               <span class="text-white font-bold">{{ auction.bar_count }} <span class="text-gray-500 font-normal">×</span> {{ auction.bar_weight }} <span class="text-gray-500 font-normal">кг</span> <span class="text-gray-500 font-normal">=</span> {{ (auction.bar_count * auction.bar_weight).toFixed(2) }} <span class="text-gray-500 font-normal">кг</span></span>
             </div>
             <!-- Participants -->
             <div class="flex items-center gap-2 min-w-[100px]">
               <span class="text-gray-500 text-xs uppercase font-bold tracking-wider">Участники</span>
               <span v-if="isLoadingParticipants" class="inline-block w-8 h-4 bg-white/5 rounded skeleton-shimmer"></span>
               <span v-else class="text-white font-bold min-w-[24px] text-center">{{ auction.invite_all ? allParticipants.length : selectedParticipantIds.length }}</span>
             </div>
             <!-- Min Price -->
             <div class="flex items-center gap-2">
               <span class="text-gray-500 text-xs uppercase font-bold tracking-wider">Мин. цена</span>
               <span class="text-emerald-400 font-bold font-mono">{{ Number(auction.min_price).toLocaleString() }} <span class="text-emerald-500/60 font-sans font-normal text-xs">₽/г</span></span>
             </div>
             <!-- Step -->
             <div class="flex items-center gap-2">
               <span class="text-gray-500 text-xs uppercase font-bold tracking-wider">Шаг</span>
               <span class="text-white font-bold font-mono">{{ Number(auction.min_step).toLocaleString() }} <span class="text-gray-500 font-sans font-normal text-xs">₽</span></span>
               <span class="text-gray-600 text-xs">({{ auction.step_time }} мин)</span>
             </div>
             <!-- Gpb -->
             <div class="flex items-center gap-2">
               <span class="text-gray-500 text-xs uppercase font-bold tracking-wider">Право ГПБ</span>
               <span class="text-white font-bold">{{ auction.gpb_minutes || 30 }} <span class="text-gray-500 font-normal text-xs">мин</span></span>
             </div>
             <!-- Spacer -->
             <div class="flex-1"></div>
             <!-- Date -->
             <div class="flex items-center gap-2 text-sm text-gray-200 font-medium my-auto">
               <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
               <span>{{ formatDate(auction.start_at) }} — {{ formatDate(auction.end_at) }}</span>
             </div>
          </div>
        </div>
      </div>

      <!-- Tabs Navigation -->
      <div class="flex border-b border-white/10 mb-4 flex-shrink-0 min-h-[42px]">
        <button type="button" @click="activeTab = 'offers'" class="px-4 py-2.5 text-xs font-bold uppercase tracking-widest transition-all relative flex items-center gap-2" :class="activeTab === 'offers' ? 'text-cyan-400' : 'text-gray-500 hover:text-gray-300'">
          Предложения
          <span v-if="isLoadingOffers" class="inline-flex items-center justify-center w-[26px] h-5 rounded-full bg-white/5 skeleton-shimmer"></span>
          <span v-else class="inline-flex items-center justify-center min-w-[20px] h-5 px-1.5 text-[10px] font-bold rounded-full" :class="activeTab === 'offers' ? 'bg-cyan-500/20 text-cyan-400' : 'bg-white/10 text-gray-400'">{{ offersDisplayCount }}</span>
          <div v-if="activeTab === 'offers'" class="absolute bottom-0 left-0 right-0 h-0.5 bg-cyan-500 rounded-full"></div>
        </button>
        <button type="button" @click="() => { activeTab = 'trading'; startCountdown() }" class="px-4 py-2.5 text-xs font-bold uppercase tracking-widest transition-all relative flex items-center gap-2" :class="activeTab === 'trading' ? 'text-amber-400' : 'text-gray-500 hover:text-gray-300'">
          Торги
          <span v-if="isLoadingBids" class="inline-flex items-center justify-center w-[26px] h-5 rounded-full bg-white/5 skeleton-shimmer"></span>
          <span v-else class="inline-flex items-center justify-center min-w-[20px] h-5 px-1.5 text-[10px] font-bold rounded-full" :class="activeTab === 'trading' ? 'bg-amber-500/20 text-amber-400' : 'bg-white/10 text-gray-400'">{{ bidsDisplayCount }}</span>
          <div v-if="activeTab === 'trading'" class="absolute bottom-0 left-0 right-0 h-0.5 bg-amber-500 rounded-full"></div>
        </button>
        <button type="button" @click="activeTab = 'gpb'" class="px-4 py-2.5 text-xs font-bold uppercase tracking-widest transition-all relative flex items-center gap-2" :class="activeTab === 'gpb' ? 'text-blue-400' : 'text-gray-500 hover:text-gray-300'">
          Право ГПБ
          <div v-if="activeTab === 'gpb'" class="absolute bottom-0 left-0 right-0 h-0.5 bg-blue-500 rounded-full"></div>
        </button>
        <button type="button" @click="activeTab = 'results'" class="px-4 py-2.5 text-xs font-bold uppercase tracking-widest transition-all relative flex items-center gap-2" :class="activeTab === 'results' ? 'text-emerald-400' : 'text-gray-500 hover:text-gray-300'">
          Итоги
          <div v-if="activeTab === 'results'" class="absolute bottom-0 left-0 right-0 h-0.5 rounded-full bg-emerald-500"></div>
        </button>
      </div>

      <!-- Tab Content Area -->
      <div class="flex-1 min-h-0 overflow-hidden flex flex-col">

      <!-- TAB: Offers -->
      <!-- TAB: Offers -->
      <div v-show="activeTab === 'offers'" class="flex-1 min-h-0 overflow-hidden flex flex-col">
        <div v-if="isLoadingOffers" class="flex-1 rounded-lg border border-white/10 bg-dark-900/30 overflow-hidden">
          <div class="flex items-center gap-4 px-4 py-3 border-b border-white/10">
            <div class="h-3 w-20 bg-white/5 rounded skeleton-shimmer"></div>
            <div class="flex-1"></div>
            <div class="h-3 w-20 bg-white/5 rounded skeleton-shimmer"></div>
            <div class="h-3 w-16 bg-white/5 rounded skeleton-shimmer"></div>
            <div class="h-3 w-14 bg-white/5 rounded skeleton-shimmer"></div>
          </div>
          <div v-for="i in 5" :key="i" class="flex items-center gap-4 px-4 py-3.5 border-b border-white/5">
            <div class="h-4 rounded skeleton-shimmer" :style="{ width: (50 + i * 20) + 'px', background: 'rgba(255,255,255,0.04)' }"></div>
            <div class="flex-1"></div>
            <div class="h-4 w-14 bg-white/[0.04] rounded skeleton-shimmer"></div>
            <div class="h-4 w-20 bg-white/[0.04] rounded skeleton-shimmer"></div>
            <div class="h-4 w-16 bg-white/[0.03] rounded skeleton-shimmer"></div>
          </div>
        </div>
        <div v-else-if="initialOffers.length === 0" class="flex-1 flex flex-col items-center justify-center text-gray-500">
          <svg class="w-10 h-10 mb-2 text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
          <span class="text-xs font-medium">Предложений пока нет</span>
        </div>
        <div v-else class="flex-1 overflow-auto scrollbar-dark rounded-lg border border-white/10 bg-dark-900/30">
          <table class="w-full text-left border-collapse relative">
            <thead class="sticky top-0 bg-dark-900 z-10">
              <tr class="border-b border-white/10 text-xs text-gray-500 uppercase tracking-widest font-bold">
                <th class="px-4 py-3 bg-dark-900">Участник</th>
                <th class="px-4 py-3 text-right bg-dark-900">Кол-во слитков</th>
                <th class="px-4 py-3 text-right bg-dark-900">Сумма</th>
                <th class="px-4 py-3 text-right bg-dark-900">Дата</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-white/5">
              <tr v-for="offer in initialOffers" :key="offer.id" class="hover:bg-white/5 transition-colors">
                <td class="px-4 py-3"><span class="text-sm text-white font-bold">{{ offer.user?.name || 'Н/Д' }}</span></td>
                <td class="px-4 py-3 text-right font-mono text-sm text-white">{{ Number(offer.volume).toLocaleString() }}</td>
                <td class="px-4 py-3 text-right font-mono text-sm text-emerald-400 font-bold"><span class="font-sans">₽</span>&nbsp;{{ Number(offer.price).toLocaleString() }}</td>
                <td class="px-4 py-3 text-right font-mono text-xs text-gray-500"><div>{{ new Date(offer.created_at).toLocaleDateString('ru-RU') }}</div><div>{{ new Date(offer.created_at).toLocaleTimeString('ru-RU') }}</div></td>
              </tr>
            </tbody>
            <tfoot v-if="initialOffers.length > 0" class="sticky bottom-0 z-10 bg-[#0d1f24]" style="box-shadow: 0 10px 0 #0d1f24">
              <tr class="text-sm font-bold">
                <td class="px-4 py-2 text-cyan-400 uppercase tracking-widest bg-[#0d1f24]" style="box-shadow: inset 0 1px 0 rgba(6,182,212,0.3)">Итого ({{ initialOffers.length }})</td>
                <td class="px-4 py-2 text-right font-mono text-white bg-[#0d1f24]" style="box-shadow: inset 0 1px 0 rgba(6,182,212,0.3)">{{ initialOffers.reduce((s, o) => s + Number(o.volume), 0).toLocaleString() }}</td>
                <td class="px-4 py-2 text-right font-mono text-cyan-400 bg-[#0d1f24]" style="box-shadow: inset 0 1px 0 rgba(6,182,212,0.3)"><span class="font-sans">₽</span>&nbsp;{{ initialOffers.reduce((s, o) => s + Number(o.price), 0).toLocaleString() }}</td>
                <td class="px-4 py-2 bg-[#0d1f24]" style="box-shadow: inset 0 1px 0 rgba(6,182,212,0.3)"></td>
              </tr>
            </tfoot>
          </table>
        </div>
      </div>

      <!-- TAB: Trading - will be added next -->
      <div v-show="activeTab === 'trading'" class="flex-1 min-h-0 overflow-hidden flex flex-col gap-3">

        <div v-if="isLoadingBids" class="flex-1 rounded-lg border border-white/10 bg-dark-900/30 overflow-hidden">
          <div class="flex items-center gap-4 px-4 py-3 border-b border-white/10">
            <div class="h-3 w-8 bg-white/5 rounded skeleton-shimmer"></div>
            <div class="h-3 w-20 bg-white/5 rounded skeleton-shimmer"></div>
            <div class="flex-1"></div>
            <div class="h-3 w-20 bg-white/5 rounded skeleton-shimmer"></div>
            <div class="h-3 w-16 bg-white/5 rounded skeleton-shimmer"></div>
            <div class="h-3 w-14 bg-white/5 rounded skeleton-shimmer"></div>
          </div>
          <div v-for="i in 5" :key="i" class="flex items-center gap-4 px-4 py-3.5 border-b border-white/5">
            <div class="h-4 w-6 bg-white/[0.04] rounded skeleton-shimmer"></div>
            <div class="h-4 rounded skeleton-shimmer" :style="{ width: (50 + i * 18) + 'px', background: 'rgba(255,255,255,0.04)' }"></div>
            <div class="flex-1"></div>
            <div class="h-4 w-14 bg-white/[0.04] rounded skeleton-shimmer"></div>
            <div class="h-4 w-20 bg-white/[0.04] rounded skeleton-shimmer"></div>
            <div class="h-4 w-16 bg-white/[0.03] rounded skeleton-shimmer"></div>
          </div>
        </div>
        <div v-else-if="auctionBids.length === 0" class="flex-1 flex flex-col items-center justify-center text-gray-500">
          <svg class="w-10 h-10 mb-2 text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
          <p class="text-xs font-medium">Ставок пока нет</p>
        </div>

        <template v-else>
          <div class="flex-1 overflow-auto scrollbar-dark rounded-lg border border-white/10 bg-dark-900/30">
            <table class="w-full text-left border-collapse relative">
              <thead class="sticky top-0 bg-dark-900 z-10">
                <tr class="border-b border-white/10 text-xs text-gray-500 uppercase tracking-widest font-bold">
                  <th class="px-4 py-3 bg-dark-900 w-8">#</th>
                  <th class="px-4 py-3 bg-dark-900">Участник</th>
                  <th class="px-4 py-3 text-right bg-dark-900">Слитков</th>
                  <th class="px-4 py-3 text-right bg-dark-900">Цена/г</th>
                  <th class="px-4 py-3 text-right bg-dark-900">Цена/слиток</th>
                  <th class="px-4 py-3 text-right bg-dark-900">Сумма</th>
                  <th v-if="hasBasisBids" class="px-4 py-3 text-right bg-dark-900">С базисом</th>
                  <th class="px-4 py-3 text-right bg-dark-900">Дата</th>
                </tr>
              </thead>
              <tbody>
                <!-- Winning -->
                <tr v-if="allocatedBids.winning.length > 0" class="bg-emerald-500/10 border-b border-emerald-500/20">
                  <td colspan="2" class="px-4 py-2"><span class="text-xs uppercase tracking-widest text-emerald-400 font-bold flex items-center gap-1.5"><svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>В лоте ({{ allocatedBids.lotBars }} слитков)</span></td>
                  <td class="px-4 py-2 text-right font-mono text-sm font-bold text-emerald-400">{{ allocatedBids.lotBars }}</td>
                  <td class="px-4 py-2"></td>
                  <td class="px-4 py-2"></td>
                  <td class="px-4 py-2 text-right font-mono text-sm font-bold text-emerald-400"><span class="font-sans">₽</span>&nbsp;{{ allocatedBids.lotTotal.toLocaleString('ru-RU', { minimumFractionDigits: 2 }) }}</td>
                  <td v-if="hasBasisBids" class="px-4 py-2"></td>
                  <td class="px-4 py-2"></td>
                </tr>
                <tr v-for="(bid, idx) in allocatedBids.winning" :key="'w-'+bid.id" class="border-b border-emerald-500/10 hover:bg-emerald-500/5 transition-colors" :class="bid.partial ? 'bg-yellow-500/5' : 'bg-emerald-500/5'">
                  <td class="px-4 py-3 text-sm text-gray-500 font-mono">{{ idx + 1 }}</td>
                  <td class="px-4 py-3"><span class="text-sm text-white font-bold">{{ bid.user?.name || 'Н/Д' }}</span></td>
                  <td class="px-4 py-3 text-right font-mono text-sm font-bold" :class="bid.partial ? 'text-yellow-400' : 'text-white'">{{ bid.fulfilled }}<span v-if="bid.partial" class="text-[10px] text-yellow-500 ml-1">(частич.)</span></td>
                  <td class="px-4 py-3 text-right font-mono text-sm text-emerald-400 font-bold"><span class="font-sans">₽</span>&nbsp;{{ Number(bid.amount).toLocaleString('ru-RU') }}</td>
                  <td class="px-4 py-3 text-right font-mono text-sm text-emerald-400 font-bold"><span class="font-sans">₽</span>&nbsp;{{ (Number(bid.amount) * allocatedBids.barWeight * 1000).toLocaleString('ru-RU') }}</td>
                  <td class="px-4 py-3 text-right font-mono text-sm text-white"><span class="font-sans">₽</span>&nbsp;{{ (bid.fulfilled * (allocatedBids.barWeight * 1000) * Number(bid.amount)).toLocaleString('ru-RU', { minimumFractionDigits: 2 }) }}</td>
                  <td v-if="hasBasisBids" class="px-4 py-3 text-right font-mono text-sm text-amber-400 font-bold"><template v-if="bid.user?.delivery_basis"><span class="font-sans">₽</span>&nbsp;{{ (bid.fulfilled * (allocatedBids.barWeight * 1000) * Number(bid.amount) * Number(bid.user.delivery_basis) / 100).toLocaleString('ru-RU', { minimumFractionDigits: 2 }) }}</template><span v-else class="text-gray-600">—</span></td>
                  <td class="px-4 py-3 text-right font-mono text-xs text-gray-500"><div>{{ new Date(bid.created_at).toLocaleDateString('ru-RU') }}</div><div>{{ new Date(bid.created_at).toLocaleTimeString('ru-RU') }}</div></td>
                </tr>
                <!-- Losing -->
                <tr v-if="allocatedBids.losing.length > 0"><td :colspan="hasBasisBids ? 8 : 7" class="px-4 py-2 bg-red-500/10 border-b border-red-500/20 border-t border-t-white/10"><span class="text-xs uppercase tracking-widest text-red-400 font-bold flex items-center gap-1.5"><svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>Не попали ({{ allocatedBids.losing.reduce((sum, b) => sum + b.bar_count, 0) }})</span></td></tr>
                <tr v-for="(bid, idx) in allocatedBids.losing" :key="'l-'+bid.id" class="border-b border-white/5 hover:bg-white/5 transition-colors opacity-60">
                  <td class="px-4 py-3 text-sm text-gray-600 font-mono">{{ allocatedBids.winning.length + idx + 1 }}</td>
                  <td class="px-4 py-3"><span class="text-sm text-gray-400 font-bold">{{ bid.user?.name || 'Н/Д' }}<span v-if="bid.isRemainder" class="text-[10px] text-yellow-500/60 ml-1">(остаток)</span></span></td>
                  <td class="px-4 py-3 text-right font-mono text-sm text-gray-400">{{ bid.bar_count }}</td>
                  <td class="px-4 py-3 text-right font-mono text-sm text-gray-500"><span class="font-sans">₽</span>&nbsp;{{ Number(bid.amount).toLocaleString('ru-RU') }}</td>
                  <td class="px-4 py-3 text-right font-mono text-sm text-gray-500"><span class="font-sans">₽</span>&nbsp;{{ (Number(bid.amount) * allocatedBids.barWeight * 1000).toLocaleString('ru-RU') }}</td>
                  <td class="px-4 py-3 text-right font-mono text-sm text-gray-600">—</td>
                  <td v-if="hasBasisBids" class="px-4 py-3 text-right font-mono text-sm text-gray-600">—</td>
                  <td class="px-4 py-3 text-right font-mono text-xs text-gray-600"><div>{{ new Date(bid.created_at).toLocaleDateString('ru-RU') }}</div><div>{{ new Date(bid.created_at).toLocaleTimeString('ru-RU') }}</div></td>
                </tr>
              </tbody>

            </table>
          </div>
        </template>
      </div>

      <!-- TAB: GPB Right -->
      <div v-show="activeTab === 'gpb'" class="flex-1 min-h-0 overflow-hidden flex flex-col gap-3">
        <div v-if="gpbFinished" class="flex items-center gap-3 px-5 py-3.5 rounded-lg border border-emerald-500/30 bg-emerald-500/10 flex-shrink-0">
          <svg class="w-5 h-5 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
          <span class="text-sm font-bold uppercase tracking-widest text-emerald-400">Право ГПБ завершено</span>
        </div>
        <div v-else-if="auction.status === 'gpb_right' && gpbAllocatedBids.hasGpbBids" class="flex items-center justify-end gap-3 px-5 py-3.5 rounded-lg border border-white/5 bg-white/5 flex-shrink-0">
          <div class="flex items-center gap-1.5">
            <span class="text-[10px] uppercase tracking-widest text-gray-400 font-bold">Итого лот</span>
            <span class="text-lg text-white font-bold font-mono"><span class="font-sans">₽</span>&nbsp;{{ gpbAllocatedBids.grandTotal.toLocaleString('ru-RU', { minimumFractionDigits: 2 }) }}</span>
          </div>
        </div>
        <div v-if="isLoadingBids" class="flex-1 rounded-lg border border-white/10 bg-dark-900/30 overflow-hidden">
          <div class="flex items-center gap-4 px-4 py-3 border-b border-white/10">
            <div class="h-3 w-8 bg-white/5 rounded skeleton-shimmer"></div>
            <div class="h-3 w-20 bg-white/5 rounded skeleton-shimmer"></div>
            <div class="flex-1"></div>
            <div class="h-3 w-20 bg-white/5 rounded skeleton-shimmer"></div>
            <div class="h-3 w-16 bg-white/5 rounded skeleton-shimmer"></div>
          </div>
          <div v-for="i in 4" :key="i" class="flex items-center gap-4 px-4 py-3.5 border-b border-white/5">
            <div class="h-4 w-6 bg-white/[0.04] rounded skeleton-shimmer"></div>
            <div class="h-4 rounded skeleton-shimmer" :style="{ width: (50 + i * 18) + 'px', background: 'rgba(255,255,255,0.04)' }"></div>
            <div class="flex-1"></div>
            <div class="h-4 w-14 bg-white/[0.04] rounded skeleton-shimmer"></div>
            <div class="h-4 w-20 bg-white/[0.04] rounded skeleton-shimmer"></div>
          </div>
        </div>
        <div v-else-if="auctionBids.length === 0" class="flex-1 flex flex-col items-center justify-center text-gray-500">
          <svg class="w-10 h-10 mb-2 text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
          <p class="text-xs font-medium">Ставок пока нет</p>
        </div>
        <template v-else>
          <div v-if="!gpbAllocatedBids.hasGpbBids" class="flex-1 flex flex-col items-center justify-center text-gray-500">
            <svg class="w-10 h-10 mb-2 text-blue-500/30" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
            <p class="text-xs font-medium">Участник ГПБ ещё не сделал ставку</p>
          </div>
          <div class="flex-1 overflow-auto scrollbar-dark rounded-lg border border-white/10 bg-dark-900/30">
            <table class="w-full text-left border-collapse relative">
              <thead class="sticky top-0 bg-dark-900 z-10">
                <tr class="border-b border-white/10 text-xs text-gray-500 uppercase tracking-widest font-bold">
                  <th class="px-4 py-3 bg-dark-900 w-8">#</th><th class="px-4 py-3 bg-dark-900">Участник</th><th class="px-4 py-3 text-right bg-dark-900">Слитков</th><th class="px-4 py-3 text-right bg-dark-900">Цена/г</th><th class="px-4 py-3 text-right bg-dark-900">Цена/слиток</th><th class="px-4 py-3 text-right bg-dark-900">Сумма</th>
                  <th v-if="hasBasisBids" class="px-4 py-3 text-right bg-dark-900">С базисом</th><th class="px-4 py-3 text-right bg-dark-900">Дата</th>
                </tr>
              </thead>
              <tbody>
                <tr v-if="gpbAllocatedBids.gpbBought.length > 0" class="bg-blue-500/10 border-b border-blue-500/20">
                  <td colspan="2" class="px-4 py-2"><span class="text-xs uppercase tracking-widest text-blue-400 font-bold flex items-center gap-1.5"><svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>Выкуп ГПБ ({{ gpbAllocatedBids.gpbTotalBars }} слитков)</span></td>
                  <td class="px-4 py-2 text-right font-mono text-sm font-bold text-blue-400">{{ gpbAllocatedBids.gpbTotalBars }}</td>
                  <td class="px-4 py-2"></td>
                  <td class="px-4 py-2"></td>
                  <td class="px-4 py-2 text-right font-mono text-sm font-bold text-blue-400"><span class="font-sans">₽</span>&nbsp;{{ gpbAllocatedBids.gpbTotal.toLocaleString('ru-RU', { minimumFractionDigits: 2 }) }}</td>
                  <td v-if="hasBasisBids" class="px-4 py-2"></td>
                  <td class="px-4 py-2"></td>
                </tr>
                <tr v-for="(item, idx) in gpbAllocatedBids.gpbBought" :key="'gpb-'+idx" class="border-b border-blue-500/10 hover:bg-blue-500/5 transition-colors bg-blue-500/5">
                  <td class="px-4 py-3 text-sm text-gray-500 font-mono">{{ idx + 1 }}</td>
                  <td class="px-4 py-3"><div class="flex items-center gap-1.5"><span class="text-sm text-blue-300 font-bold">{{ gpbAllocatedBids.gpbUser?.name || 'ГПБ' }}</span><span class="text-[10px] text-blue-500/60 bg-blue-500/10 px-1.5 py-0.5 rounded">ГПБ</span></div><span class="text-xs text-gray-500">по цене {{ item.user?.name || 'Н/Д' }}</span></td>
                  <td class="px-4 py-3 text-right font-mono text-sm text-blue-300 font-bold">{{ item.fulfilled }}</td>
                  <td class="px-4 py-3 text-right font-mono text-sm text-blue-400 font-bold"><span class="font-sans">₽</span>&nbsp;{{ item.pricePerGram.toLocaleString('ru-RU') }}</td>
                  <td class="px-4 py-3 text-right font-mono text-sm text-blue-400 font-bold"><span class="font-sans">₽</span>&nbsp;{{ (item.pricePerGram * gpbAllocatedBids.barWeight * 1000).toLocaleString('ru-RU') }}</td>
                  <td class="px-4 py-3 text-right font-mono text-sm text-white"><span class="font-sans">₽</span>&nbsp;{{ (item.fulfilled * (gpbAllocatedBids.barWeight * 1000) * item.pricePerGram).toLocaleString('ru-RU', { minimumFractionDigits: 2 }) }}</td>
                  <td v-if="hasBasisBids" class="px-4 py-3 text-right font-mono text-sm text-gray-600">—</td>
                  <td class="px-4 py-3 text-right font-mono text-xs text-gray-500"><div>{{ new Date(item.created_at).toLocaleDateString('ru-RU') }}</div><div>{{ new Date(item.created_at).toLocaleTimeString('ru-RU') }}</div></td>
                </tr>
                <!-- Participant winning -->
                <tr v-if="gpbAllocatedBids.participantWinning.length > 0" class="bg-emerald-500/10 border-b border-emerald-500/20 border-t border-t-white/10">
                  <td colspan="2" class="px-4 py-2"><span class="text-xs uppercase tracking-widest text-emerald-400 font-bold flex items-center gap-1.5"><svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>Остаток участникам ({{ gpbAllocatedBids.participantBars }} слитков)</span></td>
                  <td class="px-4 py-2 text-right font-mono text-sm font-bold text-emerald-400">{{ gpbAllocatedBids.participantBars }}</td>
                  <td class="px-4 py-2"></td>
                  <td class="px-4 py-2"></td>
                  <td class="px-4 py-2 text-right font-mono text-sm font-bold text-emerald-400"><span class="font-sans">₽</span>&nbsp;{{ gpbAllocatedBids.participantTotal.toLocaleString('ru-RU', { minimumFractionDigits: 2 }) }}</td>
                  <td v-if="hasBasisBids" class="px-4 py-2"></td>
                  <td class="px-4 py-2"></td>
                </tr>
                <tr v-for="(bid, idx) in gpbAllocatedBids.participantWinning" :key="'gpbw-'+bid.id+'-'+idx" class="border-b border-emerald-500/10 hover:bg-emerald-500/5 transition-colors" :class="bid.partial ? 'bg-yellow-500/5' : 'bg-emerald-500/5'">
                  <td class="px-4 py-3 text-sm text-gray-500 font-mono">{{ gpbAllocatedBids.gpbBought.length + idx + 1 }}</td>
                  <td class="px-4 py-3"><span class="text-sm text-white font-bold">{{ bid.user?.name || 'Н/Д' }}</span></td>
                  <td class="px-4 py-3 text-right font-mono text-sm font-bold" :class="bid.partial ? 'text-yellow-400' : 'text-white'">{{ bid.fulfilled }}<span v-if="bid.partial" class="text-[10px] text-yellow-500 ml-1">(частич.)</span></td>
                  <td class="px-4 py-3 text-right font-mono text-sm text-emerald-400 font-bold"><span class="font-sans">₽</span>&nbsp;{{ Number(bid.amount).toLocaleString('ru-RU') }}</td>
                  <td class="px-4 py-3 text-right font-mono text-sm text-emerald-400 font-bold"><span class="font-sans">₽</span>&nbsp;{{ (Number(bid.amount) * gpbAllocatedBids.barWeight * 1000).toLocaleString('ru-RU') }}</td>
                  <td class="px-4 py-3 text-right font-mono text-sm text-white"><span class="font-sans">₽</span>&nbsp;{{ (bid.fulfilled * (gpbAllocatedBids.barWeight * 1000) * Number(bid.amount)).toLocaleString('ru-RU', { minimumFractionDigits: 2 }) }}</td>
                  <td v-if="hasBasisBids" class="px-4 py-3 text-right font-mono text-sm text-amber-400 font-bold"><template v-if="bid.user?.delivery_basis"><span class="font-sans">₽</span>&nbsp;{{ (bid.fulfilled * (gpbAllocatedBids.barWeight * 1000) * Number(bid.amount) * Number(bid.user.delivery_basis) / 100).toLocaleString('ru-RU', { minimumFractionDigits: 2 }) }}</template><span v-else class="text-gray-600">—</span></td>
                  <td class="px-4 py-3 text-right font-mono text-xs text-gray-500"><div>{{ new Date(bid.created_at).toLocaleDateString('ru-RU') }}</div><div>{{ new Date(bid.created_at).toLocaleTimeString('ru-RU') }}</div></td>
                </tr>
                <!-- Participant losing -->
                <tr v-if="gpbAllocatedBids.participantLosing.length > 0"><td :colspan="hasBasisBids ? 8 : 7" class="px-4 py-2 bg-red-500/10 border-b border-red-500/20 border-t border-t-white/10"><span class="text-xs uppercase tracking-widest text-red-400 font-bold flex items-center gap-1.5"><svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>Не попали ({{ gpbAllocatedBids.participantLosing.reduce((sum, b) => sum + b.bar_count, 0) }})</span></td></tr>
                <tr v-for="(bid, idx) in gpbAllocatedBids.participantLosing" :key="'gpbl-'+bid.id+'-'+idx" class="border-b border-white/5 hover:bg-white/5 transition-colors opacity-60">
                  <td class="px-4 py-3 text-sm text-gray-600 font-mono">{{ gpbAllocatedBids.gpbBought.length + gpbAllocatedBids.participantWinning.length + idx + 1 }}</td>
                  <td class="px-4 py-3"><span class="text-sm text-gray-400 font-bold">{{ bid.user?.name || 'Н/Д' }}<span v-if="bid.lostToGpb" class="text-[10px] text-blue-400/50 ml-1">(выкуп ГПБ)</span><span v-else-if="bid.isRemainder" class="text-[10px] text-yellow-500/60 ml-1">(остаток)</span></span></td>
                  <td class="px-4 py-3 text-right font-mono text-sm text-gray-400">{{ bid.bar_count }}</td>
                  <td class="px-4 py-3 text-right font-mono text-sm text-gray-500"><span class="font-sans">₽</span>&nbsp;{{ Number(bid.amount).toLocaleString('ru-RU') }}</td>
                  <td class="px-4 py-3 text-right font-mono text-sm text-gray-500"><span class="font-sans">₽</span>&nbsp;{{ (Number(bid.amount) * gpbAllocatedBids.barWeight * 1000).toLocaleString('ru-RU') }}</td>
                  <td class="px-4 py-3 text-right font-mono text-sm text-gray-600">—</td>
                  <td v-if="hasBasisBids" class="px-4 py-3 text-right font-mono text-sm text-gray-600">—</td>
                  <td class="px-4 py-3 text-right font-mono text-xs text-gray-600"><div>{{ new Date(bid.created_at).toLocaleDateString('ru-RU') }}</div><div>{{ new Date(bid.created_at).toLocaleTimeString('ru-RU') }}</div></td>
                </tr>
              </tbody>
            </table>
          </div>
        </template>
      </div>

      <!-- TAB: Results -->
      <div v-show="activeTab === 'results'" class="flex-1 min-h-0 overflow-hidden flex flex-col gap-3">
        <div v-if="isLoadingBids" class="flex-1 flex flex-col gap-3">
          <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 flex-shrink-0">
            <div v-for="i in 4" :key="i" class="p-3 rounded-lg border border-white/10 bg-dark-900/40">
              <div class="h-2.5 w-16 bg-white/5 rounded skeleton-shimmer mb-2"></div>
              <div class="h-6 w-20 bg-white/[0.04] rounded skeleton-shimmer"></div>
            </div>
          </div>
          <div class="flex-1 rounded-lg border border-white/10 bg-dark-900/30 overflow-hidden">
            <div class="flex items-center gap-4 px-4 py-3 border-b border-white/10">
              <div class="h-3 w-8 bg-white/5 rounded skeleton-shimmer"></div>
              <div class="h-3 w-20 bg-white/5 rounded skeleton-shimmer"></div>
              <div class="flex-1"></div>
              <div class="h-3 w-16 bg-white/5 rounded skeleton-shimmer"></div>
              <div class="h-3 w-14 bg-white/5 rounded skeleton-shimmer"></div>
            </div>
            <div v-for="i in 4" :key="'r'+i" class="flex items-center gap-4 px-4 py-3.5 border-b border-white/5">
              <div class="h-4 w-6 bg-white/[0.04] rounded skeleton-shimmer"></div>
              <div class="h-4 rounded skeleton-shimmer" :style="{ width: (50 + i * 18) + 'px', background: 'rgba(255,255,255,0.04)' }"></div>
              <div class="flex-1"></div>
              <div class="h-4 w-14 bg-white/[0.04] rounded skeleton-shimmer"></div>
              <div class="h-4 w-20 bg-white/[0.04] rounded skeleton-shimmer"></div>
            </div>
          </div>
        </div>
        <div v-else-if="auctionBids.length === 0" class="flex-1 flex flex-col items-center justify-center text-gray-500">
          <svg class="w-10 h-10 mb-2 text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
          <p class="text-xs font-medium">Данных пока нет</p>
        </div>
        <template v-else>
          <!-- Summary Card -->
          <!-- Summary Card -->
          <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 flex-shrink-0 mb-4">
            
            <!-- Total Bids -->
            <div class="relative overflow-hidden rounded-2xl border border-white/10 bg-dark-900/60 p-5 flex flex-col justify-between group hover:border-white/20 transition-all duration-300">
              <div class="absolute inset-0 bg-gradient-to-br from-white/5 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-500"></div>
              <span class="text-[10px] uppercase tracking-widest text-gray-500 font-bold mb-2 relative z-10">Всего ставок</span>
              <div class="flex items-end justify-between relative z-10">
                <span class="text-3xl text-white font-bold font-mono tracking-tight">{{ auctionBids.length }}</span>
                <div class="mb-1 p-1.5 rounded-lg bg-white/5 text-gray-400 group-hover:text-white group-hover:bg-white/10 transition-colors">
                  <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 12l3-3 3 3 4-4M8 21l4-4 4 4M3 4h18M4 4h16v12a1 1 0 01-1 1H5a1 1 0 01-1-1V4z"/></svg>
                </div>
              </div>
            </div>

            <!-- Allocated -->
            <div class="relative overflow-hidden rounded-2xl border border-white/10 bg-dark-900/60 p-5 flex flex-col justify-between group hover:border-white/20 transition-all duration-300">
               <div class="absolute inset-0 bg-gradient-to-br opacity-0 group-hover:opacity-100 transition-opacity duration-500" :class="allocatedBids.lotBars >= allocatedBids.totalBars ? 'from-emerald-500/10' : 'from-yellow-500/10'"></div>
              <span class="text-[10px] uppercase tracking-widest text-gray-500 font-bold mb-2 relative z-10">Распределено</span>
              <div class="flex items-end justify-between relative z-10">
                <span class="text-3xl font-bold font-mono tracking-tight" :class="allocatedBids.lotBars >= allocatedBids.totalBars ? 'text-emerald-400' : 'text-yellow-400'">{{ allocatedBids.lotBars }} <span class="text-lg text-gray-500 font-medium">/ {{ allocatedBids.totalBars }}</span></span>
                <div class="mb-1 p-1.5 rounded-lg bg-white/5 transition-colors" :class="allocatedBids.lotBars >= allocatedBids.totalBars ? 'text-emerald-400 group-hover:bg-emerald-500/20' : 'text-yellow-400 group-hover:bg-yellow-500/20'">
                  <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                </div>
              </div>
            </div>

            <!-- Total Weight -->
            <div class="relative overflow-hidden rounded-2xl border border-white/10 bg-dark-900/60 p-5 flex flex-col justify-between group hover:border-white/20 transition-all duration-300">
              <div class="absolute inset-0 bg-gradient-to-br from-blue-500/5 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-500"></div>
              <span class="text-[10px] uppercase tracking-widest text-gray-500 font-bold mb-2 relative z-10">Общий вес</span>
              <div class="flex items-end justify-between relative z-10">
                <span class="text-3xl text-white font-bold font-mono tracking-tight">{{ allocatedBids.lotWeight.toFixed(1) }} <span class="text-lg text-gray-500 font-sans font-medium">кг</span></span>
                <div class="mb-1 p-1.5 rounded-lg bg-white/5 text-gray-400 group-hover:text-blue-400 group-hover:bg-blue-500/10 transition-colors">
                  <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3"/></svg>
                </div>
              </div>
            </div>

            <!-- Total Sum -->
            <div class="relative overflow-hidden rounded-2xl border border-emerald-500/30 bg-gradient-to-br from-emerald-500/10 to-emerald-900/20 p-5 flex flex-col justify-between group hover:border-emerald-500/50 hover:shadow-[0_0_20px_rgba(16,185,129,0.15)] transition-all duration-300">
              <div class="absolute inset-0 bg-[url('/img/noise.png')] opacity-20 mix-blend-overlay"></div>
              <div class="absolute -right-6 -top-6 w-24 h-24 bg-emerald-500/20 blur-3xl rounded-full group-hover:bg-emerald-500/30 transition-all duration-500"></div>
              
              <span class="text-[10px] uppercase tracking-widest text-emerald-400/80 font-bold mb-2 relative z-10">Итого сумма</span>
              <div class="flex items-end justify-between relative z-10">
                <span class="text-3xl text-emerald-300 font-bold font-mono tracking-tight text-shadow-sm"><span class="font-sans text-2xl opacity-80">₽</span>&nbsp;{{ allocatedBids.lotTotal.toLocaleString('ru-RU', { minimumFractionDigits: 2 }) }}</span>
              </div>
            </div>
          </div>
          <!-- Results table same as trading -->
          <div class="flex-1 overflow-auto scrollbar-dark rounded-lg border border-white/10 bg-dark-900/30">
            <table class="w-full text-left border-collapse relative">
              <thead class="sticky top-0 bg-dark-900 z-10">
                <tr class="border-b border-white/10 text-xs text-gray-500 uppercase tracking-widest font-bold">
                  <th class="px-4 py-3 bg-dark-900 w-8">#</th><th class="px-4 py-3 bg-dark-900">Участник</th><th class="px-4 py-3 text-right bg-dark-900">Слитков</th><th class="px-4 py-3 text-right bg-dark-900">Цена/г</th><th class="px-4 py-3 text-right bg-dark-900">Сумма</th><th class="px-4 py-3 text-right bg-dark-900">Дата</th>
                </tr>
              </thead>
              <tbody>
                <tr v-if="allocatedBids.winning.length > 0" class="bg-emerald-500/10 border-b border-emerald-500/20">
                  <td colspan="2" class="px-4 py-2"><span class="text-xs uppercase tracking-widest text-emerald-400 font-bold flex items-center gap-1.5"><svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>В лоте ({{ allocatedBids.lotBars }} слитков)</span></td>
                  <td class="px-4 py-2 text-right font-mono text-sm font-bold text-emerald-400">{{ allocatedBids.lotBars }}</td>
                  <td class="px-4 py-2"></td>
                  <td class="px-4 py-2 text-right font-mono text-sm font-bold text-emerald-400"><span class="font-sans">₽</span>&nbsp;{{ allocatedBids.lotTotal.toLocaleString('ru-RU', { minimumFractionDigits: 2 }) }}</td>
                  <td class="px-4 py-2"></td>
                </tr>
                <tr v-for="(bid, idx) in allocatedBids.winning" :key="'rw-'+bid.id" class="border-b border-emerald-500/10 hover:bg-emerald-500/5 transition-colors" :class="bid.partial ? 'bg-yellow-500/5' : 'bg-emerald-500/5'">
                  <td class="px-4 py-3 text-sm text-gray-500 font-mono">{{ idx + 1 }}</td>
                  <td class="px-4 py-3"><span class="text-sm text-white font-bold">{{ bid.user?.name || 'Н/Д' }}</span></td>
                  <td class="px-4 py-3 text-right font-mono text-sm font-bold" :class="bid.partial ? 'text-yellow-400' : 'text-white'">{{ bid.fulfilled }}<span v-if="bid.partial" class="text-[10px] text-yellow-500 ml-1">(частич.)</span></td>
                  <td class="px-4 py-3 text-right font-mono text-sm text-emerald-400 font-bold"><span class="font-sans">₽</span>&nbsp;{{ Number(bid.amount).toLocaleString('ru-RU') }}</td>
                  <td class="px-4 py-3 text-right font-mono text-sm text-white"><span class="font-sans">₽</span>&nbsp;{{ (bid.fulfilled * (allocatedBids.barWeight * 1000) * Number(bid.amount)).toLocaleString('ru-RU', { minimumFractionDigits: 2 }) }}</td>
                  <td class="px-4 py-3 text-right font-mono text-xs text-gray-500">{{ new Date(bid.created_at).toLocaleDateString('ru-RU') }}</td>
                </tr>
                <tr v-if="allocatedBids.losing.length > 0"><td colspan="6" class="px-4 py-2 bg-red-500/10 border-b border-red-500/20 border-t border-t-white/10"><span class="text-xs uppercase tracking-widest text-red-400 font-bold flex items-center gap-1.5"><svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>Не попали ({{ allocatedBids.losing.reduce((s,b) => s + b.bar_count, 0) }})</span></td></tr>
                <tr v-for="(bid, idx) in allocatedBids.losing" :key="'rl-'+bid.id" class="border-b border-white/5 hover:bg-white/5 transition-colors opacity-60">
                  <td class="px-4 py-3 text-sm text-gray-600 font-mono">{{ allocatedBids.winning.length + idx + 1 }}</td>
                  <td class="px-4 py-3"><span class="text-sm text-gray-400 font-bold">{{ bid.user?.name || 'Н/Д' }}</span></td>
                  <td class="px-4 py-3 text-right font-mono text-sm text-gray-400">{{ bid.bar_count }}</td>
                  <td class="px-4 py-3 text-right font-mono text-sm text-gray-500"><span class="font-sans">₽</span>&nbsp;{{ Number(bid.amount).toLocaleString('ru-RU') }}</td>
                  <td class="px-4 py-3 text-right font-mono text-sm text-gray-600">—</td>
                  <td class="px-4 py-3 text-right font-mono text-xs text-gray-600">{{ new Date(bid.created_at).toLocaleDateString('ru-RU') }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </template>
      </div>

      </div><!-- end tab content area -->

      <!-- EDIT MODAL -->
      <StandardModal :is-open="showEditModal" max-width="max-w-2xl" @close="closeEditModal(true)">
        <form @submit.prevent="saveAuction" class="flex flex-col h-[820px]">
          <h2 class="text-lg font-kanit font-bold text-white tracking-wide uppercase mb-4 flex-shrink-0">Редактирование аукциона</h2>

          <!-- Internal tabs -->
          <div class="flex border-b border-white/10 mb-4 flex-shrink-0">
            <button type="button" @click="editActiveTab = 'general'" class="px-4 py-2 text-xs font-bold uppercase tracking-widest transition-all relative" :class="editActiveTab === 'general' ? 'text-red-400' : 'text-gray-500 hover:text-gray-300'">
              Основное<div v-if="editActiveTab === 'general'" class="absolute bottom-0 left-0 right-0 h-0.5 bg-red-500 rounded-full"></div>
            </button>
            <button type="button" @click="editActiveTab = 'participants'" class="px-4 py-2 text-xs font-bold uppercase tracking-widest transition-all relative" :class="editActiveTab === 'participants' ? 'text-red-400' : 'text-gray-500 hover:text-gray-300'">
              Участники<div v-if="editActiveTab === 'participants'" class="absolute bottom-0 left-0 right-0 h-0.5 bg-red-500 rounded-full"></div>
            </button>
          </div>

          <!-- General Tab -->
          <div v-show="editActiveTab === 'general'" class="flex-1 overflow-y-auto min-h-0 pr-1 space-y-4">
            <!-- Title -->
            <div>
              <label class="block text-xs text-gray-400 font-bold uppercase tracking-widest mb-1.5">Название *</label>
              <input v-model="editForm.title" type="text" class="w-full bg-dark-900/50 border rounded-lg px-4 py-2.5 text-sm text-white focus:outline-none focus:border-red-500 focus:ring-1 focus:ring-red-500 transition-all" :class="errors.title ? 'border-red-500' : 'border-white/10'" />
              <p v-if="errors.title" class="text-red-400 text-xs mt-1">{{ errors.title }}</p>
            </div>
            <!-- Description -->
            <div>
              <label class="block text-xs text-gray-400 font-bold uppercase tracking-widest mb-1.5">Описание</label>
              <textarea v-model="editForm.description" rows="2" class="w-full bg-dark-900/50 border border-white/10 rounded-lg px-4 py-2.5 text-sm text-white focus:outline-none focus:border-red-500 focus:ring-1 focus:ring-red-500 transition-all resize-none"></textarea>
            </div>
            <!-- Status (styled like create form) -->
            <div class="relative">
              <label class="block text-[10px] uppercase tracking-widest text-gray-500 font-bold mb-1.5 ml-1">Статус</label>
              <div class="flex items-start gap-2">
                <!-- Select (with color & dot indicator) -->
                <div class="relative flex-1" style="height: 38px;">
                  <select
                    v-model="editForm.status"
                    style="height: 38px;"
                    class="w-full border border-white/10 rounded-lg pl-3 pr-12 text-sm focus:outline-none transition-all duration-300 hover:border-white/20 focus:border-red-500 focus:ring-1 focus:ring-red-500 font-bold tracking-wide appearance-none cursor-pointer no-arrow"
                    :class="getStatusClass(editForm.status)"
                  >
                    <option v-for="s in statusOptions" :key="s.value" :value="s.value" class="bg-dark-900 text-white">{{ s.label }}</option>
                  </select>
                  <div class="absolute top-0 bottom-0 right-0 flex items-center pr-3 pointer-events-none gap-2" style="height: 38px;">
                    <div class="w-2 h-2 rounded-full animate-pulse shadow-sm" :class="getStatusDotClass(editForm.status)"></div>
                    <svg class="h-4 w-4" :class="editForm.status === 'draft' ? 'text-gray-400' : 'text-current opacity-70'" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                  </div>
                </div>

                <!-- Workflow >> Button -->
                <button
                  v-if="!['completed', 'cancelled'].includes(editForm.status)"
                  type="button"
                  @click="advanceStatus"
                  title="Перейти к следующему статусу"
                  style="height: 38px; width: 38px;"
                  class="flex-shrink-0 flex items-center justify-center bg-gradient-to-r from-emerald-600 to-emerald-500 hover:from-emerald-500 hover:to-emerald-400 text-white rounded-lg shadow-lg shadow-emerald-500/20 transition-all active:scale-95"
                >
                  <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 5l7 7-7 7M5 5l7 7-7 7" />
                  </svg>
                </button>
              </div>
              <p v-if="errors.status" class="text-red-500 text-[10px] font-bold uppercase tracking-wide mt-1 ml-1">{{ errors.status }}</p>
            </div>
            <!-- Grid: bar_count, bar_weight, min_price, min_step -->
            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="block text-xs text-gray-400 font-bold uppercase tracking-widest mb-1.5">Кол-во слитков *</label>
                <input v-model.number="editForm.bar_count" type="number" min="1" class="w-full bg-dark-900/50 border rounded-lg px-4 py-2.5 text-sm text-white focus:outline-none focus:border-red-500 transition-all" :class="errors.bar_count ? 'border-red-500' : 'border-white/10'" />
                <p v-if="errors.bar_count" class="text-red-400 text-xs mt-1">{{ errors.bar_count }}</p>
              </div>
              <div>
                <label class="block text-xs text-gray-400 font-bold uppercase tracking-widest mb-1.5">Вес слитка (кг) *</label>
                <input v-model.number="editForm.bar_weight" type="number" step="0.01" min="0.01" class="w-full bg-dark-900/50 border rounded-lg px-4 py-2.5 text-sm text-white focus:outline-none focus:border-red-500 transition-all" :class="errors.bar_weight ? 'border-red-500' : 'border-white/10'" />
                <p v-if="errors.bar_weight" class="text-red-400 text-xs mt-1">{{ errors.bar_weight }}</p>
              </div>
              <div>
                <label class="block text-xs text-gray-400 font-bold uppercase tracking-widest mb-1.5">Мин. цена (₽/г) *</label>
                <input v-model.number="editForm.min_price" type="number" min="1" class="w-full bg-dark-900/50 border rounded-lg px-4 py-2.5 text-sm text-white focus:outline-none focus:border-red-500 transition-all" :class="errors.min_price ? 'border-red-500' : 'border-white/10'" />
                <p v-if="errors.min_price" class="text-red-400 text-xs mt-1">{{ errors.min_price }}</p>
              </div>
              <div>
                <label class="block text-xs text-gray-400 font-bold uppercase tracking-widest mb-1.5">Мин. шаг (₽) *</label>
                <input v-model.number="editForm.min_step" type="number" min="1" class="w-full bg-dark-900/50 border rounded-lg px-4 py-2.5 text-sm text-white focus:outline-none focus:border-red-500 transition-all" :class="errors.min_step ? 'border-red-500' : 'border-white/10'" />
                <p v-if="errors.min_step" class="text-red-400 text-xs mt-1">{{ errors.min_step }}</p>
              </div>
            </div>
            <!-- step_time, gpb_minutes, timezone -->
            <div class="grid grid-cols-3 gap-3">
              <div>
                <label class="block text-xs text-gray-400 font-bold uppercase tracking-widest mb-1.5">Время шага (мин)</label>
                <input v-model.number="editForm.step_time" type="number" min="1" max="1440" class="w-full bg-dark-900/50 border rounded-lg px-4 py-2.5 text-sm text-white focus:outline-none focus:border-red-500 transition-all" :class="errors.step_time ? 'border-red-500' : 'border-white/10'" />
              </div>
              <div>
                <label class="block text-xs text-gray-400 font-bold uppercase tracking-widest mb-1.5">ГПБ (мин)</label>
                <input v-model.number="editForm.gpb_minutes" type="number" min="1" class="w-full bg-dark-900/50 border border-white/10 rounded-lg px-4 py-2.5 text-sm text-white focus:outline-none focus:border-red-500 transition-all" />
              </div>
              <div>
                <label class="block text-xs text-gray-400 font-bold uppercase tracking-widest mb-1.5">Часовой пояс</label>
                <select v-model="editForm.timezone" class="w-full bg-dark-900/50 border border-white/10 rounded-lg px-4 py-2.5 text-sm text-white focus:outline-none focus:border-red-500 transition-all no-arrow">
                  <option v-for="tz in timezoneOptions" :key="tz.value" :value="tz.value">{{ tz.label }}</option>
                </select>
              </div>
            </div>
            <!-- Dates -->
            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="block text-xs text-gray-400 font-bold uppercase tracking-widest mb-1.5">Дата начала *</label>
                <div class="flex gap-2">
                  <input v-model="editForm.start_date" type="date" class="flex-1 bg-dark-900/50 border rounded-lg px-4 py-2.5 text-sm text-white focus:outline-none focus:border-red-500 transition-all" :class="errors.start_at ? 'border-red-500' : 'border-white/10'" />
                  <input v-model="editForm.start_time" type="time" class="w-28 bg-dark-900/50 border border-white/10 rounded-lg px-3 py-2.5 text-sm text-white focus:outline-none focus:border-red-500 transition-all" />
                </div>
                <p v-if="errors.start_at" class="text-red-400 text-xs mt-1">{{ errors.start_at }}</p>
              </div>
              <div>
                <label class="block text-xs text-gray-400 font-bold uppercase tracking-widest mb-1.5">Дата окончания *</label>
                <div class="flex gap-2">
                  <input v-model="editForm.end_date" type="date" class="flex-1 bg-dark-900/50 border rounded-lg px-4 py-2.5 text-sm text-white focus:outline-none focus:border-red-500 transition-all" :class="errors.end_at ? 'border-red-500' : 'border-white/10'" />
                  <input v-model="editForm.end_time" type="time" class="w-28 bg-dark-900/50 border border-white/10 rounded-lg px-3 py-2.5 text-sm text-white focus:outline-none focus:border-red-500 transition-all" />
                </div>
                <p v-if="errors.end_at" class="text-red-400 text-xs mt-1">{{ errors.end_at }}</p>
              </div>
            </div>
          </div>

          <!-- Participants Tab -->
          <div v-show="editActiveTab === 'participants'" class="flex-1 min-h-0 flex flex-col gap-3">
            <label class="flex items-center gap-3 px-3 py-2.5 rounded-lg border border-white/10 bg-dark-900/30 cursor-pointer hover:border-white/20 transition-all group shrink-0">
              <div class="relative flex-shrink-0">
                <input type="checkbox" v-model="inviteAll" class="sr-only peer"/>
                <div class="w-5 h-5 border-2 border-white/30 rounded transition-all peer-checked:bg-red-500 peer-checked:border-red-500 group-hover:border-white/50 flex items-center justify-center">
                  <svg v-if="inviteAll" class="w-3 h-3 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                </div>
              </div>
              <div class="flex-1"><span class="text-sm text-white font-semibold">Выбрать всех</span><span class="text-[10px] text-gray-500 block">Доступ получат все аккредитованные пользователи</span></div>
            </label>
            <div class="relative" :class="inviteAll ? 'opacity-40 pointer-events-none' : ''">
              <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
              <input ref="participantSearchRef" v-model="participantSearch" type="text" placeholder="Поиск по имени, телефону, email..." class="w-full bg-dark-900/50 border border-white/10 rounded-lg pl-9 pr-4 py-2.5 text-sm text-white focus:outline-none focus:border-red-500 transition-all placeholder-gray-600" />
            </div>
            <div class="flex items-center justify-between" :class="inviteAll ? 'opacity-40 pointer-events-none' : ''">
              <span class="text-xs text-gray-500">{{ filteredParticipants.length }} участников <span v-if="selectedParticipantIds.length > 0" class="text-red-400 font-bold ml-1">· {{ selectedParticipantIds.length }} выбрано</span></span>
              <button v-if="selectedParticipantIds.length > 0" type="button" @click="selectedParticipantIds = []" class="text-[10px] uppercase tracking-widest text-gray-500 hover:text-red-400 transition-colors font-bold">Сбросить</button>
            </div>
            <div class="flex-1 overflow-y-auto min-h-0 rounded-lg border border-white/10 bg-dark-900/30 divide-y divide-white/5" :class="inviteAll ? 'opacity-30' : ''">
              <label v-for="user in filteredParticipants" :key="user.id" class="flex items-center gap-3 px-4 py-3 cursor-pointer hover:bg-white/5 transition-colors" :class="[selectedParticipantIds.includes(user.id) ? 'bg-red-500/5' : '', inviteAll ? 'pointer-events-none' : '']">
                <div class="relative flex-shrink-0">
                  <input type="checkbox" :checked="selectedParticipantIds.includes(user.id)" @change="toggleParticipant(user.id)" class="sr-only peer"/>
                  <div class="w-5 h-5 border-2 border-white/30 rounded transition-all peer-checked:bg-red-500 peer-checked:border-red-500 hover:border-white/50 flex items-center justify-center">
                    <svg v-if="selectedParticipantIds.includes(user.id)" class="w-3 h-3 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                  </div>
                </div>
                <div class="flex-1 min-w-0">
                  <span class="text-sm text-white truncate block">{{ user.name }}</span>
                  <span class="text-[10px] text-gray-500 truncate block"><template v-if="user.inn">ИНН {{ user.inn }}</template><template v-if="user.inn && user.phone"> · </template><template v-if="user.phone">{{ user.phone }}</template></span>
                </div>
              </label>
              <div v-if="filteredParticipants.length === 0" class="px-4 py-6 text-center text-gray-500 text-xs">Участники не найдены</div>
            </div>
            <button type="button" @click="sendInvitations" :disabled="isSendingInvitations || (!inviteAll && selectedParticipantIds.length === 0)" class="py-2.5 rounded-lg text-xs font-bold uppercase tracking-widest transition-all border flex items-center justify-center gap-2 outline-none" :class="isSendingInvitations || (!inviteAll && selectedParticipantIds.length === 0) ? 'bg-white/5 text-gray-600 border-white/5 cursor-not-allowed' : 'bg-emerald-600/20 text-emerald-400 border-emerald-500/30 hover:bg-emerald-600/30 active:scale-95'">
              <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
              {{ isSendingInvitations ? 'Отправка...' : inviteAll ? 'Отправить приглашение всем' : `Отправить приглашение (${selectedParticipantIds.length})` }}
            </button>
          </div>

          <!-- Actions -->
          <div class="pt-4 flex justify-end gap-3 border-t border-white/5 mt-auto flex-shrink-0">
            <button type="button" @click="closeEditModal" class="px-6 py-2.5 rounded-lg text-xs font-bold uppercase tracking-widest text-gray-400 hover:text-white bg-white/5 hover:bg-white/10 transition-colors border border-transparent hover:border-white/10">Закрыть</button>
            <button type="submit" :disabled="isSaving" class="px-6 py-2.5 rounded-lg text-xs font-bold uppercase tracking-widest text-white bg-gradient-to-r from-red-600 to-red-500 hover:from-red-500 hover:to-red-400 shadow-lg shadow-red-900/20 transition-all active:scale-95 flex items-center gap-2" :class="isSaving ? 'opacity-70 cursor-wait' : ''">
              <svg v-if="isSaving" class="animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
              Сохранить
            </button>
          </div>
        </form>
      </StandardModal>

      <!-- Unsaved Changes Modal -->
      <StandardModal :is-open="showUnsavedModal" theme="red" z-index-class="z-[200]" backdrop-z-index-class="z-[150]" :backdrop-blur="false" @close="showUnsavedModal = false">
        <div class="text-center pt-2">
          <h3 class="text-xl font-kanit font-bold text-white tracking-wide uppercase mb-6">Закрыть<span class="text-2xl">?</span></h3>
          <p class="text-gray-400 text-sm mb-2 font-light">Внесённые изменения</p>
          <p class="text-red-400 text-sm font-semibold mb-6 tracking-wide">будут потеряны безвозвратно</p>
          <div class="flex gap-3">
            <button @click="showUnsavedModal = false" class="flex-1 py-3 rounded-lg text-xs font-bold uppercase tracking-widest text-gray-400 hover:text-white bg-white/5 hover:bg-white/10 transition-colors border border-transparent hover:border-white/10">Остаться</button>
            <button @click="confirmDiscardChanges" class="flex-1 py-3 bg-red-600 hover:bg-red-500 text-white rounded-lg font-bold text-xs uppercase tracking-widest shadow-lg shadow-red-500/20 transition-all active:scale-95 border border-red-500/50">Закрыть</button>
          </div>
        </div>
      </StandardModal>

      <!-- Invitation Confirmation Modal -->
      <StandardModal :is-open="showInviteModal" theme="red" max-width="max-w-md" z-index-class="z-[200]" backdrop-z-index-class="z-[150]" :backdrop-blur="false" @close="showInviteModal = false">
        <div class="text-center pt-2 px-4">
          <h3 class="text-xl font-kanit font-bold text-white tracking-wide uppercase mb-6 pr-8">Разослать приглашения<span class="text-2xl">?</span></h3>
          <p class="text-gray-400 text-sm mb-2 font-light">Уведомления будут отправлены</p>
          <p class="text-white font-bold text-lg mb-6">всем выбранным участникам ({{ inviteAll ? allParticipants.length : selectedParticipantIds.length }})</p>
          <div class="flex gap-3">
            <button @click="showInviteModal = false" class="flex-1 py-3 rounded-lg text-xs font-bold uppercase tracking-widest text-gray-400 hover:text-white bg-white/5 hover:bg-white/10 transition-colors border border-transparent hover:border-white/10">Отмена</button>
            <button @click="confirmSendInvitations" class="flex-1 py-3 bg-emerald-600 hover:bg-emerald-500 text-white rounded-lg font-bold text-xs uppercase tracking-widest shadow-lg shadow-emerald-500/20 transition-all active:scale-95 border border-emerald-500/50">Отправить</button>
          </div>
        </div>
      </StandardModal>

    </div>
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
