<?php
namespace myWork;

///Class to hold methods for working with cmi5
// @method - createTenant: used to create a new tenant
// @method - retrieveToken: used to retreive a bearer token with tenant id
// @property - 
class cmi5Connectors{

    public static $tenantName = "";
    public static $tenantId = "";
    public static $bearerToken = "";
    
    
    
    //////
    //Function to create a tenant
    // @param $urlToSend - URL retrieved from user in URL textbox
    // @param $user - username retrieved from user in username textbox - ideally this will be backend/hidden
    // @param $pass - password retrieved from user in password textbox - ideally this will be backend/hidden
    // @param $newTenantName - the name the new tenant will be, retreived from Tenant NAme textbox
    /////MB
    public function createTenant($urlToSend, $user, $pass, $newTenantName){ 
    
        echo"Are we making it here?";
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

        echo "<br>";
        echo "What about here?";

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
    
    
        //sends the stream to the specified URL 
        $token = cmi5Connectors::sendRequest($data, $url, $username, $password);

        if ($token === FALSE) 
            { echo"Something went wrong!";
            echo"<br>";
            var_dump($_SESSION);
        }
        else{
            echo"The token is  + $token";
            return $token;
        }

}

    ///Function to retrieve a luanch URL for course
    //@param $actorName - the tenant name to be passed as name property actor->account 
    //@param $homepage - The URL that will be passed as the homepage property in actor->account
    //@param $returnUrl - The URL that will be passed as the returnUrl property in actor
    //@param $url - The URL to send request for launch URL to
    ////////
    public function retrieveUrl($actorName, $homepage, $returnUrl, $url, $token){

        //retrieve and assign params
        $actorName = $this->$actorName;
        $homeUrl = $homepage;
        $returnUrl = $this->$returnUrl;
        $token = $this->$token;
    
        //the body of the request must be made as array first
        $data = array(
            'actor' => array (
                'account' => array(
                    "homePage:" => $homeUrl,
                    "name:" => $actorName,
            )),
            'returnUrl' => $returnUrl
        );
    
        // use key 'http' even if you send the request to https://...
        //There can be multiple headers but as an array under the ONE header
        //content(body) must be JSON encoded here, as that is what CMI5 player accepts
        $options = array(
            'http' => array(
                'method'  => 'POST',
                'header' => array('Authorization: Bearer '. base64_encode(""),  
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
     
    ?>