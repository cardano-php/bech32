<?php

namespace CardanoPhp\Bech32;

use Exception;
use SodiumException;
use function array_merge;
use function array_slice;
use function count;
use function implode;
use function ord;
use function pack;
use function strlen;

/**
 * Original inspiration for the encoding/decoding portion of this library came
 * from https://github.com/Bit-Wasp/bech32. However, there was extra and
 * unnecessary bloat there due to being used in other implementations e.g.
 * Bitcoin so I have extracted only the necessary pieces here.
 */
class Bech32
{
    const GENERATOR = [
        0x3b6a57b2, 0x26508e6d, 0x1ea119fa, 0x3d4233dd, 0x2a1462b3,
    ];

    // Valid characters in a Bech32-encoded data portion
    const CHARSET     = 'qpzry9x8gf2tvdw0s3jn54khce6mua7l';

    const CHARKEY_KEY = [
        -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1,
        -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1,
        -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, 15, -1, 10, 17, 21, 20,
        26, 30, 7, 5, -1, -1, -1, -1, -1, -1, -1, 29, -1, 24, 13, 25, 9, 8, 23,
        -1, 18, 22, 31, 27, 19, -1, 1, 0, 3, 16, 11, 28, 12, 14, 6, 4, 2, -1,
        -1, -1, -1, -1, -1, 29, -1, 24, 13, 25, 9, 8, 23, -1, 18, 22, 31, 27,
        19, -1, 1, 0, 3, 16, 11, 28, 12, 14, 6, 4, 2, -1, -1, -1, -1, -1,
    ];

    // HRM must be at least 1 character in length
    const MIN_HRP_LENGTH = 1;

    // HRP must be at most 83 characters in length
    const MAX_HRP_LENGTH = 83;

    // Printable ASCII Characters from ! (33) through ~ (126)
    const HRP_REGEX = "/^[\x21-\x7e]{1,83}$/";

    // For quick and easy checking that a hex-string has been provided
    const HEX_REGEX = '/^[0-9A-F]*$/i';

    // There must be at least 6 characters in the data portion of a valid Bech32 encoding
    const MIN_DATA_LENGTH = 6;

    // This character is reserved as the separator between the human readable
    // part (HRP) and the data. The HRP may contain te separator character so
    // the final occurrence of the separator character in the Bech32-encoded
    // string is considered to be the separator demarcation between HRP and data
    const SEPARATOR_CHARACTER = '1';

    // The minimum length of a valid bech32 string is the minimum length of the
    // HRP, the character length of the separator character, and the minimum
    // length of the data. i.e. 1 + 1 + 6 = 8
    const BECH32_MIN_LENGTH = 8;

    /**
     * @param string $hrp
     * @param array  $combinedDataChars
     *
     * @return string
     * @throws Exception
     */
    public static function encode(string $hrp, array $combinedDataChars): string
    {
        if (strlen($hrp) < self::MIN_HRP_LENGTH) {
            throw new Exception('HRP too short');
        }

        if (strlen($hrp) > self::MAX_HRP_LENGTH) {
            throw new Exception('HRP too long');
        }

        if (!preg_match(self::HRP_REGEX, $hrp)) {
            throw new Exception('Invalid characters in HRP');
        }

        $checksum = self::createChecksum($hrp, $combinedDataChars);
        $characters = array_merge($combinedDataChars, $checksum);

        $encoded = [];
        for ($i = 0, $n = count($characters); $i < $n; $i++) {
            $encoded[$i] = self::CHARSET[$characters[$i]];
        }

        return $hrp . self::SEPARATOR_CHARACTER . implode('', $encoded);
    }

    /**
     * Validates a Bech32 string and returns [$hrp, $dataChars] if
     * the conversion was successful. An exception is thrown on invalid
     * data.
     *
     * @param string $sBech - the Bech32 encoded string
     *
     * @return array - returns [$hrp, $dataChars]
     * @throws Exception
     */
    public static function decode(string $sBech): array
    {
        $length = strlen($sBech);
        if ($length < self::BECH32_MIN_LENGTH) {
            throw new Exception("Bech32 string is too short");
        }

        $chars = array_values(unpack('C*', $sBech));

        $haveUpper = false;
        $haveLower = false;
        $positionOne = -1;

        for ($i = 0; $i < $length; $i++) {
            $x = $chars[$i];
            if ($x < 33 || $x > 126) {
                throw new Exception('Out of range character in Bech32 string');
            }

            if ($x >= 0x61 && $x <= 0x7a) {
                $haveLower = true;
            }

            if ($x >= 0x41 && $x <= 0x5a) {
                $haveUpper = true;
                $x = $chars[$i] = $x + 0x20;
            }

            // find location of last '1' character
            if ($x === 0x31) {
                $positionOne = $i;
            }
        }

        if ($haveUpper && $haveLower) {
            throw new Exception('Data contains mixed case characters');
        }

        if ($positionOne === -1) {
            throw new Exception("Missing separator character");
        }

        if ($positionOne < self::MIN_HRP_LENGTH) {
            throw new Exception("HRP too short");
        }

        if ($positionOne > self::MAX_HRP_LENGTH) {
            throw new Exception("HRP too long");
        }

        if (($positionOne + 7) > $length) {
            throw new Exception('Too short checksum');
        }

        $hrp = pack("C*", ...array_slice($chars, 0, $positionOne));
        $dataS = pack("C*", ...array_slice($chars, $positionOne + 1));
        $validDataRegex = "/^[" . self::CHARSET . "]+$/";

        if (!preg_match($validDataRegex, $dataS)) {
            throw new Exception("Invalid characters in Bech32 data");
        }

        $data = [];
        for ($i = $positionOne + 1; $i < $length; $i++) {
            $data[] = ($chars[$i] & 0x80) ? -1 : self::CHARKEY_KEY[$chars[$i]];
        }

        if (!self::verifyChecksum($hrp, $data)) {
            throw new Exception('Invalid Bech32 checksum');
        }

        return [
            $hrp, array_slice($data, 0, -6),
        ];
    }

    /**
     * Verifies the checksum given $hrp and $convertedDataChars.
     *
     * @param string $hrp
     * @param int[]  $convertedDataChars
     *
     * @return bool
     */
    private static function verifyChecksum(string $hrp, array $convertedDataChars): bool
    {
        $expandHrp = self::hrpExpand($hrp, strlen($hrp));
        $r = array_merge($expandHrp, $convertedDataChars);
        $poly = self::polyMod($r, count($r));

        return $poly === 1;
    }

    /**
     * @param string $hrp
     * @param int[]  $convertedDataChars
     *
     * @return int[]
     */
    private static function createChecksum(string $hrp, array $convertedDataChars): array
    {
        $values = array_merge(self::hrpExpand($hrp, strlen($hrp)), $convertedDataChars);
        $polyMod = self::polyMod(array_merge($values, [
                0, 0, 0, 0, 0, 0,
            ]), count($values) + 6) ^ 1;
        $results = [];
        for ($i = 0; $i < 6; $i++) {
            $results[$i] = ($polyMod >> 5 * (5 - $i)) & 31;
        }

        return $results;
    }

    /**
     * Expands the human readable part into a character array for checksumming.
     *
     * @param string $hrp
     * @param int    $hrpLen
     *
     * @return int[]
     */
    private static function hrpExpand(string $hrp, int $hrpLen): array
    {
        $expand1 = [];
        $expand2 = [];
        for ($i = 0; $i < $hrpLen; $i++) {
            $o = ord($hrp[$i]);
            $expand1[] = $o >> 5;
            $expand2[] = $o & 31;
        }

        return array_merge($expand1, [0], $expand2);
    }

    /**
     * @param int[] $values
     * @param int   $numValues
     *
     * @return int
     */
    private static function polyMod(array $values, int $numValues): int
    {
        $chk = 1;
        for ($i = 0; $i < $numValues; $i++) {
            $top = $chk >> 25;
            $chk = ($chk & 0x1ffffff) << 5 ^ $values[$i];

            for ($j = 0; $j < 5; $j++) {
                $value = (($top >> $j) & 1) ? self::GENERATOR[$j] : 0;
                $chk ^= $value;
            }
        }

        return $chk;
    }

    /**
     * Converts words of $fromBits bits to $toBits bits in size.
     *
     * @param int[] $data     - character array of data to convert
     * @param int   $fromBits - word (bit count) size of provided data
     * @param int   $toBits   - requested word size (bit count)
     * @param bool  $pad      - whether to pad (only when encoding)
     *
     * @return int[]
     * @throws Exception
     */
    private static function convertBits(array $data, int $fromBits, int $toBits, bool $pad = true): array
    {
        $acc = 0;
        $bits = 0;
        $ret = [];
        $maxv = (1 << $toBits) - 1;
        $maxacc = (1 << ($fromBits + $toBits - 1)) - 1;
        $inLen = count($data);

        for ($i = 0; $i < $inLen; $i++) {
            $value = $data[$i];
            if ($value < 0 || $value >> $fromBits) {
                throw new Exception('Invalid value for convert bits');
            }

            $acc = (($acc << $fromBits) | $value) & $maxacc;
            $bits += $fromBits;

            while ($bits >= $toBits) {
                $bits -= $toBits;
                $ret[] = (($acc >> $bits) & $maxv);
            }
        }

        if ($pad) {
            if ($bits) {
                $ret[] = ($acc << $toBits - $bits) & $maxv;
            }
        } else {
            if ($bits >= $fromBits || ((($acc << ($toBits - $bits))) & $maxv)) {
                throw new Exception('Invalid data');
            }
        }

        return $ret;
    }

    /**
     * @param string $input
     *
     * @return array
     */
    public static function hexToByteArray(string $input): array
    {
        $binary = hex2bin($input);
        $bytes = '';
        foreach (unpack('C*', $binary) as $byte) {
            $bytes .= str_pad(decbin($byte), 8, '0', STR_PAD_LEFT);
        }

        $byteArray = [];
        for ($i = 0; $i < strlen($bytes); $i += 5) {
            $chunk = substr($bytes, $i, 5);
            $byteArray[] = bindec(str_pad($chunk, 5, '0'));
        }

        return $byteArray;
    }

    /**
     * byteArrayToHex
     *
     * Convert from the 5-bit array used for Bech32 encoding into hex
     * representation
     *
     * @param array $data
     * @param bool  $pad
     *
     * @return string
     * @throws Exception
     */
    public static function byteArrayToHex(array $data, bool $pad = false): string
    {
        // Convert 5-bit data to 8-bit data
        $fromBits = 5;
        $toBits = 8;

        $convertedData = self::convertBits($data, $fromBits, $toBits, $pad);

        // Convert each 8-bit value to hexadecimal representation
        $hexArray = array_map(function ($value) {
            return str_pad(dechex($value), 2, '0', STR_PAD_LEFT); // Ensure 2 characters per byte
        }, $convertedData);

        // Join hex values into a string
        return implode('', $hexArray);
    }

    /**
     * @param string $hex
     *
     * @return string
     */
    private static function hexToBinary(string $hex): string
    {
        $inputLen = strlen($hex);
        $padLength = $inputLen * 4;

        return str_pad(decbin(hexdec($hex)), $padLength, '0', STR_PAD_LEFT);
    }

    private static function binaryToHex(string $binary): string
    {
        return dechex(bindec($binary));
    }

    /**
     * @throws Exception
     */
    public static function decodeCardanoAddress($bech32): array
    {
        if (!str_starts_with($bech32, 'addr')) {
            throw new Exception('Not a Cardano Shelley Address');
        }

        [$hrp, $data] = self::decode($bech32);
        $hexPayload = self::byteArrayToHex($data);

        /**
         * Get the first byte which is the header representing the type and network
         * for this address as defined in CIP-0019:
         * https://github.com/cardano-foundation/CIPs/tree/master/CIP-0019#binary-format
         *
         *  1 byte     variable length
         *  <------> <------------------->
         * ┌────────┬─────────────────────┐
         * │ header │        payload      │
         * └────────┴─────────────────────┘
         * 🔎
         * ╎          7 6 5 4 3 2 1 0
         * ╎         ┌─┬─┬─┬─┬─┬─┬─┬─┐
         * ╰╌╌╌╌╌╌╌╌ │t│t│t│t│n│n│n│n│
         *           └─┴─┴─┴─┴─┴─┴─┴─┘
         */
        [
            $addressType, $networkId,
        ] = self::decodeAddressHeader(substr($hexPayload, 0, 2));

        if ($networkId && $hrp !== 'addr') {
            throw new Exception("HRP does not match network ID");
        }

        if (!$networkId && $hrp !== 'addr_test') {
            throw new Exception("HRP does not match network ID");
        }

        $payloadHex = substr($hexPayload, 2);

        /**
         * CIP-0019 Address Types
         * 0: 0000.... PaymentKeyHash + StakeKeyHash
         * 1: 0001.... ScriptHash + StakeKeyHash
         * 2: 0010.... PaymentKeyHash+ ScriptHash
         * 3: 0011.... ScriptHash + ScriptHash
         * 4: 0100.... PaymentKeyHash + Pointer (Deprecated)
         * 5: 0101.... ScriptHash + Pointer (Deprecated)
         * 6: 0110.... PaymentKeyHash (Enterprise)
         * 7: 0111.... ScriptHash (Enterprise)
         */
        $stakingKeyHash = '';

        switch ($addressType) {
            case 0: // PaymentKeyHash + StakeKeyHash
            case 1: // ScriptHash + StakeKeyHash
                $paymentKeyHash = substr($payloadHex, 0, 56);
                $stakingKeyHash = substr($payloadHex, 56, 56);
                break;
            case 2: // PaymentKeyHash + ScriptHash
            case 3: // ScriptHash + ScriptHash
                $paymentKeyHash = substr($payloadHex, 0, 56);
                $stakingKeyHash = substr($payloadHex, 56, 56);
                break;
            case 4: // PaymentKeyHash + Pointer (Deprecated)
            case 5: // ScriptHash + Pointer (Deprecated)
                // Pointer addresses... blah...
                $paymentKeyHash = substr($payloadHex, 0, 56);
                $stakingKeyHash = substr($payloadHex, 56);
                break;
            case 6: // PaymentKeyHash (Enterprise)
            case 7: // ScriptHash (Enterprise)
                $paymentKeyHash = substr($payloadHex, 0, 56);
                break;
            default:
                // Probably throw an error here because we don't recognize the address type?
                throw new Exception("Unknown address type");
        }

        $stakeAddress = self::encodeCardanoStakeAddress($networkId, $addressType, $stakingKeyHash);

        return [
            'address'     => $bech32, 'addressType' => $addressType,
            'networkId'   => $networkId, 'paymentHash' => $paymentKeyHash,
            'stakingHash' => $stakingKeyHash, 'stakeAddress' => $stakeAddress,
        ];
    }

    public static function encodeCardanoAddress(int $addressType, int $networkId, string $paymentHash, string $stakeHash = '')
    {
        $addressHrp = $networkId ? 'addr' : 'addr_test';

        $addressTypeHeader = self::addressTypeToBinary($addressType);
        $addressNetworkHeader = self::networkIdToBinary($networkId);
        $addressHeader = dechex(bindec($addressTypeHeader)) . dechex(bindec($addressNetworkHeader));

        if ($addressType < 6 && empty($stakeHash)) {
            throw new Exception('Specified a staking address type without a stake hash');
        }

        $stakeAddress = self::encodeCardanoStakeAddress($networkId, $addressType, $stakeHash);

        $addressBytes = self::hexToByteArray($addressHeader . $paymentHash . $stakeHash);

        $bech32 = self::encode($addressHrp, $addressBytes);

        return [
            'address'   => $bech32, 'addressType' => $addressType,
            'networkId' => $networkId, 'paymentHash' => $paymentHash,
            'stakeHash' => $stakeHash, 'stakeAddress' => $stakeAddress,
        ];
    }

    public static function encodeCardanoStakeAddress($networkId, $addressType, $stakeHash)
    {
        $stakeKeyPrefix = self::addressTypeToStakePrefixBinary($addressType);
        if (is_null($stakeKeyPrefix)) {
            // Address type does not support staking
            return null;
        }
        $networkSuffix = self::networkIdToBinary($networkId);
        $stakeAddressHeader = self::binaryToHex($stakeKeyPrefix . $networkSuffix);
        $stakeAddressHex = $stakeAddressHeader . $stakeHash;
        $stakeAddressBytes = self::hexToByteArray($stakeAddressHex);
        $stakeHrp = $networkId ? 'stake' : 'stake_test';

        return self::encode($stakeHrp, $stakeAddressBytes);
    }

    /**
     * addressTypeToBinary
     *
     * Given an integer address type, return the correct binary header bits
     *
     * @param int $addressType
     *
     * @return string
     * @throws Exception
     */
    private static function addressTypeToBinary(int $addressType): string
    {
        /**
         * CIP-0019 Address Types
         *
         * 0: 0000.... PaymentKeyHash + StakeKeyHash
         * 1: 0001.... ScriptHash + StakeKeyHash
         * 2: 0010.... PaymentKeyHash+ ScriptHash
         * 3: 0011.... ScriptHash + ScriptHash
         * 4: 0100.... PaymentKeyHash + Pointer (Deprecated)
         * 5: 0101.... ScriptHash + Pointer (Deprecated)
         * 6: 0110.... PaymentKeyHash (Enterprise)
         * 7: 0111.... ScriptHash (Enterprise)
         */
        return match ($addressType) {
            0 => '0000',
            1 => '0001',
            2 => '0010',
            3 => '0011',
            4 => '0100',
            5 => '0101',
            6 => '0110',
            7 => '0111',
            default => throw new Exception("Unknown address type {$addressType}"),
        };
    }

    /**
     * networkIdToBinary
     *
     * Return the binary header bits for the specified network ID or throw an
     * exception
     *
     * @param int $networkId
     *
     * @return string
     * @throws Exception
     */
    private static function networkIdToBinary(int $networkId): string
    {
        /**
         * CIP-0019 Defined Network Tags
         *
         * Network Tag (. . . . n n n n)
         * 0: ....0000    Testnet(s)
         * 1: ....0001    Mainnet
         */
        return match ($networkId) {
            0 => '0000',
            1 => '0001',
            default => throw new Exception('Unknown network id'),
        };
    }

    /**
     * addressTypeToStakePrefixBinary
     *
     * Return the binary header bits for the stake address given the specified
     * address type or throw an error
     *
     * @param int $addressType
     *
     * @return string|null
     */
    private static function addressTypeToStakePrefixBinary(int $addressType): string|null
    {
        /**
         * CIP-0019 Stake Types
         * Header type (t t t t . . . .)
         * 0|1: (14) 1110....    StakeKeyHash
         * 2|3: (15) 1111....    ScriptHash
         */
        return match ($addressType) {
            0, 1 => '1110',
            2, 3 => '1111',
            default => null
        };
    }

    /**
     * decodeAddressHeader
     *
     * Given the first byte of a Cardano address, return the addressType and
     * networkId based on CIP-0019.
     *
     * @param string $hex
     *
     * @return array
     */
    private static function decodeAddressHeader(string $hex): array
    {
        $typeAndNetwork = self::hexToBinary($hex);
        $addressType = bindec(substr($typeAndNetwork, 0, 4)); // First 4 bits
        $networkId = bindec(substr($typeAndNetwork, 4, 4));   // Last 4 bits

        return [$addressType, $networkId];
    }

    /**
     * hashNativeAsset
     *
     * Helper function to provide CIP-0014 support for calculating the
     * Blake2b-160 hash of the concatenated Policy ID + Asset Name. Returns the
     * hex-encoded string representation of the asset hash.
     *
     * @param string $policyId  Hex-encoded Minting Policy ID
     * @param string $assetName Hex-encoded Asset Name
     *
     * @return string Hex-encoded Blake2b-160 Hash
     * @throws SodiumException
     */
    public static function hashNativeAsset(string $policyId, string $assetName): string
    {
        $assetBinary = sodium_hex2bin($policyId . $assetName);
        // For sodium_crypto_generichash the length is specified in bytes so 20B = 160b
        $b2Sum = sodium_crypto_generichash($assetBinary, "", 20);

        return sodium_bin2hex($b2Sum);
    }

    /**
     * encodeNativeAsset
     *
     * Return the fingerprint of the native asset given the specified policy ID
     * and asset name.
     *
     * @param string $policyId  Hex-encoded Minting Policy ID
     * @param string $assetName Hex-encoded Asset Name
     *
     * @return array Hex-encoded Blake2b-160 Hash
     * @throws SodiumException
     * @throws Exception
     */
    public static function encodeNativeAsset(string $policyId, string $assetName): array
    {
        // Policy Id and Asset Name must always be lowercase, trim whitespace...
        $policyId = strtolower(trim($policyId));
        $assetName = strtolower(trim($assetName));

        if (!preg_match(self::HEX_REGEX, $policyId)) {
            throw new Exception("PolicyId contains invalid characters");
        }

        if (!preg_match(self::HEX_REGEX, $assetName)) {
            throw new Exception("AssetName contains invalid characters");
        }

        if (strlen($policyId) !== 56) {
            throw new Exception("A Cardano native asset Policy ID must be 56 characters");
        }

        if (strlen($assetName) > 64) {
            throw new Exception("AssetName too long");
        }

        $assetHash = self::hashNativeAsset($policyId, $assetName);

        $payload = self::hexToByteArray($assetHash);
        $assetFingerprint = self::encode('asset', $payload);

        return [
            'policyId'         => $policyId, 'assetName' => $assetName,
            'assetFingerprint' => $assetFingerprint, 'assetHash' => $assetHash,
        ];
    }

    /**
     * @param string $bech32
     *
     * @return array
     * @throws Exception
     */
    public static function decodeNativeAsset(string $bech32): array
    {
        if (!str_starts_with($bech32, 'asset')) {
            throw new Exception("Invalid hrp");
        }
        if (strlen($bech32) !== 44) {
            throw new Exception("A Cardano native asset fingerprint must be 44 characters");
        }

        [, $data] = self::decode($bech32);

        $assetHash = self::byteArrayToHex($data);

        return [
            'assetFingerprint' => $bech32, 'assetHash' => $assetHash,
        ];
    }
}