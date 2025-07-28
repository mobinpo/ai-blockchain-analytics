<script setup>
import AppLayout from '../Layouts/AppLayout.vue';

const projects = [
  { id: 1, name: 'Uniswap', description: 'DEX protocol' },
  { id: 2, name: 'Aave', description: 'Lending protocol' },
];
</script>

<template>
  <AppLayout>
    <h2 class="text-xl font-semibold mb-4">Your Projects</h2>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <div v-for="project in projects" :key="project.id" class="bg-white p-4 shadow rounded">
        <h3 class="font-bold text-lg">{{ project.name }}</h3>
        <p class="text-gray-600">{{ project.description }}</p>
        <button class="mt-2 px-3 py-1 bg-indigo-600 text-white rounded text-sm">View</button>
      </div>
    </div>
  </AppLayout>
</template> 