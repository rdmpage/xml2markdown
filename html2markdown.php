<?php

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

$xml_file_parts = pathinfo($filename);

// XML. datalab.to occasionally emits stray control characters (e.g. a NUL where
// an accented byte got corrupted). These are illegal in XML and abort
// DOMDocument::load, which used to yield a silent empty .md. Strip C0 control
// chars (keeping tab/LF/CR), then parse the cleaned string.
$content = file_get_contents($filename);
$content = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $content);

$xml = new DOMDocument();
if (!$xml->loadXML($content))
{
	fwrite(STDERR, "html2markdown: failed to parse HTML as XML: $filename\n");
	exit(1);
}

// XSL

$xslt_filename = dirname(__FILE__) . '/html2markdown.xsl';

$xsl = new DOMDocument();
$xsl ->load($xslt_filename);

// Proc
$proc = new XSLTProcessor();
$proc->importStylesheet($xsl);

$markdown = $proc->transformToXML($xml);

if ($markdown === false)
{
	fwrite(STDERR, "html2markdown: XSLT transform failed for $filename\n");
	exit(1);
}

// Minimal YAML front matter. datalab HTML carries no bibliographic metadata
// beyond the first heading, so emit just the title (from <h1>) and the source —
// and only when there IS a title, so data-supplement fragments (no heading) are
// left without a spurious block.
$meta = array();
$h1 = $xml->getElementsByTagName('h1')->item(0);
if ($h1)
{
	$title = trim(preg_replace('/\s+/', ' ', $h1->textContent));
	if ($title !== '')
	{
		$meta['title']  = $title;
		$meta['source'] = 'pdf';
	}
}

$front = frontmatter($meta);

file_put_contents($xml_file_parts['filename'] . '.md', $front . ltrim($markdown));

?>
