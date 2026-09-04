<?php

session_start();

if (!isset($_SESSION["usuario_id"])) {
    header("Location: index.php");
    exit;
}

require_once "config/conexao.php";

if (($_SESSION["usuario_tipo"] ?? "") !== "admin") {
    header("Location: vendas.php?erro=permissao");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: vendas.php?erro=cancelamento");
    exit;
}

$tokenRecebido = $_POST["csrf_token"] ?? "";
$tokenSessao = $_SESSION["csrf_token"] ?? "";

if (
    $tokenSessao === "" ||
    !is_string($tokenRecebido) ||
    !hash_equals($tokenSessao, $tokenRecebido)
) {
    header("Location: vendas.php?erro=cancelamento");
    exit;
}

$id = isset($_POST["id"])
    ? (int)$_POST["id"]
    : 0;

if ($id <= 0) {
    header("Location: vendas.php?erro=cancelamento");
    exit;
}

$conn->begin_transaction();

try {

    $sqlVenda = "
        SELECT status, caixa_id, forma_pagamento
        FROM vendas
        WHERE id = ?
        FOR UPDATE
    ";

    $stmtVenda = $conn->prepare($sqlVenda);
    $stmtVenda->bind_param("i", $id);
    $stmtVenda->execute();

    $resultadoVenda = $stmtVenda->get_result();

    if ($resultadoVenda->num_rows !== 1) {

        throw new Exception(
            "Venda não encontrada."
        );

    }

    $venda = $resultadoVenda->fetch_assoc();

    if ($venda["status"] === "cancelada") {

        throw new Exception(
            "Venda já cancelada."
        );

    }


    $sqlItens = "
        SELECT
            produto_id,
            quantidade
        FROM itens_venda
        WHERE venda_id = ?
    ";

    $stmtItens = $conn->prepare($sqlItens);
    $stmtItens->bind_param("i", $id);
    $stmtItens->execute();

    $resultadoItens = $stmtItens->get_result();


    while ($item = $resultadoItens->fetch_assoc()) {

        $sqlEstoque = "
            UPDATE produtos
            SET estoque = estoque + ?
            WHERE id = ?
        ";

        $stmtEstoque =
            $conn->prepare($sqlEstoque);

        $stmtEstoque->bind_param(
            "ii",
            $item["quantidade"],
            $item["produto_id"]
        );

        if (!$stmtEstoque->execute() || $stmtEstoque->affected_rows !== 1) {
            throw new Exception("Não foi possível devolver o item ao estoque.");
        }

    }


    $sqlCancelar = "
        UPDATE vendas
        SET status = 'cancelada'
        WHERE id = ?
    ";

    $stmtCancelar =
        $conn->prepare($sqlCancelar);

    $stmtCancelar->bind_param(
        "i",
        $id
    );

    if (!$stmtCancelar->execute() || $stmtCancelar->affected_rows !== 1) {
        throw new Exception("Não foi possível cancelar a venda.");
    }

    if ($venda["forma_pagamento"] === "dinheiro") {
        $stmtDinheiro = $conn->prepare("SELECT tipo, valor_centavos, quantidade FROM venda_dinheiro_detalhes WHERE venda_id = ?");
        $stmtDinheiro->bind_param("i", $id);
        $stmtDinheiro->execute();
        $resultadoDinheiro = $stmtDinheiro->get_result();
        $stmtSaldo = $conn->prepare("UPDATE caixa_denominacoes SET quantidade = quantidade + ? WHERE caixa_id = ? AND valor_centavos = ?");
        while ($detalhe = $resultadoDinheiro->fetch_assoc()) {
            $ajuste = $detalhe["tipo"] === "recebido" ? -(int)$detalhe["quantidade"] : (int)$detalhe["quantidade"];
            $caixaVendaId = (int)$venda["caixa_id"];
            $valorCentavos = (int)$detalhe["valor_centavos"];
            $stmtSaldo->bind_param("iii", $ajuste, $caixaVendaId, $valorCentavos);
            if (!$stmtSaldo->execute()) throw new Exception("Não foi possível ajustar o dinheiro do caixa.");
        }
    }


    $conn->commit();

    unset($_SESSION["csrf_token"]);


    header(
        "Location: vendas.php?cancelada=1"
    );

    exit;


}catch(Exception $e){

    $conn->rollback();

    header(
        "Location: vendas.php?erro=cancelamento"
    );

    exit;

}
