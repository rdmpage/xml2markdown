<?php

// Download Elsevier figure images and supplementary files. Every attachment is
// reachable at https://ars.els-cdn.com/content/image/<xocs:attachment-eid>.
// We fetch the display figures (IMAGE-DOWNSAMPLED) and supplementary data files
// (APPLICATION); thumbnails, high-res variants and the accepted-manuscript PDF
// are skipped.

require_once(dirname(__FILE__) . '/utils.php');

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

$xml = file_get_contents($filename);

$dom = new DOMDocument();
$dom->loadXML($xml);
$xpath = new DOMXPath($dom);
$xpath->registerNamespace('xocs', 'http://www.elsevier.com/xml/xocs/dtd');

$base = 'https://ars.els-cdn.com/content/image/';
$wanted = array('IMAGE-DOWNSAMPLED', 'APPLICATION');

foreach ($xpath->query('//xocs:attachment') as $a)
{
	$type = $xpath->query('xocs:attachment-type', $a)->item(0);
	if (!$type || !in_array(trim($type->textContent), $wanted)) { continue; }

	$eid  = $xpath->query('xocs:attachment-eid', $a)->item(0);
	$name = $xpath->query('xocs:filename', $a)->item(0);
	if (!$eid) { continue; }
	$eid = trim($eid->textContent);

	// Local name: the attachment filename, or the eid as a fallback.
	$local = $name ? trim($name->textContent) : $eid;
	$local = sanitise_filename($local);

	$data = get($base . $eid);
	if ($data !== false && $data !== '')
	{
		file_put_contents($local, $data);
		echo "  " . $local . "\n";
	}
}

?>
