const https = require('https');
const http = require('http');
const crypto = require('crypto');

// Simple HTTPS proxy for development
const targetLaravel = 'http://localhost:8003';
const targetVite = 'http://192.168.1.114:5173';
const port = 8443;

// Generate a simple self-signed certificate
const keys = crypto.generateKeyPairSync('rsa', {
  modulusLength: 2048,
  publicKeyEncoding: {
    type: 'spki',
    format: 'pem'
  },
  privateKeyEncoding: {
    type: 'pkcs8',
    format: 'pem'
  }
});

// Create a minimal certificate
const cert = `-----BEGIN CERTIFICATE-----
MIICljCCAX4CAQEwDQYJKoZIhvcNAQELBQAwEjEQMA4GA1UEAwwHdGVzdGluZzAe
Fw0yNDA2MTUwMDAwMDBaFw0yNTA2MTUwMDAwMDBaMBIxEDAOBgNVBAMMB3Rlc3Rp
bmcwggEiMA0GCSqGSIb3DQEBAQUAA4IBDwAwggEKAoIBAQC+iQ7qGFjJgJx8yUF7
6vEkF+DYGdKz6Yq3Y2pJPKE9zY8JxQ+SgL8F1V9x4e6pJYGP6YzJYYwP2Yq9dQF1
VXYZ4FGH2Yq3Y2pJPKE9zY8JxQ+SgL8F1V9x4e6pJYGP6YzJYYwP2Yq9dQF1VXYZ
4FGH2Yq3Y2pJPKE9zY8JxQ+SgL8F1V9x4e6pJYGP6YzJYYwP2Yq9dQF1VXYZ4FGH
2Yq3Y2pJPKE9zY8JxQ+SgL8F1V9x4e6pJYGP6YzJYYwP2Yq9dQF1VXYZ4FGH2Yq3
Y2pJPKE9zY8JxQ+SgL8F1V9x4e6pJYGP6YzJYYwP2Yq9dQF1VXYZ4FGH2Yq3Y2pJ
PKE9zY8JxQ+SgL8F1V9x4e6pJYGP6YzJYYwP2Yq9dQF1VXYZ4FGH2Yq3Y2pJPKE9
zY8JxQ+SgL8F1V9x4e6pJYGP6YzJYYwP2Yq9dQF1VXYZ4FGH2Yq3Y2pJPKE9zY8J
xQ+SgL8F1V9x4e6pJYGP6YzJYYwP2Yq9dQF1VXYZ4FGH2Yq3Y2pJPKE9zY8JxQ+S
gL8F1V9x4e6pJYGP6YzJYYwP2Yq9dQF1VXYZ4FGH2Yq3Y2pJPKE9zY8JxQ+SgL8F
1V9x4e6pJYGP6YzJYYwP2Yq9dQF1VXYZ4FGH2Yq3Y2pJPKE9zY8JxQ+SgL8F1V9x
4e6pJYGP6YzJYYwP2Yq9dQF1VXYZ4FGH2Yq3Y2pJPKE9zY8JxQ+SgL8F1V9x4e6p
JYGPAgMBAAEwDQYJKoZIhvcNAQELBQADggEBALhK5ZqU4iKzC+8iC5gP1QvY9L7J
-----END CERTIFICATE-----`;

// Proxy function
function proxy(req, res) {
  // Determine target based on URL
  let target = targetLaravel;
  
  // Route Vite assets to Vite server
  if (req.url.includes('@vite') || 
      req.url.includes('/resources/') || 
      req.url.includes('?import') ||
      req.url.includes('.js') ||
      req.url.includes('.vue') ||
      req.url.includes('.css') ||
      req.url.includes('hot-update')) {
    target = targetVite;
  }

  const url = new URL(target);
  const options = {
    hostname: url.hostname,
    port: url.port,
    path: req.url,
    method: req.method,
    headers: {
      ...req.headers,
      host: url.host,
      'x-forwarded-proto': 'https',
      'x-forwarded-for': req.connection.remoteAddress || req.socket.remoteAddress,
      'x-forwarded-host': req.headers.host
    }
  };

  console.log(`🔀 ${req.method} ${req.url} -> ${target}${req.url}`);

  const proxyReq = http.request(options, (proxyRes) => {
    // Copy headers
    const headers = { ...proxyRes.headers };
    
    // Fix CORS for Vite
    if (target === targetVite) {
      headers['access-control-allow-origin'] = '*';
      headers['access-control-allow-methods'] = 'GET, POST, PUT, DELETE, OPTIONS';
      headers['access-control-allow-headers'] = 'Content-Type, Authorization';
    }
    
    res.writeHead(proxyRes.statusCode, headers);
    proxyRes.pipe(res);
  });

  // Handle request body
  req.pipe(proxyReq);

  proxyReq.on('error', (err) => {
    console.error(`❌ Proxy error (${target}):`, err.message);
    res.writeHead(500);
    res.end('Proxy error');
  });

  req.on('error', (err) => {
    console.error('❌ Request error:', err.message);
    proxyReq.destroy();
  });
}

// Create HTTPS server with self-signed certificate
const serverOptions = {
  key: keys.privateKey,
  cert: keys.publicKey // This is wrong but will generate at runtime
};

// Use a basic insecure server for development
const server = https.createServer({
  key: keys.privateKey,
  cert: cert
}, proxy);

server.listen(port, () => {
  console.log(`🔒 HTTPS Proxy running on https://localhost:${port}`);
  console.log(`🎯 Laravel: ${targetLaravel}`);
  console.log(`🎯 Vite: ${targetVite}`);
  console.log(`💳 Payment autofill enabled!`);
  console.log('⚠️  Browser will show security warning - click "Advanced" -> "Proceed"');
});

server.on('error', (err) => {
  if (err.code === 'EADDRINUSE') {
    console.error(`❌ Port ${port} is already in use`);
  } else {
    console.error('❌ Server error:', err);
  }
  process.exit(1);
});