<?php if( ! defined( 'ABSPATH' ) ) exit;

if( !class_exists('door4_acf_field_taxonomy_relationship') ) :
    class door4_acf_field_taxonomy_relationship extends acf_field {

        /*
        *  __construct
        *
        *  This function will setup the field type data
        *
        *  @type	function
        *  @date	5/03/2014
        *  @since	5.0.0
        *
        *  @param	n/a
        *  @return	n/a
        */

        function __construct() {
            $this->name = 'taxonomy_relationship';
            $this->label = __('Taxonomy Relationship', 'acf-taxonomy-relationship');
            $this->category = 'relational';
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
            $this->settings = array(
                'path' => apply_filters('acf/helpers/get_path', __FILE__),
                'dir' => str_replace($_SERVER['DOCUMENT_ROOT'], '', __DIR__),
                'version' => '1.0.0'
            );

            // extra
            add_action('wp_ajax_acf/fields/taxonomy_relationship/query_terms', array($this, 'query_terms'));
            add_action('wp_ajax_nopriv_acf/fields/taxonomy_relationship/query_terms', array($this, 'query_terms'));

            parent::__construct();
        }

        /*
        *  load_field()
        *
        *  This filter is applied to the $field after it is loaded from the database
        *
        *  @type	filter
        *  @date	23/01/2013
        *  @since	3.6.0
        *
        *  @param	$field (array) the field array holding all the field options
        *  @return	$field
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
            if( ! wp_verify_nonce($options['nonce'], 'acf_nonce') ) {
                die();
            }

            // WPML
            if( $options['lang'] ) {
                global $sitepress;
                if( !empty($sitepress) )
                {
                    $sitepress->switch_lang( $options['lang'] );
                }
            }

            // convert types
            $options['taxonomy'] = explode(',', $options['taxonomy']);

            // search
            if( $options['s'] ) {
                $options['like_title'] = $options['s'];
            }

            unset( $options['s'] );

            // load field
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
        *  render_field_settings()
        *
        *  Create extra settings for your field. These are visible when editing a field
        *
        *  @type	action
        *  @since	3.6
        *  @date	23/01/13
        *
        *  @param	$field (array) the $field being edited
        *  @return	n/a
        */

        function render_field_settings( $field ) {

            /*
            *  acf_render_field_setting
            *
            *  This function will create a setting for your field. Simply pass the $field parameter and an array of field settings.
            *  The array of settings does not require a `value` or `prefix`; These settings are found from the $field array.
            *
            *  More than one setting can be added by copy/paste the above code.
            *  Please note that you must also have a matching $defaults value for the field name (font_size)
            */

            acf_render_field_setting( $field, array(
                'label' =>  __('Return Format', 'acf-taxonomy-relationship'),
                'instructions' => __('Specify the returned value on front end', 'acf-taxonomy-relationship'),
                'type' => 'radio',
                'name' => 'return_format',
                'layout' => 'horizontal',
                'choices' => array(
                    'object' => __("Term Objects",'acf-taxonomy-relationship'),
                    'id' => __("Term IDs",'acf-taxonomy-relationship')
                )
            ), true);

            acf_render_field_setting( $field, array(
                'label' =>  __('Select Taxonomy', 'acf-taxonomy-relationship'),
                'instructions' => __('Specify which taxonomies this field can choose terms from', 'acf-taxonomy-relationship'),
                'type'	=>	'select',
                'name'	=>	'taxonomy',
                'choices' => $this->getAvailableTaxonomyOptions(),
                'multiple'	=>	1,
            ), true);

            acf_render_field_setting( $field, array(
                'label' =>  __('Filters', 'acf-taxonomy-relationship'),
                'instructions' => __('Options for selecting terms', 'acf-taxonomy-relationship'),
                'type'	=>	'checkbox',
                'name'	=>	'filters',
                'choices'	=>	array(
                    'search'	=>	__("Search",'acf'),
                )
            ), true);

            acf_render_field_setting( $field, array(
                'label' =>  __('Maximum Terms', 'acf-taxonomy-relationship'),
                'instructions' => __('Set a limit for the amount of terms which can be selected', 'acf-taxonomy-relationship'),
                'type'	=>	'number',
                'name'	=>	'max',
            ), true);
        }

        private function getAvailableTaxonomyOptions()
        {
            $taxonomyOptions = array(
                '' => array(
                    'all' => __("All", 'acf-taxonomy-relationship')
                )
            );

            $taxonomy_args = array('public' => true);
            $taxonomies = get_taxonomies($taxonomy_args, 'objects');

            foreach ($taxonomies as $tax_name => $taxonomy) {
                $labels_object = $taxonomy->labels;
                $taxonomyOptions[''][$tax_name] = $labels_object->name;
            }

            return $taxonomyOptions;
        }

        /*
        *  render_field()
        *
        *  Create the HTML interface for your field
        *
        *  @param	$field (array) the $field being rendered
        *
        *  @type	action
        *  @since	3.6
        *  @date	23/01/13
        *
        *  @param	$field (array) the $field being edited
        *  @return	n/a
        */

        function render_field( $field ) {
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
                        <?php if ( $field['value'] && is_array($field['value'])) {
                            foreach ( $field['value'] as $key => $term_id ) {
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
                                    if(term_exists($term_id, $field['taxonomy'])) {
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
        *  input_admin_enqueue_scripts()
        *
        *  This action is called in the admin_enqueue_scripts action on the edit screen where your field is created.
        *  Use this action to add CSS + JavaScript to assist your render_field() action.
        *
        *  @type	action (admin_enqueue_scripts)
        *  @since	3.6
        *  @date	23/01/13
        *
        *  @param	n/a
        *  @return	n/a
        */

        function input_admin_enqueue_scripts() {
            $version = $this->settings['version'];
            // scripts
            wp_register_script( 'acf-input-taxonomy_relationship', $this->settings['dir'] . '/js/input-v5.js', array('underscore', 'acf-input'), $version );
            wp_enqueue_script('acf-input-taxonomy_relationship');
            // styles
            wp_register_style( 'acf-input-taxonomy_relationship', $this->settings['dir'] . '/css/input.css', array('acf-input'), $version );
            wp_enqueue_style('acf-input-taxonomy_relationship');
        }


        /*
        *  input_admin_head()
        *
        *  This action is called in the admin_head action on the edit screen where your field is created.
        *  Use this action to add CSS and JavaScript to assist your render_field() action.
        *
        *  @type	action (admin_head)
        *  @since	3.6
        *  @date	23/01/13
        *
        *  @param	n/a
        *  @return	n/a
        */

        /*

        function input_admin_head() {



        }

        */


        /*
           *  input_form_data()
           *
           *  This function is called once on the 'input' page between the head and footer
           *  There are 2 situations where ACF did not load during the 'acf/input_admin_enqueue_scripts' and
           *  'acf/input_admin_head' actions because ACF did not know it was going to be used. These situations are
           *  seen on comments / user edit forms on the front end. This function will always be called, and includes
           *  $args that related to the current screen such as $args['post_id']
           *
           *  @type	function
           *  @date	6/03/2014
           *  @since	5.0.0
           *
           *  @param	$args (array)
           *  @return	n/a
           */

        /*

        function input_form_data( $args ) {



        }

        */


        /*
        *  input_admin_footer()
        *
        *  This action is called in the admin_footer action on the edit screen where your field is created.
        *  Use this action to add CSS and JavaScript to assist your render_field() action.
        *
        *  @type	action (admin_footer)
        *  @since	3.6
        *  @date	23/01/13
        *
        *  @param	n/a
        *  @return	n/a
        */

        /*

        function input_admin_footer() {



        }

        */


        /*
        *  field_group_admin_enqueue_scripts()
        *
        *  This action is called in the admin_enqueue_scripts action on the edit screen where your field is edited.
        *  Use this action to add CSS + JavaScript to assist your render_field_options() action.
        *
        *  @type	action (admin_enqueue_scripts)
        *  @since	3.6
        *  @date	23/01/13
        *
        *  @param	n/a
        *  @return	n/a
        */

        /*

        function field_group_admin_enqueue_scripts() {

        }

        */


        /*
        *  field_group_admin_head()
        *
        *  This action is called in the admin_head action on the edit screen where your field is edited.
        *  Use this action to add CSS and JavaScript to assist your render_field_options() action.
        *
        *  @type	action (admin_head)
        *  @since	3.6
        *  @date	23/01/13
        *
        *  @param	n/a
        *  @return	n/a
        */

        /*

        function field_group_admin_head() {

        }

        */


        /*
        *  load_value()
        *
        *  This filter is applied to the $value after it is loaded from the db
        *
        *  @type	filter
        *  @since	3.6
        *  @date	23/01/13
        *
        *  @param	$value (mixed) the value found in the database
        *  @param	$post_id (mixed) the $post_id from which the value was loaded
        *  @param	$field (array) the field array holding all the field options
        *  @return	$value
        */

        /*

        function load_value( $value, $post_id, $field ) {

            return $value;

        }

        */


        /*
        *  update_value()
        *
        *  This filter is applied to the $value before it is saved in the db
        *
        *  @type	filter
        *  @since	3.6
        *  @date	23/01/13
        *
        *  @param	$value (mixed) the value found in the database
        *  @param	$post_id (mixed) the $post_id from which the value was loaded
        *  @param	$field (array) the field array holding all the field options
        *  @return	$value
        */

        /*

        function update_value( $value, $post_id, $field ) {

            return $value;

        }

        */


        /*
        *  format_value()
        *
        *  This filter is applied to the $value after it is loaded from the db and before it is returned to the template
        *
        *  @type	filter
        *  @since	3.6
        *  @date	23/01/13
        *
        *  @param	$value (mixed) the value which was loaded from the database
        *  @param	$post_id (mixed) the $post_id from which the value was loaded
        *  @param	$field (array) the field array holding all the field options
        *
        *  @return	$value (mixed) the modified value
        */

        function format_value( $value, $post_id, $field ) {
            if( !$value ) {
                return $value;
            }

            // Pre 3.3.3, the value is a string coma seperated
            if( is_string($value) ) {
                $value = explode(',', $value);
            }

            // empty?
            if( !is_array($value) || empty($value) ) {
                return $value;
            }

            // convert to integers
            $value = array_map('intval', $value);

            // return format
            if( $field['return_format'] == 'object' ) {
                $return_array = array();
                foreach( $value as $key => $term_id ) {
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
                        if(term_exists($term_id, $field['taxonomy'])) {
                            $return_array[] = get_term($term_id, $field['taxonomy']);
                        }
                    };
                };
                $value = $return_array;
            };

            // return
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
            if( !$value ) {
                return $value;
            }

            // Pre 3.3.3, the value is a string coma seperated
            if( is_string($value) ) {
                $value = explode(',', $value);
            }

            // empty?
            if( !is_array($value) || empty($value) ) {
                return $value;
            }

            // convert to integers
            $value = array_map('intval', $value);

            // return format
            if( $field['return_format'] == 'object' ) {
                $return_array = array();
                foreach( $value as $key => $term_id ) {
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
                        if(term_exists($term_id, $field['taxonomy'])) {
                            $return_array[] = get_term($term_id, $field['taxonomy']);
                        }
                    };
                };
                $value = $return_array;
            };

            // return
            return $value;
        }


        /*
        *  validate_value()
        *
        *  This filter is used to perform validation on the value prior to saving.
        *  All values are validated regardless of the field's required setting. This allows you to validate and return
        *  messages to the user if the value is not correct
        *
        *  @type	filter
        *  @date	11/02/2014
        *  @since	5.0.0
        *
        *  @param	$valid (boolean) validation status based on the value and the field's required setting
        *  @param	$value (mixed) the $_POST value
        *  @param	$field (array) the field array holding all the field options
        *  @param	$input (string) the corresponding input name for $_POST value
        *  @return	$valid
        */

        /*

        function validate_value( $valid, $value, $field, $input ){

            // Basic usage
            if( $value < $field['custom_minimum_setting'] )
            {
                $valid = false;
            }


            // Advanced usage
            if( $value < $field['custom_minimum_setting'] )
            {
                $valid = __('The value is too little!','TEXTDOMAIN'),
            }


            // return
            return $valid;

        }

        */

    }

new door4_acf_field_taxonomy_relationship();

// class_exists check
endif;
