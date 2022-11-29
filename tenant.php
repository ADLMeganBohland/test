<?php
$tenantName = htmlspecialchars($_POST["textboxForName"] ?? "", ENT_QUOTES);
$userName = htmlspecialchars($_POST["textboxForUser"] ?? "", ENT_QUOTES);
$password = htmlspecialchars($_POST["textboxForPassword"] ?? "", ENT_QUOTES);
$url = htmlspecialchars($_POST["textboxForURL"] ?? "", ENT_QUOTES);

echo"<div class=\"feedback\">TenantName: $tenantName<br>Username: $userName<br>Password: $password</div><br>URL: $url</div>";

echo "<br>";
echo "<br>";

createTenant($url, $userName, $password, $tenantName);
echo "<br>";
echo "<br>";


//////
//Function to create a tenant
// @param $urlToSend - URL retrieved from user in URL textbox
// @param $user - username retrieved from user in username textbox - ideally this will be backend/hidden
// @param $pass - password retrieved from user in password textbox - ideally this will be backend/hidden
// @param $tenantName - the name the new tenant will be, retreived from Tenant NAme textbox
/////MB
function createTenant($urlToSend, $user, $pass, $tenantName){ 

    //retrieve and assign params
    $url = $urlToSend;
    $username = $user;
    $password = $pass;
    $tenant = $tenantName;

    //the body of the request must be made as array first
    $data = array(
        'code' => $tenant);

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

        if ($result === FALSE) 
            { echo"Something went wrong!";
              echo"<br>";
              var_dump($_SESSION);
        }
        else{
            echo "Tenant created. Response:  $result";
        }
 }
 
 
 
 
 
 
 
 //found this on php website to check status code only 
    function get_http_response_code($theURL) {
        $headers = get_headers($theURL);
        return substr($headers[0], 9, 3);
    }