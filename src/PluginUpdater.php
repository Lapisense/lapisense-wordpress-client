<?php

namespace Lapisense\WordPressClient;

use Lapisense\PHPClient\ApiClient;
use Lapisense\PHPClient\StorageInterface;

/**
 * Plugin update integration via update_plugins_{$hostname} filter.
 *
 * Implements [TS 10.6]. Requires WP 5.8+ for the hostname-specific filter.
 * 6-hour transient cache [FS 6.2.4].
 * PHP 7.4 compatible.
 */
final class PluginUpdater
{
    /** @var array<string, mixed> */
    private $config;

    /** @var ApiClient */
    private $apiClient;

    /** @var StorageInterface */
    private $storage;

    /**
     * @param array<string, mixed> $config
     * @param ApiClient $apiClient
     * @param StorageInterface $storage
     */
    public function __construct($config, ApiClient $apiClient, StorageInterface $storage)
    {
        $this->config = $config;
        $this->apiClient = $apiClient;
        $this->storage = $storage;
    }

    /**
     * @return void
     */
    public function register()
    {
        $hostname = $this->getHostname();
        if (!$hostname) {
            return;
        }

        add_filter('update_plugins_' . $hostname, array($this, 'checkUpdate'), 10, 4);
    }

    /**
     * @param array|false $update
     * @param array<string, mixed> $pluginData
     * @param string $pluginFile
     * @param string[] $locales
     * @return array|false
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter) $locales is part of the WordPress filter signature.
     */
    public function checkUpdate($update, $pluginData, $pluginFile, $locales)
    {
        // Only handle our plugin file.
        $ourFile = isset($this->config['file']) ? $this->config['file'] : '';
        if (empty($ourFile)) {
            return $update;
        }

        // Derive plugin basename.
        $pluginBasename = plugin_basename($ourFile);
        if ($pluginFile !== $pluginBasename) {
            return $update;
        }

        $currentVersion = isset($pluginData['Version']) ? $pluginData['Version'] : '0.0.0';

        // Check transient cache.
        $cacheKey = 'lapisense_update_' . md5($this->config['product_uuid'] . $this->config['store_url']);
        $cached = get_transient($cacheKey);
        if ($cached !== false) {
            return $cached ?: false;
        }

        $result = $this->fetchUpdate($currentVersion);

        // Cache for 6 hours.
        set_transient($cacheKey, $result ?: '', 6 * HOUR_IN_SECONDS);

        return $result ?: false;
    }

    /**
     * @param string $currentVersion
     * @return array<string, mixed>|null
     */
    private function fetchUpdate($currentVersion)
    {
        $result = $this->fetchApiResult($currentVersion);

        if (!$result || empty($result['update_available'])) {
            return null;
        }

        return $this->buildUpdateArray($result);
    }

    /**
     * @param string $currentVersion
     * @return array<string, mixed>|null
     */
    private function fetchApiResult($currentVersion)
    {
        if (!empty($this->config['free'])) {
            return $this->apiClient->checkFreeUpdate($currentVersion);
        }

        $activationUuid = $this->storage->get('activation_uuid');
        if (!$activationUuid) {
            return null;
        }

        return $this->apiClient->checkUpdate($activationUuid, $currentVersion);
    }

    /**
     * @param array<string, mixed> $result
     * @return array<string, mixed>
     */
    private function buildUpdateArray($result)
    {
        $pluginFile = plugin_basename($this->config['file']);

        return array(
            'slug'         => dirname($pluginFile),
            'version'      => isset($result['version']) ? $result['version'] : '',
            'url'          => isset($result['homepage']) ? $result['homepage'] : '',
            'package'      => isset($result['package_url']) ? $result['package_url'] : '',
            'tested'       => isset($result['tested_wp']) ? $result['tested_wp'] : '',
            'requires_php' => isset($result['requires_php']) ? $result['requires_php'] : '',
            'requires'     => isset($result['requires_wp']) ? $result['requires_wp'] : '',
        );
    }

    /**
     * @return string
     */
    private function getHostname()
    {
        $storeUrl = isset($this->config['store_url']) ? $this->config['store_url'] : '';
        $parsed = wp_parse_url($storeUrl);
        return isset($parsed['host']) ? $parsed['host'] : '';
    }
}
