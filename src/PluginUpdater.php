<?php

namespace Lapisense\WordPressClient;

/**
 * Plugin update integration via update_plugins_{$hostname} filter.
 *
 * Implements [TS 10.6]. Requires WP 5.8+ for the hostname-specific filter.
 * PHP 7.4 compatible.
 */
final class PluginUpdater extends AbstractUpdater
{
    /**
     * @return string
     */
    protected function getFilterName()
    {
        return 'update_plugins_';
    }

    /**
     * @return string
     */
    protected function getCachePrefix()
    {
        return 'lapisense_update_';
    }

    /**
     * @param string $identifier Plugin file path.
     * @return bool
     */
    protected function isOurProduct($identifier)
    {
        $ourFile = isset($this->config['file']) ? $this->config['file'] : '';
        if (empty($ourFile)) {
            return false;
        }

        return $identifier === plugin_basename($ourFile);
    }

    /**
     * @param array<string, mixed> $result
     * @return array<string, mixed>
     */
    protected function buildUpdateArray($result)
    {
        $pluginFile = plugin_basename($this->config['file']);
        $requirements = isset($result['requirements']) ? $result['requirements'] : array();

        return array(
            'slug'         => dirname($pluginFile),
            'version'      => isset($result['version']) ? $result['version'] : '',
            'url'          => isset($result['homepage']) ? $result['homepage'] : '',
            'package'      => isset($result['package_url']) ? $result['package_url'] : '',
            'tested'       => isset($requirements['tested_wp']) ? $requirements['tested_wp'] : '',
            'requires_php' => isset($requirements['requires_php']) ? $requirements['requires_php'] : '',
            'requires'     => isset($requirements['requires_wp']) ? $requirements['requires_wp'] : '',
        );
    }
}
