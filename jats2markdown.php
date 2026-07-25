<?php

require_once(dirname(__FILE__) . '/utils.php');

//----------------------------------------------------------------------------------------
// Pull article metadata from JATS <article-meta> for the YAML front matter.
function jats_metadata($dom)
{
	$x = new DOMXPath($dom);

	$title = $x->query('//article-meta/title-group/article-title')->item(0);

	$authors = array();
	foreach ($x->query('//article-meta//contrib[@contrib-type="author"]') as $c)
	{
		$giv = $x->query('.//given-names', $c)->item(0);
		$sur = $x->query('.//surname', $c)->item(0);
		$name = trim(($giv ? $giv->textContent . ' ' : '') . ($sur ? $sur->textContent : ''));
		if ($name === '')
		{
			$alt = $x->query('.//string-name | .//collab', $c)->item(0);   // corporate / unstructured
			if ($alt) { $name = trim($alt->textContent); }
		}
		if ($name !== '') { $authors[] = $name; }
	}

	$doi     = $x->query('//article-meta/article-id[@pub-id-type="doi"]')->item(0);
	$pmid    = $x->query('//article-meta/article-id[@pub-id-type="pmid"]')->item(0);
	$pmc     = $x->query('//article-meta/article-id[@pub-id-type="pmcid" or @pub-id-type="pmc"]')->item(0);
	$journal = $x->query('//journal-meta//journal-title')->item(0);

	// Assemble in a stable, human-friendly key order.
	$meta = array();
	if ($title)   { $meta['title']   = $title->textContent; }
	if ($authors) { $meta['authors'] = $authors; }
	if ($doi)     { $meta['doi']     = trim($doi->textContent); }
	if ($journal) { $meta['journal'] = $journal->textContent; }

	$date = jats_pub_date($x);
	if ($date)    { $meta['date']    = $date; }

	$license = jats_license($x);
	if ($license) { $meta['license'] = $license; }

	$meta['source'] = $pmc ? 'pmc' : 'jats';
	if ($pmc)
	{
		$v = trim($pmc->textContent);
		$meta['pmcid'] = (strpos($v, 'PMC') === 0) ? $v : 'PMC' . $v;
	}
	if ($pmid) { $meta['pmid'] = trim($pmid->textContent); }

	return $meta;
}

//----------------------------------------------------------------------------------------
// Best publication date as an ISO string (prefer the electronic/print pub-date,
// which usually carries the full day/month, over a collection year).
function jats_pub_date($x)
{
	$dates = $x->query('//article-meta/pub-date');
	$best = null;
	foreach ($dates as $pd)
	{
		$type = $pd->getAttribute('pub-type') . ' ' . $pd->getAttribute('date-type') . ' ' . $pd->getAttribute('publication-format');
		if (preg_match('/epub|ppub|electronic|(^|[^a-z])pub([^a-z]|$)/i', $type)) { $best = $pd; break; }
	}
	if (!$best && $dates->length > 0) { $best = $dates->item(0); }
	if (!$best) { return null; }

	$y = $x->query('year', $best)->item(0);
	if (!$y) { return null; }

	$iso = sprintf('%04d', (int) $y->textContent);
	$m = $x->query('month', $best)->item(0);
	if ($m)
	{
		$iso .= sprintf('-%02d', (int) $m->textContent);
		$d = $x->query('day', $best)->item(0);
		if ($d) { $iso .= sprintf('-%02d', (int) $d->textContent); }
	}
	return $iso;
}

//----------------------------------------------------------------------------------------
// Normalise a Creative Commons licence from the <permissions> block, if present.
function jats_license($x)
{
	$perm = $x->query('//article-meta/permissions')->item(0);
	if (!$perm) { return null; }

	$blob = $perm->ownerDocument->saveXML($perm);
	if (preg_match('~creativecommons\.org/licenses/([a-z-]+)~i', $blob, $m)) { return 'CC ' . strtoupper($m[1]); }
	if (preg_match('~creativecommons\.org/publicdomain/zero~i', $blob)) { return 'CC0'; }
	return null;
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
$xslt_filename = dirname(__FILE__) . '/jats2markdown.xsl';

$xsl = new DOMDocument();
$xsl->load($xslt_filename);

// Proc
$proc = new XSLTProcessor();
$proc->importStylesheet($xsl);

$markdown = $proc->transformToXML($xml);

// Prepend YAML front matter (the H1 title is kept in the body for readability).
$front = frontmatter(jats_metadata($xml));

file_put_contents($xml_file_parts['filename'] . '.md', $front . $markdown);

?>
