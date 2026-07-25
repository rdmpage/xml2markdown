<?php

require_once(dirname(__FILE__) . '/utils.php');

//----------------------------------------------------------------------------------------
// Article metadata from the Elsevier <coredata> block for the YAML front matter.
function elsevier_metadata($dom)
{
	$x = new DOMXPath($dom);
	$x->registerNamespace('cd',    'http://www.elsevier.com/xml/svapi/article/dtd');
	$x->registerNamespace('ce',    'http://www.elsevier.com/xml/common/dtd');
	$x->registerNamespace('dc',    'http://purl.org/dc/elements/1.1/');
	$x->registerNamespace('prism', 'http://prismstandard.org/namespaces/basic/2.0/');

	$title = $x->query('//cd:coredata/dc:title')->item(0);

	// Prefer structured ce:author (given + surname); fall back to dc:creator.
	$authors = array();
	foreach ($x->query('//ce:author') as $a)
	{
		$g = $x->query('ce:given-name', $a)->item(0);
		$s = $x->query('ce:surname', $a)->item(0);
		$name = trim(($g ? $g->textContent . ' ' : '') . ($s ? $s->textContent : ''));
		if ($name !== '') { $authors[] = $name; }
	}
	if (count($authors) == 0)
	{
		foreach ($x->query('//cd:coredata/dc:creator') as $c)
		{
			$name = trim($c->textContent);
			if (strpos($name, ',') !== false)   // "Surname, Given" -> "Given Surname"
			{
				$b = array_map('trim', explode(',', $name, 2));
				if (count($b) == 2 && $b[1] !== '') { $name = $b[1] . ' ' . $b[0]; }
			}
			if ($name !== '') { $authors[] = $name; }
		}
	}

	$doi     = $x->query('//cd:coredata/prism:doi')->item(0);
	$journal = $x->query('//cd:coredata/prism:publicationName')->item(0);
	$date    = $x->query('//cd:coredata/prism:coverDate')->item(0);
	$license = $x->query('//cd:coredata/cd:openaccessUserLicense')->item(0);
	$pii     = $x->query('//cd:coredata/cd:pii')->item(0);

	$meta = array();
	if ($title)   { $meta['title']   = $title->textContent; }
	if ($authors) { $meta['authors'] = $authors; }
	if ($doi)     { $meta['doi']     = trim($doi->textContent); }
	if ($journal) { $meta['journal'] = $journal->textContent; }
	if ($date)    { $meta['date']    = trim($date->textContent); }
	if ($license)
	{
		if (preg_match('~creativecommons\.org/licenses/([a-z-]+)~i', $license->textContent, $m))
		{
			$meta['license'] = 'CC ' . strtoupper($m[1]);
		}
	}
	$meta['source'] = 'elsevier';
	if ($pii) { $meta['pii'] = trim($pii->textContent); }

	return $meta;
}

//----------------------------------------------------------------------------------------

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
$xslt_filename = dirname(__FILE__) . '/elsevier2markdown.xsl';

$xsl = new DOMDocument();
$xsl->load($xslt_filename);

// Proc
$proc = new XSLTProcessor();
$proc->importStylesheet($xsl);

$markdown = $proc->transformToXML($xml);

if ($markdown === false)
{
	fwrite(STDERR, "elsevier2markdown: XSLT transform failed for $filename\n");
	exit(1);
}

// Prepend YAML front matter (the H1 title is kept in the body for readability).
$front = frontmatter(elsevier_metadata($xml));

file_put_contents($xml_file_parts['filename'] . '.md', $front . ltrim($markdown));

?>
