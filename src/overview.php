<?php
session_start();
if (!isset($_SESSION['ingelogd'])) {
  header("Location: login.php");
  exit();
}

$conn = require_once "partials/dbconnection.php";
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Overview</title>
  <link rel="stylesheet" href="css/style.css">
</head>
<body>

  <a href="logout.php">Uitloggen</a>
  <a href="create.php">Nieuwe gebruiker aanmaken</a>

  <table>
    <tr>
      <th>Username</th>
      <th>wachtwoord</th>
      <th>rol</th>
      <th>Actie</th>
    </tr>
    <?php
    $stmt = $conn->prepare("SELECT * FROM user");
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 0)
      exit('No rows');
    while ($row = $result->fetch_assoc()) {
      echo "<tr>";
      echo "<td>" . $row['username'] . "</td>";
      echo "<td>" . $row['wachtwoord'] . "</td>";
      echo "<td>" . $row['rol'] . "</td>";
      echo "<td>";
      echo "<a href='update.php?id=" . $row['id'] . "'>Edit </a>";
      echo "<a href='delete.php?id=" . $row['id'] . "'onclick=\"return confirm('Weet je zeker dat je deze gebruiker wilt verwijderen?');\">Verwijderen</a>";
      echo "</tr>";
    }
    echo "</table>";
    $stmt->close();
    ?>

</body>
</html>