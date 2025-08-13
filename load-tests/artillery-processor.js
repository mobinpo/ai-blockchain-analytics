/**
 * Artillery Processor for AI Blockchain Analytics Load Testing
 * 
 * This file contains custom functions for generating dynamic test data
 * and processing responses during load testing.
 */

const crypto = require('crypto');
const os = require('os');

// Custom functions for Artillery templates
module.exports = {
  // Generate random IP addresses for X-Forwarded-For headers
  $randomIP: function(context) {
    return `${Math.floor(Math.random() * 255)}.${Math.floor(Math.random() * 255)}.${Math.floor(Math.random() * 255)}.${Math.floor(Math.random() * 255)}`;
  },

  // Generate random numbers within a range
  $randomNumber: function(min, max) {
    return Math.floor(Math.random() * (max - min + 1)) + min;
  },

  // Generate random boolean values
  $randomBoolean: function() {
    return Math.random() < 0.5;
  },

  // Generate random contract addresses (Ethereum format)
  $randomContractAddress: function() {
    return '0x' + crypto.randomBytes(20).toString('hex');
  },

  // Generate random user agent strings
  $randomUserAgent: function() {
    const userAgents = [
      'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
      'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36',
      'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36',
      'Artillery-LoadTest/1.0 (AI-Blockchain-Analytics)',
    ];
    return userAgents[Math.floor(Math.random() * userAgents.length)];
  },

  // Generate random transaction hashes
  $randomTxHash: function() {
    return '0x' + crypto.randomBytes(32).toString('hex');
  },

  // Generate random wallet addresses
  $randomWalletAddress: function() {
    return '0x' + crypto.randomBytes(20).toString('hex');
  },

  // Pre-test setup function
  beforeRequest: function(context, events, done) {
    // Add timing information
    context.vars.startTime = Date.now();
    
    // Add unique request ID for tracking
    context.vars.requestId = crypto.randomUUID();
    
    // Add load test identification
    context.vars.loadTestSession = `artillery-${Date.now()}`;
    
    return done();
  },

  // Post-response processing
  afterResponse: function(context, events, response, done) {
    // Calculate response time
    const responseTime = Date.now() - context.vars.startTime;
    
    // Emit custom metrics
    events.emit('counter', 'responses.total', 1);
    events.emit('histogram', 'response_time.custom', responseTime);
    
    // Track status codes
    events.emit('counter', `responses.status_${response.statusCode}`, 1);
    
    // Track slow responses (>2 seconds)
    if (responseTime > 2000) {
      events.emit('counter', 'responses.slow', 1);
      console.log(`[SLOW RESPONSE] ${responseTime}ms - ${context.vars.requestId}`);
    }
    
    // Track errors
    if (response.statusCode >= 400) {
      events.emit('counter', 'responses.errors', 1);
      console.log(`[ERROR RESPONSE] ${response.statusCode} - ${context.vars.requestId}`);
    }
    
    // Track successful analyses
    if (response.statusCode === 200 && context.vars.target && context.vars.target.includes('/analyze')) {
      events.emit('counter', 'analyses.successful', 1);
    }
    
    return done();
  },

  // Custom logging function
  logMetrics: function(context, events, done) {
    const timestamp = new Date().toISOString();
    const memory = process.memoryUsage();
    const cpu = os.loadavg();
    
    console.log(`[METRICS] ${timestamp} - Memory: ${Math.round(memory.rss / 1024 / 1024)}MB, CPU: ${cpu[0].toFixed(2)}`);
    
    return done();
  },

  // Generate realistic Solidity code samples
  generateSolidityCode: function(context) {
    const templates = [
      `pragma solidity ^0.8.0;

contract TestContract {
    uint256 private _value;
    address private _owner;
    
    constructor() {
        _owner = msg.sender;
    }
    
    modifier onlyOwner() {
        require(msg.sender == _owner, "Not owner");
        _;
    }
    
    function setValue(uint256 newValue) public onlyOwner {
        _value = newValue;
    }
    
    function getValue() public view returns (uint256) {
        return _value;
    }
}`,

      `pragma solidity ^0.8.0;

import "@openzeppelin/contracts/token/ERC20/ERC20.sol";
import "@openzeppelin/contracts/access/Ownable.sol";

contract CustomToken is ERC20, Ownable {
    uint256 private _maxSupply;
    
    constructor(string memory name, string memory symbol) ERC20(name, symbol) {
        _maxSupply = 1000000 * 10**decimals();
        _mint(msg.sender, _maxSupply);
    }
    
    function burn(uint256 amount) public {
        _burn(msg.sender, amount);
    }
    
    function mint(address to, uint256 amount) public onlyOwner {
        require(totalSupply() + amount <= _maxSupply, "Exceeds max supply");
        _mint(to, amount);
    }
}`,

      `pragma solidity ^0.8.0;

contract VulnerableContract {
    mapping(address => uint256) private balances;
    
    function deposit() public payable {
        balances[msg.sender] += msg.value;
    }
    
    function withdraw(uint256 amount) public {
        require(balances[msg.sender] >= amount, "Insufficient balance");
        
        // Vulnerable to reentrancy
        (bool success,) = msg.sender.call{value: amount}("");
        require(success, "Transfer failed");
        
        balances[msg.sender] -= amount;
    }
    
    function getBalance() public view returns (uint256) {
        return balances[msg.sender];
    }
}`
    ];
    
    return templates[Math.floor(Math.random() * templates.length)];
  },

  // Performance monitoring hooks
  onPhaseStarted: function(context, events, done) {
    console.log(`[PHASE START] ${context.phase.name} - Target arrival rate: ${context.phase.arrivalRate || 'N/A'}`);
    events.emit('counter', 'phases.started', 1);
    return done();
  },

  onPhaseCompleted: function(context, events, done) {
    console.log(`[PHASE COMPLETE] ${context.phase.name}`);
    events.emit('counter', 'phases.completed', 1);
    return done();
  },

  // Error handling
  onError: function(context, events, error, done) {
    console.error(`[ARTILLERY ERROR] ${error.message}`);
    events.emit('counter', 'artillery.errors', 1);
    return done();
  },

  // Generate test scenarios dynamically
  generateScenario: function(context) {
    const scenarios = [
      'comprehensive_analysis',
      'security_audit',
      'gas_optimization',
      'vulnerability_scan',
      'code_quality_check'
    ];
    
    return scenarios[Math.floor(Math.random() * scenarios.length)];
  },

  // Calculate test progress
  calculateProgress: function(context) {
    const startTime = context.vars.testStartTime || Date.now();
    const elapsed = Date.now() - startTime;
    const totalDuration = 11 * 60 * 1000; // 11 minutes in milliseconds
    
    return Math.min(100, Math.round((elapsed / totalDuration) * 100));
  },

  // Resource monitoring
  monitorResources: function(context, events, done) {
    const memory = process.memoryUsage();
    const cpu = os.loadavg();
    
    // Emit resource metrics
    events.emit('histogram', 'system.memory.rss', memory.rss);
    events.emit('histogram', 'system.memory.heap_used', memory.heapUsed);
    events.emit('histogram', 'system.cpu.load_1m', cpu[0]);
    
    // Alert on high resource usage
    if (memory.rss > 1024 * 1024 * 1024) { // 1GB
      console.warn(`[HIGH MEMORY] RSS: ${Math.round(memory.rss / 1024 / 1024)}MB`);
    }
    
    if (cpu[0] > 5) {
      console.warn(`[HIGH CPU] Load: ${cpu[0].toFixed(2)}`);
    }
    
    return done();
  }
};

// Export individual functions for template usage
module.exports.randomIP = module.exports.$randomIP;
module.exports.randomNumber = module.exports.$randomNumber;
module.exports.randomBoolean = module.exports.$randomBoolean;
module.exports.randomContractAddress = module.exports.$randomContractAddress;
module.exports.randomUserAgent = module.exports.$randomUserAgent;
module.exports.randomTxHash = module.exports.$randomTxHash;
module.exports.randomWalletAddress = module.exports.$randomWalletAddress;
