<?php
namespace myWork;
require_once("./cmi5Connector.php");

//to hold variables gather from forms
$newTenantName = htmlspecialchars($_POST["textboxForNewName"] ?? "", ENT_QUOTES);
$firstUserName = htmlspecialchars($_POST["textboxForUsername1"] ?? "", ENT_QUOTES);
$firstPassword = htmlspecialchars($_POST["textboxForPassword1"] ?? "", ENT_QUOTES);
$firstUrl = htmlspecialchars($_POST["textboxForURL1"] ?? "", ENT_QUOTES);

$userName = htmlspecialchars($_POST["textboxForUser"] ?? "", ENT_QUOTES);
$audience = htmlspecialchars($_POST["textboxAudience"] ?? "", ENT_QUOTES);
$tenantId = htmlspecialchars($_POST["textboxForId"] ?? "", ENT_QUOTES);
$password = htmlspecialchars($_POST["textboxForPassword"] ?? "", ENT_QUOTES);
$url = htmlspecialchars($_POST["textboxForURL"] ?? "", ENT_QUOTES);

$homepage = htmlspecialchars($_POST["textboxHomepage"] ?? "", ENT_QUOTES);
$actorName = htmlspecialchars($_POST["textboxNameforUrl"] ?? "", ENT_QUOTES);
$returnUrl = htmlspecialchars($_POST["textboxForReturnUrl"] ?? "", ENT_QUOTES);


//To hold variables overall, aka after manipulation from functions
$returnedTenName = "";
$returnedTenId = "";
$returnedToken = "";

//Check which button was pushed
if (isset($_POST['Register'])) {
    echo"Register button pushed";
    echo"<div class=\"feedback\">newTenantName: $newTenantName<br>Username: $firstUserName<br>Password: $firstPassword</div><br>URL: $firstUrl</div>";

  $foo = new cmi5Connectors;
  $createTenant = $foo->getCreateTenant();
   
  //will create a new tenant
    $tenantInfo = $createTenant($firstUrl, $firstUserName, $firstPassword, $newTenantName);
    echo"<br>";
    echo"<br>";
  echo"wtf";
    //var_dump($tenantInfo);

    $returnedTenName = $tenantInfo['code'];
    $returnedTenId = $tenantInfo['id'];
        echo"<br>";
        echo"returned name is $returnedTenName";
        echo"<br>";
        echo"returned id is $returnedTenId";

}elseif (isset($_POST['GetToken'])) {
    
    echo"Get Token button pushed";
    echo"<div class=\"feedback\">Username: $userName<br>Audience: $audience</div><br>Tenent ID: $tenantId</div><br>Password: $password</div><br>URL: $url</div>";

    //will retreive tenants bearer token
  $returnedToken = cmi5Connectors::retrieveToken($url, $userName, $password, $audience, $tenantId);

}elseif (isset($_POST['GetURL'])) {
 
  echo"Get Token button pushed";
  echo"<div class=\"feedback\">Actor name: $actorName<br>Homepage URL: $homepage</div><br>Return URL: $returnUrl</div>";

  $url = 
  $result = cmi5Connectors::retrieveUrl($actorName, $homepage, $returnUrl, $url, $token);

  echo"<br>";
  echo"It is working here is result ++ $result";

}

echo "<br>";
echo "<br>";

echo "<br>";
echo "<br>";
?>