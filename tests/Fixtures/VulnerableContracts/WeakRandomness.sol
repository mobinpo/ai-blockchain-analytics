// SPDX-License-Identifier: MIT
pragma solidity ^0.8.0;

/**
 * @title WeakRandomness - Weak Randomness Vulnerability
 * @dev This contract demonstrates various weak randomness vulnerabilities
 * that can be exploited to predict outcomes in gambling/lottery contracts
 * 
 * VULNERABILITY: Weak Randomness
 * SEVERITY: High
 * CWE: CWE-338 (Use of Cryptographically Weak Pseudo-Random Number Generator)
 * 
 * Expected Findings:
 * - Use of block.timestamp for randomness
 * - Use of block.difficulty/prevrandao for randomness
 * - Use of block.number for randomness
 * - Predictable random number generation
 */
contract WeakRandomness {
    address public owner;
    mapping(address => uint256) public balances;
    uint256 public jackpot;
    
    constructor() {
        owner = msg.sender;
    }
    
    receive() external payable {
        jackpot += msg.value;
    }
    
    // VULNERABLE: Using block.timestamp for randomness
    function lottery1() external payable {
        require(msg.value >= 0.1 ether, "Minimum bet is 0.1 ETH");
        
        // VULNERABILITY: block.timestamp is predictable
        uint256 random = uint256(keccak256(abi.encodePacked(block.timestamp))) % 100;
        
        if (random < 10) { // 10% chance to win
            payable(msg.sender).transfer(msg.value * 5);
        }
    }
    
    // VULNERABLE: Using block.difficulty (or prevrandao in post-merge)
    function lottery2() external payable {
        require(msg.value >= 0.1 ether, "Minimum bet is 0.1 ETH");
        
        // VULNERABILITY: block.difficulty is manipulatable by miners
        uint256 random = uint256(keccak256(abi.encodePacked(block.difficulty))) % 100;
        
        if (random < 15) { // 15% chance to win
            payable(msg.sender).transfer(msg.value * 3);
        }
    }
    
    // VULNERABLE: Using block.number for randomness
    function lottery3() external payable {
        require(msg.value >= 0.1 ether, "Minimum bet is 0.1 ETH");
        
        // VULNERABILITY: block.number is predictable
        uint256 random = uint256(keccak256(abi.encodePacked(block.number))) % 100;
        
        if (random < 20) { // 20% chance to win
            payable(msg.sender).transfer(msg.value * 2);
        }
    }
    
    // VULNERABLE: Combining multiple weak sources
    function lottery4() external payable {
        require(msg.value >= 0.1 ether, "Minimum bet is 0.1 ETH");
        
        // VULNERABILITY: Even combining weak sources doesn't make them strong
        uint256 random = uint256(keccak256(abi.encodePacked(
            block.timestamp,
            block.difficulty,
            block.number,
            msg.sender
        ))) % 100;
        
        if (random < 25) { // 25% chance to win
            payable(msg.sender).transfer(msg.value * 2);
        }
    }
    
    // VULNERABLE: Using blockhash with current block
    function lottery5() external payable {
        require(msg.value >= 0.1 ether, "Minimum bet is 0.1 ETH");
        
        // VULNERABILITY: blockhash(block.number) always returns 0
        uint256 random = uint256(blockhash(block.number)) % 100;
        
        if (random < 30) { // This will never execute since random is always 0
            payable(msg.sender).transfer(msg.value * 2);
        }
    }
    
    // VULNERABLE: Using msg.sender for randomness component
    function lottery6() external payable {
        require(msg.value >= 0.1 ether, "Minimum bet is 0.1 ETH");
        
        // VULNERABILITY: Attacker can control their address to influence outcome
        uint256 random = uint256(keccak256(abi.encodePacked(
            msg.sender,
            block.timestamp
        ))) % 100;
        
        if (random < 35) {
            payable(msg.sender).transfer(msg.value * 2);
        }
    }
    
    // VULNERABLE: Reusing nonce or state
    uint256 private nonce = 0;
    
    function lottery7() external payable {
        require(msg.value >= 0.1 ether, "Minimum bet is 0.1 ETH");
        
        // VULNERABILITY: Nonce is predictable and can be front-run
        nonce++;
        uint256 random = uint256(keccak256(abi.encodePacked(nonce, msg.sender))) % 100;
        
        if (random < 40) {
            payable(msg.sender).transfer(msg.value * 2);
        }
    }
}