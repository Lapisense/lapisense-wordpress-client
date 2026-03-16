<?php

namespace Lapisense\WordPressClient;

/**
 * Theme update integration via update_themes_{$hostname} filter.
 *
 * Implements [FS 6.2.3]. Theme-format response.
 * PHP 7.4 compatible. Requires WP 6.1+ for update_themes_{$hostname}.
 */
final class ThemeUpdater extends AbstractUpdater
{
    /**
     * @return string
     */
    protected function getFilterName()
    {
        return 'update_themes_';
    }

    /**
     * @return string
     */
    protected function getCachePrefix()
    {
        return 'lapisense_theme_update_';
    }

    /**
     * @param string $identifier Theme stylesheet.
     * @return bool
     */
    protected function isOurProduct($identifier)
    {
        $themeDir = isset($this->config['file']) ? dirname($this->config['file']) : '';

        return $identifier === basename($themeDir);
    }

    /**
     * @param array<string, mixed> $result
     * @return array<string, mixed>
     */
    protected function buildUpdateArray($result)
    {
        $themeDir = isset($this->config['file']) ? dirname($this->config['file']) : '';
        $requirements = isset($result['requirements']) ? $result['requirements'] : array();

        return array(
            'theme'        => basename($themeDir),
            'new_version'  => isset($result['version']) ? $result['version'] : '',
            'url'          => isset($result['homepage']) ? $result['homepage'] : '',
            'package'      => isset($result['package_url']) ? $result['package_url'] : '',
            'requires'     => isset($requirements['requires_wp']) ? $requirements['requires_wp'] : '',
            'requires_php' => isset($requirements['requires_php']) ? $requirements['requires_php'] : '',
        );
    }
}
