<?php

namespace Lapisense\WordPressClient\Tests\Unit;

use Brain\Monkey\Functions;
use Lapisense\WordPressClient\WpHttpClient;

/**
 * @covers \Lapisense\WordPressClient\WpHttpClient
 */
class WpHttpClientTest extends TestCase
{
    /** @var WpHttpClient */
    private $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = new WpHttpClient();
    }

    public function testGetCallsWpRemoteGet(): void
    {
        $url = 'https://store.example.com/wp-json/lapisense/v1/test';
        $response = array('headers' => array(), 'body' => '{"ok":true}', 'response' => array('code' => 200));

        Functions\expect('wp_remote_get')
            ->once()
            ->with($url, array('timeout' => 15))
            ->andReturn($response);

        Functions\expect('is_wp_error')->once()->with($response)->andReturn(false);
        Functions\expect('wp_remote_retrieve_response_code')->once()->with($response)->andReturn(200);
        Functions\expect('wp_remote_retrieve_body')->once()->with($response)->andReturn('{"ok":true}');

        $result = $this->client->get($url);
        $this->assertSame(array('ok' => true), $result);
    }

    public function testGetAppendsQueryParams(): void
    {
        $url = 'https://store.example.com/api';
        $params = array('key' => 'value', 'foo' => 'bar');
        $urlWithParams = 'https://store.example.com/api?key=value&foo=bar';
        $response = array('body' => '{"data":1}');

        Functions\expect('add_query_arg')
            ->once()
            ->with($params, $url)
            ->andReturn($urlWithParams);

        Functions\expect('wp_remote_get')
            ->once()
            ->with($urlWithParams, array('timeout' => 15))
            ->andReturn($response);

        Functions\expect('is_wp_error')->once()->andReturn(false);
        Functions\expect('wp_remote_retrieve_response_code')->once()->andReturn(200);
        Functions\expect('wp_remote_retrieve_body')->once()->andReturn('{"data":1}');

        $result = $this->client->get($url, $params);
        $this->assertSame(array('data' => 1), $result);
    }

    public function testGetReturnsDecodedJsonOnSuccess(): void
    {
        $response = array('body' => '{"name":"Test","version":"1.0"}');

        Functions\expect('wp_remote_get')->once()->andReturn($response);
        Functions\expect('is_wp_error')->once()->andReturn(false);
        Functions\expect('wp_remote_retrieve_response_code')->once()->andReturn(200);
        Functions\expect('wp_remote_retrieve_body')->once()->andReturn('{"name":"Test","version":"1.0"}');

        $result = $this->client->get('https://example.com/api');
        $this->assertSame(array('name' => 'Test', 'version' => '1.0'), $result);
    }

    public function testGetReturnsNullOnWpError(): void
    {
        $wpError = new \stdClass();

        Functions\expect('wp_remote_get')->once()->andReturn($wpError);
        Functions\expect('is_wp_error')->once()->with($wpError)->andReturn(true);

        $result = $this->client->get('https://example.com/api');
        $this->assertNull($result);
    }

    public function testGetReturnsNullOnNon2xxStatus(): void
    {
        $response = array('body' => '{"error":"not found"}');

        Functions\expect('wp_remote_get')->once()->andReturn($response);
        Functions\expect('is_wp_error')->once()->andReturn(false);
        Functions\expect('wp_remote_retrieve_response_code')->once()->andReturn(404);

        $result = $this->client->get('https://example.com/api');
        $this->assertNull($result);
    }

    public function testGetReturnsNullOnInvalidJson(): void
    {
        $response = array('body' => 'not json');

        Functions\expect('wp_remote_get')->once()->andReturn($response);
        Functions\expect('is_wp_error')->once()->andReturn(false);
        Functions\expect('wp_remote_retrieve_response_code')->once()->andReturn(200);
        Functions\expect('wp_remote_retrieve_body')->once()->andReturn('not json');

        $result = $this->client->get('https://example.com/api');
        $this->assertNull($result);
    }

    public function testPostSendsJsonBody(): void
    {
        $url = 'https://store.example.com/api';
        $body = array('license_key' => 'ABCD-1234');
        $jsonBody = '{"license_key":"ABCD-1234"}';
        $response = array('body' => '{"success":true}');

        Functions\expect('wp_json_encode')
            ->once()
            ->with($body)
            ->andReturn($jsonBody);

        Functions\expect('wp_remote_post')
            ->once()
            ->with($url, array(
                'timeout' => 15,
                'body'    => $jsonBody,
                'headers' => array(
                    'Content-Type' => 'application/json',
                ),
            ))
            ->andReturn($response);

        Functions\expect('is_wp_error')->once()->andReturn(false);
        Functions\expect('wp_remote_retrieve_response_code')->once()->andReturn(200);
        Functions\expect('wp_remote_retrieve_body')->once()->andReturn('{"success":true}');

        $result = $this->client->post($url, $body);
        $this->assertSame(array('success' => true), $result);
    }

    public function testPostReturnsDecodedJson(): void
    {
        $expected = array('activation_uuid' => 'uuid-123', 'status' => 'active');
        $response = array('body' => json_encode($expected));

        Functions\expect('wp_json_encode')->once()->andReturn('{}');
        Functions\expect('wp_remote_post')->once()->andReturn($response);
        Functions\expect('is_wp_error')->once()->andReturn(false);
        Functions\expect('wp_remote_retrieve_response_code')->once()->andReturn(200);
        Functions\expect('wp_remote_retrieve_body')->once()->andReturn(json_encode($expected));

        $result = $this->client->post('https://example.com/api', array());
        $this->assertSame($expected, $result);
    }

    public function testPostReturnsNullOnFailure(): void
    {
        $wpError = new \stdClass();

        Functions\expect('wp_json_encode')->once()->andReturn('{}');
        Functions\expect('wp_remote_post')->once()->andReturn($wpError);
        Functions\expect('is_wp_error')->once()->with($wpError)->andReturn(true);

        $result = $this->client->post('https://example.com/api', array());
        $this->assertNull($result);
    }

    public function testDeleteUsesDeleteMethod(): void
    {
        $url = 'https://store.example.com/api/activations/uuid-123';
        $response = array('body' => '{"deactivated":true}');

        Functions\expect('wp_remote_request')
            ->once()
            ->with($url, array(
                'method'  => 'DELETE',
                'timeout' => 15,
            ))
            ->andReturn($response);

        Functions\expect('is_wp_error')->once()->andReturn(false);
        Functions\expect('wp_remote_retrieve_response_code')->once()->andReturn(200);
        Functions\expect('wp_remote_retrieve_body')->once()->andReturn('{"deactivated":true}');

        $result = $this->client->delete($url);
        $this->assertSame(array('deactivated' => true), $result);
    }

    public function testDeleteAppendsQueryParams(): void
    {
        $url = 'https://store.example.com/api/activations/uuid-123';
        $params = array('product_uuid' => 'prod-uuid');
        $urlWithParams = $url . '?product_uuid=prod-uuid';
        $response = array('body' => '{"deactivated":true}');

        Functions\expect('add_query_arg')
            ->once()
            ->with($params, $url)
            ->andReturn($urlWithParams);

        Functions\expect('wp_remote_request')
            ->once()
            ->with($urlWithParams, array(
                'method'  => 'DELETE',
                'timeout' => 15,
            ))
            ->andReturn($response);

        Functions\expect('is_wp_error')->once()->andReturn(false);
        Functions\expect('wp_remote_retrieve_response_code')->once()->andReturn(200);
        Functions\expect('wp_remote_retrieve_body')->once()->andReturn('{"deactivated":true}');

        $result = $this->client->delete($url, $params);
        $this->assertSame(array('deactivated' => true), $result);
    }

    public function testDeleteReturnsNullOnFailure(): void
    {
        $wpError = new \stdClass();

        Functions\expect('wp_remote_request')->once()->andReturn($wpError);
        Functions\expect('is_wp_error')->once()->with($wpError)->andReturn(true);

        $result = $this->client->delete('https://example.com/api/test');
        $this->assertNull($result);
    }
}
