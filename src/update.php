<?php
session_start();
if (!isset($_SESSION['ingelogd'])) {
  header("Location: login.php");
  exit();
}

$conn = require_once "partials/dbconnection.php";

$id = $_GET['id'] ?? null;
if (!$id) {
  header("Location: overview.php");
  exit();
}

$errors = [];

$stmt = $conn->prepare("SELECT * FROM user WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if (!$user) {
  header("Location: overview.php");
  exit();
}

$username = $user['username'];
$rol = $user['rol'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $username = $_POST['username'];
  $password = $_POST['wachtwoord'];
  $rol = $_POST['rol'];

  if (strlen($username) < 5) {
    $errors[] = "Gebruikersnaam moet minimaal 5 tekens zijn.";
  }

  if (!empty($password) && strlen($password) < 8) {
    $errors[] = "Wachtwoord moet minimaal 8 tekens zijn.";
  }

  if (empty($errors)) {
    if (!empty($password)) {
      $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
      $stmt = $conn->prepare("UPDATE user SET username = ?, wachtwoord = ?, rol = ? WHERE id = ?");
      $stmt->bind_param("sssi", $username, $hashedPassword, $rol, $id);
    } else {
      $stmt = $conn->prepare("UPDATE user SET username = ?, rol = ? WHERE id = ?");
      $stmt->bind_param("ssi", $username, $rol, $id);
    }
    $stmt->execute();
    $stmt->close();

    header("Location: overview.php?updated=1");
    exit();
  }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Update</title>
  <link rel="stylesheet" href="wachtwoord.css">
</head>

<body style="background-color: #1c3c30">
  <div id="loginContainer">
    <h2 id="loginTitle">Gebruiker aanpassen</h2>

    <?php if (!empty($errors)) { ?>
      <div style="color: #ffb3b3; margin-bottom: 10px;">
        <?php foreach ($errors as $error) {
          echo "<p>" . $error . "</p>";
        } ?>
      </div>
    <?php } ?>

    <form method="POST" action="update.php?id=<?php echo $id; ?>">
      <input type="text" id="inlogUsername" name="username" placeholder="Username"
        value="<?php echo ($username); ?>" required>
      <br>
      <input type="password" id="inlogWachtwoord" name="wachtwoord" placeholder="Nieuw wachtwoord (leeg = ongewijzigd)">
      <br>
      <select name="rol">
        <option value="gebruiker" <?php if ($rol === "gebruiker") echo "selected"; ?>>Gebruiker</option>
        <option value="admin" <?php if ($rol === "admin") echo "selected"; ?>>Admin</option>
      </select>
      <div id="loginRegister">
        <input type="submit" value="Opslaan">
        <button><a href="overview.php">Terug</a></button>
      </div>
    </form>
  </div>
</body>

</html>