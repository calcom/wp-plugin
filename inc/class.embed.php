<?php

namespace CalCom;

defined('ABSPATH') || exit;

class Embed
{
    public function hooks()
    {
        add_shortcode('cal', array($this, 'shortcode'));
    }

    /**
     * Handle the [cal] shortcode
     * 
     * @param array $atts Shortcode attributes
     * @return string HTML output for embed
     */
    public function shortcode($atts)
    {
        $atts = $this->prepare_atts($atts);

        if (empty($atts['url'])) {
            return '';
        }

        return $this->embed($atts);
    }

    /**
     * Render the embed output and enqueue assets
     * 
     * @param array $atts Prepared shortcode attributes
     * @return string HTML output
     */
    private function embed($atts)
    {
        $this->enqueue_assets();

        $widget_data = $this->prepare_widget_data($atts);

        if (!empty($widget_data)) {
            wp_add_inline_script(
                'calcom-embed-js',
                'window.calcomData = ' . $widget_data . ';',
                'before'
            );
        }

        $type = (int) $atts['type'];

        switch ($type) {

            case 2:
                return '<span id="calcom-embed-link">' . esc_html($atts['text']) . '</span>';
            case 3:
                return '';
            default:
                return '<div id="calcom-embed"></div>';
        }
    }

    /**
     * Enqueue JS and CSS assets
     */
    private function enqueue_assets()
    {
        wp_enqueue_script('calcom-embed-js');
        wp_enqueue_style('calcom-embed-css');
    }

    /**
     * Prepare the data object for JS embed handler
     * 
     * @param array $atts Prepared shortcode attributes
     * @return string JSON encoded widget data
     */
    private function prepare_widget_data($atts)
    {
        $data = array(
            'type' => (int) $atts['type'],
            'config' => array(
                'calLink' => $atts['url'],
            ),
            'customCalUrl' => $atts['customCalInstance'],
        );

        return function_exists('wp_json_encode') ? wp_json_encode($data) : json_encode($data);
    }

    /**
     * Prepare and normalize shortcode attributes
     * 
     * @param array $atts Raw shortcode attributes
     * @return array Normalized attributes
     */
    private function prepare_atts($atts)
    {
        if (!is_array($atts)) {
            $atts = array();
        }

        $atts = shortcode_atts(array(
            'url' => '',
            'type' => 1,
            'text' => 'Book me',
            'utm' => '',
            'prefill' => 'false',
        ), $atts);

        $normalized = $this->normalize_url($atts['url']);
        $query_params = $this->build_query_params($atts);

        if (!empty($query_params)) {
            $normalized['url'] .= '?' . http_build_query($query_params, '', '&');
        }

        return array(
            'url' => $normalized['url'],
            'type' => (int) $atts['type'],
            'text' => sanitize_text_field($atts['text']),
            'customCalInstance' => esc_url_raw($normalized['instance']),
            'prefill' => ($atts['prefill'] === 'true'),
            'utm' => $this->parse_utm($atts['utm']),
        );
    }

    /**
     * Normalize a URL and detect self-hosted Cal.com instances
     * 
     * @param string $url Raw URL
     * @return array Array with url and instance (if self-hosted)
     */
    private function normalize_url($url)
    {
        $url = trim($url);

        if (empty($url)) {
            return array('url' => '', 'instance' => '');
        }

        // for default URL, only keep the path
        if (strpos($url, 'https://cal.com/') === 0) {

            $parsed = wp_parse_url($url);
            $path = isset($parsed['path']) ? trim($parsed['path'], '/') : '';

            return array(
                'url' => '/' . $path,
                'instance' => ''
            );
        }

        // full URL shows self-hosted instance
        if (preg_match('#^https?://#i', $url)) {

            $parts = wp_parse_url($url);

            if (!empty($parts['host'])) {

                $scheme = isset($parts['scheme']) ? $parts['scheme'] : 'https';
                $path = isset($parts['path']) ? trim($parts['path'], '/') : '';

                return array(
                    'url' => '/' . $path,
                    'instance' => $scheme . '://' . $parts['host']
                );
            }
        }

        return array(
            'url' => '/' . trim($url, '/'),
            'instance' => ''
        );
    }

    /**
     * Build query parameters to prefill and add params
     * 
     * @param array $atts Shortcode attributes
     * @return array Query parameters
     */
    private function build_query_params($atts)
    {
        $query_params = array();

        // prefill logged in user info
        if ($atts['prefill'] === 'true' && is_user_logged_in()) {

            $user = wp_get_current_user();

            if (!empty($user->display_name)) {
                $query_params['name'] = sanitize_text_field($user->display_name);
            }

            if (!empty($user->user_email)) {
                $query_params['email'] = sanitize_email($user->user_email);
            }
        }

        // merge UTM parameters if they exists
        $utm = $this->parse_utm($atts['utm']);

        if (!empty($utm)) {
            $query_params = array_merge($query_params, $utm);
        }

        return $query_params;
    }

    /**
     * Parse a UTM string like "source:website,medium:cpc"
     * 
     * @param string $utm_string Comma separated key:value pairs
     * @return array An array of UTM parameters
     */
    private function parse_utm($utm_string)
    {
        $utm = array();

        if (empty($utm_string)) {
            return $utm;
        }

        $pairs = explode(',', $utm_string);

        foreach ($pairs as $pair) {
            $parts = explode(':', $pair);

            if (count($parts) !== 2) {
                continue;
            }

            $key = sanitize_key(trim($parts[0]));
            $value = sanitize_text_field(trim($parts[1]));

            if ($key && $value) {
                $utm['utm_' . $key] = $value;
            }
        }

        return $utm;
    }
}
