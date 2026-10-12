<?php

namespace CalCom\EventTypes\Admin;

defined('ABSPATH') || exit;

class EventTypeSelector
{
    const OPTION_KEY = 'calcom_selected_event_type';

    public static function render($args)
    {
        $defaults = [
            'label'   => '',
            'name'    => '',
            'selected' => null,
        ];
        $args = wp_parse_args($args, $defaults);

        $options = self::get_event_type_options($args['selected']);

        // Pre-populate hidden #calLink with the selected event type's booking_url.
        $initial_url = '';
        if (!empty($options['success']) && $options['success']) {
            foreach ($options['event_types'] as $et) {
                if (isset($options['selected']) && isset($et['id']) && $et['id'] === $options['selected']) {
                    $initial_url = isset($et['booking_url']) ? (string) $et['booking_url'] : '';
                    break;
                }
            }
        }
        ?>
        <input type="hidden" id="calLink" value="<?php echo esc_attr($initial_url); ?>">
        <select name="<?php echo esc_attr($args['name']); ?>" id="<?php echo esc_attr($args['name']); ?>-selector" data-cal-link="#calLink">
            <?php if (!empty($options['success']) && $options['success']): ?>
                <?php if (isset($initial_url) && $initial_url): ?>
                    <option value=""></option>
                <?php else: ?>
                    <option value=""><?php esc_html_e('Select an event type', 'cal-com'); ?></option>
                <?php endif; ?>
                <?php foreach ($options['event_types'] as $et): ?>
                    <option value="<?php echo esc_attr($et['id']); ?>" data-booking-url="<?php echo esc_attr($et['booking_url']); ?>" <?php selected(isset($options['selected']) && $options['selected'] === $et['id']); ?>>
                        <?php echo esc_html($et['title']); ?>
                    </option>
                <?php endforeach; ?>
            <?php else: ?>
                <option value=""><?php esc_html_e('Unable to load event types', 'cal-com'); ?></option>
            <?php endif; ?>
        </select>
        <?php
    }

    /**
     * Get event type options for the admin selector.
     *
     * @param int|null $preselected  Pre-selected event type ID from caller.
     * @return array{success: bool, event_types: array, selected: int|null, error: string|null, html: string}
     */
    public static function get_event_type_options($preselected = null)
    {
        $credentials = new \CalCom\Credentials();

        if (! $credentials->exists()) {
            return [
                'success'      => false,
                'event_types'  => [],
                'selected'     => null,
                'error'         => __('No credentials configured.', 'cal-com'),
                'html'          => '<p>' . __('Please configure your Cal.com API key in the settings.', 'cal-com') . '</p>',
            ];
        }

        $et = new \CalCom\EventTypes\EventTypes($credentials);
        $result = $et->get();

        if (! $result['success']) {
            return [
                'success'      => false,
                'event_types'  => [],
                'selected'     => null,
                'error'         => $result['error'],
                'html'          => '',
            ];
        }

        $original_selected = $preselected !== null ? $preselected : self::get_selected();
        $selected = $original_selected;
        $event_types = $result['event_types'];

        if ($selected !== null) {
            $found = false;
            foreach ($event_types as $event_type) {
                if (isset($event_type['id']) && $event_type['id'] === $selected) {
                    $found = true;
                    break;
                }
            }
            if (! $found) {
                $selected = null;
            }
        }

        $html = '<select name="calcom_event_type" id="calcom-event-type-selector">';

        if (empty($event_types)) {
            $html .= '<option value="">' . __('No event types found', 'cal-com') . '</option>';
        } else {
            foreach ($event_types as $event_type) {
                $selected_attr = (isset($event_type['id']) && $selected !== null && $selected === $event_type['id']) ? ' selected' : '';
                $html .= '<option value="' . esc_attr($event_type['id']) . '"' . $selected_attr . '>'
                    . esc_html($event_type['title']) . '</option>';
            }
        }

        $html .= '</select>';

        if ($original_selected !== null && $original_selected !== $selected) {
            $html = '<p>' . __('Selected event type no longer exists in your Cal.com account.', 'cal-com') . '</p>' . $html;
        }

        return [
            'success'      => true,
            'event_types'  => $event_types,
            'selected'    => $selected,
            'error'         => null,
            'html'          => $html,
        ];
    }

    /** Get the currently selected event type ID. */
    public static function get_selected()
    {
        $id = get_option(self::OPTION_KEY, null);
        if ($id === null) {
            return null;
        }
        $id = (int) $id;
        return $id > 0 ? $id : null;
    }

    /** Save a selected event type ID, validating it exists in the account. */
    public static function save_selection($id)
    {
        if (! is_numeric($id) || (int) $id <= 0) {
            return [
                'success' => false,
                'error'   => __('Invalid event type ID.', 'cal-com'),
            ];
        }

        $id = (int) $id;

        $credentials = new \CalCom\Credentials();
        if (! $credentials->exists()) {
            return [
                'success' => false,
                'error'   => __('No credentials configured.', 'cal-com'),
            ];
        }

        $et = new \CalCom\EventTypes\EventTypes($credentials);
        $result = $et->get(true);

        if (! $result['success']) {
            return [
                'success' => false,
                'error'   => $result['error'],
            ];
        }

        $found = false;
        foreach ($result['event_types'] as $event_type) {
            if (isset($event_type['id']) && $event_type['id'] === $id) {
                $found = true;
                break;
            }
        }

        if (! $found) {
            return [
                'success' => false,
                'error'   => __('Event type not found in your Cal.com account.', 'cal-com'),
            ];
        }

        update_option(self::OPTION_KEY, $id, false);

        return [
            'success' => true,
            'error'   => null,
        ];
    }
}
