<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

// High-performance order storage (recommended)
add_filter( 'manage_woocommerce_page_wc-orders_columns', 'huxloe_add_packing_slip_column' );
// WordPress posts storage
add_filter( 'manage_edit-shop_order_columns', 'huxloe_add_packing_slip_column' );
function huxloe_add_packing_slip_column( $columns ) {
	$columns['packing_slip'] = esc_html__( 'Tracking Number', 'huxloe-shipping' );
	return $columns;
}

// High-performance order storage (recommended)
add_action( 'manage_woocommerce_page_wc-orders_custom_column', 'huxloe_packing_slip_column_content', 5, 2 );
// WordPress posts storage
add_action( 'manage_shop_order_posts_custom_column', 'huxloe_packing_slip_column_content', 5, 2 );
function huxloe_packing_slip_column_content( $column, $post_id ) {
	global $post;

	if ( 'packing_slip' === $column ) {
		$order_id = is_object( $post_id ) ? $post_id->get_id() : $post_id;
		$consigN  = get_post_meta( $order_id, 'huxloe_consigmentNumber', true );
		if ( ! empty( $consigN ) ) {
			echo esc_html( __( 'Consigment No: ', 'huxloe-shipping' ) ) . esc_html( $consigN );
		} else {
			echo '<button class="button generate-packing-slip" data-order-id="' . esc_attr( $order_id ) . '">' . esc_html( __( 'Generate Label', 'huxloe-shipping' ) ) . '<span class="spinner" style="display:none;"></span></button>';
		}
	}
}

// Add custom button to the order actions toolbar
add_action( 'woocommerce_order_actions_end', 'huxloe_add_wc_order_action_button', 100, 1 );
function huxloe_add_wc_order_action_button( $order_id ) {
	$consigmentNumber = get_post_meta( $order_id, 'huxloe_consigmentNumber', true );
	$screen           = get_current_screen();
	if ( $screen
		&& $screen->id === 'shop_order' || $screen->id === 'woocommerce_page_wc-orders'
	) { ?>
<li class="wide">
	<button type="button" class="re-generate-packing-slip button" data-order-id="<?php echo esc_attr( $order_id ); ?>"
	id="huxloe_shipping_order_action"><?php esc_html_e( 'Regenerate Label', 'huxloe-shipping' ); ?><span class="spinner"
		style="display:none;"></span></button>
</li>
		<?php
	}
}

add_action( 'admin_enqueue_scripts', 'huxloe_enqueue_packing_slip_scripts' );
function huxloe_enqueue_packing_slip_scripts( $hook ) {
	wp_enqueue_style( 'huxloe-admin-css', plugin_dir_url( __FILE__ ) . 'css/huxloe-admin.css', array(), '1.0.1', 'all' );

	if ( 'edit.php' !== $hook && isset( $_GET['post_type'] ) && 'shop_order' === sanitize_text_field( $_GET['post_type'] ) ) {
		return;
	}

	wp_enqueue_script( 'huxloe-admin-js', plugin_dir_url( __FILE__ ) . 'js/huxloe-admin.js', array( 'jquery' ), '1.0.1', true );

	wp_localize_script(
		'huxloe-admin-js',
		'HuxloeAjax',
		array(
			'ajaxurl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'huxloe_generate_packing_slip' ),
		)
	);
}

add_action( 'wp_ajax_generate_packing_slip', 'huxloe_handle_generate_packing_slip' );
function huxloe_handle_generate_packing_slip() {
	// Check if the request is valid with nonce verification
	$valid_request = (
		isset( $_POST['security'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['security'] ) ), 'huxloe_generate_packing_slip' )
	);

	if ( ! $valid_request ) {
		// The request is invalid, return an error
		wp_send_json_error( __( 'Something Wrong', 'huxloe-shipping' ) );
	}

	$order_id = isset( $_POST['order_id'] ) ? intval( $_POST['order_id'] ) : 0;
	if ( $order_id === 0 ) {
		wp_send_json_error( __( 'Invalid order ID', 'huxloe-shipping' ) );
	}

	$response = huxloe_send_payload( $order_id );

	$error_messages = array();

	if ( is_wp_error( $response ) ) {
		$error_message = $response->get_error_message();
		wp_send_json_error( sprintf( __( 'Something went wrong: %s', 'huxloe-shipping' ), $error_message ) );
	} elseif ( is_string( $response ) && strpos( $response, 'Error: {' ) !== false ) {
			$error_data = json_decode( substr( $response, strpos( $response, '{' ) ), true );
		foreach ( $error_data as $error ) {
			$error_messages = array_merge( $error_messages, $error );
		}
			wp_send_json_error( $error_messages );
	} else {
		wp_send_json_success( $response );
	}
	wp_die();
}

function huxloe_send_payload( $order_id ) {
	// Sanitize and validate order_id
	$order_id = intval( $order_id );
	if ( $order_id <= 0 ) {
		return new WP_Error( 'invalid_order_id', __( 'Invalid order ID', 'huxloe-shipping' ) );
	}

	$order = wc_get_order( $order_id );

	$dt = new DateTime(); // create a DateTime object for "now"
	$dt->add( new DateInterval( 'P1D' ) ); // add 1 day
	$dispatchDate = $dt->format( 'Y-m-d\TH:i:s.v\Z' ); // format it as desired

	$serviceCode = '';

	// Get the instance ID from the order's shipping methods
	$order_instance_id = null; // Initialize the variable

	// Loop through order shipping methods to find the instance ID
	foreach ( $order->get_shipping_methods() as $item_id => $shipping_method ) {
		$order_instance_id = intval( $shipping_method->get_instance_id() );
		break; // Get the first instance ID (if there are multiple)
	}

	// Get all your existing shipping zones IDS
	$zone_ids       = array_keys( array( '' ) + WC_Shipping_Zones::get_zones() );
	$vatPercentage  = 0;
	$dutyPercentage = 0;
	// Loop through shipping Zones IDs
	foreach ( $zone_ids as $zone_id ) {
		// Get the shipping Zone object
		$shipping_zone = new WC_Shipping_Zone( $zone_id );

		// Get all shipping method values for the shipping zone
		$shipping_methods = $shipping_zone->get_shipping_methods( true, 'values' );
		// Loop through each shipping methods set for the current shipping zone
		foreach ( $shipping_methods as $instance_id => $shipping_method ) {
			// Match order instance ID with the loop's $instance_id
			if ( $order_instance_id === $instance_id ) {
				if ( $shipping_method->method_title == $order->get_shipping_method() && $shipping_method->instance_id == $instance_id ) {
					$option_name  = 'woocommerce_' . $shipping_method->id . '_' . $instance_id . '_settings';
					$shippingData = get_option( $option_name );

					$serviceCode    = $shippingData['service_code'];
					$vatPercentage  = ( isset( $shippingData['vat_percentage'] ) ) ? $shippingData['vat_percentage'] : 0;
					$dutyPercentage = ( isset( $shippingData['duty_percentage'] ) ) ? $shippingData['duty_percentage'] : 0;
					break;
				}
			}
		}
	}

	$shop_country = WC()->countries->get_base_country();
	$shipping     = $order->get_address( 'shipping' );

	$order_items  = $order->get_items();
	$items        = array();
	$total_weight = 0;
	$total_length = 0;
	$total_height = 0;
	$total_width  = 0;
	$contents     = array();
	foreach ( $order_items as $item ) {
		// Assuming a simple product type for this example
		$product = $item->get_product();

		$product_id     = $item->get_product_id();
		$product_weight = $product->get_weight() ? $product->get_weight() * 1000 : 10000;
		$contents[]     = sanitize_text_field( $item->get_name() );

		// Assuming $itemValue and $vatPercentage might have mixed data types
		$itemValue     = is_numeric( $product->get_price() ) ? (float) $product->get_price() * 100 : 10000;
		$vatPercentage = is_numeric( $vatPercentage ) ? (float) $vatPercentage : 0.0;

		// dutyPercentage calculation
		$dutyPercentage = is_numeric( $dutyPercentage ) ? (float) $dutyPercentage : 0.0;

		// Then perform the calculation after ensuring numeric compatibility
		$vatValue    = ( $itemValue / 100 ) * $vatPercentage;
		$dutyValue   = ( $itemValue / 100 ) * $dutyPercentage;
		$paymentType = ( ! empty( $dutyValue ) ) ? true : false;

		$manufacturer = sanitize_text_field( $product->get_meta( '_huxloe_country_manufacturer', true ) );
		$items[]      = array(
			'skuCode'               => sanitize_text_field( $product->get_sku() ),
			'hsCode'                => sanitize_text_field( $product->get_meta( '_hs_code', true ) ),
			'quantity'              => intval( $item->get_quantity() ),
			'productDescription'    => sanitize_text_field( $item->get_name() ),
			'vatValue'              => $vatValue,
			'dutyValue'             => (int) $dutyValue,
			'weight'                => $product_weight,
			'countryOfManufacturer' => $manufacturer,
			'itemValue'             => $itemValue,
		);

		if ( get_option( 'huxloe_shipping_enable_log' ) === 'yes' ) {
			wc_get_logger()->debug( '==== Product ID: ' . $product_id . ' Data Start ====', array( 'source' => 'huxloe-shipping' ) );

			wc_get_logger()->debug( 'Product Name: ' . sanitize_text_field( $product->get_name() ), array( 'source' => 'huxloe-shipping' ) );

			wc_get_logger()->debug( 'Product SKU: ' . sanitize_text_field( $product->get_sku() ), array( 'source' => 'huxloe-shipping' ) );

			wc_get_logger()->debug( 'Product HS Code: ' . sanitize_text_field( $product->get_meta( '_hs_code', true ) ), array( 'source' => 'huxloe-shipping' ) );

			wc_get_logger()->debug( 'Product Manufacturer: ' . sanitize_text_field( $product->get_meta( '_huxloe_country_manufacturer', true ) ), array( 'source' => 'huxloe-shipping' ) );

			wc_get_logger()->debug( 'Product Qty: ' . intval( $item->get_quantity() ), array( 'source' => 'huxloe-shipping' ) );

			wc_get_logger()->debug( 'Product Weight : ' . (float) $product_weight, array( 'source' => 'huxloe-shipping' ) );

			wc_get_logger()->debug( 'Product Height : ' . (float) $product->get_height(), array( 'source' => 'huxloe-shipping' ) );

			wc_get_logger()->debug( 'Product Width : ' . (float) $product->get_width(), array( 'source' => 'huxloe-shipping' ) );

			wc_get_logger()->debug( 'Product Length : ' . (float) $product->get_length(), array( 'source' => 'huxloe-shipping' ) );

			wc_get_logger()->debug( '==== Product ID: ' . $product_id . " Data End ===\n", array( 'source' => 'huxloe-shipping' ) );
		}

		$total_weight += floatval( $product_weight * $item->get_quantity() );
		$total_height += floatval( floatval( $product->get_height() ) * $item->get_quantity() );
		$total_width  += floatval( floatval( $product->get_width() ) * $item->get_quantity() );
		$total_length += floatval( floatval( $product->get_length() ) * $item->get_quantity() );
	}

	$recipientDetails = array(
		'businessName'  => '', // Assuming no business name for now
		'consigneeName' => sanitize_text_field( $shipping['first_name'] . ' ' . sanitize_text_field( $shipping['last_name'] ) ),
		'address1'      => sanitize_text_field( $shipping['address_1'] ),
		'address2'      => sanitize_text_field( $shipping['address_2'] ),
		'address3'      => '', // Not typically used in WooCommerce, might require custom field
		'city'          => sanitize_text_field( $shipping['city'] ),
		'state'         => sanitize_text_field( $shipping['state'] ),
		'town'          => '',
		'postCode'      => sanitize_text_field( $shipping['postcode'] ),
		'countryCode'   => sanitize_text_field( $shipping['country'] ),
		'email'         => sanitize_email( $order->get_billing_email() ),
		'mobilePhoneNo' => sanitize_text_field( $order->get_billing_phone() ),
	);

	$url = 'https://label.svc.huxloe360.com/api/v1.0/order/evri';

	$headers = array(
		'x-tenant-id'  => sanitize_text_field( get_option( 'huxloe_shipping_tenant_id' ) ),
		'x-api-key'    => sanitize_text_field( get_option( 'huxloe_shipping_api_key' ) ),
		'Content-Type' => 'application/json',
	);

	$body = array(
		'userID'           => sanitize_text_field( get_option( 'huxloe_shipping_user_id' ) ),
		'reference1'       => 'Order #' . $order_id,
		'despatchDate'     => sanitize_text_field( $dispatchDate ),
		'currencyCode'     => sanitize_text_field( get_woocommerce_currency() ),
		'originOfParcel'   => sanitize_text_field( $shop_country ),
		'serviceCode'      => sanitize_text_field( $serviceCode ),
		'print'            => false,
		'recipientDetails' => $recipientDetails,
	);

	$common_parcel_data = array(
		'depth'           => $total_height,
		'width'           => $total_width,
		'length'          => $total_length,
		'contents'        => sanitize_text_field( implode( ',', $contents ) ),
		'weight'          => $total_weight,
		'quantityOfItems' => intval( $order->get_item_count() ),
		'value'           => number_format( ( $order->get_total() * 100 ), 0, '.', '' ),
	);

	if ( $shop_country !== $shipping['country'] ) { // International Shipping
		$body['signatureRequired']   = false;
		$body['specialInstructions'] = '';
		$body['customs']             = array(
			'ioss'          => sanitize_text_field( get_option( 'huxloe_shipping_ioss_number' ) ),
			'eori'          => sanitize_text_field( get_option( 'huxloe_shipping_eori_number' ) ),
			'vat'           => sanitize_text_field( get_option( 'huxloe_shipping_vat_number' ) ),
			'paymentMethod' => $paymentType,
			'exportReason'  => 'sale',
		);
		$body['parcels']             = array(
			array_merge( array( 'items' => $items ), $common_parcel_data ),
		);
	} else { // Domestic Shipping
		$body['signatureRequired']   = false;
		$body['specialInstructions'] = 'instructions';
		$body['parcels']             = array(
			$common_parcel_data,
		);
	}

	$response = wp_remote_post(
		$url,
		array(
			'headers'     => $headers,
			'body'        => json_encode( $body ),
			'timeout'     => 60,
			'redirection' => 10,
			'httpversion' => '1.1',
			'blocking'    => true,
			'cookies'     => array(),
		)
	);

	if ( get_option( 'huxloe_shipping_enable_log' ) === 'yes' ) {
		wc_get_logger()->debug( 'Log Start: Country ' . sanitize_text_field( $shipping['country'] ), array( 'source' => 'huxloe-shipping' ) );
		wc_get_logger()->debug( 'Payload: ' . print_r( $body, true ), array( 'source' => 'huxloe-shipping' ) );
		wc_get_logger()->debug( 'Log End: Country ' . sanitize_text_field( $shipping['country'] ), array( 'source' => 'huxloe-shipping' ) );
	}

	if ( is_wp_error( $response ) ) {
		return $response->get_error_message();
	} else {
		$body = json_decode( wp_remote_retrieve_body( $response ) );
		if ( isset( $body->errors ) ) {
			return __( 'Error: ', 'huxloe-shipping' ) . json_encode( $body->errors );
		} else {
			$consigN = $body->records->labels[0]->consigmentNumber;
			update_post_meta( $order_id, 'huxloe_consigmentNumber', sanitize_text_field( $consigN ) );
			$order = wc_get_order( $order_id );
			$order->add_order_note( sprintf( __( 'Shipping label generated, Consigment Number: %s', 'huxloe-shipping' ), $consigN ) );
			return sprintf( __( 'Shipping label generated, Consigment Number: %s', 'huxloe-shipping' ), $consigN );
		}
	}
}

function huxloe_woocommerce_product_data_fields() {
	global $post;
	$hs_code = get_post_meta( $post->ID, '_hs_code', true );

	echo '<div class="options_group">';
	woocommerce_wp_text_input(
		array(
			'id'          => '_hs_code',
			'label'       => __( 'HS Code', 'huxloe-shipping' ),
			'placeholder' => '',
			'desc_tip'    => 'true',
			'description' => __( 'Enter the HS Code here.', 'huxloe-shipping' ),
			'value'       => esc_attr( $hs_code ),
		)
	);

	if ( class_exists( 'WooCommerce' ) ) {
		// Ensure that WooCommerce is loaded to use its functions
		$countries          = new WC_Countries();
		$excluded_countries = $countries->get_countries( 'option', 'exclude=GB' );

		$selected_country = get_post_meta( $post->ID, '_huxloe_country_manufacturer', true );

		woocommerce_wp_select(
			array(
				'id'          => '_huxloe_country_manufacturer',
				'label'       => __( 'Country of manufacturer', 'huxloe-shipping' ),
				'placeholder' => '',
				'desc_tip'    => 'true',
				'description' => __( 'Choose a manufacturer country', 'huxloe-shipping' ),
				'value'       => esc_attr( $selected_country ),
				'options'     => array( '' => __( 'Select a country', 'huxloe-shipping' ) ) + $excluded_countries,
			)
		);
	}

	echo '</div>';
}
add_action( 'woocommerce_product_options_sku', 'huxloe_woocommerce_product_data_fields' );

add_action( 'woocommerce_product_options_general_product_data', 'huxloe_add_product_nonce_field' );
function huxloe_add_product_nonce_field() {
	wp_nonce_field( 'huxloe_woocommerce_product_meta', 'huxloe_woocommerce_product_nonce' );
}

function huxloe_woocommerce_process_product_meta( $post_id ) {
	if ( ! isset( $_POST['huxloe_woocommerce_product_nonce'] ) ||
		! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['huxloe_woocommerce_product_nonce'] ) ), 'huxloe_woocommerce_product_meta' )
	) {
		return;
	}

	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	if ( isset( $_POST['_hs_code'] ) ) {
		update_post_meta( $post_id, '_hs_code', sanitize_text_field( $_POST['_hs_code'] ) );
	}

	if ( isset( $_POST['_huxloe_country_manufacturer'] ) ) {
		update_post_meta( $post_id, '_huxloe_country_manufacturer', sanitize_text_field( $_POST['_huxloe_country_manufacturer'] ) );
	}
}
add_action( 'woocommerce_process_product_meta', 'huxloe_woocommerce_process_product_meta' );

function huxloe_woocommerce_variation_settings_fields( $loop, $variation_data, $variation ) {
	$hs_code = get_post_meta( $variation->ID, '_hs_code', true );
	if ( empty( $hs_code ) ) {
		$hs_code = get_post_meta( $variation->post_parent, '_hs_code', true );
	}

	woocommerce_wp_text_input(
		array(
			'id'          => '_hs_code[' . esc_attr( $variation->ID ) . ']',
			'label'       => __( 'HS Code', 'huxloe-shipping' ),
			'placeholder' => '',
			'desc_tip'    => 'true',
			'description' => __( 'Enter the HS Code for this variation.', 'huxloe-shipping' ),
			'value'       => esc_attr( $hs_code ),
		)
	);

	if ( class_exists( 'WooCommerce' ) ) {
		// Ensure that WooCommerce is loaded to use its functions
		$countries          = new WC_Countries();
		$excluded_countries = $countries->get_countries( 'option', 'exclude=GB' );

		$selected_country = get_post_meta( $variation->ID, '_huxloe_country_manufacturer', true );

		woocommerce_wp_select(
			array(
				'id'          => '_huxloe_country_manufacturer[' . esc_attr( $variation->ID ) . ']',
				'label'       => __( 'Country of manufacturer', 'huxloe-shipping' ),
				'placeholder' => '',
				'desc_tip'    => 'true',
				'description' => __( 'Choose a manufacturer country for this variation.', 'huxloe-shipping' ),
				'value'       => esc_attr( $selected_country ),
				'options'     => array( '' => __( 'Select a country', 'huxloe-shipping' ) ) + $excluded_countries,
			)
		);
	}
}
add_action( 'woocommerce_product_after_variable_attributes', 'huxloe_woocommerce_variation_settings_fields', 10, 3 );

function huxloe_woocommerce_save_variation_settings_fields( $variation_id, $loop ) {
	if ( ! isset( $_POST['huxloe_woocommerce_product_nonce'] ) ||
		! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['huxloe_woocommerce_product_nonce'] ) ), 'huxloe_woocommerce_product_meta' )
	) {
		return;
	}

	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$hs_code = isset( $_POST['_hs_code'][ $variation_id ] ) ? sanitize_text_field( $_POST['_hs_code'][ $variation_id ] ) : '';
	if ( isset( $hs_code ) ) {
		update_post_meta( $variation_id, '_hs_code', sanitize_text_field( $hs_code ) );
	}

	$country_manufacturer = isset( $_POST['_huxloe_country_manufacturer'][ $variation_id ] ) ? sanitize_text_field( $_POST['_huxloe_country_manufacturer'][ $variation_id ] ) : '';
	if ( isset( $country_manufacturer ) ) {
		update_post_meta( $variation_id, '_huxloe_country_manufacturer', sanitize_text_field( $country_manufacturer ) );
	}
}
add_action( 'woocommerce_save_product_variation', 'huxloe_woocommerce_save_variation_settings_fields', 10, 2 );

// Bulk Action
// Add a new bulk action to the order list
function huxloe_admin_order_bulk_actions( $actions ) {
	$actions['huxloe_generate_slips'] = __( 'Generate Huxloe Labels', 'huxloe-shipping' );
	return $actions;
}
// WordPress posts storage
add_filter( 'bulk_actions-edit-shop_order', 'huxloe_admin_order_bulk_actions', 20, 1 );
// High-performance order storage
add_filter( 'bulk_actions-woocommerce_page_wc-orders', 'huxloe_admin_order_bulk_actions', 20, 1 );

function huxloe_handle_bulk_action_edit_shop_order( $redirect_to, $action, $post_ids ) {
	// Sort post_ids in ascending order
	sort( $post_ids );

	if ( 'huxloe_generate_slips' !== $action ) {
		return $redirect_to;
	}

	$error_orders   = array(); // Array to store orders with errors
	$success_orders = array(); // Initialize success orders array

	foreach ( $post_ids as $post_id ) {
		// Sanitize post_id
		$post_id = intval( $post_id );

		// Here you can handle each order ID as needed
		$response = huxloe_send_payload( $post_id );
		if ( is_wp_error( $response ) ) {
			// If there's a WP_Error, log and handle the error
			$error_orders[] = $post_id;
		} elseif ( is_string( $response ) && strpos( $response, 'Error: {' ) !== false ) {
			// If there's an error in the response, log and handle the error
			$error_orders[] = $post_id;
			// Log the error response
		} else {
			// If no error, add the order ID to the success_orders array
			$success_orders[] = $post_id;
		}
	}

	// Add query args based on success and error counts
	$redirect_to = add_query_arg(
		array(
			'huxloe_generate_slips_success'   => $success_count,
			'huxloe_generate_slips_error'     => $error_count,
			'huxloe_generate_slips_error_ids' => sanitize_text_field( $error_order_ids ), // Adding error order IDs
		),
		$redirect_to
	);

	return $redirect_to;
}
// WordPress posts storage
add_filter( 'handle_bulk_actions-edit-shop_order', 'huxloe_handle_bulk_action_edit_shop_order', 10, 3 );

// High-performance order storage
add_filter( 'handle_bulk_actions-woocommerce_page_wc-orders', 'huxloe_handle_bulk_action_edit_shop_order', 10, 3 );

function huxloe_bulk_action_admin_notice() {
	$error_count = 0; // Initialize error count

	// For Success Order Display
	if ( ! empty( $_REQUEST['huxloe_generate_slips_success'] ) ) {
		$order_count = intval( sanitize_text_field( $_REQUEST['huxloe_generate_slips_success'] ) );
		printf(
			'<div id="message" class="updated fade"><p>' .
			esc_html(
				_n(
					'Processed %s order.',
					'Processed %s orders.',
					$order_count,
					'huxloe-shipping'
				)
			) . '</p></div>',
			esc_html( $order_count )
		);
	}

	// For Errors Order Display
	if ( ! empty( $_REQUEST['huxloe_generate_slips_error'] ) ) {
		$error_orders = ! empty( $_REQUEST['huxloe_generate_slips_error_ids'] ) ? array_map( 'sanitize_text_field', explode( ',', sanitize_text_field( $_REQUEST['huxloe_generate_slips_error_ids'] ) ) ) : array();
		$error_count  = count( $error_orders );
	}

	if ( $error_count > 0 ) {
		$error_message = sprintf(
			esc_html(
				_n(
					'One order encountered an API response error. Please check manually.',
					'%d orders encountered API response errors. Please check manually.',
					$error_count,
					'huxloe-shipping'
				)
			),
			esc_html( $error_count )
		);

		// Fetching and displaying error order IDs
		if ( $error_count > 0 ) {
			$error_order_ids = esc_html( implode( ', ', $error_orders ) );
			$error_message  .= ' ' . sprintf( esc_html__( 'Order IDs: %s', 'huxloe-shipping' ), $error_order_ids );
		}

		// Add plural form for orders
		if ( $error_count > 1 ) {
			$error_count_text = esc_html( _n( 'order', 'orders', $error_count, 'huxloe-shipping' ) );
			$error_message   .= sprintf( ' (%d %s)', esc_html( $error_count ), $error_count_text );
		}

		printf( '<div class="error notice"><p>%s</p></div>', wp_kses_post( $error_message ) );
	}
}
add_action( 'admin_notices', 'huxloe_bulk_action_admin_notice' );
