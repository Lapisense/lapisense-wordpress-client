<?php

namespace Lapisense\WordPressClient\Tests\Unit;

use Brain\Monkey\Functions;
use Lapisense\PHPClient\ApiClient;
use Lapisense\PHPClient\HttpClientInterface;
use Lapisense\WordPressClient\ProductInfo;
use stdClass;

/**
 * @covers \Lapisense\WordPressClient\ProductInfo
 */
class ProductInfoTest extends TestCase
{
    /** @var string */
    private $storeUrl = 'https://store.example.com';

    /** @var string */
    private $productUuid = '550e8400-e29b-41d4-a716-446655440000';

    /** @var string */
    private $pluginFile = '/var/www/html/wp-content/plugins/my-plugin/my-plugin.php';

    /**
     * @return array{ProductInfo, HttpClientInterface&\PHPUnit\Framework\MockObject\MockObject}
     */
    private function makeProductInfo(array $configOverrides = array()): array
    {
        $httpClient = $this->createMock(HttpClientInterface::class);
        $config = array_merge(array(
            'store_url'    => $this->storeUrl,
            'product_uuid' => $this->productUuid,
            'file'         => $this->pluginFile,
        ), $configOverrides);
        $apiClient = new ApiClient($config['store_url'], $config['product_uuid'], $httpClient);
        $productInfo = new ProductInfo($config, $apiClient);

        return array($productInfo, $httpClient);
    }

    public function testRegisterAddsPluginsApiFilter(): void
    {
        list($productInfo) = $this->makeProductInfo();

        Functions\expect('add_filter')
            ->once()
            ->with('plugins_api', array($productInfo, 'filterPluginsApi'), 10, 3);

        $productInfo->register();
    }

    public function testFilterIgnoresNonPluginInformationAction(): void
    {
        list($productInfo) = $this->makeProductInfo();

        $args = new stdClass();
        $args->slug = 'my-plugin';

        $result = $productInfo->filterPluginsApi(false, 'query_plugins', $args);
        $this->assertFalse($result);
    }

    public function testFilterIgnoresDifferentSlug(): void
    {
        list($productInfo) = $this->makeProductInfo();

        Functions\expect('plugin_basename')
            ->once()
            ->with($this->pluginFile)
            ->andReturn('my-plugin/my-plugin.php');

        $args = new stdClass();
        $args->slug = 'other-plugin';

        $result = $productInfo->filterPluginsApi(false, 'plugin_information', $args);
        $this->assertFalse($result);
    }

    public function testFilterReturnsStdClassWithProperties(): void
    {
        list($productInfo, $httpClient) = $this->makeProductInfo();

        $apiResponse = array(
            'name'          => 'My Plugin',
            'version'       => '2.0.0',
            'author'        => 'Test Author',
            'homepage'      => 'https://example.com',
            'download_link' => 'https://store.example.com/download/2.0.0',
            'last_updated'  => '2024-01-15',
            'requires'      => '5.8',
            'tested'        => '6.4',
            'requires_php'  => '7.4',
            'sections'      => array(
                'description' => 'A test plugin.',
                'changelog'   => 'v2.0.0: New features.',
            ),
            'banners'       => array(
                'low'  => 'https://example.com/banner-772x250.png',
                'high' => 'https://example.com/banner-1544x500.png',
            ),
        );

        Functions\expect('plugin_basename')
            ->once()
            ->with($this->pluginFile)
            ->andReturn('my-plugin/my-plugin.php');

        $httpClient->expects($this->once())
            ->method('get')
            ->willReturn($apiResponse);

        $args = new stdClass();
        $args->slug = 'my-plugin';

        $result = $productInfo->filterPluginsApi(false, 'plugin_information', $args);

        $this->assertInstanceOf(stdClass::class, $result);
        $this->assertSame('My Plugin', $result->name);
        $this->assertSame('my-plugin', $result->slug);
        $this->assertSame('2.0.0', $result->version);
        $this->assertSame('Test Author', $result->author);
        $this->assertSame('https://example.com', $result->homepage);
        $this->assertSame('https://store.example.com/download/2.0.0', $result->download_link);
        $this->assertSame('2024-01-15', $result->last_updated);
        $this->assertSame('5.8', $result->requires);
        $this->assertSame('6.4', $result->tested);
        $this->assertSame('7.4', $result->requires_php);
        $this->assertSame($apiResponse['sections'], $result->sections);
        $this->assertSame($apiResponse['banners'], $result->banners);
    }

    public function testFilterReturnsOriginalResultWhenApiReturnsNull(): void
    {
        list($productInfo, $httpClient) = $this->makeProductInfo();

        Functions\expect('plugin_basename')
            ->once()
            ->with($this->pluginFile)
            ->andReturn('my-plugin/my-plugin.php');

        $httpClient->expects($this->once())
            ->method('get')
            ->willReturn(null);

        $args = new stdClass();
        $args->slug = 'my-plugin';

        $result = $productInfo->filterPluginsApi(false, 'plugin_information', $args);
        $this->assertFalse($result);
    }

    public function testFilterAppliesDefaultsForMissingFields(): void
    {
        list($productInfo, $httpClient) = $this->makeProductInfo();

        Functions\expect('plugin_basename')
            ->once()
            ->with($this->pluginFile)
            ->andReturn('my-plugin/my-plugin.php');

        $httpClient->expects($this->once())
            ->method('get')
            ->willReturn(array('name' => 'Minimal Plugin'));

        $args = new stdClass();
        $args->slug = 'my-plugin';

        $result = $productInfo->filterPluginsApi(false, 'plugin_information', $args);

        $this->assertInstanceOf(stdClass::class, $result);
        $this->assertSame('Minimal Plugin', $result->name);
        $this->assertSame('', $result->version);
        $this->assertSame('', $result->author);
        $this->assertSame('', $result->homepage);
        $this->assertNull($result->download_link);
        $this->assertSame('', $result->last_updated);
        $this->assertSame('', $result->requires);
        $this->assertSame('', $result->tested);
        $this->assertSame('', $result->requires_php);
        $this->assertSame(array('description' => '', 'changelog' => ''), $result->sections);
        $this->assertSame(array(), $result->banners);
    }
}
