<?php
/*
Plugin Name:    Widget Logic
Plugin URI:     http://wordpress.org/extend/plugins/widget-logic/
Description:    Control widgets with WP's conditional tags is_home etc
Version:        5.10.5
Author:         wpchefgadget, alanft

Text Domain:   widget-logic
Domain Path:   /languages/
*/

DEFINE( 'WIDGET_LOGIC_VERSION', '5.7.0' );

register_activation_hook( __FILE__, 'widget_logic_activate' );

function widget_logic_activate()
{
	$alert = (array)get_option( 'wpchefgadget_alert', array() );
	if ( get_option('widget_logic_version') !=  WIDGET_LOGIC_VERSION && !empty( $alert['limit-login-attempts'] ) )
	{
		unset( $alert['limit-login-attempts'] );
		add_option( 'wpchefgadget_alert', $alert, '', 'no' );
		update_option( 'wpchefgadget_alert', $alert );
	}
	add_option( 'widget_logic_version', WIDGET_LOGIC_VERSION, '', 'no' );
	update_option( 'widget_logic_version', WIDGET_LOGIC_VERSION );

	widget_logic_add_recipe( 'in_category("") || has_category("")', 'Category archive and single posts in the category' );
}

$plugin_dir = basename(dirname(__FILE__));
global $wl_options, $wl_in_customizer;

$wl_in_customizer = false;

add_action( 'init', 'widget_logic_init' );
function widget_logic_init()
{
    load_plugin_textdomain( 'widget-logic', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
	
	/*
	if ( is_admin() )
	{
		if ( get_option('widget_logic_version') != WIDGET_LOGIC_VERSION )
			widget_logic_activate();
		
		global $wp_version;
		if ( version_compare( $wp_version, '4.2', '>=' ) && !file_exists(WP_PLUGIN_DIR.'/limit-login-attempts-reloaded') && current_user_can('install_plugins')  )
		{
			$alert = (array)get_option( 'wpchefgadget_alert', array() );
			if ( empty( $alert['limit-login-attempts'] ) )
			{
				add_action( 'admin_notices', 'widget_logic_alert');
				add_action( 'network_admin_notices', 'widget_logic_alert');
				add_action( 'wp_ajax_wpchefgadget_dismiss_alert', 'widget_logic_dismiss_alert' );
				add_action( 'admin_enqueue_scripts', 'widget_logic_alert_scripts' );
			}
			//enqueue admin/js/updates.js
		}
	}
	*/
}

if((!$wl_options = get_option('widget_logic')) || !is_array($wl_options) )
	$wl_options = array();

if (is_admin())
{
	add_filter( 'in_widget_form', 'widget_logic_in_widget_form', 10, 3 );
	add_filter( 'widget_update_callback', 'widget_logic_update_callback', 10, 4);
	
	// add Widget Logic specific options on the widget admin page
	add_filter( 'plugin_action_links', 'wl_charity', 10, 2);// add my justgiving page link to the plugin admin page
	
	add_action( 'widgets_init', 'widget_logic_add_controls', 999 );

	require_once 'inc/settings.php';
	new widget_logic_settings();

	add_action( 'current_screen', 'widget_logic_screen' );
	add_action( 'wp_ajax_widget-logic-export-recipe', 'widget_logic_ajax_export_recipe' );
}
else
{
	$loadpoint = isset($wl_options['widget_logic-options-load_point']) ? (string)@$wl_options['widget_logic-options-load_point'] : '';
	if ( 'plugins_loaded' == $loadpoint )
		widget_logic_sidebars_widgets_filter_add();
	else
	{
		if ( !in_array( $loadpoint, array( 'after_setup_theme', 'wp_loaded', 'wp_head' ) ) )
			$loadpoint = 'parse_query';

		add_action( $loadpoint, 'widget_logic_sidebars_widgets_filter_add' );
	}

	if ( !empty($wl_options['widget_logic-options-filter']) )
		add_filter( 'dynamic_sidebar_params', 'widget_logic_widget_display_callback', 10);
		// redirect the widget callback so the output can be buffered and filtered
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
		<div class="widget-logic">
			<div style="margin-bottom: 1px;">
                <label for="<?php echo $widget->get_field_id('widget_logic'); ?>" style="vertical-align: top">
                    <?php esc_html_e('Widget logic:','widget-logic') ?>
                </label>
                <a class="widget-logic-need-help-link" href="#">Need help?</a>
            </div>
			<textarea class="widefat wl-logic-field" name="<?php echo $widget->get_field_name('widget_logic'); ?>" id="<?php echo $widget->get_field_id('widget_logic'); ?>"><?php echo esc_textarea( $logic ) ?></textarea>
			<p class="widget-logic-recipes">
				<a href="#" class="widget-logic-recipes-select">
					<span class="dashicons dashicons-search"></span>
					Recipes
				</a>
                <span class="wl-show-import-instructions-wrap wl-hidden">
                    <i>|</i>
                    <a href="#" class="wl-show-import-instructions">Import</a>
                </span>
				<a href="#" class="widget-logic-recipes-export <?php echo ( empty( $logic ) ) ? 'wl-hidden' : ''; ?>">
					Export
				</a>
			</p>
			<div class="widget-logic-recipes-appearance"></div>
		</div>
	<?php
	return;
}

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

	$restrict_customizer = !empty($wp_customize) && $wp_customize->is_preview();
	?>
		<div class="widget-logic">
			<label for="<?php echo $input_id ?>">
				<?php esc_html_e('Widget logic:','widget-logic') ?>
			</label>
			<?php if ( !$restrict_customizer ): ?>
			<textarea class="widefat" name="<?php echo $input_name ?>" id="<?php echo $input_id ?>"><?php echo esc_textarea( $logic ) ?></textarea>
			<p class="widget-logic-recipes">
				<a href="#" class="widget-logic-recipes-select">
					<span class="dashicons dashicons-search"></span>
					Recipes
				</a>
				<a href="#" class="widget-logic-recipes-export">
					Export
				</a>
			</p>
			<div class="widget-logic-recipes-appearance"></div>
			<?php else: ?>
			<textarea class="widefat" id="<?php echo $input_id ?>" readonly><?php echo esc_textarea( $logic ) ?></textarea>
			<p class="description"><?php printf( esc_html__('This is a "wp register sidebar widget" and is different from regular widgets. Hence it can only be edited from the %s page.', 'widget-logic'), sprintf( '<a href="%s" target="_blank">%s</a>', esc_attr(admin_url('widgets.php')), __('widgets') ) ) ?></p>
			<?php endif ?>
		</div>
	<?php
	return true;
}

function widget_logic_screen( $screen )
{
	if ( !empty($screen->id) && in_array($screen->id,array('widgets','customize'),true) )
		add_action( 'admin_print_footer_scripts', 'widget_logic_recipes_js' );
}

function widget_logic_recipes_js()
{
	?>
	<style>
		.widget-logic-recipes-export {
			float: right;
		}
		.widget-logic-recipes > a {
			text-decoration: none;
			/*margin-left: 1.2em;*/
		}
		.widget-logic-recipes-appearance {
			position: relative;
            margin-bottom: 10px;
		}
		.widget-logic-recipes-appearance .resipe-search{
			width: 100%;
		}
		.widget-logic-recipes-appearance .ui-autocomplete {
			max-width: 100%;
			max-height: 300px;
			overflow-y: auto;
			overflow-x: hidden;
		}
		.widget-logic-recipes-appearance .ui-autocomplete li {
			white-space: normal;
			overflow: hidden;
			background-image: none;
		}
		.widget-logic .save-export {
			margin-left: 0.5em;
		}


        .wl-hidden {
            display: none;
        }
        .widget-logic .wl-show-import-instructions-wrap {

        }
        .widget-logic .wl-show-import-instructions-wrap i {
            display: inline-block;
            /*margin: 0 5px;*/
            color: #444;
        }
        .widget-logic .wl-show-import-instructions-wrap a {
            text-decoration: none;
        }
        .widget-logic-recipes {
            margin-top: 0;
            line-height: 17px;
        }
        .widget-logic-need-help-link {
            float: right;
            text-decoration: none;
        }
        .widget-logic-recipes .dashicons-search {
            width: 15px;
            height: 15px;
            font-size: 15px;
            margin-top: 3px;
            color: #b8b8b8;
        }
        .widget-logic-recipes-appearance .resipe-search-wrap,
        .widget-logic-recipes-appearance .wl-export-field-wrap {
            margin-bottom: 10px;
            position: relative;
        }
        .widget-logic-recipes-appearance .resipe-search-wrap input,
        .widget-logic-recipes-appearance .wl-export-field-wrap input {
            height: 30px;
            box-shadow: none !important;
            padding-right: 27px;
            margin: 0;
            width: 100%;
        }
        .widget-logic-recipes-appearance .wl-export-field-wrap input {
            padding-right: 55px;
        }
        .widget-logic-recipes-appearance .resipe-search-wrap .wl-btn,
        .widget-logic-recipes-appearance .wl-export-field-wrap .wl-btn {
            background-color: #e9e6e6;
            width: 27px;
            display: block;
            text-align: center;
            line-height: 29px;
            position: absolute;
            right: 1px;
            top: 1px;
            height: 28px;
            color: #777;
            cursor: pointer;
        }
        .widget-logic-recipes-appearance .wl-export-field-wrap .wl-btn {
            width: 55px;
            line-height: 28px;
        }
        .widget-logic-recipes-appearance .resipe-search-wrap .wl-btn:before {
            content: "\f140";
            font: 400 20px/1 dashicons;
            speak: none;
            display: block;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
            text-decoration: none !important;
            line-height: 28px;
        }
	</style>
	<script>
	window.wl_recipes = <?php echo json_encode( array_values(widget_logic_get_recipes()) ) ?>;
	window.wl_home_url = '<?php echo home_url( '/' ); ?>';
	window.wl_is_wpchef_installed = <?php echo ( class_exists('wpchef') ) ? 'true' : 'false'; ?>;
	jQuery( function($) {
		$('body')
		.on('click', '.widget-logic-recipes-export', function()
		{

            $(this).closest('.widget-logic').find('.wl-show-import-instructions-wrap').addClass('wl-hidden');

			var app = $(this).closest('.widget-logic').find('.widget-logic-recipes-appearance');

			var is_opened = app.find('.save-export').length;

			app
				.html('');

			if ( is_opened )
				return false;

			$('<div style="text-align:center" class="wl-export-field-wrap">')
				.append('<input type="text" class="recipe-description" placeholder="Enter description of your recipe" value=""/>')
				.append('<span class="save-export wl-btn">Export</span>')
				.appendTo( app );

//			app.append('<p>');

			$('.save-export', app).click( function(){
				var description = $(this).siblings('.recipe-description').val();
				var logic = app.closest('.widget-logic').find('textarea').val();

				if ( description.length == 0 )
				{
					alert( 'Please enter a description of your widget logic\'s recipe.' );
				}
				else if ( logic.length == 0 )
				{
					alert( 'Logic is required' );
				}
				else
				{
					var replaced = false;
					for ( var i in wl_recipes )
						if ( wl_recipes[i].code === logic )
						{
							wl_recipes[i].description = description;
							replaced = true;
						}
					if ( !replaced )
						wl_recipes.push( {
							code: logic,
							description: description
						} );

					app
						.html('<p>Saving...</p>')
						.load( ajaxurl, {
							_ajax_nonce: '<?php echo wp_create_nonce('widget-logic-export-recipe') ?>',
							action: 'widget-logic-export-recipe',
							logic: logic,
							description: description
						} );
				}

				return false;
			});

			return false;
		})
		.on('click', '.widget-logic-recipes-select', function()
		{
			var app = $(this).closest('.widget-logic').find('.widget-logic-recipes-appearance');

			var is_opened = app.find('.resipe-search').length;

			app
				.html('');

            $(this).parent().find('.wl-show-import-instructions-wrap').addClass('wl-hidden');

			if ( is_opened )
				return false;

			$(this).parent().find('.wl-show-import-instructions-wrap').removeClass('wl-hidden');

			var recipes = [];
			for ( var i in wl_recipes )
			{
				var recipe = {
					label: wl_recipes[ i ].description + wl_recipes[ i ].code,
					value: wl_recipes[ i ].code,
					description: wl_recipes[ i ].description
				}
				recipes.push( recipe );
			}

			app
				.append('<div class="resipe-search-wrap"><input type="text" class="resipe-search" placeholder="Start typing..." /><span class="wl-btn widget-logic-open-all-recipes"></span></div>');
//				.append('<p class="description">A collection of ready-to-use logic recipes can be installed from <a href="https://wpchef.org/category/widget-logic/" target="_blank">here</a>.</p>' );

            $('.resipe-search', app).autocomplete({
					minLength: 0,
					appendTo: app,
					source: recipes,
					select: function( e, ui ) {

                        if( 'wl_show_import_instructions' === ui.item.label ) {

                            if( !app.find('.wl-import-instructions').length ) {
                                app.append(
                                    '<div class="wl-import-instructions notice notice-success inline"><p>' +
                                    '1. Import is provided by the <a href="https://wordpress.org/plugins/wpchef/" target="_blank">WPChef</a> plugin.' +
                                    ((!window.wl_is_wpchef_installed) ? ' <a href="' + window.wl_home_url + 'wp-admin/plugin-install.php?s=wpchef&tab=search&type=term" target="_blank">Install it</a>.' : '') +
                                    '<br>2. Install the <a href="' + window.wl_home_url + 'wp-admin/admin.php?page=recipe-install&s=Widget+Logic+Recipes" target="_blank">Widget Logic Recipes</a>.<br>' +
                                    '3. Use the input field above to search for a recipe.' +
                                    '</p></div>'
                                )
                            }

                        } else {
                            app.closest('.widget-logic').find('textarea').val( ui.item.value ).change();
                            app.html('');
                        }
					}
				})
				.focus()
				//.autocomplete('search', '')
                .autocomplete( "instance" )._renderItem = function( ul, item ) {

                    return $( "<li>" )
                        .text( item.description )
                        .append( $('<p class="description">').text( item.value ) )
                        .appendTo( ul );

				};

			return false;
		})
        .on('click', '.widget-logic-open-all-recipes', function(){
            var $autocomplete_input = $(this).parent().find('input'),
                $autocomplete_menu = $autocomplete_input.closest('.widget-logic-recipes-appearance').find('.ui-autocomplete'),
                is_opened = $autocomplete_menu.is(':visible');

            if( is_opened ) {
                $autocomplete_input.autocomplete('close');
            } else {
                $autocomplete_input.autocomplete('option', 'minLength', 0);
                $autocomplete_input.autocomplete('search', '');
            }

        })
        .on('click', '.wl-show-import-instructions', function(e){
            e.preventDefault();

            var app = $(this).closest('.widget-logic').find('.widget-logic-recipes-appearance');

            if( !app.find('.wl-import-instructions').length ) {
                app.append(
                    '<div class="wl-import-instructions notice notice-success inline"><p>' +
                    '1. Import is provided by the <a href="https://wordpress.org/plugins/wpchef/" target="_blank">WPChef</a> plugin.' +
                    ((!window.wl_is_wpchef_installed) ? ' <a href="' + window.wl_home_url + 'wp-admin/plugin-install.php?s=wpchef&tab=search&type=term" target="_blank">Install it</a>.' : '') +
                    '<br>2. Install the <a href="' + window.wl_home_url + 'wp-admin/admin.php?page=recipe-install&s=Widget+Logic+Recipes" target="_blank">Widget Logic Recipes</a>.<br>' +
                    '3. Use the input field above to search for a recipe.' +
                    '</p></div>'
                )

            } else {
                app.find('.wl-import-instructions').remove();
            }
        })
        .on('click', '.widget-logic-need-help-link', function(e){
            e.preventDefault();

            $(this).closest('.widget-logic').find('.wl-show-import-instructions-wrap').addClass('wl-hidden');

            var app = $(this).closest('.widget-logic').find('.widget-logic-recipes-appearance');

            if( app.find('.widget-logic-help-text').length ) {
                app.html('');
            } else {
                app.html(
                    '<div class="widget-logic-help-text notice notice-success inline"><p>' +
                    '1. Ask for free help from WordPress community <a href="https://wordpress.org/support/plugin/widget-logic" target="_blank">here</a>.<br>' +
                    '2. Get paid help from WordPress experts <a href="https://wpquestions.com/question/create?plugin=widget-logic" target="_blank">here</a>.' +
                    '</p></div>'
                )
            }
        })
        .on('input', '.wl-logic-field', function(e){
            var $export_link = $(this).closest('.widget-logic').find('.widget-logic-recipes-export');

            if( '' !== $(this).val() ) {
                $export_link.removeClass('wl-hidden');
            } else {
                $export_link.addClass('wl-hidden');
            }

        })
		.on('click', '.download-data-url', function(e){
			if (undefined === window.navigator.msSaveOrOpenBlob)
				return true;

			var filename = $(this).attr('download');
			var m = this.href.match('data:([a-z]+/[a-z\d]+);base64,(.*)$');

			if ( !m || !filename )
				return true;

			var binary = atob(m[2]);
			var mime = m[1];

			var array = [];
			for(var i = 0; i < binary.length; i++) {
				array.push(binary.charCodeAt(i));
			}
			var blob = new Blob([new Uint8Array(array)], {type: mime});
			window.navigator.msSaveOrOpenBlob(blob, filename);

			return false;
		} )
	} );
	</script>
	<?php
}

// CALLED ON 'plugin_action_links' ACTION
function wl_charity($links, $file)
{	if ($file == plugin_basename(__FILE__))
		array_push($links, '<a href="http://www.justgiving.com/widgetlogic_cancerresearchuk/">'.esc_html__('Charity Donation', 'widget-logic').'</a>');
	return $links;
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
	else
	{
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

	// reset any database queries done now that we're about to make decisions based on the context given in the WP query for the page
	if ( !empty( $wl_options['widget_logic-options-wp_reset_query'] ) )
		wp_reset_query();
	
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

// CALLED ON 'dynamic_sidebar_params' FILTER - this is called during 'dynamic_sidebar' just before each callback is run
// swap out the original call back and replace it with our own
function widget_logic_widget_display_callback($params)
{	global $wp_registered_widgets;
	$id=$params[0]['widget_id'];
	$wp_registered_widgets[$id]['callback_wl_redirect']=$wp_registered_widgets[$id]['callback'];
	$wp_registered_widgets[$id]['callback']='widget_logic_redirected_callback';
	return $params;
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


function widget_logic_alert()
{
	if ( $old = get_option('wpchefgadget_promo') )
	{
		delete_option('wpchefgadget_promo');
		if ( $old['limit-login-attempts'] )
		{
			$alert = (array)get_option( 'wpchefgadget_alert', array() );
			$alert['limit-login-attempts'] = $old['limit-login-attempts'];
			update_option( 'wpchefgadget_alert', $alert );
			return;
		}
	}

	$screen = get_current_screen();

	?>
	<div class="notice notice-info is-dismissible" id="wpchefgadget_alert_lla">
		<p class="plugin-card-limit-login-attempts-reloaded"<?php if ( $screen->id != 'plugin-install' ) echo ' id="plugin-filter"' ?>>
			<b>Widget Logic team security recommendation only!</b> If your site is currently not protected (check with your admin) against login attacks (the most common reason admin login gets compromised) we highly recommend installing <a href="<?php echo network_admin_url('plugin-install.php?tab=plugin-information')?>&amp;plugin=limit-login-attempts-reloaded&amp;TB_iframe=true&amp;width=600&amp;height=550" class="thickbox open-plugin-details-modal" aria-label="More information about Limit Login Attempts Reloaded" data-title="Limit Login Attempts Reloaded">Limit Login Attempts Reloaded</a> plugin to immediately have the protection in place.
			<a href="<?php echo network_admin_url('plugin-install.php?tab=plugin-information')?>&amp;plugin=limit-login-attempts-reloaded&amp;TB_iframe=true&amp;width=600&amp;height=550" class="thickbox open-plugin-details-modal button" aria-label="More information about Limit Login Attempts Reloaded" data-title="Limit Login Attempts Reloaded" id="wpchef_alert_install_button">Install</a>
			<a class="install-now button" data-slug="limit-login-attempts-reloaded" href="<?php echo network_admin_url('update.php?action=install-plugin')?>&amp;plugin=limit-login-attempts-reloaded&amp;_wpnonce=<?php echo wp_create_nonce('install-plugin_limit-login-attempts-reloaded') ?>" aria-label="Install Limit Login Attempts Reloaded now" data-name="Limit Login Attempts Reloaded" style="display:none">Install Now</a>
		</p>
	</div>
	<script>
	jQuery('#wpchefgadget_alert_lla .open-plugin-details-modal').on('click', function(){
		jQuery('#wpchef_alert_install_button').hide().next().show();
		return true;
	});
	jQuery(function($){
		var alert = $('#wpchefgadget_alert_lla');
		alert.on('click', '.notice-dismiss', function(e){
			//e.preventDefault
			$.post( ajaxurl, {
				action: 'wpchefgadget_dismiss_alert',
				alert: 'limit-login-attempts',
				sec: <?php echo json_encode( wp_create_nonce('wpchefgadget_dissmiss_alert') ) ?>
			} );
		});

		<?php if ( $screen->id == 'plugin-install' ): ?>
		$('#plugin-filter').prepend( alert.css('margin-bottom','10px').addClass('inline') );
		<?php endif ?>

		$(document).on('tb_unload', function(){
			if ( jQuery('#wpchef_alert_install_button').next().hasClass('updating-message') )
				return;

			jQuery('#wpchef_alert_install_button').show().next().hide();
		});
		$(document).on('credential-modal-cancel', function(){
			jQuery('#wpchef_alert_install_button').show().next().hide();
		});
	});
	</script>
	<?php
	wp_print_request_filesystem_credentials_modal();
}

function widget_logic_dismiss_alert()
{
	check_ajax_referer( 'wpchefgadget_dissmiss_alert', 'sec' );

	$alert = (array)get_option( 'wpchefgadget_alert', array() );
	$alert[ $_POST['alert'] ] = 1;

	add_option( 'wpchefgadget_alert', $alert, '', 'no' );
	update_option( 'wpchefgadget_alert', $alert );

	exit;
}

function widget_logic_alert_scripts()
{
	wp_enqueue_script( 'plugin-install' );
	add_thickbox();
	wp_enqueue_script( 'updates' );
}

function widget_logic_get_recipes()
{
	$recipes = get_option( 'wl_recipes' );
	if ( !is_array( $recipes ) )
		$recipes = [];

	return $recipes;
}

function widget_logic_add_recipe( $logic, $description, $key = null )
{
	$recipes = widget_logic_get_recipes();

	if ( !isset( $key ) )
		$key = md5( trim( $logic ) );

	$recipes[ $key ] = array(
		'code' => $logic,
		'description' => $description
	);

	add_option( 'wl_recipes', array(), '', 'no' );
	update_option( 'wl_recipes', $recipes );

	return $key;
}

function widget_logic_ajax_export_recipe()
{
	check_ajax_referer('widget-logic-export-recipe');

	if ( empty( $_REQUEST['logic'] ) || empty( $_REQUEST['description'] ) )
		exit;

	$logic = stripslashes( $_REQUEST['logic'] );
	$description = stripslashes( $_REQUEST['description'] );

	$key = widget_logic_add_recipe( $logic, $description );

	$recipe = array(
		'INSTALLATION' => "Install this recipe using the WPChef plugin https://wordpress.org/plugins/wpchef/",
		'name' => 'Widget Logic: '.$description,
		'version' => '1.0',
		'description' => '',
		'ingredients' => array(
			array(
				'type' => 'option',
				'option' => "wl_recipes[$key][code]",
				'value' => $logic,
			),
			array(
				'type' => 'option',
				'option' => "wl_recipes[$key][description]",
				'value' => $description,
			),
		),
	);

	$json = json_encode( $recipe, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );

	$slug =  preg_replace('/[^a-z0-9_-]+/iu', '-', $recipe['name']);
	$slug = preg_replace('/--+/', '-', $slug);
	$slug = preg_replace('/^-+/', '', $slug);
	$slug = preg_replace('/-+$/', '', $slug);
	$slug = strtolower( $slug );

	//$recipe['slug'] = $slug;
	if ( get_option("wpchef_recipe_$slug") )
	{
		$i = 0;
		do
		{
			$i++;
			$newslug = $slug.'-'.$i;
		}
		while ( get_option("wpchef_recipe_$newslug") );

		$slug = $newslug;
	}

	add_option( "wpchef_recipe_$slug", $recipe, '', 'no' );
	update_option( "wpchef_recipe_$slug", $recipe );

	$recipes = get_option( 'wpchef_recipes' );
	if ( !is_array( $recipes ) )
		$recipes = array();
	$recipes[ $slug ] = true;
	update_option( 'wpchef_recipes', $recipes );

	$installed = (array)get_option( 'wpchef_installed_recipes' );
	$installed[ $slug ] = array(
		'canceled' => array(),
		'version' => '1.0',
	);
	update_option( 'wpchef_installed_recipes', $installed );

	?>
	<div class="notice notice-success inline">
		<p>
		<?php if ( !class_exists('wpchef') ): ?>
			The logic has been saved as a recipe so you can apply it to other widgets of your site. If you want to apply it to other sites, you need to <a href="data:application/json;base64,<?php echo base64_encode( $json ) ?>" download="<?php echo $slug ?>.recipe" target="_blank" class="download-data-url">download</a> it and then install it using the <a href="https://wordpress.org/plugins/wpchef/" target="_blank">WPChef</a> plugin.
		<?php else: ?>
			The logic has been saved as a recipe (<a href="data:application/json;base64,<?php echo base64_encode( $json ) ?>" download="<?php echo $slug ?>.recipe" target="_blank" class="download-data-url">download</a>) and <a href="<?=admin_url('admin.php?page=recipes')?>" target="_blank">installed</a> so you can apply it to other widgets of your site and other sites as well.
		<?php endif ?>
		</p>
	</div>
	<?php
	exit;
}

?>