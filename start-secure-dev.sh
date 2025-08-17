#!/bin/bash

echo "🚀 Starting Secure Development Environment..."
echo ""

# Note: Laravel is running in Docker container
echo "📱 Laravel should be running in Docker container on port 8003..."
echo "   Make sure to run: docker compose up -d"
# No need to start Laravel server - it's in Docker

# Start Vite development server
echo "⚡ Starting Vite development server..."
npm run dev &
VITE_PID=$!

# Wait a moment for servers to start
sleep 3

# Start HTTPS proxy
echo "🔒 Starting HTTPS proxy server..."
node https-proxy.cjs &
PROXY_PID=$!

echo ""
echo "✅ Development environment ready!"
echo ""
echo "🌐 Your secure application is available at:"
echo "   https://localhost:8443"
echo ""
echo "💳 Payment autofill is now enabled!"
echo ""
echo "ℹ️  To stop the servers:"
echo "   Press Ctrl+C or run: pkill -f 'artisan serve'; pkill -f 'https-proxy'"
echo ""

# Wait for interrupt
trap 'echo ""; echo "🛑 Stopping servers..."; kill $VITE_PID $PROXY_PID 2>/dev/null; exit 0' INT

wait