<?php

namespace Lapisense\WordPressClient\Tests\Unit;

use Brain\Monkey\Functions;
use Lapisense\WordPressClient\Client;

/**
 * @covers \Lapisense\WordPressClient\Client
 */
class ClientTest extends TestCase
{
    /** @var array<string, mixed> */
    private $baseConfig = array(
        'store_url'    => 'https://store.example.com',
        'product_uuid' => '550e8400-e29b-41d4-a716-446655440000',
        'product_type' => 'plugin',
        'file'         => '/var/www/html/wp-content/plugins/my-plugin/my-plugin.php',
    );

    /**
     * Stub the WordPress functions called during Client::init().
     */
    private function stubInitFunctions(): void
    {
        Functions\when('wp_parse_url')->justReturn(array('host' => 'store.example.com'));
        Functions\when('add_filter')->justReturn(true);
        Functions\when('plugin_basename')->alias(function ($file) {
            // Mimic WP plugin_basename: return path relative to plugins dir
            $pluginsDir = '/var/www/html/wp-content/plugins/';
            if (strpos($file, $pluginsDir) === 0) {
                return substr($file, strlen($pluginsDir));
            }
            return basename(dirname($file)) . '/' . basename($file);
        });
    }

    public function testInitWithPluginTypeRegistersPluginFilter(): void
    {
        Functions\when('wp_parse_url')->justReturn(array('host' => 'store.example.com'));
        Functions\when('plugin_basename')->justReturn('my-plugin/my-plugin.php');

        $pluginFilterRegistered = false;
        $productInfoRegistered = false;

        Functions\expect('add_filter')
            ->twice()
            ->with(
                \Mockery::on(function ($filterName) use (&$pluginFilterRegistered, &$productInfoRegistered) {
                    if (strpos($filterName, 'update_plugins_') === 0) {
                        $pluginFilterRegistered = true;
                    }
                    if ($filterName === 'plugins_api') {
                        $productInfoRegistered = true;
                    }
                    return true;
                }),
                \Mockery::any(),
                \Mockery::any(),
                \Mockery::any()
            );

        Client::init($this->baseConfig);

        $this->assertTrue($pluginFilterRegistered, 'update_plugins_ filter should be registered');
        $this->assertTrue($productInfoRegistered, 'plugins_api filter should be registered');
    }

    public function testInitWithThemeTypeRegistersThemeFilter(): void
    {
        $config = array_merge($this->baseConfig, array(
            'product_type' => 'theme',
            'file'         => '/var/www/html/wp-content/themes/my-theme/functions.php',
        ));

        Functions\when('wp_parse_url')->justReturn(array('host' => 'store.example.com'));

        $themeFilterRegistered = false;

        Functions\expect('add_filter')
            ->once()
            ->with(
                \Mockery::on(function ($filterName) use (&$themeFilterRegistered) {
                    if (strpos($filterName, 'update_themes_') === 0) {
                        $themeFilterRegistered = true;
                    }
                    return true;
                }),
                \Mockery::any(),
                \Mockery::any(),
                \Mockery::any()
            );

        Client::init($config);

        $this->assertTrue($themeFilterRegistered, 'update_themes_ filter should be registered');
    }

    public function testInitRegistersProductInfoForPlugins(): void
    {
        Functions\when('wp_parse_url')->justReturn(array('host' => 'store.example.com'));
        Functions\when('plugin_basename')->justReturn('my-plugin/my-plugin.php');

        $filtersRegistered = array();

        Functions\expect('add_filter')
            ->twice()
            ->with(
                \Mockery::on(function ($filterName) use (&$filtersRegistered) {
                    $filtersRegistered[] = $filterName;
                    return true;
                }),
                \Mockery::any(),
                \Mockery::any(),
                \Mockery::any()
            );

        Client::init($this->baseConfig);

        $this->assertContains('plugins_api', $filtersRegistered);
    }

    public function testInitDoesNotRegisterProductInfoForThemes(): void
    {
        $config = array_merge($this->baseConfig, array(
            'product_type' => 'theme',
            'file'         => '/var/www/html/wp-content/themes/my-theme/functions.php',
        ));

        Functions\when('wp_parse_url')->justReturn(array('host' => 'store.example.com'));

        $filtersRegistered = array();

        Functions\expect('add_filter')
            ->once()
            ->with(
                \Mockery::on(function ($filterName) use (&$filtersRegistered) {
                    $filtersRegistered[] = $filterName;
                    return true;
                }),
                \Mockery::any(),
                \Mockery::any(),
                \Mockery::any()
            );

        Client::init($config);

        $this->assertNotContains('plugins_api', $filtersRegistered);
    }

    public function testActivateStoresCredentialsOnSuccess(): void
    {
        $this->stubInitFunctions();

        $apiResponse = array(
            'success'    => true,
            'activation' => array('uuid' => 'act-uuid-123'),
        );

        Functions\expect('home_url')->once()->andReturn('https://client.example.com');
        Functions\expect('wp_json_encode')->once()->andReturn('{}');
        Functions\expect('wp_remote_post')->once()->andReturn(array('body' => ''));
        Functions\expect('is_wp_error')->once()->andReturn(false);
        Functions\expect('wp_remote_retrieve_response_code')->once()->andReturn(200);
        Functions\expect('wp_remote_retrieve_body')->once()->andReturn(json_encode($apiResponse));

        $prefix = 'lapisense_550e8400_e29b_41d4_a716_446655440000_';
        Functions\expect('update_option')
            ->once()
            ->with($prefix . 'license_key', 'ABCD-1234', false);
        Functions\expect('update_option')
            ->once()
            ->with($prefix . 'activation_uuid', 'act-uuid-123', false);

        $client = Client::init($this->baseConfig);
        $result = $client->activate('ABCD-1234');

        $this->assertSame($apiResponse, $result);
    }

    public function testActivateReturnsEmptyArrayOnApiFailure(): void
    {
        $this->stubInitFunctions();

        Functions\expect('home_url')->once()->andReturn('https://client.example.com');
        Functions\expect('wp_json_encode')->once()->andReturn('{}');
        Functions\expect('wp_remote_post')->once()->andReturn(new \stdClass());
        Functions\expect('is_wp_error')->once()->andReturn(true);

        $client = Client::init($this->baseConfig);
        $result = $client->activate('ABCD-1234');

        $this->assertSame(array(), $result);
    }

    public function testDeactivateReturnsTrueOnSuccess(): void
    {
        $this->stubInitFunctions();

        $prefix = 'lapisense_550e8400_e29b_41d4_a716_446655440000_';

        Functions\expect('get_option')
            ->once()
            ->with($prefix . 'activation_uuid', null)
            ->andReturn('act-uuid-123');

        Functions\expect('add_query_arg')->once()->andReturnUsing(function ($params, $url) {
            return $url . '?' . http_build_query($params);
        });
        Functions\expect('wp_remote_request')->once()->andReturn(array('body' => ''));
        Functions\expect('is_wp_error')->once()->andReturn(false);
        Functions\expect('wp_remote_retrieve_response_code')->once()->andReturn(200);
        Functions\expect('wp_remote_retrieve_body')
            ->once()
            ->andReturn(json_encode(array('success' => true)));

        Functions\expect('delete_option')
            ->once()
            ->with($prefix . 'license_key');
        Functions\expect('delete_option')
            ->once()
            ->with($prefix . 'activation_uuid');

        $client = Client::init($this->baseConfig);
        $result = $client->deactivate();

        $this->assertTrue($result);
    }

    public function testDeactivateReturnsFalseWhenNotActivated(): void
    {
        $this->stubInitFunctions();

        $prefix = 'lapisense_550e8400_e29b_41d4_a716_446655440000_';

        Functions\expect('get_option')
            ->once()
            ->with($prefix . 'activation_uuid', null)
            ->andReturn(null);

        $client = Client::init($this->baseConfig);
        $result = $client->deactivate();

        $this->assertFalse($result);
    }

    public function testDeactivateReturnsFalseOnApiFailure(): void
    {
        $this->stubInitFunctions();

        $prefix = 'lapisense_550e8400_e29b_41d4_a716_446655440000_';

        Functions\expect('get_option')
            ->once()
            ->with($prefix . 'activation_uuid', null)
            ->andReturn('act-uuid-123');

        Functions\expect('add_query_arg')->once()->andReturnUsing(function ($params, $url) {
            return $url . '?' . http_build_query($params);
        });
        Functions\expect('wp_remote_request')->once()->andReturn(new \stdClass());
        Functions\expect('is_wp_error')->once()->andReturn(true);

        $client = Client::init($this->baseConfig);
        $result = $client->deactivate();

        $this->assertFalse($result);
    }

    public function testIsActivatedReturnsTrueWhenActivated(): void
    {
        $this->stubInitFunctions();

        $prefix = 'lapisense_550e8400_e29b_41d4_a716_446655440000_';

        Functions\expect('get_option')
            ->once()
            ->with($prefix . 'activation_uuid', null)
            ->andReturn('act-uuid-123');

        $client = Client::init($this->baseConfig);
        $this->assertTrue($client->isActivated());
    }

    public function testIsActivatedReturnsFalseWhenNotActivated(): void
    {
        $this->stubInitFunctions();

        $prefix = 'lapisense_550e8400_e29b_41d4_a716_446655440000_';

        Functions\expect('get_option')
            ->once()
            ->with($prefix . 'activation_uuid', null)
            ->andReturn(null);

        $client = Client::init($this->baseConfig);
        $this->assertFalse($client->isActivated());
    }

    public function testGetActivationStatusReturnsCorrectArray(): void
    {
        $this->stubInitFunctions();

        $prefix = 'lapisense_550e8400_e29b_41d4_a716_446655440000_';

        Functions\expect('get_option')
            ->once()
            ->with($prefix . 'activation_uuid', null)
            ->andReturn('act-uuid-123');
        Functions\expect('get_option')
            ->once()
            ->with($prefix . 'activation_uuid', null)
            ->andReturn('act-uuid-123');
        Functions\expect('get_option')
            ->once()
            ->with($prefix . 'license_key', null)
            ->andReturn('LIC-KEY');

        $client = Client::init($this->baseConfig);
        $result = $client->getActivationStatus();

        $this->assertSame(array(
            'activated'       => true,
            'activation_uuid' => 'act-uuid-123',
            'license_key'     => 'LIC-KEY',
        ), $result);
    }
}
