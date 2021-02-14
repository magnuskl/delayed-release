<?php
/*
Plugin Name: Delayed Release
Plugin URI: http://github.com/magnuskl/delayed-release
Description: This plugin provides a delayed-release feature for member content.
Version: 1.0.0
Author: Magnus Klausen
Author URI: https://github.com/magnuskl
Licence: GPLv2 or later
Text Domain: delayed-release
*/

/*
Copyright (C) 2021 Magnus Klausen
This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
*/

defined( 'ABSPATH' ) or die();

class DelayedRelease
{
    public $delays = array(
        '5 minutes (for testing)' => 300,
        '12 hours'                => 43200,
        '24 hours'                => 86400,
        '3 days'                  => 259200,
        '1 week'                  => 604800,
        'Never'                   => -1,
    );

    static function activate() {
        flush_rewrite_rules();
    }

    static function deactivate() {
        flush_rewrite_rules();
    }

    static function uninstall() {
        unregister_setting( 'delayed_release', 'delayed_release_category' );
        unregister_setting( 'delayed_release', 'delayed_release_delay' );
        delete_option('delayed_release_delay');
        delete_option('delayed_release_category');
    }
    
    function __construct() {
        add_action(
            'transition_post_status',
            array( $this, 'transition_post_status_callback' ),
            10,
            3
        );
        add_action(
            'delayed_release',
            array( $this, 'delayed_release_callback' ),
            10,
            2
        );
        add_action(
            'admin_init',
            array( $this, 'admin_init_callback' )
        );
        add_action(
            'admin_menu',
            array( $this, 'admin_menu_callback')
        );
        add_filter( 
            'plugin_action_links_' . plugin_basename( __FILE__ ),
            array( $this, 'plugin_action_links_callback' )
        );
    }
    
    function transition_post_status_callback ( $new_status,$old_status,$post ) {
        $category = intval( get_option( 'delayed_release_category' ) );
        $delay    = intval( get_option( 'delayed_release_delay' ) );
        $post_categories = wp_get_post_categories( $post->ID );
        if (
            empty( $category )        ||
            empty( $delay )           ||
            $delay      === -1        ||
            $new_status !== 'publish'
        ) return;
        foreach ( $post_categories as $post_category ) {
            if ( $post_category !== $category ) continue;
            $timestamp = strtotime( $post->post_date_gmt ) + $delay;
            as_schedule_single_action(
                $timestamp,
                'delayed_release',
                array( $post->ID, $category ),
                'delayed_release'
            );
        }
    }
    
    function delayed_release_callback( $post_id, $category ) {
        wp_remove_object_terms( $post_id, $category, 'category' );
    }

    function admin_init_callback() {
        register_setting(
            'delayed_release',
            'delayed_release_category',
            array(
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default'           => NULL,
            )
        );
        register_setting(
            'delayed_release',
            'delayed_release_delay',
            array(
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default'           => NULL,
            )
        );

        add_settings_section( 'default', '', function () {}, 'delayed_release');

        add_settings_field(
            'delayed_release_category',
            'Category',
            array( $this, 'delayed_release_category_callback' ),
            'delayed_release'
        );
        
        add_settings_field(
            'delayed_release_delay',
            'Delay',
            array( $this, 'delayed_release_delay_callback' ),
            'delayed_release'
        );
    }
    
    function admin_menu_callback() {
        add_options_page(
            'Delayed Release Settings',
            'Delayed Release',
            'manage_options',
            'delayed_release',
            function () {
                require_once plugin_dir_path( __FILE__ ) . 'options.php';
            }
        );
    }

    function plugin_action_links_callback ( $links ) {
        $link = "<a href=\"options-general.php?page=delayed_release\">".
            'Settings</a>';
        array_push( $links, $link );
        return $links;
    }

    function delayed_release_category_callback() {
        $categories = get_terms(
            array(
                'taxonomy'   => 'category',
                'hide_empty' => false,
            )
        );
        printf( '<select name="delayed_release_category">' );
        foreach ( $categories as $cat ) {
            printf(
                '<option value="%s" %s>',
                $cat->term_id,
                selected(
                    $cat->term_id,
                    get_option( 'delayed_release_category' ),
                    false
                )
            );
            printf( '%s', $cat->name );
            printf( '</option>' );
        }
        printf('</select>');
    }
    
    function delayed_release_delay_callback() {
        foreach ( $this->delays as $description => $seconds ) {
            printf(
                '<input
                    type="radio"
                    name="delayed_release_delay"
                    value="%s"
                    %s
                />&nbsp;%s<br/>',
                esc_attr( $seconds ),
                checked(
                    $seconds,
                    get_option( 'delayed_release_delay' ),
                    false
                ),
                esc_attr( $description )
            );
        }
    }

    function write_log( $message ) {
        if ( WP_DEBUG === true ) {
            if ( is_array( $message ) || is_object( $message ) ) {
                error_log( print_r( $message, true ) );
            } else {
                error_log( $message );
            }
        }
    }
}

if ( class_exists( 'DelayedRelease' ) ) {
    $delayedRelease = new DelayedRelease();
}

register_activation_hook( __FILE__, 'DelayedRelease::activate' );
register_deactivation_hook( __FILE__, 'DelayedRelease::deactivate' );
register_uninstall_hook( __FILE__, 'DelayedRelease::uninstall' );
