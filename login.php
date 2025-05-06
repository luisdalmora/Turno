<?php
include"conexao.php";

// Recebe os dados do formulário
$usuario = $_POST['usuario'] ?? '';
$senha = $_POST['password'] ?? '';

// Previne SQL Injection
$usuario = $conn->real_escape_string($usuario);
$senha = $conn->real_escape_string($senha);

// Consulta SQL
$sql = "SELECT * FROM usuarios WHERE usuario = '$usuario' AND senha = '$senha'";
$result = $conn->query($sql);

if ($result->num_rows === 1) {
    // Login bem-sucedido
    header("Location: home.html");
    exit();
} else {
    // Falha no login
    echo "<script>alert('Usuário ou senha inválidos'); window.location.href='index.html';</script>";
}

$conn->close();
?>
?>