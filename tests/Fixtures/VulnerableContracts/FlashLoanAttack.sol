// SPDX-License-Identifier: MIT
pragma solidity ^0.8.0;

/**
 * @title FlashLoanAttack - Flash Loan Attack Vulnerabilities
 * @dev This contract demonstrates vulnerabilities that can be exploited using flash loans
 * including price manipulation, governance attacks, and arbitrage exploitation
 * 
 * VULNERABILITY: Flash Loan Attack
 * SEVERITY: Critical
 * CWE: CWE-841 (Improper Enforcement of Behavioral Workflow)
 * 
 * Expected Findings:
 * - Single transaction price manipulation
 * - Governance token flash loan voting
 * - Liquidity pool manipulation
 * - Oracle price manipulation via flash loans
 */

interface IERC20 {
    function transfer(address to, uint256 amount) external returns (bool);
    function transferFrom(address from, address to, uint256 amount) external returns (bool);
    function balanceOf(address account) external view returns (uint256);
    function totalSupply() external view returns (uint256);
}

interface IFlashLoanProvider {
    function flashLoan(address token, uint256 amount, bytes calldata data) external;
}

contract FlashLoanAttack {
    address public owner;
    IERC20 public governanceToken;
    IERC20 public liquidityToken;
    
    // Price oracle state (vulnerable)
    uint256 public tokenPrice;
    uint256 public lastPriceUpdate;
    
    // Governance state
    struct Proposal {
        string description;
        uint256 votesFor;
        uint256 votesAgainst;
        uint256 deadline;
        bool executed;
    }
    
    mapping(uint256 => Proposal) public proposals;
    mapping(uint256 => mapping(address => bool)) public hasVoted;
    uint256 public proposalCounter;
    
    // Liquidity pool state (simplified AMM)
    uint256 public tokenAReserve = 1000000; // 1M tokens
    uint256 public tokenBReserve = 1000000; // 1M tokens
    uint256 public totalLiquidity = 1000000;
    mapping(address => uint256) public liquidityShares;
    
    constructor(address _governanceToken, address _liquidityToken) {
        owner = msg.sender;
        governanceToken = IERC20(_governanceToken);
        liquidityToken = IERC20(_liquidityToken);
        tokenPrice = 100; // Initial price
    }
    
    // VULNERABLE: Price oracle that can be manipulated in single transaction
    function updatePrice() external {
        // VULNERABILITY: Price calculated from manipulatable liquidity pool
        uint256 newPrice = (tokenBReserve * 1e18) / tokenAReserve;
        tokenPrice = newPrice;
        lastPriceUpdate = block.timestamp;
    }
    
    function getPrice() external view returns (uint256) {
        return tokenPrice;
    }
    
    // VULNERABLE: Governance voting without time locks or delegation checks
    function createProposal(string memory description) external returns (uint256) {
        uint256 proposalId = proposalCounter++;
        proposals[proposalId] = Proposal({
            description: description,
            votesFor: 0,
            votesAgainst: 0,
            deadline: block.timestamp + 1 days,
            executed: false
        });
        return proposalId;
    }
    
    function vote(uint256 proposalId, bool support) external {
        require(block.timestamp <= proposals[proposalId].deadline, "Voting ended");
        require(!hasVoted[proposalId][msg.sender], "Already voted");
        
        // VULNERABILITY: Vote weight based on current balance (flashloan exploitable)
        uint256 votes = governanceToken.balanceOf(msg.sender);
        
        if (support) {
            proposals[proposalId].votesFor += votes;
        } else {
            proposals[proposalId].votesAgainst += votes;
        }
        
        hasVoted[proposalId][msg.sender] = true;
    }
    
    function executeProposal(uint256 proposalId) external {
        Proposal storage proposal = proposals[proposalId];
        require(block.timestamp > proposal.deadline, "Voting not ended");
        require(!proposal.executed, "Already executed");
        require(proposal.votesFor > proposal.votesAgainst, "Proposal rejected");
        
        proposal.executed = true;
        // Execute proposal logic here
    }
    
    // VULNERABLE: AMM swap without slippage protection
    function swapAForB(uint256 amountA) external {
        require(amountA > 0, "Amount must be positive");
        
        // VULNERABILITY: Can be manipulated with flash loans for sandwich attacks
        uint256 amountB = (amountA * tokenBReserve) / (tokenAReserve + amountA);
        
        tokenAReserve += amountA;
        tokenBReserve -= amountB;
        
        // Transfer tokens (simplified)
        // In real implementation, would handle token transfers properly
    }
    
    function swapBForA(uint256 amountB) external {
        require(amountB > 0, "Amount must be positive");
        
        // VULNERABILITY: Price impact can be exploited
        uint256 amountA = (amountB * tokenAReserve) / (tokenBReserve + amountB);
        
        tokenBReserve += amountB;
        tokenAReserve -= amountA;
    }
    
    // VULNERABLE: Liquidation based on manipulatable price
    mapping(address => uint256) public collateral;
    mapping(address => uint256) public debt;
    
    function deposit() external payable {
        collateral[msg.sender] += msg.value;
    }
    
    function borrow(uint256 amount) external {
        uint256 maxBorrow = (collateral[msg.sender] * tokenPrice * 80) / (100 * 1e18); // 80% LTV
        require(debt[msg.sender] + amount <= maxBorrow, "Insufficient collateral");
        
        debt[msg.sender] += amount;
        // Transfer borrowed tokens
    }
    
    function liquidate(address user) external {
        // VULNERABILITY: Liquidation threshold based on current price (flash loan manipulatable)
        uint256 collateralValue = (collateral[user] * tokenPrice) / 1e18;
        uint256 debtValue = debt[user];
        
        require(collateralValue < debtValue * 120 / 100, "Position healthy"); // 120% threshold
        
        // Liquidator gets bonus
        uint256 liquidationBonus = collateralValue / 10; // 10% bonus
        payable(msg.sender).transfer(liquidationBonus);
        
        // Clear position
        collateral[user] = 0;
        debt[user] = 0;
    }
    
    // VULNERABLE: Flash loan callback without proper validation
    function onFlashLoan(
        address token,
        uint256 amount,
        uint256 fee,
        bytes calldata data
    ) external {
        // VULNERABILITY: No validation of flash loan provider
        // Attacker can call this directly
        
        // Decode attack parameters
        (uint256 attackType, bytes memory attackData) = abi.decode(data, (uint256, bytes));
        
        if (attackType == 1) {
            // Price manipulation attack
            _executePriceManipulation(amount, attackData);
        } else if (attackType == 2) {
            // Governance attack
            _executeGovernanceAttack(amount, attackData);
        }
        
        // VULNERABILITY: Assumes flash loan will be repaid, but doesn't enforce it
        IERC20(token).transfer(msg.sender, amount + fee);
    }
    
    function _executePriceManipulation(uint256 amount, bytes memory data) internal {
        // Use flash loaned tokens to manipulate price
        swapAForB(amount / 2);
        
        // Update price based on manipulated pool
        updatePrice();
        
        // Exploit the manipulated price (e.g., liquidations, arbitrage)
        // ... attack logic here
        
        // Swap back to repay flash loan
        swapBForA(tokenBReserve / 4);
    }
    
    function _executeGovernanceAttack(uint256 amount, bytes memory data) internal {
        uint256 proposalId = abi.decode(data, (uint256));
        
        // Use flash loaned governance tokens to vote
        vote(proposalId, true);
        
        // If proposal can be executed immediately, do so
        if (block.timestamp > proposals[proposalId].deadline) {
            executeProposal(proposalId);
        }
    }
    
    // VULNERABLE: Single-block MEV extraction
    function extractMEV() external {
        // VULNERABILITY: Complex operation that can be exploited with flash loans
        // Update price
        updatePrice();
        
        // Perform arbitrage based on price difference
        uint256 priceA = tokenPrice;
        uint256 priceB = (tokenBReserve * 1e18) / tokenAReserve;
        
        if (priceA != priceB) {
            // Flash loan exploit opportunity
            uint256 profit = priceA > priceB ? priceA - priceB : priceB - priceA;
            // ... MEV extraction logic
        }
    }
}