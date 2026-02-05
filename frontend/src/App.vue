<script setup>
</script>

<template>
  <div class="font-sans antialiased text-slate-900 dark:text-gray-100 bg-gray-50 dark:bg-dark-900 min-h-screen">
    <component :is="$route.meta.layout || 'div'">
      <RouterView />
    </component>
  </div>
</template>
