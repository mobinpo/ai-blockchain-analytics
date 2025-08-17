<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="payment-method-type" content="credit-card">
        <meta name="supported-payment-networks" content="visa,mastercard,amex,discover,diners,jcb,unionpay">

        <title inertia>{{ config('app.name', 'Laravel') }}</title>

        <!-- Favicon and Meta Images -->
        <link rel="icon" type="image/svg+xml" href="/brand/favicon.svg">
        <link rel="alternate icon" type="image/png" href="/brand/favicon.png">
        <meta property="og:image" content="/brand/og.png">
        <meta name="theme-color" content="#00E7FF">
        <meta name="description" content="Sentiment Shield - AI-powered blockchain analytics and security platform">

        <!-- Complete Error Suppression for Browser Extensions -->
        <script>
            // Comprehensive error suppression for browser extensions and third-party scripts
            (function() {
                'use strict';
                
                // Global error handler for browser extension errors
                window.addEventListener('error', function(event) {
                    // Check if error is from browser extension or external source
                    const isExtensionError = 
                        (event.filename && (
                            event.filename.includes('chrome-extension://') ||
                            event.filename.includes('moz-extension://') ||
                            event.filename.includes('safari-extension://') ||
                            event.filename.includes('content.js') ||
                            event.filename.includes('inject')
                        )) ||
                        (event.message && (
                            event.message.includes('MetaMask') ||
                            event.message.includes('ethereum') ||
                            event.message.includes('Cannot read properties of null') ||
                            event.message.includes('chrome-extension') ||
                            event.message.includes('Extension context invalidated')
                        ));
                    
                    if (isExtensionError) {
                        console.log('%c🔌 Browser extension error suppressed', 'color: #666; font-style: italic;');
                        event.preventDefault();
                        event.stopPropagation();
                        return false;
                    }
                }, true);

                // Handle unhandled promise rejections
                window.addEventListener('unhandledrejection', function(event) {
                    const isExtensionError = 
                        (event.reason && event.reason.message && (
                            event.reason.message.includes('MetaMask') ||
                            event.reason.message.includes('ethereum') ||
                            event.reason.message.includes('chrome-extension') ||
                            event.reason.message.includes('Failed to connect') ||
                            event.reason.message.includes('Cannot read properties of null')
                        )) ||
                        (event.reason && event.reason.stack && (
                            event.reason.stack.includes('chrome-extension://') ||
                            event.reason.stack.includes('content.js')
                        ));
                    
                    if (isExtensionError) {
                        console.log('%c🔌 Browser extension promise rejection suppressed', 'color: #666; font-style: italic;');
                        event.preventDefault();
                        return false;
                    }
                });

                // Override console.error to suppress extension errors
                const originalConsoleError = console.error;
                console.error = function(...args) {
                    const message = args.join(' ');
                    if (message.includes('chrome-extension') || 
                        message.includes('content.js') || 
                        message.includes('MetaMask') ||
                        message.includes('Cannot read properties of null')) {
                        console.log('%c🔌 Extension console error suppressed:', 'color: #666; font-style: italic;', message);
                        return;
                    }
                    return originalConsoleError.apply(console, args);
                };

                // Block ethereum object completely
                try {
                    Object.defineProperty(window, 'ethereum', {
                        get() { 
                            console.log('%c🦊 MetaMask access blocked', 'color: orange;');
                            return undefined; 
                        },
                        set() { 
                            console.log('%c🦊 MetaMask injection blocked', 'color: orange;');
                            return false; 
                        },
                        configurable: false,
                        enumerable: false
                    });
                } catch (e) {
                    // If ethereum already exists, just log it
                    console.log('%c🦊 MetaMask already present - errors will be suppressed', 'color: orange;');
                }

                // Set flags to indicate error suppression is active
                window.__WEB3_DISABLED__ = true;
                window.__EXTENSION_ERRORS_SUPPRESSED__ = true;
                
                console.log('%c🔌 Browser extension error suppression active', 'color: #4CAF50; font-weight: bold; background: #e8f5e8; padding: 4px 8px; border-radius: 4px;');
                console.log('%c🦊 Web3/MetaMask functionality disabled for this application', 'color: orange; font-weight: bold; background: #fff3cd; padding: 4px 8px; border-radius: 4px;');
            })();
        </script>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @routes
        @vite(['resources/js/app.js', "resources/js/Pages/{$page['component']}.vue"])
        @inertiaHead
    </head>
    <body class="font-sans antialiased bg-ink text-cyan-50">
        @inertia
    </body>
</html>
