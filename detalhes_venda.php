<?php

session_start();

if (!isset($_SESSION["usuario_id"])) {
    header("Location: index.php");
    exit;
}

require_once "config/conexao.php";

if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

$id = isset($_GET["id"])
    ? (int)$_GET["id"]
    : 0;

if ($id <= 0) {
    header("Location: vendas.php");
    exit;
}

$sqlVenda = "
    SELECT
        v.*,
        u.nome AS usuario_nome
    FROM vendas v
    INNER JOIN usuarios u
        ON u.id = v.usuario_id
    WHERE v.id = ?
    LIMIT 1
";

$stmt = $conn->prepare($sqlVenda);
$stmt->bind_param("i", $id);
$stmt->execute();

$resultadoVenda = $stmt->get_result();

if ($resultadoVenda->num_rows !== 1) {
    header("Location: vendas.php");
    exit;
}

$venda = $resultadoVenda->fetch_assoc();


$sqlItens = "
    SELECT
        iv.quantidade,
        iv.preco_unitario,
        iv.subtotal,
        p.nome
    FROM itens_venda iv
    INNER JOIN produtos p
        ON p.id = iv.produto_id
    WHERE iv.venda_id = ?
";

$stmtItens = $conn->prepare($sqlItens);
$stmtItens->bind_param("i", $id);
$stmtItens->execute();

$resultadoItens = $stmtItens->get_result();

?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Venda #<?php echo $id; ?> | Cantina do Tio Fabinho</title>

<style>

*{
    margin:0;
    padding:0;
    box-sizing:border-box;
    font-family:Arial, Helvetica, sans-serif;
}

body{
    background:#f5f5f5;
    color:#222;
}

.container{
    max-width:900px;
    margin:40px auto;
    padding:20px;
}

.voltar{
    display:inline-block;
    margin-bottom:20px;
    text-decoration:none;
    color:#555;
}

.card{
    background:white;
    border-radius:18px;
    padding:28px;
    box-shadow:0 5px 20px rgba(0,0,0,.06);
    margin-bottom:20px;
}

.topo{
    display:flex;
    justify-content:space-between;
    gap:20px;
    margin-bottom:25px;
}

.topo h1{
    font-size:27px;
    margin-bottom:5px;
}

.topo p{
    color:#777;
    font-size:13px;
}

.badge{
    display:inline-block;
    padding:7px 11px;
    border-radius:20px;
    font-size:12px;
}

.finalizada{
    background:#e8f7ec;
    color:#247a3d;
}

.cancelada{
    background:#ffe5e5;
    color:#b42318;
}

.info-grid{
    display:grid;
    grid-template-columns:repeat(3,1fr);
    gap:15px;
}

.info{
    background:#f7f7f7;
    padding:15px;
    border-radius:11px;
}

.info span{
    display:block;
    font-size:11px;
    color:#888;
    margin-bottom:5px;
}

.info strong{
    font-size:14px;
}

table{
    width:100%;
    border-collapse:collapse;
    margin-top:20px;
}

th,
td{
    padding:13px;
    text-align:left;
    border-bottom:1px solid #eee;
}

th{
    font-size:12px;
    color:#777;
}

td{
    font-size:14px;
}

.total{
    display:flex;
    justify-content:flex-end;
    align-items:center;
    gap:20px;
    margin-top:25px;
    font-size:17px;
}

.total strong{
    font-size:25px;
}

.btn-cancelar{
    display:block;
    margin-top:25px;
    width:100%;
    text-align:center;
    padding:14px;
    background:#ffe5e5;
    color:#b42318;
    text-decoration:none;
    border-radius:10px;
    font-weight:bold;
    border:none;
    cursor:pointer;
    font-size:14px;
}

.btn-cancelar:hover{
    background:#ffd4d4;
}

@media(max-width:700px){

    .info-grid{
        grid-template-columns:1fr;
    }

}

</style>

</head>

<body>

<div class="container">

<a href="vendas.php" class="voltar">
← Voltar para vendas
</a>

<div class="card">

<div class="topo">

<div>

<h1>
Venda #<?php echo $venda["id"]; ?>
</h1>

<p>
Detalhes completos da venda.
</p>

</div>

<div>

<?php if (
    $venda["status"] === "finalizada" &&
    ($_SESSION["usuario_tipo"] ?? "") === "admin"
): ?>

<span class="badge finalizada">
Finalizada
</span>

<?php else: ?>

<span class="badge cancelada">
Cancelada
</span>

<?php endif; ?>

</div>

</div>


<div class="info-grid">

<div class="info">

<span>Data</span>

<strong>

<?php
echo date(
    "d/m/Y H:i",
    strtotime($venda["data_venda"])
);
?>

</strong>

</div>


<div class="info">

<span>Atendente</span>

<strong>
<?php echo htmlspecialchars($venda["usuario_nome"]); ?>
</strong>

</div>


<div class="info">

<span>Pagamento</span>

<strong>

<?php

if ($venda["forma_pagamento"] === "pix") {

    echo "PIX";

} elseif ($venda["forma_pagamento"] === "dinheiro") {

    echo "Dinheiro";

} else {

    echo "Cartão";

}

?>

</strong>

</div>

</div>


<table>

<thead>

<tr>
<th>Produto</th>
<th>Qtd.</th>
<th>Preço</th>
<th>Subtotal</th>
</tr>

</thead>

<tbody>

<?php while ($item = $resultadoItens->fetch_assoc()): ?>

<tr>

<td>
<?php echo htmlspecialchars($item["nome"]); ?>
</td>

<td>
<?php echo $item["quantidade"]; ?>
</td>

<td>

R$
<?php
echo number_format(
    $item["preco_unitario"],
    2,
    ",",
    "."
);
?>

</td>

<td>

<strong>

R$
<?php
echo number_format(
    $item["subtotal"],
    2,
    ",",
    "."
);
?>

</strong>

</td>

</tr>

<?php endwhile; ?>

</tbody>

</table>


<div class="total">

<span>Total</span>

<strong>

R$
<?php
echo number_format(
    $venda["total"],
    2,
    ",",
    "."
);
?>

</strong>

</div>


<?php if ($venda["status"] === "finalizada"): ?>

<form
method="POST"
action="cancelar_venda.php"
onsubmit="return confirm('Tem certeza que deseja cancelar esta venda? O estoque será devolvido.');"
>
<input type="hidden" name="id" value="<?php echo $venda["id"]; ?>">
<input
type="hidden"
name="csrf_token"
value="<?php echo htmlspecialchars($_SESSION["csrf_token"], ENT_QUOTES, "UTF-8"); ?>"
>
<button type="submit" class="btn-cancelar">
Cancelar Venda
</button>
</form>

<?php elseif ($venda["status"] === "finalizada"): ?>

<p style="margin-top:25px;color:#777;font-size:13px;text-align:center;">
Somente administradores podem cancelar vendas.
</p>

<?php endif; ?>


</div>

</div>

</body>

</html>
