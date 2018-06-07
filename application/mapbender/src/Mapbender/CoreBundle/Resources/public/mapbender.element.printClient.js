(function($) {
    
    $.widget("mapbender.mbPrintClient",  {
        options: {
            style: {
                fillColor:     '#ffffff',
                fillOpacity:   0.5,
                //strokeColor:   '#000000',
                strokeColor:   'red',
                strokeOpacity: 1.0,
                strokeWidth:    2
            }
        },
        map: null,
        layer: null,
        control: null,
        feature: null,
        renderer: null,
        lastScale: null,
        lastRotation: null,
        width: null,
        height: null,
        rotateValue: 0,

        _create: function() {
            if(!Mapbender.checkTarget("mbPrintClient", this.options.target)){
                return;
            }
            var self = this;
            Mapbender.elementRegistry.onElementReady(this.options.target, $.proxy(self._setup, self));
        },

        _setup: function(){
            var self = this;
            this.elementUrl = Mapbender.configuration.application.urls.element + '/' + this.element.attr('id') + '/';
            this.map = $('#' + this.options.target).data('mapbenderMbMap');
            
            $('select[name="scale_select"]', this.element)
                .on('change', $.proxy(this._updateGeometry, this));
            $('input[name="rotation"]', this.element)
                .on('keyup', $.proxy(this._updateGeometry, this));
            $('select[name="template"]', this.element)
                .on('change', $.proxy(this._getTemplateSize, this)); 
        
                   
            if (this.options.type === 'element') {
                $(this.element).show();
                $(this.element).on('click', '#printToggle', function(){
                    var active = $(this).attr('active');
                    if(active === 'true') {// deactivate
                        $(this).attr('active','false').removeClass('active');
                        $(this).val(Mapbender.trans('mb.core.printclient.btn.activate'));
                        self._updateElements(false);
                        $('.printSubmit', this.element).addClass('hidden');
                    }else{ // activate
                        $(this).attr('active','true').addClass('active');
                        $(this).val(Mapbender.trans('mb.core.printclient.btn.deactivate'));
                        self._getTemplateSize();
                        self._updateElements(true);
                        self._setScale();
                        $('.printSubmit', this.element).removeClass('hidden');
                    }
                });
                $('.printSubmit', this.element).on('click', $.proxy(this._print, this));
            }

            this._trigger('ready');
            this._ready();
        },

        defaultAction: function(callback) {
            this.open(callback);
        },

        open: function(callback){
            // For-Schleife überprüft ob ein Feature anvisiert/ausgewählt/markiert ist, wenn das der Fall ist, wird die Auswahl aufgehoben
            for (i = 0; i < Mapbender.elementRegistry.listWidgets().mapbenderMbRedlining.layer.features.length; i++) {
                var feature = Mapbender.elementRegistry.listWidgets().mapbenderMbRedlining.layer.features[i];
                if(feature.renderIntent === "select") {
                    Mapbender.elementRegistry.listWidgets().mapbenderMbRedlining.activeControl.unselectFeature(feature);
                }
            }
            
            this.callback = callback ? callback : null;
            var self = this;
            var me = $(this.element);
            if (this.options.type === 'dialog') {
                if(!this.popup || !this.popup.$element){
                    this.popup = new Mapbender.Popup2({
                            title: self.element.attr('title'),
                            draggable: true,
                            header: true,
                            modal: false,
                            closeButton: false,
                            closeOnESC: false,
                            content: self.element,
                            width: 400,
                            height: 490,
                            cssClass: 'customPrintDialog',
                            buttons: {
                                    'cancel': {
                                        label: Mapbender.trans('mb.core.printclient.popup.btn.cancel'),
                                        cssClass: 'button buttonCancel critical right',
                                        callback: function(){
                                            self.close();
                                        }
                                    },
                                    'ok': {
                                        label: Mapbender.trans('mb.core.printclient.popup.btn.ok'),
                                        cssClass: 'button right',
                                        callback: function(){
                                            self._print();
                                        }
                                    }
                            }
                        });
                    this.popup.$element.on('close', $.proxy(this.close, this));
                }else{
                     return;
                }
                me.show();
                this._getTemplateSize();
                this._updateElements(true);
                this._setScale();
            }
        },

        close: function() {
            if(this.popup){
                this.element.hide().appendTo($('body'));
                this._updateElements(false);
                if(this.popup.$element){
                    this.popup.destroy();
                }
                this.popup = null;
            }
            this.callback ? this.callback.call() : this.callback = null;
        },
        
        _setScale: function() {
            var select = $(this.element).find("select[name='scale_select']");
            var styledSelect = select.parent().find(".dropdownValue.iconDown");
            var scales = this.options.scales;
            var currentScale = Math.round(this.map.map.olMap.getScale());
            var selectValue;
            
            $.each(scales, function(idx, scale) {
                if(scale <= currentScale) {
                    if ( idx == (scales.length - 1) ) {
                        selectValue = scales[idx];
                    }
                    else {
                        selectValue = scales[idx + 1];
                    }
                    return false;
                }
            });

            select.val(selectValue);
            styledSelect.html('1:'+selectValue);
            
            this._updateGeometry(true);
        },
       

       
        _updateGeometry: function(reset) {
            var width = this.width,
                height = this.height,
                scale = this._getPrintScale(),
                rotationField = $(this.element).find('input[name="rotation"]');
            
            // remove all not numbers from input
            rotationField.val(rotationField.val().replace(/[^\d]+/,''));

            if (rotationField.val() === '' && this.rotateValue > '0'){
                rotationField.val('0');
            }
            var rotation = rotationField.val();
            this.rotateValue = rotation;

            if(!(!isNaN(parseFloat(scale)) && isFinite(scale) && scale > 0)) {
                if(null !== this.lastScale) {
                //$('input[name="scale_text"]').val(this.lastScale).change();  //nicht von TIM auskommentiert
                }
                return;
            }
            scale = parseInt(scale);

            if(!(!isNaN(parseFloat(rotation)) && isFinite(rotation))) {
                if(null !== this.lastRotation) {
                    rotationField.val(this.lastRotation).change();
                }
            }
            rotation= parseInt(-rotation);
            this.lastScale = scale;

            var world_size = {
                x: width * scale / 100,
                y: height * scale / 100
            };

            var center = (reset === true || !this.feature) ?
            this.map.map.olMap.getCenter() :
            this.feature.geometry.getBounds().getCenterLonLat();

            //alles auf null setzen
            if(this.feature) {                
                this.layer.removeAllFeatures();
                this.feature = null;
                
                this.map.map.olMap.removeControl(this.control);
                this.control = null;
                
                this.map.map.olMap.removeLayer(this.layer);
                this.layer = null;
            }

            this.feature = new OpenLayers.Feature.Vector(new OpenLayers.Bounds(
                center.lon - 0.5 * world_size.x,
                center.lat - 0.5 * world_size.y,
                center.lon + 0.5 * world_size.x,
                center.lat + 0.5 * world_size.y).toGeometry(), {});
            this.feature.world_size = world_size;

            if(this.map.map.olMap.units === 'degrees' || this.map.map.olMap.units === 'dd') {
                var centroid = this.feature.geometry.getCentroid();
                var centroid_lonlat = new OpenLayers.LonLat(centroid.x,centroid.y);
                var centroid_pixel = this.map.map.olMap.getViewPortPxFromLonLat(centroid_lonlat);
                var centroid_geodesSize = this.map.map.olMap.getGeodesicPixelSize(centroid_pixel);

                var geodes_diag = Math.sqrt(centroid_geodesSize.w*centroid_geodesSize.w + centroid_geodesSize.h*centroid_geodesSize.h) / Math.sqrt(2) * 100000;

                var geodes_width = width * scale / (geodes_diag);
                var geodes_height = height * scale / (geodes_diag);

                var ll_pixel_x = centroid_pixel.x - (geodes_width) / 2;
                var ll_pixel_y = centroid_pixel.y + (geodes_height) / 2;
                var ur_pixel_x = centroid_pixel.x + (geodes_width) / 2;
                var ur_pixel_y = centroid_pixel.y - (geodes_height) /2 ;
                var ll_pixel = new OpenLayers.Pixel(ll_pixel_x, ll_pixel_y);
                var ur_pixel = new OpenLayers.Pixel(ur_pixel_x, ur_pixel_y);
                var ll_lonlat = this.map.map.olMap.getLonLatFromPixel(ll_pixel);
                var ur_lonlat = this.map.map.olMap.getLonLatFromPixel(ur_pixel);

                this.feature = new OpenLayers.Feature.Vector(new OpenLayers.Bounds(
                    ll_lonlat.lon,
                    ur_lonlat.lat,
                    ur_lonlat.lon,
                    ll_lonlat.lat).toGeometry(), {});
                this.feature.world_size = {
                    x: ur_lonlat.lon - ll_lonlat.lon,
                    y: ur_lonlat.lat - ll_lonlat.lat
                };
            }
            
            if (this.layer === null) {
                this.layer = new OpenLayers.Layer.Vector("Print", {
                    styleMap: new OpenLayers.StyleMap({
                        'default': new OpenLayers.Style({
                                fillColor:     '#ffffff',
                                fillOpacity:   0.5,
                                strokeColor:   '#1879BF',
                                strokeOpacity: 1.0,
                                strokeWidth:    2,
                                cursor: 'all-scroll'
                            }),
                        "transform": new OpenLayers.Style({
                            display: "${getDisplay}",
                            cursor: "grab",
                            pointRadius: 5,
                            fillColor: "white",
                            fillOpacity: 1,
                            strokeColor: "black"
                        }, {
                            context: {
                                getDisplay: function(feature) {
                                    // hide the resize handle at the south-east corner
                                    return feature.attributes.role === "ne-resize" ? "none" : "none";
                                }
                            }
                        }),
                        "rotate": new OpenLayers.Style({
                            cursor:"pointer",
                            display: "${getDisplay}",
                            pointRadius: 10,
                            fillColor: "#D81920",
                            fillOpacity: 1,
                            strokeColor: "white"
                        }, {
                            context: {
                                getDisplay: function(feature) {
                                    // only display the rotate handle at the south-east corner
                                    return feature.attributes.role === "ne-rotate" ? "" : "none";
                                }
                            }
                        })
                    }),
                   // renderers: renderer
                });
                this.map.map.olMap.addLayer(this.layer);
            }
            
            this.control = new OpenLayers.Control.TransformFeature(this.layer, {
                renderIntent: "transform",
                rotationHandleSymbolizer: "rotate"
            });
            
            this.control.events.register('transform', this.feature, function(event){
                if(parseInt(event.object.rotation) <= 0) {
                    $('input[name="rotation"]').val(360 - (event.object.rotation + 360));
                } else if (event.object.rotation >= 360 ) {
                    $('input[name="rotation"]').val(360 - (event.object.rotation  - 360));
                } else {
                    $('input[name="rotation"]').val(360 - event.object.rotation);
                }
            });
            this.map.map.olMap.addControl(this.control);
            
            
            if(reset !== undefined && reset.type !== undefined) {
                if(reset.type === 'keyup' || reset.type === 'change') {
                //if(reset.type === 'keyup') {
                    this._rotateFeature(rotation,new OpenLayers.Geometry.Point(this.feature.geometry.getCentroid().x, this.feature.geometry.getCentroid().y));
                    this.layer.addFeatures(this.feature);
                    this.control.setFeature(this.feature, {rotation:rotation});
                }
            } else {
                this.layer.addFeatures(this.feature);
                this.control.setFeature(this.feature, {});
            }
        },
        _rotateFeature: function(angle, origin) {
            this.feature.geometry.rotate(angle, origin);
        },
        _updateElements: function(active) {
           
            if(true === active){
                if(null === this.layer) {
                    this.layer = new OpenLayers.Layer.Vector("Print", {
                        styleMap: new OpenLayers.StyleMap({
                            'default': $.extend({}, OpenLayers.Feature.Vector.style['default'], this.options.style),})});
                }

                if(null === this.control) { 
                    this.control = new OpenLayers.Control.TransformFeature(this.layer,  { 
                        renderIntent: "transform", 
                        rotationHandleSymbolizer: "rotate"
                    });
                    this.control.events.register('transform', this.feature, function(event){
                        if(parseInt(event.object.rotation) <= 0) {
                            $('input[name="rotation"]').val(360 - (event.object.rotation + 360));
                        } else if (event.object.rotation >= 360 ) {
                            $('input[name="rotation"]').val(360 - (event.object.rotation  - 360));
                        } else {
                            $('input[name="rotation"]').val(360 - event.object.rotation);
                        }
                    });
                }
                
                
                this.map.map.olMap.addControl(this.control);
                this.map.map.olMap.addLayer(this.layer);
                
                this.control.activate();
                this._updateGeometry(true);
/*
 * Else wird ausgeführt, wenn auf Abbrechen bzw. das Close-X geklickt wird
 */
            }else{
                if(null !== this.control) {
                    this.control.deactivate();
                    this.control.destroy();
                    this.map.map.olMap.removeControl(this.control);
                    this.control = null;
                }
                if(null !== this.layer) {
                    this.layer.removeAllFeatures();
                    this.map.map.olMap.removeLayer(this.layer);
                }
                if(null !== this.feature) {
                    this.feature = null;
                }
            }
        },

        _getPrintScale: function() {
            return $(this.element).find('select[name="scale_select"]').val();
        },

        _getPrintExtent: function() {
            var data = {
                extent: {},
                center: {}
            };

            data.extent.width = this.feature.world_size.x;
            data.extent.height = this.feature.world_size.y;
            data.center.x = this.feature.geometry.getBounds().getCenterLonLat().lon;
            data.center.y = this.feature.geometry.getBounds().getCenterLonLat().lat;

            return data;
        },

        _print: function() {
            var form = $('form#formats', this.element);
            var extent = this._getPrintExtent();

            // Felder für extent, center und layer dynamisch einbauen
            var fields = $();

            $.merge(fields, $('<input />', {
                type: 'hidden',
                name: 'extent[width]',
                value: extent.extent.width
            }));

            $.merge(fields, $('<input />', {
                type: 'hidden',
                name: 'extent[height]',
                value: extent.extent.height
            }));

            $.merge(fields, $('<input />', {
                type: 'hidden',
                name: 'center[x]',
                value: extent.center.x
            }));

            $.merge(fields, $('<input />', {
                type: 'hidden',
                name: 'center[y]',
                value: extent.center.y
            }));

            // extent feature
            var feature_coords = new Array();
            var feature_comp = this.feature.geometry.components[0].components;
            for(var i = 0; i < feature_comp.length-1; i++) {
                feature_coords[i] = new Object();
                feature_coords[i]['x'] = feature_comp[i].x;
                feature_coords[i]['y'] = feature_comp[i].y;
            }

            $.merge(fields, $('<input />', {
                type: 'hidden',
                name: 'extent_feature',
                value: JSON.stringify(feature_coords)
            }));

            // wms layer
            var sources = this.map.getSourceTree(), lyrCount = 0;

            function _getLegends(layer, sourceUrl) {
                var legend = null;
                if (layer.options.legend && layer.options.legend.url && layer.options.treeOptions.selected == true) {
                    legend = {};
                    legend[layer.options.title] = layer.options.legend.url;
                }
                else if (layer.options.treeOptions.selected == true && sourceUrl) {
                    legend = {};
                    legend[layer.options.title] = sourceUrl + 'service=WMS&version=1.3.0&request=GetLegendGraphic&format=image/png&sld_version=1.1.0&layer=' + layer.options.name;
                }
                if (layer.children) {
                    for (var i = 0; i < layer.children.length; i++) {
                        var help = _getLegends(layer.children[i]);
                        if (help) {
                            legend = legend ? legend : {};
                            for (key in help) {
                                legend[key] = help[key];
                            }
                        }
                    }
                }
                return legend;
            } 
            var legends = [];
            
            // Array anlegen zur Sicherstellung der korrekten Layer-Reihenfolge im Druck
            var lyrConfsWithCalculatedWeights = [];

            for (var i = 0; i < sources.length; i++) {
                var layer = this.map.map.layersList[sources[i].mqlid],
                        type = layer.olLayer.CLASS_NAME;

                if (0 !== type.indexOf('OpenLayers.Layer.')) {
                    continue;
                }

                if (Mapbender.source[sources[i].type] && typeof Mapbender.source[sources[i].type].getPrintConfig === 'function') {
                    var source = sources[i],
                        scale = this._getPrintScale(),
                        toChangeOpts = {options: {children: {}}, sourceIdx: {mqlid: source.mqlid}};
                    var visLayers = Mapbender.source[source.type].changeOptions(source, scale, toChangeOpts);
                    if (visLayers.layers.length > 0) {
                        var prevLayers = layer.olLayer.params.LAYERS;
                        layer.olLayer.params.LAYERS = visLayers.layers;

                        var opacity = sources[i].configuration.options.opacity;
                        var lyrConf = Mapbender.source[sources[i].type].getPrintConfig(layer.olLayer, this.map.map.olMap.getExtent(), sources[i].configuration.options.proxy);
                        lyrConf.opacity = opacity;
                        
                        // Wichtung des Layers auf 0 setzen
                        var calculatedWeight = 0;
                        
                        // falls Layer keine Hintergrundkarte ist...
                        if (!source.configuration.isBaseSource) {
                            // ...Wichtung des Layers auf Position in OpenLayers setzen...
                            var mqLayer = this.map.map.layersList[source.mqlid];
                            calculatedWeight = mqLayer.position();
                            // ...Array zur Sicherstellung der korrekten Layer-Reihenfolge im Druck befüllen mit Layer und dessen Wichtung
                            lyrConfsWithCalculatedWeights.push(lyrConf, calculatedWeight);
                        // ansonsten...
                        } else {
                            // ...Layer mit dessen Wichtung ablegen
                            $.merge(fields, $('<input />', {
                                type: 'hidden',
                                name: 'layers[' + lyrCount + ']',
                                value: JSON.stringify(lyrConf),
                                weight: calculatedWeight
                            }));
                            lyrCount++;
                        }
                        
                        layer.olLayer.params.LAYERS = prevLayers;
                        
                        if (sources[i].type === 'wms') {
                            var ll = _getLegends(sources[i].configuration.children[0], source.configuration.options.url.replace(/\?.*/i, '?'));
                            if (ll) {
                                legends.push(ll);
                            }
                        }
                    }
                }
            }
            
            // falls Array zur Sicherstellung der korrekten Layer-Reihenfolge im Druck nicht immer noch leer ist...
            if (lyrConfsWithCalculatedWeights.length > 0) {
            
                // ...Array zur Sicherstellung der korrekten Layer-Reihenfolge nach Wichtung sortieren (also nach dem 2., 4., 6. Wert usw.)...
                var groupSize = 2;    
                var tempArray = [];
                while((sec = lyrConfsWithCalculatedWeights.splice(0, groupSize)).length > 0) {
                    tempArray.push(sec);
                }
                tempArray.sort();
                lyrConfsWithCalculatedWeights = [];
                for(var i in tempArray) {
                    for(var j in tempArray[i]) {
                        lyrConfsWithCalculatedWeights.push(tempArray[i][j]);
                    }
                }
                
                // ...sortiertes Array zur Sicherstellung der korrekten Layer-Reihenfolge durchlaufen und jeweiligen Layer mit dessen Wichtung ablegen
                for (var j = 0; j < lyrConfsWithCalculatedWeights.length; j++) {
                    if (j % 2 == 0) {
                        $.merge(fields, $('<input />', {
                            type: 'hidden',
                            name: 'layers[' + lyrCount + ']',
                            value: JSON.stringify(lyrConfsWithCalculatedWeights[j]),
                            weight: lyrConfsWithCalculatedWeights[j + 1]
                        }));
                        lyrCount++;
                    }
                }
                
            }

            //legend
            if($('input[name="printLegend"]',form).prop('checked')){
                $.merge(fields, $('<input />', {
                    type: 'hidden',
                    name: 'legends',
                    value: JSON.stringify(legends)
                }));
            }
            
            // Iterating over all vector layers, not only the ones known to MapQuery
            var geojsonFormat = new OpenLayers.Format.GeoJSON();
            for(var i = 0; i < this.map.map.olMap.layers.length; i++) {
                var layer = this.map.map.olMap.layers[i];
                if('OpenLayers.Layer.Vector' !== layer.CLASS_NAME || this.layer === layer) {
                    continue;
                }

                var geometries = [];
                for(var idx = 0; idx < layer.features.length; idx++) {
                    var feature = layer.features[idx];
                    if (!feature.onScreen(true)) continue

                    if(this.feature.geometry.intersects(feature.geometry)){
                        var geometry = geojsonFormat.extract.geometry.apply(geojsonFormat, [feature.geometry]);
                        if(feature.style !== null){
                            geometry.style = feature.style;
                        }else{
                            geometry.style = layer.styleMap.createSymbolizer(feature,feature.renderIntent);
                        }
                        // only visible features
                        if(geometry.style.fillOpacity > 0 && geometry.style.strokeOpacity > 0){                            
                            geometries.push(geometry);
                        } else if (geometry.style.label !== undefined){
                            geometries.push(geometry);
                        }
                    }
                }

                var lyrConf = {
                    type: 'GeoJSON+Style',
                    opacity: 1,
                    geometries: geometries
                };

                $.merge(fields, $('<input />', {
                    type: 'hidden',
                    name: 'layers[' + (lyrCount + i) + ']',
                    value: JSON.stringify(lyrConf),
                    weight: this.map.map.olMap.getLayerIndex(layer)
                }));
            }
            
            // Markers
            var geojsonFormat = new OpenLayers.Format.GeoJSON();
            var markerStyleMap = new OpenLayers.StyleMap({ 
                'default': {
                    fillColor: "#780046",
                    fillOpacity: 1,
                    strokeColor: "#780046",
                    strokeOpacity: 1,
                    strokeWidth: "0",
                    pointRadius: "20"
                }
            });
            var weight = 0;
            for(var i = 0; i < this.map.map.olMap.layers.length; i++) {
                var layer = this.map.map.olMap.layers[i];
                if('OpenLayers.Layer.Markers' !== layer.CLASS_NAME || this.layer === layer) {
                    continue;
                }

                var points = [];
                for(var idy = 0; idy < layer.markers.length; idy++) {
                    var marker = layer.markers[idy];
                    var point = new OpenLayers.Geometry.Point(marker.lonlat.lon, marker.lonlat.lat);
                    
                    if(this.feature.geometry.intersects(point)){
                        var geometry = geojsonFormat.extract.geometry.apply(geojsonFormat, [point]);
                        feature = new OpenLayers.Feature.Vector(point);
                        geometry.style = markerStyleMap.createSymbolizer(feature,'default');
                        points.push(geometry);
                    }
                }

                var lyrConf = {
                    type: 'GeoJSON+Style',
                    opacity: 1,
                    geometries: points
                };

                $.merge(fields, $('<input />', {
                    type: 'hidden',
                    name: 'layers[' + (lyrCount + i) + ']',
                    value: JSON.stringify(lyrConf),
                    weight: this.map.map.olMap.getLayerIndex(layer)
                }));
                weight = this.map.map.olMap.getLayerIndex(layer);
            }
            
            // Pop-ups
            var geojsonFormat = new OpenLayers.Format.GeoJSON();
            var popupStyleMap = new OpenLayers.StyleMap({ 
                'default': {
                    fillColor: "#780046",
                    fillOpacity: 0,
                    strokeColor: "#780046",
                    strokeOpacity: 0,
                    strokeWidth: "0",
                    pointRadius: "0",
                    display: "popup"
                }
            });
            var points = [];
            for(var i = 0; i < this.map.map.olMap.popups.length; i++) {
                var popup = this.map.map.olMap.popups[i];

                var point = new OpenLayers.Geometry.Point(popup.lonlat.lon, popup.lonlat.lat);
                
                if(this.feature.geometry.intersects(point)){
                    var geometry = geojsonFormat.extract.geometry.apply(geojsonFormat, [point]);
                    feature = new OpenLayers.Feature.Vector(point);
                    geometry.style = popupStyleMap.createSymbolizer(feature,'default');
                    geometry.style.label = popup.contentHTML;
                    points.push(geometry);
                }

                var lyrConf = {
                    type: 'GeoJSON+Style',
                    opacity: 1,
                    geometries: points
                };

                $.merge(fields, $('<input />', {
                    type: 'hidden',
                    name: 'layers[' + (lyrCount + i) + ']',
                    value: JSON.stringify(lyrConf),
                    weight: weight + 1
                }));
            }

            // overview map
            var ovMap = this.map.map.olMap.getControlsByClass('OpenLayers.Control.OverviewMap')[0],
            count = 0;
            if (undefined !== ovMap){
                for(var i = 0; i < ovMap.layers.length; i++) {
                    var url = ovMap.layers[i].getURL(ovMap.map.getExtent());
                    var extent = ovMap.map.getExtent();
                    var mwidth = extent.getWidth();
                    var size = ovMap.size;
                    var width = size.w;
                    var res = mwidth / width;
                    var scale = Math.round(OpenLayers.Util.getScaleFromResolution(res,'m'));

                    var overview = {};
                    overview.url = url;
                    overview.scale = scale;

                    $.merge(fields, $('<input />', {
                        type: 'hidden',
                        name: 'overview[' + count + ']',
                        value: JSON.stringify(overview)
                    }));
                    count++;
                }
            }

            $('div#layers', form).empty();
            fields.appendTo(form.find('div#layers'));

            // Post in neuen Tab (action bei form anpassen)
            var url =  this.elementUrl + 'print';

            if (lyrCount === 0){
                Mapbender.info(Mapbender.trans('mb.core.printclient.info.noactivelayer'));
            }else{
                $('#print-loading-modal').modal('show');
                var formVars = form.serialize();
                var xhr = new XMLHttpRequest();
                xhr.open('POST', url, true);
                xhr.responseType = 'arraybuffer';
                xhr.onload = function () {
                    $('#print-loading-modal').modal('hide');
                    if (this.status === 200) {
                        var filename = '';
                        var disposition = xhr.getResponseHeader('Content-Disposition');
                        if (disposition && disposition.indexOf('attachment') !== -1) {
                            var filenameRegex = /filename[^;=\n]*=((['"]).*?\2|[^;\n]*)/;
                            var matches = filenameRegex.exec(disposition);
                            if (matches != null && matches[1]) filename = matches[1].replace(/['"]/g, '');
                        }
                        var type = xhr.getResponseHeader('Content-Type');
                        var blob = typeof File === 'function'
                            ? new File([this.response], filename, { type: type })
                            : new Blob([this.response], { type: type });
                        if (typeof window.navigator.msSaveOrOpenBlob !== 'undefined') {
                            window.navigator.msSaveOrOpenBlob(blob, filename);
                        } else {
                            var URL = window.URL || window.webkitURL;
                            var downloadUrl = URL.createObjectURL(blob);
                            if (filename) {
                                var a = document.createElement('a');
                                if (typeof a.download === 'undefined') {
                                    window.location = downloadUrl;
                                } else {
                                    a.href = downloadUrl;
                                    a.download = filename;
                                    document.body.appendChild(a);
                                    a.click();
                                }
                            } else {
                                window.location = downloadUrl;
                            }
                            setTimeout(function () { URL.revokeObjectURL(downloadUrl); }, 100);
                        }
                    }
                };
                xhr.setRequestHeader('Content-type', 'application/x-www-form-urlencoded');
                xhr.send(formVars);
            }

            if(this.options.autoClose){
                this.popup.close();
            }
        },

        _getTemplateSize: function() {
            var self = this;
            var template = $('select[name="template"]', this.element).val();

            var url =  this.elementUrl + 'getTemplateSize';
            $.ajax({
                url: url,
                type: 'GET',
                data: {template: template},
                dataType: "json",
                success: function(data) {
                    self.width = data.width;
                    self.height = data.height;
                    self._updateGeometry(); 
                }
            });
        },

        /**
         *
         */
        ready: function(callback) {
            if(this.readyState === true) {
                callback();
            } else {
                this.readyCallbacks.push(callback);
            }
        },
        /**
         *
         */
        _ready: function() {
            for(callback in this.readyCallbacks) {
                callback();
                delete(this.readyCallbacks[callback]);
            }
            this.readyState = true;
        }
    });

})(jQuery);