<?php

namespace CalCom;

defined('ABSPATH') || exit;

/**
 * Extends core Embed functionality while allowing
 * custom UI/config overrides.
 */
class CustomEmbed extends Embed
{
    const ELEMENT_ID = 'calcom-custom-embed';

    public function hooks()
    {
        add_shortcode('cal_custom', array($this, 'shortcode'));
    }

    /**
     * Handle [cal_custom] shortcode
     *
     * @param array $atts Shortcode attributes
     * @return string
     */
    public function shortcode($atts)
    {
        $atts = $this->prepare_atts($atts);

        if (empty($atts['url'])) {
            return '';
        }

        // load needed assets
        $this->enqueue_assets();
        wp_enqueue_script('calcom-custom-embed-js');

        // pass data to JS
        $this->enqueue_custom_data($atts);

        return $this->render($atts);
    }

    /**
     * Output embed HTML based on type
     *
     * @param array $atts
     * @return string
     */
    protected function render($atts)
    {
        $type = (int) $atts['type'];

        switch ($type) {
            case 2:
                return '<span id="' . esc_attr(self::ELEMENT_ID) . '" class="calcom-embed-link">' . esc_html($atts['text']) . '</span>';

            case 3:
                return ''; // floating button handled via JS

            default:
                return '<div id="' . esc_attr(self::ELEMENT_ID) . '"></div>';
        }
    }

    /**
     * Pass prepared data for frontend
     *
     * @param array $atts
     */
    protected function enqueue_custom_data($atts)
    {
        $data = array(
            'elementId' => self::ELEMENT_ID,
            'type' => (int) $atts['type'],
            'calLink' => $atts['url'],
            'config' => $atts['config'],
            'ui' => $atts['ui'],
            'customCalUrl' => $atts['customCalInstance'],
        );

        wp_add_inline_script(
            'calcom-custom-embed-js',
            'window.calCustomData = ' . wp_json_encode($data) . ';',
            'before'
        );
    }

    /**
     * Prepare attributes
     *
     * @param array $atts
     * @return array
     */
    protected function prepare_atts($atts)
    {
        $atts = parent::prepare_atts($atts);

        $atts['ui'] = $this->parse_json_attr($atts['ui'], $this->default_ui());
        $atts['config'] = $this->parse_json_attr($atts['config'], array());

        return $atts;
    }

    /**
     * Safely parse JSON attribute
     *
     * @param mixed $value
     * @param array $default
     * @return array
     */
    protected function parse_json_attr($value, $default = array())
    {
        if (is_array($value)) {
            return $value;
        }

        if (empty($value)) {
            return $default;
        }

        $decoded = json_decode($value, true);

        return (json_last_error() === JSON_ERROR_NONE && is_array($decoded))
            ? array_merge($default, $decoded)
            : $default;
    }

    /**
     * Default UI config
     *
     * @return array
     */
    protected function default_ui()
    {
        return array(
            'theme' => 'light',
            'cssVarsPerTheme' => array(
                'light' => array(
                    'cal-brand' => '#000000',
                ),
            ),
            'hideEventTypeDetails' => false,
            'layout' => 'month_view',
        );
    }
}
