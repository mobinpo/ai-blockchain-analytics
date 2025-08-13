{{-- Error Badge Display --}}
<div class="error-verification-badge inline-flex items-center gap-2 px-3 py-2 rounded-lg border-2 border-red-200 bg-red-50 text-red-700 text-sm font-medium transition-all duration-200"
     title="Verification Error: {{ $error }}">
    
    {{-- Error Icon --}}
    <div class="flex-shrink-0">
        <svg class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor">
            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
        </svg>
    </div>

    {{-- Error Text --}}
    <div class="flex flex-col gap-1">
        <div class="font-semibold leading-tight">
            Verification Failed
        </div>
        <div class="text-xs opacity-75 leading-tight">
            {{ $error }}
        </div>
    </div>
</div>

<style>
.error-verification-badge {
    animation: pulse-error 2s infinite;
}

@keyframes pulse-error {
    0%, 100% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.4); }
    50% { box-shadow: 0 0 0 4px rgba(239, 68, 68, 0.1); }
}
</style>
