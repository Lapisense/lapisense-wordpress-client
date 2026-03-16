<?php

namespace Lapisense\WordPressClient;

use Lapisense\PHPClient\ApiClient;

/**
 * Developer-facing entry point for the Lapisense WordPress client.
 *
 * Implements [TS 10.3]. Configuration via static init() method.
 * PHP 7.4 compatible.
 *
 * Usage:
 *   $lapisense = \Lapisense\WordPressClient\Client::init([
 *       'store_url'    => 'https://store.example.com',
 *       'product_uuid' => 'f47ac10b-58cc-4372-a567-0e02b2c3d479',
 *       'product_type' => 'plugin',
 *       'file'         => __FILE__,
 *   ]);
 */
final class Client
{
    /** @var ApiClient */
    private $apiClient;

    /** @var OptionStorage */
    private $storage;

    /** @var array<string, mixed> */
    private $config;

    /**
     * @param array<string, mixed> $config
     */
    private function __construct($config)
    {
        $this->config = $config;
        $httpClient = new WpHttpClient();
        $this->apiClient = new ApiClient($config['store_url'], $config['product_uuid'], $httpClient);
        $this->storage = new OptionStorage($config['product_uuid']);
    }

    /**
     * Initialize the client and register update hooks.
     *
     * @param array<string, mixed> $config {
     *     @type string $store_url    Base URL of the WooCommerce store.
     *     @type string $product_uuid Product UUID.
     *     @type string $product_type 'plugin' or 'theme'.
     *     @type string $file         Main plugin/theme file path.
     * }
     * @return self
     */
    public static function init($config)
    {
        $instance = new self($config);
        $instance->registerHooks();

        return $instance;
    }

    /**
     * Activate a license key.
     *
     * @param string $licenseKey
     * @return array<string, mixed>
     */
    public function activate($licenseKey)
    {
        $result = $this->apiClient->activate($licenseKey, home_url(), 'site_url');

        if ($result && !empty($result['success']) && !empty($result['activation']['uuid'])) {
            $this->storage->store($licenseKey, $result['activation']['uuid']);
        }

        return $result ?: array();
    }

    /**
     * Deactivate the current activation.
     *
     * @return bool
     */
    public function deactivate()
    {
        $activationUuid = $this->storage->getActivationUuid();
        if (!$activationUuid) {
            return false;
        }

        $result = $this->apiClient->deactivate($activationUuid);

        if ($result && !empty($result['success'])) {
            $this->storage->clear();
            return true;
        }

        return false;
    }

    /**
     * Check if a license is activated.
     *
     * @return bool
     */
    public function isActivated()
    {
        return $this->storage->getActivationUuid() !== null;
    }

    /**
     * Get the current activation status.
     *
     * @return array<string, mixed>
     */
    public function getActivationStatus()
    {
        return array(
            'activated'       => $this->storage->getActivationUuid() !== null,
            'activation_uuid' => $this->storage->getActivationUuid(),
            'license_key'     => $this->storage->getLicenseKey(),
        );
    }

    /**
     * @return void
     *
     * @SuppressWarnings(PHPMD.UnusedPrivateMethod) Called from static init().
     */
    private function registerHooks()
    {
        $productType = isset($this->config['product_type']) ? $this->config['product_type'] : 'plugin';

        if ($productType === 'theme') {
            $updater = new ThemeUpdater($this->config, $this->apiClient, $this->storage);
            $updater->register();
            return;
        }

        $updater = new PluginUpdater($this->config, $this->apiClient, $this->storage);
        $updater->register();

        $productInfo = new ProductInfo($this->config, $this->apiClient);
        $productInfo->register();
    }
}
