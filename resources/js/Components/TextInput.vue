<script setup>
import { onMounted, ref } from 'vue';

const model = defineModel({
    type: String,
    required: true,
});

const input = ref(null);

onMounted(() => {
    if (input.value?.hasAttribute('autofocus')) {
        // Add a small delay to prevent autofocus blocking
        setTimeout(() => {
            // Only focus if no other element is currently focused
            if (!document.activeElement || document.activeElement === document.body) {
                input.value?.focus();
            }
        }, 100);
    }
});

defineExpose({ focus: () => input.value.focus() });
</script>

<template>
    <input
        class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-brand-500 bg-white text-gray-900 placeholder-gray-500"
        v-model="model"
        ref="input"
    />
</template>
