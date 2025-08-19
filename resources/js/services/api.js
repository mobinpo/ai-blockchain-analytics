import axios from 'axios';

// Create axios instance with base configuration
const api = axios.create({
  baseURL: '/api',
  timeout: 30000,
  headers: {
    'Accept': 'application/json',
    'Content-Type': 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
    'Cache-Control': 'no-store, no-cache, must-revalidate'
  }
});

// Request interceptor to add CSRF token and cache busting
api.interceptors.request.use(
  (config) => {
    // Add CSRF token from meta tag
    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    if (token) {
      config.headers['X-CSRF-TOKEN'] = token;
    }
    
    // Add auth header if available from meta tag or sessionStorage
    const authToken = document.querySelector('meta[name="auth-token"]')?.getAttribute('content') || 
                     sessionStorage.getItem('auth_token');
    if (authToken) {
      config.headers['Authorization'] = `Bearer ${authToken}`;
    }
    
    // Add timestamp-based cache busting for analysis status endpoints
    if (config.url && config.url.includes('analysis')) {
      config.params = config.params || {};
      config.params.ts = Date.now();
    }
    
    return config;
  },
  (error) => {
    return Promise.reject(error);
  }
);

// Response interceptor for error handling
api.interceptors.response.use(
  (response) => {
    return response;
  },
  (error) => {
    if (error.response?.status === 401) {
      // Handle unauthorized access
      console.warn('Unauthorized access, redirecting to login...');
      // You can customize this behavior
    } else if (error.response?.status === 422) {
      // Handle validation errors
      console.warn('Validation error:', error.response.data);
    } else if (error.response?.status >= 500) {
      // Handle server errors
      console.error('Server error:', error.response.data);
    }
    
    return Promise.reject(error);
  }
);

export default api;