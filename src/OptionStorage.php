<?php

namespace Lapisense\WordPressClient;

use Lapisense\PHPClient\StorageInterface;

/**
 * WordPress options-based storage with lapisense_{product_uuid}_ prefix.
 *
 * Implements [TS 10.5]. Uses autoload=false.
 * PHP 7.4 compatible.
 */
final class OptionStorage implements StorageInterface
{
    /** @var string */
    private $prefix;

    /**
     * @param string $productUuid
     */
    public function __construct($productUuid)
    {
        $this->prefix = 'lapisense_' . str_replace('-', '_', $productUuid) . '_';
    }

    /**
     * @param string $key
     * @return string|null
     */
    public function get(string $key)
    {
        $value = get_option($this->prefix . $key, null);
        return $value !== null ? (string) $value : null;
    }

    /**
     * @param string $key
     * @param string $value
     * @return void
     */
    public function set(string $key, string $value)
    {
        update_option($this->prefix . $key, $value, false);
    }

    /**
     * @param string $key
     * @return void
     */
    public function delete(string $key)
    {
        delete_option($this->prefix . $key);
    }

    /**
     * @return string|null
     */
    public function getLicenseKey()
    {
        return $this->get('license_key');
    }

    /**
     * @return string|null
     */
    public function getActivationUuid()
    {
        return $this->get('activation_uuid');
    }

    /**
     * @param string $licenseKey
     * @param string $activationUuid
     * @return void
     */
    public function store($licenseKey, $activationUuid)
    {
        $this->set('license_key', $licenseKey);
        $this->set('activation_uuid', $activationUuid);
    }

    /**
     * @return void
     */
    public function clear()
    {
        $this->delete('license_key');
        $this->delete('activation_uuid');
    }
}
