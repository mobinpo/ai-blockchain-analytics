// SPDX-License-Identifier: MIT
pragma solidity ^0.8.0;

/**
 * @title Vulnerable Contracts Test Suite
 * @dev Collection of deliberately vulnerable contracts for regression testing
 * WARNING: These contracts contain known vulnerabilities - DO NOT DEPLOY TO MAINNET
 */

// 1. REENTRANCY VULNERABILITY (SWC-107)
contract ReentrancyVulnerable {
    mapping(address => uint256) public balances;
    
    function deposit() public payable {
        balances[msg.sender] += msg.value;
    }
    
    // VULNERABLE: External call before state change
    function withdraw(uint256 amount) public {
        require(balances[msg.sender] >= amount, "Insufficient balance");
        
        // Vulnerable to reentrancy - external call before state change
        (bool success, ) = msg.sender.call{value: amount}("");
        require(success, "Transfer failed");
        
        balances[msg.sender] -= amount; // State change after external call
    }
    
    function getBalance() public view returns (uint256) {
        return balances[msg.sender];
    }
}

// 2. INTEGER OVERFLOW/UNDERFLOW (SWC-101)
contract IntegerOverflowVulnerable {
    mapping(address => uint256) public balances;
    uint256 public totalSupply;
    
    // VULNERABLE: No SafeMath, potential overflow
    function mint(address to, uint256 amount) public {
        balances[to] += amount; // Potential overflow
        totalSupply += amount; // Potential overflow
    }
    
    // VULNERABLE: Potential underflow
    function burn(address from, uint256 amount) public {
        balances[from] -= amount; // Potential underflow
        totalSupply -= amount; // Potential underflow
    }
    
    // VULNERABLE: Multiplication overflow
    function calculateReward(uint256 principal, uint256 rate) public pure returns (uint256) {
        return principal * rate * 1000; // Potential overflow
    }
}

// 3. ACCESS CONTROL VULNERABILITY (A01:2021)
contract AccessControlVulnerable {
    address public owner;
    mapping(address => uint256) public balances;
    bool public paused;
    
    constructor() {
        owner = msg.sender;
    }
    
    // VULNERABLE: Missing access control
    function withdraw(uint256 amount) public {
        // Anyone can call this function!
        require(balances[msg.sender] >= amount, "Insufficient balance");
        payable(msg.sender).transfer(amount);
        balances[msg.sender] -= amount;
    }
    
    // VULNERABLE: tx.origin instead of msg.sender
    function emergencyWithdraw() public {
        require(tx.origin == owner, "Only owner"); // Should use msg.sender
        payable(owner).transfer(address(this).balance);
    }
    
    // VULNERABLE: Missing modifier
    function setPaused(bool _paused) public {
        // Missing onlyOwner modifier
        paused = _paused;
    }
}

// 4. UNCHECKED EXTERNAL CALLS (SWC-104)
contract UncheckedCallsVulnerable {
    mapping(address => uint256) public balances;
    
    function deposit() public payable {
        balances[msg.sender] += msg.value;
    }
    
    // VULNERABLE: Unchecked call return value
    function withdrawUnchecked(uint256 amount) public {
        require(balances[msg.sender] >= amount, "Insufficient balance");
        
        // Unchecked call - ignores return value
        msg.sender.call{value: amount}("");
        balances[msg.sender] -= amount;
    }
    
    // VULNERABLE: Send can fail silently
    function withdrawWithSend(uint256 amount) public {
        require(balances[msg.sender] >= amount, "Insufficient balance");
        
        // send() can fail silently
        payable(msg.sender).send(amount);
        balances[msg.sender] -= amount;
    }
}

// 5. TIMESTAMP DEPENDENCE (SWC-116)
contract TimestampVulnerable {
    uint256 public gameEnd;
    mapping(address => uint256) public bets;
    address public winner;
    
    constructor() {
        gameEnd = block.timestamp + 1 days; // VULNERABLE: block.timestamp
    }
    
    function placeBet() public payable {
        // VULNERABLE: Using block.timestamp for critical logic
        require(block.timestamp < gameEnd, "Game ended");
        bets[msg.sender] += msg.value;
    }
    
    function determineWinner() public {
        // VULNERABLE: Timestamp manipulation possible
        require(block.timestamp >= gameEnd, "Game not ended");
        
        // Pseudo-random based on timestamp
        uint256 randomness = uint256(keccak256(abi.encode(block.timestamp, block.difficulty)));
        winner = address(uint160(randomness));
    }
}

// 6. WEAK RANDOMNESS (SWC-120)
contract WeakRandomnessVulnerable {
    uint256 public prize = 1 ether;
    
    // VULNERABLE: Predictable randomness
    function lottery() public payable returns (bool) {
        require(msg.value == 0.1 ether, "Must pay 0.1 ETH");
        
        // Weak randomness - miners can manipulate
        uint256 random = uint256(keccak256(abi.encode(
            block.timestamp,
            block.difficulty,
            msg.sender
        ))) % 100;
        
        if (random < 10) { // 10% chance
            payable(msg.sender).transfer(prize);
            return true;
        }
        return false;
    }
    
    // VULNERABLE: Using block hash for randomness
    function coinFlip(bool guess) public payable returns (bool) {
        require(msg.value == 0.01 ether, "Must pay 0.01 ETH");
        
        // Block hash can be predicted/manipulated
        bool result = uint256(blockhash(block.number - 1)) % 2 == 1;
        
        if (result == guess) {
            payable(msg.sender).transfer(msg.value * 2);
            return true;
        }
        return false;
    }
}

// 7. DENIAL OF SERVICE (SWC-113)
contract DosVulnerable {
    address[] public participants;
    mapping(address => uint256) public balances;
    
    function participate() public payable {
        participants.push(msg.sender);
        balances[msg.sender] += msg.value;
    }
    
    // VULNERABLE: Gas limit DoS
    function refundAll() public {
        // Can run out of gas with many participants
        for (uint256 i = 0; i < participants.length; i++) {
            address participant = participants[i];
            uint256 balance = balances[participant];
            if (balance > 0) {
                balances[participant] = 0;
                // External call in loop - can fail and block others
                payable(participant).transfer(balance);
            }
        }
    }
    
    // VULNERABLE: Unbounded loop
    function getParticipantCount() public view returns (uint256) {
        uint256 count = 0;
        // Unbounded loop can cause DoS
        for (uint256 i = 0; i < participants.length; i++) {
            if (balances[participants[i]] > 0) {
                count++;
            }
        }
        return count;
    }
}

// 8. DELEGATECALL VULNERABILITY (SWC-112)
contract DelegatecallVulnerable {
    address public owner;
    uint256 public value;
    
    constructor() {
        owner = msg.sender;
    }
    
    // VULNERABLE: Unchecked delegatecall
    function execute(address target, bytes calldata data) public {
        require(msg.sender == owner, "Only owner");
        
        // Dangerous: delegatecall to arbitrary address
        (bool success, ) = target.delegatecall(data);
        require(success, "Delegatecall failed");
    }
    
    // VULNERABLE: Storage collision
    function updateValue(uint256 newValue) public {
        value = newValue;
    }
}

// 9. FRONT-RUNNING / MEV VULNERABILITY (SWC-114)
contract FrontRunningVulnerable {
    uint256 public secretNumber;
    uint256 public reward = 10 ether;
    bool public solved;
    
    // VULNERABLE: Commit-reveal not implemented
    function guessNumber(uint256 guess) public {
        require(!solved, "Already solved");
        
        // Guess is visible in mempool before execution
        if (guess == secretNumber) {
            solved = true;
            payable(msg.sender).transfer(reward);
        }
    }
    
    // VULNERABLE: Price oracle manipulation
    mapping(address => uint256) public prices;
    
    function updatePrice(address token, uint256 price) public {
        // No access control or validation
        prices[token] = price;
    }
    
    function buyToken(address token, uint256 amount) public payable {
        uint256 price = prices[token];
        require(msg.value >= price * amount, "Insufficient payment");
        
        // Vulnerable to price manipulation attacks
        // Transfer logic here...
    }
}

// 10. SIGNATURE REPLAY VULNERABILITY (SWC-121)
contract SignatureReplayVulnerable {
    mapping(address => uint256) public balances;
    mapping(address => uint256) public nonces;
    
    function deposit() public payable {
        balances[msg.sender] += msg.value;
    }
    
    // VULNERABLE: Missing nonce validation
    function withdrawWithSignature(
        uint256 amount,
        uint8 v,
        bytes32 r,
        bytes32 s
    ) public {
        bytes32 message = keccak256(abi.encode(msg.sender, amount));
        address signer = ecrecover(message, v, r, s);
        
        require(signer == msg.sender, "Invalid signature");
        require(balances[signer] >= amount, "Insufficient balance");
        
        // Missing nonce check - signature can be replayed!
        balances[signer] -= amount;
        payable(signer).transfer(amount);
    }
    
    // VULNERABLE: No domain separator
    function transfer(
        address to,
        uint256 amount,
        uint8 v,
        bytes32 r,
        bytes32 s
    ) public {
        bytes32 message = keccak256(abi.encode(to, amount));
        address from = ecrecover(message, v, r, s);
        
        require(balances[from] >= amount, "Insufficient balance");
        
        // No domain separator or chain ID - cross-chain replay possible
        balances[from] -= amount;
        balances[to] += amount;
    }
}

// BONUS: COMPLEX VULNERABILITY - FLASH LOAN ATTACK SCENARIO
contract FlashLoanVulnerable {
    mapping(address => uint256) public balances;
    uint256 public totalSupply;
    uint256 public price = 1 ether; // 1 token = 1 ETH
    
    function deposit() public payable {
        uint256 tokens = msg.value / price;
        balances[msg.sender] += tokens;
        totalSupply += tokens;
    }
    
    // VULNERABLE: Price manipulation through totalSupply
    function updatePrice() public {
        // Price based on contract balance vs total supply
        if (totalSupply > 0) {
            price = address(this).balance / totalSupply;
        }
    }
    
    function withdraw(uint256 tokens) public {
        require(balances[msg.sender] >= tokens, "Insufficient balance");
        
        updatePrice(); // Update price before withdrawal
        
        uint256 ethAmount = tokens * price;
        balances[msg.sender] -= tokens;
        totalSupply -= tokens;
        
        payable(msg.sender).transfer(ethAmount);
    }
    
    // VULNERABLE: Flash loan without proper checks
    function flashLoan(uint256 amount) public {
        uint256 balanceBefore = address(this).balance;
        
        // Send the loan
        payable(msg.sender).transfer(amount);
        
        // Expect caller to implement flashLoanCallback
        (bool success, ) = msg.sender.call(
            abi.encodeWithSignature("flashLoanCallback(uint256)", amount)
        );
        require(success, "Flash loan callback failed");
        
        // Check balance is restored (but price can be manipulated!)
        require(address(this).balance >= balanceBefore, "Flash loan not repaid");
    }
}

/**
 * @title Test Registry for Vulnerability Detection
 * @dev Maps contract names to expected vulnerability types for testing
 */
contract TestRegistry {
    mapping(string => string[]) public expectedVulnerabilities;
    
    constructor() {
        // Define expected vulnerabilities for each test contract
        expectedVulnerabilities["ReentrancyVulnerable"] = ["SWC-107", "Re-entrancy", "External Call"];
        expectedVulnerabilities["IntegerOverflowVulnerable"] = ["SWC-101", "Integer Overflow", "Arithmetic"];
        expectedVulnerabilities["AccessControlVulnerable"] = ["A01:2021", "Access Control", "tx.origin"];
        expectedVulnerabilities["UncheckedCallsVulnerable"] = ["SWC-104", "Unchecked Call", "Return Value"];
        expectedVulnerabilities["TimestampVulnerable"] = ["SWC-116", "Timestamp Dependence", "Block Timestamp"];
        expectedVulnerabilities["WeakRandomnessVulnerable"] = ["SWC-120", "Weak Randomness", "Predictable"];
        expectedVulnerabilities["DosVulnerable"] = ["SWC-113", "DoS", "Gas Limit"];
        expectedVulnerabilities["DelegatecallVulnerable"] = ["SWC-112", "Delegatecall", "Storage Collision"];
        expectedVulnerabilities["FrontRunningVulnerable"] = ["SWC-114", "Front-running", "MEV"];
        expectedVulnerabilities["SignatureReplayVulnerable"] = ["SWC-121", "Signature Replay", "Nonce"];
    }
    
    function getExpectedVulnerabilities(string memory contractName) 
        public 
        view 
        returns (string[] memory) 
    {
        return expectedVulnerabilities[contractName];
    }
}