<template>
  <div class="manage-badges">
    <Head title="Manage Verification Badges" />
    
    <AuthenticatedLayout>
      <template #header>
        <div class="flex justify-between items-center">
          <div>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
              Manage Verification Badges
            </h2>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
              Manage, embed, and monitor your verified smart contracts
            </p>
          </div>
          
          <div class="flex items-center space-x-4">
            <Link
              href="/get-verified"
              class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700"
            >
              <Icon name="plus" class="w-4 h-4 mr-2" />
              Verify New Contract
            </Link>
          </div>
        </div>
      </template>

      <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
          
          <!-- Quick Stats -->
          <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="bg-white dark:bg-panel overflow-hidden shadow rounded-lg">
              <div class="p-5">
                <div class="flex items-center">
                  <div class="flex-shrink-0">
                    <Icon name="shield-check" class="h-6 w-6 text-green-400" />
                  </div>
                  <div class="ml-5 w-0 flex-1">
                    <dl>
                      <dt class="text-sm font-medium text-gray-500 truncate">Total Verified</dt>
                      <dd class="text-lg font-medium text-gray-900 dark:text-white">{{ verifiedContracts.length }}</dd>
                    </dl>
                  </div>
                </div>
              </div>
            </div>

            <div class="bg-white dark:bg-panel overflow-hidden shadow rounded-lg">
              <div class="p-5">
                <div class="flex items-center">
                  <div class="flex-shrink-0">
                    <Icon name="calendar" class="h-6 w-6 text-blue-400" />
                  </div>
                  <div class="ml-5 w-0 flex-1">
                    <dl>
                      <dt class="text-sm font-medium text-gray-500 truncate">This Month</dt>
                      <dd class="text-lg font-medium text-gray-900 dark:text-white">{{ recentCount('month') }}</dd>
                    </dl>
                  </div>
                </div>
              </div>
            </div>

            <div class="bg-white dark:bg-panel overflow-hidden shadow rounded-lg">
              <div class="p-5">
                <div class="flex items-center">
                  <div class="flex-shrink-0">
                    <Icon name="clock" class="h-6 w-6 text-yellow-400" />
                  </div>
                  <div class="ml-5 w-0 flex-1">
                    <dl>
                      <dt class="text-sm font-medium text-gray-500 truncate">This Week</dt>
                      <dd class="text-lg font-medium text-gray-900 dark:text-white">{{ recentCount('week') }}</dd>
                    </dl>
                  </div>
                </div>
              </div>
            </div>

            <div class="bg-white dark:bg-panel overflow-hidden shadow rounded-lg">
              <div class="p-5">
                <div class="flex items-center">
                  <div class="flex-shrink-0">
                    <Icon name="code" class="h-6 w-6 text-purple-400" />
                  </div>
                  <div class="ml-5 w-0 flex-1">
                    <dl>
                      <dt class="text-sm font-medium text-gray-500 truncate">Embeddable</dt>
                      <dd class="text-lg font-medium text-gray-900 dark:text-white">{{ verifiedContracts.length }}</dd>
                    </dl>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Badge Management Component -->
          <BadgeManagement :initial-contracts="verifiedContracts" />

        </div>
      </div>
    </AuthenticatedLayout>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import BadgeManagement from '@/Components/Verification/BadgeManagement.vue'
import Icon from '@/Components/Icon.vue'

// Props
const props = defineProps({
  verifiedContracts: {
    type: Array,
    default: () => []
  }
})

// Computed
const recentCount = (period) => {
  const now = new Date()
  let filterDate

  switch (period) {
    case 'week':
      filterDate = new Date(now.getFullYear(), now.getMonth(), now.getDate() - 7)
      break
    case 'month':
      filterDate = new Date(now.getFullYear(), now.getMonth() - 1, now.getDate())
      break
    default:
      return 0
  }

  return props.verifiedContracts.filter(contract => {
    if (!contract.verified_at) return false
    const verifiedDate = new Date(contract.verified_at)
    return verifiedDate >= filterDate
  }).length
}
</script>

<style scoped>
.manage-badges {
  @apply min-h-screen bg-panel dark:bg-gray-900;
}
</style>