<?php

namespace jcf\components\googlemaps;

use jcf\core;
use jcf\models\Settings;

class JustField_GoogleMaps extends core\JustField
{

	public function __construct()
	{
		$field_ops = array( 'classname' => 'field_googlemaps' );
		parent::__construct('googlemaps', __('Google Maps', \JustCustomFields::TEXTDOMAIN), $field_ops);
	}

	/**
	 * 	draw field on post edit form
	 * 	you can use $this->instance, $this->entry
	 */
	public function field()
	{
		$api_key = Settings::getGoogleMapsApiKey();

		$this->entry = wp_parse_args($this->entry, array('lat' => '', 'lng' => '', 'address' => '',));
		?>
		<div id="jcf_field-<?php echo $this->id; ?>" class="jcf_edit_field <?php echo $this->fieldOptions['classname']; ?>">
			<?php echo $this->fieldOptions['before_widget']; ?>
				<?php echo $this->fieldOptions['before_title'] . esc_html($this->instance['title']) . $this->fieldOptions['after_title']; ?>
				<br />

				<?php if ( empty($api_key) ) : ?>
					<strong>Please set Google Maps API Key on Just Custom Fields <a href="<?php echo esc_url( admin_url('options-general.php?page=jcf_settings')); ?>">Settings</a> page.</strong>
				<?php else : ?>
					<div>
						<input type="text" id="<?php echo $this->getFieldId('address'); ?>" name="<?php echo $this->getFieldName('address'); ?>"
							   value="<?php echo esc_attr($this->entry['address']); ?>" class="jcf-half-width">
						<input id="<?php echo $this->getFieldId('search_btn'); ?>" type="button" class="button" value="Find">
					</div>
					<div id="<?php echo $this->getFieldId('map'); ?>" style="width: 100%; height: 400px;"></div>

					<input type="hidden"
						   name="<?php echo $this->getFieldName('lat'); ?>"
						   id="<?php echo $this->getFieldId('lat'); ?>"
						   value="<?php echo esc_attr($this->entry['lat']); ?>"/>
					<input type="hidden"
						   name="<?php echo $this->getFieldName('lng'); ?>"
						   id="<?php echo $this->getFieldId('lng'); ?>"
						   value="<?php echo esc_attr($this->entry['lng']); ?>"/>

					<?php if ( $this->instance['description'] != '' ) : ?>
						<p class="howto"><?php echo esc_html($this->instance['description']); ?></p>
					<?php endif; ?>

					<script>
						window.jcf_googlemaps.push({
						  'id': '<?php echo esc_attr( $this->id ); ?>',
						  'map_id': '<?php echo $this->getFieldId('map'); ?>',
						  'address_id': '<?php echo $this->getFieldId('address'); ?>',
						  'search_btn_id': '<?php echo $this->getFieldId('search_btn'); ?>',
						  'lng_ctrl_id': '#<?php echo $this->getFieldId('lng'); ?>',
						  'lat_ctrl_id': '#<?php echo $this->getFieldId('lat'); ?>',
						  'lat': <?php echo (float)$this->entry['lat']; ?>,
						  'lng': <?php echo (float)$this->entry['lng']; ?>,
						  'markers': []
						})
					</script>
				<?php endif; ?>

			<?php echo $this->fieldOptions['after_widget']; ?>
		</div>
		<?php
	}

	/**
	 * 	save field on post edit form
	 */
	public function save( $values )
	{
		$values = array(
			'lat' => $values['lat'],
			'lng' => $values['lng'],
			'address' => $values['address'],
		);
		return $values;
	}

	/**
	 * draw form for 
	 */
	public function form()
	{
		$instance = wp_parse_args((array) $this->instance, array( 'title' => '', 'description' => '', ));
		$description = esc_html($instance['description']);
		$title = esc_attr($instance['title']);
		$api_key = Settings::getGoogleMapsApiKey();
		?>
		<?php if ( empty($api_key) ) : ?>
			<div class="error"><?php _e('Please set Google Maps API Key on Settings page.', JCF_TEXTDOMAIN); ?></div>
		<?php endif; ?>

		<p><label for="<?php echo $this->getFieldId('title'); ?>"><?php _e('Title:', \JustCustomFields::TEXTDOMAIN); ?></label> <input class="widefat" id="<?php echo $this->getFieldId('title'); ?>" name="<?php echo $this->getFieldName('title'); ?>" type="text" value="<?php echo $title; ?>" /></p>
		<p><label for="<?php echo $this->getFieldId('description'); ?>"><?php _e('Description:', \JustCustomFields::TEXTDOMAIN); ?></label> <textarea name="<?php echo $this->getFieldName('description'); ?>" id="<?php echo $this->getFieldId('description'); ?>" cols="20" rows="4" class="widefat"><?php echo $description; ?></textarea></p>
		<?php
	}
	
	/**
	 * 	add custom scripts
	 */
	public function addJs()
	{
		if ( $api_key = Settings::getGoogleMapsApiKey() ) {
			wp_register_script('jcf_googlemaps_api', esc_url('//maps.googleapis.com/maps/api/js?key=' . $api_key), array('jquery'), '3', false);
			wp_enqueue_script('jcf_googlemaps_api');

			wp_register_script('jcf_googlemaps_events', plugins_url( '/assets/googlemaps.js', __FILE__ ), array('jquery', 'jcf_googlemaps_api'));
			wp_enqueue_script('jcf_googlemaps_events');
		}
	}

	/**
	 * 	update instance (settings) for current field
	 */
	public function update( $new_instance, $old_instance )
	{
		$instance = $old_instance;
		$instance['title'] = strip_tags($new_instance['title']);
		$instance['description'] = strip_tags($new_instance['description']);
		return $instance;
	}
	
	/**
	 * print field values inside the shortcode
	 *
	 * @params array $args	shortcode args
	 */
	public function shortcodeValue( $args )
	{
		/*
		if ( empty($this->entry) ) return '';
		$markers = str_replace('-', '_', $this->getFieldId('markers'));
		$function_prefix = str_replace('-', '', $this->id);
		$instance = wp_parse_args((array) $this->instance, array( 'title' => '', 'description' => '', 'api_key' => '' ));
		$api_key = esc_attr($instance['api_key']);
		
		ob_start();
		?>
		<script>
			(function ($){
				if ( $('script[id="jcf-google-map"]').length < 1 ) {
					document.write('<script id="jcf-google-map" src="http://maps.googleapis.com/maps/api/js?key=<?= $api_key; ?>&ver=3"><\/script>');
				}
			})(jQuery);
		</script>
		
		<div id="jcf-map-<?php echo $this->id; ?>" style="width: 100%; height: 400px;"></div>
		
		<script>
			google.maps.event.addDomListener(window, 'load', function() {
				var map = new google.maps.Map(document.getElementById('jcf-map-<?php echo $this->id; ?>'), {
					zoom: 2,
					center: {lat: 5.397, lng: 5.644},
				});

				<?php if ( !( empty($this->entry['lng']) || empty($this->entry['lat']) ) ) : ?>
					map.setCenter({lat: <?php echo $this->entry['lat']; ?>, lng: <?php echo $this->entry['lng']; ?>});
					var marker = new google.maps.Marker({
						position: {lat: <?php echo $this->entry['lat']; ?>, lng: <?php echo $this->entry['lng']; ?>},
						map: map
					});
				<?php endif; ?>
			});
		</script>
		<?php
		$content = ob_get_clean();

		return $content;
		*/
		return '';
	}

}


