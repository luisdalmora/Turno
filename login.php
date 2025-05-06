<?php
include"conexao.php";

$usuario = $_POST['usuario'];
$senha = md5($_POST['senha']);

$sql = "SELECT id FROM usuarios WHERE usuario = '$usuario' AND senha = '$senha'";
$resultado = $conexao->query($sql);

if ($resultado->num_rows == 1) {
    echo "Login realizado com sucesso!";
    // Redirecione para a página home.html ou outra página protegida
    header("Location: home.html");
} else {
    echo "Usuário ou senha incorretos.";
    header("Location: index.html"); // Redireciona de volta para o login
}

$conexao->close();
?>