<?php
/**
 * CFM Frontend Receipt.
 *
 * This file deals with the rendering the data entered on checkout on the receipt page.
 *
 * @package CFM
 * @subpackage Frontend
 * @since 2.1
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


/**
 * CFM Frontend Receipt.
 *
 * @since 2.1
 * @access public
 */
class CFM_Frontend_Receipt {

	/**
	 * Add out actions
	 *
	 * @since 2.1
	 * @access public
	 *
	 * @return void
	 */
	public function __construct() {
		add_action( 'edd_payment_receipt_before', array( $this, 'render' ), 10, 2 );
	}

	/**
	 * Render fields on the receipt.
	 *
	 * @since 2.1
	 * @access public
	 *
	 * @return void
	 */
	public function render( $payment, $receipt_args ) {

		if( ! edd_get_option( 'cfm-receipt-show-info' ) ) {
			return;
		}

		$form_id = get_option( 'cfm-checkout-form', false );

		// if we can't find the checkout form, echo an error
		if ( empty( $form_id ) ) {
			return;
		}

		$form = new CFM_Checkout_Form( $form_id, 'id', $payment->ID );

		if( ! empty( $form->fields ) ) {

			echo '<tr class="edd-cfm-receipt-fields"><th colspan="2"><strong>' . edd_get_option( 'cfm-receipt-header', __( 'Information', 'edd_cfm' ) ) . '</strong></th></tr>';

			foreach( $form->fields as $field ) {

				if( ! $this->show_field( $field ) ) {
					continue;
				}

				echo '<tr class="edd-cfm-receipt-field">';

					echo '<td id="edd-cfm-field-' . $field->name() . '">' . $field->get_label() . '</td>';

						$value = $field->export_data( $payment->ID, get_current_user_id() );

						if( 'file_upload' == $field->characteristics['template'] ) {

							$value = '<a href="' . esc_url( $value ) . '" target="_blank">' . basename( $value ) . '</a>';

						}

					echo '<td>' . $value . '</td>';

				echo '</tr>';

			}

		}

	}

	/**
	 * Determines if a field should be shown on the receipt.
	 *
	 * This looks at the show_on_receipt supports argument while also allowing the fields to be enabled / disabled with a filter
	 *
	 * @since 2.1
	 * @access public
	 * @param $field CFM_Field object
	 *
	 * @return bool
	 */
	public function show_field( $field ) {

		$ret = isset( $field->supports['show_on_receipt'] ) ? $field->supports['show_on_receipt'] : true;

		return apply_filters( 'cfm_receipt_show_field', $ret, $field );
	}

}