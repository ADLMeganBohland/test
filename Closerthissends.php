<!DOCTYPE html>
<html>
    <head>
        <title>Trial Tenant</title>
    </head>

    <body>

    <h1>Tenant retrieval form</h1>

        <?php
        var_dump($_GET);
            $tenantName = htmlspecialchars($_POST["textboxForName"] ?? "", ENT_QUOTES);
            $userName = htmlspecialchars($_POST["textboxForUser"] ?? "", ENT_QUOTES);
            $password = htmlspecialchars($_POST["textboxForPassword"] ?? "", ENT_QUOTES);

            echo"<div class=\"feedback\">TenantName: $tenantName<br>Username: $userName<br>Password: $password</div>";
        ?>
        <form method="post" action="http://127.0.0.1:63398/api/v1/tenant" setContentType=application/json setBody=("code=bob")>
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

            <input type="submit" name="submit" value="Register" class="btn btn-primary">
        </form>

    </body>


</html>