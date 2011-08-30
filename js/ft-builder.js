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
    
        defaults: {
            table_id: null,
            geometry_column: null,
            filter: null,
            label: "",
        }
    })

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
    
    var LegendRow = Backbone.Model.extend({
        
        defaults: {
            color: null,
            label: null
        }
    })

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
    
    var LegendRowCollection = Backbone.Collection.extend({
        model: LegendRow,
        
        complete: function() {
            return this.filter(function(row) {
                return (row.has('label') && row.has('color'));
            });
        }
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
        
        tableIdError: function() {
            var div = this.$('div.table_id');
            div.addClass('error');
            div.find('p.howto').text("Something went wrong. Check your Table ID and make sure your map is public, then try again.");
            return;
        },
        
        getColumnsFromUrl: function(url, callback) {
            var query = url.split('?')[1];
            if (!query) return this.tableIdError();
            var params = query.split('&');
            for (var i in params) {
                var param = params[i].split('=');
                if (param[0] === 'dsrcid') {
                    this.$('input.table_id').val(param[1]);
                    
                    this.getColumns(callback);
                    return this;
                }
            }
        },
    
        getColumns: function(callback) {
            var that = this;
            var table_id = this.$('input.table_id').val();
            if (!table_id) return;
            
            if (table_id.match(/https?:\/\/www\.google\.com\/fusiontables\/DataSource/)) {
                return this.getColumnsFromUrl(table_id, callback);
            }
            
            var sql = "SELECT * FROM " + table_id + " LIMIT 1";
            query(sql, function(resp) {
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
                    table_id: table_id,
                    columns: columns
                });
                if (that.$('div.table_id').is('.error')) {
                    that.$('div.table_id').removeClass('error').find('p.howto')
                    .text('Paste in a Table ID from Google Fusion Tables');
                };
                select.attr('disabled', false);
                if (_.isFunction(callback)) callback();
            },
            // errback
            this.tableIdError);
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

    window.AppView = Backbone.View.extend({
    
        el: $('#ft-builder'),
    
        events: {
            'click input.new-layer'   : 'createLayer',
            'click input.update-map'  : 'render_map',
            'change select#map-width' : 'render_map',
            'change input#map-height' : 'render_map',
            'change input#map-zoom'   : 'render_map',
            'change input#map-center' : 'render_map'
        },
    
        jsTemplate: _.template( $('#map-embed-template').html() ),
    
        initialize: function(options) {
            _.bindAll(this);
            layers.bind('add', this.addLayer);
            
            this.options = new MapOptions(options);            
            
            that = this;
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
                    layers: layers.complete(),
                    rows: legend.collection.complete()
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
    
    var LegendRowInput = Backbone.View.extend({
        className: 'legend-row',
        template: _.template( $('#legend-row-template').html() ),
        
        events: {
            'click input.remove' : 'remove'
        },
        
        initialize: function(options) {
            _.bindAll(this);
            this.model.view = this;
            this.watchFields();
            return this.render();
        },
        
        render: function() {
            var data = _.extend(this.model.toJSON(), {
                cid: this.model.cid
            });
            $(this.el).html( this.template(data) );
            return this;
        },
        
        
        watchFields: function() {
            var row = this.model;
            for (var field in this.model.defaults) {
                this.$('input.' + field).change(function(){
                    changes = {};
                    changes[field] = $(this).val();
                    row.set(changes);
                });
            }
            return this;
        }
        
    })
    
    window.Legend = Backbone.View.extend({
        
        el: '#legend',
        
        events: {
            'click input.add-row' : 'createRow'
        },
        
        initialize: function(options) {
            _.bindAll(this);
            this.collection = new LegendRowCollection;
            
            this.collection.bind('add', this.addRow);
            return this;
        },
        
        addRow: function(row) {
            var view = new LegendRowInput({ model: row });
            this.$('#rows').append(view.el);
            return this;
        },
        
        createRow: function(e) {
            var row = new LegendRow;
            this.collection.add(row);
            return row;
        }
        
    });
    
    window.legend = new Legend;

});