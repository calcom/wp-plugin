<?php

namespace CalCom\Admin;

defined('ABSPATH') || exit;

class Customizer
{
    public function hooks()
    {
        add_action('in_admin_header', [$this, 'clear_unwanted_notices']);
        add_action('admin_menu', [$this, 'menu']);
    }

    public function clear_unwanted_notices()
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only reading admin page slug.
        if (!isset($_GET['page'])) return;
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only reading admin page slug.
        $page = sanitize_text_field(wp_unslash($_GET['page']));
        if (strpos($page, 'calcom') === 0) {
            remove_all_actions('admin_notices');
            remove_all_actions('all_admin_notices');
        }
    }

    public function menu()
    {
        add_menu_page(
            'Cal.com',
            'Cal.com',
            'manage_options',
            'calcom',
            [$this, 'render'],
            'dashicons-calendar',
            30
        );

        add_submenu_page(
            'calcom',
            __('Cal.com Customizer', 'cal-com'),
            __('Customizer', 'cal-com'),
            'manage_options',
            'calcom',
            [$this, 'render'],
            30
        );
    }

    private function has_api_config()
    {
        $credentials = new \CalCom\Credentials();
        return $credentials->exists();
    }

    private function has_cached_event_types()
    {
        $cached = get_transient(\CalCom\EventTypes\EventTypes::CACHE_KEY);
        if ($cached === false || !is_array($cached) || empty($cached)) {
            return false;
        }

        $visible = array_filter($cached, function ($et) {
            return isset($et['hidden']) ? !$et['hidden'] : true;
        });

        return count($visible) > 0;
    }

    public function render()
    {
        wp_enqueue_script('calcom-customizer-js');
        wp_enqueue_script('calcom-customizer-extra-js');
        wp_enqueue_style('calcom-customizer-css');
        ?>
        <div class="cal-admin wrap">
            <h1><?php esc_html_e('Customize Your Cal Widget', 'cal-com'); ?></h1>

            <div id="calcom-customizer">
                <div id="cal-customizer">
                    <div class="customizer-controls">
                        <?php if ($this->has_api_config() && $this->has_cached_event_types()): ?>
                            <div class="group">
                                <div class="section-title"><?php esc_html_e('Default Event Type', 'cal-com'); ?></div>
                                <?php \CalCom\EventTypes\Admin\EventTypeSelector::render([
                                    'label' => esc_html__('Cal.com Event Type', 'cal-com'),
                                    'name' => 'calcom_event_type',
                                ]); ?>
                            </div>
                        <?php else: ?>
                            <div class="group">
                                <div class="section-title"><?php esc_html_e('Cal Link', 'cal-com'); ?></div>
                                <label><?php esc_html_e('Cal Link', 'cal-com'); ?></label>
                                <input type="text" id="calLink" placeholder="/demo/30min" value="">
                            </div>
                        <?php endif; ?>

                        <div class="shortcode-box">
                            <span class="shortcode-label"><?php esc_html_e('Generated Shortcode', 'cal-com'); ?></span>
                            <textarea id="output" readonly></textarea>
                        </div>
                        <button id="copy" class="button button-primary"><?php esc_html_e('Copy Shortcode', 'cal-com'); ?></button>

                        <div class="group">
                            <div class="section-title"><?php esc_html_e('General', 'cal-com'); ?></div>
                            <label><?php esc_html_e('Embed Type', 'cal-com'); ?></label>
                            <select id="type">
                                <option value="1"><?php esc_html_e('Inline', 'cal-com'); ?></option>
                                <option value="2"><?php esc_html_e('Modal', 'cal-com'); ?></option>
                                <option value="3"><?php esc_html_e('Floating Button', 'cal-com'); ?></option>
                            </select>

                            <label class="checkbox">
                                <input type="checkbox" id="prefill">
                                <?php esc_html_e('Prefill logged-in user', 'cal-com'); ?>
                            </label>

                            <label><?php esc_html_e('UTM Parameters (comma-separated key:value)', 'cal-com'); ?></label>
                            <input type="text" id="utm" placeholder="source:localhost,medium:web">
                        </div>

                        <div class="group">
                            <div class="section-title"><?php esc_html_e('Appearance', 'cal-com'); ?></div>

                            <label><?php esc_html_e('Theme', 'cal-com'); ?></label>
                            <select id="theme">
                                <option value="light"><?php esc_html_e('Light', 'cal-com'); ?></option>
                                <option value="dark"><?php esc_html_e('Dark', 'cal-com'); ?></option>
                            </select>

                            <label><?php esc_html_e('Brand Color', 'cal-com'); ?></label>
                            <input type="color" id="brandColor" value="#000000">

                            <label><?php esc_html_e('Layout', 'cal-com'); ?></label>
                            <select id="layout">
                                <option value="month_view"><?php esc_html_e('Month', 'cal-com'); ?></option>
                                <option value="week_view"><?php esc_html_e('Week', 'cal-com'); ?></option>
                            </select>

                            <label class="checkbox">
                                <input type="checkbox" id="hideDetails">
                                <?php esc_html_e('Hide event details', 'cal-com'); ?>
                            </label>
                        </div>

                        <div class="group">
                            <div class="section-title"><?php esc_html_e('Behavior', 'cal-com'); ?></div>

                            <label class="checkbox">
                                <input type="checkbox" id="slotsMobile">
                                <?php esc_html_e('Slots view on mobile', 'cal-com'); ?>
                            </label>

                            <label class="checkbox">
                                <input type="checkbox" id="disableScroll">
                                <?php esc_html_e('Disable mobile scroll', 'cal-com'); ?>
                            </label>
                        </div>
                    </div>

                    <div class="customizer-preview">
                        <div id="preview">
                            <?php esc_html_e('Start customizing', 'cal-com'); ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
}
