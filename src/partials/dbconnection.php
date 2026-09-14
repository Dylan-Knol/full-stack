<?php
$servername = "mysql";
$username = "root"; 
$password = "root"; 
$database = "fullstack";

try {
    $conn = new mysqli($servername, $username, $password, $database);
    if ($conn->connect_error) {
        error_log($conn->connect_error);
        exit("Connection DB failed");
    }
} catch (Exception $e) {
    error_log($e);
    exit("Connection DB failed");
}

return $conn;