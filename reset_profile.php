<?php
session_start();
require_once 'conexao.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// Delete profile to allow fresh setup
$stmt = $pdo->prepare("DELETE FROM work_profiles WHERE user_id = ?");
$stmt->execute([$user_id]);

header("Location: setup.php");
exit;
