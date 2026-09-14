<?php
session_start();
if (!isset($_SESSION['ingelogd'])) {
  header("Location: login.php");
  exit();
}

$conn = require_once "partials/dbconnection.php";


$zoek = trim($_GET['zoek'] ?? '');
$kleurFilter = $_GET['kleur'] ?? '';
$soortFilter = $_GET['soort'] ?? '';


$kleuren = [];
$kleurResult = $conn->query("SELECT DISTINCT kleur FROM product ORDER BY kleur");
while ($row = $kleurResult->fetch_assoc()) {
  $kleuren[] = $row['kleur'];
}


$soorten = [];
$soortResult = $conn->query("SELECT DISTINCT soort FROM product ORDER BY soort");
while ($row = $soortResult->fetch_assoc()) {
  $soorten[] = $row['soort'];
}


$sql = "SELECT id, gewicht, kleur, dikte, soort, prijs, voorraad, gelooid
        FROM product
        WHERE 1=1";
$types = "";
$params = [];

if ($zoek !== '') {
  $sql .= " AND (kleur LIKE ? OR soort LIKE ? OR gewicht LIKE ? OR prijs LIKE ?)";
  $like = "%" . $zoek . "%";
  $types .= "ssss";
  $params[] = $like;
  $params[] = $like;
  $params[] = $like;
  $params[] = $like;
}

if ($kleurFilter !== '') {
  $sql .= " AND kleur = ?";
  $types .= "s";
  $params[] = $kleurFilter;
}

if ($soortFilter !== '') {
  $sql .= " AND soort = ?";
  $types .= "s";
  $params[] = $soortFilter;
}

$sql .= " ORDER BY id";

$stmt = $conn->prepare($sql);
if ($types !== '') {
  $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="nl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Voorraad</title>
  <link rel="stylesheet" href="css/style.css">
</head>
<body>

  <a href="logout.php">Uitloggen</a>

  <h1>Voorraad overzicht</h1>

  <form method="GET" action="voorraad.php" id="filterForm">
    <input
      type="text"
      name="zoek"
      placeholder="Zoeken op kleur, soort, gewicht of prijs..."
      value="<?php echo htmlspecialchars($zoek); ?>"
    >

    <select name="kleur">
      <option value="">Alle kleuren</option>
      <?php foreach ($kleuren as $k) { ?>
        <option value="<?php echo htmlspecialchars($k); ?>" <?php if ($kleurFilter === $k) echo "selected"; ?>>
          <?php echo htmlspecialchars($k); ?>
        </option>
      <?php } ?>
    </select>

    <select name="soort">
      <option value="">Alle soorten</option>
      <?php foreach ($soorten as $s) { ?>
        <option value="<?php echo htmlspecialchars($s); ?>" <?php if ($soortFilter === $s) echo "selected"; ?>>
          <?php echo htmlspecialchars($s); ?>
        </option>
      <?php } ?>
    </select>

    <input type="submit" value="Filteren">
    <a href="voorraad.php">Reset filters</a>
  </form>

  <table border="1" cellpadding="6" cellspacing="0">
    <tr>
      <th>ID</th>
      <th>Gewicht</th>
      <th>Kleur</th>
      <th>Dikte</th>
      <th>Soort leer</th>
      <th>Prijs</th>
      <th>Bestelling</th>
      <th>Status bestelling</th>
      <th>Klant</th>
    </tr>
    <?php
    if ($result->num_rows === 0) {
      echo "<tr><td colspan='9'>Geen voorraad gevonden</td></tr>";
    } else {
      while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['id']) . "</td>";
        echo "<td>" . htmlspecialchars($row['gewicht']) . "</td>";
        echo "<td>" . htmlspecialchars($row['kleur']) . "</td>";
        echo "<td>" . htmlspecialchars($row['dikte']) . "</td>";
        echo "<td>" . htmlspecialchars($row['soort']) . "</td>";
        echo "<td>" . htmlspecialchars($row['prijs']) . "</td>";
        echo "<td>" . htmlspecialchars($row['gelooid'] ?? '-') . "</td>";
        echo "<td>" . htmlspecialchars($row['voorraad'] ?? '-') . "</td>";
        echo "</tr>";
      }
    }
    $stmt->close();
    ?>
  </table>

</body>
</html>