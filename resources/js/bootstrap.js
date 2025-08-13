import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

// Set up CSRF token for axios and global access (with safe DOM access)
const token = document.head?.querySelector('meta[name="csrf-token"]');

if (token && token.content) {
    window.axios.defaults.headers.common['X-CSRF-TOKEN'] = token.content;
    window._token = token.content; // Make available globally
} else {
    console.error('CSRF token not found: https://laravel.com/docs/csrf#csrf-x-csrf-token');
}

// Comprehensive browser extension error suppression
window.addEventListener('unhandledrejection', function(event) {
    // Check for browser extension or third-party script errors
    const isExtensionError = event.reason && event.reason.message && (
        event.reason.message.includes('MetaMask') ||
        event.reason.message.includes('ethereum') ||
        event.reason.message.includes('Web3') ||
        event.reason.message.includes('wallet') ||
        event.reason.message.includes('injected') ||
        event.reason.message.includes('chrome-extension') ||
        event.reason.message.includes('content.js') ||
        event.reason.message.includes('Cannot read properties of null')
    );
    
    if (isExtensionError) {
        event.preventDefault();
        console.log('🔌 Browser extension error suppressed in bootstrap');
    }
});

// Additional error suppression for console errors
window.addEventListener('error', function(event) {
    const isExtensionError = 
        (event.filename && (
            event.filename.includes('chrome-extension://') ||
            event.filename.includes('content.js') ||
            event.filename.includes('inject')
        )) ||
        (event.message && (
            event.message.includes('MetaMask') ||
            event.message.includes('ethereum') ||
            event.message.includes('Web3') ||
            event.message.includes('chrome-extension') ||
            event.message.includes('Cannot read properties of null')
        ));
    
    if (isExtensionError) {
        event.preventDefault();
        console.log('🔌 Browser extension error suppressed in bootstrap');
    }
});

// Prevent any automatic MetaMask connection attempts
if (typeof window !== 'undefined') {
    // Only block ethereum object if it doesn't already exist
    if (!window.hasOwnProperty('ethereum')) {
        try {
            Object.defineProperty(window, 'ethereum', {
                get() {
                    console.log('MetaMask access blocked - not needed for this application');
                    return undefined;
                },
                set() {
                    // Ignore attempts to set window.ethereum
                }
            });
        } catch (e) {
            // If property already exists and can't be redefined, just log it
            console.log('MetaMask already present - application will work without web3 functionality');
        }
    } else {
        console.log('MetaMask detected - application will work without web3 functionality');
    }
}
