<script setup>
import { Link } from '@inertiajs/vue3';
</script>

<template>
  <div class="min-h-screen bg-gray-100">
    <!-- Header -->
    <header class="bg-white shadow">
      <div class="mx-auto max-w-7xl px-4 py-4 sm:px-6 lg:px-8 flex items-center justify-between">
        <h1 class="text-2xl font-bold text-gray-900">Aegis AI Dashboard</h1>
        <nav class="space-x-4">
          <Link href="/" class="text-gray-600 hover:text-gray-900">Home</Link>
          <Link href="/dashboard" class="text-gray-600 hover:text-gray-900">Dashboard</Link>
        </nav>
      </div>
    </header>

    <!-- Main -->
    <main class="py-6">
      <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <slot />
      </div>
    </main>
  </div>
</template> 