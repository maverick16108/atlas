<script setup>
import BaseModal from './BaseModal.vue'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()

defineProps({
  isOpen: Boolean,
  title: String,
  content: String
})

defineEmits(['close'])
</script>

<template>
  <BaseModal :is-open="isOpen" :title="title" @close="$emit('close')">
    <div class="prose prose-invert prose-gold max-w-none text-gray-300 text-sm leading-relaxed max-h-[60vh] overflow-y-auto pr-2 custom-scrollbar">
       <!-- Safe HTML rendering for legal text -->
       <div v-html="content"></div>
    </div>
    
    <div class="mt-6 pt-6 border-t border-white/10 flex justify-end">
        <button 
           @click="$emit('close')"
           class="px-6 py-2 bg-white/5 hover:bg-white/10 text-white rounded-md transition-colors text-sm font-medium border border-white/10"
        >
            {{ t('common.close') }}
        </button>
    </div>
  </BaseModal>
</template>

<style scoped>
/* Custom Scrollbar for legal text */
.custom-scrollbar::-webkit-scrollbar {
  width: 4px;
}
.custom-scrollbar::-webkit-scrollbar-track {
  background: rgba(255, 255, 255, 0.05);
  border-radius: 4px;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
  background: rgba(212, 175, 55, 0.3);
  border-radius: 4px;
}
.custom-scrollbar::-webkit-scrollbar-thumb:hover {
  background: rgba(212, 175, 55, 0.6);
}
</style>
