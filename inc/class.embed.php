<?php

namespace CalCom;

defined('ABSPATH') || exit;

class Embed
{
    public function hooks(): void
    {
        add_shortcode('cal', [$this, 'shortcode']);
    }

    public function shortcode($atts): string
    {
        $atts = $this->prepare_atts($atts);
        return $this->embed($atts);
    }

    /**
     * Embeds Cal.com booking calendar
     * 
     * @param $atts Embed attributes
     * @return string
     */
    private function embed($atts): string
    {

        if ($atts) {

            $this->load_embed_scripts();

            switch ($atts['type']) {
                case 2:
                    $output = '<span id="calcom-embed-link" data-cal-link="' . esc_attr($atts['url']) . '">' . esc_attr($atts['text']) . '</span>';
                    $output .= '<script>var customCalUrl = "' . $atts['customCalInstance'] . '";</script>';
                    break;
                case 3:
                    $output = $this->get_floating_popup_embed_script($atts['url'], $atts['text'], $atts['customCalInstance']);
                    break;
                default:
                    $output = '<div id="calcom-embed"></div>';
                    $output .= $this->get_inline_embed_script($atts['url'], $atts['customCalInstance']);
            }

            return $output;
        }

        return '';
    }

    /**
     * Adds inline embed JS
     * 
     * @param $url Booking link
     * @param $custom_cal_url Custom cal.com URL (if self-hosted instance is used)
     * @return string
     */
    public function get_inline_embed_script($url, $custom_cal_url): string
    {
        $script = '<script>
            addEventListener("DOMContentLoaded", (event) => {
                var customCalUrl = "' . $custom_cal_url . '";
                const selector = document.getElementById("calcom-embed");
                Cal("inline", {
                    elementOrSelector: selector,
                    calLink: "' . $url . '"
                });
            });
        </script>';

        return $script;
    }

    /**
     * Adds floating-popup embed JS
     * 
     * @param $url Booking link
     * @param $text Button text
     * @param $custom_cal_url Custom cal.com URL (if self-hosted instance is used)
     * @return string
     */
    public function get_floating_popup_embed_script($url, $text, $custom_cal_url): string
    {
        $button_text = strlen($text) > 0 ? '"buttonText":"' . $text . '"' : '';
        $script = '<script>
            var customCalUrl = "' . $custom_cal_url . '";
            addEventListener("DOMContentLoaded", (event) => {
                Cal("floatingButton", {"calLink":"' . $url . '"' . (strlen($button_text) == 0 ? "" : "," . $button_text) . '});
                if(customCalUrl.length == 0) {
                    Cal("floatingButton", {"calLink":"' . $url . '"});
                }
                else {
                    Cal("floatingButton", {"calLink":"' . $url . '","calOrigin":customCalUrl});
                }
            });
        </script>';

        return $script;
    }

    /**
     * Enqueues registered embed scripts
     * 
     * @return void
     */
    private function load_embed_scripts(): void
    {
        wp_enqueue_script('calcom-embed-js');
        wp_enqueue_style('calcom-embed-css');
    }

    /**
     * Sanitizes embed shortcode attributes
     * 
     * @param $atts Shortcode attributess
     * @return $array
     */
    private function prepare_atts($atts): array
    {
        if ($atts) {

            $embed_url = '';
            $embed_type = '1';
            $embed_text = 'Book me';
            $embed_custom_cal_url = '';

            if (isset($atts['url']) && $atts['url']) {

                $url = sanitize_text_field($atts['url']);

                // ensure url is sanitized correctly
                if (str_contains($atts['url'], 'https://cal.com/')) {
                    $url = str_replace('https://cal.com/', '/', $url);
                }
                // 'https://' or 'http://' protocol indicate self-hosted instance
                elseif (str_contains($atts['url'], 'https://') || str_contains($atts['url'], 'http://')) {
                    // Start searching after 'http(s)://'
                    $firstSlashPosition = strpos($url, '/', strlen(str_contains($atts['url'], 'https://') ? "https://" : "http://"));
                    $embed_custom_cal_url = substr($url, 0, $firstSlashPosition);
                    $url = substr($url, $firstSlashPosition);
                }

                $embed_url = $url;
            }

            if (isset($atts['type']) && $atts['type']) {

                if (isset($atts['text']) && $atts['text']) {

                    $embed_text = sanitize_text_field($atts['text']);
                }

                $embed_type = sanitize_text_field($atts['type']);
            }

            return ['url' => $embed_url, 'type' => $embed_type, 'text' => $embed_text, 'customCalInstance' => $embed_custom_cal_url];
        }

        return [];
    }
}
