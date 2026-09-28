<?php
$error = ""; // Foutmelding voor in de pagina

// Alleen uitvoeren als het registratieformulier is verstuurd
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $conn = require_once "partials/dbconnection.php";

  $username = $_POST['username'];
  $password = $_POST['wachtwoord'];

  // Validatie: minimale lengte van gebruikersnaam en wachtwoord
  if (strlen($username) < 5) {
    $error = "Gebruikersnaam moet minimaal 5 tekens zijn.";
  } elseif (strlen($password) < 8) {
    $error = "Wachtwoord moet minimaal 8 tekens zijn.";
  } else {
    // Controleer of de gebruikersnaam al bestaat
    $checkStmt = $conn->prepare("SELECT id FROM user WHERE username = ?");
    $checkStmt->bind_param("s", $username);
    $checkStmt->execute();
    $checkStmt->store_result();

    if ($checkStmt->num_rows > 0) {
      $error = "Deze gebruikersnaam bestaat al.";
      $checkStmt->close();
    } else {
      $checkStmt->close();

      // Wachtwoord nooit als tekst opslaan, alleen als hash
      $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
      $stmt = $conn->prepare("INSERT INTO user (username, wachtwoord) VALUES (?, ?)");
      $stmt->bind_param("ss", $username, $hashedPassword);
      $stmt->execute();
      $stmt->close();

      // Klaar: naar de inlogpagina
      header("Location: login.php?registered=1");
      exit();
    }
  }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Register</title>
  <link rel="stylesheet" href="wachtwoord.css">
</head>

<body style="background-color: #1c3c30">
  <div id="loginContainer">
    <h2 id="loginTitle">Register</h2>

    <!-- Foutmelding tonen (met htmlspecialchars tegen XSS) -->
    <?php if ($error) echo "<p>" . htmlspecialchars($error) . "</p>"; ?>

    <form method="POST" action="register.php">
      <input type="text" id="inlogUsername" name="username" placeholder="Username" required>
      <br>
      <input type="password" id="inlogWachtwoord" name="wachtwoord" placeholder="Wachtwoord" required>
      <div id="loginRegister">
        <input type="submit" value="Register">
        <button><a href="login.php">Login</a></button>
      </div>
    </form>
  </div>
</body>

</html>