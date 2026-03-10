<?php

namespace Lapisense\WordPressClient\Tests\Unit;

use Brain\Monkey\Functions;
use Lapisense\PHPClient\ApiClient;
use Lapisense\PHPClient\HttpClientInterface;
use Lapisense\PHPClient\StorageInterface;
use Lapisense\WordPressClient\PluginUpdater;

/**
 * @covers \Lapisense\WordPressClient\PluginUpdater
 * @covers \Lapisense\WordPressClient\AbstractUpdater
 */
class PluginUpdaterTest extends TestCase
{
    /** @var string */
    private $storeUrl = 'https://store.example.com';

    /** @var string */
    private $productUuid = '550e8400-e29b-41d4-a716-446655440000';

    /** @var string */
    private $pluginFile = '/var/www/html/wp-content/plugins/my-plugin/my-plugin.php';

    /**
     * @return array<string, mixed>
     */
    private function makeConfig(array $overrides = array()): array
    {
        return array_merge(array(
            'store_url'    => $this->storeUrl,
            'product_uuid' => $this->productUuid,
            'file'         => $this->pluginFile,
        ), $overrides);
    }

    /**
     * @return array{
     *     PluginUpdater,
     *     HttpClientInterface&\PHPUnit\Framework\MockObject\MockObject,
     *     StorageInterface&\PHPUnit\Framework\MockObject\MockObject
     * }
     */
    private function makeUpdater(array $configOverrides = array()): array
    {
        $httpClient = $this->createMock(HttpClientInterface::class);
        $storage = $this->createMock(StorageInterface::class);
        $config = $this->makeConfig($configOverrides);
        $apiClient = new ApiClient($config['store_url'], $config['product_uuid'], $httpClient);
        $updater = new PluginUpdater($config, $apiClient, $storage);

        return array($updater, $httpClient, $storage);
    }

    public function testRegisterAddsFilterWithHostname(): void
    {
        list($updater) = $this->makeUpdater();

        Functions\expect('wp_parse_url')
            ->once()
            ->with($this->storeUrl)
            ->andReturn(array('host' => 'store.example.com'));

        Functions\expect('add_filter')
            ->once()
            ->with('update_plugins_store.example.com', array($updater, 'checkUpdate'), 10, 4);

        $updater->register();
    }

    public function testRegisterDoesNothingWithoutHostname(): void
    {
        list($updater) = $this->makeUpdater(array('store_url' => ''));

        Functions\expect('wp_parse_url')
            ->once()
            ->with('')
            ->andReturn(array());

        Functions\expect('add_filter')->never();

        $updater->register();
    }

    public function testCheckUpdateReturnsFalseWhenFileConfigEmpty(): void
    {
        list($updater) = $this->makeUpdater(array('file' => ''));

        $result = $updater->checkUpdate(
            false,
            array('Version' => '1.0.0'),
            'my-plugin/my-plugin.php',
            array()
        );

        $this->assertFalse($result);
    }

    public function testCheckUpdateReturnsFalseForDifferentPlugin(): void
    {
        list($updater) = $this->makeUpdater();

        Functions\expect('plugin_basename')
            ->once()
            ->with($this->pluginFile)
            ->andReturn('my-plugin/my-plugin.php');

        $result = $updater->checkUpdate(
            false,
            array('Version' => '1.0.0'),
            'other-plugin/other-plugin.php',
            array()
        );

        $this->assertFalse($result);
    }

    public function testCheckUpdateReturnsCachedResult(): void
    {
        list($updater, $httpClient) = $this->makeUpdater();
        $cached = array('slug' => 'my-plugin', 'version' => '2.0.0');

        Functions\expect('plugin_basename')
            ->once()
            ->with($this->pluginFile)
            ->andReturn('my-plugin/my-plugin.php');

        Functions\expect('get_transient')
            ->once()
            ->andReturn($cached);

        $httpClient->expects($this->never())->method('get');

        $result = $updater->checkUpdate(
            false,
            array('Version' => '1.0.0'),
            'my-plugin/my-plugin.php',
            array()
        );

        $this->assertSame($cached, $result);
    }

    public function testCheckUpdateCallsApiAndCaches(): void
    {
        list($updater, $httpClient, $storage) = $this->makeUpdater();

        $apiResponse = array(
            'update_available' => true,
            'version'          => '2.0.0',
            'homepage'         => 'https://example.com',
            'package_url'      => 'https://store.example.com/download/2.0.0',
            'tested_wp'        => '6.4',
            'requires_php'     => '7.4',
            'requires_wp'      => '5.8',
        );

        Functions\expect('plugin_basename')
            ->with($this->pluginFile)
            ->andReturn('my-plugin/my-plugin.php');

        Functions\expect('get_transient')->once()->andReturn(false);

        $storage->method('get')
            ->with('activation_uuid')
            ->willReturn('act-uuid-123');

        $httpClient->expects($this->once())
            ->method('get')
            ->willReturn($apiResponse);

        Functions\expect('set_transient')
            ->once()
            ->with(
                \Mockery::type('string'),
                \Mockery::type('array'),
                6 * HOUR_IN_SECONDS
            );

        $result = $updater->checkUpdate(
            false,
            array('Version' => '1.0.0'),
            'my-plugin/my-plugin.php',
            array()
        );

        $this->assertIsArray($result);
        $this->assertSame('2.0.0', $result['version']);
    }

    public function testCheckUpdateReturnsFalseWhenNoUpdate(): void
    {
        list($updater, $httpClient, $storage) = $this->makeUpdater();

        Functions\expect('plugin_basename')
            ->with($this->pluginFile)
            ->andReturn('my-plugin/my-plugin.php');

        Functions\expect('get_transient')->once()->andReturn(false);

        $storage->method('get')
            ->with('activation_uuid')
            ->willReturn('act-uuid-123');

        $httpClient->expects($this->once())
            ->method('get')
            ->willReturn(array('update_available' => false));

        Functions\expect('set_transient')->once();

        $result = $updater->checkUpdate(
            false,
            array('Version' => '1.0.0'),
            'my-plugin/my-plugin.php',
            array()
        );

        $this->assertFalse($result);
    }

    public function testCheckUpdateReturnsPluginFormatArray(): void
    {
        list($updater, $httpClient, $storage) = $this->makeUpdater();

        $apiResponse = array(
            'update_available' => true,
            'version'          => '2.0.0',
            'homepage'         => 'https://example.com',
            'package_url'      => 'https://store.example.com/download/2.0.0',
            'tested_wp'        => '6.4',
            'requires_php'     => '7.4',
            'requires_wp'      => '5.8',
        );

        Functions\expect('plugin_basename')
            ->with($this->pluginFile)
            ->andReturn('my-plugin/my-plugin.php');

        Functions\expect('get_transient')->once()->andReturn(false);

        $storage->method('get')
            ->with('activation_uuid')
            ->willReturn('act-uuid-123');

        $httpClient->expects($this->once())
            ->method('get')
            ->willReturn($apiResponse);

        Functions\expect('set_transient')->once();

        $result = $updater->checkUpdate(
            false,
            array('Version' => '1.0.0'),
            'my-plugin/my-plugin.php',
            array()
        );

        $this->assertIsArray($result);
        $this->assertSame('my-plugin', $result['slug']);
        $this->assertSame('2.0.0', $result['version']);
        $this->assertSame('https://example.com', $result['url']);
        $this->assertSame('https://store.example.com/download/2.0.0', $result['package']);
        $this->assertSame('6.4', $result['tested']);
        $this->assertSame('7.4', $result['requires_php']);
        $this->assertSame('5.8', $result['requires']);
    }

    public function testCheckUpdateProceedsWithoutActivationUuid(): void
    {
        list($updater, $httpClient, $storage) = $this->makeUpdater();

        $apiResponse = array(
            'update_available' => true,
            'version'          => '2.0.0',
            'homepage'         => 'https://example.com',
            'package_url'      => 'https://store.example.com/download/2.0.0',
            'tested_wp'        => '6.4',
            'requires_php'     => '7.4',
            'requires_wp'      => '5.8',
        );

        Functions\expect('plugin_basename')
            ->with($this->pluginFile)
            ->andReturn('my-plugin/my-plugin.php');

        Functions\expect('get_transient')->once()->andReturn(false);

        $storage->method('get')
            ->with('activation_uuid')
            ->willReturn(null);

        $httpClient->expects($this->once())
            ->method('get')
            ->with(
                $this->storeUrl . '/wp-json/lapisense/v1/licensing/update-check',
                array(
                    'product_uuid'    => $this->productUuid,
                    'current_version' => '1.0.0',
                )
            )
            ->willReturn($apiResponse);

        Functions\expect('set_transient')->once();

        $result = $updater->checkUpdate(
            false,
            array('Version' => '1.0.0'),
            'my-plugin/my-plugin.php',
            array()
        );

        $this->assertIsArray($result);
        $this->assertSame('2.0.0', $result['version']);
    }
}
