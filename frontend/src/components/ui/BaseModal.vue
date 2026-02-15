<script setup>
import { Dialog, DialogPanel, TransitionChild, TransitionRoot } from '@headlessui/vue'
import { XMarkIcon } from '@heroicons/vue/24/outline'

defineProps({
  isOpen: Boolean,
  title: String
})

defineEmits(['close'])
</script>

<template>
  <TransitionRoot as="template" :show="isOpen">
    <Dialog as="div" class="relative z-50" @close="$emit('close')">
      <TransitionChild as="template" enter="ease-out duration-300" enter-from="opacity-0" enter-to="opacity-100" leave="ease-in duration-200" leave-from="opacity-100" leave-to="opacity-0">
        <div class="fixed inset-0 bg-dark-900/80 backdrop-blur-sm transition-opacity" />
      </TransitionChild>

      <div class="fixed inset-0 z-10 overflow-y-auto">
        <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
          <TransitionChild as="template" enter="ease-out duration-300" enter-from="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" enter-to="opacity-100 translate-y-0 sm:scale-100" leave="ease-in duration-200" leave-from="opacity-100 translate-y-0 sm:scale-100" leave-to="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95">
            <DialogPanel class="relative transform overflow-hidden rounded-2xl bg-dark-900 border border-gold-500/30 px-4 pb-4 pt-5 text-left shadow-[0_0_50px_rgba(212,175,55,0.15)] transition-all sm:my-8 sm:w-full sm:max-w-lg sm:p-6">
              <!-- Decorative Top Line -->
              <div class="absolute top-0 left-0 right-0 h-1 bg-gradient-to-r from-transparent via-gold-500 to-transparent opacity-50"></div>
              
              
              <!-- Header with Close Button -->
              <div class="flex items-start justify-between mb-6">
                  <h3 v-if="title" class="text-2xl font-kanit font-bold leading-8 text-white uppercase tracking-widest flex items-center gap-3">
                     <span class="w-1 h-6 bg-gold-500 rounded-full shadow-[0_0_10px_rgba(212,175,55,0.8)]"></span>
                     {{ title }}
                  </h3>
                  <div v-else class="flex-1"></div> <!-- Spacer if no title -->
                  
                  <button type="button" 
                    class="flex items-center justify-center w-8 h-8 rounded-lg bg-white/5 hover:bg-gold-500/10 text-gray-400 hover:text-gold-400 focus:outline-none transition-all duration-200 flex-shrink-0" 
                    @click="$emit('close')"
                  >
                    <span class="sr-only">Close</span>
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M6 6l12 12M6 18L18 6" />
                    </svg>
                  </button>
              </div>

              <div>
                <div class="mt-2">
                    <slot />
                  </div>
                </div>
            </DialogPanel>
          </TransitionChild>
        </div>
      </div>
    </Dialog>
  </TransitionRoot>
</template>
