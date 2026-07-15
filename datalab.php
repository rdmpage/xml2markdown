<?php

if (file_exists(dirname(__FILE__) . '/env.php'))
{
	include 'env.php';
}

//----------------------------------------------------------------------------------------
function get($url)
{
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_HEADER, 0);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); 
	
	curl_setopt($ch, CURLOPT_HTTPHEADER, 
		array(
			"X-Api-Key: " . getenv('DATALAB_API_KEY')
			)
		);
	
	$response = curl_exec($ch);
	if($response == FALSE) 
	{
		$errorText = curl_error($ch);
		curl_close($ch);
		die($errorText);
	}
	
	$info = curl_getinfo($ch);
	$http_code = $info['http_code'];
		
	curl_close($ch);
	
	return $response;
}


//----------------------------------------------------------------------------------------
function post($url, $data)
{
	
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_POST, 1);
	curl_setopt($ch, CURLOPT_HEADER, 0);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); 
	
	curl_setopt($ch, CURLOPT_POSTFIELDS, $data);  
	
	curl_setopt($ch, CURLOPT_HTTPHEADER, 
		array(
			"Content-type: multipart/form-data",
			"X-Api-Key: " . getenv('DATALAB_API_KEY')
			)
		);
	
	$response = curl_exec($ch);
	if($response == FALSE) 
	{
		$errorText = curl_error($ch);
		curl_close($ch);
		die($errorText);
	}
	
	$info = curl_getinfo($ch);
	$http_code = $info['http_code'];
		
	curl_close($ch);
	
	return $response;
}

//----------------------------------------------------------------------------------------

$upload_filename = '';
if ($argc < 2)
{
	echo "Usage: " . basename(__FILE__) . " <filename>\n";
	exit(1);
}
else
{
	$upload_filename = $argv[1];
}

$url = 'https://www.datalab.to/api/v1/convert';

// output filename
$json_filename = str_replace('.pdf', '.json', $upload_filename);

$output_format = 'html';
//$output_format = 'markdown';

$data = array(
	"file" => new CurlFile($upload_filename, mime_content_type($upload_filename), $upload_filename),
	"output_format" => $output_format,
  	"mode" => "balanced",
  	"disable_image_captions" => "true"
);

$result = null;

$response = post($url, $data);

$response_obj = json_decode($response);

if ($response_obj->success)
{
	$max_polls = 300;
	for ($i = 0; $i < $max_polls; $i++)
	{
		// echo "polling [$i]\n";
		$json = get($response_obj->request_check_url);
		
		// Debugging, save JSON
		file_put_contents($json_filename, $json);
		
		$result = json_decode($json);
		
		if ($result->status == 'complete')
		{
			// re fetch
			$json = get($response_obj->request_check_url);
			$result = json_decode($json);
			break;
		}
		
		usleep(1000000);

	}
}

if ($result->status == "complete")
{
	switch ($output_format)
	{
		case 'html':
			$output_filename = str_replace('.pdf', ".html", $upload_filename);
			file_put_contents($output_filename, $result->html);
			break;
	
		case 'markdown':
		default:
			$output_filename = str_replace('.pdf', ".md", $upload_filename);
			file_put_contents($output_filename, $result->markdown);
			break;
	}
	
	foreach ($result->images as $k => $v)
	{
		$image = base64_decode($v);
		file_put_contents($k, $image);
	}

}

?>
