<?php
class CFM_Url_Field extends CFM_Field {

	/** @var bool For 3rd parameter of get_post/user_meta */
	public $single = true;

	/** @var string Version of field */
	public $version = '1.0.0';

	/** @var array Supports are things that are the same for all fields of a field type. Like whether or not a field type supports jQuery Phoenix. Stored in obj, not db. */
	public $supports = array(
		'multiple'    => true,
		'is_meta'     => true,  // in object as public (bool) $meta;
		'forms'       => array(
			'checkout'     => true,
		),
		'position'    => 'custom',
		'permissions' => array(
			'can_remove_from_formbuilder' => true,
			'can_change_meta_key'         => true,
			'can_add_to_formbuilder'      => true,
		),
		'template'   => 'url',
		'title'       => 'URL',
	);

	/** @var array Characteristics are things that can change from field to field of the same field type. Like the placeholder between two url fields. Stored in db. */
	public $characteristics = array(
		'name'            => '',
		'template'        => 'url',
		'required'        => false,
		'label'           => '',
		'css'             => '',
		'default'         => '',
		'size'            => '',
		'help'            => '',
		'placeholder'     => '',
		'meta_type'       => 'payment', // 'payment' or 'user' here if is_meta()
		'public'          => "public", // denotes whether a field shows in the admin only
		'show_in_exports' => "export", // denotes whether a field is in the CSV exports
	);

	public function set_title() {
		$title = _x( 'URL', 'CFM Field title translation', 'edd_cfm' );
		$title = apply_filters( 'cfm_' . $this->name() . '_field_title', $title );
		$this->supports['title'] = $title;
	}

	/** Returns the HTML to render a field in admin */
	public function render_field_admin( $user_id = -2, $profile = -2 ) {
		if ( $user_id === -2 ) {
			$user_id = get_current_user_id();
		}

		$value      = $this->get_field_value_admin( $this->payment_id, $this->user_id );
		$output     = '';
		$output    .= sprintf( '<p class="cfm-el %1s %2s %3s">', esc_attr( $this->template() ), esc_attr( $this->name() ), esc_attr( $this->css() ) );
		$output    .= $this->label( false );
		ob_start(); ?>
		<input name="<?php echo esc_attr( $this->name() ); ?>" id="<?php echo esc_attr( $this->name() ); ?>" class="url text edd-input" type="url" data-required="false" data-type="text" placeholder="<?php echo esc_attr( $this->placeholder() ); ?>" value="<?php echo esc_attr( $value ) ?>" size="<?php echo esc_attr( $this->size() ) ?>" />
		<?php
		$output .= ob_get_clean();
		$output .= '</p>';
		return $output;
	}

	/** Returns the HTML to render a field in frontend */
	public function render_field_frontend( $user_id = -2, $profile = -2 ) {
		if ( $user_id === -2 ) {
			$user_id = get_current_user_id();
		}

		$value     = $this->get_field_value_frontend( $this->payment_id, $this->user_id );
		$required  = $this->required();

		$output  = '';
		$output .= sprintf( '<p class="cfm-el %1s %2s %3s">', esc_attr( $this->template() ), esc_attr( $this->name() ), esc_attr( $this->css() ) );
		$output .= $this->label( ! (bool) $profile );
		ob_start(); ?>
		<input name="<?php echo esc_attr( $this->name() ); ?>" id="<?php echo esc_attr( $this->name() ); ?>" class="text edd-input <?php echo $this->required_class(); ?>" type="url" class="url" data-required="<?php echo $required; ?>" data-type="text"<?php echo $this->required_html5(); ?> placeholder="<?php echo esc_attr( $this->placeholder() ); ?>" value="<?php echo esc_attr( $value ) ?>" size="<?php echo esc_attr( $this->size() ) ?>" />
		<?php
		$output .= ob_get_clean();
		$output .= '</p>';
		return $output;
	}

	/** Returns the HTML to render a field for the formbuilder */
	public function render_formbuilder_field( $index = -2, $insert = false ) {
		$removable = $this->can_remove_from_formbuilder();
		ob_start(); ?>
		<li class="custom-field url">
			<?php $this->legend( $this->title(), $this->get_label(), $removable ); ?>
			<?php CFM_Formbuilder_Templates::hidden_field( "[$index][template]", $this->template() ); ?>

			<?php CFM_Formbuilder_Templates::field_div( $index, $this->name(), $this->characteristics, $insert ); ?>
				<?php CFM_Formbuilder_Templates::public_radio( $index, $this->characteristics ); ?>
				<?php CFM_Formbuilder_Templates::export_radio( $index, $this->characteristics ); ?>
				<?php CFM_Formbuilder_Templates::meta_type_radio( $index, $this->characteristics ); ?>
				<?php CFM_Formbuilder_Templates::standard( $index, $this ); ?>
				<?php CFM_Formbuilder_Templates::css( $index, $this->characteristics ); ?>
				<?php CFM_Formbuilder_Templates::common_text( $index, $this->characteristics ); ?>
				<?php CFM_Formbuilder_Templates::header(
					$index,
					__( 'Privacy Settings', 'edd_cfm' ),
					__( 'These settings only affect fields stored as payment meta', 'edd_cfm' )
				); ?>
				<?php CFM_Formbuilder_Templates::privacy_export( $index, $this->characteristics ); ?>
				<?php CFM_Formbuilder_Templates::eraser_action( $index, $this->characteristics ); ?>
			</div>
		</li>
		<?php
		return ob_get_clean();
	}

	public function validate( $values = array(), $payment_id = -2, $user_id = -2 ) {
		$name = $this->name();
		$return_value = false;
		if ( !empty( $values[ $name ] ) ) {
			// if the value is set
			if ( filter_var( $values[ $name ], FILTER_VALIDATE_URL ) === false ) {
				// if that's not a url
				edd_set_error( 'invalid_' . $this->id, sprintf( __( 'Please enter a valid url in the %s field.', 'edd_cfm' ), $this->get_label() ) );
			}
		} else {
			// if the url is required but isn't present
			if ( $this->required() ) {
				edd_set_error( 'invalid_' . $this->id, sprintf( __( 'Please fill out the %s field.', 'edd_cfm' ), $this->get_label() ) );
			}
		}
	}

	public function sanitize( $values = array(), $payment_id = -2, $user_id = -2 ) {
		$name = $this->name();
		if ( !empty( $values[ $name ] ) ) {
			$values[ $name ] = filter_var( trim( $values[ $name ] ), FILTER_SANITIZE_URL );
		}
		return apply_filters( 'cfm_sanitize_' . $this->template() . '_field', $values, $name, $payment_id, $user_id );
	}
}
