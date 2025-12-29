<?php
require_once 'conexao.php';

try {
    $sql = file_get_contents('database.sql');
    $pdo->exec($sql);
    echo "Database migration completed successfully! Schema updated.";
} catch (PDOException $e) {
    echo "Migration failed: " . $e->getMessage();
}
?>