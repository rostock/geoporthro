(function($){

    $.widget("mapbender.mbRedlining", {
        options: {
            target: null,
            display_type: 'dialog',
            auto_activate: false,
            deactivate_on_close: true,
            geometrytypes: ['point', 'line', 'polygon', 'rectangle', 'text'],
            paintstyles: {
                'strokeColor': '#ff0000',
                'fillColor': '#ff0000',
                'strokeWidth': '3',
                'pointRadius': '12'
            }
        },
        map: null,
        layer: null,
        activeControl: null,
        selectedFeature: null,
        geomCounter: 1,
        rowTemplate: null,
        _create: function(){
            if(!Mapbender.checkTarget("mbRedlining", this.options.target)) {
                return;
            }
            var self = this;
            this.elementUrl = Mapbender.configuration.application.urls.element + '/' + this.element.attr('id') + '/';
            Mapbender.elementRegistry.onElementReady(this.options.target, $.proxy(self._setup, self));
        },
        _setup: function(){
            this.map = $('#' + this.options.target).data('mapbenderMbMap').map.olMap;
            this.rowTemplate = this.element.find('.geometry-table tr').remove();
            var selectControl = this.map.getControlsByClass('OpenLayers.Control.SelectFeature');
            this.map.removeControl(selectControl[0]);
            if(this.options.auto_activate || this.options.display_type === 'element'){
                this.activate();
            }
            this._trigger('ready');
            this._ready();
        },
        defaultAction: function(callback){
            this.activate(callback);
        },
        activate: function(callback){
            this.callback = callback ? callback : null;
            
            var defaultStyle = new OpenLayers.Style($.extend({}, OpenLayers.Feature.Vector.style["default"], this.options.paintstyles));
            var styleMap = new OpenLayers.StyleMap({'default': defaultStyle}, {extendDefault: true});
            this.layer = new OpenLayers.Layer.Vector('Redlining', {styleMap: styleMap});
            this.map.addLayer(this.layer);
            if (this.options.display_type === 'dialog'){
                this._open();
            } else {
                this.element.removeClass('hidden');
            }
            $('.geometry-table tr', this.element).remove();
            $('.redlining-tool', this.element).on('click', $.proxy(this._newControl, this));
        },
        deactivate: function(){
            if (this.options.display_type === 'dialog'){
                this._close();
            }
            if (this.options.display_type === 'dialog' && this.options.deactivate_on_close){
                this.map.removeLayer(this.layer);
                this.callback ? this.callback.call() : this.callback = null;
            }
            $('.redlining-tool', this.element).off('click');
        },
        /**
         * deprecated
         * @param {array} callback
         */
        open: function(callback){
            this.activate(callback);
        },
        /**
         * deprecated
         */
        close: function(){
            this.deactivate();
        },
        _open: function(){
            var self = this;
            if(!this.popup || !this.popup.$element) {
                this.popup = new Mapbender.Popup2({
                    title: self.element.attr('data-title'),
                    draggable: true,
                    header: true,
                    modal: false,
                    closeButton: false,
                    closeOnESC: false,
                    content: self.element,
                    width: 500,
                    height: 380,
                    buttons: {
                        'cancel': {
                            label: Mapbender.trans('mb.core.redlining.dialog.btn.cancel'),
                            cssClass: 'button buttonCancel critical right',
                            callback: function(){
                                self.deactivate();
                            }
                        }
                    }
                });
                this.popup.$element.on('close', $.proxy(this.deactivate, this));
            } else {
                    this.popup.open(self.element);
            }
            this.element.removeClass('hidden');
        },
        _close: function(){
            if(this.popup) {
                this.element.addClass('hidden').appendTo($('body'));
                this._deactivateControl();
                if(this.popup.$element) {
                    this.popup.destroy();
                }
                this.popup = null;
            }
        },
        _newControl: function(e){
            var self = this;
            if($(e.target).hasClass('active') === true) {
                this._deactivateControl();
                return;
            }
            this._deactivateControl();
            $(e.target).addClass('active');
            switch(e.target.name)
            {
                case 'point':
                    this.activeControl = new OpenLayers.Control.DrawFeature(this.layer,
                            OpenLayers.Handler.Point, {
                                featureAdded: function(e){
                                    self._addToGeomList(e, Mapbender.trans('mb.core.redlining.geometrytype.point'));
                                }
                            });
                    break;
                case 'line':
                    this.activeControl = new OpenLayers.Control.DrawFeature(this.layer,
                            OpenLayers.Handler.Path, {
                                featureAdded: function(e){
                                    self._addToGeomList(e, Mapbender.trans('mb.core.redlining.geometrytype.line'));
                                }
                            });
                    break;
                case 'polygon':
                    this.activeControl = new OpenLayers.Control.DrawFeature(this.layer,
                            OpenLayers.Handler.Polygon, {
                                handlerOptions: {
                                    handleRightClicks: false
                                },
                                featureAdded: function(e){
                                    self._addToGeomList(e, Mapbender.trans('mb.core.redlining.geometrytype.polygon'));
                                }
                            });
                    break;
                case 'rectangle':
                    this.activeControl = new OpenLayers.Control.DrawFeature(this.layer,
                            OpenLayers.Handler.RegularPolygon, {
                                handlerOptions: {
                                    sides: 4,
                                    irregular: true,
                                    rightClick: false
                                },
                                featureAdded: function(e){
                                    self._addToGeomList(e, Mapbender.trans('mb.core.redlining.geometrytype.rectangle'));
                                }
                            });
                    break;
                case 'text':
                    $('#redlining-text-wrapper', this.element).removeClass('hidden');
                    this.activeControl = new OpenLayers.Control.DrawFeature(this.layer,
                            OpenLayers.Handler.Point, {
                                featureAdded: function(e){
                                    e.style = self._setFeatureStyle();
                                    self._addToGeomList(e, Mapbender.trans('mb.core.redlining.geometrytype.text'));
                                    self.layer.redraw();
                                }
                            });
                    break;
            }
            this.map.addControl(this.activeControl);
            this.activeControl.activate();

        },
        removeFeature: function(feature){
            this.layer.destroyFeatures([feature]);
        },
        _deactivateControl: function(){
            if(this.activeControl !== null) {
                this.activeControl.deactivate();
                this.activeControl.destroy();
                this.map.removeControl(this.activeControl);
                this.activeControl = null;
            }
            this._deactivateButton();
            $('#redlining-text-wrapper', this.element).addClass('hidden');
        },
        _deactivateButton: function(){
            $('.redlining-tool', this.element).removeClass('active');
        },
        _addToGeomList: function(feature, name){
            var self = this;
            var activeTool = $('.redlining-tool.active', this.element).attr('name');

            if(activeTool !== 'text') {
                name = name + ' ' + this.geomCounter;
                this.geomCounter++;
            }
            var row = this.rowTemplate.clone();
            row.attr("data-id", feature.id);
            $('.geometry-name', row).text(name);
            var $geomtable = $('.geometry-table', this.element);
            $geomtable.append(row);
            $('.geometry-remove', $geomtable).off('click');
            $('.geometry-remove', $geomtable).on('click', $.proxy(self._removeFromGeomList, self));
            $('.geometry-edit', $geomtable).off('click');
            $('.geometry-edit', $geomtable).on('click', $.proxy(self._modifyFeature, self));
            $('.geometry-zoom', $geomtable).off('click');
            $('.geometry-zoom', $geomtable).on('click', $.proxy(self._zoomToFeature, self));
        },
        _removeFromGeomList: function(e){
            this._deactivateControl();
            var $tr = $(e.target).parents("tr:first");
            var feature = this.layer.getFeatureById($tr.attr('data-id'));
            this.removeFeature(feature);
            $tr.remove();
            this.geomCounter--;
        },
        _modifyFeature: function(e){
            this._deactivateControl();
            var feature = this.layer.getFeatureById($(e.target).parents("tr:first").attr('data-id'));
            this.activeControl = new OpenLayers.Control.ModifyFeature(this.layer, {standalone: true});
            this.map.addControl(this.activeControl);
            this.activeControl.selectFeature(feature);
            this.activeControl.activate();
        },
        _zoomToFeature: function(e){
            this._deactivateControl();
            var feature = this.layer.getFeatureById($(e.target).parents("tr:first").attr('data-id'));
            var featureExtentWithBuffer = Mapbender.Model.calculateExtent(feature.geometry, {w: 300, h: 300});
            this.map.zoomToExtent(featureExtentWithBuffer, true);
        },
        _setFeatureStyle: function(){
            var style = OpenLayers.Util.applyDefaults(null, OpenLayers.Feature.Vector.style['default']);
            var label = $('input[name=label-text]', this.element).val();
            style.label = label;
            style.labelAlign = 'lb';
            style.labelXOffset = 0;
            style.pointRadius = 12;
            style.fillOpacity = 0;
            style.strokeOpacity = 0;
            style.fontColor = "#ff0000";
            style.fontSize = "16px";
            style.fontFamily = "Trebuchet MS, sans-serif";
            style.fontWeight = "bold";
            style.activate = function(){
                style.fillOpacity = 1;
            };
            return style;
        },
        /**
         *
         */
        ready: function(callback){
            if(this.readyState === true) {
                callback();
            } else {
                this.readyCallbacks.push(callback);
            }
        },
        /**
         *
         */
        _ready: function(){
            for(callback in this.readyCallbacks) {
                callback();
                delete(this.readyCallbacks[callback]);
            }
            this.readyState = true;
        },
    });

})(jQuery);
