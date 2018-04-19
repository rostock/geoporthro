# Changelog
* **v3.0.x.x** - 201x-xx-xx

#Release
* **v3.0.4.0** - 2014-09-12
    - Switched to MIT license
    - Added parameter group for element BaseSourceSwitcher to be able to create a menu bar with groupname as title of the menu
    - Added accordion container for SidePane
    - Upgrade to Symfony 2.3
    - fixed validate WMS GetCapabilities document
    - fixed layer sorting at backend
    - fixed application copy
    - Added spanish translation (thanks to Mario Torres)
    - Added custom buffer/ratio for gridded layers (requires DB update)
    - Added element for generic HTML
    - Added custom CSS editor for applications (requires DB update)
    - fixed element saving bug
    - use degrees as unit fallback when none are provided by SRS definition
    - added screenshot management to application editing
    - enhanced CSS URL rewrite to be more dynamic depending on apps URL rewriting
    - patched OpenLayers with unreleased upstream fixes
    - enhanced GPS position element (remove marker on disable, position averaging)
    - properly remove proxy from WMS URLs before printing
    - display WMS metadata valdiation results
    - fixed application copy bugs
    - region properties added (normal/tabs/accordion)
    - patched OpenLayers 2.13 with fixes for proper IE8-10 behavior
    - prevent unsaved element forms to be closed accidentally
    - added CSS editing to application editing
    - added generic HTML element
    - Codemirror updated
    - workaround weird fileinfo behavior during print
    - added scalebar to print
    - enhanced SimpleSearch preprocessing with regex and sensible Solr defaults
    - travis-ci.org integration for automated testing
    - SearchRouter enhancements (z-index, results counter)
    - GPS position can make the map follow it's position
    - More WMS metadata validation, handling and displaying
    - FeatureInfo can have custom data handlers
    - configurable buffer/ratio property for WMS instances
    - print using layer opacity
    - SearchRouter feature styles can be configured
    - SearchRouter: autocomplete enhancements, feature garbage collection, more configration options
    - responsive application templates
    - added session entity
    - delete ACL with delete
    - region properties (tabs/accordion)
    - fixed application copy bugs
    - popups can prevent close when unsaved data
    - dynamic user profile insertion
    - enhanced autocomplete with query term preprocessing
    - fixed popup focus behavior
    - travis-ci.org integration for automated tests
    - external user/group providers can be configured instead of FOM
    - Enhanced exception handling
    - Fix cURL behavior when closing connections
    - Added user-agent "OWSproxy3"
    - Added request/response logging
    - Oracle support for logging
    - import/export of applications/sources
    - https://github.com/mapbender/mapbender/milestones/3.0.4.0

* **v3.0.3.2** - 2014-04-04
    - Added HttpBasicAuthListener to WMS loading for for safely setting the auth header
    - WMSProxy passes auth challenges (HTTP 401) down to client
    - fixed fullscreen alternative Template
    - SearchRouter reset results in map
    - https://github.com/mapbender/mapbender/issues?milestone=14

* **v3.0.3.1** - 2014-03-20
    - Disabled WMS validation as it renders many services unusable.
    - Made WMC editor resizable and taller

* **v3.0.3.0** - 2014-03-17
    - Added function for validate WMS GetCapabilities documents
    - ACL for Elements added
    - Parameter "BaseSource" for SourceInstances added
    - Closed XSS vulnerability which required admin permissions
    - Added cache for compiled static assets
    - new parameter mapbender.static_assets, defaults to true
    - new parameter mapbender.static_assets_path, defaults to web/css/application
    - Compiled assets get cached to the directory set with the aforementioned parameter
      - This directory needs to be cleared before packaging or updating.
      - This directory needs to be refreshed before packaging.
    - Element Legend: option 'noLegend' removed
    - Translation for en,de added
    - ZoomBar option component 'zoom_in_out' added
    - added cookie_secure: false and cookie_lifetime: 3600 to parameters.yml http://symfony.com/doc/2.1/reference/configuration/framework.html#cookie-lifetime
    - Enhancements for Search-Router für SQL-Suchen (Selectboxes, Distinct)
    - WMC Editor and LoaderWMSLoader Enhancement add WMS via link
    - Sketch to draw temporary objects
    - POI - Meetingpoint
    - Imageexport to generate png or jpg
    - Change WMS Collection via button (BaselayerSwitcher)
    - Print with overview
    - Print define optional fields
    - Print define replace pattern
    - Sidepane with different elements (chnage via button)
    - Layertree context menue to change opacity and to zoom to layer
    - Open application with parameters (f.e. position)


* **v3.0.2.0** - 2013-11-26
    - Signer for OwsProxy added
    - Properties for regions added
    - Sketch feature (circle) added
    - Update layertree changed
    - Funktion Model.changeLayerState added
    - LoadWms load declarative WMS added
    - Dispatcher for declarative Sources added
    - Dropdown lists are now scrollable
    - Micro designfixes
    - Search router design added
    - New button icons for wmc editor and loader added
    - console.* stubs
    - Proxy security: Only pass correctly signed URLs
    - Allow for multiple application YAML files

* **v3.0.1.1** - 2013-09-26
    - The development controller app_dev.php is limited to localhost again

* **v3.0.1.0** - 2013-09-12
    - Fixed visibility toggle for elements and layers
    - Hide sidepane if empty
    - Parameter/Service 'mapbender.proxy' removed
    - Parameter 'mapbender.uploads_dir' added
    - Application's directory added
    - Added wgs84 print
    - Added printclient parameter file_prefix
    - Added default action for elements
    - Splited `frontend/components.js` into `sidepane.js` and `tabcontainer.js`
    - Remove unused images references
    - New popup architecture
    - Add application dublication
    - Prepare `collection.js` for dynamic element properties (full support in next versions)
    - Fix some micro css bugs
    - Map scale bugs fixes
    - Move checkbox script into `checkbox.js`
    - Merge checkbox frontend and backend script
    - Move dropdown script into `dropdown.js`
    - Merge dropdown frontend and backend script
    - Fix some dropdown bugs
    - Fix some layertree css bugs
    - Fix some popup css bugs
    - Micro design fixes
    - Remove unused jQuery-UI CSS
    - Add more translation wraps
    - Add `widget_attribute_class` macro for forms
    - Element position moved from `mapbender_theme.scss` to `fullscreen.scss`
    - Add new frontend template - Fullscreen alternative
    - Frontend jQuery upgrade to 1.9.1/1.10.2 (jQuery UI)

* **v3.0.0.2** - 2013-07-19
    - Removed incorrect feature info function `create`
    - Set overlay `position` to `fixed`
    - PrintClient Admintype added
    - PrintClient Configuration Parameter changed
    - Instance view - order of `on` and `allow` changed
    - Disable WMCBundle - Available in the next versions
    - Parameter unitPrefix added to ScaleDisplay
    - normalize.css compressed
    - Popup decrease `max-height`
    - Forgot, Register success and error messages designed
    - Restructured login, forgot and success templates
    - Elements overview is sorted by asc
    - Wmsloader popup - set fix `width`
    - Design added to ScaleDisplay
    - Fixed manager logo positioning
    - Fixed design of print client and map forms
    - Fixed double *delete* label at layers and elements
    - Fixed html and body `height`
    - Fixed Firefox font bug
    - Fixed printclient tooltip bug
    - Fixed ScaleDisplay position bug
    - Added POI (0...n) and BBOX URL parameter handling
    - Fixed ACL creation during user creation (#52)
    - Fixed ACL creation during group creation (#53)
    - Enhanced ACL creation during service creation (#54)
    - Honor published attribute on YAML-defined applications (#42)

* **v3.0.0.1** - 2013-06-07

* **v3.0.0.0** - 2013-05-31
    - First version
