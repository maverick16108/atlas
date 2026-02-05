<script setup>
import { computed } from 'vue'

const props = defineProps({
  variant: {
    type: String,
    default: 'primary',
    validator: (value) => ['primary', 'secondary', 'glass', 'danger'].includes(value)
  },
  size: {
    type: String,
    default: 'md',
    validator: (value) => ['sm', 'md', 'lg'].includes(value)
  },
  block: Boolean,
  disabled: Boolean,
  loading: Boolean,
  to: [String, Object] // Support router-link
})

const baseClasses = 'inline-flex items-center justify-center font-medium transition-all duration-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed transform active:scale-95'

const variantClasses = computed(() => {
  switch (props.variant) {
    case 'primary':
      return 'bg-gold-gradient text-dark-900 shadow-lg shadow-gold-500/20 hover:shadow-gold-500/40 border border-transparent hover:-translate-y-0.5'
    case 'secondary':
      return 'bg-dark-800 text-gold-400 border border-gold-400/30 hover:bg-dark-700 hover:border-gold-400'
    case 'glass':
      return 'bg-white/5 backdrop-blur-md border border-white/10 text-white hover:bg-white/10 hover:border-white/20'
    case 'danger':
      return 'bg-red-600 text-white hover:bg-red-700 shadow-lg shadow-red-500/20'
    default:
      return ''
  }
})

const sizeClasses = computed(() => {
  switch (props.size) {
    case 'sm': return 'text-sm px-3 py-1.5'
    case 'lg': return 'text-lg px-8 py-3'
    default: return 'text-base px-5 py-2.5'
  }
})

const componentTag = computed(() => props.to ? 'router-link' : 'button')
</script>

<template>
  <component 
    :is="componentTag" 
    :to="to" 
    :class="[baseClasses, variantClasses, sizeClasses, block ? 'w-full' : '']"
    :disabled="disabled || loading"
  >
    <svg v-if="loading" class="animate-spin -ml-1 mr-2 h-4 w-4" fill="none" viewBox="0 0 24 24">
      <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
      <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
    </svg>
    <slot />
  </component>
</template>
