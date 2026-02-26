<script setup>
import { computed } from 'vue'

const props = defineProps({
  modelValue: {
    type: [Number, String],
    default: null
  },
  min: {
    type: [Number, String],
    default: null
  },
  max: {
    type: [Number, String],
    default: null
  },
  step: {
    type: [Number, String],
    default: 1
  },
  placeholder: {
    type: String,
    default: ''
  },
  inputClass: {
    type: [String, Array, Object],
    default: 'w-full bg-dark-900/50 border border-white/10 rounded-lg px-4 py-2.5 text-sm text-white focus:outline-none focus:border-gold-500 transition-all'
  },
  disabled: {
    type: Boolean,
    default: false
  },
  dark: {
    type: Boolean,
    default: false
  }
})

const emit = defineEmits(['update:modelValue'])

const handleInput = (e) => {
  const val = e.target.value
  emit('update:modelValue', val === '' ? '' : Number(val))
}

const isFloat = computed(() => {
    return Number(props.step) % 1 !== 0
})

const increment = () => {
  if (props.disabled) return
  let current = Number(props.modelValue)
  if (isNaN(current)) current = Number(props.min) || 0
  
  let next = current + Number(props.step)
  if (props.max !== null && next > Number(props.max)) {
    next = Number(props.max)
  }
  
  emit('update:modelValue', isFloat.value ? parseFloat(next.toFixed(2)) : next)
}

const decrement = () => {
  if (props.disabled) return
  let current = Number(props.modelValue)
  if (isNaN(current)) current = Number(props.min) || 0
  
  let next = current - Number(props.step)
  if (props.min !== null && next < Number(props.min)) {
    next = Number(props.min)
  }
  
  emit('update:modelValue', isFloat.value ? parseFloat(next.toFixed(2)) : next)
}

const spinnerContainerClass = computed(() => {
  if (props.dark) {
    return 'bg-white/5 rounded divide-y divide-white/10 border border-white/10'
  }
  return 'bg-gray-200/50 dark:bg-white/5 rounded divide-y divide-gray-300 dark:divide-white/10 border border-gray-300 dark:border-white/10'
})

const spinnerBtnClass = computed(() => {
  if (props.dark) {
    return 'w-6 h-4 flex items-center justify-center text-gray-400 hover:text-red-400 hover:bg-white/10 transition-colors active:bg-red-900/40'
  }
  return 'w-6 h-4 flex items-center justify-center text-gray-500 dark:text-gray-400 hover:text-gold-600 dark:hover:text-gold-400 hover:bg-white dark:hover:bg-white/10 transition-colors active:bg-gold-50 dark:active:bg-gold-900/40'
})
</script>

<template>
  <div class="relative group">
    <input
      type="number"
      :value="modelValue"
      @input="handleInput"
      :min="min"
      :max="max"
      step="any"
      :placeholder="placeholder"
      :disabled="disabled"
      class="appearance-none [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none [-moz-appearance:textfield] pr-10"
      :class="inputClass"
    />
    <div v-if="!disabled" class="absolute right-2 top-1/2 -translate-y-1/2 flex flex-col items-center justify-center opacity-70 group-hover:opacity-100 transition-opacity overflow-hidden" :class="spinnerContainerClass">
      <button type="button" tabindex="-1" @click="increment" :class="spinnerBtnClass">
        <svg class="w-2.5 h-2.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7" /></svg>
      </button>
      <button type="button" tabindex="-1" @click="decrement" :class="spinnerBtnClass">
        <svg class="w-2.5 h-2.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" /></svg>
      </button>
    </div>
  </div>
</template>
