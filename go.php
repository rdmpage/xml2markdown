<?php

$filename = 'examples/2847.xml';
//$filename = 'wellcomeopenres-6-330-v1.xml';

//$filename = 'examples/f1000research-12-1327-v1.xml';

//$filename = 'fmars-09-00955.xml';

$filename = 'examples/kew12225.xml';

// XML
$xml = new DOMDocument();
$xml->load($filename);

// XSL
$xsl = new DOMDocument();
$xsl ->load("jats.xsl");

// Proc
$proc = new XSLTProcessor();
$proc->importStylesheet($xsl);

echo $proc->transformToXML($xml);

?>

