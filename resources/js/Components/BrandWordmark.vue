<script setup lang="ts">
import { PICK } from '@/brand/assets'
</script>

<template>
  <img 
    :src="PICK.wordmark ?? PICK.icon" 
    alt="Sentiment Shield" 
    class="h-7 w-auto drop-shadow" 
  />
</template>
