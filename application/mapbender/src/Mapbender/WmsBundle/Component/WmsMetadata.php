<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace Mapbender\WmsBundle\Component;

use Mapbender\CoreBundle\Component\SourceMetadata;
use Mapbender\WmsBundle\Entity\WmsInstance;
use Mapbender\WmsBundle\Entity\WmsLayerSource;
use Symfony\Component\Yaml\Yaml;

/**
 * 
 * @inheritdoc
 * @author Paul Schmidt
 */
class WmsMetadata extends SourceMetadata
{

    protected $instance;

    public function __construct(WmsInstance $instance)
    {
        parent::__construct();
        $this->instance = $instance;
    }
    
    private function prepareData($itemName)
    {
        $src = $this->instance->getSource();
        
        # eigene WMS
        if (strpos(SourceMetadata::getNotNull($src->getOriginUrl()), "geo.sv.rostock.de") !== false) {
            # mit Parametern
            if (strpos(SourceMetadata::getNotNull($src->getOriginUrl()), "?") !== false) {
                # Parameter entfernen
                $originUrl = substr(SourceMetadata::getNotNull($src->getOriginUrl()), 0, strpos(SourceMetadata::getNotNull($src->getOriginUrl()), "?"));
            }
            # ohne Parameter
            else {
                # URL unverändert übernehmen
                $originUrl = SourceMetadata::getNotNull($src->getOriginUrl());
            }
            # Verbindung zur Datenbank von Geolotse.HRO aufbauen
            $yaml = Yaml::parse(file_get_contents(__DIR__ . '/../../../../../app/config/parameters.yml'));
            $connection_host = $yaml['parameters']['metadata_host'];
            $connection_dbname = $yaml['parameters']['metadata_dbname'];
            $connection_user = $yaml['parameters']['metadata_user'];
            $connection_password = $yaml['parameters']['metadata_password'];
            $connection = pg_connect("host=" . $connection_host . " dbname=" . $connection_dbname . " user=" . $connection_user . " password=" . $connection_password);
            # verbundene URLs holen
            pg_prepare("", "SELECT CASE WHEN \"group\" = 'WMS' THEN '1' || \"group\" WHEN \"group\" = 'WFS' THEN '3' || \"group\" WHEN \"group\" = 'INSPIRE View Service' THEN '5' || \"group\" WHEN \"group\" = 'INSPIRE Download Service' THEN '7' || \"group\" ELSE \"group\" END AS typ, link FROM links WHERE category = 'geoservice' AND parent_id in (SELECT parent_id FROM links WHERE link = '$originUrl') UNION SELECT DISTINCT CASE WHEN s.target = 'metadata' AND l1.\"group\" = 'INSPIRE Download Service' THEN '8metadata_dls' WHEN s.target = 'metadata' AND l1.\"group\" = 'INSPIRE View Service' THEN '6metadata_vs' WHEN s.target = 'metadata' AND l1.\"group\" = 'WFS' THEN '4metadata_wfs' WHEN s.target = 'metadata' AND l1.\"group\" = 'WMS' THEN '2metadata_wms' ELSE s.target END AS typ, s.link FROM sublinks s, links_sublinks ls, links l1, links l2 WHERE s.target IN ('metadata', 'opendata') AND s.id = ls.sublink_id AND (ls.link_id = l1.id OR ls.link_id = l1.parent_id) AND l1.parent_id = l2.parent_id AND l2.link = '$originUrl' ORDER BY 1, 2");
            $result = pg_execute("", array());
            $urls = array();
            while ($row = pg_fetch_assoc($result)) {
                $urls[] = array('typ' => $row['typ'], 'link' => $row['link']);
            }
            # Beschreibung holen
            pg_prepare("", "SELECT description FROM links WHERE link = '$originUrl' LIMIT 1");
            $result = pg_execute("", array());
            while ($row = pg_fetch_assoc($result)) {
                $description = $row['description'];
            }
            # Titel holen
            pg_prepare("", "SELECT title FROM links WHERE link = '$originUrl' LIMIT 1");
            $result = pg_execute("", array());
            while ($row = pg_fetch_assoc($result)) {
                $title = $row['title'];
            }
            pg_close($connection);
        }
        # andere WMS
        else {
            # mit Parametern
            if (strpos(SourceMetadata::getNotNull($src->getOriginUrl()), "apabilitie") !== false) {
                # URL unverändert übernehmen
                $originUrl = SourceMetadata::getNotNull($src->getOriginUrl());
            }
            # ohne Parameter
            else {
                # bei UMN-MapServer-URL
                if (strpos(SourceMetadata::getNotNull($src->getOriginUrl()), "map=") !== false) {
                    # Parameter hinzufügen
                    $originUrl = SourceMetadata::getNotNull($src->getOriginUrl()) . "&service=WMS&version=1.3.0&request=GetCapabilities";
                }
                # ansonsten
                else {
                    # Parameter hinzufügen
                    $originUrl = SourceMetadata::getNotNull($src->getOriginUrl()) . "?service=WMS&version=1.3.0&request=GetCapabilities";
                }
            }
        }
        
        if ($this->getUseCommon()) {
            $source_items = array();
            if ($title)
                $source_items[] = array("title" => $title);
            else
                $source_items[] = array("title" => SourceMetadata::getNotNull($src->getTitle()));
            if ($description)
                $source_items[] = array("description" => $description);
            else
                $source_items[] = array("description" => SourceMetadata::getNotNull($src->getDescription()));
            if ($urls) {
                foreach ($urls as $url) {
                    if ($url['typ'] === 'GeoRSS') {
                        $source_items[] = array("georssUrl" => $url['link']);
                    } elseif ($url['typ'] === '7INSPIRE Download Service') {
                        $source_items[] = array("dlsUrl" => $url['link']);
                    } elseif ($url['typ'] === '5INSPIRE View Service') {
                        $source_items[] = array("vsUrl" => $url['link']);
                    } elseif ($url['typ'] === 'SOS') {
                        $source_items[] = array("sosUrl" => $url['link']);
                    } elseif ($url['typ'] === 'TMS') {
                        $source_items[] = array("tmsUrl" => $url['link']);
                    } elseif ($url['typ'] === 'WCS') {
                        $source_items[] = array("wcsUrl" => $url['link']);
                    } elseif ($url['typ'] === '3WFS') {
                        $source_items[] = array("wfsUrl" => $url['link']);
                    } elseif ($url['typ'] === '1WMS') {
                        $source_items[] = array("wmsUrl" => $url['link']);
                    } elseif ($url['typ'] === 'WMS-C') {
                        $source_items[] = array("wmscUrl" => $url['link']);
                    } elseif ($url['typ'] === 'WMTS') {
                        $source_items[] = array("wmtsUrl" => $url['link']);
                    } elseif ($url['typ'] === 'opendata') {
                        $source_items[] = array("opendataUrl" => $url['link']);
                    }
                }
            } else {
                $source_items[] = array("wmsUrl" => $originUrl);
            }
            $this->addMetadataSection(SourceMetadata::$SECTION_COMMON, $source_items);
        }
        
        if ($this->getUseUseConditions()) {
            $tou_items = array();
            $tou_items[] = array("accessconstraints" => SourceMetadata::getNotNull($src->getAccessConstraints()));
            $tou_items[] = array("fees" => SourceMetadata::getNotNull($src->getFees()));
            $this->addMetadataSection(SourceMetadata::$SECTION_USECONDITIONS, $tou_items);
        }

        if ($this->getUseItems() && $itemName !== '') {
            $layer = null;
            foreach ($this->instance->getLayers() as $layerH) {
                if ($layerH->getSourceItem()->getName() === $itemName) {
                    $layer = $layerH;
                    break;
                }
            }
            $layer_items = array();
            if ($layer) {
                $layer_items = $this->prepareLayers($layer);
            }
            $this->addMetadataSection(SourceMetadata::$SECTION_ITEMS, $layer_items);
        }
        
        if ($this->getUseContact()) {
            $contact = $src->getContact();
            $contact_items = array();
            $contact_items[] = array("organization" => SourceMetadata::getNotNull($contact->getOrganization()));
            $contact_items[] = array("voiceTelephone" => SourceMetadata::getNotNull($contact->getVoiceTelephone()));
            $contact_items[] = array("facsimileTelephone" => SourceMetadata::getNotNull($contact->getFacsimileTelephone()));
            $contact_items[] = array("electronicMailAddress" => SourceMetadata::getNotNull($contact->getElectronicMailAddress()));
            $contact_items[] = array("address" => SourceMetadata::getNotNull($contact->getAddress()));
            $contact_items[] = array("addressPostCode" => SourceMetadata::getNotNull($contact->getAddressPostCode()));
            $contact_items[] = array("addressCity" => SourceMetadata::getNotNull($contact->getAddressCity()));
            $this->addMetadataSection(SourceMetadata::$SECTION_CONTACT, $contact_items);
        }
    }
    
    private function prepareLayers($layer)
    {
        $layer_items = array();
        $layer_items[] = array("title" => SourceMetadata::getNotNull($layer->getSourceItem()->getTitle()));
        $layer_items[] = array("description" => SourceMetadata::getNotNull($layer->getSourceItem()->getAbstract()));
        $name = SourceMetadata::getNotNull($layer->getSourceItem()->getName());

        # Metadatenbank
        $yaml = Yaml::parse(file_get_contents(__DIR__ . '/../../../../../app/config/parameters.yml'));
        $connection_host = $yaml['parameters']['more_metadata_host'];
        $connection_dbname = $yaml['parameters']['more_metadata_dbname'];
        $connection_user = $yaml['parameters']['more_metadata_user'];
        $connection_password = $yaml['parameters']['more_metadata_password'];
        $connection = pg_connect("host=" . $connection_host . " dbname=" . $connection_dbname . " user=" . $connection_user . " password=" . $connection_password);
        $src = $this->instance->getSource();
        $originUrl = SourceMetadata::getNotNull($src->getOriginUrl());
        pg_prepare("", "
        SELECT
         rf,
         currentness_data,
         source_place_data,
         source_data,
         source_mail_data
          FROM (
           SELECT
            rf, currentness_data, array_to_string(array_agg(source_place_data), '~') AS source_place_data, array_to_string(array_agg(source_data), '~') AS source_data, array_to_string(array_agg(source_mail_data), '~') AS source_mail_data
             FROM (
              SELECT DISTINCT
               2 AS rf,
               to_char(r.last_update, 'DD.MM.YYYY') AS currentness_data,
               o.title AS source_place_data,
               c.first_name || ' ' || c.last_name AS source_data,
               c.email AS source_mail_data
                FROM gdihrometadata_service s
                JOIN gdihrometadata_service_repositories s_r ON s.id = s_r.service_id
                JOIN gdihrometadata_repository r ON s_r.repository_id = r.id AND r.connection_info ~ regexp_replace('$name', '.*\.', '')
                JOIN gdihrometadata_repository_authors r_a ON r.id = r_a.repository_id
                JOIN gdihrometadata_contact c ON r_a.contact_id = c.id
                LEFT JOIN gdihrometadata_organization o ON c.organization_id = o.id
                 WHERE s.link = '$originUrl'
             ) AS x
              GROUP BY rf, currentness_data
           UNION SELECT
            rf, currentness_data, array_to_string(array_agg(source_place_data), '~') AS source_place_data, array_to_string(array_agg(source_data), '~') AS source_data, array_to_string(array_agg(source_mail_data), '~') AS source_mail_data
             FROM (
              SELECT DISTINCT
               2 AS rf,
               to_char(r.last_update, 'DD.MM.YYYY') AS currentness_data,
               o.title AS source_place_data,
               c.first_name || ' ' || c.last_name AS source_data,
               c.email AS source_mail_data
                FROM gdihrometadata_service s
                JOIN gdihrometadata_service_repositories s_r ON s.id = s_r.service_id
                JOIN gdihrometadata_repository r ON s_r.repository_id = r.id
                JOIN gdihrometadata_repository_authors r_a ON r.id = r_a.repository_id
                JOIN gdihrometadata_contact c ON r_a.contact_id = c.id
                LEFT JOIN gdihrometadata_organization o ON c.organization_id = o.id
                 WHERE s.link = '$originUrl'
             ) AS y
              GROUP BY rf, currentness_data
          ) AS tabelle
           ORDER BY rf
           LIMIT 1
        ");
        $result = pg_execute("", array());
        while ($row = pg_fetch_assoc($result)) {
            $currentnessData = $row["currentness_data"];
            $sourcePlaceData = $row["source_place_data"];
            $parts = explode('~', $sourcePlaceData);
            $uniqueParts = array_unique($parts);
            $sourcePlaceData = implode('~', $uniqueParts);
            if ($row["source_data"] && $row["source_mail_data"] && strpos($row["source_data"], "Draheim") === false) {
                $sourceData = $row["source_data"];
                $sourceMailData = $row["source_mail_data"];
            }
        }
        pg_close($connection);
        
        if ($currentnessData) {
            if($layer->getSublayer()->count() == 0) {
                $layer_items[] = array("currentnessData" => $currentnessData);
            }
        }
        
        if ($sourcePlaceData) {
            if($layer->getSublayer()->count() == 0) {
                $layer_items[] = array("sourcePlaceData" => $sourcePlaceData);
            }
        }
        
        if ($sourceData) {
            if($layer->getSublayer()->count() == 0) {
                $layer_items[] = array("sourceData" => $sourceData);
            }
        }
        
        if ($sourceMailData) {
            if($layer->getSublayer()->count() == 0) {
                $layer_items[] = array("sourceMailData" => $sourceMailData);
            }
        }
        
        if($layer->getSublayer()->count() > 0) {
            $sublayers = array();
            foreach($layer->getSublayer() as $sublayer){
                $sublayers[] = $this->prepareLayers($sublayer);
            }
            $layer_items[] = array(SourceMetadata::$SECTION_SUBITEMS => $sublayers);
        }
        return $layer_items;
    }

    /**
     * @inheritdoc
     */
    public function render($templating, $itemName = null)
    {
        $this->prepareData($itemName);
        $content = $templating->render('MapbenderCoreBundle::metadata.html.twig',
            array('metadata' => $this->data, 'prefix' => 'mb.wms.metadata.section.'));
        return $content;
    }

}
