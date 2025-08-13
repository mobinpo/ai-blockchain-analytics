// SPDX-License-Identifier: MIT
pragma solidity ^0.8.0;

/**
 * @title TimestampDependence - Timestamp Dependence Vulnerabilities
 * @dev This contract demonstrates vulnerabilities related to block.timestamp manipulation
 * and time-based logic that can be exploited by miners
 * 
 * VULNERABILITY: Timestamp Dependence
 * SEVERITY: Medium
 * CWE: CWE-367 (Time-of-check Time-of-use Race Condition)
 * 
 * Expected Findings:
 * - Critical logic dependent on block.timestamp
 * - Miner manipulatable time conditions
 * - Insufficient time window validation
 * - Time-based random number generation
 */
contract TimestampDependence {
    address public owner;
    mapping(address => uint256) public balances;
    
    // Lottery state
    uint256 public lotteryStartTime;
    uint256 public lotteryDuration = 1 hours;
    address[] public lotteryParticipants;
    
    // Vesting state
    struct VestingSchedule {
        uint256 totalAmount;
        uint256 startTime;
        uint256 duration;
        uint256 claimedAmount;
    }
    mapping(address => VestingSchedule) public vestingSchedules;
    
    // Auction state
    uint256 public auctionEndTime;
    uint256 public highestBid;
    address public highestBidder;
    
    constructor() {
        owner = msg.sender;
    }
    
    // VULNERABLE: Timestamp-dependent lottery logic
    function enterLottery() external payable {
        require(msg.value >= 0.1 ether, "Minimum entry fee is 0.1 ETH");
        
        // VULNERABILITY: Exact timestamp comparison can be manipulated
        require(block.timestamp >= lotteryStartTime, "Lottery not started");
        require(block.timestamp <= lotteryStartTime + lotteryDuration, "Lottery ended");
        
        lotteryParticipants.push(msg.sender);
    }
    
    // VULNERABLE: Winner selection based on timestamp
    function selectWinner() external {
        require(msg.sender == owner, "Only owner");
        // VULNERABILITY: Using timestamp for randomness
        require(block.timestamp > lotteryStartTime + lotteryDuration, "Lottery still active");
        
        if (lotteryParticipants.length > 0) {
            // VULNERABILITY: Miner can manipulate timestamp to influence outcome
            uint256 winnerIndex = uint256(keccak256(abi.encodePacked(block.timestamp))) % lotteryParticipants.length;
            address winner = lotteryParticipants[winnerIndex];
            
            payable(winner).transfer(address(this).balance);
        }
        
        // Reset lottery
        delete lotteryParticipants;
        lotteryStartTime = block.timestamp + 1 days;
    }
    
    // VULNERABLE: Time-based access control
    function emergencyWithdraw() external {
        // VULNERABILITY: Miner can manipulate timestamp to enable early access
        require(block.timestamp > lotteryStartTime + 30 days, "Emergency period not reached");
        require(msg.sender == owner, "Only owner");
        
        payable(owner).transfer(address(this).balance);
    }
    
    // VULNERABLE: Vesting with timestamp dependence
    function createVesting(address beneficiary, uint256 amount, uint256 duration) external {
        require(msg.sender == owner, "Only owner");
        
        vestingSchedules[beneficiary] = VestingSchedule({
            totalAmount: amount,
            startTime: block.timestamp, // VULNERABILITY: Depends on miner timestamp
            duration: duration,
            claimedAmount: 0
        });
    }
    
    function claimVested() external {
        VestingSchedule storage schedule = vestingSchedules[msg.sender];
        require(schedule.totalAmount > 0, "No vesting schedule");
        
        // VULNERABILITY: Vesting calculation depends on miner-controlled timestamp
        uint256 elapsed = block.timestamp - schedule.startTime;
        uint256 vestedAmount;
        
        if (elapsed >= schedule.duration) {
            vestedAmount = schedule.totalAmount;
        } else {
            vestedAmount = (schedule.totalAmount * elapsed) / schedule.duration;
        }
        
        uint256 claimable = vestedAmount - schedule.claimedAmount;
        require(claimable > 0, "Nothing to claim");
        
        schedule.claimedAmount += claimable;
        balances[msg.sender] += claimable;
    }
    
    // VULNERABLE: Time-based pricing
    function getDynamicPrice() public view returns (uint256) {
        // VULNERABILITY: Price depends on timestamp, miner can manipulate for profit
        uint256 timeSinceDeployment = block.timestamp - 1640995200; // Jan 1, 2022
        uint256 cycles = timeSinceDeployment / 1 hours;
        
        // Price oscillates based on time
        return 100 + (cycles % 50);
    }
    
    function buyTokens() external payable {
        uint256 price = getDynamicPrice();
        uint256 tokenAmount = msg.value / price;
        
        balances[msg.sender] += tokenAmount;
    }
    
    // VULNERABLE: Auction with timestamp manipulation
    function placeBid() external payable {
        require(block.timestamp < auctionEndTime, "Auction ended");
        require(msg.value > highestBid, "Bid too low");
        
        // Refund previous bidder
        if (highestBidder != address(0)) {
            payable(highestBidder).transfer(highestBid);
        }
        
        highestBid = msg.value;
        highestBidder = msg.sender;
        
        // VULNERABILITY: Extend auction by 10 minutes if bid in last 10 minutes
        // Miner can manipulate timestamp to prevent extension
        if (auctionEndTime - block.timestamp < 10 minutes) {
            auctionEndTime = block.timestamp + 10 minutes;
        }
    }
    
    // VULNERABLE: Time-locked functions with short windows
    mapping(address => uint256) public unlockTimes;
    
    function lockFunds(uint256 duration) external payable {
        unlockTimes[msg.sender] = block.timestamp + duration;
        balances[msg.sender] += msg.value;
    }
    
    function unlockFunds() external {
        // VULNERABILITY: Small time windows can be manipulated by miners
        require(block.timestamp >= unlockTimes[msg.sender], "Funds still locked");
        require(block.timestamp <= unlockTimes[msg.sender] + 1 minutes, "Unlock window expired");
        
        uint256 amount = balances[msg.sender];
        balances[msg.sender] = 0;
        payable(msg.sender).transfer(amount);
    }
    
    // VULNERABLE: Timestamp for generating seeds
    uint256 private seed;
    
    function generateSeed() external {
        // VULNERABILITY: Using timestamp as entropy source
        seed = uint256(keccak256(abi.encodePacked(block.timestamp, msg.sender)));
    }
    
    function getRandomNumber() external view returns (uint256) {
        // VULNERABILITY: Predictable random number based on timestamp
        return uint256(keccak256(abi.encodePacked(seed, block.timestamp))) % 1000;
    }
}