<?php

namespace CardanoPhp\Bech32;

use Exception;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Bech32::class)]
class Bech32BadAssetTest extends TestCase
{
    public static function invalidAssetProvider(): array
    {
        // Test vectors are defined in ./cip14-vectors.json
        return json_decode(file_get_contents(__DIR__ . '/cip14-invalid-vectors.json'), true);
    }

    #[DataProvider('invalidAssetProvider')]
    public function testInvalidAssets($policyId, $assetName, $assetFingerprint, $test, $exception)
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage($exception);
        switch ($test) {
            case 'encode':
                Bech32::encodeNativeAsset($policyId, $assetName);
                break;
            case 'decode':
                Bech32::decodeNativeAsset($assetFingerprint);
                break;
        }
    }
}