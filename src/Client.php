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
 *       'free'         => true,
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
     *     @type bool   $free         Whether this is a free product (default false).
     * }
     * @return self
     */
    public static function init($config)
    {
        $config = array_merge(array(
            'free' => false,
        ), $config);

        $instance = new self($config);
        $instance->registerHooks();

        return $instance;
    }

    /**
     * Activate a license key.
     *
     * @param string $licenseKey
     * @return array<string, mixed>
     * @throws \LogicException If product is free.
     */
    public function activate($licenseKey)
    {
        $this->requireLicensed();

        $result = $this->apiClient->activate($licenseKey, home_url());

        if ($result && !empty($result['success'])) {
            $this->storage->set('license_key', $licenseKey);
            if (!empty($result['activation']['uuid'])) {
                $this->storage->set('activation_uuid', $result['activation']['uuid']);
            }
        }

        return $result ?: array();
    }

    /**
     * Deactivate the current activation.
     *
     * @return bool
     * @throws \LogicException If product is free.
     */
    public function deactivate()
    {
        $this->requireLicensed();

        $activationUuid = $this->storage->get('activation_uuid');
        if (!$activationUuid) {
            return false;
        }

        $result = $this->apiClient->deactivate($activationUuid);

        if ($result && !empty($result['success'])) {
            $this->storage->delete('activation_uuid');
            $this->storage->delete('license_key');
            return true;
        }

        return false;
    }

    /**
     * Check if a license is activated.
     *
     * @return bool
     * @throws \LogicException If product is free.
     */
    public function is_activated()
    {
        $this->requireLicensed();
        return $this->storage->get('activation_uuid') !== null;
    }

    /**
     * Get the current activation status.
     *
     * @return array<string, mixed>
     * @throws \LogicException If product is free.
     */
    public function get_activation_status()
    {
        $this->requireLicensed();

        return array(
            'activated'       => $this->storage->get('activation_uuid') !== null,
            'activation_uuid' => $this->storage->get('activation_uuid'),
            'license_key'     => $this->storage->get('license_key'),
        );
    }

    /**
     * @return void
     */
    private function registerHooks()
    {
        $productType = isset($this->config['product_type']) ? $this->config['product_type'] : 'plugin';

        if ($productType === 'theme') {
            $updater = new ThemeUpdater($this->config, $this->apiClient, $this->storage);
            $updater->register();
        } else {
            $updater = new PluginUpdater($this->config, $this->apiClient, $this->storage);
            $updater->register();

            $productInfo = new ProductInfo($this->config, $this->apiClient);
            $productInfo->register();
        }
    }

    /**
     * @return void
     * @throws \LogicException If product is free.
     */
    private function requireLicensed()
    {
        if (!empty($this->config['free'])) {
            throw new \LogicException('License methods are not available for free products.');
        }
    }
}
