<?php

namespace CalCom\EventTypes;

use CalCom\Credentials;

defined('ABSPATH') || exit;

/**
 * Retrieves and caches Cal.com event types for the admin selector.
 */
class EventTypes
{
    const CACHE_KEY = 'calcom_event_types';
    const CACHE_TTL = 3600;

    private $credentials;

    public function __construct($credentials = null)
    {
        $this->credentials = $credentials !== null ? $credentials : new Credentials();
    }

    public function get($force_bypass_cache = false)
    {
        if (!$force_bypass_cache) {
            $cached = get_transient(self::CACHE_KEY);
            if ($cached !== false) {
                return array(
                    'success' => true,
                    'event_types' => $cached,
                    'error' => null,
                    'cached' => true,
                );
            }
        }

        if (!$this->credentials->exists()) {
            return array(
                'success' => false,
                'event_types' => array(),
                'error' => __('No credentials configured.', 'cal-com'),
                'cached' => false,
            );
        }

        $api = $this->credentials->get_api();
        $result = $api->get_event_types();

        if (!$result['success']) {
            return array(
                'success' => false,
                'event_types' => array(),
                'error' => $result['error'] ?? __('API request failed.', 'cal-com'),
                'cached' => false,
            );
        }

        $normalized = $this->normalize($result['event_types']);
        set_transient(self::CACHE_KEY, $normalized, self::CACHE_TTL);

        return array(
            'success' => true,
            'event_types' => $normalized,
            'error' => null,
            'cached' => false,
        );
    }

    public function refresh()
    {
        $this->clear_cache();
        return $this->get();
    }

    public function clear_cache()
    {
        delete_transient(self::CACHE_KEY);
    }

    private function normalize(array $raw_event_types)
    {
        $normalized = array();

        foreach ($raw_event_types as $event_type) {
            if (!is_array($event_type) || !isset($event_type['id'])) {
                continue;
            }

            $normalized[] = array(
                'id' => (int) $event_type['id'],
                'title' => isset($event_type['title']) ? (string) $event_type['title'] : '',
                'slug' => isset($event_type['slug']) ? (string) $event_type['slug'] : '',
                'hidden' => isset($event_type['hidden']) ? (bool) $event_type['hidden'] : false,
                'booking_url' => isset($event_type['bookingUrl']) ? (string) $event_type['bookingUrl'] : '',
            );
        }

        return $normalized;
    }
}
