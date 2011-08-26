jQuery(function($) {
    /***
    Fusion Table Builder
    --------------------

    This is a port of Google's [Fusion Table Layer Builder](http://gmaps-samples.googlecode.com/svn/trunk/fusiontables/fusiontableslayer_builder.html), using jQuery, Backbone and Underscore, with the aim of integrating it into the WordPress admin.
    ***/

    // a query utility

    function query(sql, callback, errback) {
        var base = "https://www.google.com/fusiontables/api/query?";
        var url = base + $.param({sql: sql});
        $.jsonp({
            url: url,
            //dataType: 'jsonp',
            callback: 'jqjsp',
            callbackParameter: 'jsonCallback',
            success: callback,
            error: errback,
        });
    };
    window.query = query;
    
    var COLORS = {
        red:    [ "ECA496", "E27560", "D9482B", "A33622", "6D2516" ],
        green:  [ "8BC0BF", "51A09E", "18817E", "14615F", "0D4140" ],
        blue:   [ "A8D5EF", "7FBFE5", "52AADD", "3F7FA6", "295670" ],
        yellow: [ "F7E39A", "F3D56A", "EFC635", "B39430", "776428" ],
        orange: [ "F1C696", "EAAB61", "E28D2B", "AA6B28", "71481C" ]
    }
    /***
    Models
    ***/

    /***
    **FTLayer**: 

    Stores basic data about a Fusion Table:

     - table_id
     - geometry_column
     - filter

    ***/

    var FTLayer = Backbone.Model.extend({
        
        initialize: function(attributes) {
            this.bind('change:table_id', function() {
                this.set({id: this.get('table_id')});
            }, this);
            
            this.option_view = new LayerOptionView({ model: this });
        },
    
        defaults: {
            table_id: null,
            geometry_column: null,
            filter: null,
            label: "",
        }
    });

    // a singleton to hold map options
    var MapOptions = Backbone.Model.extend({
    
        defaults: {
            height: 400,
            width: 620,
            zoom: 4, // the US
            center: "38.754083,-97.734375"
        },
    
        fieldnames: ['height', 'width', 'center', 'zoom'],
        
        validate: function(attrs) {
            var intfields = ['height', 'width', 'zoom'];
            for (var i in intfields) {
                var field = intfields[i];
                var value = attrs[field];
                if (value && !_.isNumber(Number(value))) {
                    return field;
                };
            };
        }
    
    });
    
    // a singleton to hold legend metadata and options
    var Legend = Backbone.Model.extend({
        
        defaults: {
            layer_id: null,
            buckets: 5
        },
        
        initialize: function(attributes) {
            _.bindAll(this);
            this.bind('change:layer_id', this.setLayer);
            
            if (window.layers.length === 1) {
                this.set({ layer_id: window.layers.first().get('id')});
            }
        },
        
        setLayer: function() {
            var layer_id = this.get('layer_id');
            if (!layer_id) return;
            
            this.layer = window.layers.get(layer_id);
            return this.layer;
        }
    });
    
    // LayerStyle wraps the FusionTablesLayerOptions styles parameter and
    // is used to build a legend. For now, it only applies to polygons.
    var LayerStyle = Backbone.Model.extend({
        
        defaults: {
            filter: null,
            color: null,
            label: null
        },
    });

    /***
    Collections

    **LayerCollection**:
    ***/

    var LayerCollection = Backbone.Collection.extend({
    
        model: FTLayer,
    
        complete: function() {
            return this.filter(function(layer) {
                return (layer.has('table_id') && layer.has('geometry_column'));
            });
        }
    });

    window.layers = new LayerCollection;
    
    var StyleCollection = Backbone.Collection.extend({
        model: LayerStyle
    })

    /***
    Views
    ***/

    var LayerView = Backbone.View.extend({
    
        className: "layer",
    
        events: {
            'change input.table_id'         : 'getColumns',
            'change select.geometry_column' : 'setGeoColumn',
            'change input.where'            : 'setFilter',
            'change input.layer'            : 'setLabel',
            'click a.delete'                : 'remove'
        },
    
        template: _.template( $('#layer-template').html() ),
    
        initialize: function(options) {
            _.bindAll(this);
        
            this.model.view = this;
            this.render();
            return this;
        },
    
        remove: function(e) {
            e.preventDefault();
            $(this.el).remove();
            return this;
        },
    
        render: function() {
            var data = _.extend(this.model.toJSON(), {cid: this.model.cid});
            $(this.el).html(this.template(data));
            if (this.model.has('table_id')) {
                var that = this;
                this.getColumns(function() {
                    if (that.model.has('geometry_column')) {
                        that.$('select.geometry_column')
                            .val(that.model.get('geometry_column'));
                    };
                });
            }
            return this;
        },
    
        getColumns: function(callback) {
            var that = this;
            var table_id = this.$('input.table_id').val();
            if (!table_id) return;
        
            var sql = "SELECT * FROM " + table_id;
            query(sql, function(resp) {
                that.model.table = resp.table;
                var columns = resp.table.cols;
                var select = that.$('select.geometry_column');
                select.empty();
                for (var key in columns) {
                    var column = columns[key];
                    var option = $('<option/>')
                        .attr('value', column)
                        .text(column);
                    select.append(option);
                }
                that.model.set({
                    id       : table_id,
                    table_id : table_id,
                    columns  : columns
                });
                if (that.$('div.table_id').is('.error')) {
                    that.$('div.table_id').removeClass('error').find('p.howto')
                    .text('Paste in a Table ID from Google Fusion Tables');
                };
                if (_.isFunction(callback)) callback();
            },
            // errback
            function() {
                var div = that.$('div.table_id');
                div.addClass('error');
                div.find('p.howto').text("Something went wrong. Check your Table ID and make sure your map is public, then try again.");
            });
            return this;
        },
    
        setGeoColumn: function(e) {
            var column = this.$('select.geometry_column').val();
            if (column) this.model.set({geometry_column: column});
        
            // return this regardless
            return this;
        },
    
        setFilter: function(e) {
            var where = this.$('input.where').val();
            if (where) this.model.set({filter: where});
            return this;
        },
        
        setLabel: function(e) {
            var label = this.$('input.label').val();
            if (label) this.model.set({label: label});
            return this;
        }
    
    });
    
    // a simple view to keep layer options up to date
    var LayerOptionView = Backbone.View.extend({
        
        tagName: 'option',
        
        initialize: function(options) {
            _.bindAll(this);
            this.model.bind('change', this.render)
        },
        
        render: function() {
            var label = this.model.get('label');
            if (!label) label = this.model.get('table_id');
            return $(this.el)
                .attr('value', this.model.get('table_id'))
                .text(label);
        }
    });

    window.AppView = Backbone.View.extend({
    
        el: $('#ft-builder'),
    
        events: {
            'click input.new-layer'   : 'createLayer',
            'click input.update-map'  : 'render_map'
        },
    
        jsTemplate: _.template( $('#map-embed-template').html() ),
    
        initialize: function(options) {
            _.bindAll(this);
            layers.bind('add', this.addLayer);
            
            this.options = new MapOptions(options);
            this.options.bind('change', this.render_map)
            
            var that = this;
            $('form').submit(function(e) {
                that.render_map();
            });
            
            this.options.bind('error', function(model, error) {
                // error is the name of the field failing to validate
                that.$('#map-' + error).parent().addClass('error');
            });
            
            _.each(this.options.fieldnames, function(field) {
                that.options.bind('change:' + field, function(model, value) {
                    that.$('#map-' + field).parent().removeClass('error');
                });
            })
            
            this.render();
            return this;
        },
    
        mapEvents: function() {
            var map = window.ft_map;
            var that = this;
        
            google.maps.event.addListener(map, 'zoom_changed', function() {
                that.$('#map-zoom').val(map.getZoom());
            });
        
            google.maps.event.addListener(map, 'center_changed', function() {
                that.$('#map-center').val(map.getCenter().toUrlValue());
            });
            
            that.$(map.getDiv()).resize(function() {
                google.maps.event.trigger(map, 'resize');
            });
            return this;
        },
        
        // hook for when layers are added to a the layers collection
        // by user click or refresh
        addLayer: function(layer) {
            var view = new LayerView({ model: layer });
            this.$('#layers').append(view.el);
            return this;
        },
        
        // method to create a layer when a user clicks the Add Layer button
        createLayer: function(e) {
            var layer = new FTLayer;
            layers.add(layer);
            return layer;
        },
        
        render: function() {
            for (var index in this.options.fieldnames) {
                var field = this.options.fieldnames[index];
                var value = this.options.get(field);
                $('#map-' + field).val(value);
            };
            
            return this;
        },
    
        render_map: function() {
            this.updateOptions();
            $('#map_embed').remove();
            var script = $('<script/>')
                .attr('id', '#map-embed')
                .html( this.jsTemplate({
                    options: this.options.toJSON(),
                    layers: layers.complete()
                }));
            $('body').append(script);
            $('#js_code textarea').html( script.html() );
            
            // map events need to be reset since we killed the old map
            this.mapEvents();
            return this;
        },
    
        updateOptions: function(options) {
            var changes = {};
            for (var index in this.options.fieldnames) {
                var field = this.options.fieldnames[index];
                var value = $('#map-' + field).val();
                changes[field] = value;
            };
            this.options.set(changes);
            return this;
        }
    
    });
    
    // UI for each LayerStyle
    var StyleView = Backbone.View.extend({
        
        className: "layer-style",
        
        template: _.template( $('#style-template').html() ),
        
        initialize: function(options) {
            _.bindAll(this);
            this.model.view = this;
            this.render();
            this.colorpicker();
            return this;
        },
                
        colorpicker: function() {
            that = this;
            this.$('input.color').ColorPicker({
                color: '#0000ff',
            	onShow: function(colpkr) {
            		$(colpkr).fadeIn(500);
            		return false;
            	},
            	onHide: function(colpkr) {
            		$(colpkr).fadeOut(500);
            		return false;
            	},
            	onChange: function(hsb, hex, rgb) {
            		that.$('input.color').css('backgroundColor', '#' + hex);
            		that.$('input.color').val('#' + hex);
            	}
            });
            return this;
        },
        
        render: function() {
            var data = _.extend(this.model.toJSON(), {cid: this.model.cid});
            $(this.el).html(this.template(data));
            return this;
        }
    });
    
    var LegendAdmin = Backbone.View.extend({
        
        el: '#legend',
        
        events: {
            'click input.add'       : 'createStyle'
        },
        
        initialize: function(options) {
            _.bindAll(this);
            
            if (_.isUndefined(this.collection)) {
                this.collection = new StyleCollection;
            };
            
            if (_.isUndefined(this.model)) {
                this.model = model = new Legend;
            };
            
            this.collection.bind('add', this.addStyle);
            window.layers.bind('add', this.addLayerOption);
            //window.layers.bind('remove', this.setLayerChoices);
            
            $('#layer-choices').change(function() {
                model.set({ layer_id: $(this).val()});
            });
            return this;
        },
        
        addStyle: function(style) {
            var view = new StyleView({ model: style });
            this.$('#styles').append(view.el);
            return this;
        },
        
        createStyle: function(e) {
            var style = new LayerStyle;
            this.collection.add(style);
            return style;
        },
        
        addLayerOption: function(layer) {
            var select = $('#layer-choices');
            var option = layer.option_view;
            select.append(option.render());
        },
        
        removeLayerOption: function(layer) {
            layer.option_view.remove();
        }
                
    });
    
    window.legend = new LegendAdmin;
    
});