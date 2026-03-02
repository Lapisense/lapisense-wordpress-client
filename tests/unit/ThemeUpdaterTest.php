<?php

namespace Lapisense\WordPressClient\Tests\Unit;

use Brain\Monkey\Functions;
use Lapisense\PHPClient\ApiClient;
use Lapisense\PHPClient\HttpClientInterface;
use Lapisense\PHPClient\StorageInterface;
use Lapisense\WordPressClient\ThemeUpdater;

/**
 * @covers \Lapisense\WordPressClient\ThemeUpdater
 */
class ThemeUpdaterTest extends TestCase
{
    /** @var string */
    private $storeUrl = 'https://store.example.com';

    /** @var string */
    private $productUuid = '550e8400-e29b-41d4-a716-446655440000';

    /** @var string */
    private $themeFile = '/var/www/html/wp-content/themes/my-theme/functions.php';

    /**
     * @return array<string, mixed>
     */
    private function makeConfig(array $overrides = array()): array
    {
        return array_merge(array(
            'store_url'    => $this->storeUrl,
            'product_uuid' => $this->productUuid,
            'file'         => $this->themeFile,
            'free'         => false,
        ), $overrides);
    }

    /**
     * @return array{
     *     ThemeUpdater,
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
        $updater = new ThemeUpdater($config, $apiClient, $storage);

        return array($updater, $httpClient, $storage);
    }

    public function testRegisterAddsThemeFilter(): void
    {
        list($updater) = $this->makeUpdater();

        Functions\expect('wp_parse_url')
            ->once()
            ->with($this->storeUrl)
            ->andReturn(array('host' => 'store.example.com'));

        Functions\expect('add_filter')
            ->once()
            ->with('update_themes_store.example.com', array($updater, 'checkUpdate'), 10, 4);

        $updater->register();
    }

    public function testIsOurProductMatchesThemeDirectory(): void
    {
        list($updater, $httpClient, $storage) = $this->makeUpdater();

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
            'my-theme',
            array()
        );

        $this->assertFalse($result);
    }

    public function testIsOurProductRejectsDifferentTheme(): void
    {
        list($updater) = $this->makeUpdater();

        $result = $updater->checkUpdate(
            false,
            array('Version' => '1.0.0'),
            'other-theme',
            array()
        );

        $this->assertFalse($result);
    }

    public function testBuildUpdateArrayReturnsThemeFormat(): void
    {
        list($updater, $httpClient, $storage) = $this->makeUpdater();

        $apiResponse = array(
            'update_available' => true,
            'version'          => '3.0.0',
            'homepage'         => 'https://example.com/theme',
            'package_url'      => 'https://store.example.com/download/3.0.0',
            'requires_wp'      => '6.0',
            'requires_php'     => '7.4',
        );

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
            array('Version' => '2.0.0'),
            'my-theme',
            array()
        );

        $this->assertIsArray($result);
        $this->assertSame('my-theme', $result['theme']);
        $this->assertSame('3.0.0', $result['new_version']);
        $this->assertSame('https://example.com/theme', $result['url']);
        $this->assertSame('https://store.example.com/download/3.0.0', $result['package']);
        $this->assertSame('6.0', $result['requires']);
        $this->assertSame('7.4', $result['requires_php']);
        $this->assertArrayNotHasKey('slug', $result);
        $this->assertArrayNotHasKey('tested', $result);
    }
}
