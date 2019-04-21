<?php

// load composer autoloader
require_once 'vendor/autoload.php';

// define that we are using Guzzle
use GuzzleHttp\Client;

$domain = 'https://tunesliberia.com';
$links = json_decode(file_get_contents('links.json'),true);
$Client =  new Client();
$downloadsPath = 'downloads';


//---- functions ----
function addLink($aLink,array $links)
{
    global $domain;

    if(!in_array($aLink,$links) && strstr($aLink,$domain) ){
        $links[] = $aLink;
    }

    return $links;
}

function saveMp3($Response, $downloadFolder,$url): bool
{

    $contentType = $Response->getHeaderLine('Content-Type');

    if($contentType && strstr($contentType,'audio/mpeg')){
        $mp3Filename =  $downloadFolder .'/'.getFilename($Response,$url);
        if(!file_exists($mp3Filename)){
           file_put_contents($mp3Filename,$Response->getBody());
        }
        return true;
    }

    return false;
}

function getFilename($Response, $url)
{
    $disposition = $Response->getHeaderLine('Content-Disposition');

    if($disposition && strstr($disposition,'filename')){
        $exploded = explode('"',$disposition);
        if(count($exploded) >= 3){
           return $exploded[1];
        }
    }else{
        $exploded = explode('/',$url);
        return $exploded[count($exploded) - 1];
    }

}

function getAllLinks($Response,$domain)
{
    $html = (string) $Response->getBody();
    $Dom = new DOMDocument();
    $links = [];

    @$Dom->loadHTML($html);

    $aTagElements = $Dom->getElementsByTagName('a');

    foreach($aTagElements as $AnElement)
    {
        $aLink = $AnElement->getAttribute('href');
        if(filter_var($aLink,FILTER_VALIDATE_URL) && strstr($aLink,$domain)){
            $links[] = $AnElement->getAttribute('href');
        }
        
    }
   return $links;
}

function addNewLinks(array $newLinks, $links)
{
     foreach(array_reverse($newLinks) as $aLink){
         if(!in_array($aLink,$links)){
             $links[] = $aLink;
         }
     }

     if(!empty($newLinks)){
         file_put_contents('links.json',json_encode($links,JSON_PRETTY_PRINT));
     }

     return $links;
}

$currentLinkIndex = 0;

echo "Tunes Liberia downloader\r\n";
echo "========================\r\n";

do{
    $sleepTime = rand(2, 20);

    try{
        if(isset($links[$currentLinkIndex])){
            $currentUrl = $links[$currentLinkIndex];
           
            echo "[{$currentLinkIndex}] Processing: " . $currentUrl . "\r\n";

            $Response = $Client->get($currentUrl);
            $isMp3 = saveMp3($Response,$downloadsPath, $currentUrl);
            
            echo "[{$currentLinkIndex}] Processed: ". $currentUrl . "  Type: " . (($isMp3)? 'MP3 file': 'HTML page') . "\r\n";

            if(!$isMp3){
                $links = addNewLinks(getAllLinks($Response,$domain),$links);
            }
        }else{
            echo "Processing links completed \r\n";
            echo "========================== \r\n";
            break;
        }
    }catch(\Exception $ex){
       echo "Error processing link: " . $currentUrl . " msg: " . $ex->getMessage() ."\r\n";
    }
    $currentLinkIndex += 1;

    echo "Sleeping for: " . $sleepTime . " Seconds\r\n";
    sleep($sleepTime);
}while(true);



