<?php
/**
 * Plugin Name: Huxloe Shipping
 * Plugin URI:  https://huxloe.com/
 * Description: Generate labels on the Huxloe 360 Shipping platform.
 * Version: 1.0.0
 * Author: Huxloe Logistics <info@huxloe.com>
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: huxloe-shipping
 * Domain Path: /languages
 * Requires PHP: 5.6
 * Requires at least: 5.0
 * Tested up to: 6.5.2
 *
 * @category Plugin
 * @package  Huxloe_Shipping
 * @author   Huxloe Logistics <info@huxloe.com>
 * @license  GPL-2.0-or-later https://www.gnu.org/licenses/gpl-2.0.html
 * @link     https://huxloe.com/
 * @version  1.0.0
 * @php      5.6
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

require plugin_dir_path( __FILE__ ) . 'huxloe-admin-side.php';

if ( ! class_exists( 'Huxloe_Shipping_Calc' ) ) {
	/**
	 * Class Huxloe_Shipping_Calc
	 */
	class Huxloe_Shipping_Calc {

		private $plugin_slug;

		/**
		 * Huxloe_Shipping_Calc constructor.
		 */
		public function __construct() {
			$this->plugin_slug = 'huxloe-shipping';

			add_filter(
				'woocommerce_get_sections_shipping',
				array( $this, 'add_shipping_settings_section_tab' )
			);

			add_filter(
				'woocommerce_get_settings_shipping',
				array( $this, 'add_shipping_settings_fields' ),
				10,
				2
			);

			add_action(
				'woocommerce_init',
				array( $this, 'huxloe_shipping_instance_form_fields_filters' )
			);

			// Add settings link to plugins page
			add_filter(
				'plugin_action_links_' . plugin_basename( __FILE__ ),
				array( $this, 'huxloe_add_plugin_settings_link' )
			);

			// Add admin menu page
			add_action( 'admin_menu', array( $this, 'huxloe_add_admin_menu_page' ) );
		}

		/**
		 * Add settings link to plugins page.
		 *
		 * @param  array $links Plugin action links.
		 * @return array
		 */
		public function huxloe_add_plugin_settings_link( $links ) {
			$settings_link = sprintf(
				'<a href="%s">%s</a>',
				esc_url(
					add_query_arg(
						array(
							'page'    => 'wc-settings',
							'tab'     => 'shipping',
							'section' => 'shipping_calc',
						),
						admin_url( 'admin.php' )
					)
				),
				__( 'Settings', 'huxloe-shipping' )
			);

			array_unshift( $links, $settings_link );
			return $links;
		}

		/**
		 * Add admin menu page.
		 *
		 * @return void
		 */
		public function huxloe_add_admin_menu_page() {
			// Top Level menu
			add_menu_page(
				__( 'Huxloe Shipping', 'huxloe-shipping' ),
				__( 'Huxloe Shipping', 'huxloe-shipping' ),
				'manage_options', // Capability
				$this->plugin_slug, // Menu slug
				array( $this, 'huxloe_display_admin_menu_page' ), // Callback
				'dashicons-admin-generic', // Icon URL
				58 // Position
			);
		}

		/**
		 * Display admin menu page.
		 *
		 * @return void
		 */
		public function huxloe_display_admin_menu_page() {
			include_once plugin_dir_path( __FILE__ ) . 'huxloe-display-admin-menu-page.php';
		}

		/**
		 * Add shipping settings section tab.
		 *
		 * @param  array $section Sections.
		 * @return array
		 */
		public function add_shipping_settings_section_tab( $section ) {
			$section['shipping_calc'] = __( 'Huxloe Shipping', 'huxloe-shipping' );
			return $section;
		}

		/**
		 * Add shipping settings fields.
		 *
		 * @param  array  $settings        Settings.
		 * @param  string $current_section Current section.
		 * @return array
		 */
		public function add_shipping_settings_fields( $settings, $current_section ) {
			// Check the current section
			if ( 'shipping_calc' !== $current_section ) {
				return $settings; // Return the standard settings
			}

			$custom_settings = array(
				array(
					'title' => __( 'Huxloe Shipping Integration', 'huxloe-shipping' ),
					'type'  => 'title',
					'id'    => 'huxloe_shipping_integration_settings',
				),
				array(
					'title'   => __( 'API Key', 'huxloe-shipping' ),
					'type'    => 'text',
					'desc'    => __( 'Enter your API Key here', 'huxloe-shipping' ),
					'id'      => 'huxloe_shipping_api_key',
					'default' => '',
				),
				array(
					'title'   => __( 'Tenant ID', 'huxloe-shipping' ),
					'type'    => 'text',
					'desc'    => __( 'Enter your Tenant ID here', 'huxloe-shipping' ),
					'id'      => 'huxloe_shipping_tenant_id',
					'default' => '',
				),
				array(
					'title'   => __( 'User ID', 'huxloe-shipping' ),
					'type'    => 'text',
					'desc'    => __( 'Enter your User ID here', 'huxloe-shipping' ),
					'id'      => 'huxloe_shipping_user_id',
					'default' => '',
				),
				array(
					'title'   => __( 'IOSS Number', 'huxloe-shipping' ),
					'type'    => 'text',
					'desc'    => __(
						'Enter your IOSS Number here (Optional)',
						'huxloe-shipping'
					),
					'id'      => 'huxloe_shipping_ioss_number',
					'default' => '',
				),
				array(
					'title'   => __( 'EORI Number', 'huxloe-shipping' ),
					'type'    => 'text',
					'desc'    => __(
						'Enter your EORI Number here (Optional)',
						'huxloe-shipping'
					),
					'id'      => 'huxloe_shipping_eori_number',
					'default' => '',
				),
				array(
					'title'   => __( 'VAT Number', 'huxloe-shipping' ),
					'type'    => 'text',
					'desc'    => __(
						'Enter your VAT Number here (Optional)',
						'huxloe-shipping'
					),
					'id'      => 'huxloe_shipping_vat_number',
					'default' => '',
				),
				array(
					'title'   => __( 'Enable Debug Log', 'huxloe-shipping' ),
					'desc'    => __(
						'Enable or disable debug logging for shipping.',
						'huxloe-shipping'
					),
					'id'      => 'huxloe_shipping_enable_log',
					'type'    => 'checkbox',
					'default' => 'no', // Set default value
				),
				array(
					'type' => 'sectionend',
					'id'   => 'huxloe_shipping_integration_settings',
				),
			);
			return $custom_settings;
		}

		/**
		 * Add extra fields.
		 *
		 * @param  array $settings Settings.
		 * @return array
		 */
		public function huxloe_shipping_instance_form_add_extra_fields( $settings ) {
			$jsonData    = file_get_contents(
				plugin_dir_path( __FILE__ ) . 'service_codes.json'
			);
			$decodedData = json_decode(
				$jsonData,
				true
			); // Decoding JSON string to PHP array

			$formattedArray = array();

			foreach ( $decodedData as $item ) {
				$service_code                    = $item['service_code'];
				$formattedArray[ $service_code ] = $item['carrier'] . ' - ' . $item['service_description'] . ' (' . $item['service_code'] . ')';
			}

			$settings['service_code'] = array(
				'title'       => __( 'Shipping Service', 'huxloe-shipping' ),
				'type'        => 'select',
				'class'       => 'wc-enhanced-select',
				'desc_tip'    => true,
				'options'     => $formattedArray,
				'description' => __( 'Choose a shipping service for this shipping method.', 'huxloe-shipping' ),
			);

			$settings['duty_percentage'] = array(
				'title' => __( 'Duty Percentage', 'huxloe-shipping' ),
				'type'  => 'number',
				'class' => 'wc-enhanced-select',
			);

			return $settings;
		}

		/**
		 * Add form fields filters.
		 *
		 * @return void
		 */
		public function huxloe_shipping_instance_form_fields_filters() {
			if ( class_exists( 'WC_Shipping' ) ) {
				$shipping_methods = WC()->shipping->get_shipping_methods();
				foreach ( $shipping_methods as $shipping_method ) {
					add_filter(
						'woocommerce_shipping_instance_form_fields_' . $shipping_method->id,
						array( $this, 'huxloe_shipping_instance_form_add_extra_fields' )
					);
				}
			}
		}
	}
	$GLOBALS['huxloe_shipping_calc'] = new Huxloe_Shipping_Calc();
}
