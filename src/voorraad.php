<?php
session_start();

// Alleen voor ingelogde gebruikers
if (!isset($_SESSION['ingelogd'])) {
  header("Location: login.php");
  exit();
}

$conn = require_once "partials/dbconnection.php";

// Huidige pagina inclusief filters (bv. voorraad.php?kleur=Zwart), zodat we
// na een actie terug kunnen naar precies dezelfde weergave
$terugUrl = 'voorraad.php' . (($_SERVER['QUERY_STRING'] ?? '') !== '' ? '?' . $_SERVER['QUERY_STRING'] : '');

// Melding uit winkelwagen.php of verwijderen.php: eenmalig tonen en daarna wissen
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

// Totaal aantal stuks in de winkelwagen (voor de link bovenaan)
$aantalInWagen = array_sum($_SESSION['winkelwagen'] ?? []);

// Waarden uit het filterformulier (GET)
$zoek = trim($_GET['zoek'] ?? '');
$kleurFilter = $_GET['kleur'] ?? '';
$soortFilter = $_GET['soort'] ?? '';


// Alle beschikbare kleuren ophalen voor de keuzelijst
$kleuren = [];
$kleurResult = $conn->query("SELECT DISTINCT kleur FROM product ORDER BY kleur");
while ($row = $kleurResult->fetch_assoc()) {
  $kleuren[] = $row['kleur'];
}


// Alle beschikbare soorten leer ophalen voor de keuzelijst
$soorten = [];
$soortResult = $conn->query("SELECT DISTINCT soort FROM product ORDER BY soort");
while ($row = $soortResult->fetch_assoc()) {
  $soorten[] = $row['soort'];
}


// Basisquery: producten met hun (eventuele) bestelling.
// "WHERE 1=1" is een trucje zodat we hieronder gewoon "AND ..." kunnen toevoegen.
$sql = "SELECT p.id, p.naam, p.gewicht, p.kleur, p.dikte, p.soort, p.prijs, p.voorraad, p.gelooid,
               b.bestelnr, b.status AS bestelling_status
        FROM product p
        LEFT JOIN bestelling b ON b.product_id = p.id
        WHERE 1=1";
$types = "";   // types van de parameters voor bind_param ("s" = string)
$params = [];  // de waarden zelf

// Filter op naam (deel van de naam)
if ($zoek !== '') {
  $sql .= " AND p.naam LIKE ?";
  $params[] = "%$zoek%";
  $types .= "s";
}

// Filter op kleur
if ($kleurFilter !== '') {
  $sql .= " AND p.kleur = ?";
  $types .= "s";
  $params[] = $kleurFilter;
}

// Filter op soort leer
if ($soortFilter !== '') {
  $sql .= " AND p.soort = ?";
  $types .= "s";
  $params[] = $soortFilter;
}

$sql .= " ORDER BY p.id";

// Query uitvoeren; alleen parameters binden als er filters zijn gekozen
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
  <a href="winkelwagen.php">Winkelwagen (<?php echo (int) $aantalInWagen; ?>)</a>

  <h1>Voorraad overzicht</h1>

  <!-- Melding na het toevoegen van een product (uit product.php) -->
  <?php if (isset($_GET['toegevoegd'])) { ?>
    <p style="color: green;">Product en bestelling zijn toegevoegd.</p>
  <?php } ?>

  <!-- Melding na winkelwagen-actie of verwijderen (groen = gelukt, rood = fout) -->
  <?php if ($flash) { ?>
    <p style="color: <?php echo $flash['type'] === 'ok' ? 'green' : 'red'; ?>;">
      <?php echo htmlspecialchars($flash['tekst']); ?>
    </p>
  <?php } ?>

  <!-- Zoek- en filterformulier (GET, zodat de filters in de URL komen te staan) -->
  <form method="GET" action="voorraad.php" id="filterForm">
    <input
      type="text"
      name="zoek"
      placeholder="Zoeken naam."
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
    <a href="product.php">+ Nieuw product toevoegen</a>
  </form>

  <!-- Voorraadtabel -->
  <table border="1" cellpadding="6" cellspacing="0">
    <tr>
      <th>ID</th>
      <th>Naam</th>
      <th>Gewicht</th>
      <th>Kleur</th>
      <th>Dikte</th>
      <th>Soort leer</th>
      <th>Prijs</th>
      <th>Voorraad</th>
      <th>Bestelling</th>
      <th>Status bestelling</th>
      <th>Acties</th>
    </tr>
    <?php
    // Niets gevonden? Eén rij met een melding over alle kolommen
    if ($result->num_rows === 0) {
      echo "<tr><td colspan='11'>Geen voorraad gevonden</td></tr>";
    } else {
      // Per product één rij. htmlspecialchars() voorkomt dat data als HTML wordt uitgevoerd (XSS).
      while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['id']) . "</td>";
        echo "<td>" . htmlspecialchars($row['naam']) . "</td>";
        echo "<td>" . htmlspecialchars($row['gewicht']) . "</td>";
        echo "<td>" . htmlspecialchars($row['kleur']) . "</td>";
        echo "<td>" . htmlspecialchars($row['dikte']) . "</td>";
        echo "<td>" . htmlspecialchars($row['soort']) . "</td>";
        echo "<td>" . htmlspecialchars($row['prijs']) . "</td>";
        echo "<td>" . htmlspecialchars($row['voorraad']) . "</td>";
        // "-" als er (nog) geen bestelling bij dit product hoort
        echo "<td>" . htmlspecialchars($row['bestelnr'] ?? '-') . "</td>";
        echo "<td>" . htmlspecialchars($row['bestelling_status'] ?? '-') . "</td>";

        // Kolom met knoppen: winkelwagen (alleen als er voorraad is) en verwijderen
        echo "<td style='white-space: nowrap;'>";
        if ((int) $row['voorraad'] > 0) {
          // Formulier naar winkelwagen.php; max = voorraad, zodat je niet meer kunt kiezen dan er is
          echo "<form method='POST' action='winkelwagen.php' style='display: inline;'>";
          echo "<input type='hidden' name='actie' value='toevoegen'>";
          echo "<input type='hidden' name='product_id' value='" . (int) $row['id'] . "'>";
          echo "<input type='hidden' name='terug' value='" . htmlspecialchars($terugUrl, ENT_QUOTES) . "'>";
          echo "<input type='number' name='aantal' value='1' min='1' max='" . (int) $row['voorraad'] . "' style='width: 60px;'> ";
          echo "<input type='submit' value='In winkelwagen'>";
          echo "</form> ";
        } else {
          echo "Uitverkocht ";
        }

        // Formulier naar verwijderen.php; confirm() vraagt eerst om bevestiging
        echo "<form method='POST' action='verwijderen.php' style='display: inline;' onsubmit=\"return confirm('Dit product en de bijbehorende bestelling(en) verwijderen?');\">";
        echo "<input type='hidden' name='product_id' value='" . (int) $row['id'] . "'>";
        echo "<input type='hidden' name='terug' value='" . htmlspecialchars($terugUrl, ENT_QUOTES) . "'>";
        echo "<input type='submit' value='Verwijderen'>";
        echo "</form>";
        echo "</td>";

        echo "</tr>";
      }
    }
    $stmt->close();
    ?>
  </table>

</body>
</html>