#!/usr/bin/env node

/**
 * Enhanced test data generator for Artillery load tests
 * Generates realistic blockchain addresses, cryptocurrency symbols, and analysis scenarios
 */

import fs from 'fs';
import crypto from 'crypto';

// Configuration
const OUTPUT_FILE = './test-data-enhanced.csv';
const NUM_RECORDS = 1000;

// Data pools
const cryptocurrencies = [
    'BTC', 'ETH', 'USDT', 'BNB', 'USDC', 'SOL', 'XRP', 'ADA', 'DOGE', 'AVAX',
    'MATIC', 'DOT', 'UNI', 'LINK', 'ATOM', 'LTC', 'ETC', 'FIL', 'TRX', 'ICP',
    'VET', 'EOS', 'AAVE', 'CAKE', 'SUSHI', 'CRV', 'YFI', 'COMP', 'MKR', 'SNX'
];

const networks = [
    'ethereum', 'bsc', 'polygon', 'arbitrum', 'optimism', 'avalanche', 'fantom', 'harmony'
];

const analysisTypes = [
    'security', 'gas_optimization', 'vulnerability', 'performance', 'compatibility', 'comprehensive'
];

const analysisDepths = [
    'quick', 'standard', 'comprehensive', 'deep'
];

const sentimentKeywords = [
    'bullish momentum building', 'bearish market sentiment', 'technical analysis indicates',
    'price prediction shows', 'market volatility increasing', 'trading volume surge',
    'institutional adoption rising', 'regulatory clarity needed', 'defi innovation expanding',
    'nft market evolution', 'layer 2 scaling solutions', 'cross-chain interoperability',
    'yield farming opportunities', 'staking rewards available', 'liquidity mining active',
    'governance token voting', 'smart contract audit', 'security vulnerability assessment',
    'gas fee optimization', 'network congestion issues', 'consensus mechanism upgrade'
];

const realWorldContracts = [
    // Ethereum mainnet - real contracts
    '0xE592427A0AEce92De3Edee1F18E0157C05861564', // Uniswap V3 SwapRouter
    '0x87870Bca3F3fD6335C3F4ce8392D69350B4fA4E2', // Aave V3 Pool
    '0x1f98431c8ad98523631ae4a59f267346ea31f984', // Uniswap V3 Factory
    '0x5C69bEe701ef814a2B6a3EDD4B1652CB9cc5aA6f', // Uniswap V2 Factory
    '0x7a250d5630B4cF539739dF2C5dAcb4c659F2488D', // Uniswap V2 Router02
    '0xC02aaA39b223FE8D0A0e5C4F27eAD9083C756Cc2', // WETH
    '0x6B175474E89094C44Da98b954EedeAC495271d0F', // DAI
    '0xA0b86a33E6410928140CEB1Fd9f2EfDb306f8B0c', // Compound cUSDC
    '0x27182842E098f60e3D576794A5bFFb0777E025d3', // Euler Finance (known vulnerable)
    '0x3506424F91fD33084466F402d5D97f05F8e3b4AF'  // Chainlink Price Feed
];

/**
 * Generate a random Ethereum address
 */
function generateRandomAddress() {
    const randomBytes = crypto.randomBytes(20);
    return '0x' + randomBytes.toString('hex');
}

/**
 * Get a random item from an array
 */
function getRandomItem(array) {
    return array[Math.floor(Math.random() * array.length)];
}

/**
 * Generate a random integer between min and max (inclusive)
 */
function getRandomInt(min, max) {
    return Math.floor(Math.random() * (max - min + 1)) + min;
}

/**
 * Generate weighted selection (favor real contracts 30% of the time)
 */
function getContractAddress() {
    if (Math.random() < 0.3) {
        return getRandomItem(realWorldContracts);
    }
    return generateRandomAddress();
}

/**
 * Generate test data records
 */
function generateTestData() {
    const records = [];
    
    // Add header
    records.push([
        'contract_address',
        'network', 
        'analysis_type',
        'analysis_depth',
        'symbol',
        'keyword_phrase',
        'priority',
        'expected_vulnerabilities',
        'gas_limit',
        'test_scenario'
    ]);

    // Generate data records
    for (let i = 0; i < NUM_RECORDS; i++) {
        const isRealContract = realWorldContracts.includes(getContractAddress());
        const symbol = getRandomItem(cryptocurrencies);
        const network = getRandomItem(networks);
        const analysisType = getRandomItem(analysisTypes);
        const analysisDepth = getRandomItem(analysisDepths);
        const priority = getRandomItem(['low', 'normal', 'high', 'critical']);
        const gasLimit = getRandomInt(21000, 500000);
        
        // Generate expected vulnerabilities based on analysis type
        let expectedVulnerabilities = 0;
        if (analysisType === 'security' || analysisType === 'vulnerability') {
            expectedVulnerabilities = isRealContract ? getRandomInt(0, 3) : getRandomInt(1, 8);
        }
        
        // Generate test scenario type
        const scenarios = [
            'standard_analysis', 'stress_test', 'edge_case', 'performance_test',
            'security_audit', 'gas_optimization', 'compatibility_check'
        ];
        const testScenario = getRandomItem(scenarios);
        
        const record = [
            getContractAddress(),
            network,
            analysisType,
            analysisDepth,
            symbol,
            getRandomItem(sentimentKeywords),
            priority,
            expectedVulnerabilities,
            gasLimit,
            testScenario
        ];
        
        records.push(record);
    }
    
    return records;
}

/**
 * Write CSV data to file
 */
function writeCSV(records, filename) {
    const csvContent = records.map(record => 
        record.map(field => {
            // Escape fields containing commas or quotes
            if (typeof field === 'string' && (field.includes(',') || field.includes('"'))) {
                return `"${field.replace(/"/g, '""')}"`;
            }
            return field;
        }).join(',')
    ).join('\n');
    
    fs.writeFileSync(filename, csvContent, 'utf8');
    console.log(`✅ Generated ${records.length - 1} test records in ${filename}`);
}

/**
 * Generate statistics about the test data
 */
function generateStatistics(records) {
    const data = records.slice(1); // Remove header
    
    const stats = {
        totalRecords: data.length,
        networks: {},
        analysisTypes: {},
        symbols: {},
        priorities: {},
        realContracts: 0,
        avgGasLimit: 0
    };
    
    let totalGas = 0;
    
    data.forEach(record => {
        const [address, network, analysisType, , symbol, , priority, , gasLimit] = record;
        
        // Count networks
        stats.networks[network] = (stats.networks[network] || 0) + 1;
        
        // Count analysis types
        stats.analysisTypes[analysisType] = (stats.analysisTypes[analysisType] || 0) + 1;
        
        // Count symbols
        stats.symbols[symbol] = (stats.symbols[symbol] || 0) + 1;
        
        // Count priorities
        stats.priorities[priority] = (stats.priorities[priority] || 0) + 1;
        
        // Count real contracts
        if (realWorldContracts.includes(address)) {
            stats.realContracts++;
        }
        
        // Sum gas limits
        totalGas += parseInt(gasLimit);
    });
    
    stats.avgGasLimit = Math.round(totalGas / data.length);
    
    return stats;
}

/**
 * Main execution
 */
function main() {
    console.log('🔧 Generating enhanced test data for Artillery load tests...');
    console.log(`📊 Target records: ${NUM_RECORDS}`);
    console.log(`📁 Output file: ${OUTPUT_FILE}`);
    
    const startTime = Date.now();
    
    // Generate test data
    const records = generateTestData();
    
    // Write to CSV
    writeCSV(records, OUTPUT_FILE);
    
    // Generate statistics
    const stats = generateStatistics(records);
    
    const endTime = Date.now();
    const duration = endTime - startTime;
    
    console.log('\n📈 Test Data Statistics:');
    console.log(`├── Total Records: ${stats.totalRecords}`);
    console.log(`├── Real Contracts: ${stats.realContracts} (${Math.round(stats.realContracts/stats.totalRecords*100)}%)`);
    console.log(`├── Average Gas Limit: ${stats.avgGasLimit.toLocaleString()}`);
    console.log(`├── Networks: ${Object.keys(stats.networks).length}`);
    console.log(`├── Analysis Types: ${Object.keys(stats.analysisTypes).length}`);
    console.log(`├── Crypto Symbols: ${Object.keys(stats.symbols).length}`);
    console.log(`└── Generation Time: ${duration}ms`);
    
    console.log('\n🌐 Network Distribution:');
    Object.entries(stats.networks)
        .sort(([,a], [,b]) => b - a)
        .slice(0, 5)
        .forEach(([network, count]) => {
            const percentage = Math.round(count/stats.totalRecords*100);
            console.log(`├── ${network}: ${count} (${percentage}%)`);
        });
    
    console.log('\n🔍 Analysis Type Distribution:');
    Object.entries(stats.analysisTypes)
        .sort(([,a], [,b]) => b - a)
        .forEach(([type, count]) => {
            const percentage = Math.round(count/stats.totalRecords*100);
            console.log(`├── ${type}: ${count} (${percentage}%)`);
        });
    
    console.log('\n💰 Top Cryptocurrencies:');
    Object.entries(stats.symbols)
        .sort(([,a], [,b]) => b - a)
        .slice(0, 10)
        .forEach(([symbol, count]) => {
            const percentage = Math.round(count/stats.totalRecords*100);
            console.log(`├── ${symbol}: ${count} (${percentage}%)`);
        });
    
    console.log('\n🎯 Priority Distribution:');
    Object.entries(stats.priorities)
        .sort(([,a], [,b]) => b - a)
        .forEach(([priority, count]) => {
            const percentage = Math.round(count/stats.totalRecords*100);
            console.log(`├── ${priority}: ${count} (${percentage}%)`);
        });
    
    console.log('\n✅ Test data generation completed successfully!');
    console.log(`\n🚀 Ready for Artillery load testing with: artillery run artillery-500-concurrent-enhanced.yml`);
}

// Run the generator
main();

export {
    generateTestData,
    generateStatistics,
    writeCSV
};