<?php

namespace Lapisense\WordPressClient;

use Lapisense\PHPClient\HttpClientInterface;

/**
 * WordPress HTTP client using wp_remote_* functions.
 *
 * Implements [TS 10.4]. Returns null on error or non-2xx status.
 * PHP 7.4 compatible.
 */
final class WpHttpClient implements HttpClientInterface
{
    /**
     * @param string $url
     * @param array<string, string> $params
     * @return array<string, mixed>|null
     */
    public function get(string $url, $params = array())
    {
        if (!empty($params)) {
            $url = add_query_arg($params, $url);
        }

        $response = wp_remote_get($url, array(
            'timeout' => 15,
        ));

        return $this->parseResponse($response);
    }

    /**
     * @param string $url
     * @param array<string, mixed> $body
     * @return array<string, mixed>|null
     */
    public function post(string $url, $body)
    {
        $response = wp_remote_post($url, array(
            'timeout' => 15,
            'body'    => (string) wp_json_encode($body),
            'headers' => array(
                'Content-Type' => 'application/json',
            ),
        ));

        return $this->parseResponse($response);
    }

    /**
     * @param string $url
     * @param array<string, string> $params
     * @return array<string, mixed>|null
     */
    public function delete(string $url, $params = array())
    {
        if (!empty($params)) {
            $url = add_query_arg($params, $url);
        }

        $response = wp_remote_request($url, array(
            'method'  => 'DELETE',
            'timeout' => 15,
        ));

        return $this->parseResponse($response);
    }

    /**
     * @param array<string, mixed>|\WP_Error $response
     * @return array<string, mixed>|null
     */
    private function parseResponse($response)
    {
        if (is_wp_error($response)) {
            return null;
        }

        $statusCode = (int) wp_remote_retrieve_response_code($response);

        if ($statusCode < 200 || $statusCode >= 300) {
            return null;
        }

        $body = wp_remote_retrieve_body($response);
        $decoded = json_decode($body, true);

        return is_array($decoded) ? $decoded : null;
    }
}
