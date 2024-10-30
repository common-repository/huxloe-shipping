<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
$links = array(
	'settings' =>
	add_query_arg(
		array(
			'page'    => 'wc-settings',
			'tab'     => 'shipping',
			'section' => 'shipping_calc',
		),
		admin_url( 'admin.php' )
	),
);

$settingImage = plugin_dir_url( __FILE__ ) . 'images/setting-screenshot.png';
?>
<div class="wrap huxloe-shippings-setting-page">
	<div class="huxloe-shipping-quick-start-content">
		<h1 class="huxloe_shippings_heading">
			<?php esc_html_e( 'Welcome to Huxloe Shipping', 'huxloe-shipping' ); ?>
		</h1> 
		<div class="huxloe-shippings-box-section">
			<ol>
				<li><?php esc_html_e( 'Thank you for installing the Huxloe Shipping Woocommerce plugin.', 'huxloe-shipping' ); ?></li>
				<li><?php esc_html_e( 'Before using this plugin please update your details below:', 'huxloe-shipping' ); ?>
					<div class="huxloe-shippings-setting-image">
						<img src="<?php echo esc_url( $settingImage ); ?>" alt="setting-image">
					</div>
				</li>
				<li><?php esc_html_e( 'You must go to Shipping Zones and assign each shipping method to one of our Service carriers.', 'huxloe-shipping' ); ?>
				</li> 
				<li><?php esc_html_e( 'For International shipments all products will require an SKU, HS code, Country of manufacture, and weight and dims.', 'huxloe-shipping' ); ?>
				</li>
				<li><?php esc_html_e( 'Please note you need phone number to be set to mandatory in Customise > Woocommerce settings.', 'huxloe-shipping' ); ?>
				</li>
			</ol>
			<a href="<?php echo esc_url( $links['settings'] ); ?>" class="huxloe-setting-btn button-primary woocommerce-save-button">
				<?php esc_html_e( 'Go to Settings', 'huxloe-shipping' ); ?>
				<span class="dashicons dashicons-admin-links"></span>
			</a>
		</div>
	</div>
</div>
