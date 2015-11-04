<?php

class acf_field_taxonomy_relationship extends acf_field {
	
	// vars
	var $settings, // will hold info such as dir / path
		$defaults; // will hold default field options
		
		
	/*
	*  __construct
	*
	*  Set name / label needed for actions / filters
	*
	*  @since	3.6
	*  @date	23/01/13
	*/
	
	function __construct()
	{
		// vars
		$this->name = 'taxonomy_relationship';
		$this->label = __('Taxonomy Relationship');
		$this->category = __("Relational",'acf'); // Basic, Content, Choice, etc
		$this->defaults = array(
			'max' 					=>	'',
			'taxonomy'				=>	array('all'),
			'filters'				=>	array('search'),
			'return_format'			=>	'object'
		);
		$this->l10n = array(
			'max'		=> __("Maximum values reached ( {max} values )",'acf'),
			'tmpl_li'	=> '
							<li>
								<a href="#" data-term_id="<%= term_id %>"><%= title %><span class="acf-button-remove"></span></a>
								<input type="hidden" name="<%= name %>[]" value="<%= term_id %>" />
							</li>
							'
		);
		
		
		// do not delete!
    	parent::__construct();
    	
    	// extra
		add_action('wp_ajax_acf/fields/taxonomy_relationship/query_terms', array($this, 'query_terms'));
		add_action('wp_ajax_nopriv_acf/fields/taxonomy_relationship/query_terms', array($this, 'query_terms'));
    	
    	// settings
		$this->settings = array(
			'path' => apply_filters('acf/helpers/get_path', __FILE__),
			'dir' => apply_filters('acf/helpers/get_dir', __FILE__),
			'version' => '1.0.0'
		);

	}
	
	/*
	*  load_field()
	*  
	*  This filter is appied to the $field after it is loaded from the database
	*  
	*  @type filter
	*  @since 3.6
	*  @date 23/01/13
	*  
	*  @param $field - the field array holding all the field options
	*  
	*  @return $field - the field array holding all the field options
	*/
	
	function load_field( $field )
	{
	
		if( !$field['taxonomy'] || !is_array($field['taxonomy']) || in_array('', $field['taxonomy']) )
		{
			$field['taxonomy'] = array( 'all' );
		}
		
		// filters
		if( !is_array( $field['filters'] ) )
		{
			$field['filters'] = array();
		}
		
		
		// return
		return $field;
	}
	
	
	/*
	* get_terms_and_filter
	*
	* @description: now we're querying terms instead of posts, this replaces posts_where
	* created: 16/07/14
	*/
	
	function get_terms_and_filter($taxonomy, $like_title) {
		$terms = get_terms($taxonomy);		
		foreach ($terms as $key => $term) {
			if(stripos($term->name, $like_title)===false) {
				unset($terms[$key]);
			}
		}
		$filtered_terms = $terms;
		return $filtered_terms;
	}
	
	/*
	*  query_terms
	*
	*  @description: 
	*  @since: 3.6
	*  @created: 27/01/13
	*/
	
	function query_terms()
   	{
   		// vars
   		$r = array(
   			'html' => ''
   		);
   		
   		
   		// options
		$options = array(
			's'							=>	'',
			'lang'						=>	false,
			'field_key'					=>	'',
			'nonce'						=>	'',
			'ancestor'					=>	false,
		);
		
		$options = array_merge( $options, $_POST );
		
		// validate
		if( ! wp_verify_nonce($options['nonce'], 'acf_nonce') )
		{
			die();
		}
		
		
		// WPML
		if( $options['lang'] )
		{
			global $sitepress;
			
			if( !empty($sitepress) )
			{
				$sitepress->switch_lang( $options['lang'] );
			}
		}
		
		
		// convert types
		$options['taxonomy'] = explode(',', $options['taxonomy']);
		
		// search
		if( $options['s'] )
		{
			$options['like_title'] = $options['s'];
		}
		
		unset( $options['s'] );
		
		
		// load field
		$field = array();
		if( $options['ancestor'] )
		{
			$ancestor = apply_filters('acf/load_field', array(), $options['ancestor'] );
			$field = acf_get_child_field_from_parent_field( $options['field_key'], $ancestor );
		}
		else
		{
			$field = apply_filters('acf/load_field', array(), $options['field_key'] );
		}
		
		
		// get the post from which this field is rendered on
		$the_post = get_post( $options['post_id'] );
		
		// filters
		$options = apply_filters('acf/fields/taxonomy_relationship/query', $options, $field, $the_post);
		$options = apply_filters('acf/fields/taxonomy_relationship/query/name=' . $field['_name'], $options, $field, $the_post );
		$options = apply_filters('acf/fields/taxonomy_relationship/query/key=' . $field['key'], $options, $field, $the_post );
		
		// query
		
		$total_terms = array();
		if(is_array($options['taxonomy'])) {
			$tax = $options['taxonomy'];
			if(in_array('all', $tax)) {
				$taxonomy_args = array('public' => true);
				$taxonomies = get_taxonomies($taxonomy_args, 'names');
				foreach ($taxonomies as $t => $taxonomy) {
					if($options['like_title']) {
						$terms = $this->get_terms_and_filter($taxonomy, $options['like_title']);
					} else {
						$terms = get_terms($taxonomy);
					}
					$total_terms = array_merge($total_terms, $terms);
				};
			} else {
				foreach($tax as $t => $taxonomy) {
					if($options['like_title']) {
						$terms = $this->get_terms_and_filter($taxonomy, $options['like_title']);
					} else {
						$terms = get_terms($taxonomy);
					}
					$total_terms = array_merge($total_terms, $terms);
				}
			};
		} else {
			$tax = $options['taxonomy'];
			if($options['like_title']) {
				$total_terms = $this->get_terms_and_filter($tax, $options['like_title']);
			} else {
				$total_terms = get_terms($tax);
			}
		};
		
		// global
		global $post;
		
		foreach($total_terms as $term_name => $term) {
			$title = '<span class="relationship-item-info">';
			$title .= $term->taxonomy;
			$title .= '</span>';
			$title .= apply_filters('the_title', $term->name , $term->term_id);	
				
			// WPML
			if( $options['lang'] )
			{
				$title .= ' (' . $options['lang'] . ')';
			}
			
			///update html
			$r['html'] .= '<li><a href="' . get_bloginfo('home') . '/' . $term->taxonomy . '/' . $term->slug . '" data-term_id="' . $term->term_id . '">' . $title .  '<span class="acf-button-add"></span></a></li>';
		}
		
		wp_reset_postdata();		
		
		// return JSON
		echo json_encode( $r );
		
		die();
   					
	}
	
	
	/*
	*  create_field()
	*
	*  Create the HTML interface for your field
	*
	*  @param	$field - an array holding all the field's data
	*
	*  @type	action
	*  @since	3.6
	*  @date	23/01/13
	*/
	
	function create_field( $field )
	{ 
		// global
		global $post;
		
		// no row limit?
		if( !$field['max'] || $field['max'] < 1 )
		{
			$field['max'] = 9999;
		}
		
		// class
		$class = '';
		if( $field['filters'] )
		{
			foreach( $field['filters'] as $filter )
			{
				$class .= ' has-' . $filter;
			}
		}
		
		$attributes = array(
			'max' => $field['max'],
			's' => '',
			'taxonomy' => implode(',', $field['taxonomy']),
			'field_key' => $field['key']
		);
		
		// Lang
		if( defined('ICL_LANGUAGE_CODE') )
		{
			$attributes['lang'] = ICL_LANGUAGE_CODE;
		}
		
		// parent
		preg_match('/\[(field_.*?)\]/', $field['name'], $ancestor);
		if( isset($ancestor[1]) && $ancestor[1] != $field['key'])
		{
			$attributes['ancestor'] = $ancestor[1];
		}
				
		?>
		
		<div class="acf_taxonomy_relationship<?php echo $class; ?>"<?php foreach( $attributes as $k => $v ): ?> data-<?php echo $k; ?>="<?php echo $v; ?>"<?php endforeach; ?>>
			
			<!-- Hidden Blank default value -->
			<input type="hidden" name="<?php echo $field['name']; ?>" value="" />
	
			<!-- Left List -->
			<div class="relationship_left">
				<table class="widefat">
					<thead>
						<?php if(in_array( 'search', $field['filters']) ): ?>
						<tr>
							<th>
								<input class="relationship_search" placeholder="<?php _e("Search...",'acf'); ?>" type="text" id="relationship_<?php echo $field['name']; ?>" />
							</th>
						</tr>
						<?php endif; ?>
					</thead>
				</table>
				<ul class="bl relationship_list">
					<li class="load-more">
						<div class="acf-loading"></div>
					</li>
				</ul>
			</div>
			<!-- /Left List -->
	
			<!-- Right List -->
			<div class="relationship_right">
				<ul class="bl relationship_list">
					<?php 
					if( $field['value'] )
					{ 
						
						foreach( $field['value'] as $key => $term_id )
						{
							$term_object = '';
							if(is_array($field['taxonomy'])) {
								$tax = $field['taxonomy'];
								if(in_array('all', $tax)) {
									$taxonomy_args = array('public' => true);
									$tax = get_taxonomies($taxonomy_args, 'names');
									foreach ($tax as $t => $taxonomy) {
										if(term_exists($term_id, $taxonomy)) {
											$term_object = get_term($term_id, $taxonomy);
										}
									}
								} else {
									foreach ($tax as $t => $taxonomy) {
										if(term_exists($term_id, $taxonomy)) {
											$term_object = get_term($term_id, $taxonomy);
										}
									}
								};
							} else {
								if(term_exists($term_id, $taxonomy)) {
									$term_object = get_term($term_id, $field['taxonomy']);
								}
							};
			
							// right aligned info
							$title = '<span class="relationship-item-info">';
							$title .= $term_object->taxonomy;
							$title .= '</span>';
							
							// find title. Could use get_the_title, but that uses get_post(), so I think this uses less Memory
							
							$title .= apply_filters('the_title', $term_object->name , $term_object->term_id);
							
							$fieldnewslot = $field['name'] . "[]";
							$termlink = get_term_link($term_object->term_id, $term_object->taxonomy);
							
							if(!is_wp_error($termlink)) {	
							
								echo '<li>
									<a href="' . $termlink . '" class="" data-term_id="' . $term_object->term_id . '">' . $title . '
										<span class="acf-button-remove"></span>
									</a>
									<input type="hidden" name="' . $fieldnewslot . '" value="' . $term_object->term_id . '" />
								</li>';
							
							}
								
						}
					} ?>
				</ul>
			</div>
			<!-- / Right List -->
	
		</div>
	<?php 
	}
	
	
	/*
	*  create_options()
	*
	*  Create extra options for your field. This is rendered when editing a field.
	*  The value of $field['name'] can be used (like bellow) to save extra data to the $field
	*
	*  @type	action
	*  @since	3.6
	*  @date	23/01/13
	*
	*  @param	$field	- an array holding all the field's data
	*/
	
	function create_options( $field )
	{
		// vars
		$key = $field['name'];
		
		?>
<tr class="field_option field_option_<?php echo $this->name; ?>">
	<td class="label">
		<label><?php _e("Return Format",'acf'); ?></label>
		<p><?php _e("Specify the returned value on front end",'acf') ?></p>
	</td>
	<td>
		<?php
		do_action('acf/create_field', array(
			'type'		=>	'radio',
			'name'		=>	'fields['.$key.'][return_format]',
			'value'		=>	$field['return_format'],
			'layout'	=>	'horizontal',
			'choices'	=> array(
				'object'	=>	__("Term Objects",'acf'),
				'id'		=>	__("Term IDs",'acf')
			)
		));
		?>
	</td>
</tr>
<tr class="field_option field_option_<?php echo $this->name; ?>">
	<td class="label">
		<label><?php _e("Select Taxonomy", 'acf'); ?></label>
	</td>
	<td>
	<?php
	$choices = array(
		'' => array(
			'all' => __("All",'acf')
		)
	);
	
	$taxonomy_args = array('public' => true);
	$taxonomies = get_taxonomies($taxonomy_args, 'objects');
	
	foreach($taxonomies as $tax_name => $taxonomy) {
		$labels_object = $taxonomy->labels;
		$choices[''][$tax_name] = $labels_object->name;
	}
	
	do_action('acf/create_field', array(
			'type'	=>	'select',
			'name'	=>	'fields['.$key.'][taxonomy]',
			'value'	=>	$field['taxonomy'],
			'choices' => $choices,
			'multiple'	=>	1,
		));
	?>
	</td>
</tr>
<tr class="field_option field_option_<?php echo $this->name; ?>">
	<td class="label">
		<label><?php _e("Filters",'acf'); ?></label>
	</td>
	<td>
		<?php 
		do_action('acf/create_field', array(
			'type'	=>	'checkbox',
			'name'	=>	'fields['.$key.'][filters]',
			'value'	=>	$field['filters'],
			'choices'	=>	array(
				'search'	=>	__("Search",'acf'),
			)
		));
		?>
	</td>
</tr>
<tr class="field_option field_option_<?php echo $this->name; ?>">
	<td class="label">
		<label><?php _e("Maximum terms",'acf'); ?></label>
	</td>
	<td>
		<?php 
		do_action('acf/create_field', array(
			'type'	=>	'number',
			'name'	=>	'fields['.$key.'][max]',
			'value'	=>	$field['max'],
		));
		?>
	</td>
</tr>
		<?php
		
	}
	
	function input_admin_enqueue_scripts()
	{
		// Note: This function can be removed if not used


		// register ACF scripts
		wp_register_script( 'acf-input-taxonomy_relationship', $this->settings['dir'] . 'js/input.js', array('underscore', 'acf-input'), $this->settings['version'] );
		wp_register_style( 'acf-input-taxonomy_relationship', $this->settings['dir'] . 'css/input.css', array('acf-input'), $this->settings['version'] ); 


		// scripts
		wp_enqueue_script(array(
			'acf-input-taxonomy_relationship',	
		));

		// styles
		wp_enqueue_style(array(
			'acf-input-taxonomy_relationship',	
		));


	}	
	
	/*
	*  format_value()
	*
	*  This filter is appied to the $value after it is loaded from the db and before it is passed to the create_field action
	*
	*  @type	filter
	*  @since	3.6
	*  @date	23/01/13
	*
	*  @param	$value	- the value which was loaded from the database
	*  @param	$post_id - the $post_id from which the value was loaded
	*  @param	$field	- the field array holding all the field options
	*
	*  @return	$value	- the modified value
	*/
	
	function format_value( $value, $post_id, $field )
	{
		// empty?
		if( !empty($value) )
		{
			// Pre 3.3.3, the value is a string coma seperated
			if( is_string($value) )
			{
				$value = explode(',', $value);
			}
			
			
			// convert to integers
			if( is_array($value) )
			{
				$value = array_map('intval', $value);
				
			}
			
		}
		
		
		// return value
		return $value;	
	}
	
	/*
	*  format_value_for_api()
	*
	*  This filter is appied to the $value after it is loaded from the db and before it is passed back to the api functions such as the_field
	*
	*  @type	filter
	*  @since	3.6
	*  @date	23/01/13
	*
	*  @param	$value	- the value which was loaded from the database
	*  @param	$post_id - the $post_id from which the value was loaded
	*  @param	$field	- the field array holding all the field options
	*
	*  @return	$value	- the modified value
	*/
	
	function format_value_for_api( $value, $post_id, $field )
	{
		// empty?
		if( !$value )
		{
			return $value;
		}
		
		
		// Pre 3.3.3, the value is a string coma seperated
		if( is_string($value) )
		{
			$value = explode(',', $value);
		}
		
		
		// empty?
		if( !is_array($value) || empty($value) )
		{
			return $value;
		}
		
		
		// convert to integers
		$value = array_map('intval', $value);
		
		
		// return format
		if( $field['return_format'] == 'object' )
		{
			$return_array = array();
			foreach( $value as $key => $term_id ) {
				$term_object = '';
				if(is_array($field['taxonomy'])) {
					$tax = $field['taxonomy'];
					if(in_array('all', $tax)) {
						$taxonomy_args = array('public' => true);
						$tax = get_taxonomies($taxonomy_args, 'names');
						foreach ($tax as $t => $taxonomy) {
							if(term_exists($term_id, $taxonomy)) {
								$return_array[] = get_term($term_id, $taxonomy);
							}
						}
					} else {
						foreach ($tax as $t => $taxonomy) {
							if(term_exists($term_id, $taxonomy)) {
								$return_array[] = get_term($term_id, $taxonomy);
							}
						}
					};
				} else {
					if(term_exists($term_id, $taxonomy)) {
						$return_array[] = get_term($term_id, $field['taxonomy']);
					}
				};
				
			};
			
			$value = $return_array;
		};
		
		
		// return
		return $value;
		
	}
	
}


// create field
new acf_field_taxonomy_relationship();

?>
