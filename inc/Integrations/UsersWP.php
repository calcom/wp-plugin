<?php

namespace CalCom\Integrations;

defined('ABSPATH') || exit;

class UsersWP
{
    /** CSS selector targeting the UsersWP profile user title section. */
    const PROFILE_SELECTOR = '.uwp-user-title';

    /** @var Integrations */
    private $integrations;

    /** @var UserMeta */
    private $user_meta;

    public function __construct(Integrations $integrations)
    {
        $this->integrations = $integrations;
        $this->user_meta = new UserMeta();
    }

    public function hooks()
    {
        if (!$this->is_enabled()) {
            return;
        }

        // Front-end profile rendering via JS.
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
    }

    public function is_enabled()
    {
        return $this->integrations->is_enabled('users-wp');
    }

    public function render_admin_settings()
    {
        ob_start();
        ?>
        <div class="calcom-userswp-settings-section">
            <h2><?php esc_html_e('UsersWP', 'cal-com'); ?></h2>
           
            <p class="description"><?php esc_html_e('This integration uses per-user Cal.com booking URLs stored in the WordPress user profile. Configure a Cal.com URL for each member on their profile edit screen.', 'cal-com'); ?></p>
            <?php if (!function_exists('is_uwp_profile_page')): ?>
                <div class="notice notice-warning inline">
                    <p><?php esc_html_e('UsersWP is not active. Booking URL configuration will appear on user profile screens once UsersWP is installed and activated.', 'cal-com'); ?></p>
                </div>
            <?php endif; ?>
          
            <div class="calcom-userswp-info">
                <p><?php esc_html_e('To configure:', 'cal-com'); ?></p>
                <ol>
                    <li><?php esc_html_e('Go to Users &rarr; All Users', 'cal-com'); ?></li>
                    <li><?php esc_html_e('Edit any member user', 'cal-com'); ?></li>
                    <li><?php esc_html_e('Scroll to the "Cal.com Booking" section in the profile', 'cal-com'); ?></li>
                    <li><?php esc_html_e('Enter the Cal.com booking URL and optional button text', 'cal-com'); ?></li>
                    <li><?php esc_html_e('Update the user &mdash; their UsersWP profile will show the booking button', 'cal-com'); ?></li>
                </ol>
            </div>
        </div>

        <?php
        $template = ob_get_clean();
        return $template;
    }

    /**
     * Get the profile user ID from the UsersWP profile page context.
     */
    public function get_profile_user_id()
    {
        // Use UsersWP's uwp_get_displayed_user() to get the displayed profile user,
        if (function_exists('uwp_get_displayed_user')) {
            $user = uwp_get_displayed_user();
            if ($user && isset($user->ID)) {
                return (int) $user->ID;
            }
        }

        // Fallback: current user (only on own profile).
        if (is_user_logged_in()) {
            $user = \wp_get_current_user();
            if ($user && $user->ID) {
                return $user->ID;
            }
        }

        return 0;
    }

    /**
     * Inject Cal.com booking button into UsersWP profile via JS.
     *
     * Uses JavaScript to reliably inject the button inside the ".uwp-user-title"
     * section (below the user's display name).
     */
    public function enqueue_assets()
    {
        if (is_admin()) {
            return;
        }

        if (!get_query_var('uwp_profile')) {
            return;
        }
        
        $user_id = $this->get_profile_user_id();
        if (!$user_id) {
            return;
        }

        $url = $this->user_meta->get_user_booking_url($user_id);
        if (empty($url)) {
            return;
        }

        $button_text = $this->user_meta->get_user_booking_button_text($user_id);

        // Enqueue required Cal.com scripts.
        wp_enqueue_script('calcom-loader-js');
        wp_enqueue_script('calcom-embed-js');
        wp_enqueue_style('calcom-embed-css');
      
        // Generate Cal.com embed HTML server-side via the existing [cal] shortcode.
        $embed_html = $this->user_meta->get_embed_html($url, $button_text);

        // Build the booking button div
        $booking_html = sprintf(
            '<div class="calcom-userswp-profile-booking calcom-js-injected">%s</div>',
            $embed_html
        );

        // JSON-encode the HTML for safe JS insertion.
        $encoded_html = json_encode($booking_html);

        // JS that waits for the selector to appear, then injects the button.
        $selector = self::PROFILE_SELECTOR;
        $js = sprintf(
            "var calcomInjectBtn = function() { var el = document.querySelector('%s'); if (!el) { setTimeout(calcomInjectBtn, 300); return; } if (el.querySelector('.calcom-userswp-profile-booking')) { return; } var div = document.createElement('div'); div.innerHTML = %s; el.appendChild(div); if (window.calcomInitButtons) { window.calcomInitButtons(); } }; setTimeout(calcomInjectBtn, 100);",
            $selector,
            $encoded_html
        );

        wp_add_inline_script('calcom-embed-js', $js, 'before');
    }

}
