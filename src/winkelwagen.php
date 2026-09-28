<?php
session_start();

// Alleen voor ingelogde gebruikers
if (!isset($_SESSION['ingelogd'])) {
  header("Location: login.php");
  exit();
}

$conn = require_once "partials/dbconnection.php";


// De winkelwagen staat in de sessie als [product_id => aantal]. Bestaat hij nog niet, dan maken we hem leeg aan.
if (!isset($_SESSION['winkelwagen']) || !is_array($_SESSION['winkelwagen'])) {
  $_SESSION['winkelwagen'] = [];
}

// Bewaart een melding in de sessie; die wordt op de volgende pagina eenmalig getoond
function melding($type, $tekst)
{
  $_SESSION['flash'] = ['type' => $type, 'tekst' => $tekst];
}

// Haalt één product op uit de database (of null als het niet bestaat)
function haalProduct($conn, $id)
{
  $stmt = $conn->prepare("SELECT id, naam, prijs, voorraad FROM product WHERE id = ?");
  $stmt->bind_param("i", $id);
  $stmt->execute();
  $product = $stmt->get_result()->fetch_assoc();
  $stmt->close();
  return $product;
}

// Zet een getal om naar een prijs, bv. 49.95 -> "€ 49,95"
function euro($bedrag)
{
  return "€ " . number_format($bedrag, 2, ',', '.');
}

// ---- Acties verwerken (alleen als een formulier is verstuurd) ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $actie = $_POST['actie'] ?? ''; // welke knop is gebruikt: toevoegen, aanpassen, verwijderen, bestellen, leegmaken

  // Waar sturen we de gebruiker na afloop naartoe? Alleen onze eigen pagina's toestaan.
  $terug = $_POST['terug'] ?? 'winkelwagen.php';
  if (!preg_match('/^(voorraad|winkelwagen)\.php(\?[^\r\n]*)?$/', $terug)) {
    $terug = 'winkelwagen.php';
  }

  // Invoer controleren: moeten hele getallen zijn (anders false)
  $productId = filter_var($_POST['product_id'] ?? null, FILTER_VALIDATE_INT);
  $aantal = filter_var($_POST['aantal'] ?? 1, FILTER_VALIDATE_INT);

  if ($actie === 'leegmaken') {
    // Winkelwagen helemaal leeg
    $_SESSION['winkelwagen'] = [];
    melding('ok', 'Winkelwagen is leeggemaakt.');
    header("Location: " . $terug);
    exit();

  } elseif ($actie === 'bestellen') {
    // Niets te bestellen?
    if (empty($_SESSION['winkelwagen'])) {
      melding('fout', 'Je winkelwagen is leeg.');
      header("Location: winkelwagen.php");
      exit();
    }

    try {
      // Voorraad per product verlagen. Lukt er één niet, dan wordt alles teruggedraaid.
      $conn->begin_transaction();

      foreach ($_SESSION['winkelwagen'] as $id => $aantalBesteld) {
        // "AND voorraad >= ?" zorgt dat de voorraad nooit onder 0 kan komen
        $stmt = $conn->prepare("UPDATE product SET voorraad = voorraad - ? WHERE id = ? AND voorraad >= ?");
        $stmt->bind_param("iii", $aantalBesteld, $id, $aantalBesteld);
        $stmt->execute();
        $gelukt = $stmt->affected_rows; // 0 = niet genoeg voorraad (of product bestaat niet)
        $stmt->close();

        if ($gelukt === 0) {
          throw new Exception("Niet genoeg voorraad voor product $id.");
        }
      }

      $conn->commit();

      // Gelukt: winkelwagen legen en de pagina met de pop-up tonen (?besteld=1)
      $_SESSION['winkelwagen'] = [];
      header("Location: winkelwagen.php?besteld=1");
      exit();
    } catch (Throwable $e) {
      $conn->rollback();
      error_log($e);
      melding('fout', 'Bestellen is mislukt: er is niet genoeg voorraad meer van één van de producten. Pas je winkelwagen aan.');
      header("Location: winkelwagen.php");
      exit();
    }

  } elseif ($productId === false) {
    // Voor de acties hieronder is een geldig product-id nodig
    melding('fout', 'Ongeldig product.');

  } elseif ($actie === 'verwijderen') {
    // Eén product uit de winkelwagen halen
    unset($_SESSION['winkelwagen'][$productId]);
    melding('ok', 'Product is verwijderd uit je winkelwagen.');

  } elseif ($actie === 'toevoegen' || $actie === 'aanpassen') {
    $product = haalProduct($conn, $productId);

    if (!$product) {
      melding('fout', 'Dit product bestaat niet (meer).');
    } elseif ($aantal === false || $aantal < 1) {
      melding('fout', 'Aantal moet minimaal 1 zijn.');
    } else {
      // "toevoegen" telt erbij op, "aanpassen" vervangt het aantal
      $nieuwAantal = $actie === 'toevoegen'
        ? ($_SESSION['winkelwagen'][$productId] ?? 0) + $aantal
        : $aantal;

      // Nooit meer in de wagen dan er op voorraad is
      if ($nieuwAantal > (int) $product['voorraad']) {
        melding('fout', "Van " . $product['naam'] . " zijn er maar " . $product['voorraad'] . " op voorraad.");
      } else {
        $_SESSION['winkelwagen'][$productId] = $nieuwAantal;
        melding('ok', $product['naam'] . " staat nu " . $nieuwAantal . "x in je winkelwagen.");
      }
    }
  }

  // Na elke actie terug (Post/Redirect/Get: voorkomt dubbel versturen bij verversen)
  header("Location: " . $terug);
  exit();
}

// ---- Pagina opbouwen: producten in de winkelwagen ophalen ----
// Prijzen komen altijd uit de database, nooit uit het formulier.
$regels = [];   // de regels van de winkelwagen
$totaal = 0;    // totaalprijs
$ids = array_keys($_SESSION['winkelwagen']);

if (!empty($ids)) {
  // Voor elk id één ?-teken: bv. "?,?,?"
  $placeholders = implode(',', array_fill(0, count($ids), '?'));
  $stmt = $conn->prepare("SELECT id, naam, kleur, soort, prijs, voorraad FROM product WHERE id IN ($placeholders) ORDER BY naam");
  $stmt->bind_param(str_repeat('i', count($ids)), ...$ids); // "iii..." = allemaal integers
  $stmt->execute();
  $result = $stmt->get_result();

  while ($row = $result->fetch_assoc()) {
    $row['aantal'] = $_SESSION['winkelwagen'][$row['id']];
    $row['subtotaal'] = $row['prijs'] * $row['aantal'];
    $totaal += $row['subtotaal'];
    $regels[] = $row;
  }
  $stmt->close();

  // Producten die niet meer bestaan (bv. verwijderd) ook uit de winkelwagen halen
  $gevonden = array_column($regels, 'id');
  foreach ($ids as $id) {
    if (!in_array($id, $gevonden)) {
      unset($_SESSION['winkelwagen'][$id]);
    }
  }
}

// Melding uit een vorige actie eenmalig tonen
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

// Is er net besteld? Dan tonen we de pop-up
$besteld = isset($_GET['besteld']);
?>
<!DOCTYPE html>
<html lang="nl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Winkelwagen</title>
  <link rel="stylesheet" href="css/style.css">
  <style>
</head>
<body>

  <!-- Pop-up na het bestellen; de knop verbergt hem weer met JavaScript -->
  <div id="besteldOverlay">
    <div id="besteldPopup">
      <h2>Bestelling geplaatst!</h2>
      <p>Bedankt voor je bestelling. De voorraad is bijgewerkt.</p>
      <button onclick="document.getElementById('besteldOverlay').style.display='none';">Sluiten</button>
    </div>
  </div>

  <a href="logout.php">Uitloggen</a>
  <a href="voorraad.php">Verder winkelen</a>

  <h1>Winkelwagen</h1>

  <!-- Melding (groen = gelukt, rood = fout) -->
  <?php if ($flash) { ?>
    <p style="color: <?php echo $flash['type'] === 'ok' ? 'green' : 'red'; ?>;">
      <?php echo htmlspecialchars($flash['tekst']); ?>
    </p>
  <?php } ?>

  <?php if (empty($regels)) { ?>
    <p>Je winkelwagen is leeg. <a href="voorraad.php">Bekijk de voorraad</a></p>
  <?php } else { ?>
    <table border="1" cellpadding="6" cellspacing="0">
      <tr>
        <th>Naam</th>
        <th>Kleur</th>
        <th>Soort leer</th>
        <th>Prijs</th>
        <th>Aantal</th>
        <th>Subtotaal</th>
        <th>Actie</th>
      </tr>
      <?php foreach ($regels as $regel) { ?>
        <tr>
          <td><?php echo htmlspecialchars($regel['naam']); ?></td>
          <td><?php echo htmlspecialchars($regel['kleur']); ?></td>
          <td><?php echo htmlspecialchars($regel['soort']); ?></td>
          <td><?php echo euro($regel['prijs']); ?></td>
          <td>
            <!-- Aantal wijzigen (maximaal het aantal op voorraad) -->
            <form method="POST" action="winkelwagen.php">
              <input type="hidden" name="actie" value="aanpassen">
              <input type="hidden" name="product_id" value="<?php echo (int) $regel['id']; ?>">
              <input type="number" name="aantal" value="<?php echo (int) $regel['aantal']; ?>" min="1" max="<?php echo (int) $regel['voorraad']; ?>" style="width: 60px;">
              <input type="submit" value="Wijzig">
            </form>
          </td>
          <td><?php echo euro($regel['subtotaal']); ?></td>
          <td>
            <!-- Dit product uit de winkelwagen halen -->
            <form method="POST" action="winkelwagen.php">
              <input type="hidden" name="actie" value="verwijderen">
              <input type="hidden" name="product_id" value="<?php echo (int) $regel['id']; ?>">
              <input type="submit" value="Verwijderen">
            </form>
          </td>
        </tr>
      <?php } ?>
      <tr>
        <td colspan="5"><strong>Totaal</strong></td>
        <td colspan="2"><strong><?php echo euro($totaal); ?></strong></td>
      </tr>
    </table>

    <!-- Bestellen -->
    <form method="POST" action="winkelwagen.php" style="display: inline;">
      <input type="hidden" name="actie" value="bestellen">
      <input type="submit" value="Bestellen">
    </form>

    <!-- Winkelwagen leegmaken, met bevestigingsvraag -->
    <form method="POST" action="winkelwagen.php" style="display: inline;" onsubmit="return confirm('Winkelwagen leegmaken?');">
      <input type="hidden" name="actie" value="leegmaken">
      <input type="submit" value="Winkelwagen leegmaken">
    </form>
  <?php } ?>

</body>
</html>