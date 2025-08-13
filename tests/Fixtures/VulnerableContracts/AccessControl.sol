// SPDX-License-Identifier: MIT
pragma solidity ^0.8.0;

/**
 * @title AccessControl - Access Control Vulnerabilities
 * @dev This contract demonstrates various access control vulnerabilities
 * including missing modifiers, weak authentication, and privilege escalation
 * 
 * VULNERABILITY: Access Control
 * SEVERITY: Critical
 * CWE: CWE-284 (Improper Access Control)
 * 
 * Expected Findings:
 * - Missing access control on critical functions
 * - Weak owner authentication
 * - Default visibility issues
 * - Privilege escalation vulnerability
 */
contract AccessControl {
    address public owner;
    mapping(address => bool) public admins;
    mapping(address => uint256) public balances;
    
    constructor() {
        owner = msg.sender;
    }
    
    // VULNERABLE: Missing access control modifier
    function changeOwner(address newOwner) external {
        // VULNERABILITY: Anyone can change the owner
        owner = newOwner;
    }
    
    // VULNERABLE: Weak access control
    function addAdmin(address admin) external {
        // VULNERABILITY: Only checks if caller is owner, but owner can be changed by anyone
        require(msg.sender == owner, "Only owner can add admins");
        admins[admin] = true;
    }
    
    // VULNERABLE: Missing access control
    function mintTokens(address to, uint256 amount) external {
        // VULNERABILITY: Anyone can mint tokens
        balances[to] += amount;
    }
    
    // VULNERABLE: Default visibility (internal in older versions)
    function emergencyWithdraw() public {
        // VULNERABILITY: Should be restricted to owner/admin only
        payable(msg.sender).transfer(address(this).balance);
    }
    
    // VULNERABLE: Inconsistent access control
    function burnTokens(address from, uint256 amount) external {
        require(admins[msg.sender] || msg.sender == owner, "Access denied");
        // VULNERABILITY: But owner can be changed by anyone, making this check meaningless
        balances[from] -= amount;
    }
    
    // VULNERABLE: tx.origin authentication
    function sensitiveOperation() external {
        // VULNERABILITY: Using tx.origin instead of msg.sender
        require(tx.origin == owner, "Only owner allowed");
        // Critical operation here
    }
    
    // VULNERABLE: Missing modifier, only comment-based restriction
    function criticalFunction() external {
        // TODO: Add onlyOwner modifier
        // VULNERABILITY: Critical function without actual access control
        selfdestruct(payable(msg.sender));
    }
}