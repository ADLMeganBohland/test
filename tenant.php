<?php
var_dump($_REQUEST);
$tenantName = htmlspecialchars($_POST["textboxForName"] ?? "", ENT_QUOTES);
$userName = htmlspecialchars($_POST["textboxForUser"] ?? "", ENT_QUOTES);
$password = htmlspecialchars($_POST["textboxForPassword"] ?? "", ENT_QUOTES);
$url = htmlspecialchars($_POST["textboxForURL"] ?? "", ENT_QUOTES);

echo"<div class=\"feedback\">TenantName: $tenantName<br>Username: $userName<br>Password: $password</div><br>URL: $url</div>";

echo "<br>";
echo "<br>";

createTenant($url, $userName, $password);
echo "<br>";
echo "<br>";
//echo "<br>This is the response " + $response;
 function createTenant($urlToSend, $user, $pass){ 

echo"Create tenant has been called!!!!";

    //include HttpRequest;
    $url = $urlToSend;
    $userName = $user;
    $password = $pass;

echo"What is url here?  + $url";
$data = array(
    'code' => 'coffee');

// use key 'http' even if you send the request to https://...
$options = array(
    'http' => array(
        'method'  => 'POST',
        'header' => array('Authorization: Basic '. base64_encode("BasicKey:BasicSecret"),  
            "Content-Type: application/json\r\n" .
            "Accept: application/json\r\n"),
        'content' => json_encode($data)
    )
);
$context  = stream_context_create($options);

echo"what is context here, before going to file?? + $context";

$fp = fopen("http://127.0.0.1:63398/api/v1/tenant", 'r', false, $context);
fpassthru($fp);
fclose($fp);

$result = file_get_contents($fp);

if ($result === FALSE) 
{ echo"Something went wrong !<br><br>";
}

$response = json_decode( $result);
echo "The result is  ";
var_dump($response);
//return $result;

 }