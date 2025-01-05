<?php

namespace CardanoPhp\Bech32;

use Exception;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Bech32::class)]
class Bech32Test extends TestCase
{
    public function testEncodeValidInput()
    {
        $hrp = 'test';
        $dataChars = [15, 1, 0, 11, 31];
        $expected = 'test10pqtlnsvkuk';

        $result = Bech32::encode($hrp, $dataChars);
        $this->assertEquals($expected, $result);
    }

    public function testDecodeValidInput()
    {
        $bech32String = 'addr10pqtlnyq06v';
        $expectedHrp = 'addr';
        $expectedDataChars = [15, 1, 0, 11, 31];

        [$hrp, $dataChars] = Bech32::decode($bech32String);

        $this->assertEquals($expectedHrp, $hrp);
        $this->assertEquals($expectedDataChars, $dataChars);
    }

    public function testEncodeHrpTooShort()
    {
        $expectedBech32String = '10pqtlnyq06v';
        $hrp = '';
        $dataChars = [15, 1, 0, 11, 31];

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('HRP too short');

        $result = Bech32::encode($hrp, $dataChars);
    }

    public function testEncodeHrpTooLong()
    {
        $expectedBech32String = 'an84characterslonghumanreadablepartthatcontainsthenumber1andtheexcludedcharactersbio10pqtlnyq06v';
        $hrp = 'an84characterslonghumanreadablepartthatcontainsthenumber1andtheexcludedcharactersbio';
        $dataChars = [15, 1, 0, 11, 31];

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('HRP too long');

        $result = Bech32::encode($hrp, $dataChars);
    }

    public function testEncodeSpaceInHrp()
    {
        $expectedBech32String = "addr test10pqtlnyq06v";
        $hrp = "addr test";
        $dataChars = [15, 1, 0, 11, 31];

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Invalid characters in HRP');

        $result = Bech32::encode($hrp, $dataChars);
    }

    public function testDecodeSpaceInBech32()
    {
        $bech32String = "addr test10pqtlnyq06v";

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Out of range character in Bech32 string');

        $result = Bech32::decode($bech32String);
    }

    public function testDecodeHrpTooLong()
    {
        $bech32String = 'an84characterslonghumanreadablepartthatcontainsthenumber1andtheexcludedcharactersbio10pqtlnyq06v';

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('HRP too long');

        $result = Bech32::decode($bech32String);
    }

    public function testDecodeMissingSeparatorCharacter()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Missing separator character');

        Bech32::decode('addrqqqqqqqq');
    }

    public function testInvalidBitsInByteArray()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Invalid value for convert bits');

        Bech32::byteArrayToHex([-1, 13, 14]);
    }

    public function testInvalidDataInByteArray()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Invalid data');

        Bech32::byteArrayToHex([1, 10, 13]);
    }

    public function testShouldPadUnevenBits()
    {
        $result = Bech32::byteArrayToHex([
            14, 6, 8, 6, 17, 9, 29, 3, 30, 0, 4, 8, 0, 15, 22, 26, 25, 1, 29,
            15, 2, 24, 12, 24, 12, 3, 25, 12, 27, 19, 15, 4, 1, 16, 19, 9, 16,
            28, 25, 5, 21, 19, 16, 19, 17, 11, 8, 13, 9, 17, 24, 23, 23,
        ], true);

        $this->assertEquals('719068a7a3f008803edac87af1619860f2cdcde40c26987325ace138ad0d4c717b80', $result);
    }

    public function testDecodeMissingHrp()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('HRP too short');

        Bech32::decode('1qqqqqqqq');
    }

    public function testDecodeInvalidStringTooShort()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Bech32 string is too short");

        Bech32::decode("short");
    }

    public function testDecodeInvalidCharacterRange()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Invalid characters in Bech32 data");

        Bech32::decode("test1!qqqqqqqq");
    }

    public function testDecodeMixedCase()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Data contains mixed case characters");

        Bech32::decode("TeSt1pqqq0");
    }

    public function testDecodeInvalidChecksum()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Invalid Bech32 checksum");

        Bech32::decode("test1pqqqqd");
    }

    public function testDecodeTooShortChecksum()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Too short checksum");

        Bech32::decode('test1qqqqp');
    }

    public function testHexToByteArrayValidInput()
    {
        $hexInput = '0f1b2c';
        $expected = [1, 28, 13, 18, 24];

        $result = Bech32::hexToByteArray($hexInput);

        $this->assertEquals($expected, $result);
    }

    public function testHexToByteArrayEmptyInput()
    {
        $hexInput = '';
        $expected = [];

        $result = Bech32::hexToByteArray($hexInput);

        $this->assertEquals($expected, $result);
    }

    public function testDecodeShelleyStakingAddress()
    {
        $address = 'addr1qxegfu8m62peqmyamrdwmwqm00zjcak3u25xnanfdct4p9pf488uagw68fv50kjxv3wrx38829tay6zszthnccsradgqwt4upy';
        $expected = [
            'address'      => 'addr1qxegfu8m62peqmyamrdwmwqm00zjcak3u25xnanfdct4p9pf488uagw68fv50kjxv3wrx38829tay6zszthnccsradgqwt4upy',
            'addressType'  => 0, 'networkId' => 1,
            'paymentHash'  => 'b284f0fbd283906c9dd8daedb81b7bc52c76d1e2a869f6696e175094',
            'stakingHash'  => '29a9cfcea1da3a5947da46645c3344e75157d2685012ef3c6203eb50',
            'stakeAddress' => 'stake1uy56nn7w58dr5k28mfrxghpngnn4z47jdpgp9meuvgp7k5qmhycnp',
        ];

        $result = Bech32::decodeCardanoAddress($address);

        $this->assertEquals($expected, $result);
    }

    public function testDecodeShelleyStakedScript()
    {
        $address = 'addr1xxgx3far7qygq0k6epa0zcvcvrevmn0ypsnfsue94nsn3tfvjel5h55fgjcxgchp830r7h2l5msrlpt8262r3nvr8eks2utwdd';
        $expected = [
            'address'      => 'addr1xxgx3far7qygq0k6epa0zcvcvrevmn0ypsnfsue94nsn3tfvjel5h55fgjcxgchp830r7h2l5msrlpt8262r3nvr8eks2utwdd',
            'addressType'  => 3, 'networkId' => 1,
            'paymentHash'  => '9068a7a3f008803edac87af1619860f2cdcde40c26987325ace138ad',
            'stakingHash'  => '2c967f4bd28944b06462e13c5e3f5d5fa6e03f8567569438cd833e6d',
            'stakeAddress' => 'stake17ykfvl6t62y5fvryvtsnch3lt406dcpls4n4d9pcekpnumg6v83tq',
        ];

        $result = Bech32::decodeCardanoAddress($address);

        $this->assertEquals($expected, $result);
    }

    public function testDecodeShelleyEnterpriseAddress()
    {
        $address = 'addr1vyntqn4yflcsjj2akudmxwjq0anmk99ut9zmghk7qe99jpcg69wvw';
        $expected = [
            'address'     => 'addr1vyntqn4yflcsjj2akudmxwjq0anmk99ut9zmghk7qe99jpcg69wvw',
            'addressType' => 6, 'networkId' => 1,
            'paymentHash' => '26b04ea44ff109495db71bb33a407f67bb14bc5945b45ede064a5907',
            'stakingHash' => '', 'stakeAddress' => null,
        ];

        $result = Bech32::decodeCardanoAddress($address);

        $this->assertEquals($expected, $result);
    }

    public function testDecodeShelleyEnterpriseScript()
    {
        $address = 'addr1wxgx3far7qygq0k6epa0zcvcvrevmn0ypsnfsue94nsn3tgdf3chh';
        $expected = [
            'address'     => 'addr1wxgx3far7qygq0k6epa0zcvcvrevmn0ypsnfsue94nsn3tgdf3chh',
            'addressType' => 7, 'networkId' => 1,
            'paymentHash' => '9068a7a3f008803edac87af1619860f2cdcde40c26987325ace138ad',
            'stakingHash' => '', 'stakeAddress' => null,
        ];

        $result = Bech32::decodeCardanoAddress($address);

        $this->assertEquals($expected, $result);
    }

    public function testDecodeAddressUnknownType()
    {
        $address = 'addr1sxgx3far7qygq0k6epa0zcvcvrevmn0ypsnfsue94nsn3tgp2pc4h';
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Unknown address type');

        Bech32::decodeCardanoAddress($address);
    }

    public function testDecodeAddressBadPrefix()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Not a Cardano Shelley Address");

        $address = 'hosky1vyntqn4yflcsjj2akudmxwjq0anmk99ut9zmghk7qe99jpcg69wvw';

        Bech32::decodeCardanoAddress($address);
    }

    public function testDecodeAddressWrongHrpNetworkTestnet()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("HRP does not match network ID");

        $address = 'addr_test1qxegfu8m62peqmyamrdwmwqm00zjcak3u25xnanfdct4p9pf488uagw68fv50kjxv3wrx38829tay6zszthnccsradgqutpmlx';

        Bech32::decodeCardanoAddress($address);
    }

    public function testDecodeAddressWrongHrpNetworkMainnet()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("HRP does not match network ID");

        $address = 'addr1qzegfu8m62peqmyamrdwmwqm00zjcak3u25xnanfdct4p9pf488uagw68fv50kjxv3wrx38829tay6zszthnccsradgqlaumne';

        Bech32::decodeCardanoAddress($address);
    }

    public function testEncodeEnterpriseKeyAddress()
    {
        /**
         * JPG v2 Enterprise Public Contract
         */
        $expected = [
            'address'     => 'addr1wxgx3far7qygq0k6epa0zcvcvrevmn0ypsnfsue94nsn3tgdf3chh',
            'addressType' => 7, 'networkId' => 1,
            'paymentHash' => '9068a7a3f008803edac87af1619860f2cdcde40c26987325ace138ad',
            'stakeHash'   => '', 'stakeAddress' => null,
        ];

        $address = Bech32::encodeCardanoAddress(7, 1, '9068a7a3f008803edac87af1619860f2cdcde40c26987325ace138ad');
        $this->assertEquals($expected, $address);
    }

    public function testEncodeScriptScriptAddress()
    {
        /**
         * JPG v2 Staked Public Contract
         */
        $expected = [
            'address'      => 'addr1xxgx3far7qygq0k6epa0zcvcvrevmn0ypsnfsue94nsn3tfvjel5h55fgjcxgchp830r7h2l5msrlpt8262r3nvr8eks2utwdd',
            'addressType'  => 3, 'networkId' => 1,
            'paymentHash'  => '9068a7a3f008803edac87af1619860f2cdcde40c26987325ace138ad',
            'stakeHash'    => '2c967f4bd28944b06462e13c5e3f5d5fa6e03f8567569438cd833e6d',
            'stakeAddress' => 'stake17ykfvl6t62y5fvryvtsnch3lt406dcpls4n4d9pcekpnumg6v83tq',
        ];

        $address = Bech32::encodeCardanoAddress(3, 1, '9068a7a3f008803edac87af1619860f2cdcde40c26987325ace138ad', '2c967f4bd28944b06462e13c5e3f5d5fa6e03f8567569438cd833e6d');
        $this->assertEquals($expected, $address);
    }

    public function testEncodeScriptKeyAddress()
    {
        /**
         * Splash Protocol Order Contract
         */
        $expected = [
            'address'      => 'addr1z9ryamhgnuz6lau86sqytte2gz5rlktv2yce05e0h3207q4hjqytncm25ykw7f62p0zzs2tk87w0lkzvnza32luf993q7cy7fs',
            'addressType'  => 1, 'networkId' => 1,
            'paymentHash'  => '464eeee89f05aff787d40045af2a40a83fd96c513197d32fbc54ff02',
            'stakeHash'    => 'b79008b9e36aa12cef274a0bc42829763f9cffd84c98bb157f892962',
            'stakeAddress' => 'stake1uxmeqz9eud42zt80ya9qh3pg99mrl88lmpxf3wc407yjjcsrtmywc',
        ];

        $address = Bech32::encodeCardanoAddress(1, 1, '464eeee89f05aff787d40045af2a40a83fd96c513197d32fbc54ff02', 'b79008b9e36aa12cef274a0bc42829763f9cffd84c98bb157f892962');
        $this->assertEquals($expected, $address);
    }

    public function testEncodeStakeWithoutStakeHash()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Specified a staking address type without a stake hash");

        Bech32::encodeCardanoAddress(0, 1, '9068a7a3f008803edac87af1619860f2cdcde40c26987325ace138ad');
    }

}
