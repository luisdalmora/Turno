<?php
echo "Tentando conectar a https://oauth2.googleapis.com/token via cURL do PHP...<br>";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "https://oauth2.googleapis.com/token");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_VERBOSE, 1); // Habilita saída verbosa
curl_setopt($ch, CURLOPT_HEADER, 1);   // Inclui o cabeçalho na saída

// Para depurar problemas de SSL (se o erro mudar depois de resolver o Connection Refused):
// curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true); // Mantenha true para produção
// curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);   // Mantenha 2 para produção
// Se você suspeitar do cacert.pem, pode especificar aqui também, embora o php.ini deva cobrir:
// curl_setopt($ch, CURLOPT_CAINFO, "C:/xampp/php/extras/ssl/cacert.pem");


$response = curl_exec($ch);
$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error_number = curl_errno($ch);
$curl_error_message = curl_error($ch);

if ($curl_error_number) {
    echo "Erro do cURL (" . $curl_error_number . "): " . htmlspecialchars($curl_error_message) . "<br>";
} else {
    echo "Conexão bem-sucedida! Código de status HTTP: " . $httpcode . "<br>";
}
echo "<pre>Resposta completa (incluindo cabeçalhos):\n" . htmlspecialchars($response) . "</pre>";

curl_close($ch);
