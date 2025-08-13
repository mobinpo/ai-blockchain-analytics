// SPDX-License-Identifier: MIT
pragma solidity ^0.8.0;

/**
 * @title UncheckedExternalCall - Unchecked External Call Vulnerability
 * @dev This contract demonstrates vulnerabilities related to unchecked external calls
 * including silent failures and unexpected behavior
 * 
 * VULNERABILITY: Unchecked External Call
 * SEVERITY: Medium to High
 * CWE: CWE-252 (Unchecked Return Value)
 * 
 * Expected Findings:
 * - Unchecked call() return value
 * - Unchecked send() return value
 * - Unchecked transfer() exceptions not handled
 * - Silent failure in external calls
 */
contract UncheckedExternalCall {
    address public owner;
    mapping(address => uint256) public balances;
    
    constructor() {
        owner = msg.sender;
    }
    
    function deposit() external payable {
        balances[msg.sender] += msg.value;
    }
    
    // VULNERABLE: Unchecked call() return value
    function withdrawUnchecked(uint256 amount) external {
        require(balances[msg.sender] >= amount, "Insufficient balance");
        
        balances[msg.sender] -= amount;
        
        // VULNERABILITY: Return value not checked, call might fail silently
        msg.sender.call{value: amount}("");
    }
    
    // VULNERABLE: Unchecked send() return value
    function withdrawSend(uint256 amount) external {
        require(balances[msg.sender] >= amount, "Insufficient balance");
        
        balances[msg.sender] -= amount;
        
        // VULNERABILITY: send() can fail and return false, but we don't check
        payable(msg.sender).send(amount);
    }
    
    // VULNERABLE: Multiple unchecked external calls
    function batchTransfer(address[] memory recipients, uint256[] memory amounts) external {
        require(recipients.length == amounts.length, "Array length mismatch");
        
        for (uint i = 0; i < recipients.length; i++) {
            // VULNERABILITY: External call success not verified
            payable(recipients[i]).call{value: amounts[i]}("");
        }
    }
    
    // VULNERABLE: Unchecked delegatecall
    function proxyCall(address target, bytes memory data) external {
        require(msg.sender == owner, "Only owner");
        
        // VULNERABILITY: delegatecall return value not checked
        target.delegatecall(data);
    }
    
    // VULNERABLE: Unchecked staticcall
    function readOnlyCall(address target, bytes memory data) external view returns (bytes memory) {
        // VULNERABILITY: staticcall success not verified
        (, bytes memory result) = target.staticcall(data);
        return result;
    }
    
    // VULNERABLE: External call in loop without proper error handling
    function notifyMultiple(address[] memory contracts, bytes memory data) external {
        for (uint i = 0; i < contracts.length; i++) {
            // VULNERABILITY: If one call fails, we don't know which one
            contracts[i].call(data);
        }
    }
    
    // VULNERABLE: Ether transfer without checking recipient capability
    function forceTransfer(address payable recipient, uint256 amount) external {
        require(msg.sender == owner, "Only owner");
        require(address(this).balance >= amount, "Insufficient contract balance");
        
        // VULNERABILITY: Not checking if recipient can receive Ether
        recipient.transfer(amount); // Can throw if recipient rejects
    }
    
    // VULNERABLE: Low-level call without gas limit
    function unlimitedGasCall(address target, bytes memory data) external {
        require(msg.sender == owner, "Only owner");
        
        // VULNERABILITY: No gas limit specified, could consume all gas
        (bool success,) = target.call(data);
        require(success, "Call failed");
    }
    
    // VULNERABLE: Ignoring return data from external contract
    interface IExternalContract {
        function riskyOperation() external returns (bool success, string memory error);
    }
    
    function callExternalContract(address contractAddr) external {
        IExternalContract externalContract = IExternalContract(contractAddr);
        
        // VULNERABILITY: Not checking return values from external contract
        externalContract.riskyOperation(); // Might return important error info
    }
}