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
        
        // scripts & styles
        add_action( 'admin_print_scripts-post.php', 
            array( &$this, 'register_admin_scripts' )
        );
        add_action( 'admin_print_scripts-post-new.php', 
            array( &$this, 'register_admin_scripts' )
        );
        add_action( 
            'admin_print_styles-post.php', array( &$this, 'add_stylesheet' ) 
        );
        add_action( 
            'admin_print_styles-post-new.php', 
            array( &$this, 'add_stylesheet' ) 
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
    
    function render_meta_box($post) { ?>
        <div id="map-wrapper">
            <div id="map_canvas"></div>
            <div id="js_code">
                <textarea style="width:100%;" rows="10"></textarea>
            </div>
        </div>
        <div id="layers-wrap" class="alignleft">
            <div id="layers">
                <h2>Add a layer</h2>
            </div>
            <p>
                <input type="button" class="update-map" value="Update Map" />
                <input type="button" class="new-layer" value="Add another layer">
            </p>
        </div>
        <div id="options-wrap" class="alignright">
            <div id="map-options">
                <h2>Edit Map</h2>
                <p>
                    <label for="dimensions">Dimensions (px)</label>
                </p>
                <p>Width: <input type="text" id="map-width" name="map-width" /></p>
                <p>Height: <input type="text" id="map-height" name="map-height" /></p>
                <p>
                    <label for="map-center">Map center</label>
                    <input type="text" id="map-center" name="map-center">
                </p>
                <p>
                    <label for="map-zoom">Zoom</label>
                    <input type="text" id="map-zoom" name="map-zoom">
                </p>
                <input type="button" class="update-map" value="Update Map" />
            </div>
        </div>
        
        
        
        <script type="x-javascript-template" id="layer-template">
        <div>
            <label for="table_id-<%= cid %>">Table ID:</label>
            <input type="text" class="table_id" name="table_id-<%= cid %>" value="<%= table_id %>" />
        </div>
        <div>
            <label for="geometry_column-<%= cid %>">Location column</label>
                <select class="geometry_column" name="geometry_column-<%= cid %>">
                    <option> --- select --- </option>
                </select>
        </div>
        <div>
            <label for="where-<%= cid %>">Filter (WHERE)</label>
            <input type="text" class="where" name="where-<%= cid %>" value="<%= filter %>" />
        </div>
        <p><a href="#" class="delete">X</a></p>
        <input type="hidden" name="layer-id" value="<%= cid %>" />
        </script>

        <script type="x-javacript-template" id="map-embed-template">
        jQuery('#map_canvas').css({
            height: "<%= options.height %>",
            width: "<%= options.width %>"
        });

        window.ft_map = new google.maps.Map(document.getElementById('map_canvas'), {
            center: new google.maps.LatLng(<%= options.center %>),
            zoom: <%= options.zoom %>,
            mapTypeId: google.maps.MapTypeId.ROADMAP
        });

        <% for (var i in layers) { %>
            var newlayer = new google.maps.FusionTablesLayer({
                query: {
                    select: "<%= layers[i].get('geometry_column') %>",
                    from: "<%= layers[i].get('table_id') %>",
                    where: "<%= layers[i].get('filter') %>"
                }
            });

            newlayer.setMap(ft_map);
        <% } %>
        </script>

        <script id="map_embed">
        var center = new google.maps.LatLng(41.09577724339462, -77.57904618437499);
        window.ft_map = new google.maps.Map(document.getElementById('map_canvas'), {
            center: center,
            zoom: 7,
            mapTypeId: google.maps.MapTypeId.ROADMAP
        });
        /***
        var layer = new google.maps.FusionTablesLayer({
            query: {
                select: 'geometry',
                from: '1174691',
                where: ''
            }
        });
        layer.setMap(map);
        ***/
        </script>
        
        <?php
    }
    
    function add_stylesheet() {
        $style_css = plugins_url( 'css/style.css', __FILE__ );
        wp_enqueue_style( 
            'navis-ft-layerbuilder-styles', $style_css, array(), '1.0'
        );
    }
    
    function register_admin_scripts() {
        $jslibs = array(
            'gmaps' => 'http://maps.googleapis.com/maps/api/js?sensor=false',
            'underscore' => plugins_url('js/underscore-min.js', __FILE__),
            'backbone' => plugins_url('js/backbone-min.js', __FILE__),
            'builder' => plugins_url('js/ft-builder.js', __FILE__)
        );
        
        wp_enqueue_script( 'gmaps', $jslibs['gmaps']);
        wp_enqueue_script( 'underscore', $jslibs['underscore']);
        wp_enqueue_script( 'backbone', $jslibs['backbone'],
            array('underscore', 'jquery'));
        wp_enqueue_script( 'ft-builder', $jslibs['builder'],
            array('gmaps', 'jquery', 'underscore', 'backbone'));
    }
}

new Navis_Layer_Builder;

?>