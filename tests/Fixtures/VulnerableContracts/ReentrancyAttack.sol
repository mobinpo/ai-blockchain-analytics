// SPDX-License-Identifier: MIT
pragma solidity ^0.8.0;

/**
 * @title ReentrancyAttack - Classic Reentrancy Vulnerability
 * @dev This contract demonstrates the infamous reentrancy vulnerability
 * that was exploited in the DAO hack of 2016
 * 
 * VULNERABILITY: Reentrancy
 * SEVERITY: Critical
 * CWE: CWE-284 (Improper Access Control)
 * 
 * Expected Findings:
 * - Reentrancy vulnerability in withdraw function
 * - State changes after external call
 * - Missing reentrancy guard
 * - Unsafe external call pattern
 */
contract ReentrancyAttack {
    mapping(address => uint256) public balances;
    
    function deposit() external payable {
        balances[msg.sender] += msg.value;
    }
    
    // VULNERABLE: Reentrancy attack possible
    function withdraw() external {
        uint256 amount = balances[msg.sender];
        require(amount > 0, "Insufficient balance");
        
        // VULNERABILITY: External call before state update
        (bool success, ) = msg.sender.call{value: amount}("");
        require(success, "Transfer failed");
        
        // VULNERABILITY: State updated after external call
        balances[msg.sender] = 0;
    }
    
    function getBalance() external view returns (uint256) {
        return balances[msg.sender];
    }
}