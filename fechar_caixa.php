<?php

session_start();

if (!isset($_SESSION["usuario_id"])) {
    header("Location: index.php");
    exit;
}

require_once "config/conexao.php";
require_once "config/dinheiro.php";

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
    AND caixa_id = ?
";

$stmtVendas =
    $conn->prepare($sqlVendas);

$stmtVendas->bind_param(
    "i",
    $id
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

$denominacoesEsperadas = array_fill_keys(array_keys(denominacoesDinheiro()), 0);
$stmtDenominacoes = $conn->prepare("SELECT valor_centavos, quantidade FROM caixa_denominacoes WHERE caixa_id = ?");
$stmtDenominacoes->bind_param("i", $id);
$stmtDenominacoes->execute();
$resultadoDenominacoes = $stmtDenominacoes->get_result();
while ($linha = $resultadoDenominacoes->fetch_assoc()) {
    $denominacoesEsperadas[(int)$linha["valor_centavos"]] = (int)$linha["quantidade"];
}


if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $contagemFinal = lerQuantidadesDinheiro($_POST["contagem"] ?? []);
    $valorFinal = totalDinheiro($contagemFinal) / 100;

    $stmtContagem = $conn->prepare("INSERT INTO caixa_contagem_fechamento (caixa_id, valor_centavos, quantidade) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE quantidade = VALUES(quantidade)");
    foreach ($contagemFinal as $valorCentavos => $quantidade) {
        $stmtContagem->bind_param("iii", $id, $valorCentavos, $quantidade);
        $stmtContagem->execute();
    }


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

.contagem{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin:18px 0}
.contagem label{background:#f7f7f7;padding:10px;border-radius:10px;margin:0}.contagem small{display:block;color:#777;margin:4px 0 7px}
.contagem input{margin:0;padding:10px;background:#fff}.total-contado{padding:14px;background:#181818;color:#fff;border-radius:10px;margin-bottom:16px;display:flex;justify-content:space-between}
@media(max-width:550px){.contagem{grid-template-columns:repeat(2,1fr)}}

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

<div class="contagem">
<?php foreach (denominacoesDinheiro() as $valorCentavos => $rotulo): ?>
<label><?php echo htmlspecialchars($rotulo); ?><small>Esperado: <?php echo $denominacoesEsperadas[$valorCentavos]; ?></small><input type="number" name="contagem[<?php echo $valorCentavos; ?>]" min="0" value="<?php echo $denominacoesEsperadas[$valorCentavos]; ?>" oninput="calcularContado()" required></label>
<?php endforeach; ?>
</div>
<div class="total-contado"><span>Total contado</span><strong id="totalContado">R$ <?php echo number_format($valorEsperado,2,',','.'); ?></strong></div>

<button type="submit">
Confirmar Fechamento
</button>

</form>

</div>

</div>

<script>
function calcularContado(){let c=0;document.querySelectorAll('.contagem input').forEach(i=>{c+=parseInt(i.name.match(/\[(\d+)\]/)[1],10)*(parseInt(i.value,10)||0)});document.getElementById('totalContado').textContent=(c/100).toLocaleString('pt-BR',{style:'currency',currency:'BRL'})}
calcularContado();
</script>
</body>

</html>
