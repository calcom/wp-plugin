<?php

namespace CalCom\Integrations;

use CalCom\Credentials;
use CalCom\EventTypes\EventTypes;
use CalCom\EventTypes\Admin\EventTypeSelector;

defined('ABSPATH') || exit;

class WooCommerce
{

	/** Meta keys used to store Cal.com event configuration. */
	const META_KEY_EVENT_TYPE = '_calcom_event_type_id';
	const META_KEY_EVENT_URL  = '_calcom_event_url';

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

		if (! is_plugin_active('woocommerce/woocommerce.php')) {
			return;
		}

		add_action('woocommerce_product_options_general_product_data', array($this, 'add_product_meta_field'));
		add_action('woocommerce_process_product_meta', array($this, 'save_product_meta'));
		add_action('woocommerce_thankyou', array($this, 'render_thankyou_booking'), 10, 1);
		add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
	}

	public function enqueue_admin_assets($hook)
	{
		if (! is_plugin_active('woocommerce/woocommerce.php')) {
			return;
		}

		$screen = get_current_screen();
		if (! $screen || strpos((string) $screen->id, 'product') === false) {
			return;
		}

		wp_enqueue_style('calcom-admin-integrations-css');
	}

	public function is_enabled()
	{
		return $this->integrations->is_enabled('woocommerce');
	}

	public function has_api_config()
	{
		$credentials = new Credentials();
		return $credentials->exists();
	}

	public function get_settings()
	{
		$defaults = array(
			'cal_button_text'   => '',
			'cal_section_title' => '',
		);

		$settings = get_option('cal_com_woocommerce_settings', array());

		if (! is_array($settings)) {
			return $defaults;
		}

		return array_merge($defaults, $settings);
	}

	public function get_button_text()
	{
		$settings = $this->get_settings();
		$text     = isset($settings['cal_button_text']) ? $settings['cal_button_text'] : '';

		return ! empty($text) ? sanitize_text_field($text) : __('Let\'s talk', 'cal-com');
	}

	public function get_section_title()
	{
		$settings = $this->get_settings();
		$title    = isset($settings['cal_section_title']) ? $settings['cal_section_title'] : '';

		return ! empty($title) ? sanitize_text_field($title) : __('Schedule your appointment', 'cal-com');
	}

	public function add_product_meta_field()
	{
		$product_id = isset($GLOBALS['post']->ID) ? $GLOBALS['post']->ID : 0;
		if (! $product_id) {
			return;
		}

		$event_type_id = (int) get_post_meta($product_id, self::META_KEY_EVENT_TYPE, true);
		$event_url     = get_post_meta($product_id, self::META_KEY_EVENT_URL, true);
		$event_url     = $event_url ? esc_url_raw($event_url) : '';

		echo '<div class="options_group calcom-woocommerce-options-group">';
		echo '<p class="form-field calcom-woocommerce-event-type-field">';
		echo '<label for="calcom_event_type_id">' . esc_html__('Cal.com', 'cal-com') . '</label>';

		if ($this->has_api_config()) {
			echo '<span class="calcom-woocommerce-selector">';
			EventTypeSelector::render(
				array(
					'label'    => esc_html__('Cal.com Event Type', 'cal-com'),
					'name'     => 'calcom_event_type_id',
					'selected' => $event_type_id > 0 ? $event_type_id : null,
				)
			);
			echo '<input type="hidden" name="calcom_event_url" id="calcom_event_url" value="' . esc_attr($event_url) . '" />';
			echo '<p class="form-field">' . esc_html__('Select a Cal.com event type. After purchase, customers can book this event type on the order confirmation page.', 'cal-com') . '</p>';
			echo '</span>';
		} else {
			echo '<span class="calcom-woocommerce-selector">';
			echo '<input type="url" name="calcom_event_url" id="calcom_event_url" value="' . esc_attr($event_url) . '" placeholder="https://cal.com/example/30min" class="regular-text" />';
			echo '<p class="form-field">' . esc_html__('Enter a public Cal.com event URL (e.g., https://cal.com/example/30min), or configure API credentials for the event type selector.', 'cal-com') . '</p>';
			echo '</span>';
		}

		echo '</p>';
		echo '</div>';
	}

	public function save_product_meta($product_id)
	{
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- Nonce verified below.
		if (! isset($_POST['woocommerce_meta_nonce']) || ! wp_verify_nonce(wp_unslash($_POST['woocommerce_meta_nonce']), 'woocommerce_save_data')) {
			return;
		}

		if (! current_user_can('edit_product', $product_id)) {
			return;
		}

		$event_type_id = null;
		$event_url     = '';

		if (isset($_POST['calcom_event_type_id']) && is_numeric($_POST['calcom_event_type_id']) && (int) $_POST['calcom_event_type_id'] > 0) {
			$event_type_id = (int) $_POST['calcom_event_type_id'];
			$event_url     = $this->resolve_event_type_url($event_type_id);
		}

		if (empty($event_url) && isset($_POST['calcom_event_url'])) {
			$event_url     = esc_url_raw(wp_unslash($_POST['calcom_event_url']));
			$event_type_id = null;
		}

		if (! empty($event_url) && ! preg_match('#^https?://#i', $event_url)) {
			$event_url     = '';
			$event_type_id = null;
		}

		update_post_meta($product_id, self::META_KEY_EVENT_TYPE, $event_type_id);
		update_post_meta($product_id, self::META_KEY_EVENT_URL, $event_url);
	}

	public function render_thankyou_booking($order_id)
	{
		if (! $order_id) {
			return;
		}

		$order = function_exists('wc_get_order') ? wc_get_order($order_id) : false;
		if (! $order) {
			return;
		}

		$events = array();

		foreach ($order->get_items() as $item) {
			$product_id = $item->get_product_id();

			if (! $product_id) {
				continue;
			}

			$event_url = get_post_meta($product_id, self::META_KEY_EVENT_URL, true);
			if (empty($event_url) || ! is_string($event_url)) {
				continue;
			}

			$event_url = esc_url_raw($event_url);
			if (! preg_match('#^https?://#i', $event_url)) {
				continue;
			}

			$events[] = $event_url;
		}

		if (empty($events)) {
			return;
		}

		$section_title = esc_html($this->get_section_title());
		$button_text   = esc_attr($this->get_button_text());

		echo '<div class="calcom-woocommerce-thankyou-booking">';
		echo '<h3>' . esc_html($section_title) . '</h3>';

		foreach ($events as $event_url) {
			$shortcode = sprintf(
				'[cal type="2" url="%s" text="%s"]',
				esc_url($event_url),
				$button_text
			);

			$button_html = do_shortcode($shortcode);

			printf(
				'<div class="calcom-woocommerce-booking-section">%s</div>',
				wp_kses_post($button_html)
			);
		}

		echo '</div>';
	}

	public function has_products_with_calcom_events()
	{
		if (! is_plugin_active('woocommerce/woocommerce.php') || ! function_exists('WC') || ! WC()->cart) {
			return false;
		}

		foreach (WC()->cart->get_cart() as $cart_item) {
			$product = isset($cart_item['data']) ? $cart_item['data'] : null;
			if (! $product) {
				continue;
			}

			$event_url = get_post_meta($product->get_id(), self::META_KEY_EVENT_URL, true);
			if (! empty($event_url) && is_string($event_url)) {
				return true;
			}
		}

		return false;
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

	public function admin_notice()
	{
		if (! $this->is_enabled()) {
			return;
		}
	}

	public function render_admin_settings()
	{
		$settings      = $this->get_settings();
		$button_text   = esc_attr($settings['cal_button_text']);
		$section_title = esc_attr($settings['cal_section_title']);

		ob_start();
?>
		<div class="calcom-woocommerce-settings-section">
			<h2><?php esc_html_e('WooCommerce', 'cal-com'); ?></h2>
			<p class="description"><?php esc_html_e('The Cal.com event type is configured per product on the product edit screen. After a customer purchases a product with a Cal.com event type, the booking prompt appears on the order confirmation page.', 'cal-com'); ?></p>

			<?php if (! is_plugin_active('woocommerce/woocommerce.php')) : ?>
				<div class="notice notice-warning inline">
					<p><?php esc_html_e('WooCommerce is not active. The integration settings will appear on product edit screens once WooCommerce is installed and activated.', 'cal-com'); ?></p>
				</div>
			<?php endif; ?>

			<form id="calcom-woocommerce-settings-form" method="post" action="">
				<?php wp_nonce_field('calcom_woocommerce_settings', 'calcom_woocommerce_nonce'); ?>
				<input type="hidden" name="calcom_woocommerce_action" value="save_settings" />
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">
							<label for="calcom_woocommerce_button_text"><?php esc_html_e('Button Text', 'cal-com'); ?></label>
						</th>
						<td>
							<input
								type="text"
								id="calcom_woocommerce_button_text"
								name="calcom_woocommerce_button_text"
								value="<?php echo esc_attr($button_text); ?>"
								placeholder="Let\'s talk"
								class="regular-text" />
							<p class="description"><?php esc_html_e('Enter the text for the booking button on the thank-you page. Leave empty to use "Let\'s talk".', 'cal-com'); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="calcom_woocommerce_section_title"><?php esc_html_e('Section Title', 'cal-com'); ?></label>
						</th>
						<td>
							<input
								type="text"
								id="calcom_woocommerce_section_title"
								name="calcom_woocommerce_section_title"
								value="<?php echo esc_attr($section_title); ?>"
								placeholder="Schedule your appointment"
								class="regular-text" />
							<p class="description"><?php esc_html_e('Enter the heading text for the booking section on the thank-you page. Leave empty to use "Schedule your appointment".', 'cal-com'); ?></p>
						</td>
					</tr>
				</table>
				<?php submit_button(__('Save Changes', 'cal-com'), 'primary', 'calcom_woocommerce_save', false); ?>
			</form>

			<div class="calcom-woocommerce-info">
				<p><?php esc_html_e('To configure:', 'cal-com'); ?></p>
				<ol>
					<li><?php esc_html_e('Go to any product edit screen', 'cal-com'); ?></li>
					<li><?php esc_html_e('Scroll to the "Cal.com" section', 'cal-com'); ?></li>
					<li><?php esc_html_e('Select a Cal.com event type or enter a URL', 'cal-com'); ?></li>
					<li><?php esc_html_e('Update the product', 'cal-com'); ?></li>
					<li><?php esc_html_e('Customers will see the booking option on the thank-you page after purchase', 'cal-com'); ?></li>
				</ol>
			</div>
		</div>
		<?php
		$template = ob_get_clean();
		return $template;
	}

	public function handle_form_post()
	{
		if (! isset($_POST['calcom_woocommerce_action']) || $_POST['calcom_woocommerce_action'] !== 'save_settings') {
			return;
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- Nonce verified below.
		if (! isset($_POST['calcom_woocommerce_nonce']) || ! wp_verify_nonce(wp_unslash($_POST['calcom_woocommerce_nonce']), 'calcom_woocommerce_settings')) {
			wp_die(esc_html__('Security check failed.', 'cal-com'));
		}

		if (! current_user_can('manage_options')) {
			wp_die(esc_html__('You do not have permission to manage this setting.', 'cal-com'));
		}

		$button_text   = isset($_POST['calcom_woocommerce_button_text']) ? sanitize_text_field(wp_unslash($_POST['calcom_woocommerce_button_text'])) : '';
		$section_title = isset($_POST['calcom_woocommerce_section_title']) ? sanitize_text_field(wp_unslash($_POST['calcom_woocommerce_section_title'])) : '';

		update_option(
			'cal_com_woocommerce_settings',
			array(
				'cal_button_text'   => $button_text,
				'cal_section_title' => $section_title,
			)
		);

		add_action(
			'admin_notices',
			function () {
		?>
			<div class="notice notice-success is-dismissible">
				<p><?php esc_html_e('Cal.com WooCommerce settings saved.', 'cal-com'); ?></p>
			</div>
<?php
			}
		);
	}
}
