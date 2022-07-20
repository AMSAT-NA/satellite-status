<?php
  include("config.php");
?>
<!DOCTYPE html>
<html>
  <head>
    <meta charset="utf-8">
    <title>Login</title>
    <link rel="stylesheet" href="<?php echo $siteUrl; ?>/admin/assets/global.css" media="screen">
  </head>
  <body>
    <h1>Login</h1>

    <form action="<?php echo $siteUrl; ?>/admin/login.php" method="post">
      <table>
        <tr>
          <td>
            Username
          </td>
          <td>
            <input type="text" name="username" value="">
          </td>
        </tr>

        <tr>
          <td>
            Password
          </td>
          <td>
            <input type="password" name="password" value="">
          </td>
        </tr>

        <tr>
          <td>

          </td>

          <td>
            <input type="submit" value="Login">
          </td>
        </tr>
      </table>
    </form>
  </body>
</html>
