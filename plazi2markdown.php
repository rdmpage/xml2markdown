<?php

require_once(dirname(__FILE__) . '/utils.php');

//----------------------------------------------------------------------------------------
// Pull article metadata from the Plazi MODS block for the YAML front matter.
function plazi_metadata($dom)
{
	$x = new DOMXPath($dom);
	$x->registerNamespace('m', 'http://www.loc.gov/mods/v3');

	$title = $x->query('//m:mods/m:titleInfo/m:title')->item(0);

	$authors = array();
	foreach ($x->query('//m:mods/m:name') as $n)
	{
		// Keep only Author-role names (when a role is given).
		$roles = $x->query('.//m:roleTerm', $n);
		if ($roles->length > 0)
		{
			$is_author = false;
			foreach ($roles as $r) { if (stripos($r->textContent, 'author') !== false) { $is_author = true; } }
			if (!$is_author) { continue; }
		}

		$np = $x->query('.//m:namePart', $n)->item(0);
		if (!$np) { continue; }

		// Plazi stores "Surname, Given"; flip to "Given Surname" to match JATS.
		$name = trim($np->textContent);
		if (strpos($name, ',') !== false)
		{
			$bits = array_map('trim', explode(',', $name, 2));
			if (count($bits) == 2 && $bits[1] !== '') { $name = $bits[1] . ' ' . $bits[0]; }
		}
		if ($name !== '') { $authors[] = $name; }
	}

	$journal = $x->query('//m:mods/m:relatedItem//m:title')->item(0);
	$doi     = $x->query('//m:mods//m:identifier[@type="DOI"]')->item(0);
	$zenodo  = $x->query('//m:mods//m:identifier[@type="Zenodo-Dep"]')->item(0);
	$zoobank = $x->query('//m:mods//m:identifier[@type="ZooBank"]')->item(0);

	// Prefer the full publication date (part/detail[@type="pubDate"]) over the
	// bare year in mods:date.
	$date = null;
	$pubdate = $x->query('//m:mods//m:part/m:detail[@type="pubDate"]/m:number')->item(0);
	if ($pubdate) { $date = trim($pubdate->textContent); }
	else
	{
		$bd = $x->query('//m:mods//m:dateIssued | //m:mods//m:date')->item(0);
		if ($bd) { $date = trim($bd->textContent); }
	}

	// Assemble in the same key order as the JATS front matter.
	$meta = array();
	if ($title)   { $meta['title']   = $title->textContent; }
	if ($authors) { $meta['authors'] = $authors; }
	if ($doi)     { $meta['doi']     = trim($doi->textContent); }
	if ($journal) { $meta['journal'] = $journal->textContent; }
	if ($date)    { $meta['date']    = $date; }
	$meta['source'] = 'plazi';
	if ($zenodo)  { $meta['zenodo']  = trim($zenodo->textContent); }   // Plazi-specific ids
	if ($zoobank) { $meta['zoobank'] = trim($zoobank->textContent); }

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
$xslt_filename = dirname(__FILE__) . '/plazi.xsl';

$xsl = new DOMDocument();
$xsl->load($xslt_filename);

// Proc
$proc = new XSLTProcessor();
$proc->importStylesheet($xsl);

$markdown = $proc->transformToXML($xml);

// Prepend YAML front matter (the treatment heading is kept in the body). ltrim
// the transform output so the body starts cleanly at the first heading, rather
// than after the stray whitespace the stylesheet emits ahead of the treatment.
$front = frontmatter(plazi_metadata($xml));

file_put_contents($xml_file_parts['filename'] . '.md', $front . ltrim($markdown));

?>
