<?php

namespace Auth0\Tests\unit\API\Management;

use Auth0\SDK\API\Helpers\InformationHeaders;
use Auth0\Tests\API\ApiTests;
use GuzzleHttp\Psr7\Response;

class BlacklistsTest extends ApiTests
{
    /**
     * Expected telemetry value.
     *
     * @var string
     */
    protected static $expectedTelemetry;

    /**
     * Default request headers.
     *
     * @var array
     */
    protected static $headers = ['content-type' => 'json'];

    /**
     * Runs before test suite starts.
     */
    public static function setUpBeforeClass(): void
    {
        $infoHeadersData = new InformationHeaders();
        $infoHeadersData->setCorePackage();
        self::$expectedTelemetry = $infoHeadersData->build();
    }

    public function testGet()
    {
        $api = new MockManagementApi([new Response(200, self::$headers)]);

        $api->call()->blacklists()->get('__test_aud__');

        $this->assertEquals('GET', $api->getHistoryMethod());
        $this->assertStringStartsWith('https://api.test.local/api/v2/blacklists/tokens', $api->getHistoryUrl());

        $this->assertEquals('aud=__test_aud__', $api->getHistoryQuery());
    }

    public function testBlacklist()
    {
        $api = new MockManagementApi([new Response(200, self::$headers)]);

        $api->call()->blacklists()->create('__test_jti__', '__test_aud__');

        $this->assertEquals('POST', $api->getHistoryMethod());
        $this->assertEquals('https://api.test.local/api/v2/blacklists/tokens', $api->getHistoryUrl());
        $this->assertEmpty($api->getHistoryQuery());

        $body = $api->getHistoryBody();
        $this->assertArrayHasKey('aud', $body);
        $this->assertEquals('__test_aud__', $body['aud']);
        $this->assertArrayHasKey('jti', $body);
        $this->assertEquals('__test_jti__', $body['jti']);
    }
}
