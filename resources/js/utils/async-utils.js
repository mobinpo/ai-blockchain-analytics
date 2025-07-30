/**
 * Async utilities for handling timeouts, retries, and error handling
 */

/**
 * Creates a timeout promise that rejects after specified milliseconds
 */
export const createTimeout = (ms, message = 'Operation timed out') => {
    return new Promise((_, reject) => {
        setTimeout(() => reject(new Error(message)), ms);
    });
};

/**
 * Wraps a promise with a timeout
 */
export const withTimeout = async (promise, ms = 15000, timeoutMessage) => {
    const timeoutPromise = createTimeout(ms, timeoutMessage || `Operation timed out after ${ms}ms`);
    
    try {
        return await Promise.race([promise, timeoutPromise]);
    } catch (error) {
        // Add timeout context to error
        if (error.message.includes('timed out')) {
            error.isTimeout = true;
        }
        throw error;
    }
};

/**
 * Retry mechanism with exponential backoff
 */
export const retryWithBackoff = async (
    fn,
    maxRetries = 3,
    baseDelay = 1000,
    maxDelay = 10000,
    backoffFactor = 2
) => {
    let lastError;
    
    for (let attempt = 0; attempt <= maxRetries; attempt++) {
        try {
            const result = await fn(attempt);
            console.log(`✅ Operation succeeded on attempt ${attempt + 1}`);
            return result;
        } catch (error) {
            lastError = error;
            console.warn(`❌ Attempt ${attempt + 1} failed:`, error.message);
            
            if (attempt === maxRetries) {
                console.error(`🚫 All ${maxRetries + 1} attempts failed`);
                break;
            }
            
            // Calculate delay with exponential backoff
            const delay = Math.min(baseDelay * Math.pow(backoffFactor, attempt), maxDelay);
            console.log(`⏳ Retrying in ${delay}ms...`);
            
            await new Promise(resolve => setTimeout(resolve, delay));
        }
    }
    
    throw lastError;
};

/**
 * Safe async function wrapper with error handling
 */
export const safeAsync = (fn, fallback = null, context = 'unknown') => {
    return async (...args) => {
        try {
            const result = await fn(...args);
            return result;
        } catch (error) {
            console.error(`🚨 Error in ${context}:`, error);
            
            // Log additional context for debugging
            console.error('Error details:', {
                message: error.message,
                stack: error.stack,
                context,
                args: args.length > 0 ? 'provided' : 'none',
                timestamp: new Date().toISOString()
            });
            
            // Return fallback or rethrow based on configuration
            if (fallback !== null) {
                console.log(`🔄 Using fallback for ${context}`);
                return typeof fallback === 'function' ? fallback(error, ...args) : fallback;
            }
            
            throw error;
        }
    };
};

/**
 * Debounced async function
 */
export const debounceAsync = (fn, delay = 300) => {
    let timeoutId;
    let lastPromise = Promise.resolve();
    
    return (...args) => {
        clearTimeout(timeoutId);
        
        return new Promise((resolve, reject) => {
            timeoutId = setTimeout(async () => {
                try {
                    // Chain with previous promise to prevent race conditions
                    await lastPromise;
                    const result = await fn(...args);
                    resolve(result);
                } catch (error) {
                    reject(error);
                }
            }, delay);
        });
    };
};

/**
 * Connection state manager for handling network connections
 */
export class ConnectionManager {
    constructor(name = 'connection') {
        this.name = name;
        this.isConnected = false;
        this.reconnectAttempts = 0;
        this.maxReconnectAttempts = 5;
        this.reconnectDelay = 1000;
        this.listeners = new Set();
        this.cleanup = new Set();
        
        // Bind methods to maintain context
        this.connect = this.connect.bind(this);
        this.disconnect = this.disconnect.bind(this);
        this.reconnect = this.reconnect.bind(this);
        this.checkConnection = this.checkConnection.bind(this);
        
        console.log(`🔌 ConnectionManager '${this.name}' initialized`);
    }
    
    async connect(connectFn) {
        try {
            console.log(`🔄 Connecting ${this.name}...`);
            await connectFn();
            this.isConnected = true;
            this.reconnectAttempts = 0;
            console.log(`✅ ${this.name} connected successfully`);
            this.notifyListeners('connected');
        } catch (error) {
            console.error(`❌ Failed to connect ${this.name}:`, error);
            this.isConnected = false;
            throw error;
        }
    }
    
    async disconnect() {
        console.log(`🔌 Disconnecting ${this.name}...`);
        this.isConnected = false;
        
        // Run cleanup functions
        for (const cleanupFn of this.cleanup) {
            try {
                await cleanupFn();
            } catch (error) {
                console.error(`⚠️ Cleanup error for ${this.name}:`, error);
            }
        }
        
        this.cleanup.clear();
        this.notifyListeners('disconnected');
        console.log(`✅ ${this.name} disconnected`);
    }
    
    async reconnect(connectFn) {
        if (this.reconnectAttempts >= this.maxReconnectAttempts) {
            console.error(`🚫 Max reconnection attempts reached for ${this.name}`);
            this.notifyListeners('failed');
            return false;
        }
        
        this.reconnectAttempts++;
        const delay = this.reconnectDelay * Math.pow(2, this.reconnectAttempts - 1);
        
        console.log(`🔄 Reconnecting ${this.name} (attempt ${this.reconnectAttempts}/${this.maxReconnectAttempts}) in ${delay}ms...`);
        
        await new Promise(resolve => setTimeout(resolve, delay));
        
        try {
            await this.connect(connectFn);
            return true;
        } catch (error) {
            console.error(`❌ Reconnection failed for ${this.name}:`, error);
            return this.reconnect(connectFn);
        }
    }
    
    checkConnection() {
        return this.isConnected;
    }
    
    addCleanup(cleanupFn) {
        this.cleanup.add(cleanupFn);
    }
    
    onStateChange(listener) {
        this.listeners.add(listener);
        return () => this.listeners.delete(listener);
    }
    
    notifyListeners(state) {
        for (const listener of this.listeners) {
            try {
                listener(state, this.name);
            } catch (error) {
                console.error(`⚠️ Listener error for ${this.name}:`, error);
            }
        }
    }
}

/**
 * Enhanced fetch with timeout, retries, and error handling
 */
export const safeFetch = async (url, options = {}, timeout = 15000) => {
    const controller = new AbortController();
    const timeoutId = setTimeout(() => controller.abort(), timeout);
    
    try {
        const response = await fetch(url, {
            ...options,
            signal: controller.signal
        });
        
        clearTimeout(timeoutId);
        
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        
        return response;
    } catch (error) {
        clearTimeout(timeoutId);
        
        if (error.name === 'AbortError') {
            throw new Error(`Request timed out after ${timeout}ms`);
        }
        
        throw error;
    }
};

/**
 * Promise queue to prevent overwhelming the system
 */
export class PromiseQueue {
    constructor(concurrency = 3) {
        this.concurrency = concurrency;
        this.running = 0;
        this.queue = [];
    }
    
    async add(promiseFactory) {
        return new Promise((resolve, reject) => {
            this.queue.push({
                promiseFactory,
                resolve,
                reject
            });
            
            this.process();
        });
    }
    
    async process() {
        if (this.running >= this.concurrency || this.queue.length === 0) {
            return;
        }
        
        this.running++;
        const { promiseFactory, resolve, reject } = this.queue.shift();
        
        try {
            const result = await promiseFactory();
            resolve(result);
        } catch (error) {
            reject(error);
        } finally {
            this.running--;
            this.process();
        }
    }
}