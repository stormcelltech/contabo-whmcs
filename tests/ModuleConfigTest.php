<?php

declare(strict_types=1);

use Contabo\Whmcs\ModuleConfig;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class ModuleConfigTest extends TestCase
{
    public function test_whmcs_options_are_mapped_in_declared_order(): void
    {
        $config = ModuleConfig::fromWhmcs([
            'configoption1' => 'client-id',
            'configoption2' => 'client-secret',
            'configoption3' => 'api@example.com',
            'configoption4' => 'api-password',
            'configoption5' => 'European Union (EU)',
            'configoption6' => 'V153',
            'configoption7' => '3f184ab8-a600-4e7c-8c9b-3413e21a3752',
            'configoption8' => 'customer-vps',
            'configoption9' => '6',
            'configoption10' => 'on',
            'configoption11' => '',
        ]);

        self::assertSame('client-id', $config->clientId);
        self::assertSame('EU', $config->region);
        self::assertSame('V153', $config->productId);
        self::assertSame(6, $config->contractPeriod);
        self::assertTrue($config->clientPowerEnabled);
        self::assertFalse($config->automaticCancellationEnabled);
    }

    public function test_invalid_api_user_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new ModuleConfig('id', 'secret', 'not-email', 'password', 'EU', 'V153', '3f184ab8-a600-4e7c-8c9b-3413e21a3752', 'whmcs', 1, false, false);
    }

    #[DataProvider('maliciousProvisioningValues')]
    public function test_provisioning_identifiers_reject_injection_payloads(
        string $region,
        string $productId,
        string $imageId,
        string $hostnamePrefix,
    ): void {
        $this->expectException(InvalidArgumentException::class);
        new ModuleConfig('id', 'secret', 'api@example.com', 'password', $region, $productId, $imageId, $hostnamePrefix, 1, false, false);
    }

    /** @return iterable<string, array{string, string, string, string}> */
    public static function maliciousProvisioningValues(): iterable
    {
        $image = '3f184ab8-a600-4e7c-8c9b-3413e21a3752';
        yield 'region URL/SSRF' => ['http://169.254.169.254', 'V153', $image, 'whmcs'];
        yield 'product command injection' => ['EU', 'V153;whoami', $image, 'whmcs'];
        yield 'image traversal' => ['EU', 'V153', '../../etc/passwd', 'whmcs'];
        yield 'hostname XSS' => ['EU', 'V153', $image, '<script>alert(1)</script>'];
    }
}
