<?php

namespace Lapisense\WordPressClient;

use Lapisense\PHPClient\ApiClient;

/**
 * Plugin information modal integration via plugins_api filter.
 *
 * Implements [TS 10.7]. Hooks into the "View Details" modal.
 * PHP 7.4 compatible.
 */
final class ProductInfo
{
    /** @var array<string, mixed> */
    private $config;

    /** @var ApiClient */
    private $apiClient;

    /**
     * @param array<string, mixed> $config
     * @param ApiClient $apiClient
     */
    public function __construct($config, ApiClient $apiClient)
    {
        $this->config = $config;
        $this->apiClient = $apiClient;
    }

    /**
     * @return void
     */
    public function register()
    {
        add_filter('plugins_api', array($this, 'filterPluginsApi'), 10, 3);
    }

    /**
     * @param false|object|\stdClass $result
     * @param string $action
     * @param object $args
     * @return false|object|\stdClass
     */
    public function filterPluginsApi($result, $action, $args)
    {
        if ($action !== 'plugin_information') {
            return $result;
        }

        $pluginFile = isset($this->config['file']) ? $this->config['file'] : '';
        $slug = dirname(plugin_basename($pluginFile));

        if (!isset($args->slug) || $args->slug !== $slug) {
            return $result;
        }

        $info = $this->apiClient->getProductInfo();
        if (!$info) {
            return $result;
        }

        $response = new \stdClass();
        $response->name = isset($info['name']) ? $info['name'] : '';
        $response->slug = $slug;
        $response->version = isset($info['version']) ? $info['version'] : '';
        $response->author = '';
        $response->homepage = isset($info['homepage']) ? $info['homepage'] : '';
        $response->requires = isset($info['requires_wp']) ? $info['requires_wp'] : '';
        $response->tested = isset($info['tested_wp']) ? $info['tested_wp'] : '';
        $response->requires_php = isset($info['requires_php']) ? $info['requires_php'] : '';

        $response->sections = array(
            'description' => isset($info['description']) ? $info['description'] : '',
            'changelog'   => isset($info['changelog']) ? $info['changelog'] : '',
        );

        return $response;
    }
}
