<?php

require_once(dirname(__FILE__) . '/jats-to-csl.php');

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

$bibliography = jats_to_csl($xml);

//print_r($bibliography);

file_put_contents($xml_file_parts['filename'] . '-references.json', json_encode($bibliography, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");
