<?php

session_start();

if (!isset($_SESSION["usuario_id"])) {
    header("Location: index.php");
    exit;
}

require_once "config/conexao.php";

$sql = "
    SELECT
        v.id,
        v.total,
        v.forma_pagamento,
        v.status,
        v.data_venda,
        u.nome AS usuario_nome
    FROM vendas v
    INNER JOIN usuarios u
        ON u.id = v.usuario_id
    ORDER BY v.data_venda DESC
";

$resultado = $conn->query($sql);

?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Vendas | Cantina do Tio Fabinho</title>

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
    padding:30px;
}

.voltar{
    display:inline-block;
    margin-bottom:20px;
    text-decoration:none;
    color:#555;
    font-size:14px;
}

.topo{
    margin-bottom:25px;
}

.topo h1{
    font-size:28px;
    margin-bottom:5px;
}

.topo p{
    color:#777;
    font-size:14px;
}

.tabela-container{
    background:white;
    border-radius:16px;
    padding:20px;
    box-shadow:0 5px 20px rgba(0,0,0,.06);
    overflow-x:auto;
    max-width:100%;
}

table{
    width:100%;
    border-collapse:collapse;
    min-width:850px;
}

th{
    text-align:left;
    padding:14px;
    font-size:13px;
    color:#777;
    border-bottom:1px solid #eee;
}

td{
    padding:14px;
    font-size:14px;
    border-bottom:1px solid #eee;
}

.badge{
    display:inline-block;
    padding:6px 10px;
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

.pagamento{
    background:#f0f0f0;
    color:#444;
}

.btn{
    text-decoration:none;
    background:#1b1b1b;
    color:white;
    padding:8px 12px;
    border-radius:8px;
    font-size:12px;
}

.btn:hover{
    background:#333;
}

.vazio{
    text-align:center;
    padding:50px;
    color:#999;
}

.mensagem{
    margin-bottom:20px;
    padding:13px;
    border-radius:10px;
    background:#e8f7ec;
    color:#247a3d;
    font-size:13px;
}

@media(max-width:600px){
    .container{
        padding:16px;
    }

    .tabela-container{
        padding:12px;
    }
}

</style>

</head>

<body>

<div class="container">

<a href="dashboard.php" class="voltar">
← Voltar ao dashboard
</a>

<div class="topo">

<h1>Histórico de Vendas</h1>

<p>
Acompanhe todas as vendas realizadas na cantina.
</p>

</div>

<?php if (isset($_GET["cancelada"])): ?>

<div class="mensagem">
Venda cancelada e estoque devolvido com sucesso.
</div>

<?php endif; ?>

<?php if (($_GET["erro"] ?? "") === "cancelamento"): ?>

<div class="mensagem" style="background:#ffe5e5;color:#b42318;">
Não foi possível cancelar a venda. Atualize a página e tente novamente.
</div>

<?php endif; ?>

<?php if (($_GET["erro"] ?? "") === "permissao"): ?>

<div class="mensagem" style="background:#fff3d6;color:#9a6700;">
Somente administradores podem cancelar vendas.
</div>

<?php endif; ?>

<div class="tabela-container">

<?php if ($resultado && $resultado->num_rows > 0): ?>

<table>

<thead>

<tr>
<th>#</th>
<th>Data</th>
<th>Atendente</th>
<th>Pagamento</th>
<th>Total</th>
<th>Status</th>
<th>Ação</th>
</tr>

</thead>

<tbody>

<?php while ($venda = $resultado->fetch_assoc()): ?>

<tr>

<td>
#<?php echo $venda["id"]; ?>
</td>

<td>
<?php
echo date(
    "d/m/Y H:i",
    strtotime($venda["data_venda"])
);
?>
</td>

<td>
<?php echo htmlspecialchars($venda["usuario_nome"]); ?>
</td>

<td>

<span class="badge pagamento">

<?php

$forma = $venda["forma_pagamento"];

if ($forma === "pix") {
    echo "PIX";
} elseif ($forma === "dinheiro") {
    echo "Dinheiro";
} else {
    echo "Cartão";
}

?>

</span>

</td>

<td>

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

</td>

<td>

<?php if ($venda["status"] === "finalizada"): ?>

<span class="badge finalizada">
Finalizada
</span>

<?php else: ?>

<span class="badge cancelada">
Cancelada
</span>

<?php endif; ?>

</td>

<td>

<a
href="detalhes_venda.php?id=<?php echo $venda["id"]; ?>"
class="btn"
>
Ver detalhes
</a>

</td>

</tr>

<?php endwhile; ?>

</tbody>

</table>

<?php else: ?>

<div class="vazio">

<h3>Nenhuma venda registrada</h3>

<p>
As vendas realizadas aparecerão aqui.
</p>

</div>

<?php endif; ?>

</div>

</div>

</body>

</html>
