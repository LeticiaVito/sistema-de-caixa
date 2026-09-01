<?php

session_start();

if (!isset($_SESSION["usuario_id"])) {
    header("Location: index.php");
    exit;
}

require_once "config/conexao.php";

$id = isset($_GET["id"])
    ? (int)$_GET["id"]
    : 0;

if ($id <= 0) {
    header("Location: vendas.php");
    exit;
}

$conn->begin_transaction();

try {

    $sqlVenda = "
        SELECT status
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

        $stmtEstoque->execute();

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

    $stmtCancelar->execute();


    $conn->commit();


    header(
        "Location: vendas.php?cancelada=1"
    );

    exit;


}catch(Exception $e){

    $conn->rollback();

    header(
        "Location: vendas.php"
    );

    exit;

}