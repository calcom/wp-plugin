<?php

namespace CalCom\Integrations;

use CalCom\Credentials;
use CalCom\EventTypes\EventTypes;
use CalCom\EventTypes\Admin\EventTypeSelector;

defined('ABSPATH') || exit;

class WPForms
{

	/** Meta keys used to store Cal.com WPForms integration configuration. */
	const OPTION_KEY = 'cal_com_wpforms_settings';

	/** @var Integrations */
	private $integrations;

	public function __construct(Integrations $integrations)
	{
		$this->integrations = $integrations;
	}

	public function hooks()
	{
		if (! $this->is_enabled()) {
			return;
		}

		if (! is_plugin_active('wpforms-lite/wpforms.php')) {
			return;
		}

		add_action('wpforms_frontend_js', array($this, 'enqueue_form_assets'));
		add_action('wpforms_frontend_confirmation_message', array($this, 'inject_booking_prompt'), 10, 4);
	}

	public function is_enabled()
	{
		return $this->integrations->is_enabled('wpforms');
	}

	public function has_api_config()
	{
		$credentials = new Credentials();
		return $credentials->exists();
	}

	public function get_settings()
	{
		$defaults = array(
			'cal_event_url'   => '',
			'cal_event_type'  => null,
			'cal_button_text' => '',
			'cal_form_ids'    => '',
		);

		$settings = get_option(self::OPTION_KEY, array());

		if (! is_array($settings)) {
			return $defaults;
		}

		return array_merge($defaults, $settings);
	}

	public function get_event_url()
	{
		$settings = $this->get_settings();
		$url      = isset($settings['cal_event_url']) ? $settings['cal_event_url'] : '';

		return esc_url_raw($url);
	}

	public function get_button_text()
	{
		$settings = $this->get_settings();
		$text     = isset($settings['cal_button_text']) ? $settings['cal_button_text'] : '';

		return ! empty($text) ? sanitize_text_field($text) : __('Schedule a call instead', 'cal-com');
	}

	public function get_form_ids()
	{
		$settings = $this->get_settings();
		$ids      = isset($settings['cal_form_ids']) ? $settings['cal_form_ids'] : '';

		return array_filter(
			array_map(
				function ($id) {
					return sanitize_key(trim($id));
				},
				explode(',', (string) $ids)
			)
		);
	}

	public function enqueue_form_assets()
	{
		if (empty($this->get_event_url())) {
			return;
		}

		wp_enqueue_script('calcom-forms-js');
		wp_enqueue_script('calcom-embed-js');
		wp_enqueue_style('calcom-embed-css');
	}

	public function inject_booking_prompt($message, $form_data, $fields, $entry_id)
	{
		if (empty($this->get_event_url())) {
			return $message;
		}

		// Check form ID restriction
		$form_ids = $this->get_form_ids();
		if (! empty($form_ids)) {
			$current_form_id = isset($form_data['id']) ? sanitize_key((string) $form_data['id']) : '';
			if (! in_array($current_form_id, $form_ids, true)) {
				return $message;
			}
		}

		$event_url   = esc_url_raw($this->get_event_url());
		$button_text = esc_attr($this->get_button_text());

		$shortcode = sprintf(
			'[cal type="2" url="%s" text="%s"]',
			esc_url($event_url),
			$button_text
		);

		$button_html = do_shortcode($shortcode);

		// Append booking prompt to the confirmation message
		$prompt = sprintf(
			'<div class="calcom-wpforms-prompt calcom-forms-prompt">%s</div>',
			$button_html
		);

		return $message . $prompt;
	}

	private function resolve_event_type_url($event_type_id)
	{
		$credentials = new Credentials();

		if (! $credentials->exists()) {
			return '';
		}

		$et     = new EventTypes($credentials);
		$result = $et->get(true);

		if (! $result['success']) {
			return '';
		}

		foreach ($result['event_types'] as $event_type) {
			if (isset($event_type['id']) && $event_type['id'] === $event_type_id) {
				return isset($event_type['booking_url']) ? (string) $event_type['booking_url'] : '';
			}
		}

		return '';
	}

	private function save_settings($event_url, $event_type_id = null, $button_text = '', $form_ids = '')
	{
		$settings = array(
			'cal_event_url'   => $event_url,
			'cal_event_type'  => $event_type_id,
			'cal_button_text' => $button_text,
			'cal_form_ids'    => $form_ids,
		);

		update_option(self::OPTION_KEY, $settings);
	}

	public function admin_notice()
	{
		if (! $this->is_enabled() || ! empty($this->get_event_url())) {
			return;
		}

		$screen = get_current_screen();

		if (! $screen || strpos((string) $screen->id, 'calcom') === false) {
			return;
		}

		$settings_url = esc_url(admin_url('admin.php?page=calcom-integrations'));
?>
		<div class="notice notice-warning is-dismissible">
			<p><?php esc_html_e('WPForms integration is enabled, but no Cal.com event URL is configured.', 'cal-com'); ?></p>
			<p>
				<?php
				// translators: %s is the URL to the integrations settings page.
				printf(wp_kses_post(__(' <a href="%s">Configure the event URL now</a>', 'cal-com')), esc_url($settings_url));
				?>
			</p>
		</div>
	<?php
	}

	public function render_admin_settings()
	{
		$settings    = $this->get_settings();
		$event_url   = esc_attr($settings['cal_event_url']);
		$event_type  = ! empty($settings['cal_event_type']) ? (int) $settings['cal_event_type'] : null;
		$button_text = esc_attr($settings['cal_button_text']);
		$form_ids    = esc_attr($settings['cal_form_ids']);
		$description = __('After a successful WPForms submission, visitors can optionally book a time using this Cal.com event.', 'cal-com');

		ob_start();
	?>
		<div class="calcom-wpforms-settings-section">
			<h2><?php esc_html_e('WPForms', 'cal-com'); ?></h2>
			<p class="description"><?php echo esc_html($description); ?></p>

			<?php if (empty($event_url) && $this->is_enabled()) : ?>
				<div class="notice notice-warning inline">
					<p><?php esc_html_e('Cal.com event URL is required for the WPForms integration to display the scheduling prompt.', 'cal-com'); ?></p>
				</div>
			<?php endif; ?>

			<form id="calcom-wpforms-settings-form" method="post" action="">
				<?php wp_nonce_field('calcom_wpforms_settings', 'calcom_wpforms_nonce'); ?>
				<input type="hidden" name="calcom_wpforms_action" value="save_settings" />
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">
							<label for="calcom_wpforms_event_url"><?php esc_html_e('Cal.com Event URL', 'cal-com'); ?></label>
						</th>
						<td>
							<?php if ($this->has_api_config()) : ?>
								<div class="calcom-wpforms-event-type-selector-wrapper">
									<?php
									EventTypeSelector::render(
										array(
											'label'    => esc_html__('Cal.com Event Type', 'cal-com'),
											'name'     => 'calcom_wpforms_event_type',
											'selected' => $event_type,
										)
									);
									?>
								</div>
								<p class="description"><?php esc_html_e('Select an event type from your Cal.com account. Your visitors will be able to book this event type after a successful form submission.', 'cal-com'); ?></p>
							<?php else : ?>
								<input
									type="url"
									id="calcom_wpforms_event_url"
									name="calcom_wpforms_event_url"
									value="<?php echo esc_attr($event_url); ?>"
									placeholder="https://cal.com/example/30min"
									class="regular-text" />
								<p class="description"><?php esc_html_e('Enter a public Cal.com event URL (e.g., https://cal.com/example/30min).', 'cal-com'); ?></p>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="calcom_wpforms_button_text"><?php esc_html_e('Button Text', 'cal-com'); ?></label>
						</th>
						<td>
							<input
								type="text"
								id="calcom_wpforms_button_text"
								name="calcom_wpforms_button_text"
								value="<?php echo esc_attr($button_text); ?>"
								placeholder="Schedule a call instead"
								class="regular-text" />
							<p class="description"><?php esc_html_e('Enter the text for the booking button. Leave empty to use "Schedule a call instead".', 'cal-com'); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="calcom_wpforms_form_ids"><?php esc_html_e('Form IDs', 'cal-com'); ?></label>
						</th>
						<td>
							<input
								type="text"
								id="calcom_wpforms_form_ids"
								name="calcom_wpforms_form_ids"
								value="<?php echo esc_attr($form_ids); ?>"
								placeholder="123, 456, 789"
								class="regular-text" />
							<p class="description"><?php esc_html_e('Enter comma-separated WPForms form IDs to restrict which forms show the booking button. Leave empty to show on all forms.', 'cal-com'); ?></p>
						</td>
					</tr>
				</table>
				<?php submit_button(__('Save Changes', 'cal-com'), 'primary', 'calcom_wpforms_save', false); ?>
			</form>
		</div>
		<?php
		$template = ob_get_clean();
		return $template;
	}

	public function handle_form_post()
	{
		if (! isset($_POST['calcom_wpforms_action']) || $_POST['calcom_wpforms_action'] !== 'save_settings') {
			return;
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- Nonce verified below.
		if (! isset($_POST['calcom_wpforms_nonce']) || ! wp_verify_nonce(wp_unslash($_POST['calcom_wpforms_nonce']), 'calcom_wpforms_settings')) {
			wp_die(esc_html__('Security check failed.', 'cal-com'));
		}

		if (! current_user_can('manage_options')) {
			wp_die(esc_html__('You do not have permission to manage this setting.', 'cal-com'));
		}

		$event_url     = '';
		$event_type_id = null;

		if (isset($_POST['calcom_wpforms_event_type']) && is_numeric($_POST['calcom_wpforms_event_type']) && (int) $_POST['calcom_wpforms_event_type'] > 0) {
			$event_type_id = (int) $_POST['calcom_wpforms_event_type'];
			$event_url     = $this->resolve_event_type_url($event_type_id);
		}

		if (empty($event_url) && isset($_POST['calcom_wpforms_event_url'])) {
			$event_url     = esc_url_raw(wp_unslash($_POST['calcom_wpforms_event_url']));
			$event_type_id = null;
		}

		if (! empty($event_url) && ! preg_match('#^https?://#i', $event_url)) {
			$event_url     = '';
			$event_type_id = null;
		}

		$button_text = isset($_POST['calcom_wpforms_button_text']) ? sanitize_text_field(wp_unslash($_POST['calcom_wpforms_button_text'])) : '';
		$form_ids    = isset($_POST['calcom_wpforms_form_ids']) ? sanitize_text_field(wp_unslash($_POST['calcom_wpforms_form_ids'])) : '';

		$this->save_settings($event_url, $event_type_id, $button_text, $form_ids);

		add_action(
			'admin_notices',
			function () {
		?>
			<div class="notice notice-success is-dismissible">
				<p><?php esc_html_e('Cal.com event URL saved.', 'cal-com'); ?></p>
			</div>
<?php
			}
		);
	}
}
