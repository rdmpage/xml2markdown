<?php
// process_watch.php
//
// Watch-folder orchestrator. Scans watch/ for new source and processes each
// item into its own bundle folder under output/. One-shot: processes everything
// currently in watch/, then exits (run from cron or by hand).
//
//   Files   in watch/ are treated as XML: the format is sniffed (JATS/BioC/TEI/
//           Wiley) and dispatched. Only JATS is wired up so far; anything else
//           (e.g. a PDF) is treated as unprocessable.
//   Folders in watch/ are treated as OCR-tool output (HTML + images): the folder
//           is copied to output/<name>/ and html2markdown + tables run inside it.
//           (If a folder contains XML instead of HTML it is routed to the XML
//           pipeline, so PMC-style folders also work.)
//
// Folders:
//   watch/   new source (XML files, or folders of HTML + images from OCR tools)
//   output/  one self-contained bundle per input; the source is moved in on success
//   failed/  source we could not process
//
// "New" just means "currently in watch/" — there is no state file.

require_once(dirname(__FILE__) . '/utils.php');

$ROOT       = dirname(__FILE__);
$WATCH_DIR  = $ROOT . '/watch';
$OUTPUT_DIR = $ROOT . '/output';
$FAILED     = $ROOT . '/failed';

foreach ([$OUTPUT_DIR, $FAILED] as $d)
{
	if (!is_dir($d)) { mkdir($d, 0777, true); }
}

//----------------------------------------------------------------------------------------
// Sniff the flavour of an XML document from its DOCTYPE / namespaces.
// Mirrors the Python xml_types table; returns '' if unrecognised.
function detect_xml_type($xml)
{
	// Plazi treatment XML carries a MODS metadata block (and a <document>/
	// <treatment> structure); TaxPub/JATS with taxon markup does not, even though
	// it can share the TaxonX DOCTYPE. Check MODS first so a Plazi treatment is
	// not mis-detected as jats. Scan the whole string in case MODS is declared
	// deep in the document.
	if (strpos($xml, 'loc.gov/mods/v3') !== false)
	{
		return 'plazi';
	}

	$head = substr($xml, 0, 4000);

	$xml_types = [
		'bioc'  => '/BioC\.dtd/i',
		'jats'  => '/(NLM|TaxonX)\/\/DTD/i',
		'tei'   => '/www\.tei-c\.org\/ns/i',
		'wiley' => '/www\.wiley\.com\/namespaces/i',
	];

	foreach ($xml_types as $type => $re)
	{
		if (preg_match($re, $head))
		{
			return $type;
		}
	}

	// Fallback: a bare <article> root with no DOCTYPE is almost always JATS.
	if (preg_match('/<article[\s>]/', $head))
	{
		return 'jats';
	}

	return '';
}

//----------------------------------------------------------------------------------------
// Run one of the sub-scripts as a child process with its working directory set
// to $cwd (that is where the script writes its output). Returns [exit_code, stdout, stderr].
function run_tool($script, $input_abs, $cwd)
{
	$cmd = 'php ' . escapeshellarg($script) . ' ' . escapeshellarg($input_abs);

	$descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
	$proc = proc_open($cmd, $descriptors, $pipes, $cwd);

	if (!is_resource($proc))
	{
		return [1, '', "could not start: $cmd"];
	}

	$out = stream_get_contents($pipes[1]); fclose($pipes[1]);
	$err = stream_get_contents($pipes[2]); fclose($pipes[2]);
	$code = proc_close($proc);

	return [$code, $out, $err];
}

//----------------------------------------------------------------------------------------
// Run a list of tools; return true only if every one exited 0.
function run_tools($scripts, $input_abs, $cwd, $root)
{
	$ok = true;

	foreach ($scripts as $script)
	{
		list($code, $out, $err) = run_tool($root . '/' . $script, $input_abs, $cwd);

		if ($code !== 0)
		{
			$ok = false;
			echo "    ! $script exited $code" . ($err ? ": " . trim($err) : "") . "\n";
		}
		else
		{
			echo "    - $script ok\n";
		}
	}

	return $ok;
}

//----------------------------------------------------------------------------------------
// Recursively copy a directory.
function copy_dir($src, $dst)
{
	if (!is_dir($dst)) { mkdir($dst, 0777, true); }

	foreach (scandir($src) as $item)
	{
		if ($item === '.' || $item === '..') { continue; }

		$s = $src . '/' . $item;
		$d = $dst . '/' . $item;

		if (is_dir($s)) { copy_dir($s, $d); }
		else            { copy($s, $d); }
	}
}

//----------------------------------------------------------------------------------------
// Recursively delete a file or directory.
function rrmdir($path)
{
	if (is_dir($path))
	{
		foreach (scandir($path) as $item)
		{
			if ($item === '.' || $item === '..') { continue; }
			rrmdir($path . '/' . $item);
		}
		rmdir($path);
	}
	else if (file_exists($path))
	{
		unlink($path);
	}
}

//----------------------------------------------------------------------------------------
// Move a file/folder into $dest_dir, avoiding name collisions.
function move_into($src, $dest_dir)
{
	$name = basename($src);
	$dest = $dest_dir . '/' . $name;

	if (file_exists($dest))
	{
		$dest = $dest_dir . '/' . $name . '-' . time();
	}

	rename($src, $dest);
	return $dest;
}

//----------------------------------------------------------------------------------------
// Find top-level files with one of the given extensions inside a folder.
function find_by_ext($dir, $exts)
{
	$hits = [];
	foreach (scandir($dir) as $item)
	{
		if ($item === '.' || $item === '..') { continue; }
		$path = $dir . '/' . $item;
		if (!is_file($path)) { continue; }

		$ext = strtolower(pathinfo($item, PATHINFO_EXTENSION));
		if (in_array($ext, $exts)) { $hits[] = $path; }
	}
	return $hits;
}

//----------------------------------------------------------------------------------------
// Process a single XML file into output/<basename>/ using the JATS toolchain.
// Returns [bool ok, string|null output_dir]. output_dir is null when we never
// created one (e.g. an unsupported format such as a PDF).
function process_xml_file($input_abs, $ROOT, $OUTPUT_DIR)
{
	$xml  = file_get_contents($input_abs);
	$type = detect_xml_type($xml);

	if ($type === '')
	{
		echo "  unrecognised / unsupported format — cannot process.\n";
		return [false, null, false];
	}

	echo "  XML type: $type\n";

	switch ($type)
	{
		case 'jats':
			// Markdown, tables (CSV), references (CSL-JSON), images.
			$scripts = ['jats2markdown.php', 'tables.php', 'references.php', 'images.php'];
			break;

		case 'plazi':
			// Plazi treatment -> Markdown + references (unstructured, CSL-ish JSON) + figure images.
			$scripts = ['plazi2markdown.php', 'plazi-references.php', 'plazi-images.php'];
			break;

		default:
			echo "  no handler for '$type' yet.\n";
			return [false, null, false];
	}

	$id  = pathinfo($input_abs, PATHINFO_FILENAME);
	$out = $OUTPUT_DIR . '/' . $id;
	if (!is_dir($out)) { mkdir($out, 0777, true); }

	$ok = run_tools($scripts, $input_abs, $out, $ROOT);

	return [$ok, $out, false];
}

//----------------------------------------------------------------------------------------
// Process a PDF via datalab.to into output/<basename>/.
// datalab.php writes its .html/.json next to the input PDF and images to the
// working directory, so we copy the PDF into the bundle and run it there — that
// lands every output in output/<id>/ and folds the source PDF in. The HTML is
// then passed through tables.php. Returns [bool ok, string output_dir, bool source_in_bundle].
// True if a .docx actually contains a table, so we only spend a datalab call on
// Word docs worth extracting. A .docx is a zip; tables appear as <w:tbl...> in
// word/document.xml.
function docx_has_table($path)
{
	if (!class_exists('ZipArchive')) { return true; }   // can't inspect -> assume yes
	$zip = new ZipArchive();
	if ($zip->open($path) !== true) { return false; }
	$doc = $zip->getFromName('word/document.xml');
	$zip->close();
	return $doc !== false && strpos($doc, '<w:tbl') !== false;
}

// Convert a document (PDF, Word, ...) that already sits in $out to HTML via
// datalab, then Markdown + tables. datalab writes .html/.json next to the input
// and images to the working dir, so with the doc in $out (cwd=$out) everything
// lands in the bundle. datalab die()s on API errors (exit 0), so verify the
// .html artefact rather than trust the exit code. Returns true on success.
function convert_document($doc_abs, $out, $ROOT)
{
	echo "  datalab: " . basename($doc_abs) . "\n";
	list($code, $stdout, $stderr) = run_tool($ROOT . '/datalab.php', $doc_abs, $out);
	if ($stderr) { echo "    " . trim($stderr) . "\n"; }

	$html = $out . '/' . pathinfo($doc_abs, PATHINFO_FILENAME) . '.html';
	if (!is_file($html) || filesize($html) === 0)
	{
		echo "    ! no HTML for " . basename($doc_abs) . " (unsupported format / API error / no key).\n";
		return false;
	}
	echo "    - ok (" . basename($html) . ")\n";

	// Markdown + tables (CSV) from the HTML — roughly the JATS-equivalent output.
	return run_tools(['html2markdown.php', 'tables.php'], $html, $out, $ROOT);
}

// Process a standalone PDF into output/<basename>/: copy it into the bundle and
// convert it. Returns [bool ok, string output_dir, bool source_in_bundle].
function process_pdf_file($input_abs, $ROOT, $OUTPUT_DIR)
{
	$id  = pathinfo($input_abs, PATHINFO_FILENAME);
	$out = $OUTPUT_DIR . '/' . $id;
	if (!is_dir($out)) { mkdir($out, 0777, true); }

	// Copy the PDF into the bundle so datalab writes its siblings here.
	$pdf_in_bundle = $out . '/' . basename($input_abs);
	copy($input_abs, $pdf_in_bundle);

	$ok = convert_document($pdf_in_bundle, $out, $ROOT);

	return [$ok, $out, true];
}

// Process a standalone .xlsx into output/<basename>/: copy it in and split each
// worksheet to CSV (no datalab call needed). Returns [ok, output_dir, source_in_bundle].
function process_excel_file($input_abs, $ROOT, $OUTPUT_DIR)
{
	$id  = pathinfo($input_abs, PATHINFO_FILENAME);
	$out = $OUTPUT_DIR . '/' . $id;
	if (!is_dir($out)) { mkdir($out, 0777, true); }

	$xlsx_in_bundle = $out . '/' . basename($input_abs);
	copy($input_abs, $xlsx_in_bundle);

	echo "  excel2csv: " . basename($input_abs) . "\n";
	$ok = run_tools(['excel2csv.php'], $xlsx_in_bundle, $out, $ROOT);

	return [$ok, $out, true];
}

//----------------------------------------------------------------------------------------
// Process a folder (OCR HTML + images, or an XML + images bundle).
// The folder is copied to output/<name>/ and the tools run inside the copy.
// Returns [bool ok, string output_dir].
function process_folder($src_abs, $ROOT, $OUTPUT_DIR)
{
	$name = basename($src_abs);
	$out  = $OUTPUT_DIR . '/' . $name;

	echo "  copying folder -> output/" . $name . "\n";
	copy_dir($src_abs, $out);
	$source_in_bundle = true;

	// Prefer HTML (OCR output), then XML (open-access PMC), then documents
	// (non-open-access PMC: a main PDF plus Office supplementary files).
	$html = find_by_ext($out, ['html', 'htm']);
	$xmls = find_by_ext($out, ['xml', 'nxml']);
	$docs = find_by_ext($out, ['pdf', 'docx', 'doc', 'pptx', 'xlsx']);

	$ok = true;

	if (count($html) > 0)
	{
		foreach ($html as $h)
		{
			echo "  html: " . basename($h) . "\n";
			$ok = run_tools(['html2markdown.php', 'tables.php'], $h, $out, $ROOT) && $ok;
		}
	}
	else if (count($xmls) > 0)
	{
		foreach ($xmls as $x)
		{
			echo "  xml: " . basename($x) . "\n";
			$ok = run_tools(['jats2markdown.php', 'tables.php', 'references.php', 'images.php'], $x, $out, $ROOT) && $ok;
		}
	}
	else if (count($docs) > 0)
	{
		// Convert each document via datalab. For Word docs only spend a call when
		// the file actually contains a table (the reason we'd send it). The
		// folder counts as processed if at least one document converted.
		$converted = 0;
		foreach ($docs as $doc)
		{
			$ext = strtolower(pathinfo($doc, PATHINFO_EXTENSION));

			// Spreadsheets convert straight to CSV — no datalab call needed.
			if ($ext === 'xlsx')
			{
				echo "  excel2csv: " . basename($doc) . "\n";
				if (run_tools(['excel2csv.php'], $doc, $out, $ROOT)) { $converted++; }
				continue;
			}

			// Word docs: only worth a datalab call if they actually have a table.
			if ($ext === 'docx' && !docx_has_table($doc))
			{
				echo "  skip (no table): " . basename($doc) . "\n";
				continue;
			}
			if (convert_document($doc, $out, $ROOT)) { $converted++; }
		}
		$ok = ($converted > 0);
	}
	else
	{
		echo "  ! no HTML, XML, or convertible document found in folder.\n";
		$ok = false;
	}

	return [$ok, $out, $source_in_bundle];
}

//----------------------------------------------------------------------------------------
// Main: scan watch/ and process each entry.

if (!is_dir($WATCH_DIR))
{
	echo "watch/ does not exist: $WATCH_DIR\n";
	exit(1);
}

$entries = array_diff(scandir($WATCH_DIR), ['.', '..']);
$count = 0;

foreach ($entries as $entry)
{
	// Skip hidden / bookkeeping files (e.g. .DS_Store).
	if (substr($entry, 0, 1) === '.') { continue; }

	$path = $WATCH_DIR . '/' . $entry;
	$is_dir = is_dir($path);
	$count++;

	echo "\n== $entry ==\n";

	$ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

	if ($is_dir)
	{
		list($ok, $out, $source_in_bundle) = process_folder($path, $ROOT, $OUTPUT_DIR);
	}
	else if ($ext === 'pdf')
	{
		list($ok, $out, $source_in_bundle) = process_pdf_file($path, $ROOT, $OUTPUT_DIR);
	}
	else if ($ext === 'xlsx')
	{
		list($ok, $out, $source_in_bundle) = process_excel_file($path, $ROOT, $OUTPUT_DIR);
	}
	else
	{
		list($ok, $out, $source_in_bundle) = process_xml_file($path, $ROOT, $OUTPUT_DIR);
	}

	if ($ok)
	{
		// Make output/<id>/ self-contained. Processors that already copied the
		// source in (folders, PDFs) just need the watch copy removed; others get
		// the source moved in.
		if ($source_in_bundle)
		{
			rrmdir($path);
		}
		else
		{
			rename($path, $out . '/' . basename($path));
		}
		echo "  done -> output/" . basename($out) . "\n";
	}
	else
	{
		// Remove any half-built output, then set the source aside.
		if ($out !== null && is_dir($out)) { rrmdir($out); }
		$moved = move_into($path, $FAILED);
		echo "  FAILED -> failed/" . basename($moved) . "\n";
	}
}

if ($count === 0)
{
	echo "watch/ is empty — nothing to do.\n";
}
else
{
	echo "\nProcessed $count item(s).\n";
}
