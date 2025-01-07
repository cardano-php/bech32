<?php

namespace CardanoPhp\Bech32;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Bech32::class)]
class Bech32AssetTest extends TestCase
{
    public static function assetProvider(): array
    {
        // Test vectors are defined in ./cip14-vectors.json
        return json_decode(file_get_contents(__DIR__ . '/cip14-vectors.json'), true);
    }

    #[DataProvider('assetProvider')]
    public function testManualAssetEncoding($policyId, $assetName, $assetHash, $assetFingerprint)
    {
        $hash = Bech32::hashNativeAsset($policyId, $assetName);
        $this->assertEquals($assetHash, $hash);
        $result = Bech32::encode('asset', Bech32::hexToByteArray($hash));
        $this->assertEquals($assetFingerprint, $result);
    }

    #[DataProvider('assetProvider')]
    public function testAutomaticAssetEncoding($policyId, $assetName, $assetHash, $assetFingerprint)
    {
        $result = Bech32::encodeNativeAsset($policyId, $assetName);
        $this->assertEquals([
            'policyId'  => $policyId, 'assetName' => $assetName,
            'assetHash' => $assetHash, 'assetFingerprint' => $assetFingerprint,
        ], $result);
    }

    #[DataProvider('assetProvider')]
    public function testManualAssetDecoding($policyId, $assetName, $assetHash, $assetFingerprint)
    {
        $hash = Bech32::hashNativeAsset($policyId, $assetName);
        $this->assertEquals($assetHash, $hash);
        [$hrp, $data] = Bech32::decode($assetFingerprint);
        $decodedHash = Bech32::byteArrayToHex($data);

        $this->assertEquals('asset', $hrp);
        $this->assertEquals($hash, $decodedHash);
    }

    #[DataProvider('assetProvider')]
    public function testAutomaticAssetDecoding($policyId, $assetName, $assetHash, $assetFingerprint)
    {
        $hash = Bech32::hashNativeAsset($policyId, $assetName);
        $this->assertEquals($assetHash, $hash);
        $result = Bech32::decodeNativeAsset($assetFingerprint);
        $this->assertEquals([
            'assetHash' => $assetHash, 'assetFingerprint' => $assetFingerprint,
        ], $result);
    }
}