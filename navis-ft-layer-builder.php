<?php
/***
 * Plugin Name: Navis Fusion Table Layer Builder
 * Description: Build multi-layered maps with Google Fusion Tables in WordPress
 * Version: 0.1
 * Author: Chris Amico
 * License: GPLv2
***/
/*
    Copyright 2011 National Public Radio, Inc. 

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as 
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

class Navis_Layer_Builder {
    
    function  __construct() {
        // post type
        // meta box
        // save hook
        // shortcode
        // render
        
        add_action( 'init', array( &$this, 'register_post_type' ) );
        add_action( 'add_meta_boxes', array( &$this, 'add_meta_boxes' ));
        //add_action( 'save_post', array( &$this, 'save' ));
        
        // scripts
        add_action( 'admin_print_scripts-post.php', 
            array( &$this, 'register_admin_scripts' )
        );
        add_action( 'admin_print_scripts-post-new.php', 
            array( &$this, 'register_admin_scripts' )
        );
        
        // shortcode
        // add_shortcode( 'navis_fusion_map', array( &$this, 'embed_shortcode' ));
        
    }
    
    function register_post_type() {
        register_post_type('Fusion Tables Map', array(
            'labels' => array(
                'name' => 'Fusion Tables Maps',
                'singular_name' => 'Fusion Tables Map',
                'add_new' => 'Add new map',
                'add_new_item' => 'Add new map',
                'edit_tem' => 'Edit map',
                'new_item' => 'New map',
                'view_item' => 'View map',
                'search_items' => 'Search maps',
                'not_found' => 'No maps found',
                'not_found_in_trash' => 'No maps found in trash'
            ),
            'public' => true,
            'supports' => array(
                'title', 'editor', 'excerpt', 'thumbnail',
                'author', 'comments', 'revisions'
            ),
            'taxonomies' => array('post_tag'),
            'has_archive' => true,
            'rewrite' => array(
                'slug' => 'maps'
            )
        ));
    }
    
    function add_meta_boxes() {
        add_meta_box( 'ft-builder', 'Layer Builder', 
        array( &$this, 'render_meta_box'),
        'fusiontablesmap', 'normal', 'high');
    }
    
    function render_meta_box($post) {
        echo "Meta!";
    }
    
    function register_admin_scripts() {
        $jslibs = array(
            'underscore' => plugins_url('js/underscore-min.js', __FILE__),
            'backbone' => plugins_url('js/backbone-min.js', __FILE__),
            'builder' => plugins_url('js/ft-builder.js', __FILE__)
        );
        
        wp_enqueue_script( 'underscore', $jslibs['underscore']);
        wp_enqueue_script( 'backbone', $jslibs['backbone'],
            array('underscore', 'jquery'));
        wp_enqueue_script( 'ft-builder', $jslibs['builder'],
            array('jquery', 'underscore', 'backbone'));
    }
}

new Navis_Layer_Builder;

?>