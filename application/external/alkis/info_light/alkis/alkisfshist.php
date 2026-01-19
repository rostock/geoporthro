<?php
function fzerleg($fs) {
/*	Flurstückskennzeichen (20) zerlegen als lesbares Format (wie im Balken):
	Dies FS-Kennz-Format wird auch als Eingabe in der Navigation akzeptiert 
   ....*....1....*....2
   ll    fff     nnnn
     gggg   zzzzz    __
*/
	$fst=rtrim($fs,"_");	
	$zer=substr ($fst, 2, 4)."-".ltrim(substr($fst, 6, 3), "0")."-<b>".ltrim(substr($fst, 9, 5),"0");
	$nenn=ltrim(substr($fst, 14), "0");
	if ($nenn != "") {$zer.="/".$nenn;}
	$zer.="</b>";
	return $zer; 
}

function gemkg_name($gkey) {
// Schluessel wird uebergeben, Name in DB nachschlagen
	global $con;
	$sql = "SELECT bezeichnung FROM aaa_ogr.ax_gemarkung g WHERE g.gemarkungsnummer= $1 ;";
	$v=array($gkey);
	$res=pg_prepare("", $sql);
	$res=pg_execute("", $v);
	if (!$res) {echo "\n<p class='err'>Fehler bei Gemarkung.</p>";}
	$zgmk=0;
	while($row = pg_fetch_array($res)) { // eigentlich nur EINE
		$gmkg=$row["bezeichnung"];
		$zgmk++;
	}
	if ($zgmk == 0) {
		echo "\n<p class='err'>Gemarkung ".$gkey." ist unbekannt.</p>";
		return;
	}
	return $gmkg;
}

//session_start();
//import_request_variables("G"); // php 5.3 deprecated, php 5.4 entfernt
$cntget = extract($_GET);
require_once("alkis_conf_location.php");
if ($auth == "mapbender") {require_once($mapbender);}
include("alkisfkt.php");
if ($id == "j") {$idanzeige=true;} else {$idanzeige=false;}
$keys = isset($_GET["showkey"]) ? $_GET["showkey"] : "n";
if ($keys == "j") {$showkey=true;} else {$showkey=false;}
?>

<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
<head>
	<meta name="author" content="b600352" >
	<meta http-equiv="cache-control" content="no-cache">
	<meta http-equiv="pragma" content="no-cache">
	<meta http-equiv="expires" content="0">
	<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
	<title>Flurstückshistorie</title>
	<link rel="stylesheet" type="text/css" href="alkisauszug.css">
	<link rel="stylesheet" type="text/css" href="css-treeview.css">
	<link rel="shortcut icon" type="image/x-icon" href="ico/Flurstueck_Historisch.ico">
	<style type='text/css' media='print'>
		.noprint {visibility: hidden;}
	</style>
</head>
<body>

<?php
$con = pg_connect("host=".$dbhost." port=" .$dbport." dbname=".$dbname." user=".$dbuser." password=".$dbpass." sslmode=require");
if (!$con) echo "<p class='err'>Fehler beim Verbinden der DB</p>\n";
// if ($debug > 1) {echo "<p class='err'>DB=".$dbname.", user=".$dbuser."</p>";}

function vorgaenger($fskennz, $gmlid, $con) {
    // Vorgänger bestimmen
    $gefunden = false;
    // unsinnige Fälle abfangen, in denen ein Flurstückskennzeichen mehrfach in der Historie auftritt; Beispiel:
    // 132218003000040006__ (hier sind die korrekten Nachfolger angegeben)
    // 13221800300004000601 (verweist auf 13221800300004000602)
    // 13221800300004000602 (verweist auf 13221800300004000603)
    // 13221800300004000603 (hier sind die korrekten Vorgänger angegeben)
    // => 13221800300004000603 statt 132218003000040006__ verwenden
    $temp_gmlid = $gmlid;
    $temp_sql = "SELECT DISTINCT gml_id FROM aaa_ogr.ax_historischesflurstueckohneraumbezug WHERE flurstueckskennzeichen = (SELECT max(flurstueckskennzeichen) FROM aaa_ogr.ax_historischesflurstueckohneraumbezug WHERE flurstueckskennzeichen ~ (SELECT DISTINCT regexp_replace(flurstueckskennzeichen, '__$', '') FROM aaa_ogr.ax_historischesflurstueckohneraumbezug WHERE gml_id = $1));";
    pg_prepare($con, "", $temp_sql);
    $temp_res = pg_execute($con, "", array($gmlid));
    if ($temp_resultate = pg_fetch_array($temp_res)) {
        $temp_gmlid = $temp_resultate[0];
    }
    pg_free_result($temp_res);
    $sql_vorgaenger = "
     SELECT
      array_agg(flurstueckskennzeichen) AS flurstueckskennzeichen
       FROM (
        SELECT
         flurstueckskennzeichen
          FROM aaa_ogr.ax_historischesflurstueckohneraumbezug
          WHERE (
           SELECT
            flurstueckskennzeichen
             FROM aaa_ogr.ax_flurstueck
              WHERE endet IS NULL
              AND gml_id = $1
          ) = ANY (nachfolgerflurstueckskennzeichen)
        UNION SELECT
         flurstueckskennzeichen
          FROM aaa_ogr.ax_historischesflurstueckohneraumbezug
           WHERE (
            SELECT
             flurstueckskennzeichen
              FROM aaa_ogr.ax_flurstueck
               WHERE endet IS NOT NULL
               AND gml_id = $1
                ORDER BY endet DESC
                 LIMIT 1
           ) = ANY (nachfolgerflurstueckskennzeichen)
        UNION SELECT
         flurstueckskennzeichen
          FROM aaa_ogr.ax_historischesflurstueckohneraumbezug
           WHERE (
            SELECT DISTINCT
             flurstueckskennzeichen
              FROM aaa_ogr.ax_historischesflurstueckohneraumbezug
               WHERE gml_id = $1
           ) = ANY (nachfolgerflurstueckskennzeichen)
       ) AS tabelle";
    pg_prepare($con, "", $sql_vorgaenger);
    $res_vorgaenger = pg_execute($con, "", array($temp_gmlid));
    if ($resultate = pg_fetch_array($res_vorgaenger)) {
        $stri = trim($resultate[0], "{}");
        if (!empty($stri)) {
          $gefunden = true;
        }
        $vorgaenger[0] = explode(",", $stri);
    }
    pg_free_result($res_vorgaenger);
    if ($gefunden === false) {
        $sql_vorgaenger = "
         SELECT
          array_agg(flurstueckskennzeichen) AS flurstueckskennzeichen
           FROM (
            SELECT DISTINCT
             unnest(zeigtaufaltesflurstueck) AS flurstueckskennzeichen
              FROM aaa_ogr.ax_fortfuehrungsfall
               WHERE zeigtaufaltesflurstueck != zeigtaufneuesflurstueck
               AND (
                SELECT
                 flurstueckskennzeichen
                  FROM aaa_ogr.ax_flurstueck
                   WHERE gml_id = $1
                    LIMIT 1
               ) = ANY (zeigtaufneuesflurstueck)
           ) AS tabelle";
        pg_prepare($con, "", $sql_vorgaenger);
        $res_vorgaenger = pg_execute($con, "", array($gmlid));
        if ($resultate = pg_fetch_array($res_vorgaenger)) {
            $stri = trim($resultate[0], "{}");
            if (!empty($stri)) {
              $gefunden = true;
            }
            $vorgaenger[0] = explode(",", $stri);
        }
        pg_free_result($res_vorgaenger);
    }
    if ($gefunden === false) {
        // unsinnige Fälle abfangen, in denen ein Flurstückskennzeichen mehrfach in der Historie auftritt; Beispiel:
        // 132218003000040006__ (hier sind die korrekten Nachfolger angegeben)
        // 13221800300004000601 (verweist auf 13221800300004000602)
        // 13221800300004000602 (verweist auf 13221800300004000603)
        // 13221800300004000603 (hier sind die korrekten Vorgänger angegeben)
        // => 13221800300004000603 statt 132218003000040006__ verwenden
        $temp_fskennz = $fskennz;
        $temp_sql = "SELECT max(flurstueckskennzeichen) FROM aaa_ogr.ax_historischesflurstueckohneraumbezug WHERE flurstueckskennzeichen ~ regexp_replace($1, '__$', '');";
        pg_prepare($con, "", $temp_sql);
        $temp_res = pg_execute($con, "", array($fskennz));
        if ($temp_resultate = pg_fetch_array($temp_res)) {
            $temp_fskennz = $temp_resultate[0];
        }
        pg_free_result($temp_res);
        $sql_vorgaenger = "
         SELECT
          array_agg(flurstueckskennzeichen) AS flurstueckskennzeichen
           FROM (
            SELECT
             unnest(vorgaengerflurstueckskennzeichen) AS flurstueckskennzeichen
              FROM aaa_ogr.ax_historischesflurstueckohneraumbezug
               WHERE flurstueckskennzeichen = $1
            UNION SELECT DISTINCT
             unnest(zeigtaufaltesflurstueck) AS flurstueckskennzeichen
              FROM aaa_ogr.ax_fortfuehrungsfall
               WHERE zeigtaufaltesflurstueck != zeigtaufneuesflurstueck
               AND $1 = ANY (zeigtaufneuesflurstueck)
           ) AS tabelle";
        pg_prepare($con, "", $sql_vorgaenger);
        $res_vorgaenger = pg_execute($con, "", array($temp_fskennz));
        if ($resultate = pg_fetch_array($res_vorgaenger)) {
            $stri = trim($resultate[0], "{}");
            if (!empty($stri)) {
              $gefunden = true;
            }
            $vorgaenger[0] = explode(",", $stri);
        }
        pg_free_result($res_vorgaenger);
    }
    
    // Vorgänger von Duplikaten befreien und sortieren
    $vorgaenger[0] = array_unique($vorgaenger[0]);
    asort($vorgaenger[0]);

    return $vorgaenger;
}

function nachfolger($fskennz, $con) {
    // Nachfolger bestimmen
    $sql_nachfolger = "
     SELECT
      array_agg(CASE WHEN flurstueckskennzeichen !~ '__$' THEN regexp_replace(flurstueckskennzeichen, '[0-9]{2}$', '__') ELSE flurstueckskennzeichen END) AS flurstueckskennzeichen
       FROM (
        SELECT
         unnest(nachfolgerflurstueckskennzeichen) AS flurstueckskennzeichen
          FROM aaa_ogr.ax_historischesflurstueckohneraumbezug
           WHERE flurstueckskennzeichen = $1
        UNION SELECT DISTINCT
         unnest(zeigtaufneuesflurstueck) AS flurstueckskennzeichen
          FROM aaa_ogr.ax_fortfuehrungsfall
           WHERE zeigtaufaltesflurstueck != zeigtaufneuesflurstueck
           AND $1 = ANY (zeigtaufaltesflurstueck)
       ) AS tabelle";
    pg_prepare($con, "", $sql_nachfolger);
    $res_nachfolger = pg_execute($con, "", array($fskennz));
    if ($resultate = pg_fetch_array($res_nachfolger)) {
        $stri = trim($resultate[0], "{}");
        $nachfolger[0] = explode(",", $stri);
    }
    pg_free_result($res_nachfolger);
    
    // Nachfolger von Duplikaten befreien und sortieren
    $nachfolger[0] = array_unique($nachfolger[0]);
    asort($nachfolger[0]);

    return $nachfolger;
}

// Such-Parameter bekommen? Welche?
if ($gmlid != "") { // Ja, die GML wurde uebergeben
	$parmtyp="GML";
	$parmval=$gmlid;
	$whereclause="WHERE gml_id= $1 ";
	$v = array($gmlid);
} else {	// Alternativ: das Flurstücks-Kennzeichen wurde übergeben
	if ($fskennz != "") {
		$parmtyp="Flurstückskennzeichen";
		$parmval=$fskennz;
		$whereclause="WHERE flurstueckskennzeichen= $1 "; // hinten auffuellen mit _ auf 20 Stellen
		$v = array($fskennz);
	} else { // Pfui!
		$parmtyp="";
		echo "<p class='err'>Parameter 'gmlid' oder 'fskennz' fehlt.</p>";
	}
}

if ($parmtyp != "") { // einer der beiden erlaubten Fälle
	// UNION-Abfrage auf 3ähnliche Tabellen, darin aber immer nur 1 Treffer.
	$felder="endet, zeigtaufexternes_art AS art, zeigtaufexternes_name AS name, gml_id, land, flurnummer, zaehler, nenner, flurstueckskennzeichen, amtlicheflaeche, zeitpunktderentstehung, gemarkungsnummer, angabenzumabschnittflurstueck, ";

	$sqlu = "SELECT 'a' AS ftyp, ".$felder."null::text[] AS nach, null::text[] AS vor ";
	$sqlu.="FROM aaa_ogr.ax_flurstueck f ".$whereclause." AND f.endet IS NULL ";
	$sqlu.="UNION ";
	$sqlu.= "SELECT 'h' AS ftyp, ".$felder."null::text[] AS nach, null::text[] AS vor ";
	$sqlu.="FROM aaa_ogr.ax_flurstueck f ".$whereclause." AND f.endet IS NOT NULL ";
	$sqlu.="UNION ";
	$sqlu.= "SELECT 'o' AS ftyp, ".$felder."nachfolgerflurstueckskennzeichen AS nach, vorgaengerflurstueckskennzeichen AS vor ";
	$sqlu.="FROM aaa_ogr.ax_historischesflurstueckohneraumbezug o ".$whereclause;
	$sqlu.="ORDER BY endet DESC LIMIT 1";
	
	$resu = pg_prepare("", $sqlu);
	$resu = pg_execute("", $v);
	if ($rowu = pg_fetch_array($resu)) {
		$ftyp=$rowu["ftyp"];
		$land=$rowu["land"];
		$gmkgnr=$rowu["gemarkungsnummer"];
		$flurnummer=$rowu["flurnummer"];
		$zaehler=$rowu["zaehler"];
		$nenner=$rowu["nenner"];
		$flstnummer=$zaehler;
		if ($nenner > 0) {$flstnummer.="/".$nenner;} // BruchNr
		$fskenn=$rowu["flurstueckskennzeichen"];
    $amtlicheflaeche=$rowu["amtlicheflaeche"]; // amtliche Fläche
    $amtlicheflaeched=($amtlicheflaeche < 1 ? rtrim(number_format($amtlicheflaeche,2,",","."),"0") : number_format($amtlicheflaeche,0,",",".")); // Display-Format dazu
		$name=$rowu["name"]; // in DB ein Array
		$arrn=explode(",", trim($name, "{}") ); // PHP-Array
        $fortfuehrungsbelegnummer=$rowu["name"]; // Fortführungsbelegnummer(n)
        $fortfuehrungsbelegnummer_array=explode(",", trim($fortfuehrungsbelegnummer, "{}") ); // PHP-Array mit Fortführungsbelegnummer(n)
		$gemkname= gemkg_name($gmkgnr);
        if (!empty($rowu["zeitpunktderentstehung"]))
            $entstehung_datum = strftime('%d.%m.%Y', strtotime($rowu["zeitpunktderentstehung"]));
        else
            $entstehung_datum = "unbekannt";
        $alb_datenarten = $rowu["art"]; // ALB-Datenarten
        $alb_datenarten = explode(",", trim(str_replace('"', '', $alb_datenarten), "{}")); // PHP-Array mit ALB-Datenarten
        $alb_daten = $rowu["name"]; // ALB-Daten
        $alb_daten = explode(",", trim(str_replace('"', '', $alb_daten), "{}")); // PHP-Array mit ALB-Daten
        $zusatzangaben = $rowu["angabenzumabschnittflurstueck"]; // Zusatzangaben
        $zusatzangaben = explode(",", trim($zusatzangaben, "{}")); // PHP-Array mit Zusatzangaben
        $vor=$rowu["vor"];
		$nach=$rowu["nach"];
		if ($gmlid == "") {$gmlid=$rowu["gml_id"];} // für selbst-link-Umschalter ueber footer
	} else {
		if ($debug > 1) {echo "<br><p class='err'>Fehler! Kein Treffer für ".$parmtyp." = '".$parmval."'</p><br>";}
		if ($debug > 2) {echo "<p class='dbg'>SQL=<br>".$sqlu."<br>$1=".$parmtyp." = '".$parmval."'</p>";}
	}
}

switch ($ftyp) { // Unterschiede Historisch/Aktuell
	case 'a': 
		$wert = "aktuell";
		$ico= "Flurstueck.ico";
		$cls= "kennzfs";	
	break;
	case 'h': 
		$wert = "historisch<br>mit Raumbezug";
		$ico= "Flurstueck_Historisch.ico"; //
		$cls= "kennzfsh";
	break;
	case 'o': 
		$wert = "historisch<br>ohne Raumbezug";
		$ico= "Flurstueck_Historisch_oR.ico";
		$cls= "kennzfsh";
	break;
	default:
		$wert = "<b>nicht gefunden: ".$parmtyp." = '".$parmval."'</b>";
		$ico= "Flurstueck_Historisch.ico";
		$cls= "kennzfsh";
	break;
}

// Balken
echo "<p class='fshis'>Flurstück <span title='Flurstückskennzeichen in der offiziellen ALKIS-Notation'>".$fskenn."</span>&nbsp;</p>\n";
echo "\n<h2><img src='ico/".$ico."' width='16' height='16' alt=''> Flurstückshistorie</h2>\n";

echo "\n<table class='outer'>\n<tr>\n\t<td>"; // linke Seite
	// darin Tabelle Kennzeichen
	echo "\n\t<table class='kennzfs'>\n\t<tr>";
		echo "\n\t\t<td class='head'>Gemarkung</td>\n\t\t<td class='head'>Flur</td>\n\t\t<td class='head'>Flurstück</td>\n\t</tr>";
		echo "\n\t<tr>\n\t\t<td title='Gemarkungsname'>";
		if ($showkey) {
			echo "<span class='key' title='Gemarkungsschlüssel'>".$land.$gmkgnr."</span><br>";
		}
		echo $gemkname."&nbsp;</td>";
		echo "\n\t\t<td title='Flurnummer'>".$flurnummer."</td>";
		echo "\n\t\t<td title='Flurstücksnummer in der Notation: Zähler/Nenner'><span class='wichtig'>".$flstnummer."</span></td>\n\t</tr>";
	echo "\n\t</table>";
    echo "\n\t</td>";
	if ($idanzeige) {linkgml($gkz, $gmlid, "Flurstück"); }
echo "\n</tr>\n</table>";

if ($ftyp == "a") { // Aktuell -> Historie
	echo "\n<p class='nwlink noprint'>weitere Auskunft: ";
		echo "<a href='alkisfsnw.php?gkz=".$gkz."&amp;gmlid=".$gmlid;
			if ($idanzeige) {echo "&amp;id=j";}
			if ($showkey)   {echo "&amp;showkey=j";}
			echo "' title='Flurstücksnachweis'>Flurstück ";
			echo "<img src='ico/Flurstueck_Link.ico' width='16' height='16' alt=''>";
		echo "</a>";
}
echo "\n<hr class='thin'>";

echo "<table class='outer'>";
	echo "\n<tr>
		<td class='head'>Flurstück</td>
		<td class='head'>Vorgänger</td>
		<td class='head'>Nachfolger</td>
	</tr>"; // Head
	
	// Spalte 1: F l u r s t ü c k
	echo "\n<tr>\n\t<td>";
		echo "<img src='ico/".$ico."' width='16' height='16' alt=''> ".$wert;
		echo "<br><span title='amtliche Fläche (Buchfläche) des Flurstücks'>Fläche</span> <span class='flae'>".($amtlicheflaeched == '0,' ? "unbekannt" : $amtlicheflaeched." m²")."</span>";
	echo "</td>";

	// Spalte 2: V o r g ä n g e r
    $vorgaenger = vorgaenger($fskennz, $gmlid, $con);
    echo "\n\t<td>";
    $i = 0;
    if (isset($vorgaenger[$i]) === true && strlen($vorgaenger[$i][0]) > 0) {
        foreach($vorgaenger[$i] AS $val) {
            echo "Flurstück <a title='Historie des direkten Vorgängerflurstücks' href='".$_SERVER['PHP_SELF']."?gkz=".$gkz."&amp;fskennz=".$val;
            if ($idanzeige) {echo "&amp;id=j";}
            if ($showkey)   {echo "&amp;showkey=j";}
            echo "'>".fzerleg($val)."</a><br>";
        }
    }
	else {
        echo "keine(r) im Datenbestand vorhanden";
    }
	echo"</td>";

	// Spalte 3: N a c h f o l g e r
    $nachfolger = nachfolger($fskennz, $con);
    echo "\n\t<td>";
    $j = 0;
    if (isset($nachfolger[$j]) === true && strlen($nachfolger[$i][0]) > 0) {
        foreach($nachfolger[$j] AS $val) {
            echo "Flurstück <a title='Historie des direkten Nachfolgerflurstücks' href='".$_SERVER['PHP_SELF']."?gkz=".$gkz."&amp;fskennz=".$val;
            if ($idanzeige) {echo "&amp;id=j";}
            if ($showkey)   {echo "&amp;showkey=j";}
            echo "'>".fzerleg($val)."</a><br>";
        }
    }
	else {
        echo "noch keine(r)";
    }
	echo"</td>\n</tr>";
	echo "</td>\n</tr>";
echo "\n</table>";


if ($debug > 1) {
	$z=1;
	while($rowu = pg_fetch_array($resu)) {
		$ftyp=$rowu["ftyp"];
		echo "<p class='dbg'>Mehr als EIN Eintrag gefunden: '".$ftyp."' (".$z.")</p>";
		$z++;
	}
}
?>

<form action=''>
	<div class='buttonbereich noprint'>
	<hr class='thick'>
		<a title="zurürck zur vorherigen Ansicht" href='javascript:history.back()'><img src="ico/zurueck.ico" width="16" height="16" alt="zurück"></a>&nbsp;
		<a title="drucken" href='javascript:window.print()'><img src="ico/print.ico" width="16" height="16" alt="Drucken"></a>&nbsp;
	</div>
</form>

<?php footer($gmlid, $_SERVER['PHP_SELF']."?"); ?>

</body>
</html>