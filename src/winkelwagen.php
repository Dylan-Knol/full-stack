<?php
session_start();
if (!isset($_SESSION['ingelogd'])) {
  header("Location: login.php");
  exit();
}

$conn = require_once "partials/dbconnection.php";

// De winkelwagen staat in de sessie als [product_id => aantal]
if (!isset($_SESSION['winkelwagen']) || !is_array($_SESSION['winkelwagen'])) {
  $_SESSION['winkelwagen'] = [];
}

function melding($type, $tekst)
{
  $_SESSION['flash'] = ['type' => $type, 'tekst' => $tekst];
}

function haalProduct($conn, $id)
{
  $stmt = $conn->prepare("SELECT id, naam, prijs, voorraad FROM product WHERE id = ?");
  $stmt->bind_param("i", $id);
  $stmt->execute();
  $product = $stmt->get_result()->fetch_assoc();
  $stmt->close();
  return $product;
}

function euro($bedrag)
{
  return "€ " . number_format($bedrag, 2, ',', '.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $actie = $_POST['actie'] ?? '';

  // Alleen terugsturen naar onze eigen pagina's
  $terug = $_POST['terug'] ?? 'winkelwagen.php';
  if (!preg_match('/^(voorraad|winkelwagen)\.php(\?[^\r\n]*)?$/', $terug)) {
    $terug = 'winkelwagen.php';
  }

  $productId = filter_var($_POST['product_id'] ?? null, FILTER_VALIDATE_INT);
  $aantal = filter_var($_POST['aantal'] ?? 1, FILTER_VALIDATE_INT);

  if ($actie === 'leegmaken') {
    $_SESSION['winkelwagen'] = [];
    melding('ok', 'Winkelwagen is leeggemaakt.');

  } elseif ($productId === false) {
    melding('fout', 'Ongeldig product.');

  } elseif ($actie === 'verwijderen') {
    unset($_SESSION['winkelwagen'][$productId]);
    melding('ok', 'Product is verwijderd uit je winkelwagen.');

  } elseif ($actie === 'toevoegen' || $actie === 'aanpassen') {
    $product = haalProduct($conn, $productId);

    if (!$product) {
      melding('fout', 'Dit product bestaat niet (meer).');
    } elseif ($aantal === false || $aantal < 1) {
      melding('fout', 'Aantal moet minimaal 1 zijn.');
    } else {
      $nieuwAantal = $actie === 'toevoegen'
        ? ($_SESSION['winkelwagen'][$productId] ?? 0) + $aantal
        : $aantal;

      if ($nieuwAantal > (int) $product['voorraad']) {
        melding('fout', "Van " . $product['naam'] . " zijn er maar " . $product['voorraad'] . " op voorraad.");
      } else {
        $_SESSION['winkelwagen'][$productId] = $nieuwAantal;
        melding('ok', $product['naam'] . " staat nu " . $nieuwAantal . "x in je winkelwagen.");
      }
    }
  }

  header("Location: " . $terug);
  exit();
}

// Producten in de winkelwagen ophalen (prijzen altijd uit de database, nooit uit het formulier)
$regels = [];
$totaal = 0;
$ids = array_keys($_SESSION['winkelwagen']);

if (!empty($ids)) {
  $placeholders = implode(',', array_fill(0, count($ids), '?'));
  $stmt = $conn->prepare("SELECT id, naam, kleur, soort, prijs, voorraad FROM product WHERE id IN ($placeholders) ORDER BY naam");
  $stmt->bind_param(str_repeat('i', count($ids)), ...$ids);
  $stmt->execute();
  $result = $stmt->get_result();

  while ($row = $result->fetch_assoc()) {
    $row['aantal'] = $_SESSION['winkelwagen'][$row['id']];
    $row['subtotaal'] = $row['prijs'] * $row['aantal'];
    $totaal += $row['subtotaal'];
    $regels[] = $row;
  }
  $stmt->close();

  // Producten die niet meer bestaan uit de winkelwagen halen
  $gevonden = array_column($regels, 'id');
  foreach ($ids as $id) {
    if (!in_array($id, $gevonden)) {
      unset($_SESSION['winkelwagen'][$id]);
    }
  }
}

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
?>
<!DOCTYPE html>
<html lang="nl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Winkelwagen</title>
  <link rel="stylesheet" href="css/style.css">
</head>
<body>

  <a href="logout.php">Uitloggen</a>
  <a href="voorraad.php">Verder winkelen</a>

  <h1>Winkelwagen</h1>

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
            <form method="POST" action="winkelwagen.php">
              <input type="hidden" name="actie" value="aanpassen">
              <input type="hidden" name="product_id" value="<?php echo (int) $regel['id']; ?>">
              <input type="number" name="aantal" value="<?php echo (int) $regel['aantal']; ?>" min="1" max="<?php echo (int) $regel['voorraad']; ?>" style="width: 60px;">
              <input type="submit" value="Wijzig">
            </form>
          </td>
          <td><?php echo euro($regel['subtotaal']); ?></td>
          <td>
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

    <form method="POST" action="winkelwagen.php" onsubmit="return confirm('Winkelwagen leegmaken?');">
      <input type="hidden" name="actie" value="leegmaken">
      <input type="submit" value="Winkelwagen leegmaken">
    </form>
  <?php } ?>

</body>
</html>