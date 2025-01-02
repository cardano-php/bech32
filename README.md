# Bech32 Encoder/Decoder for Cardano

This library provides a pure PHP implementation of a Bech32 Encoder/Decoder tailored for use with the Cardano
blockchain. It allows developers to seamlessly encode and decode Bech32 strings, which are widely used in Cardano
addresses and other blockchain-related data.

## Features

- **Pure PHP Implementation**: No external dependencies, making it lightweight and easy to integrate.
- **Cardano-Specific**: Designed to meet the encoding and decoding requirements of the Cardano blockchain.
- **Simple API**: Intuitive methods for both encoding and decoding.

## Installation

To install the library, use Composer:

```bash
composer require cardano-php/bech32
```

Alternatively, you can download the source code and include it manually in your project:

```php
require_once 'path/to/Bech32.php';
```

## Usage

### Encoding

```php
use PhpCardano\Bech32;

$data = Bech32::hexToByteArray('your-data-hex-encoded-here');
$hrp = 'addr'; // Human Readable Part (e.g., "addr" for Cardano addresses)

$encoded = Bech32Cardano::encode($hrp, $data);
echo "Encoded: $encoded";
```

### Decoding

```php
use PhpCardano\Bech32;

$bech32 = 'addr1...'; // Your Bech32 string

list($hrp, $data) = Bech32::decode($bech32);
echo "HRP: $hrp\n";
echo "Data: $data\n";
```

### Decoding a Cardano Address

Example PHP Code:

```php 
/**
* JPG v2 Staked Public Contract
**/

$bech32String = 'addr1qxegfu8m62peqmyamrdwmwqm00zjcak3u25xnanfdct4p9pf488uagw68fv50kjxv3wrx38829tay6zszthnccsradgqwt4upy';
$result = Bech32::decodeCardanoAddress($bech32String);
echo "Result:\n".json_encode($result, JSON_PRETTY_PRINT)."\n";
```

Output:

```
Result:
{
    "address": "addr1xxgx3far7qygq0k6epa0zcvcvrevmn0ypsnfsue94nsn3tfvjel5h55fgjcxgchp830r7h2l5msrlpt8262r3nvr8eks2utwdd",
    "addressType": 3,
    "networkId": 1,
    "paymentHash": "9068a7a3f008803edac87af1619860f2cdcde40c26987325ace138ad",
    "stakingHash": "2c967f4bd28944b06462e13c5e3f5d5fa6e03f8567569438cd833e6d",
    "stakeAddress": "stake17ykfvl6t62y5fvryvtsnch3lt406dcpls4n4d9pcekpnumg6v83tq"
}
```

### Encoding  Cardano Address

Example PHP Code:

```php 
/**
* JPG v2 Staked Public Contract
**/

$addressType = 3;                                                          // Script Hash + Script Hash
$networkId = 1;                                                            // Cardano Mainnet
$paymentHash = '9068a7a3f008803edac87af1619860f2cdcde40c26987325ace138ad'; // Payment Validator
$stakeHash = '2c967f4bd28944b06462e13c5e3f5d5fa6e03f8567569438cd833e6d';   // Stake Validator

$address = Bech32::encodeCardanoAddress($addressType, $networkId, $paymentHash, $stakeHash);
echo "Result:\n" . json_encode($address, JSON_PRETTY_PRINT) . "\n";
```

``` 
Result:
{
    "address": "addr1xxgx3far7qygq0k6epa0zcvcvrevmn0ypsnfsue94nsn3tfvjel5h55fgjcxgchp830r7h2l5msrlpt8262r3nvr8eks2utwdd",
    "addressType": 3,
    "networkId": 1,
    "paymentHash": "9068a7a3f008803edac87af1619860f2cdcde40c26987325ace138ad",
    "stakeHash": "2c967f4bd28944b06462e13c5e3f5d5fa6e03f8567569438cd833e6d",
    "stakeAddress": "stake17ykfvl6t62y5fvryvtsnch3lt406dcpls4n4d9pcekpnumg6v83tq"
}
```

### Error Handling

Ensure to handle potential exceptions when decoding invalid Bech32 strings:

```php
try {
    [$hrp, $data] = Bech32::decode($bech32);
} catch (Exception $e) {
    echo "Decoding failed: " . $e->getMessage();
}
```

## Requirements

- PHP 8.2 or later

## Tests

Run the tests to ensure the library works as expected:

```bash
composer test
```

> Note: This will also generate code coverage reports that can be viewed in the `./coverage` folder.

## Contributing

Contributions are welcome! Feel free to fork the repository and submit a pull request. Ensure to include tests for any
new features or bug fixes.

## License

This project is licensed under the Apache 2.0 License. See the [LICENSE](LICENSE) file for details.

## Acknowledgments

This library was inspired by the Bech32 implementation originally
by [Bit-Wasp Bech32 Implementation](https://github.com/Bit-Wasp/bech32) and
Cardano [CIP-0019](https://github.com/cardano-foundation/CIPs/blob/master/CIP-0019/README.md).
