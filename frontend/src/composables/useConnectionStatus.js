import { ref, onMounted, onUnmounted } from 'vue'
import echo from '@/echo.js'

/**
 * Composable to track real-time connection status.
 * Uses Pusher connection state under the hood.
 */
export function useConnectionStatus() {
    const isConnected = ref(false)
    const statusText = ref('Подключение...')
    let stateHandler = null

    const updateStatus = (state) => {
        switch (state) {
            case 'connected':
                isConnected.value = true
                statusText.value = 'Онлайн обновления'
                break
            case 'connecting':
            case 'initialized':
                isConnected.value = false
                statusText.value = 'Подключение...'
                break
            case 'unavailable':
            case 'failed':
            case 'disconnected':
                isConnected.value = false
                statusText.value = 'Нет связи'
                break
            default:
                isConnected.value = false
                statusText.value = 'Нет связи'
        }
    }

    onMounted(() => {
        try {
            const pusher = echo.connector.pusher
            // Set initial state
            updateStatus(pusher.connection.state)
            // Listen for state changes
            stateHandler = (states) => {
                updateStatus(states.current)
            }
            pusher.connection.bind('state_change', stateHandler)
        } catch (e) {
            isConnected.value = false
            statusText.value = 'Нет связи'
        }
    })

    onUnmounted(() => {
        try {
            if (stateHandler) {
                echo.connector.pusher.connection.unbind('state_change', stateHandler)
            }
        } catch (e) { /* ignore */ }
    })

    return { isConnected, statusText }
}
