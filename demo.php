<?php
include_once "sqlotron.class.php";

$sqlTest = 'SELECT f.rowid, SUM(f.amount), """hello world' . '\\' . '"()" AS teststring, ( SELECT e.name FROM llx_entity e WHERE e.rowid = f.entity) AS entity_name FROM llx_facture f LEFT JOIN `llx_societe` s ON f.fk_soc = s.rowid WHERE f.rowid IN (SELECT fk_facture FROM llx_facturedet fdet WHERE fdet.total_ttc > 1000) AND \'tutu\' == "tutu" ORDER BY f.rowid ASC LIMIT 25 OFFSET 12;';
$sqlTest2 = 'SELECT toto, (SELECT COUNT(rowid) FROM tutu WHERE tutu.titi = 4) as pepo FROM tata JOIN tete ON tete.txtx = tata.fktete WHERE toto < 100 HAVING pepo > 1 ORDER BY truc;';

// parse $sqlTest
$a = new SQLBreakDown($sqlTest);

// check how it was split into its main parts
echo json_encode($a->serializeBuckets(), JSON_PRETTY_PRINT);

// alter it by adding more fields to select and more filters
echo "\n\n\n";
echo $a->getAlteredSQL([
    'select' => ', truc.chouette AS machinchose',
    'where' => 'AND toto = "tata"'
]);
echo "\n\n\n";