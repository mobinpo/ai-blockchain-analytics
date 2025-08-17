const http = require('http');

// Development proxy without HTTPS complications
const targetLaravel = 'http://localhost:8003';
const targetVite = 'http://192.168.1.114:5173';
const port = 8080;

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

// Create HTTP server
const server = http.createServer(proxy);

server.listen(port, () => {
  console.log(`🔀 Development Proxy running on http://localhost:${port}`);
  console.log(`🎯 Laravel: ${targetLaravel}`);
  console.log(`🎯 Vite: ${targetVite}`);
  console.log(`💡 Access your app at: http://localhost:${port}`);
});

server.on('error', (err) => {
  if (err.code === 'EADDRINUSE') {
    console.error(`❌ Port ${port} is already in use`);
  } else {
    console.error('❌ Server error:', err);
  }
  process.exit(1);
});