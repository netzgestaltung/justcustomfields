<?php
/*
Plugin Name: Just Custom Fields for Wordpress
Plugin URI: http://justcoded.com/just-labs/just-custom-fields-for-wordpress-plugin/
Description: This plugin add custom fields for standard and custom post types in WordPress.
Tags: custom, fields, custom fields, meta, post meta, object meta, editor
Author: Alexander Prokopenko
Author URI: http://justcoded.com/
Version: 1.4.1
Donate link: http://justcoded.com/just-labs/just-custom-fields-for-wordpress-plugin/
*/

define('JCF_ROOT', dirname(__FILE__));
define('JCF_TEXTDOMAIN', 'just-custom-fields');
define('JCF_VERSION', 1.41);

require_once( JCF_ROOT.'/inc/functions.multisite.php' );
require_once( JCF_ROOT.'/inc/class.field.php' );
require_once( JCF_ROOT.'/inc/functions.fieldset.php' );
require_once( JCF_ROOT.'/inc/functions.fields.php' );
require_once( JCF_ROOT.'/inc/functions.ajax.php' );
require_once( JCF_ROOT.'/inc/functions.post.php' );
require_once( JCF_ROOT.'/inc/functions.themes.php' );
require_once( JCF_ROOT.'/inc/functions.import.php' );

// composants
require_once( JCF_ROOT.'/components/input-text.php' );
require_once( JCF_ROOT.'/components/select.php' );
require_once( JCF_ROOT.'/components/select-multiple.php' );
require_once( JCF_ROOT.'/components/checkbox.php' );
require_once( JCF_ROOT.'/components/textarea.php' );
require_once( JCF_ROOT.'/components/datepicker/datepicker.php' );
require_once( JCF_ROOT.'/components/uploadmedia/uploadmedia.php' );
require_once( JCF_ROOT.'/components/fieldsgroup/fields-group.php' );
require_once( JCF_ROOT.'/components/relatedcontent/related-content.php' );


if(!function_exists('pa')){
function pa($mixed, $stop = false) {
	$ar = debug_backtrace(); $key = pathinfo($ar[0]['file']); $key = $key['basename'].':'.$ar[0]['line'];
	$print = array($key => $mixed); echo( '<pre>'.htmlentities(print_r($print,1)).'</pre>' );
	if($stop == 1) exit();
}
}

add_action('after_setup_theme', 'jcf_init');
function jcf_init(){
	if( !is_admin() ) return;
	
	/**
	 *	load translations
	 */
	load_plugin_textdomain( JCF_TEXTDOMAIN, false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
	
	// init global variables
	global $jcf_fields, $jcf_fieldsets;
	
	// add admin page
	add_action( 'admin_menu', 'jcf_admin_menu' );
	
	// add ajax call processors
	add_action('wp_ajax_jcf_add_fieldset', 'jcf_ajax_add_fieldset');
	add_action('wp_ajax_jcf_delete_fieldset', 'jcf_ajax_delete_fieldset');
	add_action('wp_ajax_jcf_change_fieldset', 'jcf_ajax_change_fieldset');
	add_action('wp_ajax_jcf_update_fieldset', 'jcf_ajax_update_fieldset');
	
	add_action('wp_ajax_jcf_add_field', 'jcf_ajax_add_field');
	add_action('wp_ajax_jcf_save_field', 'jcf_ajax_save_field');
	add_action('wp_ajax_jcf_delete_field', 'jcf_ajax_delete_field');
	add_action('wp_ajax_jcf_edit_field', 'jcf_ajax_edit_field');
	add_action('wp_ajax_jcf_fields_order', 'jcf_ajax_fields_order');

	add_action('wp_ajax_jcf_export_fields', 'jcf_ajax_export_fields');
	add_action('wp_ajax_jcf_export_fields_form', 'jcf_ajax_export_fields_form');
	add_action('wp_ajax_jcf_import_fields', 'jcf_ajax_import_fields');
	add_action('wp_ajax_jcf_check_file', 'jcf_ajax_check_file');

	add_action('admin_notices', 'jcf_admin_notice');
	// add $post_type for ajax
	if(!empty($_POST['post_type'])) jcf_set_post_type( $_POST['post_type'] );
	
	// init field classes and fields array
	jcf_field_register( 'Just_Field_Input' );
	jcf_field_register( 'Just_Field_Select' );
	jcf_field_register( 'Just_Field_SelectMultiple' );
	jcf_field_register( 'Just_Field_Checkbox' );
	jcf_field_register( 'Just_Field_Textarea' );
	jcf_field_register( 'Just_Field_DatePicker' );
	jcf_field_register( 'Just_Field_Upload' );
	jcf_field_register( 'Just_Field_FieldsGroup' );
	jcf_field_register( 'Just_Field_RelatedContent' );
	/**
	 *	to add more fields with your custom plugin:
	 *	- add_action  'jcf_register_fields'
	 *	- include your components files
	 *	- run jcf_field_register('YOUR_COMPONENT_CLASS_NAME');
	 */
	do_action( 'jcf_register_fields' );


	// add post edit/save hooks
	add_action( 'add_meta_boxes', 'jcf_post_load_custom_fields', 10, 1 ); 
	add_action( 'save_post', 'jcf_post_save_custom_fields', 10, 2 );
	
	// add custom styles and scripts
	if( !empty($_GET['page']) && $_GET['page'] == 'just_custom_fields' ){
		add_action('admin_print_styles', 'jcf_admin_add_styles');
		add_action('admin_print_scripts', 'jcf_admin_add_scripts');
	}
}

// hook to add new admin setting page
function jcf_admin_menu(){
	add_options_page(__('Just Custom Fields', JCF_TEXTDOMAIN), __('Just Custom Fields', JCF_TEXTDOMAIN), 'manage_options', 'just_custom_fields', 'jcf_admin_settings_page');
}

/**
 *	Admin main page
 */
function jcf_admin_settings_page(){
	$post_types = jcf_get_post_types( 'object' );
	$jcf_read_settings = jcf_get_read_settings();
	$jcf_multisite_settings = jcf_get_multisite_settings();
	$jcf_tabs = !isset($_GET['tab']) ? 'fields' : $_GET['tab'];

	// edit page
	if( !empty($_GET['pt']) && isset($post_types[ $_GET['pt'] ]) ){
		jcf_admin_fields_page( $post_types[ $_GET['pt'] ] );
		return;
	}

	if( !empty($_POST['save_import']) ) {
		$import_data = $_POST['import_data'];
		$save = jcf_admin_save_settings($import_data);
		$notice = $save ? array('notice' => '<strong>Import </strong>was success!') : array('error' => 'Error! <strong>Import </strong> was not sucess! Check the file of import');
		do_action('admin_notices', $notice);
	}
	
	if( !empty($_POST['jcf_update_settings']) ) {
		if( MULTISITE )
		{
			$jcf_multisite_settings = jcf_save_multisite_settings($jcf_multisite_settings);
		}
		$jcf_read_settings = jcf_update_read_settings();
	}
	// load template
	include( JCF_ROOT . '/templates/settings_page.tpl.php' );
}

/**
 *	Fields UI page
 */
function jcf_admin_fields_page( $post_type ){
	jcf_set_post_type( $post_type->name );
	$jcf_read_settings = jcf_get_read_settings();
	if( !empty($jcf_read_settings) && ($jcf_read_settings == 'theme' OR $jcf_read_settings == 'global') ){
		$jcf_settings = jcf_get_all_settings_from_file();
		$key = $post_type->name;
		$fieldsets = $jcf_settings['fieldsets'][$key];
		$field_settings = $jcf_settings['field_settings'][$key];
	}else{
		$fieldsets = jcf_fieldsets_get();
		$field_settings = jcf_field_settings_get();		
	}

	// load template
	include( JCF_ROOT . '/templates/fields_ui.tpl.php' );
}

/**
 *	Keep settings in the file of theme
 */
function jcf_admin_keep_settings($dir, $read_settings){
	$jcf_settings = jcf_get_all_settings_from_db();
	$settings_data = json_encode($jcf_settings);
	$home_dir = get_home_path();
	if( is_writable($home_dir)){
		if( !file_exists($dir) ){
			if( mkdir($dir, 0777) ){
				if( is_writable($dir) ){
					$save = jcf_admin_save_all_settings_in_file($settings_data, $read_settings);
					$notice = $save ? array('notice' => '<strong>Config file</strong> has saved') : array('error' => 'Error! <strong>Config file</strong> has not saved. Check the writable rules for ' . get_template_directory() . ' directory');
				}else{
					$notice = array('error' => 'Error! Check the writable rules for ' . $dir . ' directory ');
				}
			} else {
				$notice = array('error' => 'Error! <strong>Config file</strong> has not saved. Check the writable rules for ' . get_template_directory() . ' directory');
			}
		}else{
			$save = jcf_admin_save_all_settings_in_file($settings_data, $read_settings);
			$notice = $save ? array('notice' => '<strong>Config file</strong> has saved') : array('error' => 'Error! <strong>Config file</strong> has not saved. Check the writable rules for ' . get_template_directory() . ' directory');
		}
	}else{
		$notice = array('error' => 'Error! <strong>Config File</strong> has not created. Check the writable rules for ' . $home_dir . ' directory ');
	}
	do_action('admin_notices', $notice);
	return $save;
}

/**
 *	Import page
 */
function jcf_admin_import_page(){
	if( !empty($_POST['import-btn']) ){
		if(!empty($_FILES['import_data']['name']) ){
			$path_info = pathinfo($_FILES['import_data']['name']);

			if( $path_info['extension'] == 'json'){
				$uploaddir = get_home_path() . "wp-content/uploads/";
				$uploadfile = $uploaddir . basename($_FILES['import_data']['name']);

				if ( copy($_FILES['import_data']['tmp_name'], $uploadfile) ){
					$import_data = jcf_get_settings_from_file($uploadfile);
					$save = jcf_admin_save_settings($import_data);
					$notice = $save ? array('notice' => '<strong>Import</strong> was success, all fields has imported') : array('error' => 'Error! <strong>Import</strong> was not success' );
				}else{
					$notice = array('error' => 'Error! The file has not loaded!');
				}
			}else{
				$notice = array('error' => 'Error! Check <strong>extension</strong> of the file!');
			}
		}else{
			$notice = array('error' => 'Error! The file is empty!');
		}
	}
	do_action('admin_notices', $notice);
}

/**
 *	javascript localization
 */
function jcf_get_language_strings(){
	global $wp_version;

	$strings = array(
		'hi' => __('Hello there', JCF_TEXTDOMAIN),
		'edit' => __('Edit', JCF_TEXTDOMAIN),
		'delete' => __('Delete', JCF_TEXTDOMAIN),
		'confirm_field_delete' => __('Are you sure you want to delete selected field?', JCF_TEXTDOMAIN),
		'confirm_fieldset_delete' => __("Are you sure you want to delete the fieldset?\nAll fields will be also deleted!", JCF_TEXTDOMAIN),
		'update_image' => __('Update Image', JCF_TEXTDOMAIN),
		'update_file' => __('Update File', JCF_TEXTDOMAIN),
		'yes' => __('Yes', JCF_TEXTDOMAIN),
		'no' => __('No', JCF_TEXTDOMAIN),
		
		'wp_version' => $wp_version,
	);
	$strings = apply_filters('jcf_localize_script_strings', $strings);
	return $strings;
}

// print image with loader
function print_loader_img(){
	return '<img class="ajax-feedback " alt="" title="" src="' . get_bloginfo('url') . '/wp-admin/images/wpspin_light.gif" style="visibility: hidden;">';
}

// set post_type in global variable, so we can use it in internal functions
function jcf_set_post_type( $post_type ){
	global $jcf_post_type;
	$jcf_post_type = $post_type;
}

// return jcf_post_type global variable
function jcf_get_post_type(){
	global $jcf_post_type;
	return $jcf_post_type;
}

// get registered post types
function jcf_get_post_types( $format = 'single' ){
	
	$all_post_types = get_post_types(array('show_ui' => true ), 'object');
	
	$post_types = array();
	
	foreach($all_post_types as $key=>$val){
		
		//we should exclude 'revision' and 'nav menu items'
		if($val == 'revision' || $val == 'nav_menu_item') continue;
		
		$post_types[$key] = $val;
	}
	
	return $post_types;
}

// add custom scripts for plugin settings page
function jcf_admin_add_scripts() {
	wp_register_script(
			'just_custom_fields',
			WP_PLUGIN_URL.'/just-custom-fields/assets/just_custom_fields.js',
			array('jquery', 'json2', 'jquery-form', 'jquery-ui-sortable')
		);
	wp_enqueue_script('just_custom_fields');
	
	// add text domain
	wp_localize_script( 'just_custom_fields', 'jcf_textdomain', jcf_get_language_strings() );
}

// add custom styles for plugin settings page
function jcf_admin_add_styles() { 
	wp_register_style('jcf-styles', WP_PLUGIN_URL.'/just-custom-fields/assets/styles.css');
	wp_enqueue_style('jcf-styles'); 
}


// get all settings from db
function jcf_get_all_settings_from_db(){
	global $wpdb;
	$sql_posts_id="SELECT id, post_type FROM " . $wpdb->base_prefix . "posts WHERE post_type = 'post' OR post_type = 'page' OR post_type = 'attachment'";
	$posts = $wpdb->get_results($sql_posts_id);
	$jcf_settings = array();
	$post_types = jcf_get_post_types();
	$fieldsets = array();
	$field_settings = array();
	$field_options = array();
	foreach($post_types as $key => $value){
		$fieldsets[$key] = jcf_fieldsets_get('', 'jcf_fieldsets-'.$key);
		$field_settings[$key] = jcf_field_settings_get('', 'jcf_fields-'.$key, true);
		foreach($posts as $post){
			foreach($field_settings[$key] as $fskey => $field_setting){
				$field_setting = $field_setting;
				if($post->post_type == $key){
					$field_options[$post->post_type][$post->id][$field_setting['slug']] = get_post_meta($post->id, $field_setting['slug'], true);
				}
			}
		}
	}

	$jcf_settings = array(
		'post_types' => $post_types,
		'fieldsets' => $fieldsets,
		'field_settings' => $field_settings,
		'field_options' => $field_options,
	);
	return $jcf_settings;
}

// get all settings from file
function jcf_get_all_settings_from_file(){
	$filename = jcf_get_file_settings_name();
	if (file_exists($filename)) {
		return jcf_get_settings_from_file($filename);
	}else{
		$notice = array('error' => 'The file of settings is not found');
		do_action('admin_notices', $notice);
		return false;
	}
}

// get settings from file
function jcf_get_settings_from_file($uploadfile){
	$content = file_get_contents($uploadfile);
	$data = json_decode($content, true);
	return $data;
}

// save settings to file
function jcf_admin_save_all_settings_in_file($data, $saving_method = ''){
	$jcf_read_settings = $saving_method ? $saving_method :  jcf_get_read_settings();
	if( !empty($jcf_read_settings)){
		if ($jcf_read_settings == 'theme' ){
			$fp = fopen(get_template_directory() . '/jcf-settings/jcf_settings.json', 'w');
			$content = $data . "\r\n";
			$fw = fwrite($fp, $content);
			fclose($fp);
		}elseif($jcf_read_settings == 'global'){
			$fp = fopen(get_home_path() . 'wp-content/jcf-settings/jcf_settings.json', 'w');
			$content = $data . "\r\n";
			$fw = fwrite($fp, $content);
			fclose($fp);
		}else{
			$fw = false;
		}
	}
	if($fw){
		return true;
	}else{
		return false;
	}
}

// save settings in db
function jcf_admin_save_settings($data){
	foreach($data as $key => $post_type ){
		if(is_array($post_type) && !empty($post_type['fieldsets'])){
			foreach($post_type['fieldsets'] as $fieldset_id => $fieldset){
				$status_fieldset = jcf_import_add_fieldset($fieldset['title'], $key, $fieldset_id);
				if( empty($status_fieldset) ){
					$notice = array('error' => 'Error! Please check <strong>import file</strong>');
					do_action('admin_notices', $notice);
					return false;
				}else{
					$fieldset_id = $status_fieldset;
					if(!empty($fieldset['fields'])){
						$old_fields = jcf_field_settings_get('', 'jcf_fields-' . $key);
						if(!empty($old_fields)){
							foreach($old_fields as $old_field_id => $old_field){
								$old_slugs[] = $old_field['slug'];
								$old_field_ids[$old_field['slug']] = $old_field_id;
							}
						}
						foreach($fieldset['fields'] as $field_id => $field){
							$slug_checking = !empty($old_slugs) ? in_array($field['slug'], $old_slugs) : false;
							if($slug_checking){
								$status_field = jcf_import_add_field($old_field_ids[$field['slug']], $fieldset_id, $field, $key );
							}else{
								$status_field = jcf_import_add_field($field_id, $fieldset_id, $field, $key );
							}
						}
					}
				}
			}
			if( !empty($status_fieldset) ){
				if( $_POST['file_name'] ){
					unlink($_POST['file_name']);
				}
				
			}
		}
	}
	return $status_fieldset;
}

// get options
function jcf_get_options($key){
	$jcf_multisite_settings = jcf_get_multisite_settings();
	return $jcf_multisite_settings == 'network' ? get_site_option($key, array()) : get_option($key, array());
}

// update options
function jcf_update_options($key, $value){
	$jcf_multisite_settings = jcf_get_multisite_settings();
	$jcf_multisite_settings == 'network' ? update_site_option($key, $value) : update_option($key, $value);
	return true;
}

// admin notice
function jcf_admin_notice($args = array()){
	if(!empty($args))
	{
		foreach($args as $key => $value)
		{
			echo '<div id="message" class="updated notice ' . ($key == 'error' ? $key . ' is-dismissible' : 'is-dismissible') . ' below-h2 "><p>' . $value . '</p><button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></button></div>';
		}
	}
}

// get read sttings
function jcf_get_read_settings(){
	$multisite_setting = jcf_get_multisite_settings();
	$jcf_read_settings = $multisite_setting == 'network' ? get_site_option('jcf_read_settings') : get_option('jcf_read_settings') ;
	return $jcf_read_settings;
}
	
// get file name for all settings
function jcf_get_file_settings_name(){
	$jcf_read_settings = jcf_get_read_settings();
	if(!empty($jcf_read_settings) && ($jcf_read_settings == 'theme' OR $jcf_read_settings == 'global') ){
		return $jcf_read_settings == 'theme' ? get_template_directory() . '/jcf-settings/jcf_settings.json' : get_home_path() . 'wp-content/jcf-settings/jcf_settings.json' ;
	}else{
		return false;
	}
}

// update read settings
function jcf_update_read_settings(){
	$jcf_read_settings = jcf_get_read_settings();
	$read_settings = $_POST['jcf_read_settings'];
	if($_POST['jcf_multisite_setting'] != 'network' && $read_settings == 'global' ){
		$notice = array('error' => 'Error! <strong>Saving method</strong> has not saved. Please change the <strong>multisite setting</strong> on "Make fields settings global for all network"');
		do_action('admin_notices', $notice);
		return $jcf_read_settings;
	}else{
		$multisite_setting = $_POST['jcf_multisite_setting'];
		if( !empty($jcf_read_settings) ){
			if($_POST['jcf_keep_settings']){
				if( $read_settings == 'theme' OR $read_settings == 'global' ){
					$settings_dir = $read_settings == 'theme' ? get_template_directory() . '/jcf-settings/' : get_home_path() . 'wp-content/jcf-settings/';
					if( jcf_admin_keep_settings($settings_dir, $read_settings) ){
						$save = $multisite_setting == 'network' ? update_site_option('jcf_read_settings', $read_settings) : update_option('jcf_read_settings', $read_settings);
						$notice = $save ? array('notice' => '<strong>Saving method</strong> has saved') : array();
					} else {
						$notice = array('error' => 'Error! <strong>Saving method</strong> has not saved.');
					}
				}
			}else{
				$save = $multisite_setting == 'network' ? update_site_option('jcf_read_settings', $read_settings) : update_option('jcf_read_settings', $read_settings);
				$notice = $save ? array('notice' => '<strong>Saving method</strong> has saved') : array();
			}
		}else{
			$save = $multisite_setting == 'network' ? add_site_option('jcf_read_settings', $read_settings) : add_option('jcf_read_settings', $read_settings);
			$notice = $save ? array('notice' => '<strong>Saving method</strong> has saved') : array();
		}
		do_action('admin_notices', $notice);
		return $save ? $read_settings : $jcf_read_settings;
	}
}
?>
