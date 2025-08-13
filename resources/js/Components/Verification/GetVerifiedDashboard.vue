<template>
    <div class="get-verified-dashboard">
        <!-- Dashboard Header -->
        <div class="dashboard-header bg-gradient-to-r from-blue-600 via-purple-600 to-indigo-700 rounded-xl p-8 text-white mb-8">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold mb-2">
                        🛡️ Secure Contract Verification
                    </h1>
                    <p class="text-lg opacity-90 mb-4">
                        Get cryptographically signed verification badges using SHA-256 + HMAC
                    </p>
                    <div class="flex items-center gap-6 text-sm">
                        <div class="flex items-center gap-2">
                            <div class="w-3 h-3 bg-green-400 rounded-full animate-pulse"></div>
                            <span>Anti-Spoofing Protection</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <div class="w-3 h-3 bg-blue-400 rounded-full animate-pulse"></div>
                            <span>Time-Based Expiration</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <div class="w-3 h-3 bg-purple-400 rounded-full animate-pulse"></div>
                            <span>Replay Attack Prevention</span>
                        </div>
                    </div>
                </div>
                <div class="text-right">
                    <div class="text-3xl font-bold">{{ stats.total_verified || 0 }}</div>
                    <div class="text-sm opacity-75">Contracts Verified</div>
                    <div class="mt-2 text-lg font-semibold">{{ stats.verified_today || 0 }}</div>
                    <div class="text-xs opacity-75">Verified Today</div>
                </div>
            </div>
        </div>

        <!-- Main Content Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            
            <!-- Left Column: Verification Generator -->
            <div class="lg:col-span-2 space-y-6">
                
                <!-- Verification Form -->
                <div class="bg-white rounded-xl shadow-lg border border-gray-200 p-6">
                    <div class="flex items-center justify-between mb-6">
                        <h2 class="text-xl font-semibold text-gray-900">
                            🔐 Generate Verification URL
                        </h2>
                        <div class="flex items-center gap-2 text-sm text-gray-500">
                            <div class="w-2 h-2 rounded-full" :class="verificationStatus === 'ready' ? 'bg-green-500' : 'bg-yellow-500'"></div>
                            <span>{{ verificationStatus === 'ready' ? 'Ready' : 'Processing' }}</span>
                        </div>
                    </div>
                    
                    <form @submit.prevent="generateVerificationUrl" class="space-y-6">
                        <!-- Contract Address -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Smart Contract Address *
                            </label>
                            <div class="relative">
                                <input
                                    v-model="form.contractAddress"
                                    type="text"
                                    placeholder="0x..."
                                    pattern="^0x[a-fA-F0-9]{40}$"
                                    required
                                    :class="[
                                        'w-full px-4 py-3 border rounded-lg transition-colors',
                                        contractAddressValid 
                                            ? 'border-green-300 bg-green-50 focus:border-green-500 focus:ring-green-200' 
                                            : form.contractAddress 
                                                ? 'border-red-300 bg-red-50 focus:border-red-500 focus:ring-red-200'
                                                : 'border-gray-300 focus:border-blue-500 focus:ring-blue-200'
                                    ]"
                                    @input="validateContractAddress"
                                />
                                <div class="absolute right-3 top-1/2 transform -translate-y-1/2">
                                    <svg v-if="contractAddressValid" class="w-5 h-5 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                    </svg>
                                    <svg v-else-if="form.contractAddress && !contractAddressValid" class="w-5 h-5 text-red-500" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                                    </svg>
                                </div>
                            </div>
                            <p v-if="form.contractAddress && !contractAddressValid" class="mt-1 text-sm text-red-600">
                                Please enter a valid Ethereum contract address (0x followed by 40 hexadecimal characters)
                            </p>
                        </div>

                        <!-- Project Information -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Project Name
                                </label>
                                <input
                                    v-model="form.projectName"
                                    type="text"
                                    placeholder="e.g., DeFi Protocol"
                                    maxlength="100"
                                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:border-blue-500 focus:ring-blue-200 transition-colors"
                                />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Category
                                </label>
                                <select
                                    v-model="form.category"
                                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:border-blue-500 focus:ring-blue-200 transition-colors"
                                >
                                    <option value="">Select Category</option>
                                    <option v-for="category in categories" :key="category" :value="category">
                                        {{ category }}
                                    </option>
                                </select>
                            </div>
                        </div>

                        <!-- Website URL -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Website URL
                            </label>
                            <input
                                v-model="form.website"
                                type="url"
                                placeholder="https://yourproject.com"
                                maxlength="200"
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:border-blue-500 focus:ring-blue-200 transition-colors"
                            />
                        </div>

                        <!-- Description -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Description
                            </label>
                            <textarea
                                v-model="form.description"
                                placeholder="Brief description of your smart contract or project..."
                                maxlength="500"
                                rows="3"
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:border-blue-500 focus:ring-blue-200 transition-colors resize-none"
                            ></textarea>
                            <div class="mt-1 text-sm text-gray-500 text-right">
                                {{ form.description.length }}/500 characters
                            </div>
                        </div>

                        <!-- Tags -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Tags (comma-separated)
                            </label>
                            <input
                                v-model="form.tagsInput"
                                type="text"
                                placeholder="e.g., defi, yield-farming, ethereum"
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:border-blue-500 focus:ring-blue-200 transition-colors"
                                @input="updateTags"
                            />
                            <div v-if="form.tags.length > 0" class="mt-2 flex flex-wrap gap-2">
                                <span
                                    v-for="tag in form.tags"
                                    :key="tag"
                                    class="inline-flex items-center px-2.5 py-1 bg-blue-100 text-blue-800 text-sm rounded-full"
                                >
                                    {{ tag }}
                                    <button
                                        type="button"
                                        @click="removeTag(tag)"
                                        class="ml-1.5 text-blue-600 hover:text-blue-800"
                                    >
                                        <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                                        </svg>
                                    </button>
                                </span>
                            </div>
                        </div>

                        <!-- Advanced Options -->
                        <div class="border-t border-gray-200 pt-6">
                            <button
                                type="button"
                                @click="showAdvancedOptions = !showAdvancedOptions"
                                class="flex items-center justify-between w-full text-left text-sm font-medium text-gray-700 hover:text-gray-900"
                            >
                                <span>Advanced Options</span>
                                <svg :class="{ 'rotate-180': showAdvancedOptions }" class="w-4 h-4 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                </svg>
                            </button>
                            
                            <div v-if="showAdvancedOptions" class="mt-4 space-y-4 pl-4 border-l-2 border-gray-100">
                                <!-- URL Lifetime -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                        Verification URL Lifetime
                                    </label>
                                    <select
                                        v-model="form.urlLifetime"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:border-blue-500 focus:ring-blue-200 text-sm"
                                    >
                                        <option value="1800">30 minutes</option>
                                        <option value="3600">1 hour (recommended)</option>
                                        <option value="7200">2 hours</option>
                                        <option value="14400">4 hours</option>
                                    </select>
                                </div>

                                <!-- Security Options -->
                                <div class="space-y-3">
                                    <label class="block text-sm font-medium text-gray-700">Security Features</label>
                                    <div class="space-y-2">
                                        <label class="flex items-center">
                                            <input
                                                v-model="form.requireIpBinding"
                                                type="checkbox"
                                                class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50"
                                            />
                                            <span class="ml-2 text-sm text-gray-700">Require IP address binding</span>
                                        </label>
                                        <label class="flex items-center">
                                            <input
                                                v-model="form.requireUserAgentBinding"
                                                type="checkbox"
                                                class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50"
                                            />
                                            <span class="ml-2 text-sm text-gray-700">Require user agent binding</span>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Submit Button -->
                        <div class="flex items-center justify-between pt-6">
                            <button
                                type="button"
                                @click="resetForm"
                                class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800 transition-colors"
                            >
                                Reset Form
                            </button>
                            <button
                                type="submit"
                                :disabled="!canSubmit || isGenerating"
                                :class="[
                                    'px-6 py-3 rounded-lg font-medium transition-all duration-200',
                                    canSubmit && !isGenerating
                                        ? 'bg-blue-600 hover:bg-blue-700 text-white shadow-lg hover:shadow-xl transform hover:-translate-y-0.5'
                                        : 'bg-gray-300 text-gray-500 cursor-not-allowed'
                                ]"
                            >
                                <div v-if="isGenerating" class="flex items-center gap-2">
                                    <svg class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="m4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    Generating...
                                </div>
                                <div v-else class="flex items-center gap-2">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                                    </svg>
                                    Generate Secure URL
                                </div>
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Generated URL Display -->
                <div v-if="generatedUrl" class="bg-green-50 border border-green-200 rounded-xl p-6">
                    <div class="flex items-center gap-3 mb-4">
                        <div class="w-8 h-8 bg-green-100 rounded-full flex items-center justify-center">
                            <svg class="w-5 h-5 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-lg font-semibold text-green-800">Verification URL Generated!</h3>
                            <p class="text-sm text-green-600">Click the URL below to complete verification</p>
                        </div>
                    </div>
                    
                    <div class="space-y-4">
                        <!-- URL Display -->
                        <div class="bg-white border border-green-200 rounded-lg p-4">
                            <label class="block text-sm font-medium text-green-700 mb-2">Verification URL</label>
                            <div class="flex items-center gap-2">
                                <input
                                    :value="generatedUrl.verification_url"
                                    readonly
                                    class="flex-1 px-3 py-2 bg-panel border border-gray-200 rounded text-sm font-mono"
                                />
                                <button
                                    @click="copyToClipboard(generatedUrl.verification_url)"
                                    class="px-3 py-2 bg-green-600 text-white rounded hover:bg-green-700 transition-colors"
                                    title="Copy to clipboard"
                                >
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                                    </svg>
                                </button>
                            </div>
                        </div>

                        <!-- URL Info -->
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
                            <div class="bg-white border border-green-200 rounded-lg p-3">
                                <div class="text-green-600 font-medium">Expires In</div>
                                <div class="text-green-800 font-semibold">{{ formatExpirationTime(generatedUrl.expires_at) }}</div>
                            </div>
                            <div class="bg-white border border-green-200 rounded-lg p-3">
                                <div class="text-green-600 font-medium">Security Level</div>
                                <div class="text-green-800 font-semibold">SHA-256 + HMAC</div>
                            </div>
                            <div class="bg-white border border-green-200 rounded-lg p-3">
                                <div class="text-green-600 font-medium">Token ID</div>
                                <div class="text-green-800 font-mono text-xs">{{ generatedUrl.token.substring(0, 16) }}...</div>
                            </div>
                        </div>

                        <!-- Actions -->
                        <div class="flex items-center gap-3">
                            <a
                                :href="generatedUrl.verification_url"
                                target="_blank"
                                class="inline-flex items-center gap-2 px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                                </svg>
                                Verify Now
                            </a>
                            <button
                                @click="shareUrl"
                                class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.367 2.684 3 3 0 00-5.367-2.684z"/>
                                </svg>
                                Share
                            </button>
                            <button
                                @click="generatedUrl = null"
                                class="inline-flex items-center gap-2 px-4 py-2 bg-ink text-gray-700 rounded-lg hover:bg-gray-200 transition-colors"
                            >
                                Generate Another
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Sidebar -->
            <div class="space-y-6">
                
                <!-- Live Stats -->
                <div class="bg-white rounded-xl shadow-lg border border-gray-200 p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">📊 Live Statistics</h3>
                    <div class="space-y-4">
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-gray-600">Total Verified</span>
                            <span class="font-semibold text-gray-900">{{ stats.total_verified || 0 }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-gray-600">Verified Today</span>
                            <span class="font-semibold text-green-600">{{ stats.verified_today || 0 }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-gray-600">This Week</span>
                            <span class="font-semibold text-blue-600">{{ stats.verified_this_week || 0 }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-gray-600">This Month</span>
                            <span class="font-semibold text-purple-600">{{ stats.verified_this_month || 0 }}</span>
                        </div>
                    </div>
                </div>

                <!-- Badge Preview -->
                <div class="bg-white rounded-xl shadow-lg border border-gray-200 p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">🎨 Badge Styles</h3>
                    <div class="space-y-4">
                        <div>
                            <div class="text-sm font-medium text-gray-700 mb-2">Default Badge</div>
                            <SecureVerificationBadge
                                contract-address="0x1234567890123456789012345678901234567890"
                                :auto-verify="false"
                                verified-text="Verified"
                            />
                        </div>
                        <div>
                            <div class="text-sm font-medium text-gray-700 mb-2">Compact Badge</div>
                            <SecureVerificationBadge
                                contract-address="0x1234567890123456789012345678901234567890"
                                variant="compact"
                                :auto-verify="false"
                            />
                        </div>
                        <div>
                            <div class="text-sm font-medium text-gray-700 mb-2">Icon Badge</div>
                            <SecureVerificationBadge
                                contract-address="0x1234567890123456789012345678901234567890"
                                variant="icon"
                                :auto-verify="false"
                            />
                        </div>
                    </div>
                </div>

                <!-- Security Features -->
                <div class="bg-white rounded-xl shadow-lg border border-gray-200 p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">🔐 Security Features</h3>
                    <div class="space-y-3">
                        <div class="flex items-center gap-3">
                            <div class="w-2 h-2 bg-green-500 rounded-full"></div>
                            <span class="text-sm text-gray-700">HMAC-SHA256 Signature</span>
                        </div>
                        <div class="flex items-center gap-3">
                            <div class="w-2 h-2 bg-green-500 rounded-full"></div>
                            <span class="text-sm text-gray-700">Anti-Spoofing Protection</span>
                        </div>
                        <div class="flex items-center gap-3">
                            <div class="w-2 h-2 bg-green-500 rounded-full"></div>
                            <span class="text-sm text-gray-700">Time-based Expiration</span>
                        </div>
                        <div class="flex items-center gap-3">
                            <div class="w-2 h-2 bg-green-500 rounded-full"></div>
                            <span class="text-sm text-gray-700">Nonce Replay Protection</span>
                        </div>
                        <div class="flex items-center gap-3">
                            <div class="w-2 h-2 bg-green-500 rounded-full"></div>
                            <span class="text-sm text-gray-700">Rate Limiting</span>
                        </div>
                        <div class="flex items-center gap-3">
                            <div class="w-2 h-2 bg-green-500 rounded-full"></div>
                            <span class="text-sm text-gray-700">Input Sanitization</span>
                        </div>
                    </div>
                </div>

                <!-- Recent Verifications -->
                <div v-if="recentVerifications.length > 0" class="bg-white rounded-xl shadow-lg border border-gray-200 p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">📋 Recent Verifications</h3>
                    <div class="space-y-3">
                        <div
                            v-for="verification in recentVerifications.slice(0, 5)"
                            :key="verification.contract_address"
                            class="flex items-center gap-3 p-3 bg-panel rounded-lg"
                        >
                            <SecureVerificationBadge
                                :contract-address="verification.contract_address"
                                variant="icon"
                                :auto-verify="false"
                                :show-tooltip="false"
                            />
                            <div class="flex-1 min-w-0">
                                <div class="text-sm font-medium text-gray-900 truncate">
                                    {{ verification.metadata?.project_name || 'Contract' }}
                                </div>
                                <div class="text-xs text-gray-500 font-mono">
                                    {{ truncateAddress(verification.contract_address) }}
                                </div>
                            </div>
                            <div class="text-xs text-gray-400">
                                {{ formatDate(verification.verified_at) }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Notifications -->
        <div v-if="notification.show" :class="notificationClasses" class="fixed bottom-4 right-4 p-4 rounded-lg shadow-lg z-50 max-w-md">
            <div class="flex items-center gap-3">
                <div class="flex-shrink-0">
                    <svg v-if="notification.type === 'success'" class="w-5 h-5 text-green-400" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                    </svg>
                    <svg v-else class="w-5 h-5 text-red-400" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                    </svg>
                </div>
                <div class="flex-1">
                    <p class="text-sm font-medium">{{ notification.message }}</p>
                </div>
                <button @click="hideNotification" class="flex-shrink-0 text-gray-400 hover:text-gray-600">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import axios from 'axios'
import SecureVerificationBadge from './SecureVerificationBadge.vue'

// Props
const props = defineProps({
    initialStats: {
        type: Object,
        default: () => ({})
    },
    initialVerifications: {
        type: Array,
        default: () => []
    }
})

// Reactive state
const stats = ref(props.initialStats)
const recentVerifications = ref(props.initialVerifications)
const isGenerating = ref(false)
const verificationStatus = ref('ready')
const showAdvancedOptions = ref(false)
const generatedUrl = ref(null)
const notification = ref({
    show: false,
    type: 'success',
    message: ''
})

// Form data
const form = ref({
    contractAddress: '',
    projectName: '',
    category: '',
    website: '',
    description: '',
    tagsInput: '',
    tags: [],
    urlLifetime: 3600,
    requireIpBinding: true,
    requireUserAgentBinding: true
})

// Constants
const categories = [
    'DeFi', 'NFT', 'Gaming', 'Infrastructure', 'Governance',
    'Bridge', 'Exchange', 'Lending', 'Yield Farming', 'Insurance',
    'Oracle', 'Other'
]

// Computed
const contractAddressValid = computed(() => {
    return /^0x[a-fA-F0-9]{40}$/.test(form.value.contractAddress)
})

const canSubmit = computed(() => {
    return contractAddressValid.value && !isGenerating.value
})

const notificationClasses = computed(() => {
    return notification.value.type === 'success'
        ? 'bg-green-50 border border-green-200 text-green-800'
        : 'bg-red-50 border border-red-200 text-red-800'
})

// Methods
const validateContractAddress = () => {
    // Reactive validation happens through computed property
}

const updateTags = () => {
    const tags = form.value.tagsInput
        .split(',')
        .map(tag => tag.trim())
        .filter(tag => tag.length > 0 && tag.length <= 30)
        .slice(0, 10) // Max 10 tags
    
    form.value.tags = [...new Set(tags)] // Remove duplicates
}

const removeTag = (tagToRemove) => {
    form.value.tags = form.value.tags.filter(tag => tag !== tagToRemove)
    form.value.tagsInput = form.value.tags.join(', ')
}

const generateVerificationUrl = async () => {
    if (!canSubmit.value) return

    isGenerating.value = true
    verificationStatus.value = 'processing'

    try {
        const metadata = {
            project_name: form.value.projectName,
            category: form.value.category,
            website: form.value.website,
            description: form.value.description,
            tags: form.value.tags
        }

        const options = {
            lifetime: form.value.urlLifetime,
            require_ip_binding: form.value.requireIpBinding,
            require_user_agent_binding: form.value.requireUserAgentBinding
        }

        const response = await axios.post('/enhanced-verification/generate', {
            contract_address: form.value.contractAddress,
            metadata,
            options
        })

        if (response.data.success) {
            generatedUrl.value = response.data.data
            showNotification('Verification URL generated successfully!', 'success')
            
            // Update stats
            await loadStats()
        } else {
            throw new Error(response.data.error || 'Failed to generate verification URL')
        }
    } catch (error) {
        console.error('Failed to generate verification URL:', error)
        showNotification(
            error.response?.data?.error || error.message || 'Failed to generate verification URL',
            'error'
        )
    } finally {
        isGenerating.value = false
        verificationStatus.value = 'ready'
    }
}

const resetForm = () => {
    form.value = {
        contractAddress: '',
        projectName: '',
        category: '',
        website: '',
        description: '',
        tagsInput: '',
        tags: [],
        urlLifetime: 3600,
        requireIpBinding: true,
        requireUserAgentBinding: true
    }
    generatedUrl.value = null
}

const copyToClipboard = async (text) => {
    try {
        await navigator.clipboard.writeText(text)
        showNotification('URL copied to clipboard!', 'success')
    } catch (error) {
        console.error('Failed to copy to clipboard:', error)
        showNotification('Failed to copy URL', 'error')
    }
}

const shareUrl = async () => {
    if (!generatedUrl.value) return

    if (navigator.share) {
        try {
            await navigator.share({
                title: 'Contract Verification URL',
                text: 'Verify this smart contract',
                url: generatedUrl.value.verification_url
            })
        } catch (error) {
            if (error.name !== 'AbortError') {
                copyToClipboard(generatedUrl.value.verification_url)
            }
        }
    } else {
        copyToClipboard(generatedUrl.value.verification_url)
    }
}

const showNotification = (message, type = 'success') => {
    notification.value = {
        show: true,
        type,
        message
    }

    setTimeout(() => {
        hideNotification()
    }, 5000)
}

const hideNotification = () => {
    notification.value.show = false
}

const loadStats = async () => {
    try {
        const response = await axios.get('/api/verification/stats')
        if (response.data.success) {
            stats.value = response.data.data
        }
    } catch (error) {
        console.error('Failed to load stats:', error)
    }
}

const formatExpirationTime = (expiresAt) => {
    const now = new Date()
    const expires = new Date(expiresAt)
    const diff = expires - now
    
    if (diff <= 0) return 'Expired'
    
    const hours = Math.floor(diff / (1000 * 60 * 60))
    const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60))
    
    if (hours > 0) {
        return `${hours}h ${minutes}m`
    } else {
        return `${minutes}m`
    }
}

const truncateAddress = (address) => {
    if (!address) return ''
    return `${address.slice(0, 6)}...${address.slice(-4)}`
}

const formatDate = (dateString) => {
    if (!dateString) return ''
    return new Date(dateString).toLocaleDateString()
}

// Lifecycle
onMounted(() => {
    loadStats()
})
</script>

<style scoped>
.get-verified-dashboard {
    @apply min-h-screen bg-panel p-6;
}

/* Custom animations */
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}

.dashboard-header {
    animation: fadeIn 0.6s ease-out;
}

/* Form focus styles */
.get-verified-dashboard input:focus,
.get-verified-dashboard select:focus,
.get-verified-dashboard textarea:focus {
    @apply ring-2 ring-opacity-50 outline-none transition-all duration-200;
}

/* Custom scrollbar */
.get-verified-dashboard ::-webkit-scrollbar {
    width: 6px;
    height: 6px;
}

.get-verified-dashboard ::-webkit-scrollbar-track {
    @apply bg-ink rounded;
}

.get-verified-dashboard ::-webkit-scrollbar-thumb {
    @apply bg-gray-300 rounded;
}

.get-verified-dashboard ::-webkit-scrollbar-thumb:hover {
    @apply bg-gray-400;
}

/* Responsive design */
@media (max-width: 768px) {
    .get-verified-dashboard {
        @apply p-4;
    }
    
    .dashboard-header {
        @apply p-6;
    }
    
    .dashboard-header h1 {
        @apply text-2xl;
    }
}

/* Dark mode support */
@media (prefers-color-scheme: dark) {
    .get-verified-dashboard {
        @apply bg-gray-900;
    }
}
</style>
