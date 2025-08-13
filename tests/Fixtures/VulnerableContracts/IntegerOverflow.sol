// SPDX-License-Identifier: MIT
pragma solidity ^0.7.6; // Vulnerable version without automatic overflow protection

/**
 * @title IntegerOverflow - Integer Overflow/Underflow Vulnerability
 * @dev This contract demonstrates integer overflow and underflow vulnerabilities
 * common in Solidity versions before 0.8.0
 * 
 * VULNERABILITY: Integer Overflow/Underflow
 * SEVERITY: High
 * CWE: CWE-190 (Integer Overflow), CWE-191 (Integer Underflow)
 * 
 * Expected Findings:
 * - Integer overflow in transfer function
 * - Integer underflow in withdraw function
 * - Missing SafeMath library usage
 * - Unchecked arithmetic operations
 */
contract IntegerOverflow {
    mapping(address => uint256) public balances;
    uint256 public totalSupply;
    
    constructor() {
        totalSupply = 1000000;
        balances[msg.sender] = totalSupply;
    }
    
    // VULNERABLE: Integer overflow possible
    function transfer(address to, uint256 amount) external {
        require(balances[msg.sender] >= amount, "Insufficient balance");
        
        // VULNERABILITY: No overflow check on addition
        balances[to] += amount;
        balances[msg.sender] -= amount;
    }
    
    // VULNERABLE: Integer underflow possible
    function withdraw(uint256 amount) external {
        // VULNERABILITY: No underflow check on subtraction
        balances[msg.sender] -= amount;
        
        payable(msg.sender).transfer(amount);
    }
    
    // VULNERABLE: Overflow in multiplication
    function calculateReward(uint256 baseAmount, uint256 multiplier) external pure returns (uint256) {
        // VULNERABILITY: No overflow check on multiplication
        return baseAmount * multiplier;
    }
    
    // VULNERABLE: Division by zero not handled
    function divide(uint256 a, uint256 b) external pure returns (uint256) {
        // VULNERABILITY: No check for division by zero
        return a / b;
    }
}