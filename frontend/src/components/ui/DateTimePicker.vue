<script setup>
import { ref, watch } from 'vue'
import { VueDatePicker } from '@vuepic/vue-datepicker'
import '@vuepic/vue-datepicker/dist/main.css'

const props = defineProps({
    modelValue: {
        type: [String, Date],
        default: ''
    },
    placeholder: {
        type: String,
        default: 'Выберите дату'
    },
    error: {
        type: Boolean,
        default: false
    },
    enableTime: {
        type: Boolean,
        default: true
    },
    minDate: {
        type: [String, Date],
        default: null
    },
    maxDate: {
        type: [String, Date],
        default: null
    }
})

const emit = defineEmits(['update:modelValue'])

const internalValue = ref(props.modelValue ? new Date(props.modelValue) : null)

watch(() => props.modelValue, (newVal) => {
    internalValue.value = newVal ? new Date(newVal) : null
})

const handleUpdate = (val) => {
    if (val) {
        const date = new Date(val)
        emit('update:modelValue', date.toISOString())
    } else {
        emit('update:modelValue', null)
    }
}

const formatDate = (date) => {
    if (!date) return ''
    const d = new Date(date)
    const day = String(d.getDate()).padStart(2, '0')
    const month = String(d.getMonth() + 1).padStart(2, '0')
    const year = d.getFullYear()
    const hours = String(d.getHours()).padStart(2, '0')
    const minutes = String(d.getMinutes()).padStart(2, '0')
    
    if (props.enableTime) {
        return `${day}.${month}.${year}, ${hours}:${minutes}`
    }
    return `${day}.${month}.${year}`
}
</script>

<template>
    <div class="date-picker-wrapper" :class="{ 'has-error': error }">
        <VueDatePicker
            v-model="internalValue"
            @update:model-value="handleUpdate"
            :enable-time-picker="enableTime"
            :min-date="minDate"
            :max-date="maxDate"
            :format="formatDate"
            locale="ru"
            auto-apply
            :clearable="true"
            week-start="1"
            dark
            :placeholder="placeholder"
            input-class-name="dp-custom-input"
        />
    </div>
</template>

<style>
/* Wrapper styles */
.date-picker-wrapper {
    width: 100%;
}

/* Override default input */
.dp-custom-input {
    width: 100% !important;
    background-color: rgba(26, 26, 26, 0.5) !important;
    border: 1px solid rgba(255, 255, 255, 0.1) !important;
    border-radius: 8px !important;
    padding: 12px 16px !important;
    color: #ffffff !important;
    font-family: ui-monospace, monospace !important;
    font-size: 16px !important;
    transition: all 0.3s !important;
}

.dp-custom-input::placeholder {
    color: #4b5563 !important;
}

.dp-custom-input:hover {
    border-color: rgba(255, 255, 255, 0.2) !important;
}

.dp-custom-input:focus {
    border-color: #ef4444 !important;
    box-shadow: 0 0 0 1px #ef4444 !important;
    outline: none !important;
}

.has-error .dp-custom-input {
    border-color: rgba(239, 68, 68, 0.5) !important;
}

.has-error .dp-custom-input:focus {
    border-color: #ef4444 !important;
    box-shadow: 0 0 0 1px #ef4444 !important;
}

/* Icon styling */
.dp__input_icon {
    color: #9ca3af !important;
}

.dp__input_icon:hover {
    color: #ffffff !important;
}

.dp__clear_icon {
    color: #9ca3af !important;
}

.dp__clear_icon:hover {
    color: #ef4444 !important;
}

/* VueDatePicker Dark Theme */
.dp__theme_dark {
    --dp-background-color: #1a1a1a;
    --dp-text-color: #ffffff;
    --dp-hover-color: rgba(239, 68, 68, 0.1);
    --dp-hover-text-color: #ffffff;
    --dp-hover-icon-color: #ef4444;
    --dp-primary-color: #ef4444;
    --dp-primary-disabled-color: rgba(239, 68, 68, 0.5);
    --dp-primary-text-color: #ffffff;
    --dp-secondary-color: #374151;
    --dp-border-color: rgba(255, 255, 255, 0.1);
    --dp-menu-border-color: rgba(255, 255, 255, 0.1);
    --dp-border-color-hover: rgba(255, 255, 255, 0.2);
    --dp-disabled-color: #4b5563;
    --dp-scroll-bar-background: #1f2937;
    --dp-scroll-bar-color: #374151;
    --dp-success-color: #10b981;
    --dp-success-color-disabled: rgba(16, 185, 129, 0.5);
    --dp-icon-color: #9ca3af;
    --dp-danger-color: #ef4444;
    --dp-marker-color: #ef4444;
    --dp-tooltip-color: #1f2937;
    --dp-disabled-color-text: #6b7280;
    --dp-highlight-color: rgba(239, 68, 68, 0.1);
    --dp-range-between-dates-background-color: rgba(239, 68, 68, 0.1);
    --dp-range-between-dates-text-color: #ffffff;
    --dp-range-between-border-color: rgba(239, 68, 68, 0.1);
}

.dp__menu {
    border-radius: 12px !important;
    box-shadow: 0 20px 50px rgba(0, 0, 0, 0.5) !important;
    border: 1px solid rgba(255, 255, 255, 0.1) !important;
}

.dp__calendar_header_item {
    font-weight: 600 !important;
    text-transform: uppercase !important;
    font-size: 11px !important;
    letter-spacing: 0.05em !important;
}

.dp__cell_inner {
    border-radius: 8px !important;
    font-weight: 500 !important;
}

.dp__today {
    border: 1px solid #ef4444 !important;
}

.dp__active_date {
    background-color: #ef4444 !important;
}

.dp__action_row {
    padding: 12px !important;
    border-top: 1px solid rgba(255, 255, 255, 0.1) !important;
}

.dp__action_button {
    border-radius: 8px !important;
    font-weight: 600 !important;
    text-transform: uppercase !important;
    font-size: 11px !important;
    letter-spacing: 0.05em !important;
}

.dp__action_cancel {
    background-color: rgba(255, 255, 255, 0.05) !important;
    color: #9ca3af !important;
}

.dp__action_cancel:hover {
    background-color: rgba(255, 255, 255, 0.1) !important;
    color: #ffffff !important;
}

.dp__action_select {
    background-color: #ef4444 !important;
    color: #ffffff !important;
}

.dp__action_select:hover {
    background-color: #dc2626 !important;
}

.dp__time_display {
    background-color: rgba(239, 68, 68, 0.1) !important;
    border-radius: 8px !important;
    color: #ffffff !important;
    font-weight: 600 !important;
}

.dp__overlay_cell_active {
    background-color: #ef4444 !important;
}

.dp__month_year_select:hover {
    color: #ef4444 !important;
}

.dp__arrow_top,
.dp__arrow_bottom {
    border-color: rgba(255, 255, 255, 0.1) !important;
}

.dp__input_wrap {
    width: 100%;
}
</style>
