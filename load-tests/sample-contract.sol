// SPDX-License-Identifier: MIT
pragma solidity ^0.8.0;

/**
 * @title SampleContract for Load Testing
 * @dev Simple contract for testing file upload and analysis performance
 */
contract SampleContract {
    address public owner;
    mapping(address => uint256) public balances;
    uint256 public totalSupply;
    
    event Transfer(address indexed from, address indexed to, uint256 amount);
    event Mint(address indexed to, uint256 amount);
    
    modifier onlyOwner() {
        require(msg.sender == owner, "Not the owner");
        _;
    }
    
    constructor() {
        owner = msg.sender;
        totalSupply = 1000000 * 10**18;
        balances[owner] = totalSupply;
    }
    
    function transfer(address to, uint256 amount) external returns (bool) {
        require(balances[msg.sender] >= amount, "Insufficient balance");
        require(to != address(0), "Invalid recipient");
        
        balances[msg.sender] -= amount;
        balances[to] += amount;
        
        emit Transfer(msg.sender, to, amount);
        return true;
    }
    
    function mint(address to, uint256 amount) external onlyOwner {
        require(to != address(0), "Invalid recipient");
        
        balances[to] += amount;
        totalSupply += amount;
        
        emit Mint(to, amount);
    }
    
    function burn(uint256 amount) external {
        require(balances[msg.sender] >= amount, "Insufficient balance");
        
        balances[msg.sender] -= amount;
        totalSupply -= amount;
    }
    
    function getBalance(address account) external view returns (uint256) {
        return balances[account];
    }
    
    // Vulnerable function for testing (intentional)
    function withdraw() external {
        uint256 balance = balances[msg.sender];
        require(balance > 0, "No balance to withdraw");
        
        // Reentrancy vulnerability for testing
        (bool success, ) = payable(msg.sender).call{value: balance}("");
        require(success, "Transfer failed");
        
        balances[msg.sender] = 0;
    }
}