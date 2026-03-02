<?php

namespace Lapisense\WordPressClient;

use Lapisense\PHPClient\ApiClient;
use Lapisense\PHPClient\StorageInterface;

/**
 * Base class for plugin and theme update integration.
 *
 * Hooks into update_{plugins|themes}_{$hostname} filters with 6-hour transient cache.
 * PHP 7.4 compatible.
 *
 * @SuppressWarnings(PHPMD.UnusedFormalParameter) $locales is part of the WordPress filter signature.
 */
abstract class AbstractUpdater
{
    /** @var array<string, mixed> */
    protected $config;

    /** @var ApiClient */
    protected $apiClient;

    /** @var StorageInterface */
    protected $storage;

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

        add_filter($this->getFilterName() . $hostname, array($this, 'checkUpdate'), 10, 4);
    }

    /**
     * @param array|false $update
     * @param array<string, mixed> $data
     * @param string $identifier Plugin file or theme stylesheet.
     * @param string[] $locales
     * @return array|false
     */
    public function checkUpdate($update, $data, $identifier, $locales)
    {
        if (!$this->isOurProduct($identifier)) {
            return $update;
        }

        $currentVersion = isset($data['Version']) ? $data['Version'] : '0.0.0';

        // Check transient cache.
        $cacheKey = $this->getCachePrefix() . md5($this->config['product_uuid'] . $this->config['store_url']);
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
     * @return string Filter name prefix, e.g. 'update_plugins_' or 'update_themes_'.
     */
    abstract protected function getFilterName();

    /**
     * @return string Transient cache key prefix.
     */
    abstract protected function getCachePrefix();

    /**
     * @param string $identifier Plugin file or theme stylesheet.
     * @return bool
     */
    abstract protected function isOurProduct($identifier);

    /**
     * @param array<string, mixed> $result API response.
     * @return array<string, mixed> WordPress-format update array.
     */
    abstract protected function buildUpdateArray($result);

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
     * @return string
     */
    private function getHostname()
    {
        $storeUrl = isset($this->config['store_url']) ? $this->config['store_url'] : '';
        $parsed = wp_parse_url($storeUrl);
        return isset($parsed['host']) ? $parsed['host'] : '';
    }
}
