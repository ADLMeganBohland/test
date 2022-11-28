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
        'Authorization: Basic '. base64_encode("$userName:$password"),
        'header'  => "Content-type: application/x-www-form-urlencoded", 
            "Content-Type: application/json\r\n" .
                "Accept: application/json\r\n",
        'content' => json_encode($data)
    )
);
$context  = stream_context_create($options);
$result = file_get_contents($url, false, $context);
$response = json_decode( $result);
//if ($result === FALSE) { /* Handle error */ 
//*/
echo "The result is  ";
var_dump($response);
//return $result;

 }


    /*
    $url = $urlToSend;
    $versioned_statements = "code=bob";
    $statements = "";
        $requestCfg = array(
            'headers' => array(
                'Content-Type' => 'application/json'
            ),
            'content' => json_encode($versioned_statements, JSON_UNESCAPED_SLASHES),
        );
        //if (! empty($attachments_map)) {
        //    $$lets->_buildAttachmentContent($requestCfg, array_values($attachments_map));
      //  }
       //I instantiated it correctly!!!~!~
       $response = sendRequest('POST', $url, $requestCfg);

       /*
        if ($response) {
            $parsed_content = json_decode($response->content, true);
            foreach ($parsed_content as $i => $stId) {
                $statements[$i]->setId($stId);
            }

            $response = $statements;
        }*/
        //echo "$response";
       // echo"worked?";
//*/
       // return $response;
//////////////trial curlless method