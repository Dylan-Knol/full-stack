<?php
session_start();
$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $conn = require_once "partials/dbconnection.php";

  $stmt = $conn->prepare("SELECT * FROM user WHERE username = ?");
  $stmt->bind_param("s", $_POST['name']);
  $stmt->execute();
  $result = $stmt->get_result();
  $row = $result->fetch_assoc();
  $stmt->close();

  if ($row && password_verify($_POST['wachtwoord'], $row['wachtwoord'])) {
    
    $_SESSION['ingelogd'] = true;
    $_SESSION['rol'] = $row['rol'];
 
    if ($row['rol'] === 'admin') {
      header("Location: overview.php");
    } else {
      header("Location: voorraad.php");
    }
    exit();

  } else {
    $error = "Combinatie van gebruikersnaam en wachtwoord klopt niet.";
  }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login</title>
  <link rel="stylesheet" href="wachtwoord.css">
</head>

<body style="background-color: #1c3c30">
  <div id="loginContainer">
    <h2 id="loginTitle">Login</h2>

    <?php if ($error) echo "<p>" . $error . "</p>"; ?>

    <form method="POST" action="login.php">
      <input type="text" id="inlogUsername" name="name" placeholder="Name" required>
      <br>
      <input type="password" id="inlogWachtwoord" name="wachtwoord" placeholder="Wachtwoord" required>
      <div id="loginRegister">
        <input type="submit" name="knop" value="Verstuur">
        <button><a href="register.php">register</a></button>
      </div>
    </form>
  </div>
</body>

</html>