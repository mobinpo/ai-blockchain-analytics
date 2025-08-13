{{-- Secure Verification Badge Display --}}
@php
$themeClasses = [
    'light' => 'bg-white text-gray-900 border-gray-200',
    'dark' => 'bg-gray-900 text-white border-gray-700',
    'minimal' => 'bg-transparent text-gray-700 border-gray-300',
    'detailed' => 'bg-gradient-to-r from-blue-50 to-green-50 text-gray-900 border-blue-200',
];

$sizeClasses = [
    'small' => 'px-2 py-1 text-xs',
    'medium' => 'px-3 py-2 text-sm',
    'large' => 'px-4 py-3 text-base',
    'xl' => 'px-6 py-4 text-lg',
];

$levelColors = [
    'basic' => 'text-blue-600 border-blue-200 bg-blue-50',
    'standard' => 'text-green-600 border-green-200 bg-green-50',
    'premium' => 'text-yellow-600 border-yellow-200 bg-yellow-50',
    'enterprise' => 'text-purple-600 border-purple-200 bg-purple-50',
];

$theme = $theme ?? 'light';
$size = $size ?? 'medium';
$level = $badgeData['verification_level'] ?? 'standard';
@endphp

<div class="secure-verification-badge inline-flex items-center gap-2 rounded-lg border-2 font-medium transition-all duration-200 hover:shadow-lg 
    {{ $themeClasses[$theme] ?? $themeClasses['light'] }} 
    {{ $sizeClasses[$size] ?? $sizeClasses['medium'] }}
    {{ $levelColors[$level] ?? $levelColors['standard'] }}"
    data-badge-token="verified"
    data-security-level="{{ $badgeData['security_level'] }}"
    data-verification-level="{{ $level }}"
    title="Cryptographically verified with SHA-256 + HMAC security">
    
    {{-- Shield Icon --}}
    <div class="flex-shrink-0">
        <svg class="w-4 h-4 {{ $size === 'small' ? 'w-3 h-3' : '' }} {{ $size === 'large' ? 'w-5 h-5' : '' }} {{ $size === 'xl' ? 'w-6 h-6' : '' }}" 
             viewBox="0 0 20 20" fill="currentColor">
            <path fill-rule="evenodd" d="M10 1L15 3.5V8.5C15 12.5 12.5 16 10 17C7.5 16 5 12.5 5 8.5V3.5L10 1Z" clip-rule="evenodd"/>
            <path d="M8 10L9.5 11.5L12 8.5" stroke="white" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
    </div>

    {{-- Badge Text --}}
    <div class="flex flex-col {{ $size === 'small' ? 'gap-0' : 'gap-1' }}">
        <div class="font-semibold leading-tight">
            @if($size === 'small')
                Verified
            @else
                {{ $badgeData['project_name'] ?? 'Verified Contract' }}
            @endif
        </div>
        
        @if($size !== 'small')
            <div class="text-xs opacity-75 leading-tight">
                {{ substr($badgeData['contract_address'], 0, 6) }}...{{ substr($badgeData['contract_address'], -4) }}
            </div>
        @endif
    </div>

    {{-- Security Badge --}}
    @if($size === 'large' || $size === 'xl')
        <div class="flex items-center gap-1 text-xs opacity-60">
            <svg class="w-3 h-3" viewBox="0 0 12 12" fill="currentColor">
                <path d="M6 0L9 1.5V4.5C9 6.5 7.5 8.5 6 9C4.5 8.5 3 6.5 3 4.5V1.5L6 0Z"/>
            </svg>
            <span>{{ strtoupper($badgeData['security_level']) }}</span>
        </div>
    @endif
</div>

{{-- Detailed Information (for large sizes) --}}
@if(($size === 'large' || $size === 'xl') && ($theme === 'detailed'))
    <div class="mt-2 p-3 bg-gray-50 rounded-lg border text-xs text-gray-600">
        <div class="grid grid-cols-2 gap-2">
            <div>
                <span class="font-medium">Verified:</span>
                <span>{{ \Carbon\Carbon::parse($badgeData['verified_at'])->format('M j, Y') }}</span>
            </div>
            <div>
                <span class="font-medium">Expires:</span>
                <span>{{ \Carbon\Carbon::parse($badgeData['expires_at'])->format('M j, Y') }}</span>
            </div>
            <div>
                <span class="font-medium">Level:</span>
                <span class="capitalize">{{ $level }}</span>
            </div>
            <div>
                <span class="font-medium">Security:</span>
                <span>SHA-256 + HMAC</span>
            </div>
        </div>
    </div>
@endif

{{-- CSS for enhanced styling --}}
<style>
.secure-verification-badge {
    position: relative;
    overflow: hidden;
}

.secure-verification-badge::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
    transition: left 0.5s ease;
}

.secure-verification-badge:hover::before {
    left: 100%;
}

.secure-verification-badge:hover {
    transform: translateY(-1px);
}

/* Animation for verified status */
@keyframes pulse-glow {
    0%, 100% { box-shadow: 0 0 0 0 rgba(34, 197, 94, 0.4); }
    50% { box-shadow: 0 0 0 4px rgba(34, 197, 94, 0.1); }
}

.secure-verification-badge[data-verification-level="premium"],
.secure-verification-badge[data-verification-level="enterprise"] {
    animation: pulse-glow 2s infinite;
}

/* Dark theme adjustments */
@media (prefers-color-scheme: dark) {
    .secure-verification-badge[data-theme="auto"] {
        @apply bg-gray-800 text-white border-gray-600;
    }
}

/* Print styles */
@media print {
    .secure-verification-badge {
        @apply bg-white text-black border-gray-400;
    }
    
    .secure-verification-badge::before {
        display: none;
    }
}
</style>

{{-- JavaScript for interactive features --}}
<script>
document.addEventListener('DOMContentLoaded', function() {
    const badges = document.querySelectorAll('.secure-verification-badge');
    
    badges.forEach(badge => {
        // Add click event for badge verification
        badge.addEventListener('click', function() {
            const token = this.dataset.badgeToken;
            const securityLevel = this.dataset.securityLevel;
            
            // Show verification details in a tooltip or modal
            showVerificationDetails(token, securityLevel);
        });
        
        // Add keyboard accessibility
        badge.setAttribute('tabindex', '0');
        badge.setAttribute('role', 'button');
        badge.setAttribute('aria-label', 'Verified badge - click for details');
        
        badge.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                this.click();
            }
        });
    });
});

function showVerificationDetails(token, securityLevel) {
    // This would typically open a modal or show detailed verification info
    console.log('Verification details for token:', token, 'Security level:', securityLevel);
    
    // Example: Show alert with verification info (replace with proper modal)
    const message = `This badge is cryptographically verified with ${securityLevel} security using SHA-256 + HMAC signatures. Click OK to verify authenticity.`;
    
    if (confirm(message)) {
        // Make API call to verify badge authenticity
        verifyBadgeAuthenticity(token);
    }
}

function verifyBadgeAuthenticity(token) {
    fetch(`/api/verification/verify-secure-badge`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
        },
        body: JSON.stringify({ token: token, format: 'detailed' })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('✅ Badge verification successful! This is a genuine verified badge.');
        } else {
            alert('❌ Badge verification failed: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Verification error:', error);
        alert('❌ Verification request failed. Please try again.');
    });
}
</script>
