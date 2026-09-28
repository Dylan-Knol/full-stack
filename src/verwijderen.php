<?php
session_start();

// Alleen voor ingelogde gebruikers
if (!isset($_SESSION['ingelogd'])) {
  header("Location: login.php");
  exit();
}

// Alleen via een formulier (POST) toegestaan, niet door de URL rechtstreeks te openen
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  header("Location: voorraad.php");
  exit();
}

$conn = require_once "partials/dbconnection.php";

// Waar sturen we de gebruiker na afloop naartoe? Alleen naar voorraad.php
// (met filters), zodat niemand ons naar een andere website kan doorsturen.
$terug = $_POST['terug'] ?? 'voorraad.php';
if (!preg_match('/^voorraad\.php(\?[^\r\n]*)?$/', $terug)) {
  $terug = 'voorraad.php';
}

// Product-id controleren: moet een heel getal zijn
$productId = filter_var($_POST['product_id'] ?? null, FILTER_VALIDATE_INT);

if ($productId === false) {
  $_SESSION['flash'] = ['type' => 'fout', 'tekst' => 'Ongeldig product.'];
  header("Location: " . $terug);
  exit();
}

try {
  // Eerst de bestelling(en) van dit product verwijderen, anders blokkeert de
  // foreign key het verwijderen van het product. Beide stappen samen in één
  // transactie: gaat er iets mis, dan wordt alles teruggedraaid.
  $conn->begin_transaction();

  $stmt = $conn->prepare("DELETE FROM bestelling WHERE product_id = ?");
  $stmt->bind_param("i", $productId);
  $stmt->execute();
  $stmt->close();

  $stmt = $conn->prepare("DELETE FROM product WHERE id = ?");
  $stmt->bind_param("i", $productId);
  $stmt->execute();
  $verwijderd = $stmt->affected_rows; // aantal echt verwijderde producten (0 of 1)
  $stmt->close();

  $conn->commit();

  // Ook uit de winkelwagen halen als het daarin zat
  unset($_SESSION['winkelwagen'][$productId]);

  // Melding voor op voorraad.php (wordt daar getoond en meteen gewist)
  if ($verwijderd > 0) {
    $_SESSION['flash'] = ['type' => 'ok', 'tekst' => 'Product en bijbehorende bestelling(en) zijn verwijderd.'];
  } else {
    $_SESSION['flash'] = ['type' => 'fout', 'tekst' => 'Dit product bestaat niet (meer).'];
  }
} catch (Throwable $e) {
  // Bij een fout: alles terugdraaien en de fout loggen
  $conn->rollback();
  error_log($e);
  $_SESSION['flash'] = ['type' => 'fout', 'tekst' => 'Verwijderen is mislukt. Probeer het opnieuw.'];
}

header("Location: " . $terug);
exit();