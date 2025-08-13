<script setup lang="ts">
import { BRAND_ASSETS, PICK } from '@/brand/assets'

const props = defineProps<{ 
  name?: string
  class?: string 
}>()

const src = props.name ? BRAND_ASSETS[props.name] : (PICK.icon ?? Object.values(BRAND_ASSETS)[0])
</script>

<template>
  <img 
    :src="src" 
    alt="Sentiment Shield" 
    :class="['h-9 w-auto drop-shadow', $attrs.class]" 
    decoding="async" 
  />
</template>