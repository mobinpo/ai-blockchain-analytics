<script setup>
defineProps({ type: { type: String, default: 'button' } })
</script>

<template>
  <button
    :type="type"
    class="inline-flex items-center justify-center rounded-lg bg-brand-500 px-4 py-2
           font-semibold text-gray-900 transition active:scale-[.99]
           hover:bg-brand-400 focus:outline-none focus-visible:ring-2
           focus-visible:ring-brand-500/70 shadow-glow"
  >
    <slot />
  </button>
</template>
