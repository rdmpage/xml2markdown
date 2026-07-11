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

$xml = file_get_contents($filename);

$dom= new DOMDocument;
$dom->loadXML($xml);
$xpath = new DOMXPath($dom);

$xpath->registerNamespace("xlink", "http://www.w3.org/1999/xlink");

// test whether this is a PMC document
$pmcid = '';
foreach ($xpath->query ('//article-id[@pub-id-type="pmc"]') as $pmcid_node)
{
	$pmcid = $pmcid_node->firstChild->nodeValue;
}

// get figures
foreach ($xpath->query ('//fig/graphic') as $graphic_node)
{
	$graphic = new stdclass;

	$href = $graphic_node->getAttribute('xlink:href');
	
	echo "href=$href\n";
	
	foreach ($xpath->query ('uri', $graphic_node) as $uri_node)
	{
		$graphic->uri = $uri_node->firstChild->nodeValue;
	}
	
	// Do we have a direct link to the image?
	if (!isset($graphic->url))
	{
		if (preg_match('/^http/', $href))
		{
			$graphic->url = $href;			
		}
	}
		
	// Pensoft figures have image URLs stored as <uri>
	if (isset($graphic->uri) && preg_match('/^http/', $graphic->uri))
	{
		$graphic->url = $graphic->uri;
	}
		
	// PlosONE
	// Construct URL the image
	if (preg_match('/journal.pone/', $href))
	{
		$graphic->url = 'https://journals.plos.org/plosone/article/figure/image?size=large&id=' . $href;
		$graphic->filename = str_replace('info:doi/10.1371/', '', $href);
	}
	
	// PMC
	// Image is part of PMC download
	if (!isset($graphic->url) && $pmcid != "")
	{
		$graphic->filename = $href . '.jpg';
		$graphic->url = 'file://' . $xml_file_parts['dirname'] . '/' . $graphic->filename;
	}
	
	// If we don't have an explict name for the image file, construct one from the image URL
	if (isset($graphic->url) && !isset($graphic->filename))
	{
		$parts = parse_url($graphic->url);			
		if (isset($parts['path']))
		{
			$graphic->filename = basename($parts['path']) . "\n";
		}	
	}
	
	if (isset($graphic->filename))
	{
		$graphic->filename = sanitise_filename($graphic->filename);
	}
	
	// fetch
	if (isset($graphic->url))
	{
		// Fetch from a server, make sure file has extension
		if (preg_match('/^http/', $graphic->url))
		{
			$image = get($graphic->url);
			file_put_contents($graphic->filename, $image);
			
			// does it have an image extension?
			if (!preg_match('/\.(gif|jpeg|jpg|png|tif|tiff|webp)$/', $graphic->filename))
			{
				$image_type = exif_imagetype($graphic->filename);
				if ($image_type)
				{
					$mime_type = image_type_to_mime_type($image_type);
					rename($graphic->filename, $graphic->filename . '.' . mime2ext($mime_type));
				}
			}
		}
		
		// We have image file downloaded (e.g., PMC)
		if (preg_match('/^file:\/\/(.*)$/', $graphic->url, $m))
		{
			copy($m[1], $graphic->filename);		
		}
	
	}
	
	
	print_r($graphic);
}


/*
      <!-- PLoS -->
      <xsl:when test="contains(graphic/@xlink:href, 'journal.pone')">
        <xsl:value-of select="concat('https://journals.plos.org/plosone/article/figure/image?size=large&amp;id=', graphic/@xlink:href)" />
      </xsl:when>

      <!-- Wellcome -->
      <xsl:when test="contains(graphic/@xlink:href, 'wellcomeopenresearch.s3.eu-west-1.amazonaws.com')">
        <xsl:value-of select="concat( substring-before(graphic/@xlink:href, 'wellcomeopenresearch.s3.eu-west-1.amazonaws.com'), 'wellcomeopenresearch-files.f1000.com', substring-after(graphic/@xlink:href, 'wellcomeopenresearch.s3.eu-west-1.amazonaws.com') )" />
      </xsl:when>
 
      <!-- Pensoft -->
      <xsl:when test="contains(graphic/@xlink:href, 'ZooKeys')">
        <xsl:value-of select="graphic/uri" />
      </xsl:when>

      <xsl:when test="graphic/uri/@content-type='original_file'">
        <xsl:value-of select="graphic/uri" />
      </xsl:when>
     
      <!-- PMC -->
      <xsl:when test="//article-id[@pub-id-type='pmc']">
        <xsl:value-of select="concat('PMC', //article-id[@pub-id-type='pmc'], '/', graphic/@xlink:href, '.jpg')" /> 
      </xsl:when>
*/