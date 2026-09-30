<?php
$host = "localhost";
$db = "portal_escolar";
$user = "portaluser";
$pass = "ibem@2026";

// Conexão tolerante a falhas (não derruba a aplicação caso o MySQL esteja inativo)
$conn = @new mysqli($host, $user, $pass, $db);

if ($conn && $conn->connect_error) {
    $conn = null;
}
?>
