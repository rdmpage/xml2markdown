<?php

// Extract tables from JATS XML or HTML and export as CSV files

require_once(dirname(__FILE__) . '/utils.php');

//----------------------------------------------------------------------------------------
function html_table_to_array($html) 
{
    libxml_use_internal_errors(true);

    $dom = new DOMDocument();
    $dom->loadHTML('<?xml encoding="UTF-8">' . $html);

    $xpath = new DOMXPath($dom);
    $rows = $xpath->query('//table//tr');

    $grid = [];
    $rowIndex = 0;
    $rowspans = [];

    foreach ($rows as $tr) 
    {
        $grid[$rowIndex] = [];
        $colIndex = 0;

        foreach ($tr->childNodes as $cell) 
        {
            if (!in_array($cell->nodeName, ['td', 'th'])) 
            {
                continue;
            }

            while (isset($rowspans[$rowIndex][$colIndex])) 
            {
                $grid[$rowIndex][$colIndex] = $rowspans[$rowIndex][$colIndex];
                unset($rowspans[$rowIndex][$colIndex]);
                $colIndex++;
            }

            $text = trim(preg_replace('/\s+/', ' ', $cell->textContent));

            $colspan = $cell->hasAttribute('colspan')
                ? max(1, intval($cell->getAttribute('colspan')))
                : 1;

            $rowspan = $cell->hasAttribute('rowspan')
                ? max(1, intval($cell->getAttribute('rowspan')))
                : 1;

            for ($c = 0; $c < $colspan; $c++) {
                $grid[$rowIndex][$colIndex + $c] = $text;

                for ($r = 1; $r < $rowspan; $r++) {
                    $rowspans[$rowIndex + $r][$colIndex + $c] = $text;
                }
            }

            $colIndex += $colspan;
        }

        while (isset($rowspans[$rowIndex][$colIndex])) 
        {
            $grid[$rowIndex][$colIndex] = $rowspans[$rowIndex][$colIndex];
            unset($rowspans[$rowIndex][$colIndex]);
            $colIndex++;
        }

        ksort($grid[$rowIndex]);
        $rowIndex++;
    }

    return $grid;
}

//----------------------------------------------------------------------------------------
function html_table_to_csv($html, $csv_filename) 
{
    $rows = html_table_to_array($html);

    $maxCols = 0;
    foreach ($rows as $row) 
    {
        $maxCols = max($maxCols, count($row));
    }

    $fh = fopen($csv_filename, 'w');

    foreach ($rows as $row) 
    {
        $csvRow = [];

        for ($i = 0; $i < $maxCols; $i++) 
        {
            $csvRow[] = isset($row[$i]) ? $row[$i] : '';
        }

        fputcsv($fh, $csvRow);
    }

    fclose($fh);
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

$xml = file_get_contents($filename);

$xml_file_parts = pathinfo($filename);

$dom= new DOMDocument;
$dom->loadXML($xml);
$xpath = new DOMXPath($dom);


// JATS should have table-wrap with local identifier
$table_wraps = $xpath->query('//table-wrap');

if (count($table_wraps) > 0)
{
	$table_count = 0;
	
	foreach ($table_wraps as $table_wrap) 
	{
		$id = $table_wrap->getAttribute('id');
		
		if ($id == '')
		{
			$id = $table_count++;
		}
		
		$table_filename = sanitise_filename($id . '.csv');
	
		foreach ($xpath->query('.//table', $table_wrap) as $table)
		{
			$html = $dom->saveXML($table);
		
			html_table_to_csv($html, $table_filename);
		}
	}
}
else
{
	// HTML will just have table
	$table_count = 0;
	
	foreach ($xpath->query('//table') as $table)
	{
		// Number tables from 1 to be consistent with how they are likely numbered in the document
		$table_count++;
		
		$table_filename = sanitise_filename('table-' . $table_count . '.csv');
		
		$html = $dom->saveXML($table);
		
		html_table_to_csv($html, $table_filename);
		
		
	}
	
	
	

}