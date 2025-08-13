<?php

declare(strict_types=1);

namespace Tests\Fixtures;

/**
 * Real-World Vulnerable Smart Contracts for Regression Testing
 * 
 * This collection includes 10 well-documented vulnerable contracts from actual incidents
 * and common vulnerability patterns found in production smart contracts.
 */
class RealWorldVulnerableContracts
{
    /**
     * Get all real-world vulnerable contract test cases
     */
    public static function getAllTestCases(): array
    {
        return [
            self::getTheDAOContract(),
            self::getParity1Contract(),
            self::getParity2Contract(),
            self::getBatchOverflowContract(),
            self::getKingOfTheEtherContract(),
            self::getFoMo3DContract(),
            self::getBancorContract(),
            self::getSpankChainContract(),
            self::getPolyMathContract(),
            self::getRug5gDigitalContract(),
        ];
    }

    /**
     * 1. The DAO - Classic Reentrancy (2016)
     * The most famous smart contract vulnerability in Ethereum history
     */
    public static function getTheDAOContract(): array
    {
        return [
            'id' => 'dao_reentrancy_2016',
            'name' => 'The DAO Reentrancy Vulnerability',
            'network' => 'ethereum',
            'address' => '0xbb9bc244d798123fde783fcc1c72d3bb8c189413',
            'category' => 'REENTRANCY',
            'severity' => 'CRITICAL',
            'cwe' => 'CWE-841',
            'swc' => 'SWC-107',
            'incident_date' => '2016-06-17',
            'funds_lost' => '3600000 ETH (~$60M USD at time)',
            'description' => 'The DAO hack exploited a reentrancy vulnerability in the withdrawal function, allowing attackers to recursively call the withdraw function before the balance was updated.',
            'vulnerability_summary' => 'External call before state change in withdrawal function',
            'expected_findings' => [
                'Reentrancy vulnerability in withdraw function',
                'External call before balance update',
                'Missing checks-effects-interactions pattern',
                'Potential for recursive calls',
                'State change after external call'
            ],
            'relevant_code' => '
function withdrawRewardFor(address _account) noEther internal returns (bool _success) {
    if ((balanceOf(_account) * rewardAccount.accumulatedInput()) / totalSupply < paidOut[_account])
        throw;

    uint reward = 
        (balanceOf(_account) * rewardAccount.accumulatedInput()) / totalSupply - paidOut[_account];
    if (!rewardAccount.payOut(_account, reward))  // VULNERABLE: External call
        throw;
    paidOut[_account] += reward;  // State change after external call
    return true;
}',
            'attack_vector' => 'Recursive withdrawal calls before balance update',
            'remediation' => [
                'Use checks-effects-interactions pattern',
                'Update state before external calls',
                'Implement reentrancy guards',
                'Use pull payment pattern'
            ]
        ];
    }

    /**
     * 2. Parity Wallet #1 - Delegatecall Vulnerability (2017)
     */
    public static function getParity1Contract(): array
    {
        return [
            'id' => 'parity_delegatecall_2017',
            'name' => 'Parity Multi-Sig Wallet #1',
            'network' => 'ethereum',
            'address' => '0x863df6bfa4469f3ead0be8f9f2aae51c91a907b4',
            'category' => 'DELEGATECALL',
            'severity' => 'CRITICAL',
            'cwe' => 'CWE-20',
            'swc' => 'SWC-112',
            'incident_date' => '2017-07-19',
            'funds_lost' => '150000 ETH (~$30M USD)',
            'description' => 'Parity wallet vulnerability allowing attackers to become owners of any wallet by exploiting delegatecall to an unprotected initialization function.',
            'vulnerability_summary' => 'Unprotected delegatecall allowing arbitrary code execution',
            'expected_findings' => [
                'Dangerous delegatecall usage',
                'Unprotected initialization function',
                'Missing access control on critical functions',
                'Proxy pattern implementation flaws',
                'State manipulation via delegatecall'
            ],
            'relevant_code' => '
// Library contract with unprotected initialization
function initWallet(address[] _owners, uint _required, uint _daylimit) {
    initDaylimit(_daylimit);
    initMultiowned(_owners, _required);
}

// Wallet contract with vulnerable delegatecall
function() payable {
    if (msg.value > 0)
        Deposit(msg.sender, msg.value);
    else if (msg.data.length > 0)
        _walletLibrary.delegatecall(msg.data);  // VULNERABLE: No access control
}',
            'attack_vector' => 'Call initWallet through delegatecall to become owner',
            'remediation' => [
                'Restrict delegatecall usage',
                'Implement proper access controls',
                'Use safer proxy patterns',
                'Add initialization protection'
            ]
        ];
    }

    /**
     * 3. Parity Wallet #2 - Accidental Suicide (2017)
     */
    public static function getParity2Contract(): array
    {
        return [
            'id' => 'parity_suicide_2017',
            'name' => 'Parity Multi-Sig Wallet #2 (Library Suicide)',
            'network' => 'ethereum',
            'address' => '0x863df6bfa4469f3ead0be8f9f2aae51c91a907b4',
            'category' => 'SELFDESTRUCT',
            'severity' => 'CRITICAL',
            'cwe' => 'CWE-284',
            'swc' => 'SWC-106',
            'incident_date' => '2017-11-06',
            'funds_lost' => '513774.16 ETH (~$280M USD)',
            'description' => 'A user accidentally killed the Parity wallet library contract, freezing funds in all dependent wallets.',
            'vulnerability_summary' => 'Unprotected selfdestruct in library contract',
            'expected_findings' => [
                'Unprotected selfdestruct function',
                'Missing access control on destructive operations',
                'Library contract can be destroyed',
                'Dependent contracts become unusable',
                'Funds permanently locked'
            ],
            'relevant_code' => '
contract WalletLibrary is WalletEvents {
    function initWallet(address[] _owners, uint _required, uint _daylimit) {
        initDaylimit(_daylimit);
        initMultiowned(_owners, _required);
    }
    
    function kill(address _to) onlymanyowners(sha3(msg.data)) external {
        suicide(_to);  // VULNERABLE: Can destroy library contract
    }
}',
            'attack_vector' => 'Accidental initialization and destruction of library contract',
            'remediation' => [
                'Separate library and wallet logic',
                'Protect critical functions with proper access control',
                'Avoid selfdestruct in library contracts',
                'Implement upgrade patterns safely'
            ]
        ];
    }

    /**
     * 4. BatchOverflow (BEC Token) - Integer Overflow (2018)
     */
    public static function getBatchOverflowContract(): array
    {
        return [
            'id' => 'batchoverflow_bec_2018',
            'name' => 'BEC Token BatchOverflow',
            'network' => 'ethereum',
            'address' => '0xc5d105e63711398af9bbff092d4b6769c82f793d',
            'category' => 'INTEGER_OVERFLOW',
            'severity' => 'CRITICAL',
            'cwe' => 'CWE-190',
            'swc' => 'SWC-101',
            'incident_date' => '2018-04-22',
            'funds_lost' => 'Token value crashed to near zero',
            'description' => 'Integer overflow in batchTransfer function allowed creation of astronomical token amounts, crashing the token value.',
            'vulnerability_summary' => 'Integer overflow in multiplication without SafeMath',
            'expected_findings' => [
                'Integer overflow in multiplication',
                'Missing SafeMath usage',
                'Unchecked arithmetic operations',
                'Potential for massive token creation',
                'Balance manipulation vulnerability'
            ],
            'relevant_code' => '
function batchTransfer(address[] _receivers, uint256 _value) public whenNotPaused returns (bool) {
    uint cnt = _receivers.length;
    uint256 amount = uint256(cnt) * _value;  // VULNERABLE: Overflow possible
    require(cnt > 0 && cnt <= 20);
    require(_value > 0 && balances[msg.sender] >= amount);
    
    balances[msg.sender] = balances[msg.sender].sub(amount);
    for (uint i = 0; i < cnt; i++) {
        balances[_receivers[i]] = balances[_receivers[i]].add(_value);
        Transfer(msg.sender, _receivers[i], _value);
    }
    return true;
}',
            'attack_vector' => 'Overflow cnt * _value to bypass balance check',
            'remediation' => [
                'Use SafeMath for all arithmetic operations',
                'Check for overflows before operations',
                'Validate input parameters thoroughly',
                'Use Solidity 0.8.0+ built-in overflow protection'
            ]
        ];
    }

    /**
     * 5. King of the Ether - DoS via Revert (2016)
     */
    public static function getKingOfTheEtherContract(): array
    {
        return [
            'id' => 'king_of_ether_dos_2016',
            'name' => 'King of the Ether DoS',
            'network' => 'ethereum',
            'address' => '0x3c9aacb1c3a8fc397a1b0871c7dddd1c24e9dfe6',
            'category' => 'DENIAL_OF_SERVICE',
            'severity' => 'HIGH',
            'cwe' => 'CWE-400',
            'swc' => 'SWC-113',
            'incident_date' => '2016-02-05',
            'funds_lost' => 'Game permanently broken',
            'description' => 'A player created a contract that always reverts, breaking the game permanently when they became king.',
            'vulnerability_summary' => 'External call failure can break contract functionality',
            'expected_findings' => [
                'Unchecked external call return value',
                'Single point of failure in payment logic',
                'DoS via failed external call',
                'Missing fallback handling',
                'Game logic depends on external success'
            ],
            'relevant_code' => '
function() public payable {
    require(msg.value >= price);
    
    // Refund the previous king
    if (king != 0) {
        king.transfer(balance);  // VULNERABLE: Can fail and break game
    }
    
    king = msg.sender;
    price = msg.value;
    balance = msg.value;
}',
            'attack_vector' => 'Deploy contract that reverts on receive, become king',
            'remediation' => [
                'Use pull payment pattern',
                'Handle external call failures gracefully',
                'Implement withdrawal pattern',
                'Never let external calls break core functionality'
            ]
        ];
    }

    /**
     * 6. FoMo3D - Weak Randomness (2018)
     */
    public static function getFoMo3DContract(): array
    {
        return [
            'id' => 'fomo3d_randomness_2018',
            'name' => 'FoMo3D Weak Randomness',
            'network' => 'ethereum',
            'address' => '0xa62142888aba8370742be823c1782d17a0389da1',
            'category' => 'WEAK_RANDOMNESS',
            'severity' => 'HIGH',
            'cwe' => 'CWE-330',
            'swc' => 'SWC-120',
            'incident_date' => '2018-08-22',
            'funds_lost' => '10469 ETH manipulated winnings',
            'description' => 'Predictable randomness based on block variables allowed attackers to manipulate game outcomes.',
            'vulnerability_summary' => 'Randomness based on predictable block variables',
            'expected_findings' => [
                'Weak randomness using block variables',
                'Predictable pseudo-random number generation',
                'Block timestamp manipulation vulnerability',
                'Deterministic outcomes in gambling logic',
                'Miner manipulation possible'
            ],
            'relevant_code' => '
function getRandomNumber() private view returns(uint256) {
    // VULNERABLE: All these values are predictable/manipulable
    return uint256(keccak256(
        block.timestamp,
        block.difficulty,
        block.number,
        blockhash(block.number - 1),
        msg.sender
    ));
}

function airdrop() private {
    uint256 seed = getRandomNumber();
    if ((seed % 1000) == 0) {  // 0.1% chance
        // Award airdrop - but chance is manipulable
    }
}',
            'attack_vector' => 'Predict/manipulate block variables to control randomness',
            'remediation' => [
                'Use commit-reveal schemes',
                'Implement Chainlink VRF or similar oracle',
                'Use multiple entropy sources',
                'Avoid block variables for randomness'
            ]
        ];
    }

    /**
     * 7. Bancor - Front-running Vulnerability (2018)
     */
    public static function getBancorContract(): array
    {
        return [
            'id' => 'bancor_frontrunning_2018',
            'name' => 'Bancor Front-running',
            'network' => 'ethereum',
            'address' => '0x1f573d6fb3f13d689ff844b4ce37794d79a7ff1c',
            'category' => 'FRONT_RUNNING',
            'severity' => 'MEDIUM',
            'cwe' => 'CWE-362',
            'swc' => 'SWC-114',
            'incident_date' => '2018-07-09',
            'funds_lost' => '25000 ETH (~$12.5M USD)',
            'description' => 'Predictable transaction ordering allowed front-running attacks on token conversion rates.',
            'vulnerability_summary' => 'Lack of slippage protection enables front-running',
            'expected_findings' => [
                'Missing slippage protection',
                'Predictable transaction ordering vulnerability',
                'MEV (Maximal Extractable Value) exposure',
                'Price manipulation via front-running',
                'Lack of minimum return parameters'
            ],
            'relevant_code' => '
function convert(
    IERC20Token[] _path,
    uint256 _amount,
    uint256 _minReturn  // Should protect against slippage
) public payable returns (uint256) {
    // VULNERABLE: No protection against front-running
    // Attackers can see this transaction and front-run it
    
    uint256 amount = _amount;
    for (uint256 i = 1; i < _path.length; i++) {
        amount = convertByPath(_path, amount, i);
    }
    
    require(amount >= _minReturn, "Insufficient return");  // Often set too low
    return amount;
}',
            'attack_vector' => 'Monitor mempool, front-run large trades to manipulate price',
            'remediation' => [
                'Implement commit-reveal for sensitive operations',
                'Add time delays for large transactions',
                'Use better slippage protection',
                'Implement MEV protection mechanisms'
            ]
        ];
    }

    /**
     * 8. SpankChain - Reentrancy in Payment Channel (2018)
     */
    public static function getSpankChainContract(): array
    {
        return [
            'id' => 'spankchain_reentrancy_2018',
            'name' => 'SpankChain Payment Channel Reentrancy',
            'network' => 'ethereum',
            'address' => '0xf91546835f756da0c10cfa0cda95b15577b84aa7b',
            'category' => 'REENTRANCY',
            'severity' => 'HIGH',
            'cwe' => 'CWE-841',
            'swc' => 'SWC-107',
            'incident_date' => '2018-10-09',
            'funds_lost' => '165.38 ETH (~$40k USD)',
            'description' => 'Reentrancy vulnerability in payment channel withdrawal function.',
            'vulnerability_summary' => 'State channel reentrancy allowing double withdrawals',
            'expected_findings' => [
                'Reentrancy in withdrawal function',
                'State channel security flaw',
                'External call before state update',
                'Missing reentrancy protection',
                'Double spending vulnerability'
            ],
            'relevant_code' => '
function withdraw(uint256 _amount) public {
    require(balances[msg.sender] >= _amount, "Insufficient balance");
    
    // VULNERABLE: External call before state change
    (bool success, ) = msg.sender.call{value: _amount}("");
    require(success, "Transfer failed");
    
    balances[msg.sender] -= _amount;  // State change after external call
    totalSupply -= _amount;
}',
            'attack_vector' => 'Recursive calls to withdraw before balance update',
            'remediation' => [
                'Use checks-effects-interactions pattern',
                'Implement reentrancy guards',
                'Update state before external calls',
                'Use withdrawal pattern'
            ]
        ];
    }

    /**
     * 9. PolyMath - Integer Overflow (2018)
     */
    public static function getPolyMathContract(): array
    {
        return [
            'id' => 'polymath_overflow_2018',
            'name' => 'PolyMath Integer Overflow',
            'network' => 'ethereum',
            'address' => '0x9992ec3cf6a55b00978cddf2b27bc6882d88d1ec',
            'category' => 'INTEGER_OVERFLOW',
            'severity' => 'HIGH',
            'cwe' => 'CWE-190',
            'swc' => 'SWC-101',
            'incident_date' => '2018-05-01',
            'funds_lost' => 'Market manipulation potential',
            'description' => 'Integer overflow in token allocation function allowing unlimited token creation.',
            'vulnerability_summary' => 'Multiplication overflow in token allocation',
            'expected_findings' => [
                'Integer overflow in multiplication',
                'Missing SafeMath library usage',
                'Unchecked arithmetic operations',
                'Token supply manipulation',
                'Balance overflow possibility'
            ],
            'relevant_code' => '
function allocateTokens(address _investor, uint256 _tokens) public onlyOwner {
    uint256 granularity = 10**18;
    uint256 allocatedTokens = _tokens * granularity;  // VULNERABLE: Overflow
    
    require(totalSupply + allocatedTokens <= maxSupply, "Exceeds max supply");
    
    balances[_investor] += allocatedTokens;
    totalSupply += allocatedTokens;
    
    emit Transfer(address(0), _investor, allocatedTokens);
}',
            'attack_vector' => 'Overflow _tokens * granularity to bypass max supply check',
            'remediation' => [
                'Use SafeMath for all arithmetic',
                'Check for overflows before operations',
                'Validate input parameters',
                'Use Solidity 0.8.0+ automatic checks'
            ]
        ];
    }

    /**
     * 10. Rug Pull Contract - Hidden Backdoor (2021)
     */
    public static function getRug5gDigitalContract(): array
    {
        return [
            'id' => 'rug_5g_digital_2021',
            'name' => '5G Digital Token Rug Pull',
            'network' => 'bsc',
            'address' => '0x9A946c3Cb16c08334b69aE249690C236Ebd5583E',
            'category' => 'RUG_PULL',
            'severity' => 'CRITICAL',
            'cwe' => 'CWE-284',
            'swc' => 'SWC-106',
            'incident_date' => '2021-03-13',
            'funds_lost' => '$2.5M USD equivalent',
            'description' => 'Hidden backdoor functions allowed developers to drain liquidity and manipulate token supply.',
            'vulnerability_summary' => 'Hidden administrative functions for fund extraction',
            'expected_findings' => [
                'Hidden owner-only functions',
                'Unlimited minting capability',
                'Liquidity extraction backdoor',
                'Transfer tax manipulation',
                'Honeypot characteristics'
            ],
            'relevant_code' => '
// Hidden in obfuscated code
mapping(address => bool) private _isExcludedFromFee;
address private _developmentWallet;

function setDevelopmentWallet(address newWallet) external onlyOwner {
    _developmentWallet = newWallet;
}

// VULNERABLE: Hidden function to extract funds
function emergencyWithdraw() external {
    require(msg.sender == _developmentWallet, "Unauthorized");
    payable(_developmentWallet).transfer(address(this).balance);
}

// VULNERABLE: Can manipulate fees to create honeypot
function setTaxFeePercent(uint256 taxFee) external onlyOwner {
    _taxFee = taxFee;  // Can be set to 100% to prevent selling
}',
            'attack_vector' => 'Use hidden administrative functions to drain funds',
            'remediation' => [
                'Transparent contract code',
                'Time-locked administrative functions',
                'Community governance for changes',
                'Immutable core functions',
                'Third-party audits'
            ]
        ];
    }

    /**
     * Get test cases by category
     */
    public static function getTestCasesByCategory(string $category): array
    {
        return array_filter(self::getAllTestCases(), function($testCase) use ($category) {
            return $testCase['category'] === $category;
        });
    }

    /**
     * Get test cases by severity
     */
    public static function getTestCasesBySeverity(string $severity): array
    {
        return array_filter(self::getAllTestCases(), function($testCase) use ($severity) {
            return $testCase['severity'] === $severity;
        });
    }

    /**
     * Get test cases by network
     */
    public static function getTestCasesByNetwork(string $network): array
    {
        return array_filter(self::getAllTestCases(), function($testCase) use ($network) {
            return $testCase['network'] === $network;
        });
    }
}