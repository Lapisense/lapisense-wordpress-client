<?php

namespace Lapisense\WordPressClient;

use Lapisense\PHPClient\ApiClient;
use stdClass;

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
     * @param false|object|stdClass $result
     * @param string $action
     * @param object $args
     * @return false|object|stdClass
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

        return $this->buildPluginInfoResponse($info, $slug);
    }

    /**
     * @param array<string, mixed> $info
     * @param string $slug
     * @return stdClass
     */
    private function buildPluginInfoResponse($info, $slug)
    {
        $defaults = array(
            'name'         => '',
            'version'      => '',
            'homepage'     => '',
            'requires_wp'  => '',
            'tested_wp'    => '',
            'requires_php' => '',
            'description'  => '',
            'changelog'    => '',
        );
        $info = array_merge($defaults, $info);

        $response = new stdClass();
        $response->name = $info['name'];
        $response->slug = $slug;
        $response->version = $info['version'];
        $response->author = '';
        $response->homepage = $info['homepage'];
        $response->requires = $info['requires_wp'];
        $response->tested = $info['tested_wp'];
        $response->requires_php = $info['requires_php'];

        $response->sections = array(
            'description' => $info['description'],
            'changelog'   => $info['changelog'],
        );

        return $response;
    }
}
