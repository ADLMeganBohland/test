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
    
    public function getCreateTenant(){
        return [$this, 'createTenant'];
    }
    public function getRetrieveToken(){
        return [$this, 'retrieveToken'];
    }
    public function getRetrieveUrl(){
        return [$this, 'retrieveUrl'];
    }
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
        $result = $this->sendRequest($data, $url, $username, $password);

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
    public function retrieveUrl($actorName, $homepage, $returnUrl, $url, $bearerToken){

        echo "Retreive url function entered ";

        //retrieve and assign params
        $actor = $actorName;
        $homeUrl = $homepage;
        $retUrl = $returnUrl;
        $token = $bearerToken;
        $reqUrl = $url;
        echo "<br>";
        echo "actorname is {$actor} and the home URL is {$homeUrl}, now returnURL is {$retUrl}, the token is {$token}, and its going to {$reqUrl}";
        echo "<br>";

        /**
         *  //the body of the request must be made as array first
        *$data = array(
        *   'tenantId' => $id,
        *  'audience' => $tokenUser
        *);
         */
        //the body of the request must be made as array first
        $data1 = array(
            'actor' => array (
                'account' => array(
                    "homePage:" => $homeUrl,
                    "name:" => $actor,
                )
            ),
            'returnUrl' => $retUrl
        );

        $data2 = json_encode($data1, JSON_UNESCAPED_SLASHES);

        echo "<br>";
        echo "Ok, how are we doing, is the body correct?  ";  
        echo"$data2";
        echo "<br>";
    
        // use key 'http' even if you send the request to https://...
        //There can be multiple headers but as an array under the ONE header
        //content(body) must be JSON encoded here, as that is what CMI5 player accepts
        //JSON_UNESCAPED_SLASHES used so http addresses are displayed correctly
        $options = array(
            'http' => array(
                'method'  => 'POST',
                'ignore_errors' => true,
                'header' => array("Authorization: Bearer ". $token,  
                    "Content-Type: application/json\r\n" .
                    "Accept: application/json\r\n"),
                'content' => json_encode($data1, JSON_UNESCAPED_SLASHES)

            )
        )
            ;

        
        echo "<br>";
        echo" huh what about error " . json_last_error();

        echo "<br>";
        echo"Welll what is content??";
        var_dump($options);
        echo "<br>";



        //the options are here placed into a stream to be sent
       $context  = stream_context_create(($options));
        echo "<br>";
       echo "And the context is " . $context;
        echo "<br>";

        /*
        //lets see if this brings back a cERTAIN error
        $context = stream_context_create(array(
            'http' => array('ignore_errors' => true,
            $options
        )));
        */
        //$result = file_get_contents('http://your/url', false, $context);
        echo "<br>";
        echo"Welll, and where is it going??? {$reqUrl}";
        echo "<br>";

        //$reqUrl = urlencode($reqUrl);
        //sends the stream to the specified URL and stores results (the false is use_include_path, which we dont want in this case, we want to go to the url)
        $result = file_get_contents( $reqUrl, false, $context );
    /*
        $fp = fopen($reqUrl, 'r', false, $context);
        fpassthru($fp);
        fclose($fp);
*/
        echo "<br> url RETRIEVED. URL is $result";
        echo "<br>";
        var_dump(json_decode($result, true));
        //return response

        echo"<br>";
        echo"About to dump http_res_headers: ";
        echo"<br>";

        //maybe this will shed light?
        if ($result === FALSE) {
            var_dump($http_response_header);
        }
            echo"<br>";

            echo"<br>";
        echo "where any headers sent?";
        echo "<br>";
        var_dump(headers_sent());

        
       // return $result;
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