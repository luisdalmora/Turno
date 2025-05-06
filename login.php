<?php
include"conexao.php";
$usuario = $_POST['usuario'];
$senha = $_POST['password'];

$stmt = $pdo->prepare("SELECT * FROM usuarios WHERE usuario = :usuario AND senha = :senha");
$stmt->execute(['usuario' => $usuario, 'senha' => $senha]);

if ($stmt->rowCount() > 0) {
    header("Location: home.html");
} else {
    echo "<script>alert('Usuário ou senha inválidos'); window.location.href='index.html';</script>";
}

