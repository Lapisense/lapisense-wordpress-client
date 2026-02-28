<?php

namespace Lapisense\WordPressClient;

use Lapisense\PHPClient\ApiClient;
use Lapisense\PHPClient\StorageInterface;

/**
 * Theme update integration via update_themes_{$hostname} filter.
 *
 * Implements [FS 6.2.3]. Theme-format response.
 * PHP 7.4 compatible. Requires WP 6.1+ for update_themes_{$hostname}.
 */
final class ThemeUpdater
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

        add_filter('update_themes_' . $hostname, array($this, 'checkUpdate'), 10, 4);
    }

    /**
     * @param array|false $update
     * @param array<string, mixed> $themeData
     * @param string $themeStylesheet
     * @param string[] $locales
     * @return array|false
     */
    public function checkUpdate($update, $themeData, $themeStylesheet, $locales)
    {
        // Derive theme slug from file path.
        $themeDir = isset($this->config['file']) ? dirname($this->config['file']) : '';
        $themeSlug = basename($themeDir);

        if ($themeStylesheet !== $themeSlug) {
            return $update;
        }

        $currentVersion = isset($themeData['Version']) ? $themeData['Version'] : '0.0.0';

        // Check transient cache.
        $cacheKey = 'lapisense_theme_update_' . md5($this->config['product_uuid'] . $this->config['store_url']);
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
        $isFree = !empty($this->config['free']);

        if ($isFree) {
            $result = $this->apiClient->checkFreeUpdate($currentVersion);
        } else {
            $activationUuid = $this->storage->get('activation_uuid');
            if (!$activationUuid) {
                return null;
            }
            $result = $this->apiClient->checkUpdate($activationUuid, $currentVersion);
        }

        if (!$result || empty($result['update_available'])) {
            return null;
        }

        $themeDir = isset($this->config['file']) ? dirname($this->config['file']) : '';
        $themeSlug = basename($themeDir);

        return array(
            'theme'        => $themeSlug,
            'new_version'  => isset($result['version']) ? $result['version'] : '',
            'url'          => isset($result['homepage']) ? $result['homepage'] : '',
            'package'      => isset($result['package_url']) ? $result['package_url'] : '',
            'requires'     => isset($result['requires_wp']) ? $result['requires_wp'] : '',
            'requires_php' => isset($result['requires_php']) ? $result['requires_php'] : '',
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
