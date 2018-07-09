<?php
class CFM_Form {

	/** @var string The form ID. */
	public $id = null;

	/** @var array Array of fields */
	public $fields = array();

	/** @var string The form's name. */
	public $name = null;

	/** @var string Title of the form */
	public $title = '';

	/** @var int The id of the object the form value is saved to. This is the payment ID. */
	public $payment_id = -2;

	/** @var int The id of the object the form value is saved to. This is the payment ID. */
	public $user_id = -2;

	/** @var array Array of things it supports */
	public $supports = array();

	/** @var array Array of characteristics of the form that need to be stored in the database */
	public $characteristics = array();

	/** Make form object */
	// key can either be the id (default) or name of form
	// by is id for form id, name for get by form name
	public function __construct( $key = 0, $by = 'id', $payment_id = -2, $user_id = -2 ) {
		if ( $key === 0 ) { // let's fallback to login form if something catastrophic happens
			$key = get_option( 'cfm-checkout-form', false );
		}
		if ( $by === 'name' ) {
			$key = $this->get_form_id_by_name( $key );
			if ( !$key ) {
				return;
			}
		}

		$this->id              = $key;
		$this->payment_id      = $payment_id;
		if ( $user_id === -2 ){
			if ( $payment_id !== -2 ){
				$payment = new EDD_Payment( $payment_id );
				$user_id = $payment->user_id;
			}
		}
		$this->user_id         = $user_id;

		$characteristics       = get_post_meta( $key, 'cfm-characteristics', true );
		$characteristics       = !empty( $characteristics ) ? $characteristics : $this->characteristics;
		$this->characteristics = apply_filters( 'cfm_form_construct_characteristics', $characteristics, $this );

		$fields                = get_post_meta( $key, 'cfm-form', true );
		$fields                = !empty( $fields ) ? $fields : array();
		$fields                = apply_filters( 'cfm_form_construct_fields', $fields, $this );

		$this->load_fields( $fields );

		// use this to manipulate things like supports on instantiation
		do_action( 'cfm_form_after_construct', $this );
		do_action( 'cfm_' . $this->name() . '_form_after_construct', $this );

		$this->set_title();

		$this->extending_constructor();
	}

	public function extending_constructor() {
		// declared in extending form if wanted
	}

	public function get_fields( ) {
		$fields = $this->fields;
		$fields = apply_filters( 'cfm_get_' . $this->name() . '_form_fields', $fields, $this );
		return $fields;
	}

	public function load_fields( $fields = array() ) {

		$final = array();
		if ( !empty( $fields ) ) {
			foreach ( $fields as $key => $value ) {
				if ( isset( $value['template'] ) && !empty( $value['template'] )  ) {
					$class = EDD_CFM()->helper->get_field_class_by_name( $value['template'] );
					if ( $class != '' && ! empty( $value['name'] ) ) {
						$final[ $value['name'] ] = new $class( $value, $this->id, $this->payment_id, $this->user_id );
					} else {
						$final[ $value['name'] ] = $value;
					}
				}
			}
		}
		$final = apply_filters( 'cfm_load_' . $this->name() . '_form_fields', $final, $this );
		$this->fields = $final;
	}

	/** Sets things this field supports. Hint: use $field->supports to get the things the field already supports.
	 If adding a support do something like $supports = $field->supports; $supports['something'] = true; $field->add_support( $support );
	 If removing do $supports = $field->supports; unset($supports['something']); $field->add_support( $support );
	 */
	public function add_support( $supports ) {
		$this->supports = $supports;
	}

	public function render_form( $current_user_id = -2 ) {
		$output = '';
		if ( cfm_is_admin() ) {
			$output .= $this->render_form_admin( $current_user_id );
		} else {
			$output .= $this->render_form_frontend( $current_user_id );
		}
		return $output;
	}

	public function render_form_admin( $current_user_id = -2, $profile = false ) {
		if ( $current_user_id === -2 ) {
			$current_user_id = get_current_user_id();
		}

		// See if can use form
		if ( !$this->can_render_form_admin( $current_user_id, $profile ) ) {
			return __( 'Access denied.', 'edd_cfm' );
		}

		$output = '';

		$output = apply_filters( 'cfm_render_' . $this->name() . '_form_admin_output_before_fields', $output, $this, $current_user_id, $profile );
		do_action( 'cfm_render_' . $this->name() . '_form_admin_before_fields', $this, $current_user_id, $profile );
		do_action( 'cfm_render_form_above_' . $this->name() . '_form', $this->payment_id, $this->user_id );
		$fields = $this->fields;
		$fields = apply_filters( 'cfm_render_' . $this->name() . '_form_admin_fields', $fields, $this, $current_user_id, $profile );

		$count = 0;
		foreach ( $fields as $field ) {
			if ( ! is_object( $field ) ) {
				continue;
			}

			$templates_to_exclude = apply_filters( 'cfm_templates_to_exclude_render_' . $this->name() . '_form_admin', array(), $profile );
			if ( is_array( $templates_to_exclude ) && in_array( $field->template(), $templates_to_exclude ) ) {
				continue;
			} else if ( is_object( $field ) && $profile && ( ! $field->is_meta() || $field->meta_type() !== 'user' ) ){
				continue;
			} else if ( is_object( $field ) && ! $profile && ( ! $field->is_meta() || $field->meta_type() !== 'payment' ) ){
				continue;
			} else {
				$count++;
			};
		}

		if ( !empty( $fields ) && $count > 0 ) {
			$output .= '<div class="cfm-form cfm-' . $this->name() . '-form-div">';

			foreach ( $fields as $field ) {

				$templates_to_exclude = apply_filters( 'cfm_templates_to_exclude_render_' . $this->name() . '_form_admin', array(), $profile );
				if ( is_object( $field ) && is_array( $templates_to_exclude ) && in_array( $field->supports['template'], $templates_to_exclude ) ) {
					continue;
				}

				$output .= apply_filters( 'cfm_render_' . $this->name() . '_form_admin_fields_before_field', '', $field, $this, $current_user_id, $profile );

				if ( is_object( $field ) && $profile && ( ! $field->is_meta() || $field->meta_type() !== 'user' ) ){
					continue;
				}

				if ( is_object( $field ) && ! $profile && ( ! $field->is_meta() || $field->meta_type() !== 'payment' ) ){
					continue;
				}

				if ( is_object( $field ) && method_exists( $field, 'render_field_admin' ) ) {
					$output .= $field->render_field_admin( $current_user_id, $profile );
				}

				$output .= apply_filters( 'cfm_render_' . $this->name() . '_form_admin_fields_after_field', '', $field, $this, $current_user_id, $profile );
			}
			$output .= wp_nonce_field( 'cfm-' . $this->name() .'-form', 'cfm-' . $this->name() .'-form', false, false );
			$output .= '<input type="hidden" name="cfm_form_id" value="' . $this->id . '">';
			$output .= '<input type="hidden" name="cfm_user_id" value="' . $current_user_id . '">';
			$output .= '<input type="hidden" name="cfm_action" value="submit-' . $this->name() . '-form">';
			$output .= '<input type="hidden" name="cfm_profile" value="' . json_encode( $profile ) . '">';
			$output .= '</div>';
		} else {
			$output .= __( 'The form has no custom fields!', 'edd_cfm' );
		}

		do_action( 'cfm_render_' . $this->name() . '_form_admin_after_fields', $this, $current_user_id, $profile );
		do_action( 'cfm_render_form_below_' . $this->name() . '_form', $this->payment_id, $this->user_id );
		$output = apply_filters( 'cfm_render_' . $this->name() . '_form_admin_output_after_fields', $output, $this, $current_user_id, $profile );
		return $output;
	}

	public function render_form_frontend( $current_user_id = -2, $profile = false ) {
		if ( $current_user_id === -2 ) {
			$current_user_id = get_current_user_id();
		}

		// See if can use form
		if ( !$this->can_render_form_frontend( $current_user_id, $profile ) ) {
			return __( 'Access denied.', 'edd_cfm' );
		}

		$output = '';
		$output = apply_filters( 'cfm_render_' . $this->name() . '_form_frontend_output_before_fields', $output, $this, $current_user_id, $profile );
		do_action( 'cfm_render_' . $this->name() . '_form_frontend_before_fields', $this, $current_user_id, $profile );
		do_action( 'cfm_render_form_above_' . $this->name() . '_form', $this->payment_id, $this->user_id, $profile );
		$fields = $this->fields;
		$fields = apply_filters( 'cfm_render_' . $this->name() . '_form_frontend_fields', $fields, $this, $current_user_id, $profile );
		$count = 0;
		foreach ( $fields as $field ) {
			$templates_to_exclude = apply_filters( 'cfm_templates_to_exclude_render_' . $this->name() . '_form_frontend', array(), $profile );
			if ( is_object( $field ) && ( ( is_array( $templates_to_exclude ) && in_array( $field->template(), $templates_to_exclude ) ) || ! $field->is_public() ) ) {
				continue;
			} else if ( is_object( $field ) && $profile && ( ! $field->is_meta() || $field->meta_type() !== 'user' ) ){
				continue;
			} else {
				$count++;
			}
		}

		if ( !empty( $fields ) && $count > 0 ) {
			if ( ! $profile ) {
				$output .= '<fieldset id="edd_checkout_user_info" class="cfm-form"><span><legend>' . __('Personal Info', 'edd_cfm' ) . '</legend></span>';
			} else {
				$output .= '<div class="cfm-form cfm-' . $this->name() . '-form-div">';
			}

			foreach ( $fields as $field ) {
				$templates_to_exclude = apply_filters( 'cfm_templates_to_exclude_render_' . $this->name() . '_form_frontend', array(), $profile );
				if ( is_object( $field ) && ( ( is_array( $templates_to_exclude ) && in_array( $field->template(), $templates_to_exclude ) ) || ! $field->is_public() ) ){
					continue;
				}

				if ( is_object( $field ) && $profile && ( ! $field->is_meta() || $field->meta_type() !== 'user' ) ){
					continue;
				}
				$output .= apply_filters( 'cfm_render_' . $this->name() . '_form_frontend_fields_before_field', '', $field, $this, $current_user_id, $profile );

				if ( is_object( $field ) && method_exists( $field, 'render_field_frontend' ) ) {
					$output .= $field->render_field_frontend( $current_user_id, $profile );
				} else if ( isset( $field['template'] ) ) {
					_cfm_deprecated( 'Outputting using a non CFM Field is deprecated. Support will be removed in 2.1.' );
					ob_start();
					do_action( 'cfm_render_field_' . $field['template'], $this->characteristics, $this->payment_id, '' );
					$output .= ob_get_clean();
				}
				$output .= apply_filters( 'cfm_render_' . $this->name() . '_form_frontend_fields_after_field', '', $field, $this, $current_user_id, $profile );
			}
			$output .= wp_nonce_field( 'cfm-' . $this->name() .'-form', 'cfm-' . $this->name() .'-form', false, false );
			$output .= '<input type="hidden" name="cfm_form_id" value="' . $this->id . '">';
			$output .= '<input type="hidden" name="cfm_user_id" value="' . $current_user_id . '">';
			$output .= '<input type="hidden" name="cfm_action" value="submit-' . $this->name() . '-form">';
			$output .= '<input type="hidden" name="cfm_profile" value="' . json_encode( $profile ) . '">';
			if ( ! $profile ) {
				$output .= '</fieldset>';
			} else {
				$output .= '</div>';
			}
		} else {
			$output .= __( 'The form has no fields!', 'edd_cfm' );
		}

		do_action( 'cfm_render_' . $this->name() . '_form_frontend_after_fields', $this, $current_user_id, $profile );
		do_action( 'cfm_render_form_below_' . $this->name() . '_form', $this->payment_id, $this->user_id );
		$output = apply_filters( 'cfm_render_' . $this->name() . '_form_frontend_output_after_fields', $output, $this, $current_user_id, $profile );
		return $output;
	}

	public function has_fields_to_render( $current_user_id = -2, $profile = false ) {
		if ( cfm_is_admin() ) {
			return $this->has_fields_to_render_admin( $current_user_id, $profile );
		} else {
			return $this->has_fields_to_render_frontend( $current_user_id, $profile );
		}
	}

	public function has_fields_to_render_admin( $current_user_id = -2, $profile = false ) {
		if ( $current_user_id === -2 ) {
			$current_user_id = get_current_user_id();
		}

		// See if can use form
		if ( !$this->can_render_form_admin( $current_user_id, $profile ) ) {
			return false;
		}

		$fields = $this->fields;
		$fields = apply_filters( 'cfm_render_' . $this->name() . '_form_admin_fields', $fields, $this, $current_user_id, $profile );
		$count = 0;

		foreach ( $fields as $field ) {
			if ( ! is_object( $field ) ) {
				continue;
			}

			$templates_to_exclude = apply_filters( 'cfm_templates_to_exclude_render_' . $this->name() . '_form_admin', array(), $profile );
			if ( is_object( $field ) && ( ( is_array( $templates_to_exclude ) && in_array( $field->template(), $templates_to_exclude ) ) ) ) {
				continue;
			} else if ( is_object( $field ) && $profile && ( ! $field->is_meta() || $field->meta_type() !== 'user' ) ){
				continue;
			} else if ( is_object( $field ) && ! $profile && ( ! $field->is_meta() || $field->meta_type() !== 'payment' ) ){
				continue;
			} else {
				$count++;
			}
		}

		if ( !empty( $fields ) && $count > 0 ) {
			return true;
		} else {
			return false;
		}
	}

	public function has_fields_to_render_frontend( $current_user_id = -2, $profile = false ) {
		if ( $current_user_id === -2 ) {
			$current_user_id = get_current_user_id();
		}

		// See if can use form
		if ( !$this->can_render_form_frontend( $current_user_id, $profile ) ) {
			return false;
		}

		$fields = $this->fields;
		$fields = apply_filters( 'cfm_render_' . $this->name() . '_form_frontend_fields', $fields, $this, $current_user_id, $profile );
		$count = 0;
		foreach ( $fields as $field ) {
			$templates_to_exclude = apply_filters( 'cfm_templates_to_exclude_render_' . $this->name() . '_form_frontend', array(), $profile );
			if ( is_object( $field ) && ( ( is_array( $templates_to_exclude ) && in_array( $field->template(), $templates_to_exclude ) ) || ! $field->is_public() ) ) {
				continue;
			} else if ( is_object( $field ) && $profile && ( ! $field->is_meta() || $field->meta_type() !== 'user' ) ){
				continue;
			} else {
				$count++;
			}
		}

		if ( !empty( $fields ) && $count > 0 ) {
			return true;
		} else {
			return false;
		}
	}

	public function validate_form( $values = array(), $current_user_id = -2, $profile = false ) {
		$output = false;
		if ( cfm_is_admin() ) {
			$output = $this->validate_form_admin( $values, $current_user_id, $profile );
		} else {
			$output = $this->validate_form_frontend( $values, $current_user_id, $profile );
		}
		return $output;
	}

	public function validate_form_admin( $values = array(), $current_user_id = -2, $profile = false ) {
		if ( $current_user_id === -2 ) {
			$current_user_id = get_current_user_id();
		}

		if ( !defined( 'DOING_CFM_FORM_SUBMISSION' ) ) {
			define( 'DOING_CFM_FORM_SUBMISSION', $this->id );
		}

		if ( !defined( 'DOING_CFM_FORM_SUBMISSION_LOCATION' ) ) {
			define( 'DOING_CFM_FORM_SUBMISSION_LOCATION', 'admin' );
		}

		$current_user_id  = apply_filters( 'cfm_save_' . $this->name() . '_form_admin_user_id', $current_user_id, $this, $this->payment_id, $this->user_id );
		$values   = apply_filters( 'cfm_save_' . $this->name() . '_form_admin_values', $values, $this, $this->payment_id, $this->user_id );
		$profile   = apply_filters( 'cfm_save_' . $this->name() . '_form_admin_profile', $profile, $this, $this->payment_id, $this->user_id );

		if ( !( cfm_is_admin() ) || ( !isset( $_REQUEST['cfm-' . $this->name() .'-form'] ) || !wp_verify_nonce( $_REQUEST['cfm-' . $this->name() .'-form'], 'cfm-' . $this->name() .'-form' ) ) ) {
			return;
		}

		// See if can save form
		if ( ! $this->can_save_form_admin( $current_user_id, $profile ) ) {
			edd_set_error( 'cfm_unauthorized_save_admin', __( 'You are not permitted to do this.', 'edd_cfm' ) );
			return false;
		}

		do_action( 'cfm_save_' . $this->name() . '_form_admin_values_before_save', $this, $current_user_id, $this->payment_id, $this->user_id );

		$fields = $this->fields;
		$fields = apply_filters( 'cfm_save_' . $this->name() . '_form_admin_fields', $fields,  $this, $current_user_id, $this->payment_id, $this->user_id );

		if ( !empty( $fields ) ) {
			foreach ( $fields as $field ) {
				if ( ! is_object( $field ) ) {
					continue;
				}

				$templates_to_exclude = apply_filters( 'cfm_templates_to_exclude_sanitize_' . $this->name() . '_form_admin', array(), $profile );
				if ( is_array( $templates_to_exclude ) && in_array( $field->supports['template'], $templates_to_exclude ) ) {
					continue;
				}

				if ( is_object( $field ) && $profile && ( ! $field->is_meta() || $field->meta_type() !== 'user' ) ){
					continue;
				}

				if ( is_object( $field ) && ! $profile && ( ! $field->is_meta() || $field->meta_type() !== 'payment' ) ){
					continue;
				}
				$values = $field->sanitize( $values, $this->payment_id, $this->user_id ); // this works like an apply_filters. Simply tack your error onto errors if needed
			}

			foreach ( $fields as $field ) {
				if ( ! is_object( $field ) ) {
					continue;
				}

				$templates_to_exclude = apply_filters( 'cfm_templates_to_exclude_validate_' . $this->name() . '_form_admin', array(), $profile );
				if ( is_array( $templates_to_exclude ) && in_array( $field->supports['template'], $templates_to_exclude ) ) {
					continue;
				}

				if ( is_object( $field ) && $profile && ( ! $field->is_meta() || $field->meta_type() !== 'user' ) ){
					continue;
				}

				if ( is_object( $field ) && ! $profile && ( ! $field->is_meta() || $field->meta_type() !== 'payment' ) ){
					continue;
				}
				$field->validate( $values, $this->payment_id, $this->user_id );
			}

			$this->before_form_error_check_admin( $this->payment_id, $this->user_id, $values, $current_user_id, $profile );
			$errors = edd_get_errors();

			if ( $errors ) {
				return false;
			} else {
				return true;
			}
		} else {
			edd_set_error( 'cfm_no_fields_admin', __( 'There are no fields on the form.', 'edd_cfm' ) );
			return false;
		}
	}

	public function validate_form_frontend( $values = array(), $current_user_id = -2, $profile = false ) {
		if ( $current_user_id === -2 ) {
			$current_user_id = get_current_user_id();
		}

		if ( !defined( 'DOING_CFM_FORM_SUBMISSION' ) ) {
			define( 'DOING_CFM_FORM_SUBMISSION', $this->id );
		}

		if ( !defined( 'DOING_CFM_FORM_SUBMISSION_LOCATION' ) ) {
			define( 'DOING_CFM_FORM_SUBMISSION_LOCATION', 'frontend' );
		}

		$current_user_id  = apply_filters( 'cfm_save_' . $this->name() . '_form_frontend_user_id', $current_user_id, $this, $this->payment_id, $this->user_id );
		$values   = apply_filters( 'cfm_save_' . $this->name() . '_form_frontend_values', $values, $this, $this->payment_id, $this->user_id );
		$profile   = apply_filters( 'cfm_save_' . $this->name() . '_form_frontend_profile', $profile, $this, $this->payment_id, $this->user_id );

		if ( ( cfm_is_admin() ) || ( ( !isset( $_REQUEST['cfm-' . $this->name() .'-form'] ) || !wp_verify_nonce( $_REQUEST['cfm-' . $this->name() .'-form'], 'cfm-' . $this->name() .'-form' ) ) && $profile ) ) {
			return;
		}

		// See if can save form
		if ( ! $this->can_save_form_frontend( $current_user_id, $profile ) ) {
			edd_set_error( 'cfm_unauthorized_save_frontend', __( 'You are not permitted to do this.', 'edd_cfm' ) );
			return false;
		}

		do_action( 'cfm_save_' . $this->name() . '_form_frontend_values_before_save', $this, $current_user_id, $this->payment_id, $this->user_id );

		$fields = $this->fields;
		$fields = apply_filters( 'cfm_save_' . $this->name() . '_form_frontend_fields', $fields,  $this, $current_user_id, $this->payment_id, $this->user_id );

		if ( !empty( $fields ) ) {
			foreach ( $fields as $field ) {
				if ( ! is_object( $field ) ) {
					continue;
				}

				$templates_to_exclude = apply_filters( 'cfm_templates_to_exclude_sanitize_' . $this->name() . '_form_frontend', array(), $profile );
				if ( is_object( $field ) && ( ( is_array( $templates_to_exclude ) && in_array( $field->template(), $templates_to_exclude ) ) || ! $field->is_public() ) ){
					continue;
				}

				if ( is_object( $field ) && $profile && ( ! $field->is_meta() || $field->meta_type() !== 'user' ) ){
					continue;
				}
				$values = $field->sanitize( $values, $this->payment_id, $this->user_id ); // this works like an apply_filters. Simply tack your error onto errors if needed
			}

			foreach ( $fields as $field ) {
				if ( ! is_object( $field ) ) {
					continue;
				}

				$templates_to_exclude = apply_filters( 'cfm_templates_to_exclude_validate_' . $this->name() . '_form_frontend', array(), $profile );
				if ( is_object( $field ) && ( ( is_array( $templates_to_exclude ) && in_array( $field->template(), $templates_to_exclude ) ) || ! $field->is_public() ) ){
					continue;
				}

				if ( is_object( $field ) && $profile && ( ! $field->is_meta() || $field->meta_type() !== 'user' ) ){
					continue;
				}
				$field->validate( $values, $this->payment_id, $this->user_id );
			}

			$this->before_form_error_check_frontend( $this->payment_id, $this->user_id, $values, $current_user_id, $profile );

			$errors = edd_get_errors();
			if ( $errors ) {
				return false;
			} else {
				return true;
			}
		} else {
			edd_set_error( 'cfm_no_fields_frontend', __( 'There are no fields on the form.', 'edd_cfm' ) );
			return false;
		}
	}

	public function save_form( $values = array(), $current_user_id = -2 ) {
		$output = false;
		if ( cfm_is_admin() ) {
			$output = $this->save_form_admin( $values, $current_user_id );
		} else {
			$output = $this->save_form_frontend( $values, $current_user_id );
		}
		return $output;
	}

	public function save_form_admin( $values = array(), $current_user_id = -2, $profile = false ) {
		if ( $current_user_id === -2 ) {
			$current_user_id = get_current_user_id();
		}

		if ( !defined( 'DOING_CFM_FORM_SUBMISSION' ) ) {
			define( 'DOING_CFM_FORM_SUBMISSION', $this->id );
		}

		if ( !defined( 'DOING_CFM_FORM_SUBMISSION_LOCATION' ) ) {
			define( 'DOING_CFM_FORM_SUBMISSION_LOCATION', 'admin' );
		}

		$current_user_id  = apply_filters( 'cfm_save_' . $this->name() . '_form_admin_user_id', $current_user_id, $this, $this->payment_id, $this->user_id );
		$values   = apply_filters( 'cfm_save_' . $this->name() . '_form_admin_values', $values, $this, $this->payment_id, $this->user_id );
		$profile   = apply_filters( 'cfm_save_' . $this->name() . '_form_admin_profile', $profile, $this, $this->payment_id, $this->user_id );

		if ( !( cfm_is_admin() ) || ( !isset( $_REQUEST['cfm-' . $this->name() .'-form'] ) || !wp_verify_nonce( $_REQUEST['cfm-' . $this->name() .'-form'], 'cfm-' . $this->name() .'-form' ) ) ) {
			return;
		}

		// See if can save form
		if ( ! $this->can_save_form_admin( $current_user_id, $profile ) ) {
			return false;
		}

		$errors = edd_get_errors();
		if ( $errors ) {
			return false;
		} else {
			$this->before_form_save( $this->payment_id, $this->user_id, $values, $current_user_id, $profile );
			$fields = $this->fields;
			foreach ( $fields as $field ) {
				if ( ! is_object( $field ) ) {
					continue;
				}

				$templates_to_exclude = apply_filters( 'cfm_templates_to_exclude_sanitize_' . $this->name() . '_form_admin', array(), $profile );
				if ( is_array( $templates_to_exclude ) && in_array( $field->supports['template'], $templates_to_exclude ) ) {
					continue;
				}

				if ( is_object( $field ) && $profile && ( ! $field->is_meta() || $field->meta_type() !== 'user' ) ){
					continue;
				}

				if ( is_object( $field ) && ! $profile && ( ! $field->is_meta() || $field->meta_type() !== 'payment' ) ){
					continue;
				}
				$values = $field->sanitize( $values, $this->payment_id, $this->user_id ); // this works like an apply_filters. Simply tack your error onto errors if needed
			}
			foreach ( $fields as $field ) {

				if ( ! is_object( $field ) ) {
					continue;
				}

				$templates_to_exclude = apply_filters( 'cfm_templates_to_exclude_save_' . $this->name() . '_form_admin', array(), $profile );
				if ( is_array( $templates_to_exclude ) && in_array( $field->supports['template'], $templates_to_exclude ) ) {
					continue;
				}

				if ( is_object( $field ) && $profile && ( ! $field->is_meta() || $field->meta_type() !== 'user' ) ){
					continue;
				}

				if ( is_object( $field ) && ! $profile && ( ! $field->is_meta() || $field->meta_type() !== 'payment' ) ){
					continue;
				}

				$field->save_field_values( $this->payment_id, $this->user_id, $values, $current_user_id );
			}
			$this->after_form_save_admin( $this->payment_id, $this->user_id, $values, $current_user_id, $profile );
			do_action( 'cfm_save_' . $this->name() . '_form_admin_values_after_save', $values, $this, $current_user_id, $this->payment_id, $this->user_id);
			do_action( 'cfm_save_' . $this->name() . '_form_after_admin', $values, $current_user_id );
			do_action( 'cfm_save_' . $this->name() . '_form_values_after_save', $this, $current_user_id, $this->payment_id, $this->user_id );
			return true;
		}
	}

	public function save_form_frontend( $values = array(), $current_user_id = -2, $profile = false ) {
		if ( $current_user_id === -2 ) {
			$current_user_id = get_current_user_id();
		}

		if ( !defined( 'DOING_CFM_FORM_SUBMISSION' ) ) {
			define( 'DOING_CFM_FORM_SUBMISSION', $this->id );
		}

		if ( !defined( 'DOING_CFM_FORM_SUBMISSION_LOCATION' ) ) {
			define( 'DOING_CFM_FORM_SUBMISSION_LOCATION', 'frontend' );
		}

		$current_user_id  = apply_filters( 'cfm_save_' . $this->name() . '_form_frontend_user_id', $current_user_id, $this, $this->payment_id, $this->user_id );
		$values   = apply_filters( 'cfm_save_' . $this->name() . '_form_frontend_values', $values, $this, $this->payment_id, $this->user_id );
		$profile   = apply_filters( 'cfm_save_' . $this->name() . '_form_frontend_profile', $profile, $this, $this->payment_id, $this->user_id );

		if ( ( cfm_is_admin() ) || ( ( !isset( $_REQUEST['cfm-' . $this->name() .'-form'] ) || !wp_verify_nonce( $_REQUEST['cfm-' . $this->name() .'-form'], 'cfm-' . $this->name() .'-form' ) ) && $profile ) ) {
			return;
		}

		// See if can save form
		if ( ! $this->can_save_form_frontend( $current_user_id, $profile ) ) {
			return false;
		}

		$errors = edd_get_errors();
		if ( $errors ) {
			return false;
		} else {
			$this->before_form_save( $this->payment_id, $this->user_id, $values, $current_user_id, $profile );
			$fields = $this->fields;
			foreach ( $fields as $field ) {
				if ( ! is_object( $field ) ) {
					continue;
				}

				$templates_to_exclude = apply_filters( 'cfm_templates_to_exclude_sanitize_' . $this->name() . '_form_frontend', array(), $profile );
				if ( is_object( $field ) && ( ( is_array( $templates_to_exclude ) && in_array( $field->template(), $templates_to_exclude ) ) || ! $field->is_public() ) ){
					continue;
				}

				if ( is_object( $field ) && $profile && ( ! $field->is_meta() || $field->meta_type() !== 'user' ) ){
					continue;
				}
				$values = $field->sanitize( $values, $this->payment_id, $this->user_id ); // this works like an apply_filters. Simply tack your error onto errors if needed
			}
			foreach ( $fields as $field ) {

				if ( ! is_object( $field ) ) {
					continue;
				}

				$templates_to_exclude = apply_filters( 'cfm_templates_to_exclude_save_' . $this->name() . '_form_frontend', array(), $profile );
				if ( is_object( $field ) && ( ( is_array( $templates_to_exclude ) && in_array( $field->template(), $templates_to_exclude ) ) || ! $field->is_public() ) ){
					continue;
				}

				if ( is_object( $field ) && $profile && ( ! $field->is_meta() || $field->meta_type() !== 'user' ) ){
					continue;
				}
				$field->save_field_values( $this->payment_id, $this->user_id, $values, $current_user_id, $profile );
			}
			$this->after_form_save_frontend( $this->payment_id, $this->user_id, $values, $current_user_id, $profile );
			do_action( 'cfm_save_' . $this->name() . '_form_frontend_values_after_save', $values, $this, $current_user_id, $this->payment_id, $this->user_id );
			do_action( 'cfm_save_' . $this->name() . '_form_after_frontend', $values, $current_user_id );
			do_action( 'cfm_save_' . $this->name() . '_form_values_after_save', $this, $current_user_id, $this->payment_id, $this->user_id );
			return true;
		}
	}

	public function before_form_error_check( $payment_id = -2, $user_id = -2, $values = array(), $current_user_id = -2, $profile = false ) {
		if ( cfm_is_admin() ) {
			$this->before_form_error_check_admin( $payment_id, $user_id, $values, $current_user_id, $profile );
		} else {
			$this->before_form_error_check_frontend( $payment_id, $user_id, $values, $current_user_id, $profile );
		}
	}

	public function before_form_error_check_frontend( $payment_id = -2, $user_id = -2, $values = array(), $current_user_id = -2, $profile = false ) {
		if ( $current_user_id === -2 ) {
			$current_user_id = get_current_user_id();
		}
		do_action( 'cfm_before_' . $this->name() . '_form_error_check_action_frontend', $payment_id, $user_id, $values, $current_user_id, $profile );
	}

	public function before_form_error_check_admin( $payment_id = -2, $user_id = -2, $values = array(), $current_user_id = -2, $profile = false ) {
		if ( $current_user_id === -2 ) {
			$current_user_id = get_current_user_id();
		}
		do_action( 'cfm_before_' . $this->name() . '_form_error_check_action_admin', $payment_id, $user_id, $values, $current_user_id, $profile );
	}

	public function before_form_save( $payment_id = -2, $user_id = -2, $values = array(), $current_user_id = -2, $profile = false ) {
		if ( cfm_is_admin() ) {
			$this->before_form_save_admin( $payment_id, $user_id, $values, $current_user_id, $profile );
		} else {
			$this->before_form_save_frontend( $payment_id, $user_id, $values, $current_user_id, $profile );
		}
	}

	public function before_form_save_frontend( $payment_id = -2, $user_id = -2, $values = array(), $current_user_id = -2, $profile = false ) {
		if ( $current_user_id === -2 ) {
			$current_user_id = get_current_user_id();
		}
		do_action( 'cfm_before_' . $this->name() . '_form_save_frontend', $payment_id, $user_id, $values, $current_user_id, $profile );
	}

	public function before_form_save_admin( $payment_id = -2, $user_id = -2, $values = array(), $current_user_id = -2, $profile = false ) {
		if ( $current_user_id === -2 ) {
			$current_user_id = get_current_user_id();
		}
		do_action( 'cfm_before_' . $this->name() . '_form_save_admin', $payment_id, $user_id, $values, $current_user_id, $profile );
	}

	public function after_form_save( $payment_id = -2, $user_id = -2, $values = array(), $current_user_id = -2, $profile = false ) {
		if ( cfm_is_admin() ) {
			$this->after_form_save_admin( $payment_id, $user_id, $values, $current_user_id, $profile );
		} else {
			$this->after_form_save_frontend( $payment_id, $user_id, $values, $current_user_id, $profile );
		}
	}

	public function after_form_save_frontend($payment_id = -2, $user_id = -2, $values = array(), $current_user_id = -2, $profile = false ) {
		if ( $current_user_id === -2 ) {
			$current_user_id = get_current_user_id();
		}
		do_action( 'cfm_after_' . $this->name() . '_form_save_frontend', $payment_id, $user_id, $values, $current_user_id, $profile );
	}

	public function after_form_save_admin( $payment_id = -2, $user_id = -2, $values = array(), $current_user_id = -2, $profile = false ) {
		if ( $current_user_id === -2 ) {
			$current_user_id = get_current_user_id();
		}
		do_action( 'cfm_after_' . $this->name() . '_form_save_admin', $payment_id, $user_id, $values, $current_user_id, $profile );
	}

	public function render_formbuilder_fields() {
		$output  = apply_filters( 'cfm_render_' . $this->name() . '_form_formbuilder_output_before_loop', '' );
		$fields = $this->fields;
		$fields = apply_filters( 'cfm_render_' . $this->name() . '_form_formbuilder_fields', $fields );

		if ( !empty( $fields ) ) {
			if ( current_user_can( 'manage_shop_settings' ) ) {
				foreach ( $fields as $index => $field ) {
					$output .= apply_filters( 'cfm_render_' . $this->name() . '_form_formbuilder_before_field', '', $index, $field );
					$output .= $field->render_formbuilder_field( $index, $field );
					$output .= apply_filters( 'cfm_render_' . $this->name() . '_form_formbuilder_after_field', '', $index, $field );
				}
			}
		}

		$output  = apply_filters( 'cfm_render_' . $this->name() . '_form_formbuilder_output_after_loop', $output );
		return $output;
	}

	/** Saves the formbuilder fields. post_id is the post->ID of the form **/
	public function save_formbuilder_fields( $post_id = -2, $values = array() ) {

		if ( $post_id === -2 ) {
			$post_id = get_the_ID();
		}

		if ( ! current_user_can( 'manage_shop_settings' ) ) {
			return $post_id;
		}

		if ( !empty( $values ) ) {
			foreach ( $values as $id => $value ) {
				if ( !empty ( $value['name'] ) ) {
					$values[$id]['name'] = sanitize_key( $value['name'] );
				} else {
					unset( $values[$id] );
				}
			}
		}

		$values  = apply_filters( 'cfm_save_' . $this->name() . '_form_formbuilder_fields_values', $values );

		do_action( 'cfm_save_' . $this->name() . '_form_formbuilder_fields_before_save', $values );

		update_post_meta( $post_id, 'cfm-form', $values  );

		do_action( 'cfm_save_' . $this->name() . '_form_formbuilder_fields_after_save', $values );
	}

	/** Used when you need to change the payment_id of the form and all of it's fields */
	public function change_payment_id( $payment_id ) {
		$this->payment_id = $payment_id;
		$fields                = get_post_meta( $this->id, 'cfm-form', true );
		$fields                = !empty( $fields ) ? $fields : array();
		$this->load_fields( $fields );
	}

	/** Used when you need to change the user_id of the form and all of it's fields */
	public function change_user_id( $user_id ) {
		$this->user_id 		   = $user_id;
		$fields                = get_post_meta( $this->id, 'cfm-form', true );
		$fields                = !empty( $fields ) ? $fields : array();
		$this->load_fields( $fields );
	}

	public function has_formbuilder() {
		if ( isset( $this->supports['formbuilder'] ) ) {
			return true;
		} else {
			return false;
		}
	}

	public function is_formbuilder( $id ) {
		if ( $id == $this->id ) {
			return true;
		} else {
			return false;
		}
	}

	public function name() {
		return $this->name;
	}

	public function get_form_id_by_name( $name ) {
		return get_option( 'cfm-'. $name . '-form', false );
	}

	public function set_title() {
		$title = _x( 'Checkout', 'CFM Form title translation', 'edd_cfm' );
		$title = apply_filters( 'cfm_' . $this->name() . '_form_title', $this->title );
		$this->title = $title;
	}

	public function title( $form = false ) {
		if ( $form ) {
			return sprintf( _x( "%s Form", '%s = CFM Form Name (translated)', 'edd_cfm' ), $this->title );
		} else {
			return $this->title;
		}
	}

	public function can_render_form( $current_user_id = -2, $profile = false ) {
		$output = false;
		if ( cfm_is_admin() ) {
			$output = $this->can_render_form_admin( $current_user_id, $profile );
		} else {
			$output = $this->can_render_form_frontend( $current_user_id, $profile );
		}
		return $output;
	}



	public function can_render_form_admin( $current_user_id = -2, $profile = false ) {
		if ( $current_user_id === -2 ) {
			$current_user_id = get_current_user_id();
		}
		if ( user_can( $current_user_id, 'manage_shop_settings' ) ){
			return true;
		} else {
			return false;
		}
	}

	public function can_render_form_frontend( $current_user_id = -2, $profile = false ) {
		if ( $current_user_id === -2 ) {
			$current_user_id = get_current_user_id();
		}

		if ( $profile ) {
			if ( $current_user_id === get_current_user_id() ) {
				return true;
			} else {
				return false;
			}
		} else {
			return true;
		}
	}

	public function can_save_form( $current_user_id = -2, $profile = false ) {
		$output = false;
		if ( cfm_is_admin() ) {
			$output = $this->can_save_form_admin( $current_user_id, $profile );
		} else {
			$output = $this->can_save_form_frontend( $current_user_id, $profile );
		}
		return $output;
	}

	public function can_save_form_admin( $current_user_id = -2, $profile = false ) {
		if ( $current_user_id === -2 ) {
			$current_user_id = get_current_user_id();
		}

		if ( user_can( $current_user_id, 'manage_shop_settings' ) ){
			return true;
		} else {
			return false;
		}
	}


	public function can_save_form_frontend( $current_user_id = -2, $profile = false ) {
		if ( $current_user_id === -2 ) {
			$current_user_id = get_current_user_id();
		}

		if ( $profile ) {
			if ( $current_user_id === get_current_user_id() ) {
				return true;
			} else {
				return false;
			}
		} else {
			return true;
		}
	}
}
