<?php

namespace Tests\Fixtures;

/**
 * Collection of known vulnerable smart contracts for regression testing
 * These contracts contain well-documented vulnerabilities that our analysis should detect
 */
class VulnerableContracts
{
    /**
     * Get all vulnerable contract test cases
     */
    public static function getAllTestCases(): array
    {
        return [
            self::getReentrancyContract(),
            self::getIntegerOverflowContract(),
            self::getAccessControlContract(),
            self::getUncheckedCallContract(),
            self::getTimestampDependenceContract(),
            self::getDenialOfServiceContract(),
            self::getTxOriginContract(),
            self::getUninitializedStorageContract(),
            self::getFrontRunningContract(),
            self::getRandomnessContract()
        ];
    }

    /**
     * Classic reentrancy vulnerability (DAO-style)
     */
    public static function getReentrancyContract(): array
    {
        return [
            'name' => 'Reentrancy Vulnerability',
            'category' => 'REENTRANCY',
            'description' => 'Classic reentrancy vulnerability similar to the DAO hack',
            'source_code' => '
pragma solidity ^0.8.0;

contract VulnerableBank {
    mapping(address => uint256) public balances;
    
    function deposit() public payable {
        balances[msg.sender] += msg.value;
    }
    
    function withdraw(uint256 amount) public {
        require(balances[msg.sender] >= amount, "Insufficient balance");
        
        // Vulnerable: External call before state change
        (bool success, ) = msg.sender.call{value: amount}("");
        require(success, "Transfer failed");
        
        balances[msg.sender] -= amount; // State change after external call
    }
    
    function getBalance() public view returns (uint256) {
        return balances[msg.sender];
    }
}',
            'expected_findings' => [
                [
                    'severity' => 'HIGH',
                    'category' => 'REENTRANCY',
                    'title_contains' => 'reentrancy',
                    'location_function' => 'withdraw',
                    'swc_id' => 'SWC-107'
                ]
            ],
            'network' => 'ethereum',
            'compiler_version' => '^0.8.0'
        ];
    }

    /**
     * Integer overflow vulnerability (pre-Solidity 0.8.0 style)
     */
    public static function getIntegerOverflowContract(): array
    {
        return [
            'name' => 'Integer Overflow Vulnerability',
            'category' => 'ARITHMETIC',
            'description' => 'Integer overflow vulnerability in token calculation',
            'source_code' => '
pragma solidity ^0.7.0;

contract VulnerableToken {
    mapping(address => uint256) public balances;
    uint256 public totalSupply;
    
    function transfer(address to, uint256 amount) public {
        require(balances[msg.sender] >= amount, "Insufficient balance");
        
        // Vulnerable: No SafeMath, potential overflow
        balances[msg.sender] -= amount;
        balances[to] += amount; // Potential overflow here
    }
    
    function mint(address to, uint256 amount) public {
        // Vulnerable: totalSupply can overflow
        totalSupply += amount;
        balances[to] += amount;
    }
    
    function calculateReward(uint256 stake, uint256 multiplier) public pure returns (uint256) {
        // Vulnerable: multiplication overflow
        return stake * multiplier;
    }
}',
            'expected_findings' => [
                [
                    'severity' => 'MEDIUM',
                    'category' => 'ARITHMETIC',
                    'title_contains' => 'overflow',
                    'location_function' => 'mint',
                    'swc_id' => 'SWC-101'
                ],
                [
                    'severity' => 'MEDIUM',
                    'category' => 'ARITHMETIC',
                    'title_contains' => 'overflow',
                    'location_function' => 'calculateReward'
                ]
            ],
            'network' => 'ethereum',
            'compiler_version' => '^0.7.0'
        ];
    }

    /**
     * Access control vulnerability
     */
    public static function getAccessControlContract(): array
    {
        return [
            'name' => 'Access Control Vulnerability',
            'category' => 'ACCESS_CONTROL',
            'description' => 'Missing access control on critical functions',
            'source_code' => '
pragma solidity ^0.8.0;

contract VulnerableDAO {
    address public owner;
    mapping(address => bool) public members;
    uint256 public funds;
    
    constructor() {
        owner = msg.sender;
    }
    
    function addMember(address member) public {
        // Vulnerable: No access control - anyone can add members
        members[member] = true;
    }
    
    function removeMember(address member) public {
        // Vulnerable: No access control
        members[member] = false;
    }
    
    function withdraw(uint256 amount) public {
        // Vulnerable: Only checks membership, not ownership for critical function
        require(members[msg.sender], "Not a member");
        require(funds >= amount, "Insufficient funds");
        
        funds -= amount;
        payable(msg.sender).transfer(amount);
    }
    
    function emergencyStop() public {
        // Vulnerable: No access control on emergency function
        selfdestruct(payable(msg.sender));
    }
    
    receive() external payable {
        funds += msg.value;
    }
}',
            'expected_findings' => [
                [
                    'severity' => 'HIGH',
                    'category' => 'ACCESS_CONTROL',
                    'title_contains' => 'access control',
                    'location_function' => 'addMember'
                ],
                [
                    'severity' => 'CRITICAL',
                    'category' => 'ACCESS_CONTROL',
                    'title_contains' => 'access control',
                    'location_function' => 'emergencyStop'
                ]
            ],
            'network' => 'ethereum',
            'compiler_version' => '^0.8.0'
        ];
    }

    /**
     * Unchecked external call vulnerability
     */
    public static function getUncheckedCallContract(): array
    {
        return [
            'name' => 'Unchecked External Call',
            'category' => 'UNCHECKED_CALLS',
            'description' => 'External calls without checking return values',
            'source_code' => '
pragma solidity ^0.8.0;

interface IERC20 {
    function transfer(address to, uint256 amount) external returns (bool);
    function transferFrom(address from, address to, uint256 amount) external returns (bool);
}

contract VulnerableExchange {
    IERC20 public token;
    mapping(address => uint256) public deposits;
    
    constructor(address _token) {
        token = IERC20(_token);
    }
    
    function deposit(uint256 amount) public {
        // Vulnerable: Not checking return value
        token.transferFrom(msg.sender, address(this), amount);
        deposits[msg.sender] += amount;
    }
    
    function withdraw(uint256 amount) public {
        require(deposits[msg.sender] >= amount, "Insufficient balance");
        
        deposits[msg.sender] -= amount;
        // Vulnerable: Not checking return value
        token.transfer(msg.sender, amount);
    }
    
    function batchTransfer(address[] memory recipients, uint256[] memory amounts) public {
        require(recipients.length == amounts.length, "Length mismatch");
        
        for (uint i = 0; i < recipients.length; i++) {
            // Vulnerable: Not checking return value, could silently fail
            token.transfer(recipients[i], amounts[i]);
        }
    }
}',
            'expected_findings' => [
                [
                    'severity' => 'MEDIUM',
                    'category' => 'UNCHECKED_CALLS',
                    'title_contains' => 'unchecked',
                    'location_function' => 'deposit'
                ],
                [
                    'severity' => 'MEDIUM',
                    'category' => 'UNCHECKED_CALLS',
                    'title_contains' => 'return value',
                    'location_function' => 'withdraw'
                ]
            ],
            'network' => 'ethereum',
            'compiler_version' => '^0.8.0'
        ];
    }

    /**
     * Timestamp dependence vulnerability
     */
    public static function getTimestampDependenceContract(): array
    {
        return [
            'name' => 'Timestamp Dependence',
            'category' => 'TIME_MANIPULATION',
            'description' => 'Vulnerable to miner timestamp manipulation',
            'source_code' => '
pragma solidity ^0.8.0;

contract VulnerableLottery {
    address public winner;
    uint256 public prize;
    uint256 public endTime;
    mapping(address => bool) public participants;
    
    constructor() {
        endTime = block.timestamp + 1 days;
        prize = 1 ether;
    }
    
    function participate() public payable {
        require(msg.value == 0.1 ether, "Entry fee is 0.1 ETH");
        require(block.timestamp < endTime, "Lottery ended");
        
        participants[msg.sender] = true;
    }
    
    function selectWinner() public {
        require(block.timestamp >= endTime, "Lottery not ended yet");
        require(winner == address(0), "Winner already selected");
        
        // Vulnerable: Using block.timestamp for randomness
        uint256 randomNumber = uint256(keccak256(abi.encodePacked(block.timestamp, block.difficulty))) % 100;
        
        if (randomNumber > 50) {
            winner = msg.sender;
            payable(winner).transfer(prize);
        }
    }
    
    function emergencyWithdraw() public {
        // Vulnerable: Time-based access control
        require(block.timestamp > endTime + 7 days, "Emergency period not reached");
        payable(msg.sender).transfer(address(this).balance);
    }
}',
            'expected_findings' => [
                [
                    'severity' => 'HIGH',
                    'category' => 'TIME_MANIPULATION',
                    'title_contains' => 'timestamp',
                    'location_function' => 'selectWinner',
                    'swc_id' => 'SWC-116'
                ],
                [
                    'severity' => 'HIGH',
                    'category' => 'BAD_RANDOMNESS',
                    'title_contains' => 'randomness',
                    'location_function' => 'selectWinner',
                    'swc_id' => 'SWC-120'
                ]
            ],
            'network' => 'ethereum',
            'compiler_version' => '^0.8.0'
        ];
    }

    /**
     * Denial of Service vulnerability
     */
    public static function getDenialOfServiceContract(): array
    {
        return [
            'name' => 'Denial of Service',
            'category' => 'DENIAL_OF_SERVICE',
            'description' => 'DoS through gas limit and failed calls',
            'source_code' => '
pragma solidity ^0.8.0;

contract VulnerableAuction {
    address public highestBidder;
    uint256 public highestBid;
    mapping(address => uint256) public bids;
    
    function bid() public payable {
        require(msg.value > highestBid, "Bid too low");
        
        // Vulnerable: DoS if refund fails
        if (highestBidder != address(0)) {
            // This can fail and block new bids
            payable(highestBidder).transfer(highestBid);
        }
        
        highestBidder = msg.sender;
        highestBid = msg.value;
        bids[msg.sender] = msg.value;
    }
    
    function refundAll(address[] memory bidders) public {
        // Vulnerable: DoS through gas limit
        for (uint i = 0; i < bidders.length; i++) {
            if (bids[bidders[i]] > 0) {
                uint256 amount = bids[bidders[i]];
                bids[bidders[i]] = 0;
                // Could run out of gas with large arrays
                payable(bidders[i]).transfer(amount);
            }
        }
    }
    
    function emergencyWithdraw() public {
        require(msg.sender == highestBidder, "Not highest bidder");
        
        // Vulnerable: Could fail and lock funds
        payable(msg.sender).transfer(address(this).balance);
    }
}',
            'expected_findings' => [
                [
                    'severity' => 'HIGH',
                    'category' => 'DENIAL_OF_SERVICE',
                    'title_contains' => 'denial of service',
                    'location_function' => 'bid',
                    'swc_id' => 'SWC-113'
                ],
                [
                    'severity' => 'MEDIUM',
                    'category' => 'DENIAL_OF_SERVICE',
                    'title_contains' => 'gas limit',
                    'location_function' => 'refundAll'
                ]
            ],
            'network' => 'ethereum',
            'compiler_version' => '^0.8.0'
        ];
    }

    /**
     * tx.origin vulnerability
     */
    public static function getTxOriginContract(): array
    {
        return [
            'name' => 'tx.origin Vulnerability',
            'category' => 'TX_ORIGIN_USAGE',
            'description' => 'Using tx.origin for authorization',
            'source_code' => '
pragma solidity ^0.8.0;

contract VulnerableWallet {
    address public owner;
    mapping(address => bool) public authorized;
    
    constructor() {
        owner = msg.sender;
        authorized[msg.sender] = true;
    }
    
    modifier onlyOwner() {
        // Vulnerable: Using tx.origin instead of msg.sender
        require(tx.origin == owner, "Not owner");
        _;
    }
    
    modifier onlyAuthorized() {
        // Vulnerable: tx.origin can be manipulated through phishing
        require(authorized[tx.origin], "Not authorized");
        _;
    }
    
    function withdraw(uint256 amount) public onlyOwner {
        require(address(this).balance >= amount, "Insufficient balance");
        payable(tx.origin).transfer(amount);
    }
    
    function transfer(address to, uint256 amount) public onlyAuthorized {
        require(address(this).balance >= amount, "Insufficient balance");
        payable(to).transfer(amount);
    }
    
    function addAuthorized(address user) public onlyOwner {
        authorized[user] = true;
    }
    
    receive() external payable {}
}',
            'expected_findings' => [
                [
                    'severity' => 'HIGH',
                    'category' => 'TX_ORIGIN_USAGE',
                    'title_contains' => 'tx.origin',
                    'location_function' => 'onlyOwner',
                    'swc_id' => 'SWC-115'
                ],
                [
                    'severity' => 'HIGH',
                    'category' => 'TX_ORIGIN_USAGE',
                    'title_contains' => 'tx.origin',
                    'location_function' => 'onlyAuthorized'
                ]
            ],
            'network' => 'ethereum',
            'compiler_version' => '^0.8.0'
        ];
    }

    /**
     * Uninitialized storage vulnerability
     */
    public static function getUninitializedStorageContract(): array
    {
        return [
            'name' => 'Uninitialized Storage',
            'category' => 'UNINITIALIZED_STORAGE',
            'description' => 'Uninitialized storage variables vulnerability',
            'source_code' => '
pragma solidity ^0.7.0;

contract VulnerableStorage {
    address public owner;
    uint256 public totalSupply;
    mapping(address => uint256) public balances;
    
    struct User {
        address addr;
        uint256 balance;
        bool active;
    }
    
    constructor() {
        owner = msg.sender;
        totalSupply = 1000000;
    }
    
    function updateUser(uint256 index) public {
        User memory user; // Vulnerable: uninitialized storage
        
        // This could overwrite important storage slots
        user.addr = msg.sender;
        user.balance = 1000;
        user.active = true;
        
        // Dangerous operations with uninitialized storage
        if (user.addr == owner) {
            totalSupply += user.balance;
        }
    }
    
    function processUsers() public {
        User storage user; // Vulnerable: uninitialized storage pointer
        
        // This could point to arbitrary storage slots
        user.addr = msg.sender;
        user.balance = balances[msg.sender];
        user.active = true;
    }
    
    function dangerousFunction() public {
        User storage user; // Uninitialized
        
        // Could overwrite owner or other critical variables
        user.addr = address(0);
    }
}',
            'expected_findings' => [
                [
                    'severity' => 'HIGH',
                    'category' => 'UNINITIALIZED_STORAGE',
                    'title_contains' => 'uninitialized',
                    'location_function' => 'updateUser',
                    'swc_id' => 'SWC-109'
                ],
                [
                    'severity' => 'HIGH',
                    'category' => 'UNINITIALIZED_STORAGE',
                    'title_contains' => 'storage',
                    'location_function' => 'processUsers'
                ]
            ],
            'network' => 'ethereum',
            'compiler_version' => '^0.7.0'
        ];
    }

    /**
     * Front-running vulnerability
     */
    public static function getFrontRunningContract(): array
    {
        return [
            'name' => 'Front-running Vulnerability',
            'category' => 'FRONT_RUNNING',
            'description' => 'Vulnerable to front-running attacks',
            'source_code' => '
pragma solidity ^0.8.0;

contract VulnerableExchange {
    mapping(address => uint256) public balances;
    uint256 public exchangeRate = 100; // 1 ETH = 100 tokens
    
    event RateUpdate(uint256 newRate);
    
    function updateExchangeRate(uint256 newRate) public {
        // Vulnerable: Rate change is visible in mempool before execution
        exchangeRate = newRate;
        emit RateUpdate(newRate);
    }
    
    function buyTokens() public payable {
        // Vulnerable: Can be front-run when rate is about to change
        uint256 tokens = msg.value * exchangeRate;
        balances[msg.sender] += tokens;
    }
    
    function commitReveal(bytes32 commitment) public {
        // Vulnerable: No actual commit-reveal implementation
        // This is just a placeholder showing the vulnerability
        uint256 value = uint256(commitment);
        balances[msg.sender] += value;
    }
    
    function claimReward(string memory secret) public {
        // Vulnerable: Secret visible in transaction data
        bytes32 hash = keccak256(abi.encodePacked(secret));
        uint256 reward = uint256(hash) % 1000;
        balances[msg.sender] += reward;
    }
}',
            'expected_findings' => [
                [
                    'severity' => 'MEDIUM',
                    'category' => 'FRONT_RUNNING',
                    'title_contains' => 'front',
                    'location_function' => 'buyTokens',
                    'swc_id' => 'SWC-114'
                ],
                [
                    'severity' => 'HIGH',
                    'category' => 'FRONT_RUNNING',
                    'title_contains' => 'mev',
                    'location_function' => 'updateExchangeRate'
                ]
            ],
            'network' => 'ethereum',
            'compiler_version' => '^0.8.0'
        ];
    }

    /**
     * Bad randomness vulnerability
     */
    public static function getRandomnessContract(): array
    {
        return [
            'name' => 'Bad Randomness',
            'category' => 'BAD_RANDOMNESS',
            'description' => 'Weak sources of randomness',
            'source_code' => '
pragma solidity ^0.8.0;

contract VulnerableRandom {
    uint256 public jackpot = 10 ether;
    uint256 public ticketPrice = 0.1 ether;
    
    function playLottery() public payable {
        require(msg.value == ticketPrice, "Wrong ticket price");
        
        // Vulnerable: Predictable randomness
        uint256 random1 = uint256(keccak256(abi.encodePacked(block.timestamp))) % 100;
        
        if (random1 == 42) {
            payable(msg.sender).transfer(jackpot);
            jackpot = 0;
        }
    }
    
    function spinWheel() public payable {
        require(msg.value >= 0.01 ether, "Minimum bet required");
        
        // Vulnerable: Multiple weak randomness sources
        uint256 random2 = uint256(keccak256(abi.encodePacked(
            block.timestamp,
            block.difficulty,
            msg.sender
        ))) % 10;
        
        if (random2 > 7) {
            payable(msg.sender).transfer(msg.value * 2);
        }
    }
    
    function generateSeed() public view returns (uint256) {
        // Vulnerable: All parameters are predictable
        return uint256(keccak256(abi.encodePacked(
            block.timestamp,
            block.difficulty,
            block.number,
            blockhash(block.number - 1)
        )));
    }
    
    function quickRandom() public view returns (uint256) {
        // Vulnerable: Extremely weak randomness
        return block.timestamp % 10;
    }
    
    receive() external payable {
        jackpot += msg.value;
    }
}',
            'expected_findings' => [
                [
                    'severity' => 'HIGH',
                    'category' => 'BAD_RANDOMNESS',
                    'title_contains' => 'randomness',
                    'location_function' => 'playLottery',
                    'swc_id' => 'SWC-120'
                ],
                [
                    'severity' => 'HIGH',
                    'category' => 'BAD_RANDOMNESS',
                    'title_contains' => 'predictable',
                    'location_function' => 'generateSeed'
                ]
            ],
            'network' => 'ethereum',
            'compiler_version' => '^0.8.0'
        ];
    }

    /**
     * Get a contract by name for testing
     */
    public static function getContractByName(string $name): ?array
    {
        $contracts = self::getAllTestCases();
        
        foreach ($contracts as $contract) {
            if ($contract['name'] === $name) {
                return $contract;
            }
        }
        
        return null;
    }

    /**
     * Get contracts by vulnerability category
     */
    public static function getContractsByCategory(string $category): array
    {
        $contracts = self::getAllTestCases();
        
        return array_filter($contracts, function($contract) use ($category) {
            return $contract['category'] === $category;
        });
    }

    /**
     * Get summary of all test cases
     */
    public static function getTestSummary(): array
    {
        $contracts = self::getAllTestCases();
        $summary = [
            'total_contracts' => count($contracts),
            'categories' => [],
            'expected_findings_total' => 0
        ];

        foreach ($contracts as $contract) {
            $category = $contract['category'];
            if (!isset($summary['categories'][$category])) {
                $summary['categories'][$category] = 0;
            }
            $summary['categories'][$category]++;
            $summary['expected_findings_total'] += count($contract['expected_findings']);
        }

        return $summary;
    }

    /**
     * Get contracts for comprehensive testing (legacy method name)
     */
    public static function getContracts(): array
    {
        $testCases = self::getAllTestCases();
        $contracts = [];
        
        foreach ($testCases as $key => $contract) {
            $contracts[self::getContractKey($contract['name'])] = [
                'name' => $contract['name'],
                'category' => $contract['category'],
                'severity' => self::determineSeverity($contract['expected_findings']),
                'code' => $contract['source_code'],
                'expected_findings' => array_column($contract['expected_findings'], 'title_contains')
            ];
        }
        
        return $contracts;
    }
    
    /**
     * Convert contract name to a key
     */
    private static function getContractKey(string $name): string
    {
        return strtolower(str_replace([' ', '-'], '_', $name));
    }
    
    /**
     * Determine severity based on expected findings
     */
    private static function determineSeverity(array $expectedFindings): string
    {
        $highestSeverity = 'low';
        
        foreach ($expectedFindings as $finding) {
            $severity = strtolower($finding['severity']);
            if ($severity === 'critical') {
                return 'critical';
            } elseif ($severity === 'high' && $highestSeverity !== 'critical') {
                $highestSeverity = 'high';
            } elseif ($severity === 'medium' && !in_array($highestSeverity, ['critical', 'high'])) {
                $highestSeverity = 'medium';
            }
        }
        
        return $highestSeverity;
    }
}