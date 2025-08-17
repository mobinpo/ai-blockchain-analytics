#!/bin/bash

echo "🔒 Setting up HTTPS for local development..."

# Check if mkcert is installed
if ! command -v mkcert &> /dev/null; then
    echo "Installing mkcert for local SSL certificates..."
    
    # Install mkcert based on OS
    if [[ "$OSTYPE" == "linux-gnu"* ]]; then
        # Linux
        curl -JLO "https://dl.filippo.io/mkcert/latest?for=linux/amd64"
        chmod +x mkcert-v*-linux-amd64
        sudo mv mkcert-v*-linux-amd64 /usr/local/bin/mkcert
    elif [[ "$OSTYPE" == "darwin"* ]]; then
        # macOS
        brew install mkcert
    else
        echo "Please install mkcert manually from https://github.com/FiloSottile/mkcert"
        exit 1
    fi
fi

# Create certificates directory
mkdir -p storage/ssl

# Generate local CA
mkcert -install

# Generate certificate for localhost
cd storage/ssl
mkcert localhost 127.0.0.1 ::1
cd ../..

echo "✅ SSL certificates generated!"

# Create a simple HTTPS proxy script
cat > storage/ssl/https-proxy.js << 'EOF'
const https = require('https');
const http = require('http');
const fs = require('fs');
const httpProxy = require('http-proxy');

// Create a proxy server with SSL
const proxy = httpProxy.createProxyServer({});

const options = {
  key: fs.readFileSync('./storage/ssl/localhost+2-key.pem'),
  cert: fs.readFileSync('./storage/ssl/localhost+2.pem')
};

const server = https.createServer(options, function (req, res) {
  proxy.web(req, res, {
    target: 'http://localhost:8003',
    changeOrigin: true
  });
});

server.listen(8443, function () {
  console.log('🔒 HTTPS proxy server listening on https://localhost:8443');
  console.log('🌟 Payment autofill should now work!');
});
EOF

echo ""
echo "🚀 To start the HTTPS development environment:"
echo "1. Start your Laravel server: php artisan serve --port=8003"
echo "2. Install http-proxy: npm install -g http-proxy"
echo "3. Start HTTPS proxy: node storage/ssl/https-proxy.js"
echo "4. Access your app at: https://localhost:8443"
echo ""
echo "Payment autofill will be enabled on the HTTPS URL!"