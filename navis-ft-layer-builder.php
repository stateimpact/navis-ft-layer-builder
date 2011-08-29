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

        // new post type is added late so taxonomies have time to register
        add_action( 'init', array( &$this, 'register_post_type' ), 15 );
        add_action( 'add_meta_boxes', array( &$this, 'add_meta_boxes' ));
        add_action( 'save_post', array( &$this, 'save' ));
        
        // scripts & styles
        add_action( 'admin_print_scripts-post.php', 
            array( &$this, 'register_admin_scripts' )
        );
        add_action( 'admin_print_scripts-post-new.php', 
            array( &$this, 'register_admin_scripts' )
        );
        add_action( 'wp_print_scripts',
            array( &$this, 'register_scripts' )
        );
        add_action('wp_print_styles', array(&$this, 'add_map_styles'));
        add_action( 'wp_head',
            array( &$this, 'render_js')
        );
        add_action( 
            'admin_print_styles-post.php', array( &$this, 'add_stylesheet' ) 
        );
        add_action( 
            'admin_print_styles-post-new.php', 
            array( &$this, 'add_stylesheet' ) 
        );
                
        // shortcode
        add_shortcode( 'fusion_map', array( &$this, 'embed_shortcode' ));
        
        // tinymce plugin
        add_action('init', array(&$this, 'register_tinymce_filters'));
        
        add_action( 'admin_menu', array(&$this, 'add_options_page'));
    
        add_action( 'admin_init', array(&$this, 'settings_init'));
        
        $this->map_options_fields = array(
            'map-height', 'map-width', 
            'map-center', 'map-zoom',
            'ft_map_js'
        );
        
    }
    
    function get_defaults() {
        return array(
            'height' => get_option('ft_maps_default_height', 400),
            'width' => get_option('ft_maps_default_width', 620),
            'zoom' => get_option('ft_maps_default_zoom', 4), // the US
            'center' => get_option('ft_maps_default_center', "38.754083,-97.734375")
        );
    }
    
    function add_options_page() {
        add_options_page('Fusion Tables Maps', 'Fusion Tables Maps', 'manage_options',
                        'ft_maps', array(&$this, 'render_options_page'));
    }
    
    function render_options_page() { ?>
        <h2>Fusion Tables Map Options</h2>
        <div id="demo_map" style="border: 1px solid #ddd;"></div>
        <form action="options.php" method="post">
            <?php settings_fields('ft_maps'); ?>
            <?php do_settings_sections('ft_maps'); ?>
            <p>
                <input name="Submit" type="submit" class="button-primary" value="<?php esc_attr_e('Save Changes'); ?>" />
                <input name="Reset" type="reset" class="button" />
            </p>
        </form>
        <script src="http://maps.googleapis.com/maps/api/js?sensor=false"></script>
        <script>
        jQuery(function($) {
            $('#demo_map').css({
                height: <?php echo get_option('ft_maps_default_height', 400); ?>,
                width: <?php echo get_option('ft_maps_default_width', 620); ?>
            });
            
            var center = new google.maps.LatLng(<?php echo get_option('ft_maps_default_center', "38.754083,-97.734375"); ?>);
            window.map = new google.maps.Map(document.getElementById('demo_map'), {
                zoom: <?php echo get_option('ft_maps_default_zoom', 4); ?>,
                center: center,
                mapTypeId: google.maps.MapTypeId.ROADMAP,
                scrollwheel: false
            });
            
            google.maps.event.addListener(map, 'zoom_changed', function() {
                $('input[name=ft_maps_default_zoom]').val(map.getZoom());
            });
        
            google.maps.event.addListener(map, 'center_changed', function() {
                $('input[name=ft_maps_default_center]').val(map.getCenter().toUrlValue());
            });
            
            $(map.getDiv()).resize(function() {
                google.maps.event.trigger(map, 'resize');
            });
            
            $('input[name=ft_maps_default_height]').change(function(e){
                var height = parseInt($(this).val());
                if (height === NaN) return;
                $(map.getDiv()).css({height: height});
            });
            
            $('input[name=ft_maps_default_width]').change(function(e){
                var width = parseInt($(this).val());
                if (width === NaN) return;
                $(map.getDiv()).css({width: width});
            });
            
        })
        </script>
        <?php
    }
    
    function settings_init() {
        add_settings_section( 'ft_maps', '',
            array(&$this, 'settings_section'), 'ft_maps');
        
        add_settings_field('ft_maps_default_height', 'Default Map Height (px)',
            array(&$this, 'default_height_field'), 'ft_maps', 'ft_maps');
        register_setting('ft_maps', 'ft_maps_default_height');
        
        add_settings_field('ft_maps_default_width', 'Default Map Width (px)',
            array(&$this, 'default_width_field'), 'ft_maps', 'ft_maps');
        register_setting('ft_maps', 'ft_maps_default_width');
        
        add_settings_field('ft_maps_full_width', 'Full-width Map Width (px)',
            array(&$this, 'full_width_field'), 'ft_maps', 'ft_maps');
        register_setting('ft_maps', 'ft_maps_full_width');
        
        add_settings_field('ft_maps_default_zoom', 'Default Zoom',
            array(&$this, 'default_zoom_field'), 'ft_maps', 'ft_maps');
        register_setting('ft_maps', 'ft_maps_default_zoom');
        
        add_settings_field('ft_maps_default_center', 'Default Map Center',
            array(&$this, 'default_center_field'), 'ft_maps', 'ft_maps');
        register_setting('ft_maps', 'ft_maps_default_center');
    }
    
    function default_height_field() {
        $option = intval(get_option( 'ft_maps_default_height', 400 ));
        echo "<input type='text' value='$option' name='ft_maps_default_height' />";
    }
    
    function default_width_field() {
        $option = intval(get_option( 'ft_maps_default_width', 620 ));
        echo "<input type='text' value='$option' name='ft_maps_default_width' />";
    }
    
    function full_width_field() {
        $option = intval(get_option( 'ft_maps_full_width', 620 ));
        echo "<input type='text' value='$option' name='ft_maps_full_width' />";
    }
    
    function default_zoom_field() {
        $option = intval(get_option( 'ft_maps_default_zoom', 4 ));
        echo "<input type='text' value='$option' name='ft_maps_default_zoom' />";
    }
    
    function default_center_field() {
        $option = get_option('ft_maps_default_center', "38.754083,-97.734375");
        echo "<input type='text' value='$option' name='ft_maps_default_center' />";
    }
    
    function settings_section() {}
    
    function register_tinymce_filters() {
        add_filter('mce_external_plugins', 
            array(&$this, 'add_tinymce_plugin')
        );
        add_filter('mce_buttons', 
            array(&$this, 'register_button')
        );
    }
    
    function add_tinymce_plugin($plugin_array) {
        $plugin_array['ft_builder'] = plugins_url(
            'js/tinymce/editor-plugin.js', __FILE__);

        return $plugin_array;
    }
    
    function register_button($buttons) {
        array_push($buttons, '|', 'ft_builder');
        return $buttons;
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
            'taxonomies' => apply_filters('fustiontablesmap_taxonomies', array('post_tag', 'category')),
            'has_archive' => true,
            'rewrite' => array(
                'slug' => 'maps'
            )
        ));
    }
    
    function save($post_id) {
        if ( get_post_type($post_id) != 'fusiontablesmap') {
            return;
        }
        
        $changed = false;
        $defaults = $this->get_defaults();
        $options = get_post_meta($post_id, 'ft_map_options', true);
        foreach($defaults as $key => $value) {
            if (isset( $_POST['map'][$key] )) {
                $options[$key] = $_POST['map'][$key];
                $changed = true;
            }
        }
        
        if ($changed) update_post_meta($post_id, 'ft_map_options', $options);
        
        if (isset($_POST['ft_map_js'])) {
            update_post_meta($post_id, 'ft_map_js', $_POST['ft_map_js']);
        }
                
        // deliberately compressing layers here to a numeric array
        // layers without a table id won't be saved
        $layers = array();
        if ( isset($_POST['layers']) ) {
            foreach( $_POST['layers'] as $cid => $layer ) {
                if ( $layer['table_id'] ) $layers[] = $layer;
            }            
            update_post_meta($post_id, 'layers', $layers);
        }
        
        // styles are part of map legends, compressed in the same way as layers
        $styles = array();
        if ( isset($_POST['legend']['styles']) && isset($_POST['legend']['layer_id']) ) {
            foreach( $_POST['legend']['styles'] as $cid => $style ) {
                if ($style['label'] && $style['color']) $styles[] = $style;
            }
            
            update_post_meta($post_id, 'legend_layer', $_POST['legend']['layer_id']);
            update_post_meta($post_id, 'legend_styles', $styles);
        }
        
        // wide assets
        if ($changed) {
            $wide_assets = get_post_meta($post_id, 'wide_assets', true);
            if (intval( $options['width'] ) > $defaults['width']) {
                $wide_assets['ft_map'] = true;
            } else {
                $wide_assets['ft_map'] = false;
            }
            update_post_meta($post_id, 'wide_assets', $wide_assets);
        }
    }
    
    function embed_shortcode($atts, $content, $code) {
        return '<div id="map_canvas"></div>';
    }
    
    function add_meta_boxes() {
        add_meta_box( 'ft-builder', 'Layer Builder', 
        array( &$this, 'render_meta_box'),
        'fusiontablesmap', 'normal', 'high');
        
        add_meta_box( 'js_code', 'Map JavaScript',
        array( &$this, 'js_meta_box' ),
        'fusiontablesmap', 'normal', 'high');
    }
    
    function js_meta_box($post) { ?>
        <textarea name="ft_map_js" style="width:100%;" rows="10" readonly="readonly"></textarea>
        <?php
    }
    
    function render_meta_box($post) { 
        /***
        $height = get_post_meta($post->ID, 'map-height', true);
        $width = get_post_meta($post->ID, 'map-width', true);
        $center = get_post_meta($post->ID, 'map-center', true);
        $zoom = get_post_meta($post->ID, 'map-zoom', true);
        ***/
        $options = get_post_meta($post->ID, 'ft_map_options', true);
        $layers = get_post_meta($post->ID, 'layers', true);
        $styles = get_post_meta($post->ID, 'legend_styles', true);
        $legend_layer = get_post_meta($post->ID, 'legend_layer', true);
        ?>
        <div id="map-wrapper">
            <div id="map_canvas"></div>
        </div>
        <div id="layers-wrap" class="alignleft">
            <div id="layers">
                <h2>Add a layer</h2>
            </div>
            <p>
                <input type="button" class="new-layer button" value="Add another layer">
                <input type="button" class="update-map button-primary" value="Update Map" />
            </p>
        </div>
        <div id="options-wrap" class="alignleft">
            <div id="map-options">
                <h2>Edit Map</h2>
                <p>
                    <label for="dimensions">Dimensions</label>
                </p>
                <p>Height: <input type="text" id="map-height" name="map[height]" /></p>
                <p>Width: 
                    <select id="map-width" name="map[width]">
                        <option value="<?php echo get_option('ft_maps_default_width', 620); ?>">Normal</option>
                        <option value="<?php echo get_option('ft_maps_full_width', 940); ?>">Wide</option>
                    </select>
                </p>
                <p>
                    <label for="map[center]">Map center</label>
                    <input type="text" id="map-center" name="map[center]">
                </p>
                <p>
                    <label for="map[zoom]">Zoom</label>
                    <input type="text" id="map-zoom" name="map[zoom]">
                </p>
                <p class="howto">Map center and zoom will update automatically when the map changes</p>
            </div>
            <div id="legend">
                <h3>Legend</h3>
                <p class="howto">Optional: Define map colors that will be represented in a legend</p>
                <div>
                    <label for="layer-choices">Choose a layer</label>
                    <select id="layer-choices" name="legend[layer_id]">
                        <option>Choose a layer</option>
                    </select>
                    <p class="howto">All styles will be applied to this layer</p>
                </div>
                <!--
                <div>
                    <label for="column-choices">Choose a column</label>
                    <select id="column-choices"></select>
                </div>
                -->
                <div id="styles"></div>
                <p><input type="button" class="add button" value="Add Style" /></p>
            </div>
        </div>

        <script type="x-javascript-template" id="layer-template">
        <div class="table_label">
            <p>
                <label for="layers[<%= cid %>][label]">Label</label>
                <input type="text" class="label" name="layers[<%= cid %>][label]" value="<%= label %>" />
            </p>
            <p class="howto">Optional: Give this layer a name</p>
        </div>
        
        <div class="table_id">
            <p>
                <label for="layers[<%= cid %>][table_id]">Table ID:</label>
                <input type="text" class="table_id" name="layers[<%= cid %>][table_id]" value="<%= table_id %>" />
            </p>
            <p class="howto">Paste in a <strong>Table ID</strong> or <strong>URL</strong> from Google Fusion Tables</p>
        </div>
        <div class="geometry_column">
            <p>
                <label for="layers[<%= cid %>][geometry_column]">Location column</label>
                    <select disabled="disabled" class="geometry_column" name="layers[<%= cid %>][geometry_column]">
                        <option value=""> --- select --- </option>
                    </select>
            </p>
        </div>
        <div class="where">
            <p>
                <label for="layers[<%= cid %>][where]">Filter (WHERE)</label>
                <input type="text" class="where" name="layers[<%= cid %>][filter]" value="<%= filter %>" />
            </p>
        </div>
        <p><a href="#" class="delete">X</a></p>
        </script>
        
        <script type="x-javascript-template" id="style-template">
        <div class="layer-where">
            <p>
                <label for="legend[styles][<%= cid %>][filter]">Filter (WHERE)</label>
                <input type="text" class="where" name="legend[styles][<%= cid %>][filter]" value="<%= filter %>"/>
            </p>
        </div>
        <div class="layer-label">
            <p>
                <label for="legend[styles][<%= cid %>][label]">Label</label>
                <input type="text" class="label" name="legend[styles][<%= cid %>][label]" value="<%= label %>"/>
            </p>
        </div>
        <div class="layer-color">
            <p>
                <label for="legend[styles][<%= cid %>][color]">Fill Color</label>
                <select class="color" name="legend[styles][<%= cid %>][color]" value="<%= color %>">
                    <option>Colors</option>
                </select>
            </p>
        </script>

        <script type="x-javascript-template" id="map-embed-template">
        jQuery(function($) {
            
            $('#map_canvas').css({
                height: "<%= options.height %>",
                width: "<%= options.width %>"
            });

            window.ft_map = new google.maps.Map(document.getElementById('map_canvas'), {
                center: new google.maps.LatLng(<%= options.center %>),
                zoom: <%= options.zoom %>,
                mapTypeId: google.maps.MapTypeId.ROADMAP,
                scrollwheel: false
            });

            <% for (var i in layers) { %>
                new google.maps.FusionTablesLayer({
                    query: {
                        select: "<%= layers[i].get('geometry_column') %>",
                        from: "<%= layers[i].get('table_id') %>",
                        where: "<%= layers[i].get('filter') %>"
                    }, <% if (layers[i].isStyled()) { %>
                    styles: [{ <% for (var i in styles) { %>
                        <% if (styles[i].get('filter')) { %>
                        where: "<%= styles[i].get('filter') %>",
                        <% } %>
                        polygonOptions: {
                          fillColor: "#<%= styles[i].get('color') %>"
                        }
                        <% } %>
                    }],
                    <% } %>
                    map: ft_map
                });
            <% } %>
            <% if (styles.length) { %>
                var row, color;
                window.leg = leg = $('<div/>')
                    .addClass('legend');
                <% for (var i in styles) { %>
                    color = $('<div/>')
                        .addClass('color')
                        .css({
                            width: '20px',
                            height: '20px',
                            'background-color': "#<%= styles[i].get('color') %>"
                        });
                    row = $('<p/>')
                        .addClass('row')
                        .text("<%= styles[i].get('label') %>")
                        .prepend(color)
                    leg.append(row);
                <% } %>
                
                leg = leg[0];
                leg.index = 1;
                ft_map.controls[google.maps.ControlPosition.RIGHT_BOTTOM].push(leg);
            <% } %>
        });
        </script>
        
        <script>
        // bootstrap
        jQuery(function($) {
            
            window.ft_builder = new AppView(<?php echo json_encode($options); ?>);
            
            window.layers.add(<?php echo json_encode($layers); ?>);
            if (!window.layers.length) {ft_builder.createLayer();}
            
            window.legend.collection.add(<?php echo json_encode($styles); ?>);
            window.legend.model.set({ layer_id: "<?php echo $legend_layer; ?>"});
            
            _.defer(ft_builder.render_map);
            
            function setMinWidth() {
                var width = Number($('select#map-width').val()) + 20;
                if (width > $('#normal-sortables').width()) {
                    $('#ft-builder').css({width: width});
                    $('#wpwrap').css({width: width + 200});
                } else {
                    $('#ft-builder').css({width: ''});
                    $('#wpwrap').css({width: ''});
                }
            };
            
            $('select#map-width').change(setMinWidth);
            setMinWidth();
        });
        </script>
        <?php
    }
    
    function add_map_styles() {}
    
    function add_stylesheet() {
        $css = array(
            'style' => plugins_url( 'css/style.css', __FILE__ ),
            'color_picker' => plugins_url( 'css/colorpicker.css', __FILE__ ),
        );
        wp_enqueue_style( 
            'navis-ft-layerbuilder-styles', $css['style'], array(), '1.0'
        );
        
        wp_enqueue_style('color_picker', $css['color_picker']);
    }
    
    function register_admin_scripts() {
        if (get_post_type() != 'fusiontablesmap') return;
        
        $jslibs = array(
            'gmaps' => 'http://maps.googleapis.com/maps/api/js?sensor=false',
            'underscore' => plugins_url('js/underscore-min.js', __FILE__),
            'backbone' => plugins_url('js/backbone-min.js', __FILE__),
            'builder' => plugins_url('js/ft-builder.js', __FILE__),
            'jsonp' => plugins_url('js/jquery.jsonp-2.1.4.min.js', __FILE__),
            'color_picker' => plugins_url('js/colorpicker.js', __FILE__),
        );
        
        wp_enqueue_script( 'jsonp', $jslibs['jsonp']);
        wp_enqueue_script( 'gmaps', $jslibs['gmaps']);
        wp_enqueue_script( 'color_picker', $jslibs['color_picker']);
        wp_enqueue_script( 'underscore', $jslibs['underscore']);
        wp_enqueue_script( 'backbone', $jslibs['backbone'],
            array('underscore', 'jquery'));
        wp_enqueue_script( 'ft-builder', $jslibs['builder'],
            array('gmaps', 'jquery', 'underscore', 'backbone', 'color_picker'),
            "0.2");
    }
    
    function register_scripts() {        
        if (get_post_type() != 'fusiontablesmap') return;
        
        wp_enqueue_script( 'gmaps',
            'http://maps.googleapis.com/maps/api/js?sensor=false',
            array('jquery'));    
    }
    
    function render_js() {
        global $post;
        if (is_single()) {
            $js = get_post_meta($post->ID, 'ft_map_js', true );
            if ($js) echo "<script>$js</script>\n";
        }
    }
}

new Navis_Layer_Builder;

?>
