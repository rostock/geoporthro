<?php
namespace Mapbender\AlkisBundle\Command;

use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\Output;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Mapbender\AlkisBundle\Component\ColognePhonetic;

use ARP\SolrClient2\SolrClient;

class IndexBaumCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
        ->setDescription('Reset\'s the solr index.')
        ->setName('hro:index:reset:baeume');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        error_reporting(E_ERROR);

        // Force garbage collector to do its job.
        gc_collect_cycles();

        // Turn SQL-Logger off.
        $this
            ->getContainer()
            ->get('doctrine')
            ->getManager()
            ->getConnection()
            ->getConfiguration()
            ->setSQLLogger(null);

        
        $type = 'baum';
        $phonetic = ColognePhonetic::singleton();
        
        $solr = new SolrClient(
            $this->getContainer()->getParameter('solr')
        );

        $solr->deleteByQuery('id:' . $type . '_' . '*');
        $solr->commit();

        $conn = $this->getContainer()->get('doctrine.dbal.hro_search_data_connection');

        // niedriges "Pagination"-Limit, da sonst Abfragen nicht vollständig durchlaufen und damit nicht alle Objekte aus der DB-Tabelle in den Index gelangen
        $limit = 200;
        $offset = 0;
        $id = 0;

        $output->writeln('Indiziere Baeume fuer HRO-Baumkatastersuche ... ');


        $stmt = $conn->query('SELECT count(*) AS count FROM fachdaten.baeume_hro');
        $result = $stmt->fetch();

        while ($offset < $result['count']) {
            $stmt = $conn->query("
                SELECT
                 uuid,
                 'nein' AS gefaellt,
                 CASE
                  WHEN bewirtschafter ~ 'Kataster' THEN '62'
                  WHEN bewirtschafter ~ 'Stadtgrün' THEN '67'
                  WHEN bewirtschafter ~ 'Eigenbetrieb Kommunale Objektbewirtschaftung' THEN '88'
                  ELSE bewirtschafter
                 END AS bewirtschafter,
                 gruenpflegebezirk AS bezirk,
                 nummer_gruenpflegeobjekt AS objektnummer,
                 regexp_replace(nummer_gruenpflegeobjekt, '\/', '', 'g') AS objektnummer_ohne_slashes,
                 regexp_replace(nummer_gruenpflegeobjekt, '\/', ' ', 'g') AS objektnummer_mit_leerzeichen,
                 replace(replace(bezeichnung_gruenpflegeobjekt, 'é', 'e'), 'è', 'e') AS objektbezeichnung,
                 nummer,
                 laufende_nummer,
                 ST_AsText(ST_Centroid(geometrie)) AS geom,
                 ST_AsText(geometrie) AS wktgeom
                  FROM fachdaten.baeume_regis_hro
                UNION SELECT
                 uuid,
                 'ja' AS gefaellt,
                 CASE
                  WHEN bewirtschafter ~ 'Kataster' THEN '62'
                  WHEN bewirtschafter ~ 'Stadtgrün' THEN '67'
                  WHEN bewirtschafter ~ 'Eigenbetrieb Kommunale Objektbewirtschaftung' THEN '88'
                  ELSE bewirtschafter
                 END AS bewirtschafter,
                 gruenpflegebezirk AS bezirk,
                 nummer_gruenpflegeobjekt AS objektnummer,
                 regexp_replace(nummer_gruenpflegeobjekt, '\/', '', 'g') AS objektnummer_ohne_slashes,
                 regexp_replace(nummer_gruenpflegeobjekt, '\/', ' ', 'g') AS objektnummer_mit_leerzeichen,
                 replace(replace(bezeichnung_gruenpflegeobjekt, 'é', 'e'), 'è', 'e') AS objektbezeichnung,
                 nummer,
                 laufende_nummer,
                 ST_AsText(ST_Centroid(geometrie)) AS geom,
                 ST_AsText(geometrie) AS wktgeom
                  FROM fachdaten.baeume_gefaellt_regis_hro
                   ORDER BY uuid
                    LIMIT " . $limit . " OFFSET " . $offset);

            while ($row = $stmt->fetch()) {
                list($x, $y) = $this->prepairPoint($row['geom']);

                $doc = $solr->newDocument();
                $doc->id = $type . '_' . ++$id;
                $doc->text = $this->concat(
                    $row['bewirtschafter'],
                    $row['bezirk'],
                    $row['objektnummer'],
                    $row['objektnummer_ohne_slashes'],
                    $row['objektnummer_mit_leerzeichen'],
                    $row['objektbezeichnung'],
                    $row['nummer'],
                    $row['laufende_nummer'],
                    $row['gefaellt']
                );
                
                $doc->phonetic = $this->addPhonetic($this->concat(
                    $row['bewirtschafter'],
                    $row['bezirk'],
                    $row['objektnummer'],
                    $row['objektnummer_ohne_slashes'],
                    $row['objektnummer_mit_leerzeichen'],
                    $row['objektbezeichnung'],
                    $row['nummer'],
                    $row['laufende_nummer'],
                    $row['gefaellt']
                ));

                $doc->label = "3".$row['bewirtschafter'].$row['bezirk'].$row['objektnummer'].$row['objektnummer_ohne_slashes'].$row['objektnummer_mit_leerzeichen'].$row['objektbezeichnung'].$row['nummer'].$row['laufende_nummer'].$row['gefaellt'];

                $doc->json = json_encode(array(
                    'data'   => array(
                        'type'              => 'baum',
                        'bewirtschafter'    => $row['bewirtschafter'],
                        'bezirk'            => $row['bezirk'],
                        'objektnummer'      => $row['objektnummer'],
                        'objektbezeichnung' => $row['objektbezeichnung'],
                        'nummer'            => $row['nummer'],
                        'laufende_nummer'   => $row['laufende_nummer'],
                        'gefaellt'          => $row['gefaellt']
                    ),
                    'x'      => $x,
                    'y'      => $y,
                    'geom'   => $row['wktgeom'],
                ));
                $doc->type = 'baumkataster';

                $solr->addDocument($doc);
            }

            $solr->commit();
            $offset += $limit;

            $output->writeln("\t" . (
                $offset > $result['count'] ? $result['count'] : $offset
            ) . " von " . $result['count'] . " indiziert.");
        }

        $solr->commit();
        $solr->optimize();

        $output->writeln('fertig');
    }

    public function concat()
    {
        return implode(" ", array_filter(func_get_args()));
    }

    public function prepairPoint($p)
    {
        if (substr($p, 0, 5) === 'POINT') {
            return explode(' ', substr($p, 6, -1));
        }

        return array('','');
    }

    public function addPhonetic($string)
    {
        $result   = "";
        $phonetic = ColognePhonetic::singleton();

        $array = array_filter(
            explode(" ", preg_replace("/[^a-zäöüßÄÖÜ0-9]/i", " ", $string))
        );

        foreach ($array as $val) {
            if (preg_match("/^[a-zäöüßÄÖÜ]+$/i", $val)) {
                $result .= " AND (" . $val. '^20 OR ' . $val . '*^15';
                
                if($val !== 'h') {
                    $result .= ' OR phonetic:' . $phonetic->encode($val) . '^1'
                    . ' OR phonetic:' . $phonetic->encode($val) . '*^0.5';
                }

                $result .= ")";
            } else {
                $result .= " AND (" . $val. '^2' . " OR " . $val . "*^1)";
            }
        }

        return substr(trim($result), 3);
    }
}
