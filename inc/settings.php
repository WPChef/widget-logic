<?php

class widget_logic_settings {
	public function __construct()
	{
		add_action( 'init', array( $this, 'init') );
		$this->options = get_option('widget_logic');
		if ( !is_array( $this->options ) )
			$this->options = array();
	}

	public function init()
	{
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
		add_action( 'admin_init', array( $this, 'init_settings' ) );
	}

	public function admin_menu()
	{
		add_options_page( 'Widget Logic Options', 'Widget Logic', 'manage_options', 'widget-logic', array( $this, 'options_page' ) );
	}

	public function options_page()
	{
		?>
		<form method="POST" action="options.php">
		<?php
			settings_fields( 'widget_logic' );
			do_settings_sections( 'widget_logic' );
			submit_button( __( 'Save WL options', 'widget-logic' ) );
		?>
		</form>
		<?php
	}

	public function init_settings()
	{
		add_settings_section(
			'widget_logic_section',
			'Widget Logic Settings',
			array( $this, 'section_title'),
			'widget_logic'
		);

		if ( !empty( $this->option['widget_logic-options-filter'] ) )
		add_settings_field(
			'widget_logic[widget_logic-options-filter]',
			__('Add \'widget_content\' filter', 'widget-logic'),
			array( $this, 'field_checkbox'),
			'widget_logic',
			'widget_logic_section',
			array(
				'label_for' => 'widget_logic-options-filter',
				'hint' => __('Adds a new WP filter you can use in your own code. Not needed for main Widget Logic functionality.','widget-logic'),
			)
		);

		add_settings_field(
			'widget_logic[widget_logic-options-wp_reset_query]',
			__('Use \'wp_reset_query\' fix', 'widget-logic'),
			array( $this, 'field_checkbox'),
			'widget_logic',
			'widget_logic_section',
			array(
				'label_for' => 'widget_logic-options-wp_reset_query',
				'hint' => __('Resets a theme\'s custom queries before your Widget Logic is checked','widget-logic'),
			)
		);

		add_settings_field(
			'widget_logic[widget_logic-options-load_point]',
			__('Load logic', 'widget-logic'),
			array( $this, 'field_load_point'),
			'widget_logic',
			'widget_logic_section',
			array( 'label_for' => 'widget_logic-options-load_point' )
		);

		add_settings_field(
			'widget_logic[widget_logic-options-show_errors]',
			__('Display logic errors to admin', 'widget-logic'),
			array( $this, 'field_checkbox'),
			'widget_logic',
			'widget_logic_section',
			array( 'label_for' => 'widget_logic-options-show_errors' )
		);

		register_setting( 'widget_logic', 'widget_logic' );
	}

	public function field_checkbox( $args )
	{
		$id = $args['label_for'];
		?>
		<input id="<?=$id?>" name="widget_logic[<?=$id?>]" type="checkbox" value="1" class="checkbox" <?php if (!empty($this->options[ $id ])) echo "checked" ?> />
		<?php if ( !empty( $args['hint'] ) ) : ?>
		<p class="description"><?php echo esc_attr($args['hint']) ?></p>
		<?php endif;
	}

	public function field_load_point()
	{
		$wl_load_points = array(
			'parse_query'    =>	__( 'after query variables set (default)', 'widget-logic' ),
			'plugins_loaded'    =>	__( 'when plugin starts', 'widget-logic' ),
			'after_setup_theme' =>	__( 'after theme loads', 'widget-logic' ),
			'wp_loaded'         =>	__( 'when all PHP loaded', 'widget-logic' ),
			'wp_head'           =>	__( 'during page header', 'widget-logic' )
		);
		?>
		<select id="widget_logic-options-load_point" name="widget_logic[widget_logic-options-load_point]" >
			<?php foreach($wl_load_points as $action => $action_desc) :
				$selected = isset($this->options['widget_logic-options-load_point']) && $action == $this->options['widget_logic-options-load_point']
			?>
			<option value="<?=$action?>" <?php if ($selected) echo " selected" ?> ><?=$action_desc?></option>
			<?php endforeach ?>
		</select>
		<?php
	}

	function section_title()
	{
	}
}