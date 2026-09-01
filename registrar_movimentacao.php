<?php

session_start();

if (!isset($_SESSION["usuario_id"])) {
    header("Location: index.php");
    exit;
}

require_once "config/conexao.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {

    header("Location: caixa.php");
    exit;

}

$tipo =
    $_POST["tipo"] ?? "";

$descricao =
    trim($_POST["descricao"] ?? "");

$valor =
    (float)($_POST["valor"] ?? 0);

$usuarioId =
    (int)$_SESSION["usuario_id"];


$tiposPermitidos = [
    "entrada",
    "saida",
    "sangria"
];


if (
    !in_array(
        $tipo,
        $tiposPermitidos,
        true
    )
    ||
    $descricao === ""
    ||
    $valor <= 0
) {

    header(
        "Location: caixa.php?erro=1"
    );

    exit;

}


/* caixa aberto */

$sqlCaixa = "
    SELECT id
    FROM caixas
    WHERE status = 'aberto'
    ORDER BY id DESC
    LIMIT 1
";

$resultadoCaixa =
    $conn->query($sqlCaixa);


if (
    !$resultadoCaixa ||
    $resultadoCaixa->num_rows !== 1
) {

    header(
        "Location: caixa.php?erro=1"
    );

    exit;

}


$caixa =
    $resultadoCaixa->fetch_assoc();

$caixaId =
    (int)$caixa["id"];


$sql = "
    INSERT INTO movimentacoes_caixa
    (
        caixa_id,
        usuario_id,
        tipo,
        descricao,
        valor
    )
    VALUES
    (?, ?, ?, ?, ?)
";

$stmt =
    $conn->prepare($sql);

$stmt->bind_param(
    "iissd",
    $caixaId,
    $usuarioId,
    $tipo,
    $descricao,
    $valor
);


if ($stmt->execute()) {

    header(
        "Location: caixa.php?movimentacao=1"
    );

}else{

    header(
        "Location: caixa.php?erro=1"
    );

}

exit;