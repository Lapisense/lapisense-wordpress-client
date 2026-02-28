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
    public function get($key)
    {
        $value = get_option($this->prefix . $key, null);
        return $value !== null ? (string) $value : null;
    }

    /**
     * @param string $key
     * @param string $value
     * @return void
     */
    public function set($key, $value)
    {
        update_option($this->prefix . $key, $value, false);
    }

    /**
     * @param string $key
     * @return void
     */
    public function delete($key)
    {
        delete_option($this->prefix . $key);
    }
}
