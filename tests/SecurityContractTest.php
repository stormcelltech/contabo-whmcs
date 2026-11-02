<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class SecurityContractTest extends TestCase
{
    public function test_entrypoint_does_not_route_actions_from_request_superglobal(): void
    {
        $source = (string) file_get_contents(__DIR__ . '/../contabo.php');
        self::assertStringNotContainsString('$_REQUEST', $source);
        self::assertStringContainsString("check_token('WHMCS.default')", $source);
    }

    public function test_client_state_changes_are_posted_with_csrf_token(): void
    {
        $template = (string) file_get_contents(__DIR__ . '/../templates/clientarea.tpl');
        self::assertSame(3, substr_count($template, 'method="post"'));
        self::assertSame(3, substr_count($template, 'name="token"'));
        self::assertStringNotContainsString('fetch(', $template);
    }

    public function test_raw_whmcs_params_are_never_sent_to_module_log(): void
    {
        $source = (string) file_get_contents(__DIR__ . '/../contabo.php');
        self::assertDoesNotMatchRegularExpression('/logModuleCall\([^;]*\$params/s', $source);
    }

    public function test_outbound_provider_hosts_are_fixed_and_tls_verification_cannot_be_disabled(): void
    {
        $api = (string) file_get_contents(__DIR__ . '/../lib/ContaboApi.php');
        $http = (string) file_get_contents(__DIR__ . '/../lib/Http.php');

        self::assertStringContainsString("private const API_BASE = 'https://api.contabo.com/v1'", $api);
        self::assertStringContainsString("private const AUTH_URL = 'https://auth.contabo.com/", $api);
        self::assertStringContainsString('CURLOPT_SSL_VERIFYPEER => true', $http);
        self::assertStringContainsString('CURLOPT_SSL_VERIFYHOST => 2', $http);
        self::assertStringContainsString('CURLOPT_FOLLOWLOCATION => false', $http);
        self::assertStringContainsString('CURLOPT_PROTOCOLS => CURLPROTO_HTTPS', $http);
    }

    public function test_database_locks_use_bound_parameters(): void
    {
        $source = (string) file_get_contents(__DIR__ . '/../lib/WhmcsAdapters.php');
        self::assertStringContainsString("selectOne('SELECT GET_LOCK(?, 10) AS acquired', [\$lockName])", $source);
        self::assertStringContainsString("selectOne('SELECT RELEASE_LOCK(?) AS released', [\$lockName])", $source);
    }

    public function test_client_and_admin_outputs_apply_contextual_html_escaping(): void
    {
        $entrypoint = (string) file_get_contents(__DIR__ . '/../contabo.php');
        $template = (string) file_get_contents(__DIR__ . '/../templates/clientarea.tpl');
        self::assertStringContainsString('htmlspecialchars(', $entrypoint);
        self::assertGreaterThanOrEqual(7, substr_count($template, '|escape'));
        self::assertStringNotContainsString('|raw', $template);
    }
}
