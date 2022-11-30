<?php
$newTenantName = htmlspecialchars($_POST["textboxForNewName"] ?? "", ENT_QUOTES);
$firstUserName = htmlspecialchars($_POST["textboxForUsername1"] ?? "", ENT_QUOTES);
$firstPassword = htmlspecialchars($_POST["textboxForPassword1"] ?? "", ENT_QUOTES);
$firstUrl = htmlspecialchars($_POST["textboxForURL1"] ?? "", ENT_QUOTES);

$userName = htmlspecialchars($_POST["textboxForUser"] ?? "", ENT_QUOTES);
$audience = htmlspecialchars($_POST["textboxAudience"] ?? "", ENT_QUOTES);
$tenantId = htmlspecialchars($_POST["textboxForId"] ?? "", ENT_QUOTES);
$password = htmlspecialchars($_POST["textboxForPassword"] ?? "", ENT_QUOTES);
$url = htmlspecialchars($_POST["textboxForURL"] ?? "", ENT_QUOTES);

//Check which button was pushed
if (isset($_POST['Register'])) {
    echo"Register button pushed";
    echo"<div class=\"feedback\">newTenantName: $newTenantName<br>Username: $firstUserName<br>Password: $firstPassword</div><br>URL: $firstUrl</div>";

    //will create a new tenant
    $tenantInfo = cmi5Connectors::createTenant($firstUrl, $firstUserName, $firstPassword, $newTenantName);
    echo"<br>";
    echo"<br>";

    var_dump($tenantInfo);
    $tenantName = $tenantInfo['code'];
    $tenantId = $tenantInfo['id'];
        echo"<br>";
        echo"returned name is $tenantName";
        echo"<br>";
        echo"returned id is $tenantId";

}elseif (isset($_POST['GetToken'])) {
    
    echo"Get Token button pushed";
    echo"<div class=\"feedback\">Username: $userName<br>Audience: $audience</div><br>Tenent ID: $tenantId</div><br>Password: $password</div><br>URL: $url</div>";

    //will retreive tenants bearer token
    cmi5Connectors::retrieveToken($url, $userName, $password, $audience, $tenantId);
}

echo "<br>";
echo "<br>";

//createTenant($url, $userName, $password, $newTenantName);
echo "<br>";
echo "<br>";

///Class to hold methods for working with cmi5
// @method - createTenant: used to create a new tenant
// @method - retrieveToken: used to retreive a bearer token with tenant id
// @property - 
class cmi5Connectors{

public static $tenantName = "";
public static $retTenantId = "";
public static $bearerToken = "";



//////
//Function to create a tenant
// @param $urlToSend - URL retrieved from user in URL textbox
// @param $user - username retrieved from user in username textbox - ideally this will be backend/hidden
// @param $pass - password retrieved from user in password textbox - ideally this will be backend/hidden
// @param $newTenantName - the name the new tenant will be, retreived from Tenant NAme textbox
/////MB
function createTenant($urlToSend, $user, $pass, $newTenantName){ 

    //retrieve and assign params
    $url = $urlToSend;
    $username = $user;
    $password = $pass;
    $tenant = $newTenantName;

    //the body of the request must be made as array first
    $data = array(
        'code' => $tenant);

    //sends the stream to the specified URL 
    $result = cmi5Connectors::sendRequest($data, $url, $username, $password);

    if ($result === FALSE) 
        { echo"Something went wrong!";
            echo"<br>";
            var_dump($_SESSION);
    }
    else{
        echo "Tenant created. Response: is $result";
        var_dump(json_decode($result, true));
    }
        //decode returned response into array
        $returnedInfo = json_decode($result, true);
        
        //Return an array with tenant name and info
        return $returnedInfo;
}


 //Ok, next we need a function to retrieve bearer token, all in PHP
 //@param $urlToSend - URL retrieved from user in URL textbox
// @param $user - username retrieved from user in username textbox - ideally this will be backend/hidden
// @param $pass - password retrieved from user in password textbox - ideally this will be backend/hidden
// @param $audience - the name the of the audience using the token, retreived from audience textbox
// @param #tenantId - the id of the tenant, retreived from Tenant Id text box
//Note - this is for trial, ideally the id will be stored in php variable in createTenant func and then supplied as needed
/////MB
 function retrieveToken($urlToSend, $user, $pass, $audience, $tenantId){

     //retrieve and assign params
     $url = $urlToSend;
     $username = $user;
     $password = $pass;
     $tokenUser = $audience;
     $id = $tenantId;
 
     //the body of the request must be made as array first
     $data = array(
            'tenantId' => $id,
            'audience' => $tokenUser
        );
 

     // use key 'http' even if you send the request to https://...
     //There can be multiple headers but as an array under the ONE header
     //content(body) must be JSON encoded here, as that is what player accepts
     $options = array(
         'http' => array(
             'method'  => 'POST',
             'header' => array('Authorization: Basic '. base64_encode("$username:$password"),  
                 "Content-Type: application/json\r\n" .
                 "Accept: application/json\r\n"),
             'content' => json_encode($data)
         )
     );
     //the options are here placed into a stream to be sent
     $context  = stream_context_create($options);
     
 
         //sends the stream to the specified URL and stores results (the false is use_include_path, which we dont want in this case, we want to go to the url)
         $result = file_get_contents( $url, false, $context );

         $token = $result;

         if ($result === FALSE) 
             { echo"Something went wrong!";
               echo"<br>";
               var_dump($_SESSION);
         }
         else{
             echo "Token retrieved. Response:  $result";
             echo"<br>";
             echo"The token is  + $token";
         }

    }

 
    ///Function to construct, send an URL, and save result
    //@param $dataBody - the data that will be used to construct the body of request as JSON 
    //@param $url - The URL the request will be sent to
    //@param $username - the username for basic auth
    //@param $password - the password for basic auth
    ///TODO - perhaps make an overload constructor that can take header info as an array, and method so it can work for GET/POST
    /////
    public function sendRequest($dataBody, $urlDest, $username, $password) {
        $data = $dataBody;
        $url = $urlDest;
        $user = $username;
        $pass = $password;

        echo"sendRequest has been fired";

    // use key 'http' even if you send the request to https://...
    //There can be multiple headers but as an array under the ONE header
    //content(body) must be JSON encoded here, as that is what CMI5 player accepts
    $options = array(
        'http' => array(
            'method'  => 'POST',
            'header' => array('Authorization: Basic '. base64_encode("$user:$pass"),  
                "Content-Type: application/json\r\n" .
                "Accept: application/json\r\n"),
            'content' => json_encode($data)
        )
    );
    //the options are here placed into a stream to be sent
    $context  = stream_context_create($options);
    

    //sends the stream to the specified URL and stores results (the false is use_include_path, which we dont want in this case, we want to go to the url)
    $result = file_get_contents( $url, false, $context );

    //return response
    return $result;
    }
 
} 
 
 
 //found this on php website to check status code only 
 //   function get_http_response_code($theURL) {
   //     $headers = get_headers($theURL);
     //   return substr($headers[0], 9, 3);
    //