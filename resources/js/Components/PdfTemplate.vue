<template>
    <div class="pdf-template" :class="[`pdf-template--${templateType}`, { 'pdf-mode': pdfMode }]">
        <!-- PDF Header -->
        <div v-if="showHeader" class="pdf-header">
            <div class="pdf-header__content">
                <div class="pdf-header__logo">
                    <h1 class="pdf-header__title">{{ title || 'AI Blockchain Analytics' }}</h1>
                    <p class="pdf-header__subtitle">{{ subtitle || 'Advanced Blockchain Intelligence Platform' }}</p>
                </div>
                <div class="pdf-header__meta">
                    <div class="pdf-header__date">
                        Generated: {{ formatDate(new Date()) }}
                    </div>
                    <div v-if="reportId" class="pdf-header__id">
                        Report ID: {{ reportId }}
                    </div>
                </div>
            </div>
            <div class="pdf-header__divider"></div>
        </div>

        <!-- PDF Content Area -->
        <div class="pdf-content" :style="contentStyles">
            <slot name="content">
                <div class="pdf-placeholder">
                    <h2>PDF Content</h2>
                    <p>No content provided for PDF generation.</p>
                </div>
            </slot>
        </div>

        <!-- PDF Footer -->
        <div v-if="showFooter" class="pdf-footer">
            <div class="pdf-footer__divider"></div>
            <div class="pdf-footer__content">
                <div class="pdf-footer__left">
                    <span class="pdf-footer__company">{{ companyName || 'AI Blockchain Analytics' }}</span>
                    <span v-if="website" class="pdf-footer__website">{{ website }}</span>
                </div>
                <div class="pdf-footer__center">
                    <span v-if="confidentialityLevel" class="pdf-footer__confidentiality">
                        {{ confidentialityLevel }}
                    </span>
                </div>
                <div class="pdf-footer__right">
                    <span class="pdf-footer__page">Page {{ currentPage || 1 }} of {{ totalPages || 1 }}</span>
                </div>
            </div>
        </div>

        <!-- PDF Metadata (hidden but available for parsing) -->
        <div v-if="pdfMode" class="pdf-metadata" style="display: none;">
            <div data-pdf-title="{{ title }}"></div>
            <div data-pdf-author="{{ author }}"></div>
            <div data-pdf-subject="{{ subject }}"></div>
            <div data-pdf-keywords="{{ keywords }}"></div>
            <div data-pdf-creator="AI Blockchain Analytics Platform"></div>
            <div data-pdf-created="{{ new Date().toISOString() }}"></div>
        </div>
    </div>
</template>

<script setup>
import { computed, ref } from 'vue'

// Props
const props = defineProps({
    // Template Configuration
    templateType: {
        type: String,
        default: 'standard',
        validator: value => ['standard', 'dashboard', 'report', 'chart', 'analysis'].includes(value)
    },
    
    // PDF Mode (enables PDF-specific styling and features)
    pdfMode: {
        type: Boolean,
        default: false
    },
    
    // Header Configuration
    showHeader: {
        type: Boolean,
        default: true
    },
    title: {
        type: String,
        default: ''
    },
    subtitle: {
        type: String,
        default: ''
    },
    reportId: {
        type: String,
        default: ''
    },
    
    // Footer Configuration
    showFooter: {
        type: Boolean,
        default: true
    },
    companyName: {
        type: String,
        default: 'AI Blockchain Analytics'
    },
    website: {
        type: String,
        default: 'https://ai-blockchain-analytics.com'
    },
    confidentialityLevel: {
        type: String,
        default: '',
        validator: value => ['', 'Public', 'Internal', 'Confidential', 'Restricted'].includes(value)
    },
    currentPage: {
        type: Number,
        default: 1
    },
    totalPages: {
        type: Number,
        default: 1
    },
    
    // PDF Metadata
    author: {
        type: String,
        default: 'AI Blockchain Analytics'
    },
    subject: {
        type: String,
        default: 'Blockchain Analytics Report'
    },
    keywords: {
        type: String,
        default: 'blockchain,analytics,security,cryptocurrency,AI'
    },
    
    // Styling Options
    contentPadding: {
        type: String,
        default: '2rem'
    },
    backgroundColor: {
        type: String,
        default: '#ffffff'
    },
    fontFamily: {
        type: String,
        default: 'Inter, system-ui, sans-serif'
    },
    
    // Layout Options
    pageSize: {
        type: String,
        default: 'A4',
        validator: value => ['A4', 'A3', 'Letter', 'Legal'].includes(value)
    },
    orientation: {
        type: String,
        default: 'portrait',
        validator: value => ['portrait', 'landscape'].includes(value)
    }
})

// Computed styles
const contentStyles = computed(() => ({
    padding: props.contentPadding,
    backgroundColor: props.backgroundColor,
    fontFamily: props.fontFamily,
    minHeight: props.pdfMode ? 'calc(100vh - 200px)' : 'auto'
}))

// Utility methods
const formatDate = (date) => {
    return new Intl.DateTimeFormat('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        timeZoneName: 'short'
    }).format(date)
}

// Generate unique report ID if not provided
const generateReportId = () => {
    const timestamp = Date.now().toString(36)
    const random = Math.random().toString(36).substr(2, 5)
    return `RPT-${timestamp}-${random}`.toUpperCase()
}

// Auto-generate report ID if not provided
if (!props.reportId && props.pdfMode) {
    const reportId = ref(generateReportId())
}
</script>

<style scoped>
/* Base PDF Template Styles */
.pdf-template {
    @apply w-full min-h-screen bg-white text-gray-900;
    font-family: 'Inter', system-ui, sans-serif;
    line-height: 1.6;
    -webkit-print-color-adjust: exact;
    color-adjust: exact;
}

/* PDF Mode Specific Styles */
.pdf-template.pdf-mode {
    @apply m-0 p-0;
    max-width: none;
    min-height: 100vh;
    box-shadow: none;
    border-radius: 0;
}

/* Header Styles */
.pdf-header {
    @apply border-b border-gray-200 mb-8;
    page-break-inside: avoid;
}

.pdf-header__content {
    @apply flex justify-between items-start p-6;
}

.pdf-header__logo {
    @apply flex-1;
}

.pdf-header__title {
    @apply text-3xl font-bold text-gray-900 mb-1;
    margin: 0;
}

.pdf-header__subtitle {
    @apply text-lg text-gray-600 m-0;
}

.pdf-header__meta {
    @apply text-right text-sm text-gray-500;
}

.pdf-header__date,
.pdf-header__id {
    @apply mb-1;
}

.pdf-header__divider {
    @apply h-px bg-gradient-to-r from-blue-500 via-purple-500 to-green-500;
}

/* Content Styles */
.pdf-content {
    @apply flex-1;
    page-break-inside: auto;
}

.pdf-placeholder {
    @apply text-center py-16;
}

.pdf-placeholder h2 {
    @apply text-2xl font-semibold text-gray-700 mb-4;
}

.pdf-placeholder p {
    @apply text-gray-500;
}

/* Footer Styles */
.pdf-footer {
    @apply mt-8;
    page-break-inside: avoid;
}

.pdf-footer__divider {
    @apply h-px bg-gray-200 mb-4;
}

.pdf-footer__content {
    @apply flex justify-between items-center px-6 py-4 text-xs text-gray-500;
}

.pdf-footer__left {
    @apply flex flex-col space-y-1;
}

.pdf-footer__center {
    @apply flex-1 text-center;
}

.pdf-footer__right {
    @apply text-right;
}

.pdf-footer__company {
    @apply font-semibold text-gray-700;
}

.pdf-footer__website {
    @apply text-blue-600;
}

.pdf-footer__confidentiality {
    @apply inline-block px-3 py-1 bg-red-100 text-red-800 rounded-full text-xs font-medium;
}

.pdf-footer__page {
    @apply font-medium;
}

/* Template Type Variations */
.pdf-template--dashboard {
    @apply bg-panel;
}

.pdf-template--dashboard .pdf-content {
    @apply bg-white m-4 p-6 rounded-lg shadow-sm;
}

.pdf-template--report .pdf-header__title {
    @apply text-4xl;
}

.pdf-template--chart .pdf-content {
    @apply p-4;
}

.pdf-template--analysis .pdf-header {
    @apply bg-gradient-to-r from-blue-50 to-purple-50;
}

/* Print and PDF Specific Styles */
@media print, (prefers-color-scheme: no-preference) {
    .pdf-template {
        @apply m-0 p-0 bg-white;
        font-size: 12pt;
        line-height: 1.5;
    }
    
    .pdf-header,
    .pdf-footer {
        -webkit-print-color-adjust: exact;
        color-adjust: exact;
    }
    
    .pdf-content {
        orphans: 3;
        widows: 3;
    }
    
    h1, h2, h3, h4, h5, h6 {
        page-break-after: avoid;
        break-after: avoid;
    }
    
    img, svg, canvas {
        page-break-inside: avoid;
        break-inside: avoid;
    }
}

/* Page Size Specific Styles */
.pdf-template[data-page-size="A4"] {
    width: 210mm;
    min-height: 297mm;
}

.pdf-template[data-page-size="A3"] {
    width: 297mm;
    min-height: 420mm;
}

.pdf-template[data-page-size="Letter"] {
    width: 8.5in;
    min-height: 11in;
}

.pdf-template[data-page-size="Legal"] {
    width: 8.5in;
    min-height: 14in;
}

/* Landscape Orientation */
.pdf-template[data-orientation="landscape"] {
    page-orientation: landscape;
}

/* Dark Mode Support (for screen viewing) */
@media (prefers-color-scheme: dark) {
    .pdf-template:not(.pdf-mode) {
        @apply bg-gray-900 text-gray-100;
    }
    
    .pdf-template:not(.pdf-mode) .pdf-header {
        @apply border-gray-700;
    }
    
    .pdf-template:not(.pdf-mode) .pdf-header__title {
        @apply text-gray-100;
    }
    
    .pdf-template:not(.pdf-mode) .pdf-header__subtitle {
        @apply text-gray-300;
    }
    
    .pdf-template:not(.pdf-mode) .pdf-footer__divider {
        @apply bg-gray-700;
    }
}

/* Animation for loading states */
.pdf-template.loading {
    @apply opacity-75;
}

.pdf-template.loading .pdf-content {
    @apply animate-pulse;
}

/* Accessibility improvements */
.pdf-template {
    scroll-behavior: smooth;
}

.pdf-template:focus-within {
    @apply ring-2 ring-blue-500 ring-opacity-50;
}

/* High contrast mode */
@media (prefers-contrast: high) {
    .pdf-template {
        @apply border-2 border-gray-900;
    }
    
    .pdf-header__divider {
        @apply bg-gray-900;
        height: 2px;
    }
}

/* Reduced motion */
@media (prefers-reduced-motion: reduce) {
    .pdf-template * {
        animation-duration: 0.01ms !important;
        animation-iteration-count: 1 !important;
        transition-duration: 0.01ms !important;
    }
}
</style>
