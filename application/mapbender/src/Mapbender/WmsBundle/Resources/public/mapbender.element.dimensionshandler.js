(function($) {
$.widget("mapbender.mbDimensionsHandler", {
options: {
},
    elementUrl: null,
    model: null,
    _create: function() {
    var self = this;
        if (!Mapbender.checkTarget("mbDimensionsHandler", this.options.target)) {
    return;
    }
    Mapbender.elementRegistry.onElementReady(this.options.target, $.proxy(self._setup, self));
    },
    _setup: function() {
    this.elementUrl = Mapbender.configuration.application.urls.element + '/' + this.element.attr('id') + '/';
        this.model = $("#" + this.options.target).data("mapbenderMbMap").getModel();
        for (dimId in this.options.dimensionsets) {
    this._setupGroup(dimId);
    }
    this._trigger('ready');
        this._ready();
        this.styleDimensionshandler();
    },
    _setupGroup: function(key) {
    var self = this;
        this.elementUrl = Mapbender.configuration.application.urls.element + '/' + this.element.attr('id') + '/';
        this.model = $("#" + this.options.target).data("mapbenderMbMap").getModel();
        var dimensionset = this.options.dimensionsets[key];
        var dimension = Mapbender.Dimension(dimensionset['dimension']);
        var def = dimension.partFromValue(dimension.getDefault()); // * 100;
        var valarea = $('#' + key + ' .dimensionset-value', this.element);
        $.each(dimensionset.group, function(idx, item) {
        var temp = self.model.findSource({
        origId: item.split('-')[0]
        });
            if (temp.length > 0 && temp[0].configuration.options.dimensions) {
        temp[0].configuration.options.dimensions = [];
        }
        });
        valarea.text(dimension.getDefault());
        $('#' + key + ' .mb-slider', this.element).slider({
    min: 0,
        max: 100,
        value: def * 100,
        slide: function(event, ui) {
        valarea.text(dimension.valueFromPart(ui.value / 100));
        },
        stop: function(event, ui) {
        $.each(dimensionset.group, function(idx, item) {
        var sources = self.model.findSource({
        origId: item.split('-')[0]
        });
            if (sources.length > 0) {
        var params = {};
            params[dimension.options.__name] = dimension.valueFromPart(ui.value / 100);
            self.model.resetSourceUrl(sources[0], {
            'add': params
            },
                true);
        }
        });
        }
    });
    },
    styleDimensionshandler: function() {
    var li = $(".mb-element-dimensionshandler").closest("li");
        if (li.closest("ul").hasClass("top") === true) {
    $(".mb-element-dimensionshandler").attr("style", "position:absolute;margin-top:-10px;");
        $(".dimensionset span").css("font-size", "14px");
        $(".mb-element-dimensionshandler div:eq(0)").attr("style", "margin-top: -10px; position: relative;display: block;top: 1px;");
    }
    else if (li.closest("ul").hasClass("bottom") === true) {
    $(".mb-element-dimensionshandler").css("line-height", "10px");
        $(".dimensionset span").css("font-size", "10px");
    }
    else {
    }
    },
        ready: function(callback) {
        if (this.readyState === true) {
        callback();
        } else {
        this.readyCallbacks.push(callback);
        }
        },
        _ready: function() {
        for (callback in this.readyCallbacks) {
        callback();
            delete(this.readyCallbacks[callback]);
        }
        this.readyState = true;
        },
        _destroy: $.noop
    });
        })(jQuery);
