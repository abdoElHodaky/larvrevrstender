#!/usr/bin/env node

/**
 * RPC Load Testing Script
 * Tests JSON-RPC 2.0 endpoints with configurable load patterns
 */

const axios = require('axios');
const { performance } = require('perf_hooks');

class RpcLoadTester {
    constructor(url, method, procedure, requests, concurrency) {
        this.url = url;
        this.method = method;
        this.procedure = procedure;
        this.totalRequests = parseInt(requests);
        this.concurrency = parseInt(concurrency);
        this.results = {
            successful: 0,
            failed: 0,
            responseTimes: [],
            errors: [],
            startTime: null,
            endTime: null
        };
    }

    /**
     * Generate RPC request payload
     */
    generateRpcPayload(id, params = {}) {
        return {
            jsonrpc: '2.0',
            method: `${this.method}@${this.procedure}`,
            params: params,
            id: id
        };
    }

    /**
     * Execute single RPC request
     */
    async executeRequest(id, params = {}) {
        const startTime = performance.now();
        
        try {
            const payload = this.generateRpcPayload(id, params);
            const response = await axios.post(this.url, payload, {
                headers: {
                    'Content-Type': 'application/json',
                    'X-Correlation-ID': `load-test-${id}-${Date.now()}`
                },
                timeout: 30000
            });

            const endTime = performance.now();
            const responseTime = endTime - startTime;

            if (response.data && response.data.result !== undefined) {
                this.results.successful++;
                this.results.responseTimes.push(responseTime);
                return { success: true, responseTime, data: response.data };
            } else if (response.data && response.data.error) {
                this.results.failed++;
                this.results.errors.push({
                    id,
                    error: response.data.error,
                    responseTime
                });
                return { success: false, responseTime, error: response.data.error };
            } else {
                throw new Error('Invalid RPC response format');
            }

        } catch (error) {
            const endTime = performance.now();
            const responseTime = endTime - startTime;
            
            this.results.failed++;
            this.results.errors.push({
                id,
                error: error.message,
                responseTime
            });
            
            return { success: false, responseTime, error: error.message };
        }
    }

    /**
     * Execute batch of requests with controlled concurrency
     */
    async executeBatch(requests) {
        const promises = requests.map(({ id, params }) => 
            this.executeRequest(id, params)
        );
        
        return Promise.all(promises);
    }

    /**
     * Generate test parameters based on procedure type
     */
    generateTestParams(id) {
        switch (this.procedure.toLowerCase()) {
            case 'authenticate':
                return {
                    email: `test${id}@reversetender.com`,
                    password: 'testpassword123'
                };
            case 'verifytoken':
                return {
                    token: `test-token-${id}-${Date.now()}`
                };
            case 'hashpassword':
                return {
                    password: `testpassword${id}`
                };
            case 'generaterandomstring':
                return {
                    length: 32,
                    type: 'alphanumeric'
                };
            case 'ping':
            case 'check':
            case 'generateuuid':
            default:
                return {};
        }
    }

    /**
     * Run the complete load test
     */
    async run() {
        console.log(`🚀 Starting RPC Load Test`);
        console.log(`📊 Target: ${this.url}`);
        console.log(`🎯 Method: ${this.method}@${this.procedure}`);
        console.log(`📈 Load: ${this.totalRequests} requests, ${this.concurrency} concurrent`);
        console.log(`⏰ Started at: ${new Date().toISOString()}`);
        console.log('');

        this.results.startTime = performance.now();

        // Create request batches
        const batches = [];
        for (let i = 0; i < this.totalRequests; i += this.concurrency) {
            const batchSize = Math.min(this.concurrency, this.totalRequests - i);
            const batch = [];
            
            for (let j = 0; j < batchSize; j++) {
                const requestId = i + j + 1;
                batch.push({
                    id: requestId,
                    params: this.generateTestParams(requestId)
                });
            }
            
            batches.push(batch);
        }

        // Execute batches sequentially to control concurrency
        let completedRequests = 0;
        for (const batch of batches) {
            await this.executeBatch(batch);
            completedRequests += batch.length;
            
            // Progress indicator
            const progress = Math.round((completedRequests / this.totalRequests) * 100);
            process.stdout.write(`\r⏳ Progress: ${progress}% (${completedRequests}/${this.totalRequests})`);
        }

        this.results.endTime = performance.now();
        console.log('\n');
        
        this.printResults();
    }

    /**
     * Calculate and print test results
     */
    printResults() {
        const totalTime = this.results.endTime - this.results.startTime;
        const avgResponseTime = this.results.responseTimes.length > 0 
            ? this.results.responseTimes.reduce((a, b) => a + b, 0) / this.results.responseTimes.length 
            : 0;
        const minResponseTime = this.results.responseTimes.length > 0 
            ? Math.min(...this.results.responseTimes) 
            : 0;
        const maxResponseTime = this.results.responseTimes.length > 0 
            ? Math.max(...this.results.responseTimes) 
            : 0;
        const requestsPerSecond = (this.totalRequests / totalTime) * 1000;
        const successRate = (this.results.successful / this.totalRequests) * 100;

        // Calculate percentiles
        const sortedTimes = this.results.responseTimes.sort((a, b) => a - b);
        const p50 = this.getPercentile(sortedTimes, 50);
        const p95 = this.getPercentile(sortedTimes, 95);
        const p99 = this.getPercentile(sortedTimes, 99);

        console.log('📊 RPC Load Test Results');
        console.log('========================');
        console.log(`🎯 Method: ${this.method}@${this.procedure}`);
        console.log(`📈 Total Requests: ${this.totalRequests}`);
        console.log(`✅ Successful: ${this.results.successful}`);
        console.log(`❌ Failed: ${this.results.failed}`);
        console.log(`📊 Success Rate: ${successRate.toFixed(2)}%`);
        console.log('');
        console.log('⏱️  Response Time Statistics:');
        console.log(`   Average response time: ${avgResponseTime.toFixed(2)}ms`);
        console.log(`   Min response time: ${minResponseTime.toFixed(2)}ms`);
        console.log(`   Max response time: ${maxResponseTime.toFixed(2)}ms`);
        console.log(`   50th percentile: ${p50.toFixed(2)}ms`);
        console.log(`   95th percentile: ${p95.toFixed(2)}ms`);
        console.log(`   99th percentile: ${p99.toFixed(2)}ms`);
        console.log('');
        console.log('🚀 Performance Metrics:');
        console.log(`   Requests per second: ${requestsPerSecond.toFixed(2)}`);
        console.log(`   Total test duration: ${(totalTime / 1000).toFixed(2)}s`);
        console.log(`   Concurrency level: ${this.concurrency}`);
        console.log('');

        if (this.results.errors.length > 0) {
            console.log('❌ Error Summary:');
            const errorCounts = {};
            this.results.errors.forEach(error => {
                const errorType = error.error.split(':')[0];
                errorCounts[errorType] = (errorCounts[errorType] || 0) + 1;
            });
            
            Object.entries(errorCounts).forEach(([errorType, count]) => {
                console.log(`   ${errorType}: ${count} occurrences`);
            });
            console.log('');
        }

        console.log(`✅ Test completed at: ${new Date().toISOString()}`);
    }

    /**
     * Calculate percentile from sorted array
     */
    getPercentile(sortedArray, percentile) {
        if (sortedArray.length === 0) return 0;
        
        const index = Math.ceil((percentile / 100) * sortedArray.length) - 1;
        return sortedArray[Math.max(0, Math.min(index, sortedArray.length - 1))];
    }
}

// Main execution
async function main() {
    const args = process.argv.slice(2);
    
    if (args.length < 5) {
        console.error('Usage: node rpc-load-test.js <url> <method> <procedure> <requests> <concurrency>');
        console.error('Example: node rpc-load-test.js http://localhost:6010 Health ping 1000 10');
        process.exit(1);
    }

    const [url, method, procedure, requests, concurrency] = args;
    
    // Validate inputs
    if (isNaN(requests) || isNaN(concurrency)) {
        console.error('Error: requests and concurrency must be numbers');
        process.exit(1);
    }

    if (parseInt(concurrency) > parseInt(requests)) {
        console.error('Error: concurrency cannot be greater than total requests');
        process.exit(1);
    }

    try {
        const tester = new RpcLoadTester(url, method, procedure, requests, concurrency);
        await tester.run();
    } catch (error) {
        console.error('❌ Load test failed:', error.message);
        process.exit(1);
    }
}

// Handle graceful shutdown
process.on('SIGINT', () => {
    console.log('\n🛑 Load test interrupted by user');
    process.exit(0);
});

process.on('SIGTERM', () => {
    console.log('\n🛑 Load test terminated');
    process.exit(0);
});

// Run the test
main().catch(error => {
    console.error('❌ Unexpected error:', error);
    process.exit(1);
});
