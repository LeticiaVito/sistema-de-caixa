<?php

session_start();

if (!isset($_SESSION["usuario_id"])) {
    header("Location: index.php");
    exit;
}

require_once "config/conexao.php";

$id =
    isset($_GET["id"])
    ? (int)$_GET["id"]
    : 0;


if ($id <= 0) {

    header("Location: caixa.php");
    exit;

}


$sql = "
    SELECT *
    FROM caixas
    WHERE id = ?
    AND status = 'aberto'
    LIMIT 1
";

$stmt =
    $conn->prepare($sql);

$stmt->bind_param(
    "i",
    $id
);

$stmt->execute();

$resultado =
    $stmt->get_result();


if ($resultado->num_rows !== 1) {

    header("Location: caixa.php");
    exit;

}


$caixa =
    $resultado->fetch_assoc();


/* movimentações */

$sqlMov = "
    SELECT
        COALESCE(
            SUM(
                CASE
                    WHEN tipo = 'entrada'
                    THEN valor
                    ELSE 0
                END
            ),
            0
        ) AS entradas,

        COALESCE(
            SUM(
                CASE
                    WHEN tipo = 'saida'
                    THEN valor
                    ELSE 0
                END
            ),
            0
        ) AS saidas,

        COALESCE(
            SUM(
                CASE
                    WHEN tipo = 'sangria'
                    THEN valor
                    ELSE 0
                END
            ),
            0
        ) AS sangrias

    FROM movimentacoes_caixa
    WHERE caixa_id = ?
";

$stmtMov =
    $conn->prepare($sqlMov);

$stmtMov->bind_param(
    "i",
    $id
);

$stmtMov->execute();

$mov =
    $stmtMov
    ->get_result()
    ->fetch_assoc();


/* vendas em dinheiro */

$sqlVendas = "
    SELECT
        COALESCE(SUM(total),0) AS total
    FROM vendas
    WHERE status = 'finalizada'
    AND forma_pagamento = 'dinheiro'
    AND data_venda >= ?
";

$stmtVendas =
    $conn->prepare($sqlVendas);

$stmtVendas->bind_param(
    "s",
    $caixa["aberto_em"]
);

$stmtVendas->execute();

$vendas =
    $stmtVendas
    ->get_result()
    ->fetch_assoc();


$valorEsperado =
    (float)$caixa["valor_inicial"]
    + (float)$vendas["total"]
    + (float)$mov["entradas"]
    - (float)$mov["saidas"]
    - (float)$mov["sangrias"];


if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $valorFinal =
        (float)($_POST["valor_final"] ?? 0);


    $sqlFechar = "
        UPDATE caixas
        SET
            valor_final = ?,
            status = 'fechado',
            fechado_em = NOW()
        WHERE id = ?
        AND status = 'aberto'
    ";

    $stmtFechar =
        $conn->prepare($sqlFechar);

    $stmtFechar->bind_param(
        "di",
        $valorFinal,
        $id
    );


    if ($stmtFechar->execute()) {

        header(
            "Location: caixa.php?fechado=1"
        );

        exit;

    }

}

?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Fechar Caixa | Cantina do Tio Fabinho</title>

<style>

*{
    margin:0;
    padding:0;
    box-sizing:border-box;
    font-family:Arial, Helvetica, sans-serif;
}

body{
    background:#f5f5f5;
}

.container{
    max-width:600px;
    margin:60px auto;
    padding:20px;
}

.voltar{
    display:inline-block;
    margin-bottom:20px;
    color:#555;
    text-decoration:none;
}

.card{
    background:white;
    padding:30px;
    border-radius:18px;
    box-shadow:0 5px 20px rgba(0,0,0,.06);
}

h1{
    margin-bottom:8px;
}

.sub{
    color:#777;
    margin-bottom:30px;
}

.resumo{
    background:#f7f7f7;
    padding:20px;
    border-radius:12px;
    margin-bottom:20px;
}

.resumo span{
    display:block;
    color:#777;
    font-size:13px;
    margin-bottom:5px;
}

.resumo strong{
    font-size:30px;
}

label{
    display:block;
    font-size:13px;
    font-weight:bold;
    margin-bottom:8px;
}

input{
    width:100%;
    padding:14px;
    border:1px solid #ddd;
    border-radius:10px;
    margin-bottom:20px;
}

button{
    width:100%;
    padding:14px;
    border:none;
    background:#b42318;
    color:white;
    border-radius:10px;
    font-weight:bold;
    cursor:pointer;
}

button:hover{
    background:#8f1c14;
}

</style>

</head>

<body>

<div class="container">

<a href="caixa.php" class="voltar">
← Voltar
</a>

<div class="card">

<h1>Fechar Caixa</h1>

<p class="sub">
Confira o valor esperado e informe o valor contado.
</p>

<div class="resumo">

<span>
Valor esperado no caixa
</span>

<strong>

R$
<?php
echo number_format(
    $valorEsperado,
    2,
    ",",
    "."
);
?>

</strong>

</div>

<form method="POST">

<label>
Valor contado no caixa
</label>

<input
type="number"
name="valor_final"
step="0.01"
min="0"
required
>

<button type="submit">
Confirmar Fechamento
</button>

</form>

</div>

</div>

</body>

</html>