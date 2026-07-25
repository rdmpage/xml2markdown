<?php

// Extract references from Elsevier full-text XML as CSL-JSON, mirroring
// jats-to-csl.php. Each ce:bib-reference carries a structured sb:reference plus
// a ready-made ce:source-text citation (used as `unstructured`).

//----------------------------------------------------------------------------------------
function elsevier_to_csl($xml)
{
	$dom = new DOMDocument();
	$dom->loadXML($xml);

	$xpath = new DOMXPath($dom);
	$xpath->registerNamespace('ce',    'http://www.elsevier.com/xml/common/dtd');
	$xpath->registerNamespace('sb',    'http://www.elsevier.com/xml/common/struct-bib/dtd');
	$xpath->registerNamespace('cd',    'http://www.elsevier.com/xml/svapi/article/dtd');
	$xpath->registerNamespace('prism', 'http://prismstandard.org/namespaces/basic/2.0/');

	// Identifier for the parent article (used to namespace each citation id).
	$work_doi = '';
	$node = $xpath->query('//cd:coredata/prism:doi')->item(0);
	if ($node) { $work_doi = trim($node->textContent); }
	$work_id = ($work_doi !== '') ? 'https://doi.org/' . $work_doi : md5($xml);

	$bibliography = array();

	$count = 0;
	foreach ($xpath->query('//ce:bib-reference') as $ref)
	{
		$citation = new stdclass;
		$citation->type = 'journal-article';

		$key = $ref->getAttribute('id');
		if ($key === '') { $key = (string) $count; }
		$citation->id = $work_id . '#' . $key;

		// Authors (sb:author -> ce:given-name / ce:surname). Omit the field
		// entirely when there are none (e.g. unstructured other-refs).
		$authors = array();
		foreach ($xpath->query('.//sb:author', $ref) as $a)
		{
			$author = new stdclass;
			$g = $xpath->query('ce:given-name', $a)->item(0);
			$s = $xpath->query('ce:surname', $a)->item(0);
			if ($g) { $author->given  = trim($g->textContent); }
			if ($s) { $author->family = trim($s->textContent); }
			if (isset($author->given) || isset($author->family)) { $authors[] = $author; }
		}
		if (count($authors) > 0) { $citation->author = $authors; }

		// Clean, ready-made citation string: ce:source-text for structured refs,
		// or ce:textref for Elsevier's unstructured fallback (ce:other-ref).
		$st = $xpath->query('.//ce:source-text | .//ce:textref', $ref)->item(0);
		if ($st) { $citation->unstructured = trim(preg_replace('/\s+/', ' ', $st->textContent)); }

		// Article title.
		$t = $xpath->query('.//sb:contribution/sb:title/sb:maintitle', $ref)->item(0);
		if ($t) { $citation->title = trim($t->textContent); }

		// Journal (series title). Fall back to a book title -> chapter.
		$j = $xpath->query('.//sb:host//sb:series/sb:title/sb:maintitle', $ref)->item(0);
		if ($j)
		{
			$citation->{'container-title'} = trim($j->textContent);
		}
		else
		{
			$bt = $xpath->query('.//sb:host//sb:book//sb:maintitle | .//sb:host/sb:edited-book//sb:maintitle', $ref)->item(0);
			if ($bt) { $citation->{'container-title'} = trim($bt->textContent); $citation->type = 'chapter'; }
		}

		// Volume / issue / pages.
		$v = $xpath->query('.//sb:volume-nr', $ref)->item(0);
		if ($v) { $citation->volume = trim($v->textContent); }

		$iss = $xpath->query('.//sb:issue-nr', $ref)->item(0);
		if ($iss) { $citation->issue = trim($iss->textContent); }

		$fp = $xpath->query('.//sb:pages/sb:first-page', $ref)->item(0);
		$lp = $xpath->query('.//sb:pages/sb:last-page', $ref)->item(0);
		if ($fp) { $citation->page = trim($fp->textContent) . ($lp ? '-' . trim($lp->textContent) : ''); }

		// Year.
		$y = $xpath->query('.//sb:date', $ref)->item(0);
		if ($y && preg_match('/(\d{4})/', $y->textContent, $m))
		{
			$citation->issued = new stdclass;
			$citation->issued->{'date-parts'} = array(array((int) $m[1]));
		}

		// DOI.
		$doi = $xpath->query('.//ce:doi', $ref)->item(0);
		if ($doi) { $citation->DOI = trim($doi->textContent); }

		$bibliography[$key] = $citation;
		$count++;
	}

	return $bibliography;
}

?>
