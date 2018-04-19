(function($){

    $.widget("mapbender.mbOverview", {
        options: {
            layerset: []
        },
        overview: null,
        layersOrigExtents: {},
        mapOrigExtents: {},
        startproj: null,
        /**
         * Creates the overview
         */
        _create: function(){
            if(!Mapbender.checkTarget("mbOverview", this.options.target)){
                return;
            }
            var self = this;
            Mapbender.elementRegistry.onElementReady(this.options.target, $.proxy(self._setup, self));
        },
        /**
         * Initializes the overview
         */
        _setup: function(){
            var self = this;
            var mbMap = $('#' + this.options.target).data('mapbenderMbMap');
            $(this.element).addClass(this.options.anchor);
            var proj = mbMap.model.mapMaxExtent.projection;
            var max_ext = mbMap.model.mapMaxExtent.extent;
            this.startproj = proj;
            var layers_overview = [];
            $.each(Mapbender.configuration.layersets[self.options.layerset].reverse(),
                function(idx, item){
                    $.each(item, function(idx2, layerDef){
                        if(layerDef.type === "wms"){
                            var ls = "";
                            var layers = Mapbender.source[layerDef.type].getLayersList(layerDef, layerDef.configuration.children[0], true);
                            for(var i = 0; i < layers.layers.length; i++){
                                ls += layers.layers[i].options.name !== "" ? "," + layers.layers[i].options.name : "";
                            }

                            // Add proxy if needed
                            var url = layerDef.configuration.options.url;
                            if(layerDef.configuration.options.proxy) {
                                url = OpenLayers.ProxyHost + encodeURIComponent(url);
                            }
                            layers_overview.push(new OpenLayers.Layer.WMS(
                                layerDef.title,
                                url,
                                {
                                    layers: ls.substring(1),
                                    format: layerDef.configuration.options.format,
                                    transparent: layerDef.configuration.options.transparent
                                },
                            {
                                isBaseLayer: idx === 0 ? true : false,
                                opacity: layerDef.configuration.options.opacity,
                                singleTile: true
                            }
                            ));
                            self._addOrigLayerExtent(layerDef);
                        }
                    });
                });
            if(layers_overview.length === 0){
                Mapbender.info(Mapbender.trans("mb.core.overview.nolayer"));
                return;
            }
            this.mapOrigExtents = {
                max: {
                    projection: proj,
                    extent: max_ext
                }
            };
            var div = $('#mb-element-overview-map', self.element);
            div = div.size() > 0 ? div.get(0) : undefined;
            var overviewOptions = {
                layers: layers_overview,
                div: div,
                size: new OpenLayers.Size(self.options.width, self.options.height),
                minRatio: 16,
                maxRatio: 32,
                autoPan: true,
                //maximized: self.options.maximized,
                mapOptions: {
                    maxExtent: max_ext,
                    projection: proj,
                    theme: null
                }
            };
            if(this.options.fixed){
                $.extend(overviewOptions, {
                    minRatio: 1,
                    maxRatio: 1000000000,
                    autoPan: false
                });
            }
            this.overview = new OpenLayers.Control.OverviewMap(overviewOptions);          
            mbMap.map.olMap.addControl(this.overview);
            $(document).bind('mbmapsrschanged', $.proxy(this._changeSrs, this));
            $(self.element).find('.toggleOverview').bind('click', $.proxy(this._openClose, this));
            
            if(!this.options.maximized){
                $(this.element).addClass("closed");
            }    
                
            this._trigger('ready');
            this._ready();
        },
        /**
         * Opens/closes the overview element
         */
        _openClose: function(event){
            var self = this;
            $(this.element).toggleClass('closed');
            window.setTimeout(function(){
                if(!$(self.element).hasClass('closed')){
                    self.overview.ovmap.updateSize();
                }
            }, 300);
        },
        /*
         * Transforms an extent into 'projection' projection.
         */
        _transformExtent: function(extentObj, projection){
            if(extentObj.extent != null){
                if(extentObj.projection.projCode == projection.projCode){
                    return extentObj.extent.clone();
                }else{
                    var newextent = extentObj.extent.clone();
                    newextent.transform(extentObj.projection, projection);
                    return newextent;
                }
            }else{
                return null;
            }
        },
        /**
         * Cahnges the overview srs
         */
        _changeSrs: function(event, srs){
            var self = this;
            var oldProj = this.overview.ovmap.projection;
            var center = this.overview.ovmap.getCenter().transform(oldProj, srs.projection);
            this.overview.ovmap.projection = srs.projection;
            this.overview.ovmap.displayProjection = srs.projection;
            this.overview.ovmap.units = srs.projection.proj.units;

            this.overview.ovmap.maxExtent = this._transformExtent(
                this.mapOrigExtents.max, srs.projection);
            $.each(self.overview.ovmap.layers, function(idx, layer){
                layer.projection = srs.projection;
                layer.units = srs.projection.proj.units;
                if(!self.layersOrigExtents[layer.id]){
                    self._addOrigLayerExtent(layer);
                }
                if(layer.maxExtent && layer.maxExtent != self.overview.ovmap.maxExtent){
                    layer.maxExtent = self._transformExtent(
                        self.layersOrigExtents[layer.id].max, srs.projection);
                }
                layer.initResolutions();
            });
            this.overview.update();
            this.overview.ovmap.setCenter(center, this.overview.ovmap.getZoom(), false, true);
        },
        /**
         * Adds a layer's original extent into the widget layersOrigExtent.
         */
        _addOrigLayerExtent: function(layer){
            if(layer.olLayer){
                layer = layer.olLayer;
            }
            if(!this.layersOrigExtents[layer.id]){
                this.layersOrigExtents[layer.id] = {
                    max: {
                        projection: this.startproj,
                        extent: layer.maxExtent ? layer.maxExtent.clone() : null
                    }
                };
            }
        },
        /**
         *
         */
        ready: function(callback){
            if(this.readyState === true){
                callback();
            }else{
                this.readyCallbacks.push(callback);
            }
        },
        /**
         *
         */
        _ready: function(){
            for(callback in this.readyCallbacks){
                callback();
                delete(this.readyCallbacks[callback]);
            }
            this.readyState = true;
        },
    });

})(jQuery);
