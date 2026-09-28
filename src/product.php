<?php
session_start();

// Alleen voor ingelogde gebruikers
if (!isset($_SESSION['ingelogd'])) {
  header("Location: login.php");
  exit();
}

$conn = require_once "partials/dbconnection.php";

$errors = []; // validatiefouten
$statussen = ["Geplaatst", "Verwerkt", "Verzonden", "Afgeleverd", "Geannuleerd"]; // toegestane bestelstatussen

// Formulier verstuurd: waarden overnemen en controleren
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // trim() haalt spaties aan begin/eind weg; "?? ''" voorkomt fouten als een veld ontbreekt
  $naam        = trim($_POST['naam'] ?? '');
  $gewicht     = $_POST['gewicht'] ?? '';
  $kleur       = trim($_POST['kleur'] ?? '');
  $dikte       = $_POST['dikte'] ?? '';
  $soort       = trim($_POST['soort'] ?? '');
  $gelooid     = isset($_POST['gelooid']) ? 1 : 0; // checkbox: aangevinkt = 1
  $prijs       = $_POST['prijs'] ?? '';
  $voorraad    = $_POST['voorraad'] ?? '';
  $hoeveelheid = $_POST['hoeveelheid'] ?? '';
  $status      = $_POST['status'] ?? '';

  // Validatie: elke fout komt in $errors
  if ($naam === '') $errors[] = "Naam is verplicht.";
  elseif (mb_strlen($naam) > 100) $errors[] = "Naam mag maximaal 100 tekens zijn.";
  if ($kleur === '') $errors[] = "Kleur is verplicht.";
  if ($soort === '') $errors[] = "Soort leer is verplicht.";
  // Max 999,99 omdat de database-kolommen decimal(5,2) zijn
  if (!is_numeric($gewicht) || $gewicht <= 0 || $gewicht > 999.99) $errors[] = "Gewicht moet een positief getal zijn (max 999,99).";
  if (!is_numeric($dikte) || $dikte <= 0 || $dikte > 999.99) $errors[] = "Dikte moet een positief getal zijn (max 999,99).";
  if (!is_numeric($prijs) || $prijs <= 0) $errors[] = "Prijs moet een positief getal zijn.";
  if (filter_var($voorraad, FILTER_VALIDATE_INT) === false || $voorraad < 0) $errors[] = "Voorraad moet een heel getal van 0 of hoger zijn.";
  if (filter_var($hoeveelheid, FILTER_VALIDATE_INT) === false || $hoeveelheid <= 0) $errors[] = "Hoeveelheid van de bestelling moet een positief heel getal zijn.";
  if (!in_array($status, $statussen, true)) $errors[] = "Kies een geldige status voor de bestelling.";

  // Alles goed? Dan opslaan
  if (empty($errors)) {
    try {
      // Product en bestelling worden samen opgeslagen: lukt één van beide niet,
      // dan wordt alles teruggedraaid.
      $conn->begin_transaction();

      // 1. Product opslaan
      $stmt = $conn->prepare("INSERT INTO product (naam, gewicht, kleur, dikte, soort, gelooid, prijs, voorraad) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
      // Waarden omzetten naar het juiste type voor bind_param
      $gewichtF = (float) $gewicht;
      $diktef = (float) $dikte;
      $prijsF = (float) $prijs;
      $voorraadI = (int) $voorraad;
      // Types: s=string, d=decimal, i=integer (in de volgorde van de ?-tekens)
      $stmt->bind_param("sdsdsidi", $naam, $gewichtF, $kleur, $diktef, $soort, $gelooid, $prijsF, $voorraadI);
      $stmt->execute();
      $productId = $conn->insert_id; // id van het zojuist toegevoegde product
      $stmt->close();

      // 2. Bestelling aanmaken, gekoppeld aan het nieuwe product.
      // Het bestelnummer maken we pas na de insert, want het is gebaseerd op het bestelling-id.
      $tempBestelnr = "TEMP";
      $hoeveelheidI = (int) $hoeveelheid;
      $bestelStmt = $conn->prepare("INSERT INTO bestelling (hoeveelheid, bestelnr, status, besteldatum, product_id) VALUES (?, ?, ?, NOW(), ?)");
      $bestelStmt->bind_param("issi", $hoeveelheidI, $tempBestelnr, $status, $productId);
      $bestelStmt->execute();
      $bestellingId = $conn->insert_id;
      $bestelStmt->close();

      // 3. Echt bestelnummer invullen, bv. id 51 wordt "B0000051"
      $bestelnr = sprintf("B%07d", $bestellingId);
      $updateStmt = $conn->prepare("UPDATE bestelling SET bestelnr = ? WHERE id = ?");
      $updateStmt->bind_param("si", $bestelnr, $bestellingId);
      $updateStmt->execute();
      $updateStmt->close();

      // Alles gelukt: definitief opslaan
      $conn->commit();

      header("Location: voorraad.php?toegevoegd=1");
      exit();
    } catch (Throwable $e) {
      // Iets ging mis: alles terugdraaien, fout loggen en een melding tonen
      $conn->rollback();
      error_log($e);
      $errors[] = "Opslaan is mislukt. Probeer het opnieuw.";
    }
  }
}
?>
<!DOCTYPE html>
<html lang="nl">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Nieuw product</title>
  <link rel="stylesheet" href="wachtwoord.css">
</head>

<body style="background-color: #1c3c30">
  <div id="loginContainer">
    <h2 id="loginTitle">Nieuw product toevoegen</h2>

    <!-- Validatiefouten tonen -->
    <?php if (!empty($errors)) { ?>
      <div style="color: #ffb3b3; margin-bottom: 10px;">
        <?php foreach ($errors as $error) echo "<p>" . htmlspecialchars($error) . "</p>"; ?>
      </div>
    <?php } ?>

    <!-- Bij een fout blijven de ingevulde waarden staan via value="<?php echo ...; ?>" -->
    <form method="POST" action="product.php" style="text-align: left;">
      <label>Naam</label><br>
      <input type="text" name="naam" maxlength="100" value="<?php echo htmlspecialchars($_POST['naam'] ?? ''); ?>" required><br><br>

      <label>Gewicht (kg)</label><br>
      <input type="number" step="0.01" name="gewicht" value="<?php echo htmlspecialchars($_POST['gewicht'] ?? ''); ?>" required><br><br>

      <label>Kleur</label><br>
      <input type="text" name="kleur" value="<?php echo htmlspecialchars($_POST['kleur'] ?? ''); ?>" required><br><br>

      <label>Dikte (mm)</label><br>
      <input type="number" step="0.01" name="dikte" value="<?php echo htmlspecialchars($_POST['dikte'] ?? ''); ?>" required><br><br>

      <label>Soort leer</label><br>
      <input type="text" name="soort" value="<?php echo htmlspecialchars($_POST['soort'] ?? ''); ?>" required><br><br>

      <label>
        <input type="checkbox" name="gelooid" <?php if (!empty($_POST['gelooid'])) echo "checked"; ?>>
        Gelooid
      </label><br><br>

      <label>Prijs (&euro;)</label><br>
      <input type="number" step="0.01" name="prijs" value="<?php echo htmlspecialchars($_POST['prijs'] ?? ''); ?>" required><br><br>

      <label>Voorraad (aantal)</label><br>
      <input type="number" name="voorraad" min="0" value="<?php echo htmlspecialchars($_POST['voorraad'] ?? ''); ?>" required><br><br>

      <hr>
      <h3 style="margin-top: 0;">Bijbehorende bestelling</h3>

      <label>Hoeveelheid</label><br>
      <input type="number" name="hoeveelheid" min="1" value="<?php echo htmlspecialchars($_POST['hoeveelheid'] ?? ''); ?>" required><br><br>

      <label>Status bestelling</label><br>
      <select name="status" required>
        <option value="">-- kies status --</option>
        <?php foreach ($statussen as $s) { ?>
          <option value="<?php echo $s; ?>" <?php if (($_POST['status'] ?? '') === $s) echo "selected"; ?>><?php echo $s; ?></option>
        <?php } ?>
      </select><br><br>

      <div id="loginRegister">
        <input type="submit" value="Opslaan">
        <button type="button" onclick="window.location.href='voorraad.php'">Terug</button>
      </div>
    </form>
  </div>
</body>

</html>