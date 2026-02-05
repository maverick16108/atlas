<template>
  <div class="glass-panel p-6 rounded-xl hover:border-gold-500/30 transition-all duration-300 group h-full">
    <div v-if="$slots.icon" class="mb-4 text-gold-400 group-hover:scale-110 transition-transform duration-300 inline-block p-2 bg-gold-400/10 rounded-lg">
      <slot name="icon" />
    </div>
    <h3 class="text-lg font-semibold text-white mb-2 font-kanit uppercase tracking-wide">
      <slot name="title" />
    </h3>
    <p class="text-gray-400 text-sm leading-relaxed">
      <slot />
    </p>
  </div>
</template>
