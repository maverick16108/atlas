<script setup>
import { ref, computed, onMounted, onUnmounted, nextTick } from 'vue'
import { XMarkIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
  isOpen: Boolean,
  title: String,
  theme: {
    type: String,
    default: 'red', // 'red' (Admin) or 'gold' (User)
    validator: (value) => ['red', 'gold'].includes(value)
  },
  maxWidth: {
    type: String,
    default: 'max-w-sm'
  },
  closeOnEscape: {
    type: Boolean,
    default: true
  },
  zIndexClass: {
    type: String,
    default: 'z-[100]'
  },
  backdropZIndexClass: {
    type: String,
    default: 'z-[50]'
  },
  backdropBlur: {
    type: Boolean,
    default: true
  },
  heightClass: {
    type: String,
    default: 'max-h-[95vh]'
  }
})

const emit = defineEmits(['close'])

const close = () => {
  emit('close')
}

const modalRef = ref(null)

// Focus Trapping
const focusTrap = (e) => {
  if (e.key === 'Tab' && props.isOpen) {
    const focusableElements = modalRef.value?.querySelectorAll(
      'a[href], button, textarea, input[type="text"], input[type="radio"], input[type="checkbox"], select'
    )
    if (!focusableElements || focusableElements.length === 0) return

    const firstElement = focusableElements[0]
    const lastElement = focusableElements[focusableElements.length - 1]

    if (e.shiftKey) {
      if (document.activeElement === firstElement) {
        lastElement.focus()
        e.preventDefault()
      }
    } else {
      if (document.activeElement === lastElement) {
        firstElement.focus()
        e.preventDefault()
      }
    }
  }
}

// Ensure focus on mount
const setInitialFocus = async () => {
    if (!props.isOpen) return
    await nextTick()
    
    const focusableElements = modalRef.value?.querySelectorAll(
      'button:not([disabled]), [href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'
    )
    
    if (focusableElements && focusableElements.length > 0) {
        // Try to focus the first element that isn't the close button
        // Assuming close button is the last one or first one? Usually close button is first in DOM order if header.
        // Actually close button is usually at the top right visually but in DOM...
        // Header close button is usually first tabbable? Or last?
        
        // Let's just focus the first element for now.
        // But better: focus the first input if possible.
        const firstInput = Array.from(focusableElements).find(el => ['INPUT', 'TEXTAREA', 'SELECT'].includes(el.tagName))
        if (firstInput) {
            firstInput.focus()
        } else {
            focusableElements[0].focus()
        }
    } else {
        // Fallback: focus the modal itself
        modalRef.value?.focus()
    }
}

// Close on Escape
const onKeydown = (e) => {
  if (e.key === 'Escape' && props.isOpen && props.closeOnEscape) {
    close()
  }
  focusTrap(e)
}

onMounted(() => {
  document.addEventListener('keydown', onKeydown)
  if (props.isOpen) {
      setInitialFocus()
  }
})

import { watch as vueWatch } from 'vue'
vueWatch(() => props.isOpen, (val) => {
    if (val) {
        setInitialFocus()
    }
})

onUnmounted(() => {
  document.removeEventListener('keydown', onKeydown)
})

// Compute theme colors
const themeClasses = computed(() => {
  if (props.theme === 'gold') {
    return {
      border: 'border-gold-500/30',
      shadow: 'shadow-[0_0_50px_rgba(212,175,55,0.15)]',
      closeHover: 'hover:text-gold-400',
      titleBar: 'bg-gold-500',
      titleShadow: 'shadow-[0_0_10px_rgba(212,175,55,0.8)]'
    }
  }
  if (props.theme === 'emerald') {
    return {
      border: 'border-emerald-500/30',
      shadow: 'shadow-[0_0_50px_rgba(16,185,129,0.15)]',
      closeHover: 'hover:text-emerald-400',
      titleBar: 'bg-emerald-600',
      titleShadow: 'shadow-[0_0_10px_rgba(16,185,129,0.8)]'
    }
  }
  // Default 'red'
  return {
    border: 'border-white/10',
    shadow: 'shadow-[0_0_50px_rgba(239,68,68,0.15)]',
    closeHover: 'hover:text-red-500',
    titleBar: 'bg-red-600',
    titleShadow: 'shadow-[0_0_10px_rgba(239,68,68,0.8)]'
  }
})
</script>

<template>
  <Teleport to="body">
    <div v-if="isOpen" class="fixed inset-0 bg-black/10 transition-opacity" :class="[backdropZIndexClass, backdropBlur ? 'backdrop-blur-sm' : '']" @click="close"></div>
    
    
    <div v-if="isOpen" class="fixed inset-0 flex items-center justify-center p-4 font-sans pointer-events-none" :class="zIndexClass">
      <div 
        ref="modalRef"
        class="relative w-full bg-dark-800/95 rounded-2xl overflow-hidden p-6 pointer-events-auto backdrop-blur-md transition-all duration-300 flex flex-col"
        :class="[maxWidth, heightClass, themeClasses.border, themeClasses.shadow, 'border']"
      >
        
        <!-- Header with Close Button -->
        <div class="flex items-start justify-between mb-6 flex-shrink-0" v-if="title || $slots.header">
            <!-- Header Slot or Standard Title -->
            <slot name="header">
                 <h3 class="text-2xl font-kanit font-bold leading-8 text-white uppercase tracking-widest flex items-center gap-3">
                     <span class="w-1 h-6 rounded-full" :class="[themeClasses.titleBar, themeClasses.titleShadow]"></span>
                     {{ title }}
                 </h3>
            </slot>
            
            <!-- Close Button (XMark) -->
            <button type="button" 
              class="flex items-center justify-center w-8 h-8 rounded-lg bg-white/5 hover:bg-white/10 text-gray-400 focus:outline-none transition-all duration-200 flex-shrink-0"
              :class="themeClasses.closeHover"
              @click="close"
            >
              <span class="sr-only">Close</span>
              <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 6l12 12M6 18L18 6" />
              </svg>
            </button>
        </div>
        
        <!-- Close Button for no-title modals -->
        <div class="absolute right-6 top-6 z-10" v-else>
          <button type="button" 
            class="flex items-center justify-center w-8 h-8 rounded-lg bg-white/5 hover:bg-white/10 text-gray-400 focus:outline-none transition-all duration-200"
            :class="themeClasses.closeHover"
            @click="close"
          >
            <span class="sr-only">Close</span>
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M6 6l12 12M6 18L18 6" />
            </svg>
          </button>
        </div>

        <!-- Content -->
        <div class="flex-1 min-h-0 overflow-hidden flex flex-col -mx-6 -mb-6 px-6 pb-6">
            <slot />
        </div>

      </div>
    </div>
  </Teleport>
</template>
