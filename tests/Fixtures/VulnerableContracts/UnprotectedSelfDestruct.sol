// SPDX-License-Identifier: MIT
pragma solidity ^0.8.0;

/**
 * @title UnprotectedSelfDestruct - Self-Destruct Vulnerability
 * @dev This contract demonstrates unprotected self-destruct vulnerabilities
 * where anyone can destroy the contract and steal funds
 * 
 * VULNERABILITY: Unprotected Self-Destruct
 * SEVERITY: Critical
 * CWE: CWE-284 (Improper Access Control)
 * 
 * Expected Findings:
 * - Unprotected selfdestruct function
 * - Missing access control on destructive operations
 * - Potential fund theft via selfdestruct
 * - Contract can be permanently disabled by attackers
 */
contract UnprotectedSelfDestruct {
    address public owner;
    mapping(address => uint256) public balances;
    uint256 public totalFunds;
    
    constructor() {
        owner = msg.sender;
    }
    
    function deposit() external payable {
        balances[msg.sender] += msg.value;
        totalFunds += msg.value;
    }
    
    // VULNERABLE: Unprotected selfdestruct
    function kill() external {
        // VULNERABILITY: Anyone can destroy the contract and steal all funds
        selfdestruct(payable(msg.sender));
    }
    
    // VULNERABLE: Conditional selfdestruct with weak condition
    function emergencyDestruct(string memory password) external {
        // VULNERABILITY: Weak password check, easily bypassed
        require(keccak256(bytes(password)) == keccak256(bytes("admin123")), "Wrong password");
        selfdestruct(payable(msg.sender));
    }
    
    // VULNERABLE: Selfdestruct in fallback function
    fallback() external payable {
        // VULNERABILITY: Contract can be destroyed by sending specific data
        if (msg.data.length == 4 && 
            bytes4(msg.data) == bytes4(keccak256("destroy()"))) {
            selfdestruct(payable(msg.sender));
        }
    }
    
    // VULNERABLE: Logic bomb with selfdestruct
    function processTransaction(uint256 amount) external {
        require(balances[msg.sender] >= amount, "Insufficient balance");
        
        // VULNERABILITY: Hidden selfdestruct condition
        if (amount == 1337) {
            selfdestruct(payable(msg.sender));
        }
        
        balances[msg.sender] -= amount;
    }
    
    // VULNERABLE: Time-based selfdestruct without proper checks
    function timedDestruct() external {
        // VULNERABILITY: Anyone can trigger after arbitrary time
        require(block.timestamp > 1640995200, "Too early"); // Jan 1, 2022
        selfdestruct(payable(msg.sender));
    }
}