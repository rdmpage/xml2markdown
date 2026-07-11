<?php

$filename = '';
if ($argc < 2)
{
	echo "Usage: " . basename(__FILE__) . " <filename>\n";
	exit(1);
}
else
{
	$filename = $argv[1];
}

$xml_file_parts = pathinfo($filename);

// XML
$xml = new DOMDocument();
$xml->load($filename);

// XSL

$xslt_filename = 'html2markdown.xsl';

$xsl = new DOMDocument();
$xsl ->load($xslt_filename);

// Proc
$proc = new XSLTProcessor();
$proc->importStylesheet($xsl);

$markdown = $proc->transformToXML($xml);

echo $markdown;

file_put_contents($xml_file_parts['filename'] . '.md', $markdown);

?>
