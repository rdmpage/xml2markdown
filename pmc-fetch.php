<?php

// Fetch PMC record

require_once(dirname(__FILE__) . '/utils.php');

//----------------------------------------------------------------------------------------
// Stream a URL to a file (curl handles the ftp:// links the OA service returns).
function download_to_file($url, $dest)
{
	$fp = fopen($dest, 'w');
	if (!$fp) { return false; }

	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_FILE, $fp);
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
	curl_setopt($ch, CURLOPT_FAILONERROR, 1);
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
	curl_setopt($ch, CURLOPT_TIMEOUT, 600);
	curl_setopt($ch, CURLOPT_USERAGENT, 'xml2markdown/1.0 (+https://github.com/rdmpage/xml2markdown)');

	$ok  = curl_exec($ch);
	$err = curl_error($ch);
	curl_close($ch);
	fclose($fp);

	if (!$ok) { echo "  download error: $err\n"; @unlink($dest); return false; }
	return true;
}

//----------------------------------------------------------------------------------------
// Recursively delete a directory (named to avoid clashing with process_watch.php).
function pmc_remove_dir($path)
{
	foreach (scandir($path) as $f)
	{
		if ($f === '.' || $f === '..') { continue; }
		$p = $path . '/' . $f;
		is_dir($p) ? pmc_remove_dir($p) : unlink($p);
	}
	rmdir($path);
}

$pmc = '';
if ($argc < 2)
{
	echo "Usage: " . basename(__FILE__) . " <PMC number (including PMC prefix)>\n";
	exit(1);
}
else
{
	$pmc = $argv[1];
}

if (!preg_match('/^PMC\d+$/', $pmc))
{
	echo "$pmc does not look like a PMC number\n";
	exit(1);
}

// The PMC Open Access subset is a world-readable S3 bucket (HTTPS, no auth),
// organised by article version under the prefix "PMC<id>.<version>/". The old
// FTP/oa_package tar.gz distribution is deprecated and being removed. So: list
// the objects for this article, pick the highest version, and download its files
// into watch/<PMC>/ for process_watch.php to pick up.
//   https://pmc.ncbi.nlm.nih.gov/tools/pmcaws/
$bucket = 'https://pmc-oa-opendata.s3.amazonaws.com';

// 1. List objects for this article (the trailing dot keeps us to this exact id,
//    not e.g. PMC114503810).
$list = get($bucket . '/?list-type=2&prefix=' . rawurlencode($pmc . '.') . '&max-keys=1000');

$dom = new DOMDocument;
$dom->loadXML($list);
$xpath = new DOMXPath($dom);

$keys = array();
foreach ($xpath->query('//*[local-name()="Key"]') as $k)   // ListBucketResult is namespaced
{
	$keys[] = $k->textContent;
}

if (count($keys) == 0)
{
	echo "$pmc not found in the PMC Open Access subset (it may not be open access)\n";
	exit(1);
}

// 2. Highest available version.
$version = 0;
foreach ($keys as $key)
{
	if (preg_match('#^' . preg_quote($pmc, '#') . '\.(\d+)/#', $key, $m))
	{
		$version = max($version, (int) $m[1]);
	}
}
$prefix = $pmc . '.' . $version . '/';

// 3. Download that version's files into watch/<PMC>/.
$watch = dirname(__FILE__) . '/watch';
if (!is_dir($watch)) { mkdir($watch, 0777, true); }

$dest = $watch . '/' . $pmc;
if (is_dir($dest)) { pmc_remove_dir($dest); }   // replace any previous copy
mkdir($dest, 0777, true);

echo "Fetching $pmc (version $version) -> watch/$pmc\n";

$count = 0;
foreach ($keys as $key)
{
	if (strpos($key, $prefix) !== 0) { continue; }

	// Encode each path segment but keep the slashes.
	$encoded = implode('/', array_map('rawurlencode', explode('/', $key)));

	if (download_to_file($bucket . '/' . $encoded, $dest . '/' . basename($key)))
	{
		echo "  " . basename($key) . "\n";
		$count++;
	}
}

if ($count == 0)
{
	echo "No files downloaded for $pmc\n";
	exit(1);
}

echo "Placed watch/$pmc ($count files) — run process_watch.php to process it\n";