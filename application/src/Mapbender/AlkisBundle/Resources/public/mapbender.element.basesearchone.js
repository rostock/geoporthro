(function ($) {
    $.widget("mapbender.mbBaseSearchOne", {
        options: {
            timeoutDelay: 300,
            timeoutId: null,
            buffer: 1.0,
            dataSrs: 'EPSG:25833',
            spatialSearchSrs: 'EPSG:4326'
        },
        hilfetexte: {
            allgemein: 'Die Suche startet automatisch während der Eingabe. Sie können Ihre Suche über folgende Arten von Eingaben gestalten:<br/><br/><ul class="hilfetexte-liste">',
            addr: '<li>→ Ortsteilname [Beispiele: <span>schmarl</span> oder <span>brinckmans</span>]</li><li>→ Straßenname [Beispiele: <span>wagner</span> oder <span>holbei</span>]</li><li>→ Adresse (Straße mit Hausnummer und eventuellem Hausnummernzusatz) [Beispiele: <span>riga 19</span> oder <span>löns 14a</span>]</li></ul><br/>Resultate können Ortsteile, Straßen, Adressen (Straßen mit Hausnummer und eventuellem Hausnummernzusatz) und historische Adressen (Straßen mit Hausnummer und eventuellem Hausnummernzusatz sowie Angabe des Datums, an dem die Adresse historisch wurde) sein, jeweils gekennzeichnet durch ein vorangestelltes sprechendes Icon.',
            eigen: '<li>→ Vorname [Beispiel: <span>jürg</span>]</li><li>→ Nachname [Beispiel: <span>schmi</span>]</li><li>→ Kombination aus Vor- und Nachname (Reihenfolge egal) [Beispiel: <span>schmi jürg</span>]</li><li>→ Bezeichnung (bei Firmen, Organisationen etc.) [Beispiel: <span>carit</span>]</li></ul>',
            flur: '<li>→ Gemarkungsschlüssel [Beispiel: <span>2218</span>]</li><li>→ Gemarkungsname [Beispiel: <span>kasseb</span>]</li><li>→ Flur als Kombination aus Gemarkungsschlüssel und Flurnummer [Beispiel: <span>2222 3</span>]</li><li>→ Flur als Kombination aus Gemarkungsname und Flurnummer [Beispiel: <span>evershagen 3</span>]</li><li>→ Flurstück als Kombination aus Gemarkungsschlüssel oder Gemarkungsname und Flurnummer, Zähler und Nenner [Beispiele: <span>2232 1 461</span> oder <span>2232 1 160/2</span> oder <span>krummen 1 461</span> oder <span>krummen 1 160/2</span>]</li></ul><br/>Resultate können Gemarkungen, Fluren und Flurstücke sein, jeweils gekennzeichnet durch ein vorangestelltes sprechendes Icon.',
            grund: '<li>→ Grundbuchbezirksname [Beispiel: <span>rosto</span>]</li><li>→ Grundbuchbezirksnummer [Beispiel: <span>2250</span>]</li><li>→ Grundbuchblattnummer [Beispiel: <span>18305</span>]</li><li>→ Kombination aus Grundbuchbezirks- und Grundbuchblattnummer (Reihenfolge egal) [Beispiel: <span>2250 18305</span>]</li></ul>',
            schiffe: '<li>→ Bezeichnung [Beispiele: <span>jantje</span> oder <span>PHOEN</span>]</li><li>→ Typ [Beispiele: <span>kutter</span> oder <span>Yacht</span>]</li><li>→ vollständiges Baujahr [Beispiel: <span>1980</span>]</li><li>→ Liegeplatz (Kurzbezeichnung) [Beispiele: <span>WMD</span> oder <span>rsc</span>]</li><li>→ Liegeplatz (Langbezeichnung) [Beispiel: <span>warnemü</span>]</li></ul>'
        },
        line_style: {
            'strokeColor': '#cc00ff',
            'fillColor': '#cc00ff',
            'strokeWidth': '3',
            'fillOpacity': '0.1'
        },
        point_style: {
            'strokeColor': '#cc00ff',
            'fillColor': '#cc00ff',
            'strokeWidth': '3',
            'fillOpacity': '0.1',
            'pointRadius': '10'
        },
        geomFeature: null,
        foundedFeature: null,
        mbMap: null,
        alkisInfoDialog: null,
        mobile: null,
        _create: function () {
            var self = this;
            if (!Mapbender.checkTarget("Search", this.options.target)) {
                return;
            }
            if (!Mapbender.checkTarget("AlkisInfo", this.options.alkisinfo)) {
                return;
            }
            Mapbender.elementRegistry.onElementReady(this.options.target, $.proxy(self._setup, self));
            Mapbender.elementRegistry.onElementReady(this.options.alkisinfo, $.proxy(self._setupAlkisinfo, self));
            // falls mobile Oberfläche...
            if ($('#mobilePane').length) {
                mobile = true;
            }
             // ansonsten...
            else {
                mobile = false;
            }
        },
        /**
         * Initializes the wmc handler
         */
        _setup: function () {
            var self = this;
            this.mbMap = $('#' + this.options.target).data('mapbenderMbMap');
            this.dataSrsProj = Mapbender.Model.getProj(this.options.dataSrs);
            $('input', this.element).on('keyup', $.proxy(self._findOnKeyup, self));
            
            // falls mobile Oberfläche...
            if (mobile === true) {
                // Button zum Zurücksetzen umgestalten
                $('#removeResults', this.element).empty();
                $('#removeResults', this.element).removeClass('button');
                $('#removeResults', this.element).removeClass('critical');
                // Suchfenster schließen bei Klick auf Resultat
                $('#removeResults', this.element).on('click', $.proxy(self._resetSearch, self));
                $('#clear-search', this.element).on('click', $.proxy(self._clearSearch, self));
                $(document).on('click', ' .clickable', $.proxy(self._closeMobilePane, self));
                $(document).on('click', "#" + this.element.attr('id') + ' .document', $.proxy(self._zoom, self));
                $(document).on('click', "#" + this.element.attr('id') + ' .document > i', $.proxy(self._zoom, self));
                $(document).on('click', "#" + this.element.attr('id') + ' .document > small', $.proxy(self._zoom, self));
                $(document).on('click', "#" + this.element.attr('id') + ' div[data-page]', $.proxy(self._changePage, self));
                $(document).on('change', "#" + this.element.attr('id') + " select", $.proxy(self._changeSearchType, self));
                $(document).on('click', "#" + this.element.attr('id') + ' .eigen', $.proxy(self._showEigenInfo, self));
                $(document).on('click', "#" + this.element.attr('id') + ' .grund', $.proxy(self._showGrundInfo, self));
            }
             // ansonsten...
            else {
                $('#removeResults', this.element).on('click', $.proxy(self._resetSearch, self));
                $('#clear-search', this.element).on('click', $.proxy(self._clearSearch, self));
                $(document).on('click', "#" + this.element.attr('id') + ' .document', $.proxy(self._zoom, self));
                $(document).on('click', "#" + this.element.attr('id') + ' .document > i', $.proxy(self._zoom, self));
                $(document).on('click', "#" + this.element.attr('id') + ' .document > small', $.proxy(self._zoom, self));
                $(document).on('click', "#" + this.element.attr('id') + ' .document', $.proxy(self._highlightOn, self));
                $(document).on('mouseover', "#" + this.element.attr('id') + ' .document', $.proxy(self._highlightOn, self));
                $(document).on('mouseover', "#" + this.element.attr('id') + ' .document > i', $.proxy(self._highlightOn, self));
                $(document).on('mouseover', "#" + this.element.attr('id') + ' .document > small', $.proxy(self._highlightOn, self));
                $(document).on('mouseout', "#" + this.element.attr('id') + ' .document', $.proxy(self._highlightOff, self));
                $(document).on('mouseout', "#" + this.element.attr('id') + ' .document > i', $.proxy(self._highlightOff, self));
                $(document).on('mouseout', "#" + this.element.attr('id') + ' .document > small', $.proxy(self._highlightOff, self));
                $(document).on('click', "#" + this.element.attr('id') + ' div[data-page]', $.proxy(self._changePage, self));
                $(document).on('change', "#" + this.element.attr('id') + " select", $.proxy(self._changeSearchType, self));
                $(document).on('click', "#" + this.element.attr('id') + ' .eigen', $.proxy(self._showEigenInfo, self));
                $(document).on('click', "#" + this.element.attr('id') + ' .grund', $.proxy(self._showGrundInfo, self));
            }

            this._showSearch();
        },
        _closeMobilePane: function (e) {
            $('#mobilePaneClose').click();
        },
        _showSearch: function () {
            var search = $('#search-select', this.element).val();
            $('.removeResultsButton', this.element).removeClass('hidden');
            $('.search', this.element).removeClass('hidden');
            $('#search', this.element).val('');
            $('#searchResults', this.element).remove();
            if (search === 'addr') {
                $('.basesearchonecontent').html(this.hilfetexte.allgemein.concat(this.hilfetexte.addr));
            } else if (search === 'eigen') {
                $('.basesearchonecontent').html(this.hilfetexte.allgemein.concat(this.hilfetexte.eigen));
            } else if (search === 'flur') {
                $('.basesearchonecontent').html(this.hilfetexte.allgemein.concat(this.hilfetexte.flur));
            } else if (search === 'grund') {
                $('.basesearchonecontent').html(this.hilfetexte.allgemein.concat(this.hilfetexte.grund));
            } else if (search === 'schiffe') {
                $('.basesearchonecontent').html(this.hilfetexte.allgemein.concat(this.hilfetexte.schiffe));
            }
        },
        _clearSearch: function () {
            $('#search', this.element).val('');
            $('#searchResults', this.element).remove();
            $('#page', this.element).val(1);
            $('#search', this.element).val('');
            $('#searchResults', this.element).remove();
            var search = $('#search-select', this.element).val();
            if (search === 'addr') {
                $('.basesearchonecontent').html(this.hilfetexte.allgemein.concat(this.hilfetexte.addr));
            } else if (search === 'eigen') {
                $('.basesearchonecontent').html(this.hilfetexte.allgemein.concat(this.hilfetexte.eigen));
            } else if (search === 'flur') {
                $('.basesearchonecontent').html(this.hilfetexte.allgemein.concat(this.hilfetexte.flur));
            } else if (search === 'grund') {
                $('.basesearchonecontent').html(this.hilfetexte.allgemein.concat(this.hilfetexte.grund));
            } else if (search === 'schiffe') {
                $('.basesearchonecontent').html(this.hilfetexte.allgemein.concat(this.hilfetexte.schiffe));
            }
        },
        _resetSearch: function () {
            this._highlightOffAll();
            $('#page', this.element).val(1);
            $('#search', this.element).val('');
            $('#searchResults', this.element).remove();
            var search = $('#search-select', this.element).val();
            if (search === 'addr') {
                $('.basesearchonecontent').html(this.hilfetexte.allgemein.concat(this.hilfetexte.addr));
            } else if (search === 'eigen') {
                $('.basesearchonecontent').html(this.hilfetexte.allgemein.concat(this.hilfetexte.eigen));
            } else if (search === 'flur') {
                $('.basesearchonecontent').html(this.hilfetexte.allgemein.concat(this.hilfetexte.flur));
            } else if (search === 'grund') {
                $('.basesearchonecontent').html(this.hilfetexte.allgemein.concat(this.hilfetexte.grund));
            } else if (search === 'schiffe') {
                $('.basesearchonecontent').html(this.hilfetexte.allgemein.concat(this.hilfetexte.schiffe));
            }
        },
        _zoomToTarget: function (point) {
            var olMap = this.mbMap.map.olMap;
            $.proxy(this._zoomto(olMap), this);
            $.proxy(this._setCenter(point, olMap), this);
        },
        _zoomto: function (map) {
            var mapscales = map.options.scales;
            var scalesSize = mapscales.length;

            var zoom = scalesSize / 2;
            if (scalesSize % 2 !== 0) {
                zoom = parseInt(zoom, 10);
            }
            map.zoomTo(zoom);
        },
        _setCenter: function (point, map) {
            var targetCoord = new OpenLayers.LonLat(point.x, point.y);
            map.setCenter(targetCoord);
            $.proxy(this._zoom(map), false);

        },
        _setupAlkisinfo: function () {
            this.alkisInfoDialog = $('#' + this.options.alkisinfo);
        },
        _alkisinfo: function (func, param) {
            $('#' + this.options.alkisinfo).mbAlkisInfo(func, param);
        },
        _showEigenInfo: function (e) {
            var self = this;
            var gmlId = $(e.currentTarget).attr('data-gmlid');
            self._alkisinfo('activateEigenSearchInfo', gmlId);
        },
        _showGrundInfo: function (e) {
            var self = this;
            var gmlId = $(e.currentTarget).attr('data-gmlid');
            self._alkisinfo('activateGrundSearchInfo', gmlId);
        },
        _changeSearchType: function (e) {
            var self = this;
            this._highlightOffAll();
            this._showSearch();
        },
        _changePage: function (e) {
            var self = this;
            $('#page', this.element).val($(e.target).data('page'));
            self._find();
        },
        _findOnKeyup: function (e) {
            var self = this;

            if (typeof self.options.timeoutId !== 'undefined') {
                window.clearTimeout(self.options.timeoutId);
            }

            self.options.timeoutId = window.setTimeout(function () {
                self.options.timeoutId = undefined;
                $('#page', self.element).val(1);
                self._find();
            }, self.options.timeoutDelay);
        },
        _find: function (terms) {
            var self = this;
            $.ajax({
                url: Mapbender.configuration.application.urls.element + '/' + this.element.attr('id') + '/search',
                type: 'POST',
                data: {
                    term: $('#search', self.element).val(),
                    page: $('#page', self.element).val(),
                    type: $("#" + $(self.element).attr('id') + " select").val(),
                },
                dataType: 'text',
                contetnType: 'text/html',
                context: this,
                success: this._findSuccess,
                error: this._findError
            });
            return false;
        },
        _findSuccess: function (response, textStatus, jqXHR) {
            $('div.basesearchonecontent', this.element).html(response);
        },
        _zoom: function (e) {
            var geom,
                    mapProj = Mapbender.Model.getCurrentProj();
            if ($(e.target).data('geom')) {
                geom = OpenLayers.Geometry.fromWKT($(e.target).data('geom'));
            } else if ($(e.target).data('x') && $(e.target).data('y')) {
                geom = new OpenLayers.Geometry.Point(parseFloat($(e.target).data('x')), parseFloat($(e.target).data('y')));
            } else if ($(e.target).parent().data('geom')) {
                geom = OpenLayers.Geometry.fromWKT($(e.target).parent().data('geom'));
            } else if ($(e.target).parent().data('x') && $(e.target).parent().data('y')) {
                geom = new OpenLayers.Geometry.Point(parseFloat($(e.target).parent().data('x')), parseFloat($(e.target).parent().data('y')));
            } else {
                return;
            }
            
            if (this.dataSrsProj.projCode !== mapProj.projCode) {
                geom = geom.transform(this.dataSrsProj, mapProj);
            }
            var buffer = this.options.buffer ? this.options.buffer : 1; // kilometer
            var geomExtent = Mapbender.Model.calculateExtent(geom, {w: buffer, h: buffer});
            var zoomLevel = this.mbMap.map.olMap.getZoomForExtent(geomExtent, false);
            var centroid = geom.getCentroid(true);
            this.mbMap.map.olMap.setCenter(new OpenLayers.LonLat(centroid.x, centroid.y), zoomLevel);
            // falls keine mobile Oberfläche...
            if (mobile === false) {
                if (this.foundedFeature) {
                    Mapbender.Model.highlightOff(this.foundedFeature);
                    this.foundedFeature = null;
                }
            }
            if (geom.CLASS_NAME === "OpenLayers.Geometry.Point") {
                var centroid = geom.getCentroid(true);
                var poi = {
                    position: new OpenLayers.LonLat(centroid.x, centroid.y),
                    label: ""
                };
                var foundedFeature = new OpenLayers.Feature.Vector(centroid, poi);
                foundedFeature.style = OpenLayers.Util.applyDefaults(this.point_style, OpenLayers.Feature.Vector.style["default"]);
            } else {
                var foundedFeature = new OpenLayers.Feature.Vector(geom);
                foundedFeature.style = OpenLayers.Util.applyDefaults(this.line_style, OpenLayers.Feature.Vector.style["default"]);

            }
            if (foundedFeature) {
                // falls mobile Oberfläche...
                if (mobile === true) {
                    // permanenete Markierung zunächst zulassen...
                    Mapbender.Model.highlightOn(foundedFeature, {clearFirst: false, "goto": false});
                    // ...nach ein paar Sekunden aber wieder löschen
                    function highlightOffAfterTimeout() {
                        Mapbender.Model.highlightOff(foundedFeature);
                    }
                    setTimeout(highlightOffAfterTimeout, 5000)
                // ansonsten...
                } else {
                    // permanenete Markierung
                    if (foundedFeature) {
                        this.foundedFeature = [foundedFeature];
                        Mapbender.Model.highlightOn(this.foundedFeature, {clearFirst: false, "goto": false});
                    }
                }
            } 
        },
        _highlightOn: function (e) {
            var geom,
                    mapProj = Mapbender.Model.getCurrentProj();
            if ($(e.target).data('geom')) {
                geom = OpenLayers.Geometry.fromWKT($(e.target).data('geom'));
            } else if ($(e.target).data('x') && $(e.target).data('y')) {
                geom = new OpenLayers.Geometry.Point(parseFloat($(e.target).data('x')), parseFloat($(e.target).data('y')));
            } else if ($(e.target).parent().data('geom')) {
                geom = OpenLayers.Geometry.fromWKT($(e.target).parent().data('geom'));
            } else if ($(e.target).parent().data('x') && $(e.target).parent().data('y')) {
                geom = new OpenLayers.Geometry.Point(parseFloat($(e.target).parent().data('x')), parseFloat($(e.target).parent().data('y')));
            } else {
                return;
            }

            if (this.dataSrsProj.projCode !== mapProj.projCode)
                geom = geom.transform(this.dataSrsProj, mapProj);
            this._highlightOff(e);
            if (Mapbender.Model.getMapExtent().toGeometry().intersects(geom)) {
                var geomFeature = new OpenLayers.Feature.Vector(geom);
                if (geomFeature) {
                    if (geom.CLASS_NAME === "OpenLayers.Geometry.Point") {
                        geomFeature.style = OpenLayers.Util.applyDefaults(this.point_style, OpenLayers.Feature.Vector.style["default"]);
                    } else {
                        geomFeature.style = OpenLayers.Util.applyDefaults(this.line_style, OpenLayers.Feature.Vector.style["default"]);
                    }
                    this.geomFeature = [geomFeature];
                    this.mbMap.highlightOn(this.geomFeature, {clearFirst: false, "goto": false});
                }
            }
        },
        _highlightOff: function (e) {
            if (this.geomFeature) {
                Mapbender.Model.highlightOff(this.geomFeature);
                this.geomFeature = null;
            }
        },
        _highlightOffAll: function (e) {
            if (this.geomFeature) {
                Mapbender.Model.highlightOff(this.geomFeature);
                this.geomFeature = null;
            }
            if (this.foundedFeature) {
                Mapbender.Model.highlightOff(this.foundedFeature);
                this.foundedFeature = null;
            }
        },
        _findError: function (response) {
            Mapbender.error(response);
        },
        _destroy: $.noop
    });

})(jQuery);
