<?php
/*
Plugin Name: Widget Logic
Author URI:  https://wpchef.org
Description: Control widgets with WP's conditional tags is_home etc
Version:     6.0.0
Author:      WPChef
Text Domain: widget-logic
*/

$plugin_dir = basename(dirname(__FILE__));
global $wl_options, $wl_in_customizer;

$wl_in_customizer = false;

add_action( 'init', 'widget_logic_init' );
function widget_logic_init()
{
    load_plugin_textdomain( 'widget-logic', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
}

if((!$wl_options = get_option('widget_logic')) || !is_array($wl_options) )
	$wl_options = array();

add_filter( 'in_widget_form', 'widget_logic_in_widget_form', 10, 3 );
add_filter( 'widget_update_callback', 'widget_logic_update_callback', 10, 4);

add_action( 'sidebar_admin_setup', 'widget_logic_expand_control');
// before any HTML output save widget changes and add controls to each widget on the widget admin page
add_action( 'sidebar_admin_page', 'widget_logic_options_control');

add_action( 'widgets_init', 'widget_logic_add_controls', 999 );

if ( !is_admin() ) {
	add_action( 'parse_query', 'widget_logic_sidebars_widgets_filter_add' );
}


function widget_logic_in_customizer()
{
	global $wl_in_customizer;
	$wl_in_customizer = true;

	//add_filter( 'widget_display_callback', 'widget_logic_customizer_display_callback', 10, 3 );
	add_action( 'dynamic_sidebar', 'widget_logic_customizer_dynamic_sidebar_callback' );
}
add_action( 'customize_preview_init', 'widget_logic_in_customizer' );


function widget_logic_sidebars_widgets_filter_add()
{
	// actually remove the widgets from the front end depending on widget logic provided
	add_filter( 'sidebars_widgets', 'widget_logic_filter_sidebars_widgets', 10);
}
// wp-admin/widgets.php explicitly checks current_user_can('edit_theme_options')
// which is enough security, I believe. If you think otherwise please contact me


// CALLED VIA 'widget_update_callback' FILTER (ajax update of a widget)
function widget_logic_update_callback( $instance, $new_instance, $old_instance, $this_widget )
{
	if ( isset( $new_instance['widget_logic'] ) )
		$instance['widget_logic'] = $new_instance['widget_logic'];

	return $instance;
}


// CALLED VIA 'sidebar_admin_setup' ACTION
// adds in the admin control per widget, but also processes import/export
function widget_logic_expand_control()
{
    global $wp_registered_widgets, $wp_registered_widget_controls, $wl_options;

	// UPDATE OTHER WIDGET LOGIC OPTIONS
	// must update this to use http://codex.wordpress.org/Settings_API
	if (
        isset($_POST['widget_logic-options-submit']) &&
        current_user_can('administrator') &&
        isset( $_POST['widget_logic_nonce'] ) &&
        wp_verify_nonce( $_POST['widget_logic_nonce'], 'widget_logic_settings') )
	{
		$wl_options['widget_logic-options-show_errors'] = !empty($_POST['widget_logic-options-show_errors']);
	}


	update_option('widget_logic', $wl_options);

}




// CALLED VIA 'sidebar_admin_page' ACTION
// output extra HTML
// to update using http://codex.wordpress.org/Settings_API asap
function widget_logic_options_control()
{	global $wp_registered_widget_controls, $wl_options;

	if ( isset($wl_options['msg']))
	{	if (substr($wl_options['msg'],0,2)=="OK")
			echo '<div id="message" class="updated">';
		else
			echo '<div id="message" class="error">';
		echo '<p>Widget Logic – '.$wl_options['msg'].'</p></div>';
		unset($wl_options['msg']);
		update_option('widget_logic', $wl_options);
	}


	?><div class="wrap">

		<h2><?php _e('Widget Logic options', 'widget-logic'); ?></h2>
		<form method="POST">
			<ul>
				<li>
					<label for="widget_logic-options-show_errors">
					<input id="widget_logic-show_errors" name="widget_logic-options-show_errors" type="checkbox" value="1" class="checkbox" <?php if (!empty($wl_options['widget_logic-options-show_errors'])) echo "checked" ?> />
					<?php esc_html_e('Display logic errors to admin', 'widget-logic'); ?>
					</label>
			</ul>

			<?php wp_nonce_field( 'widget_logic_settings', 'widget_logic_nonce' ); ?>
			<?php submit_button( __( 'Save WL options', 'widget-logic' ), 'button-primary', 'widget_logic-options-submit', false ); ?>

		</form>
	</div>

	<?php
}

function widget_logic_add_controls()
{
	global $wp_registered_widget_controls, $wp_registered_widgets, $wp_registered_widget_updates;

	foreach ( $wp_registered_widgets as $id => $widget )
	{
		if ( preg_match( '/^(.+)-(\d+)$/', $id) )
			continue;

		if ( !isset( $wp_registered_widget_controls[ $id ] ) )
		{
			wp_register_widget_control( $id, $id, 'widget_logic_extra_control', array(), $id, null );
			continue;
		}

		if ( @$wp_registered_widget_controls[ $id ]['callback'] != 'widget_logic_extra_control' )
		{
			$wp_registered_widget_controls[$id]['params'][] = $id;
			$wp_registered_widget_controls[$id]['params'][] = @$wp_registered_widget_controls[$id]['callback'];
			$wp_registered_widget_controls[$id]['callback'] = 'widget_logic_extra_control';

			$wp_registered_widget_updates[$id]['params'][] = $id;
			$wp_registered_widget_updates[$id]['params'][] = @$wp_registered_widget_updates[$id]['callback'];
			$wp_registered_widget_updates[$id]['callback'] = 'widget_logic_extra_control';
		}
	}
}

function widget_logic_in_widget_form( $widget, $return, $instance )
{
	$logic = isset( $instance['widget_logic'] ) ? $instance['widget_logic'] : widget_logic_by_id( $widget->id );

	?>
		<p>
			<label for="<?php echo $widget->get_field_id('widget_logic'); ?>">
				<?php esc_html_e('Widget logic:','widget-logic') ?>
			</label>
			<textarea class="widefat" name="<?php echo $widget->get_field_name('widget_logic'); ?>" id="<?php echo $widget->get_field_id('widget_logic'); ?>"><?php echo esc_textarea( $logic ) ?></textarea>
		</p>
	<?php
	return;
}

// added to widget functionality in 'widget_logic_expand_control' (above)
function widget_logic_extra_control()
{
	global $wp_customize;
	$args = func_get_args();

	$callback = array_pop( $args );
	$widget_id = array_pop( $args );

	if ( is_callable($callback) )
		call_user_func_array( $callback, $args );

	if ( isset( $_POST["widget-$widget_id"]['widget_logic'] ) )
	{
		$logic = stripslashes( $_POST["widget-$widget_id"]['widget_logic'] );
		widget_logic_save( $widget_id, $logic );
	}
	else
		$logic = widget_logic_by_id( $widget_id );

	$input_id = "widget-$widget_id-widget_logic";
	$input_name = "widget-{$widget_id}[widget_logic]";
	?>
		<p>
			<label for="<?php echo $input_id ?>">
				<?php esc_html_e('Widget logic:','widget-logic') ?>
			</label>
			<?php if ( !empty($wp_customize) && $wp_customize->is_preview() ): ?>
			<textarea class="widefat" id="<?php echo $input_id ?>" readonly><?php echo esc_textarea( $logic ) ?></textarea>
			<br>
			<span class="description"><?php printf( esc_html__('This is a "wp register sidebar widget" and is different from regular widgets. Hence it can only be edited from the %s page.', 'widget-logic'), sprintf( '<a href="%s" target="_blank">%s</a>', esc_attr(admin_url('widgets.php')), __('widgets') ) ) ?></span>
			<?php else: ?>
			<textarea class="widefat" name="<?php echo $input_name ?>" id="<?php echo $input_id ?>"><?php echo esc_textarea( $logic ) ?></textarea>
			<?php endif ?>
			<?php wp_nonce_field( 'widget_logic_save', 'widget_logic_nonce' ); ?>
		</p>
	<?php
	return true;
}

// FRONT END FUNCTIONS...

function widget_logic_by_id( $widget_id )
{
	global $wl_options;

	if ( preg_match( '/^(.+)-(\d+)$/', $widget_id, $m ) )
	{
		$widget_class = $m[1];
		$widget_i = $m[2];

		$info = get_option( 'widget_'.$widget_class );
		if ( empty( $info[ $widget_i ] ) )
			return '';

		$info = $info[ $widget_i ];
	}
	else
		$info = (array)get_option( 'widget_'.$widget_id, array() );

	if ( isset( $info['widget_logic'] ) )
		$logic = $info['widget_logic'];

	elseif ( isset( $wl_options[ $widget_id ] ) )
	{
		$logic = stripslashes( $wl_options[ $widget_id ] );
		widget_logic_save( $widget_id, $logic );

		unset( $wl_options[ $widget_id ] );
		update_option( 'widget_logic', $wl_options );
	}

	else
		$logic = '';

	return $logic;
}

function widget_logic_save( $widget_id, $logic )
{
	global $wl_options;

	if ( preg_match( '/^(.+)-(\d+)$/', $widget_id, $m ) )
	{
		$widget_class = $m[1];
		$widget_i = $m[2];

		$info = get_option( 'widget_'.$widget_class );
		if ( !is_array( $info[ $widget_i ] ) )
			$info[ $widget_i ] = array();

		$info[ $widget_i ]['widget_logic'] = $logic;
		update_option( 'widget_'.$widget_class, $info );
	}
	else if( isset( $_POST['widget_logic_nonce'] ) && wp_verify_nonce( $_POST['widget_logic_nonce'], 'widget_logic_save') ) {

        $info = (array)get_option( 'widget_'.$widget_id, array() );
        $info['widget_logic'] = $logic;
        update_option( 'widget_'.$widget_id, $info );
    }
}

// CALLED ON 'sidebars_widgets' FILTER
function widget_logic_filter_sidebars_widgets( $sidebars_widgets )
{
	global $wl_options, $wl_in_customizer;

	if ( $wl_in_customizer )
		return $sidebars_widgets;
	
	// loop through every widget in every sidebar (barring 'wp_inactive_widgets') checking WL for each one
	foreach($sidebars_widgets as $widget_area => $widget_list)
	{
		if ($widget_area=='wp_inactive_widgets' || empty($widget_list))
			continue;

		foreach($widget_list as $pos => $widget_id)
		{
			$logic = widget_logic_by_id( $widget_id );

			if ( !widget_logic_check_logic( $logic ) )
				unset($sidebars_widgets[$widget_area][$pos]);
		}
	}
	return $sidebars_widgets;
}


function widget_logic_check_logic( $logic )
{
	$logic = @trim( (string)$logic );
	$logic = apply_filters( "widget_logic_eval_override", $logic );

	if ( is_bool( $logic ) )
		return $logic;

	if ( $logic === '' )
		return true;

	if ( stristr( $logic, "return" ) === false )
		$logic = "return ( $logic );";

	set_error_handler( 'widget_logic_error_handler' );

	try {
		$show_widget = eval($logic);
	}
	catch ( Error $e ) {
		trigger_error( $e->getMessage(), E_USER_WARNING );

		$show_widget = false;
	}

	restore_error_handler();

	return $show_widget;
}

function widget_logic_error_handler( $errno , $errstr )
{
	global $wl_options;
	$show_errors = !empty($wl_options['widget_logic-options-show_errors']) && current_user_can('manage_options');

	if ( $show_errors )
		echo 'Invalid Widget Logic: '.$errstr;

	return true;
}

function widget_logic_customizer_dynamic_sidebar_callback( $widget )
{
	widget_logic_customizer_display( $widget['id'] );
}

function widget_logic_customizer_display( $widget_id )
{
	if ( !preg_match( '/^(.+)-(\d+)$/', $widget_id) )
		return;

	$logic = widget_logic_by_id( $widget_id );

	global $wl_options;
	$show_errors = !empty($wl_options['widget_logic-options-show_errors']) && current_user_can('manage_options');

	ob_start();
	$show_widget = widget_logic_check_logic( $logic );
	$error = ob_get_clean();

	if ( $show_errors && $error ) :
		?><script>jQuery(function($){$('#<?php echo $widget_id?>').append( $('<p class="widget-logic-error">').html(<?php echo json_encode($error)?>) );})</script><?php
	endif;
	if ( !$show_widget ):
		?><script>jQuery(function($){$('#<?php echo $widget_id?>').children().not('.widget-logic-error').css('opacity', '0.2');})</script><?php
	endif;
}


// the redirection comes here
function widget_logic_redirected_callback()
{	global $wp_registered_widgets;

	// replace the original callback data
	$params=func_get_args();
	$id=$params[0]['widget_id'];
	$callback=$wp_registered_widgets[$id]['callback_wl_redirect'];
	$wp_registered_widgets[$id]['callback']=$callback;

	// run the callback but capture and filter the output using PHP output buffering
	if ( is_callable($callback) )
	{	ob_start();
		call_user_func_array($callback, $params);
		$widget_content = ob_get_contents();
		ob_end_clean();
		echo apply_filters( 'widget_content', $widget_content, $id);
	}
}

?>