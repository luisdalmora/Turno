<?php
include"conexao.php"; // Inclui o arquivo de conexão

$nome_completo = $_POST['nome_completo'];
$email = $_POST['email'];
$usuario = $_POST['usuario'];
$senha = md5($_POST['senha']); // Criptografa a senha

$sql = "INSERT INTO usuarios (nome_completo, email, usuario, senha) 
        VALUES ('$nome_completo', '$email', '$usuario', '$senha')";

if ($conexao->query($sql) === TRUE) {
    echo "Cadastro realizado com sucesso!";
    header("Location: index.html"); // Redireciona para a página de login
} else {
    echo "Erro ao cadastrar: " . $conexao->error;
}

$conexao->close();
?>