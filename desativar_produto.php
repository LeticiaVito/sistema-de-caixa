<?php

session_start();

if (!isset($_SESSION["usuario_id"])) {
    header("Location: index.php");
    exit;
}

require_once "config/conexao.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: produtos.php?erro=status");
    exit;
}

$tokenRecebido = $_POST["csrf_token"] ?? "";
$tokenSessao = $_SESSION["csrf_token_produtos"] ?? "";

if (!is_string($tokenRecebido) || $tokenSessao === "" || !hash_equals($tokenSessao, $tokenRecebido)) {
    header("Location: produtos.php?erro=status");
    exit;
}

$id = isset($_POST["id"]) ? (int)$_POST["id"] : 0;
$acao = $_POST["acao"] ?? "";

if ($id <= 0 || !in_array($acao, ["arquivar", "restaurar"], true)) {
    header("Location: produtos.php");
    exit;
}

$novoStatus = $acao === "restaurar" ? 1 : 0;
$sql = "UPDATE produtos
        SET ativo = ?
        WHERE id = ?";

$stmt = $conn->prepare($sql);

$stmt->bind_param("ii", $novoStatus, $id);

$stmt->execute();

$destino = $acao === "restaurar"
    ? "produtos.php?arquivados=1&restaurado=1"
    : "produtos.php?arquivado=1";

header("Location: " . $destino);
exit;
