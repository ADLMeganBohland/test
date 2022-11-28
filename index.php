<!DOCTYPE html>
<html>
    <head>
        <title>Trial Tenant</title>
    </head>

    <body>

    <h1>Tenant retrieval form</h1>
<?php
//require_once('RemoteLRS.php');
var_dump($_POST);
$tenantName = htmlspecialchars($_POST["textboxForName"] ?? "", ENT_QUOTES);
//$userName = htmlspecialchars($_POST["textboxForUser"] ?? "", ENT_QUOTES);
//$password = htmlspecialchars($_POST["textboxForPassword"] ?? "", ENT_QUOTES);
$url = htmlspecialchars($_POST["textboxForURL"] ?? "", ENT_QUOTES);

echo"<div class=\"feedback\">TenantName: $tenantName<br>Username: $userName<br>Password: $password</div>";
//$response = RemoteLRS::createTenant($url);

        ?>

        <form method="post" action="tenant.php">
            <div class="feedback">
            <label for="name">Tenant Name</label>
            <input type="text" name="textboxForName">
            </div>
        
            <div class="feedback">
            <label for="name">Username</label>
            <input type="text" name="textboxForUser">
            </div>

            <div class="feedback">
            <label for="name">Password</label>
            <input type="text" name="textboxForPassword">
            </div>

            <div class="feedback">
            <label for="name">URL</label>
            <input type="text" name="textboxForURL">
            </div>

            <input type="submit" name="submit" value="Register" class="btn btn-primary">
        </form>

    </body>


</html>