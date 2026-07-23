<?php

// Convert an .xlsx spreadsheet to CSV — one file per worksheet, written to the
// current directory as "<basename>-<sheet name>.csv". Dependency-free: an .xlsx
// is a zip of XML, read here with ZipArchive + DOMDocument.

require_once(dirname(__FILE__) . '/utils.php');

//----------------------------------------------------------------------------------------
// Column letters ("A", "AB") from a cell ref -> zero-based column index.
function xlsx_col_index($ref)
{
	$letters = preg_replace('/[0-9]+/', '', $ref);
	$n = 0;
	$len = strlen($letters);
	for ($i = 0; $i < $len; $i++)
	{
		$n = $n * 26 + (ord($letters[$i]) - ord('A') + 1);
	}
	return $n - 1;
}

//----------------------------------------------------------------------------------------
// Shared strings table: cells of type "s" hold an index into this list.
function xlsx_shared_strings($zip)
{
	$strings = array();
	$data = $zip->getFromName('xl/sharedStrings.xml');
	if ($data === false) { return $strings; }

	$dom = new DOMDocument();
	$dom->loadXML($data);

	foreach ($dom->getElementsByTagName('si') as $si)
	{
		// Concatenate every <t> in the item (covers rich-text runs).
		$text = '';
		foreach ($si->getElementsByTagName('t') as $t)
		{
			$text .= $t->textContent;
		}
		$strings[] = $text;
	}
	return $strings;
}

//----------------------------------------------------------------------------------------
// Worksheets in workbook (display) order: [ ['name' => ..., 'path' => ...], ... ].
function xlsx_sheets($zip)
{
	$sheets = array();

	$wb = $zip->getFromName('xl/workbook.xml');
	if ($wb === false) { return $sheets; }

	// r:id -> worksheet part, from the workbook relationships.
	$rid_target = array();
	$rels = $zip->getFromName('xl/_rels/workbook.xml.rels');
	if ($rels !== false)
	{
		$rdom = new DOMDocument();
		$rdom->loadXML($rels);
		foreach ($rdom->getElementsByTagName('Relationship') as $rel)
		{
			$rid_target[$rel->getAttribute('Id')] = $rel->getAttribute('Target');
		}
	}

	$dom = new DOMDocument();
	$dom->loadXML($wb);

	$i = 0;
	foreach ($dom->getElementsByTagName('sheet') as $sheet)
	{
		$i++;
		$name = $sheet->getAttribute('name');

		$rid = $sheet->getAttributeNS('http://schemas.openxmlformats.org/officeDocument/2006/relationships', 'id');
		if ($rid === '') { $rid = $sheet->getAttribute('r:id'); }

		$target = isset($rid_target[$rid]) ? $rid_target[$rid] : '';
		if ($target === '')
		{
			$path = 'xl/worksheets/sheet' . $i . '.xml';   // fall back to positional
		}
		else if (substr($target, 0, 1) === '/')
		{
			$path = ltrim($target, '/');                   // absolute in package
		}
		else
		{
			$path = 'xl/' . $target;                       // relative to xl/
		}

		$sheets[] = array('name' => $name, 'path' => $path);
	}
	return $sheets;
}

//----------------------------------------------------------------------------------------
// Number formats from xl/styles.xml. Returns:
//   ['xf'   => [cellXf index -> numFmtId],   // a cell's s="" attribute indexes this
//    'code' => [numFmtId -> format code]]    // custom (>=164) format strings
function xlsx_styles($zip)
{
	$xf = array();
	$code = array();

	$data = $zip->getFromName('xl/styles.xml');
	if ($data === false) { return array('xf' => $xf, 'code' => $code); }

	$dom = new DOMDocument();
	$dom->loadXML($data);

	// Custom format codes.
	foreach ($dom->getElementsByTagName('numFmt') as $nf)
	{
		$code[(int) $nf->getAttribute('numFmtId')] = $nf->getAttribute('formatCode');
	}

	// Cell formats — the <xf> children of <cellXfs> (NOT cellStyleXfs).
	$cellXfs = $dom->getElementsByTagName('cellXfs')->item(0);
	if ($cellXfs)
	{
		$i = 0;
		foreach ($cellXfs->getElementsByTagName('xf') as $x)
		{
			$xf[$i] = (int) $x->getAttribute('numFmtId');
			$i++;
		}
	}
	return array('xf' => $xf, 'code' => $code);
}

//----------------------------------------------------------------------------------------
// Workbooks default to the 1900 date system; old Mac files use 1904.
function xlsx_is_1904($zip)
{
	$wb = $zip->getFromName('xl/workbook.xml');
	if ($wb === false) { return false; }
	$dom = new DOMDocument();
	$dom->loadXML($wb);
	$pr = $dom->getElementsByTagName('workbookPr')->item(0);
	return $pr && ($pr->getAttribute('date1904') === '1' || $pr->getAttribute('date1904') === 'true');
}

//----------------------------------------------------------------------------------------
// Classify a number format as 'date', 'time', 'datetime', or '' (not a date).
// Built-in date/time ids are known; custom codes are sniffed for date/time tokens.
function xlsx_format_kind($numFmtId, $code)
{
	if (in_array($numFmtId, array(14, 15, 16, 17, 22), true)) { return ($numFmtId === 22) ? 'datetime' : 'date'; }
	if (in_array($numFmtId, array(18, 19, 20, 21, 45, 46, 47), true)) { return 'time'; }
	if ($code === '') { return ''; }

	// Drop [colour]/[locale]/[elapsed], "quoted text" and \escapes before sniffing.
	$c = preg_replace('/\[[^\]]*\]/', '', $code);
	$c = preg_replace('/"[^"]*"/', '', $c);
	$c = preg_replace('#\\\\.#', '', $c);
	$lc = strtolower($c);

	$has_y   = strpos($lc, 'y') !== false;
	$has_d   = strpos($lc, 'd') !== false;
	$has_mmm = strpos($lc, 'mmm') !== false;   // month name -> unambiguously a date
	$has_h   = strpos($lc, 'h') !== false;
	$has_s   = strpos($lc, 's') !== false;

	if ($has_y || $has_d || $has_mmm) { return ($has_h || $has_s) ? 'datetime' : 'date'; }
	if ($has_h || $has_s) { return 'time'; }
	return '';   // bare "m"/"mm" is ambiguous -> leave as a number
}

//----------------------------------------------------------------------------------------
// Convert an Excel date serial to an ISO string for the given kind.
function xlsx_serial_to_string($serial, $kind, $is1904)
{
	if ($serial === '' || !is_numeric($serial)) { return $serial; }
	$serial = (float) $serial;

	if ($kind === 'time')
	{
		$secs = (int) round(($serial - floor($serial)) * 86400);
		$secs = (($secs % 86400) + 86400) % 86400;
		return gmdate('H:i:s', $secs);
	}

	// 25569 = serial of 1970-01-01 in the 1900 system; 24107 in the 1904 system.
	$base = $is1904 ? 24107 : 25569;
	$ts = (int) round(($serial - $base) * 86400);

	return ($kind === 'datetime') ? gmdate('Y-m-d H:i:s', $ts) : gmdate('Y-m-d', $ts);
}

//----------------------------------------------------------------------------------------
// Read one worksheet into a dense array of rows (each a 0..maxCol array).
function xlsx_read_sheet($zip, $path, $strings, $styles, $is1904)
{
	$rows = array();
	$data = $zip->getFromName($path);
	if ($data === false) { return $rows; }

	$dom = new DOMDocument();
	$dom->loadXML($data);

	foreach ($dom->getElementsByTagName('row') as $row)
	{
		$cells = array();
		$max_col = -1;
		$auto_col = 0;

		foreach ($row->getElementsByTagName('c') as $c)
		{
			$ref = $c->getAttribute('r');
			$col = ($ref !== '') ? xlsx_col_index($ref) : $auto_col;
			$auto_col = $col + 1;

			$type = $c->getAttribute('t');
			$val = '';

			if ($type === 'inlineStr')
			{
				$is = $c->getElementsByTagName('is')->item(0);
				if ($is)
				{
					foreach ($is->getElementsByTagName('t') as $t) { $val .= $t->textContent; }
				}
			}
			else
			{
				$v = $c->getElementsByTagName('v')->item(0);
				$raw = $v ? $v->textContent : '';

				if ($type === 's')
				{
					$idx = (int) $raw;
					$val = isset($strings[$idx]) ? $strings[$idx] : '';
				}
				else
				{
					$val = $raw;   // number / formula string / boolean / error

					// A date/time is a number carrying a date-formatted style; convert it.
					if (($type === '' || $type === 'n') && $raw !== '')
					{
						$s = $c->getAttribute('s');
						if ($s !== '')
						{
							$fmt  = isset($styles['xf'][(int) $s]) ? $styles['xf'][(int) $s] : 0;
							$code = isset($styles['code'][$fmt]) ? $styles['code'][$fmt] : '';
							$kind = xlsx_format_kind($fmt, $code);
							if ($kind !== '')
							{
								$val = xlsx_serial_to_string($raw, $kind, $is1904);
							}
						}
					}
				}
			}

			$cells[$col] = $val;
			if ($col > $max_col) { $max_col = $col; }
		}

		$dense = array();
		for ($i = 0; $i <= $max_col; $i++)
		{
			$dense[] = isset($cells[$i]) ? $cells[$i] : '';
		}
		$rows[] = $dense;
	}
	return $rows;
}

//----------------------------------------------------------------------------------------

$filename = '';
if ($argc < 2)
{
	echo "Usage: " . basename(__FILE__) . " <filename.xlsx>\n";
	exit(1);
}
else
{
	$filename = $argv[1];
}

$xml_file_parts = pathinfo($filename);

$zip = new ZipArchive();
if ($zip->open($filename) !== true)
{
	fwrite(STDERR, "Could not open '$filename' as an .xlsx (zip) file\n");
	exit(1);
}

$strings = xlsx_shared_strings($zip);
$styles  = xlsx_styles($zip);
$is1904  = xlsx_is_1904($zip);
$sheets  = xlsx_sheets($zip);

$sheet_number = 0;
foreach ($sheets as $sheet)
{
	$sheet_number++;

	$rows = xlsx_read_sheet($zip, $sheet['path'], $strings, $styles, $is1904);
	if (count($rows) == 0) { continue; }   // skip empty sheets

	// widest row -> pad every row to a rectangle
	$cols = 0;
	foreach ($rows as $r) { $cols = max($cols, count($r)); }

	$sheet_name = ($sheet['name'] !== '') ? $sheet['name'] : ('sheet' . $sheet_number);
	$csv_filename = sanitise_filename($xml_file_parts['filename'] . '-' . $sheet_name . '.csv');

	$fh = fopen($csv_filename, 'w');
	foreach ($rows as $r)
	{
		$out = array();
		for ($i = 0; $i < $cols; $i++)
		{
			$out[] = isset($r[$i]) ? $r[$i] : '';
		}
		fputcsv($fh, $out);
	}
	fclose($fh);
}

$zip->close();
