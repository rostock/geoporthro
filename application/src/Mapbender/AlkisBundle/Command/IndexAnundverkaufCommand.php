<?php
namespace Mapbender\AlkisBundle\Command;

use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\Output;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Mapbender\AlkisBundle\Component\ColognePhonetic;

use ARP\SolrClient2\SolrClient;

class IndexAnundverkaufCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
        ->setDescription('Reset\'s the solr index.')
        ->setName('hro:index:reset:anundverkauf');
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

        
        $type = 'anundverkauf';
        $phonetic = ColognePhonetic::singleton();
        
        $solr = new SolrClient(
            $this->getContainer()->getParameter('solr')
        );

        $solr->deleteByQuery('id:' . $type . '_' . '*');
        $solr->commit();

        $conn = $this->getContainer()->get('doctrine.dbal.hro_search_data_connection');

        $limit = 50;
        $offset = 0;
        $id = 0;

        $output->writeln('Indiziere An- und Verkauf fuer HRO-Suche nach An- und Verkauf ... ');


        $stmt = $conn->query('SELECT count(*) AS count FROM fachdaten_flurstuecksbezug.an_und_verkauf_regis_hro WHERE flaeche_im_flurstueck > 1');
        $result = $stmt->fetch();
        $count = intval($result['count']);
        $stmt = $conn->query('SELECT count(*) AS count FROM (SELECT uuid FROM fachdaten_flurstuecksbezug.an_und_verkauf_regis_hro WHERE flaeche > 1 GROUP BY uuid) AS tabelle');
        $result = $stmt->fetch();
        $count = $count + intval($result['count']);

        while ($offset < $count) {
            $stmt = $conn->query("
                SELECT
                 flurstueckskennzeichen,
                 aktenzeichen,
                 vorgangsart,
                 flaeche_im_flurstueck_formatiert AS flaeche,
                 ST_AsText(ST_Centroid(geometrie)) AS geom,
                 ST_AsText(geometrie) AS wktgeom
                  FROM fachdaten_flurstuecksbezug.an_und_verkauf_regis_hro
                   WHERE flaeche_im_flurstueck > 1
                UNION SELECT
                 NULL AS flurstueckskennzeichen,
                 aktenzeichen,
                 vorgangsart,
                 flaeche_formatiert AS flaeche,
                 ST_AsText(ST_Centroid(ST_Union(ST_MakeValid(geometrie)))) AS geom,
                 ST_AsText(ST_Union(ST_MakeValid(geometrie))) AS wktgeom
                  FROM fachdaten_flurstuecksbezug.an_und_verkauf_regis_hro
                   WHERE flaeche > 1
                    GROUP BY uuid, aktenzeichen, vorgangsart, flaeche_formatiert
                     ORDER BY aktenzeichen, flurstueckskennzeichen DESC
                      LIMIT " . $limit . " OFFSET " . $offset);

            while ($row = $stmt->fetch()) {
                list($x, $y) = $this->prepairPoint($row['geom']);

                $doc = $solr->newDocument();
                $doc->id = $type . '_' . ++$id;
                $doc->text = $this->concat(
                    $row['aktenzeichen'],
                    $row['vorgangsart'],
                    $row['flurstueckskennzeichen']
                );
                
                $doc->phonetic = $this->addPhonetic($this->concat(
                    $row['aktenzeichen'],
                    $row['vorgangsart'],
                    $row['flurstueckskennzeichen']
                ));

                $doc->label = "1".$row['aktenzeichen'].$row['vorgangsart'].$row['flurstueckskennzeichen'];

                $doc->json = json_encode(array(
                    'data'   => array(
                        'type'                    => $type,
                        'aktenzeichen'            => $row['aktenzeichen'],
                        'vorgangsart'             => $row['vorgangsart'],
                        'flurstueckskennzeichen'  => $row['flurstueckskennzeichen'],
                        'flaeche'                 => $row['flaeche']
                    ),
                    'x'      => $x,
                    'y'      => $y,
                    'geom'   => $row['wktgeom'],
                ));
                $doc->type = $type;

                $solr->addDocument($doc);
            }

            $solr->commit();
            $offset += $limit;

            $output->writeln("\t" . (
                $offset > $count ? $count : $offset
            ) . " von " . $count . " indiziert.");
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
