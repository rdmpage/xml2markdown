<?php

// Extract references from Markdown derived from a PDF/HTML (datalab output) as
// unstructured CSL-JSON. datalab typically renders the reference section as a
// heading ("References" / "Literature Cited" / ...) followed by a list, one
// reference per item. We scope to that section, treat each item as a reference,
// and keep the citation text as `unstructured` (to be structured later). Output
// is written only when a reference section is actually found, so this no-ops on
// supplements and on documents datalab did not mark up.

//----------------------------------------------------------------------------------------
// Strip Markdown emphasis and collapse whitespace.
function md_clean($s)
{
	$s = preg_replace('/\*\*([^*]+)\*\*/', '$1', $s);   // **bold**
	$s = preg_replace('/\*([^*]+)\*/', '$1', $s);        // *italic*
	$s = preg_replace('/_([^_]+)_/', '$1', $s);          // _italic_
	$s = preg_replace('/\s+/', ' ', $s);
	return trim($s);
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

$parts = pathinfo($filename);

// Work on the generated Markdown that sits next to the input.
$md_file = $parts['dirname'] . '/' . $parts['filename'] . '.md';
if (!is_file($md_file)) { exit(0); }

$lines = explode("\n", file_get_contents($md_file));

// 1. Find the references heading (allow the common variants).
$ref_heading = '/^(references|references cited|literature cited|cited literature|bibliography|works cited)$/';
$start = -1;
for ($i = 0; $i < count($lines); $i++)
{
	if (preg_match('/^#{1,6}\s+(.+?)\s*$/', $lines[$i], $m))
	{
		$h = strtolower(trim(preg_replace('/[*_]/', '', $m[1])));
		if (preg_match($ref_heading, $h)) { $start = $i + 1; break; }
	}
}
if ($start < 0) { exit(0); }   // no references section

// 2. Collect items until the next heading. A new reference begins at a list/number
//    marker or after a blank line; other lines continue the current reference.
$items = array();
$current = '';
for ($i = $start; $i < count($lines); $i++)
{
	$line = $lines[$i];
	if (preg_match('/^#{1,6}\s/', $line)) { break; }

	if (trim($line) === '')
	{
		if (trim($current) !== '') { $items[] = $current; }
		$current = '';
		continue;
	}

	if (preg_match('/^\s*(?:[-*+\x{2022}]|\d+[.\)]|\[\d+\])\s+(.*)$/u', $line, $m))
	{
		if (trim($current) !== '') { $items[] = $current; }
		$current = $m[1];
	}
	else
	{
		$current = ($current === '') ? trim($line) : $current . ' ' . trim($line);
	}
}
if (trim($current) !== '') { $items[] = $current; }

// 3. Keep reference-looking items (must contain a 4-digit year) as unstructured CSL.
$bibliography = array();
$n = 0;
foreach ($items as $item)
{
	$text = md_clean($item);
	if (!preg_match('/\b(1[5-9]\d\d|20\d\d)\b/', $text)) { continue; }

	$n++;
	$work = new stdclass;
	$work->id = $parts['filename'] . '#' . $n;
	$work->unstructured = $text;
	$bibliography[] = $work;
}

if (count($bibliography) == 0) { exit(0); }

file_put_contents(
	$parts['filename'] . '-references.json',
	json_encode($bibliography, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n"
);

?>
