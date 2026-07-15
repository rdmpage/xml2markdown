<?php

// Extract citations from JATS XML

error_reporting(E_ALL);


//----------------------------------------------------------------------------------------
// Plazi refStrings sometimes inject spaces into DOIs/URLs, e.g.
// "https: // doi. org / 10.11646 / zootaxa. 2170.1.6". Repair by matching a URL
// from its scheme through the following run of URL characters (spaces allowed)
// and stripping the whitespace inside that match only. Anchoring on https?://
// keeps the rest of the citation — page ranges like "1 - 44", "et al." — intact.
function fix_broken_urls($text)
{
	return preg_replace_callback(
		'!https?\s*:\s*/\s*/(?:\s*[\w.:/~%#?=&@+-])+!i',
		function ($m) {
			// Remove only whitespace touching URL punctuation (: / .). Every
			// injected space in a broken URL is adjacent to one of these, while
			// word-to-word spaces (any prose the match over-ran) are left alone.
			return preg_replace('~\s+(?=[:/.])|(?<=[:/.])\s+~', '', $m[0]);
		},
		$text
	);
}

//----------------------------------------------------------------------------------------
function plazi_to_csl($xml)
{
	$bibliography = array();

	$dom= new DOMDocument;
	$dom->loadXML($xml);
	$xpath = new DOMXPath($dom);
	
	foreach($xpath->query('//bibRefCitation') as $node)
	{
		
		$work = new stdclass;
		$work->id = $node->getAttribute('refId');
		
		$work->unstructured = fix_broken_urls($node->getAttribute('refString'));
		
		
		$bibliography[$work->id] = $work;
	}

	return $bibliography;
}

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

$xml = file_get_contents($filename);

$bibliography = plazi_to_csl($xml);

//print_r($bibliography);

file_put_contents($xml_file_parts['filename'] . '-references.json', json_encode($bibliography, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");

?>
