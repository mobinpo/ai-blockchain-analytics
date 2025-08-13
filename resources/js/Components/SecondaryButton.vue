<script setup>
defineProps({ type: { type: String, default: 'button' } })
</script>

<template>
  <button
    :type="type"
    class="inline-flex items-center justify-center rounded-lg border border-brand-500/50
           bg-panel px-4 py-2 text-cyan-100 hover:bg-panel2 hover:border-brand-500
           transition focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500/60
           shadow-inset-glow"
  >
    <slot />
  </button>
</template>
