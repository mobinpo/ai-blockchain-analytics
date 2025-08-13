<script setup lang="ts">
import { BRAND_ASSETS } from '@/brand/assets'

const urls = Object.values(BRAND_ASSETS)
</script>

<template>
  <div class="grid grid-cols-3 sm:grid-cols-5 gap-4">
    <img 
      v-for="url in urls" 
      :key="url" 
      :src="url" 
      class="h-10 w-auto mx-auto opacity-90 hover:opacity-100 transition" 
      alt="Sentiment Shield Logo Variant"
    />
  </div>
</template>