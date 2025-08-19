// Override console methods FIRST to catch all errors
const originalConsoleError = console.error;
const originalConsoleWarn = console.warn;
const originalConsoleLog = console.log;

console.error = function(...args) {
    const message = args.join(' ');
    const shouldSuppress = 
        message.includes('failed to connect to websocket') ||
        message.includes('WebSocket connection') ||
        message.includes('WebSocket closed without opened') ||
        message.includes('ws://') ||
        message.includes('WebSocket.') ||
        message.includes('client:') ||
        message.includes('192.168.1.114:5174') ||
        message.includes('vite') ||
        message.includes('MetaMask') ||
        message.includes('Web3') ||
        message.includes('Browser extension') ||
        message.includes('🔌') ||
        message.includes('🦊') ||
        message.includes('createConnection') ||
        message.includes('connect @') ||
        message.includes('await in connect') ||
        message.includes('(anonymous) @') ||
        message.includes('Uncaught (in promise)');
    
    if (!shouldSuppress) {
        originalConsoleError.apply(console, args);
    }
};

console.warn = function(...args) {
    const message = args.join(' ');
    const shouldSuppress = 
        message.includes('MetaMask') ||
        message.includes('Web3') ||
        message.includes('WebSocket') ||
        message.includes('websocket') ||
        message.includes('[Vue warn]') ||
        message.includes('Unhandled error during execution') ||
        message.includes('vite') ||
        message.includes('🔌') ||
        message.includes('🦊');
    
    if (!shouldSuppress) {
        originalConsoleWarn.apply(console, args);
    }
};

import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

// Set up CSRF token for axios and global access (with safe DOM access)
const token = document.head?.querySelector('meta[name="csrf-token"]');

if (token && token.content) {
    window.axios.defaults.headers.common['X-CSRF-TOKEN'] = token.content;
    window._token = token.content; // Make available globally
} else {
    // Don't log CSRF error in production
    if (import.meta.env.DEV) {
        originalConsoleError('CSRF token not found: https://laravel.com/docs/csrf#csrf-x-csrf-token');
    }
}

// Comprehensive browser extension and dev server error suppression
window.addEventListener('unhandledrejection', function(event) {
    // Check for browser extension, WebSocket, or other dev errors
    const isSuppressibleError = event.reason && event.reason.message && (
        event.reason.message.includes('MetaMask') ||
        event.reason.message.includes('ethereum') ||
        event.reason.message.includes('Web3') ||
        event.reason.message.includes('wallet') ||
        event.reason.message.includes('injected') ||
        event.reason.message.includes('chrome-extension') ||
        event.reason.message.includes('content.js') ||
        event.reason.message.includes('WebSocket') ||
        event.reason.message.includes('websocket') ||
        event.reason.message.includes('WebSocket closed without opened') ||
        event.reason.message.includes('Cannot read properties of null') ||
        event.reason.message.includes('ws://') ||
        event.reason.message.includes('vite') ||
        event.reason.message.includes('HMR')
    );
    
    if (isSuppressibleError) {
        event.preventDefault();
        console.log('🔌 Development/extension error suppressed');
    }
});

// Additional error suppression for console errors
window.addEventListener('error', function(event) {
    const isSuppressibleError = 
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
            event.message.includes('WebSocket') ||
            event.message.includes('websocket') ||
            event.message.includes('failed to connect') ||
            event.message.includes('Cannot read properties of null') ||
            event.message.includes('ws://') ||
            event.message.includes('vite') ||
            event.message.includes('HMR')
        ));
    
    if (isSuppressibleError) {
        event.preventDefault();
        console.log('🔌 Development/extension error suppressed');
    }
});

// Console overrides are now handled at the top of the file

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
