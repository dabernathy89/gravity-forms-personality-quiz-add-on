<?php
/*
Plugin Name: Gravity Forms Personality Quiz Add-On
Description: Create personality quizzes with Gravity Forms.
Version: 1.0.0
Author: Daniel Abernathy
Author URI: http://www.danielabernathy.com
License: GPLv3

    Copyright 2014 Daniel Abernathy (email : dabernathy89@gmail.com)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 3, as
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/


add_action( 'gform_loaded', 'gf_pq_register_addon', 5 );
function gf_pq_register_addon() {
    if ( ! method_exists( 'GFForms', 'include_addon_framework' ) ) {
        return;
    }
    GFForms::include_addon_framework();
    require 'class-gravity-forms-personality-quiz-addon.php';
    GFAddOn::register( 'GravityFormsPersonalityQuizAddon' );
}
