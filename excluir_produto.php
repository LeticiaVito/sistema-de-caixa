<?php

session_start();

if (!isset($_SESSION["usuario_id"])) {
    header("Location: index.php");
    exit;
}

if (($_SESSION["usuario_tipo"] ?? "") !== "admin") {
    header("Location: produtos.php?erro=permissao");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: produtos.php?erro=exclusao");
    exit;
}

$tokenRecebido = $_POST["csrf_token"] ?? "";
$tokenSessao = $_SESSION["csrf_token_produtos"] ?? "";

if (
    !is_string($tokenRecebido) ||
    $tokenSessao === "" ||
    !hash_equals($tokenSessao, $tokenRecebido)
) {
    header("Location: produtos.php?erro=exclusao");
    exit;
}

$id = (int)($_POST["id"] ?? 0);

if ($id <= 0) {
    header("Location: produtos.php?erro=exclusao");
    exit;
}

require_once "config/conexao.php";
require_once "config/foto_produto.php";

$conn->begin_transaction();

try {
    $stmtProduto = $conn->prepare("SELECT foto FROM produtos WHERE id = ? FOR UPDATE");
    $stmtProduto->bind_param("i", $id);
    $stmtProduto->execute();
    $resultadoProduto = $stmtProduto->get_result();

    if ($resultadoProduto->num_rows !== 1) {
        throw new RuntimeException("Produto não encontrado.");
    }

    $produto = $resultadoProduto->fetch_assoc();

    $stmtHistorico = $conn->prepare("SELECT 1 FROM itens_venda WHERE produto_id = ? LIMIT 1");
    $stmtHistorico->bind_param("i", $id);
    $stmtHistorico->execute();

    if ($stmtHistorico->get_result()->num_rows > 0) {
        $conn->rollback();
        header("Location: produtos.php?erro=historico");
        exit;
    }

    $stmtExcluir = $conn->prepare("DELETE FROM produtos WHERE id = ?");
    $stmtExcluir->bind_param("i", $id);

    if (!$stmtExcluir->execute() || $stmtExcluir->affected_rows !== 1) {
        throw new RuntimeException("Falha ao excluir produto.");
    }

    $conn->commit();
    excluirFotoProduto($produto["foto"] ?? null);
    unset($_SESSION["csrf_token_produtos"]);

    header("Location: produtos.php?excluido=1");
    exit;
} catch (Throwable $e) {
    $conn->rollback();
    header("Location: produtos.php?erro=exclusao");
    exit;
}
