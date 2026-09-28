<?php
session_start();
$error = ""; // Foutmelding die in de pagina getoond wordt

// Alleen uitvoeren als het inlogformulier is verstuurd
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $conn = require_once "partials/dbconnection.php";

  // Zoek de gebruiker op naam (prepared statement voorkomt SQL-injectie)
  $stmt = $conn->prepare("SELECT * FROM user WHERE username = ?");
  $stmt->bind_param("s", $_POST['name']);
  $stmt->execute();
  $result = $stmt->get_result();
  $row = $result->fetch_assoc(); // false als de gebruiker niet bestaat
  $stmt->close();

  // Gebruiker gevonden én wachtwoord komt overeen met de opgeslagen hash?
  if ($row && password_verify($_POST['wachtwoord'], $row['wachtwoord'])) {

    // Onthoud in de sessie dat de gebruiker is ingelogd en welke rol hij heeft
    $_SESSION['ingelogd'] = true;
    $_SESSION['rol'] = $row['rol'];

    // Admins gaan naar het gebruikersoverzicht, gewone gebruikers naar de voorraad
    if ($row['rol'] === 'admin') {
      header("Location: overview.php");
    } else {
      header("Location: voorraad.php");
    }
    exit();

  } else {
    // Bewust één melding voor "gebruiker bestaat niet" en "wachtwoord fout"
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

    <!-- Foutmelding tonen als die er is -->
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