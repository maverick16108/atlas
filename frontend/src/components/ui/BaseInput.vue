<script setup>
import { computed, ref } from 'vue'
import { ExclamationCircleIcon } from '@heroicons/vue/20/solid'

const props = defineProps({
  modelValue: [String, Number],
  label: String,
  type: {
    type: String,
    default: 'text'
  },
  placeholder: String,
  error: String,
  disabled: Boolean,
  icon: Object, // Component
  premium: Boolean
})

const emit = defineEmits(['update:modelValue'])
const inputRef = ref(null)

const inputClasses = computed(() => {
  // Premium Style (Client Login)
  if (props.premium) {
      const base = 'block w-full rounded-lg px-4 py-3 bg-dark-900/50 text-white placeholder-gray-600 border border-white/10 focus:border-gold-500 focus:ring-1 focus:ring-gold-500 focus:outline-none transition-all duration-300 font-mono text-lg tracking-widest hover:border-white/20'
      if (props.error) return `${base} border-red-500 focus:border-red-500 ring-red-500`
      return base
  }

  // Standard Admin/Default Style
  const base = 'block w-full rounded-lg border-0 py-2.5 bg-dark-800 text-white shadow-sm ring-1 ring-inset focus:ring-2 focus:ring-inset sm:text-sm sm:leading-6 transition-all duration-200'
  
  if (props.error) {
    return `${base} ring-red-500 focus:ring-red-500 placeholder:text-red-300`
  }
  
  return `${base} ring-white/10 placeholder:text-gray-500 focus:ring-gold-400/50 focus:bg-dark-700`
})

defineExpose({
    focus: () => {
        inputRef.value?.focus()
    }
})
</script>

<template>
  <div>
    <label v-if="label" class="block text-sm font-medium leading-6 mb-1.5" :class="premium ? 'text-gold-500 uppercase tracking-widest text-xs font-bold' : 'text-gray-300'">
      {{ label }}
    </label>
    <div class="relative mt-1 rounded-md shadow-sm">
      <div v-if="icon" class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
        <component :is="icon" class="h-5 w-5 text-gray-400" aria-hidden="true" />
      </div>
      
      <input
        ref="inputRef"
        :type="type"
        :value="modelValue"
        @input="$emit('update:modelValue', $event.target.value)"
        :class="[inputClasses, icon ? 'pl-10' : (premium ? 'pl-4' : 'pl-3')]"
        :placeholder="placeholder"
        :disabled="disabled"
      />
      
      <div v-if="error" class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3">
        <ExclamationCircleIcon class="h-5 w-5 text-red-500" aria-hidden="true" />
      </div>
    </div>
    <p v-if="error" class="mt-2 text-sm text-red-500" id="email-error">{{ error }}</p>
  </div>
</template>
