// SPDX-License-Identifier: MIT
pragma solidity ^0.8.0;

/**
 * @title DenialOfService - Denial of Service Vulnerabilities
 * @dev This contract demonstrates various DoS attack vectors
 * including gas limit DoS, external call DoS, and unexpected revert DoS
 * 
 * VULNERABILITY: Denial of Service
 * SEVERITY: Medium to High
 * CWE: CWE-400 (Uncontrolled Resource Consumption)
 * 
 * Expected Findings:
 * - Unbounded loops leading to gas limit DoS
 * - External call dependency causing DoS
 * - Array manipulation without bounds
 * - Unexpected revert DoS patterns
 */
contract DenialOfService {
    address public owner;
    address[] public participants;
    mapping(address => uint256) public balances;
    mapping(address => bool) public isParticipant;
    
    // Auction state
    address public highestBidder;
    uint256 public highestBid;
    
    constructor() {
        owner = msg.sender;
    }
    
    // VULNERABLE: Unbounded loop - gas limit DoS
    function distributeRewards() external {
        require(msg.sender == owner, "Only owner");
        
        // VULNERABILITY: Loop over all participants without gas limit check
        for (uint i = 0; i < participants.length; i++) {
            payable(participants[i]).transfer(0.1 ether);
        }
    }
    
    // VULNERABLE: External call DoS in refund mechanism
    function refundAll() external {
        require(msg.sender == owner, "Only owner");
        
        // VULNERABILITY: If any participant's receive() reverts, entire function fails
        for (uint i = 0; i < participants.length; i++) {
            address participant = participants[i];
            uint256 balance = balances[participant];
            
            if (balance > 0) {
                balances[participant] = 0;
                // VULNERABILITY: External call can fail and block all refunds
                payable(participant).transfer(balance);
            }
        }
    }
    
    // VULNERABLE: Auction with external call DoS
    function bid() external payable {
        require(msg.value > highestBid, "Bid too low");
        
        // Return funds to previous highest bidder
        if (highestBidder != address(0)) {
            // VULNERABILITY: If previous bidder's receive() reverts, new bids are blocked
            payable(highestBidder).transfer(highestBid);
        }
        
        highestBidder = msg.sender;
        highestBid = msg.value;
    }
    
    // VULNERABLE: Unbounded array operations
    function addParticipants(address[] memory newParticipants) external {
        require(msg.sender == owner, "Only owner");
        
        // VULNERABILITY: No limit on array size, can cause out-of-gas
        for (uint i = 0; i < newParticipants.length; i++) {
            if (!isParticipant[newParticipants[i]]) {
                participants.push(newParticipants[i]);
                isParticipant[newParticipants[i]] = true;
            }
        }
    }
    
    // VULNERABLE: Gas grief via external calls
    function processWithdrawals() external {
        require(msg.sender == owner, "Only owner");
        
        for (uint i = 0; i < participants.length; i++) {
            address participant = participants[i];
            
            // VULNERABILITY: External call with all remaining gas
            participant.call{value: balances[participant]}("");
            balances[participant] = 0;
        }
    }
    
    // VULNERABLE: Block gas limit DoS through computation
    function expensiveCalculation(uint256 iterations) external view returns (uint256) {
        // VULNERABILITY: No gas limit on computation, can hit block gas limit
        uint256 result = 0;
        for (uint i = 0; i < iterations; i++) {
            result += i * i;
        }
        return result;
    }
    
    // VULNERABLE: Unexpected revert DoS
    mapping(address => bool) public blacklisted;
    
    function processPayments() external {
        require(msg.sender == owner, "Only owner");
        
        for (uint i = 0; i < participants.length; i++) {
            address participant = participants[i];
            
            // VULNERABILITY: If any participant is blacklisted, entire function fails
            require(!blacklisted[participant], "Blacklisted participant found");
            
            payable(participant).transfer(0.05 ether);
        }
    }
    
    // VULNERABLE: Array deletion causing gas issues
    function removeParticipant(address participant) external {
        require(msg.sender == owner, "Only owner");
        
        // VULNERABILITY: Linear search through array for removal
        for (uint i = 0; i < participants.length; i++) {
            if (participants[i] == participant) {
                // VULNERABILITY: Inefficient array manipulation
                for (uint j = i; j < participants.length - 1; j++) {
                    participants[j] = participants[j + 1];
                }
                participants.pop();
                isParticipant[participant] = false;
                break;
            }
        }
    }
    
    // VULNERABLE: External dependency DoS
    interface IPriceOracle {
        function getPrice() external view returns (uint256);
    }
    
    IPriceOracle public priceOracle;
    
    function setPriceOracle(address oracle) external {
        require(msg.sender == owner, "Only owner");
        priceOracle = IPriceOracle(oracle);
    }
    
    function calculateValue() external view returns (uint256) {
        // VULNERABILITY: Depends on external contract that might revert or be slow
        uint256 price = priceOracle.getPrice();
        return address(this).balance * price;
    }
    
    // VULNERABLE: Fallback function with expensive operations
    fallback() external payable {
        // VULNERABILITY: Expensive operations in fallback can cause DoS
        for (uint i = 0; i < participants.length; i++) {
            balances[participants[i]] += msg.value / participants.length;
        }
    }
}