<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const props = defineProps({
    preferences: {
        type: Object,
        required: true,
    },
    stats: {
        type: Object,
        required: true,
    },
    pdf_mode: {
        type: Boolean,
        default: false,
    },
});

const form = useForm({
    marketing_emails: props.preferences.marketing_emails,
    product_updates: props.preferences.product_updates,
    security_alerts: props.preferences.security_alerts,
    onboarding_emails: props.preferences.onboarding_emails,
    weekly_digest: props.preferences.weekly_digest,
    frequency: props.preferences.frequency || 'normal',
});

// PDF generation state
const pdfGenerating = ref(false);
const pdfError = ref(null);
const pdfSuccess = ref(null);

// Computed for PDF-friendly styling
const containerClasses = computed(() => {
    return props.pdf_mode 
        ? 'max-w-none' 
        : 'mx-auto max-w-7xl sm:px-6 lg:px-8';
});

const submit = () => {
    form.patch(route('email.preferences.update'), {
        preserveScroll: true,
    });
};

// PDF Generation Functions
const generatePdf = async (engine = 'auto') => {
    try {
        pdfGenerating.value = true;
        pdfError.value = null;
        pdfSuccess.value = null;

        const response = await fetch(route('email.preferences.pdf'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content'),
            },
            body: JSON.stringify({
                engine: engine,
                format: 'A4',
                orientation: 'portrait',
                filename: `email-preferences-${new Date().toISOString().split('T')[0]}.pdf`
            })
        });

        const result = await response.json();

        if (result.success) {
            pdfSuccess.value = `PDF generated successfully using ${result.data.engine_used}`;
            
            // Auto-download the PDF
            if (result.data.download_url) {
                window.open(result.data.download_url, '_blank');
            } else if (result.data.filename) {
                window.open(route('email.preferences.pdf.download', { filename: result.data.filename }), '_blank');
            }
        } else {
            throw new Error(result.message || 'PDF generation failed');
        }
    } catch (error) {
        console.error('PDF generation error:', error);
        pdfError.value = error.message || 'Failed to generate PDF';
    } finally {
        pdfGenerating.value = false;
        
        // Clear success/error messages after 5 seconds
        setTimeout(() => {
            pdfSuccess.value = null;
            pdfError.value = null;
        }, 5000);
    }
};

const generatePdfWithEngine = (engine) => {
    generatePdf(engine);
};
</script>

<template>
    <Head title="Email Preferences" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                Email Preferences
            </h2>
        </template>

        <div class="py-12" :class="pdf_mode ? 'py-6' : 'py-12'">
            <div :class="[containerClasses, 'space-y-6']">
                <!-- PDF Generation Controls (hidden in PDF mode) -->
                <div v-if="!pdf_mode" class="bg-white p-4 shadow sm:rounded-lg sm:p-8">
                    <div class="max-w-xl">
                        <section>
                            <header>
                                <h2 class="text-lg font-medium text-gray-900">Export to PDF</h2>
                                <p class="mt-1 text-sm text-gray-600">
                                    Generate a PDF report of your email preferences and statistics.
                                </p>
                            </header>

                            <div class="mt-6 flex flex-wrap gap-3">
                                <button
                                    @click="generatePdf('auto')"
                                    :disabled="pdfGenerating"
                                    class="rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-blue-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600 disabled:opacity-50 disabled:cursor-not-allowed"
                                >
                                    <span v-if="pdfGenerating" class="inline-flex items-center">
                                        <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                        </svg>
                                        Generating...
                                    </span>
                                    <span v-else>Generate PDF (Auto)</span>
                                </button>
                                
                                <button
                                    @click="generatePdfWithEngine('browserless')"
                                    :disabled="pdfGenerating"
                                    class="rounded-md bg-green-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-green-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-green-600 disabled:opacity-50 disabled:cursor-not-allowed"
                                >
                                    High Quality (Browserless)
                                </button>
                                
                                <button
                                    @click="generatePdfWithEngine('dompdf')"
                                    :disabled="pdfGenerating"
                                    class="rounded-md bg-purple-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-purple-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-purple-600 disabled:opacity-50 disabled:cursor-not-allowed"
                                >
                                    Fast (DomPDF)
                                </button>
                            </div>
                            
                            <!-- PDF Status Messages -->
                            <div v-if="pdfSuccess" class="mt-4 rounded-md bg-green-50 p-4">
                                <div class="flex">
                                    <div class="flex-shrink-0">
                                        <svg class="h-5 w-5 text-green-400" viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                                        </svg>
                                    </div>
                                    <div class="ml-3">
                                        <p class="text-sm font-medium text-green-800">{{ pdfSuccess }}</p>
                                    </div>
                                </div>
                            </div>
                            
                            <div v-if="pdfError" class="mt-4 rounded-md bg-red-50 p-4">
                                <div class="flex">
                                    <div class="flex-shrink-0">
                                        <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
                                        </svg>
                                    </div>
                                    <div class="ml-3">
                                        <p class="text-sm font-medium text-red-800">{{ pdfError }}</p>
                                    </div>
                                </div>
                            </div>
                        </section>
                    </div>
                </div>
                <!-- Email Statistics Card -->
                <div class="bg-white p-4 shadow sm:rounded-lg sm:p-8 email-preferences-loaded">
                    <div class="max-w-xl">
                        <section>
                            <header>
                                <h2 class="text-lg font-medium text-gray-900">Email Statistics</h2>
                                <p class="mt-1 text-sm text-gray-600">
                                    Your email engagement overview.
                                </p>
                            </header>

                            <div class="mt-6 grid grid-cols-2 gap-4 sm:grid-cols-4">
                                <div class="bg-panel p-4 rounded-lg text-center">
                                    <div class="text-2xl font-bold text-gray-900">{{ stats.total_sent }}</div>
                                    <div class="text-sm text-gray-600">Total Sent</div>
                                </div>
                                <div class="bg-green-50 p-4 rounded-lg text-center">
                                    <div class="text-2xl font-bold text-green-900">{{ stats.delivered }}</div>
                                    <div class="text-sm text-green-600">Delivered</div>
                                </div>
                                <div class="bg-blue-50 p-4 rounded-lg text-center">
                                    <div class="text-2xl font-bold text-blue-900">{{ stats.opened }}</div>
                                    <div class="text-sm text-blue-600">Opened</div>
                                </div>
                                <div class="bg-purple-50 p-4 rounded-lg text-center">
                                    <div class="text-2xl font-bold text-purple-900">{{ stats.clicked }}</div>
                                    <div class="text-sm text-purple-600">Clicked</div>
                                </div>
                            </div>

                            <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div class="text-center">
                                    <div class="text-lg font-semibold text-gray-900">{{ stats.open_rate }}%</div>
                                    <div class="text-sm text-gray-600">Open Rate</div>
                                </div>
                                <div class="text-center">
                                    <div class="text-lg font-semibold text-gray-900">{{ stats.click_rate }}%</div>
                                    <div class="text-sm text-gray-600">Click Rate</div>
                                </div>
                            </div>
                        </section>
                    </div>
                </div>

                <!-- Email Preferences Form -->
                <div class="bg-white p-4 shadow sm:rounded-lg sm:p-8">
                    <div class="max-w-xl">
                        <section>
                            <header>
                                <h2 class="text-lg font-medium text-gray-900">Email Preferences</h2>
                                <p class="mt-1 text-sm text-gray-600">
                                    Control what types of emails you receive from us.
                                </p>
                            </header>

                            <form @submit.prevent="submit" class="mt-6 space-y-6">
                                <!-- Email Type Preferences -->
                                <div class="space-y-4">
                                    <div class="flex items-center">
                                        <input
                                            id="marketing_emails"
                                            type="checkbox"
                                            v-model="form.marketing_emails"
                                            class="h-4 w-4 rounded border-gray-300 text-brand-500 focus:ring-indigo-600"
                                        />
                                        <label for="marketing_emails" class="ml-3 block text-sm font-medium leading-6 text-gray-900">
                                            Marketing Emails
                                        </label>
                                    </div>
                                    <div class="text-sm text-gray-600 ml-7">
                                        Receive updates about new features, promotions, and blockchain insights.
                                    </div>

                                    <div class="flex items-center">
                                        <input
                                            id="product_updates"
                                            type="checkbox"
                                            v-model="form.product_updates"
                                            class="h-4 w-4 rounded border-gray-300 text-brand-500 focus:ring-indigo-600"
                                        />
                                        <label for="product_updates" class="ml-3 block text-sm font-medium leading-6 text-gray-900">
                                            Product Updates
                                        </label>
                                    </div>
                                    <div class="text-sm text-gray-600 ml-7">
                                        Get notified when we release new tools and improvements.
                                    </div>

                                    <div class="flex items-center">
                                        <input
                                            id="security_alerts"
                                            type="checkbox"
                                            v-model="form.security_alerts"
                                            class="h-4 w-4 rounded border-gray-300 text-brand-500 focus:ring-indigo-600"
                                        />
                                        <label for="security_alerts" class="ml-3 block text-sm font-medium leading-6 text-gray-900">
                                            Security Alerts
                                        </label>
                                    </div>
                                    <div class="text-sm text-gray-600 ml-7">
                                        Important security notifications and account alerts. (Recommended)
                                    </div>

                                    <div class="flex items-center">
                                        <input
                                            id="onboarding_emails"
                                            type="checkbox"
                                            v-model="form.onboarding_emails"
                                            class="h-4 w-4 rounded border-gray-300 text-brand-500 focus:ring-indigo-600"
                                        />
                                        <label for="onboarding_emails" class="ml-3 block text-sm font-medium leading-6 text-gray-900">
                                            Onboarding Emails
                                        </label>
                                    </div>
                                    <div class="text-sm text-gray-600 ml-7">
                                        Helpful tips and tutorials to get you started.
                                    </div>

                                    <div class="flex items-center">
                                        <input
                                            id="weekly_digest"
                                            type="checkbox"
                                            v-model="form.weekly_digest"
                                            class="h-4 w-4 rounded border-gray-300 text-brand-500 focus:ring-indigo-600"
                                        />
                                        <label for="weekly_digest" class="ml-3 block text-sm font-medium leading-6 text-gray-900">
                                            Weekly Digest
                                        </label>
                                    </div>
                                    <div class="text-sm text-gray-600 ml-7">
                                        Weekly summary of blockchain trends and your activity.
                                    </div>
                                </div>

                                <!-- Frequency Selection -->
                                <div>
                                    <label for="frequency" class="block text-sm font-medium leading-6 text-gray-900">
                                        Email Frequency
                                    </label>
                                    <select
                                        id="frequency"
                                        v-model="form.frequency"
                                        class="mt-2 block w-full rounded-md border-0 py-1.5 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6"
                                    >
                                        <option value="low">Low - Essential emails only</option>
                                        <option value="normal">Normal - Regular updates</option>
                                        <option value="high">High - All notifications</option>
                                    </select>
                                </div>

                                <div class="flex items-center gap-4">
                                    <button
                                        type="submit"
                                        :disabled="form.processing"
                                        class="rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 disabled:opacity-50"
                                    >
                                        Save Preferences
                                    </button>

                                    <div v-if="form.recentlySuccessful" class="text-sm text-green-600">
                                        Preferences saved successfully!
                                    </div>
                                </div>

                                <div v-if="form.hasErrors" class="text-sm text-red-600">
                                    <div v-for="error in Object.values(form.errors)" :key="error">
                                        {{ error }}
                                    </div>
                                </div>
                            </form>
                        </section>
                    </div>
                </div>

                <!-- Last Updated Info -->
                <div class="bg-white p-4 shadow sm:rounded-lg sm:p-8">
                    <div class="max-w-xl">
                        <div class="text-sm text-gray-600">
                            <span v-if="preferences.last_updated">
                                Last updated: {{ new Date(preferences.last_updated).toLocaleDateString() }}
                            </span>
                            <span v-else>
                                No preferences set yet.
                            </span>
                            
                            <!-- PDF Generation Timestamp -->
                            <div v-if="pdf_mode" class="mt-2 pt-2 border-t border-gray-200">
                                <span class="text-xs text-gray-500">
                                    Report generated on {{ new Date().toLocaleString() }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
/* PDF-specific styles */
@media print {
    .bg-white {
        background: white !important;
        box-shadow: none !important;
    }
    
    .shadow {
        box-shadow: none !important;
    }
    
    .rounded-lg {
        border-radius: 0 !important;
    }
    
    /* Ensure good contrast for PDF */
    .text-gray-900 {
        color: #000 !important;
    }
    
    .text-gray-600 {
        color: #374151 !important;
    }
    
    .text-gray-500 {
        color: #6b7280 !important;
    }
    
    /* Hide interactive elements in print */
    button,
    input[type="checkbox"],
    select {
        display: none !important;
    }
    
    /* Show form values instead of inputs */
    .form-value {
        display: block !important;
        font-weight: 500;
        color: #000;
    }
}

/* PDF mode styles (for Browserless rendering) */
.pdf-mode {
    background: white;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

.pdf-mode .bg-white {
    background: white !important;
    box-shadow: none !important;
    border: 1px solid #e5e7eb;
}

.pdf-mode .shadow {
    box-shadow: none !important;
}

.pdf-mode .text-gray-900 {
    color: #111827 !important;
}

.pdf-mode .text-gray-600 {
    color: #4b5563 !important;
}

/* Loading indicator for PDF generation */
.email-preferences-loaded {
    opacity: 1;
    transition: opacity 0.3s ease;
}
</style>