<?php

// Extract images from Plazi record

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

// get figures
foreach ($xpath->query ('//caption') as $graphic_node)
{
	$graphic = new stdclass;
	
	$graphic->id = $graphic_node->getAttribute('id');
	$graphic->filename = $graphic->id;
	
	$zenodo_id = $graphic_node->getAttribute('ID-Zenodo-Dep');
	if ($zenodo_id != '')
	{
		$graphic->url = 'https://zenodo.org/record/' . $zenodo_id . '/thumb750';
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
	
	}
}
