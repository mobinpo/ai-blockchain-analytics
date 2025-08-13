// SPDX-License-Identifier: MIT
pragma solidity ^0.8.0;

/**
 * @title FrontRunning - Front-Running and MEV Vulnerabilities
 * @dev This contract demonstrates various front-running vulnerabilities
 * where transaction ordering can be exploited for profit
 * 
 * VULNERABILITY: Front-Running / MEV
 * SEVERITY: Medium to High
 * CWE: CWE-362 (Concurrent Execution using Shared Resource with Improper Synchronization)
 * 
 * Expected Findings:
 * - Price oracle manipulation vulnerability
 * - Auction front-running
 * - Commit-reveal scheme missing
 * - Transaction ordering dependency
 */
contract FrontRunning {
    address public owner;
    mapping(address => uint256) public balances;
    
    // Price oracle state
    uint256 public currentPrice = 100; // Starting price
    address public lastUpdater;
    
    // Auction state
    struct Auction {
        uint256 highestBid;
        address highestBidder;
        uint256 endTime;
        bool ended;
    }
    
    mapping(uint256 => Auction) public auctions;
    uint256 public auctionCounter;
    
    constructor() {
        owner = msg.sender;
    }
    
    // VULNERABLE: Price update can be front-run
    function updatePrice(uint256 newPrice) external {
        // VULNERABILITY: Anyone can see this transaction in mempool and front-run
        require(newPrice > 0, "Price must be positive");
        
        currentPrice = newPrice;
        lastUpdater = msg.sender;
        
        // Reward for updating price - creates front-running incentive
        payable(msg.sender).transfer(0.01 ether);
    }
    
    // VULNERABLE: Buying at current price can be front-run
    function buyTokens() external payable {
        // VULNERABILITY: Attacker can see this tx and update price first
        uint256 tokenAmount = msg.value / currentPrice;
        require(tokenAmount > 0, "Insufficient payment");
        
        balances[msg.sender] += tokenAmount;
    }
    
    // VULNERABLE: Auction bidding without commit-reveal
    function placeBid(uint256 auctionId) external payable {
        Auction storage auction = auctions[auctionId];
        require(block.timestamp < auction.endTime, "Auction ended");
        require(msg.value > auction.highestBid, "Bid too low");
        
        // VULNERABILITY: Bid amount is visible in mempool
        // Others can front-run with slightly higher bid
        
        // Refund previous highest bidder
        if (auction.highestBidder != address(0)) {
            payable(auction.highestBidder).transfer(auction.highestBid);
        }
        
        auction.highestBid = msg.value;
        auction.highestBidder = msg.sender;
    }
    
    // VULNERABLE: First-come-first-serve without protection
    function claimReward() external {
        // VULNERABILITY: Miners/MEV bots can reorder transactions
        require(address(this).balance >= 1 ether, "No rewards available");
        
        // Only first caller gets reward - creates front-running race
        payable(msg.sender).transfer(1 ether);
    }
    
    // VULNERABLE: Arbitrage opportunity exposure
    mapping(address => uint256) public tokenPrices; // Different token prices
    
    function setTokenPrice(address token, uint256 price) external {
        require(msg.sender == owner, "Only owner");
        tokenPrices[token] = price;
    }
    
    function arbitrageSwap(address tokenA, address tokenB, uint256 amount) external {
        // VULNERABILITY: Arbitrage opportunity visible in mempool
        uint256 priceA = tokenPrices[tokenA];
        uint256 priceB = tokenPrices[tokenB];
        
        require(priceA != priceB, "No arbitrage opportunity");
        
        // Calculate profit - this calculation is visible to front-runners
        uint256 profit = amount * (priceA > priceB ? priceA - priceB : priceB - priceA) / 1000;
        
        balances[msg.sender] += profit;
    }
    
    // VULNERABLE: Liquidation without proper ordering protection
    mapping(address => uint256) public debts;
    mapping(address => uint256) public collateral;
    
    function liquidate(address user) external {
        uint256 collateralValue = collateral[user] * currentPrice;
        uint256 debtValue = debts[user];
        
        // VULNERABILITY: Liquidation can be front-run by price manipulation
        require(collateralValue < debtValue * 150 / 100, "Position not liquidatable");
        
        // Liquidator gets 10% bonus
        uint256 liquidationBonus = collateralValue * 10 / 100;
        balances[msg.sender] += liquidationBonus;
        
        // Clear position
        collateral[user] = 0;
        debts[user] = 0;
    }
    
    // VULNERABLE: Time-sensitive operation
    uint256 public flashSaleEndTime;
    uint256 public flashSalePrice = 50; // Discounted price
    
    function startFlashSale(uint256 duration) external {
        require(msg.sender == owner, "Only owner");
        flashSaleEndTime = block.timestamp + duration;
    }
    
    function buyFlashSale() external payable {
        // VULNERABILITY: MEV bots can front-run as soon as flash sale starts
        require(block.timestamp <= flashSaleEndTime, "Flash sale ended");
        
        uint256 tokenAmount = msg.value / flashSalePrice;
        balances[msg.sender] += tokenAmount;
    }
}