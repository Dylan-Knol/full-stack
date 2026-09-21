<?php
session_start();
if (!isset($_SESSION['ingelogd'])) {
  header("Location: login.php");
  exit();
}

// Alleen via een formulier (POST)
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  header("Location: voorraad.php");
  exit();
}

$conn = require_once "partials/dbconnection.php";

// Alleen terugsturen naar voorraad.php (met de filters die je had ingesteld)
$terug = $_POST['terug'] ?? 'voorraad.php';
if (!preg_match('/^voorraad\.php(\?[^\r\n]*)?$/', $terug)) {
  $terug = 'voorraad.php';
}

$productId = filter_var($_POST['product_id'] ?? null, FILTER_VALIDATE_INT);

if ($productId === false) {
  $_SESSION['flash'] = ['type' => 'fout', 'tekst' => 'Ongeldig product.'];
  header("Location: " . $terug);
  exit();
}

try {
  // Eerst de bestelling(en) van dit product, anders blokkeert de foreign key het verwijderen.
  // Beide of geen: bij een fout wordt alles teruggedraaid.
  $conn->begin_transaction();

  $stmt = $conn->prepare("DELETE FROM bestelling WHERE product_id = ?");
  $stmt->bind_param("i", $productId);
  $stmt->execute();
  $stmt->close();

  $stmt = $conn->prepare("DELETE FROM product WHERE id = ?");
  $stmt->bind_param("i", $productId);
  $stmt->execute();
  $verwijderd = $stmt->affected_rows;
  $stmt->close();

  $conn->commit();

  // Ook uit de winkelwagen halen als het daarin zat
  unset($_SESSION['winkelwagen'][$productId]);

  if ($verwijderd > 0) {
    $_SESSION['flash'] = ['type' => 'ok', 'tekst' => 'Product en bijbehorende bestelling(en) zijn verwijderd.'];
  } else {
    $_SESSION['flash'] = ['type' => 'fout', 'tekst' => 'Dit product bestaat niet (meer).'];
  }
} catch (Throwable $e) {
  $conn->rollback();
  error_log($e);
  $_SESSION['flash'] = ['type' => 'fout', 'tekst' => 'Verwijderen is mislukt. Probeer het opnieuw.'];
}

header("Location: " . $terug);
exit();