(function($) {

$.widget("mapbender.mbActivityIndicator", {
    options: {
        activityClass: 'mb-activity',
        ajaxActivityClass: 'mb-activity-ajax',
        tileActivityClass: 'mb-activity-tile'
    },

    elementUrl: null,
    ajaxActivity: false,
    tileActivity: false,
    loadingLayers: {},
    targets: {},

    _create: function() {
       for(id in Mapbender.configuration.elements){
           var element = Mapbender.configuration.elements[id];
            if(element.init === 'mapbender.mbMap'){
                this.targets[id] = false;
                if(!Mapbender.checkTarget("mbActivityIndicator", id)){ // check if target defined
                    return;
                }
                var self = this;
                Mapbender.elementRegistry.onElementReady(id, $.proxy(self._setup, self, id)); // call _setup if target ready
            }
        }
    },
    _setup: function(id) {
        var self = this;
        $('#'+id).each(function() {
            var mqMap = $(this).data('mapbenderMbMap').map;
            $.each(mqMap.layers(), function(idx, mqLayer) {
                self._bindToLayer(mqLayer);
                // Is it already loading tiles?
                if(typeof mqLayer.olLayer.numLoadingTiles === 'number' &&
                    mqLayer.olLayer.numLoadingTiles > 0) {
                    self.loadingLayers[mqLayer.olLayer.id] = true;
                    self._onLayerLoadChange();
                }
            });
            mqMap.events.bind('mqAddLayer', function(event, mqLayer) {
                self._bindToLayer(mqLayer);
            });
        });
        this.targets[id] = true;
        var allInited = true;
        for(id in this.targets){
            if(!this.targets[id])
                allInited = false;
        }
        if(allInited){
            this.element.bind('ajaxStart', $.proxy(this._onAjaxStart, this));
            this.element.bind('ajaxStop', $.proxy(this._onAjaxStop, this));
        }
    },

    destroy: function() {},

    _bindToLayer: function(mqLayer) {
        mqLayer.olLayer.events.on({
            scope: this,
            loadstart: function(event) {
                this.loadingLayers[event.object.id] = true;
                this._onLayerLoadChange();
            },
            loadend: function(event) {
                delete this.loadingLayers[event.object.id];
                this._onLayerLoadChange();
            }
        });
    },

    _destroy: function() {
        var classes = [
            this.options.activityClass,
            this.options.ajaxActivityClass,
            this.options.tileActivityClass];
        $('body').removeClass(classes.join(' '));
    },

    /**
     * Listener for global ajaxStart events
     */
    _onAjaxStart: function() {
        this.ajaxActivity = true;
        this._updateBodyClass();
    },

    /**
     * Listener for global ajaxStop events
     */
    _onAjaxStop: function() {
        this.ajaxActivity = false;
        this._updateBodyClass();
    },

    _onLayerLoadChange: function() {
        var keys = Object.keys || function(obj) {
            var keys = [];
            for(var key in obj) {
                if(obj.hasOwnProperty(key)) {
                    keys[keys.length] = key;
                }
            }
            return keys;
        };

        var stillLoading = keys(this.loadingLayers).length > 0;
        if(stillLoading !== this.tileActivity) {
            this.tileActivity = stillLoading;
            this._updateBodyClass();
        }
    },

    /**
     * Update body classes to match current activity
     */
    _updateBodyClass: function() {
        var body = $('body'),
            hasAjaxClass = body.hasClass(this.options.ajaxActivityClass),
            hasTileClass = body.hasClass(this.options.ajaxActivityClass),
            hasActivityClass = body.hasClass(this.options.activityClass);

        if(this.ajaxActivity !== hasAjaxClass) {
            body.toggleClass(this.options.ajaxActivityClass);
        }

        if(this.tileActivity !== hasTileClass) {
            body.toggleClass(this.options.tileActivityClass);
        }

        if((this.tileActivity || this.ajaxActivity) !== hasActivityClass) {
            body.toggleClass(this.options.activityClass);
        }
    }
});

})(jQuery);

