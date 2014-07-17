<?php

/*
Plugin Name: Advanced Custom Fields: Taxonomy Relationship
Plugin URI: https://github.com/DanBeckett/acf-taxonomy-relationship
Description: Extends Advanced Custom Fields to allow you to select and order Taxonomy Terms in the same way the standard Relationship field allows with Posts.
Version: 0.8.2
Author: Dan Beckett
Author URI: http://www.door4.com
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
*/

/*  Copyright 2014 Door4 Ltd

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA

*/


// 1. set text domain
// Reference: https://codex.wordpress.org/Function_Reference/load_plugin_textdomain
load_plugin_textdomain( 'acf-taxonomy-relationship', false, dirname( plugin_basename(__FILE__) ) . '/lang/' ); 




// 2. Include field type for ACF5
// $version = 5 and can be ignored until ACF6 exists
function include_field_types_taxonomy_relationship( $version ) {

	include_once('acf-taxonomy-relationship-v5.php');

}

add_action('acf/include_field_types', 'include_field_types_taxonomy_relationship');	




// 3. Include field type for ACF4
function register_fields_taxonomy_relationship() {

	include_once('acf-taxonomy-relationship-v4.php');

}

add_action('acf/register_fields', 'register_fields_taxonomy_relationship');	


?>
