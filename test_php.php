<?php
echo "<h2>Teste de Extensões PHP</h2>";

echo "<h3>cURL:</h3>";
if (function_exists('curl_init')) {
    echo "✅ cURL está funcionando!<br>";
} else {
    echo "❌ cURL NÃO está habilitado<br>";
}

echo "<h3>PDO MySQL:</h3>";
if (extension_loaded('pdo_mysql')) {
    echo "✅ PDO MySQL está funcionando!<br>";
} else {
    echo "❌ PDO MySQL NÃO está habilitado<br>";
}

echo "<h3>OpenSSL:</h3>";
if (extension_loaded('openssl')) {
    echo "✅ OpenSSL está funcionando!<br>";
} else {
    echo "❌ OpenSSL NÃO está habilitado<br>";
}

echo "<hr>";
echo "<h3>extension_dir:</h3>";
echo ini_get('extension_dir');

echo "<br><br>";
echo "<h3>Todas extensões carregadas:</h3>";
echo implode(', ', get_loaded_extensions());
?>
