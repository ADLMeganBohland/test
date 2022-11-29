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
    createTenant($firstUrl, $firstUserName, $firstPassword, $newTenantName);
}elseif (isset($_POST['GetToken'])) {
    
    echo"Get Token button pushed";
    echo"<div class=\"feedback\">Username: $userName<br>Audience: $audience</div><br>Tenent ID: $tenantId</div><br>Password: $password</div><br>URL: $url</div>";

    //will retreive tenants bearer token
    retrieveToken($url, $userName, $password, $audience, $tenantId);
}

echo "<br>";
echo "<br>";

//createTenant($url, $userName, $password, $newTenantName);
echo "<br>";
echo "<br>";


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
        $result = file( $url, false, $context );

        if ($result === FALSE) 
            { echo"Something went wrong!";
              echo"<br>";
              var_dump($_SESSION);
        }
        else{
            echo "Tenant created. Response:  ";
            //implode function joins elements of array, it takes the wanted separater as first arg and array as second
            echo implode(" ", $result);
            echo"<br>";
            echo"<br>";
        }
        //Now lets try and SAVE the info returned as variables so we don't need user input through boxes
        //explode separates the values in a string, we need this now because file() returned the code and id as a single array element//
        //aka a single string
        $returnedInfo = $result[0];
        $info = explode('","', $returnedInfo);
        //$info2 = explode(':', $info);
        $returnedName = explode('":"', $info[0]);
        $returnedId = explode('":', '}', $info[1]);
        ///well it works but not wuite right, what if we split on , then on : seems excessive,buuuut
        //Dang last }!! However str_split() looks really promising and if not preg_split() also looks good, but it's about quitting time!!
        echo"did it work?";
        echo"<br>";
        echo"returned name is $returnedName[1]";
        echo"<br>";
        echo"returned id is  $returnedId[1]";

        //what does this do?
        echo"<br>";
        // Loop through our array, show HTML source as HTML source; and line numbers too.
        foreach ($result as $line_num => $line) {
        echo "Line #<b>{$line_num}</b> : " . htmlspecialchars($line) . "<br />\n";
}

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
 
 
 
 
 
 
 
 //found this on php website to check status code only 
    function get_http_response_code($theURL) {
        $headers = get_headers($theURL);
        return substr($headers[0], 9, 3);
    }