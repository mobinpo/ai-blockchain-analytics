const https = require('https');
const http = require('http');
const fs = require('fs');
const path = require('path');

// Simple HTTPS proxy for development (Docker setup)
const target = 'http://localhost:8003'; // Docker exposed port
const port = 8443;

// Generate self-signed certificate (WARNING: Only for development!)
const tls = require('tls');
const crypto = require('crypto');

// Create a simple self-signed certificate
const attrs = [
  { name: 'commonName', value: 'localhost' },
  { name: 'countryName', value: 'US' },
  { shortName: 'ST', value: 'Test' },
  { name: 'localityName', value: 'Test' },
  { name: 'organizationName', value: 'Test' },
  { shortName: 'OU', value: 'Test' }
];

// Pre-generated self-signed certificate for development
// This avoids runtime certificate generation issues

// Simple HTTP proxy function
function proxy(req, res) {
  // Determine target based on URL
  let targetPort = 8003; // Default to Laravel
  
  // Proxy Vite assets to Vite dev server
  if (req.url.includes('@vite') || 
      req.url.includes('/resources/') || 
      req.url.includes('?import') ||
      req.url.includes('.js') ||
      req.url.includes('.vue') ||
      req.url.includes('.css')) {
    targetPort = 5173;
  }

  const options = {
    hostname: targetPort === 5173 ? '192.168.1.114' : 'localhost',
    port: targetPort,
    path: req.url,
    method: req.method,
    headers: {
      ...req.headers,
      'x-forwarded-proto': 'https',
      'x-forwarded-for': req.connection.remoteAddress || req.socket.remoteAddress,
      'x-forwarded-host': req.headers.host
    }
  };

  const proxyReq = http.request(options, (proxyRes) => {
    // Copy headers but modify some for HTTPS
    const headers = { ...proxyRes.headers };
    
    // Fix CORS for Vite
    if (targetPort === 5173) {
      headers['access-control-allow-origin'] = '*';
      headers['access-control-allow-methods'] = 'GET, POST, PUT, DELETE, OPTIONS';
      headers['access-control-allow-headers'] = 'Content-Type, Authorization';
    }
    
    res.writeHead(proxyRes.statusCode, headers);
    proxyRes.pipe(res, { end: true });
  });

  req.pipe(proxyReq, { end: true });

  proxyReq.on('error', (err) => {
    console.error(`Proxy error (port ${targetPort}):`, err.message);
    res.writeHead(500);
    res.end('Proxy error');
  });
}

// Create HTTPS server with self-signed certificate
const serverOptions = {
  key: `-----BEGIN PRIVATE KEY-----
MIIEvQIBADANBgkqhkiG9w0BAQEFAASCBKcwggSjAgEAAoIBAQC7VJTUt9Us8cKB
wEiOfQqzVetyBSfLS2vui/w/z7lZqJ6FldHSOVd2pzw6vu5C3YOK3LGCgHHEiCg2
4+Rr1XRQo7dCi8eHM1lWRGO/QKFOOkCr1hs0LPqOhwzWHxQNMlbzCJpJxjyGV8Lz
gk1Ej7N7cEJOYxFClr1MdCuCN7WmgHzYtVG7Z7l+6F8Kp6K6v2j6Q6fP8uF2FgEf
pZCqT8j2+Q3j+VjzNQJ2w4zV3hV0GNnQ3C8J6uN2nF8Q8V2A6s3Q7hK9F6rCjN7z
d+U3PgGOYWlEqDk8nJ0qC6LzTJq5nP0iZpGlBR7VK3C9mJ0z7k6J8V3r7k1QV+G2
yYl6CZULAgMBAAECggEBAKTmjaS6tkK8BlPXClTQ2vpz/N6uxDeS35mXpqasqskV
laAidgg/sWqpjXDbXr93otIMLlWsM+X0CqMDgSXKejLS2jx4GDjI1ZplJkr4NKhe
9MlqDBINPvCNqh7N2h8w+K1dEZqgj4vvJ4/lNKG4tE8A5Q2w8nVBKGYO3QFMR8QN
j1f1gBqnAGsK7BnU+1HECqWtH2h7Nb7QJGjLwGRKwRZqFBM5N6j8ZQhjKJ5E4+0L
RlOV2TM9J9Y5TnP8HdGHKV7d6aIDHCZvFoqKzE1mKjx4J8U5v3sPcSx9WLF8YHqP
6Yp9N1Y5Lh2r0kzBDVR9U6r2HdoEgAZQRjKgEgWX8hQP4uT+XGuXhE1K3KvQjT5S
N1lrXHCFU6fR+/rQ4Kf8Gw==
-----END PRIVATE KEY-----`,
  cert: `-----BEGIN CERTIFICATE-----
MIIDXTCCAkWgAwIBAgIJAKP+hc0pQr8RMA0GCSqGSIb3DQEBCwUAMEUxCzAJBgNV
BAYTAkFVMRMwEQYDVQQIDApTb21lLVN0YXRlMSEwHwYDVQQKDBhJbnRlcm5ldCBX
aWRnaXRzIFB0eSBMdGQwHhcNMjMwNjE1MDAwMDAwWhcNMjQwNjE0MDAwMDAwWjBF
MQswCQYDVQQGEwJBVTETMBEGA1UECAwKU29tZS1TdGF0ZTEhMB8GA1UECgwYSW50
ZXJuZXQgV2lkZ2l0cyBQdHkgTHRkMIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIB
CgKCAQEAu1SU1L7VLPHCgcBIjn0Ks1XrcgUny0tr7ov8P8+5WaiehZXR0jlXdqc8
Or7uQt2DityxgoBxxIgoNuPka9V0UKO3QovHhzNZVkRjv0ChTjpAq9YbNCz6jocM
1h8UDTJWWnrGSYpMhlfC84JNRI+ze3BCTmMRQpa9THQrgjOlpoB82LVRu2e5fuhf
Cqeiur9o+kOnz/LhdhYBH6WQqk/I9vkN4/lY8zUCdsOM1d4VdBjZ0NwvCerjdpxf
EPFdgOrN0O4SvReqwoza83flNz4BjmFpRKg5PJydKgui80yauZz9ImaRpQUe1Stw
vZidM+5OifFd6+5NUFfhtsmJegmVCwIDAQABo1AwTjAdBgNVHQ4EFgQU7T6ByOWj
xmRgVjbg1V8lU3Ft6nIwHwYDVR0jBBgwFoAU7T6ByOWjxmRgVjbg1V8lU3Ft6nIw
DAYDVR0TBAUwAwEB/zANBgkqhkiG9w0BAQsFAAOCAQEAh1o8Tl+BHKXPjQnP5wLz
D0QFkn+NeCh4dJ9VvFYGm6dj7qV1JmT7LRKKOzCQV4dFBJ7QU3vN8d6w6+Qz5k6P
LK7eNnJ2w4zWGz1K6EvF4o3aQrAzV8XsV+J7Z2v1Y7nZt1VqFcE+WnP4VHGZ7r5K
JqS3nF5K6sAoX5r+VzJ7wnF3vQ3H2dWzr9O+1aKz4VrQ3K4F8J7VzK2S9W6xG8jm
3F9rCp7VqX5G7z8lOzBnJ3D3VGWK5o6dJ3aRz7x6J8n2QdVr3c7W2bKlGtGdl1c3
mF5Bs3HRJ9K1k8s2P3dV2qB6L8w3Y5n6f3M9V7e8C6L5vQJQ3Kf2K2e8zD2qBcKi
8jMd1w==
-----END CERTIFICATE-----`
};

const server = https.createServer(serverOptions, proxy);

server.listen(port, () => {
  console.log(`🔒 HTTPS Proxy Server running on https://localhost:${port}`);
  console.log(`🎯 Proxying to: ${target}`);
  console.log(`💳 Payment autofill should now work!`);
  console.log('\n⚠️  You may see a security warning in your browser.');
  console.log('   Click "Advanced" -> "Proceed to localhost" to continue.');
  console.log('   This is safe for local development only.\n');
});

server.on('error', (err) => {
  if (err.code === 'EADDRINUSE') {
    console.error(`❌ Port ${port} is already in use. Please stop other services on this port.`);
  } else {
    console.error('❌ Server error:', err);
  }
  process.exit(1);
});