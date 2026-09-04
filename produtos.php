<?php

session_start();

if (!isset($_SESSION["usuario_id"])) {
    header("Location: index.php");
    exit;
}

require_once "config/conexao.php";

$sql = "SELECT * FROM produtos ORDER BY ativo DESC, id DESC";
$resultado = $conn->query($sql);

?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Produtos | Cantina do Tio Fabinho</title>

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

.topo{
    display:flex;
    justify-content:space-between;
    align-items:center;
    margin-bottom:25px;
}

.topo h1{
    font-size:28px;
}

.topo p{
    color:#777;
    margin-top:5px;
}

.btn{
    background:#1b1b1b;
    color:white;
    text-decoration:none;
    padding:13px 18px;
    border-radius:10px;
    font-size:14px;
}

.btn:hover{
    background:#333;
}

.tabela-container{
    background:white;
    border-radius:16px;
    padding:20px;
    box-shadow:0 5px 20px rgba(0,0,0,0.06);
    overflow-x:auto;
    max-width:100%;
}

table{
    width:100%;
    border-collapse:collapse;
    min-width:980px;
}

th{
    text-align:left;
    padding:14px;
    color:#777;
    font-size:13px;
    border-bottom:1px solid #eee;
}

td{
    padding:14px;
    border-bottom:1px solid #eee;
    font-size:14px;
}

.badge{
    display:inline-block;
    padding:6px 10px;
    border-radius:20px;
    font-size:12px;
}

.ok{
    background:#e8f7ec;
    color:#247a3d;
}

.baixo{
    background:#fff3d6;
    color:#9a6700;
}

.zero{
    background:#ffe5e5;
    color:#b42318;
}

.inativo{
    background:#eeeeee;
    color:#666666;
}

.vazio{
    text-align:center;
    padding:50px;
    color:#999;
}

.voltar{
    display:inline-block;
    margin-bottom:20px;
    text-decoration:none;
    color:#555;
    font-size:14px;
}

.acoes{
    display:flex;
    gap:8px;
    align-items:center;
}

.btn-editar{
    text-decoration:none;
    background:#1b1b1b;
    color:white;
    padding:8px 12px;
    border-radius:8px;
    font-size:12px;
}

.btn-editar:hover{
    background:#333;
}

.btn-desativar{
    text-decoration:none;
    background:#ffe5e5;
    color:#b42318;
    padding:8px 12px;
    border-radius:8px;
    font-size:12px;
}

.btn-desativar:hover{
    background:#ffd5d5;
}

.linha-inativa{
    opacity:0.6;
}

.mensagem{
    background:#e8f7ec;
    color:#247a3d;
    padding:12px 14px;
    border-radius:10px;
    margin-bottom:20px;
    font-size:13px;
}

.foto-produto{
    width:54px;
    height:54px;
    border-radius:11px;
    object-fit:cover;
    display:block;
    background:#eee;
}

.foto-vazia{
    width:54px;
    height:54px;
    border-radius:11px;
    display:flex;
    align-items:center;
    justify-content:center;
    background:#f0f0f0;
    font-size:23px;
}

@media(max-width:600px){
    .container{
        padding:16px;
    }

    .topo{
        flex-direction:column;
        align-items:flex-start;
        gap:15px;
    }

    .tabela-container{
        padding:12px;
    }
}

</style>

</head>

<body>

<div class="container">

<a class="voltar" href="dashboard.php">
← Voltar ao dashboard
</a>

<div class="topo">

<div>
<h1>Produtos</h1>
<p>Gerencie os produtos da Cantina do Tio Fabinho.</p>
</div>

<a class="btn" href="cadastrar_produto.php">
+ Novo Produto
</a>

</div>

<?php if (isset($_GET["desativado"])): ?>

<div class="mensagem">
Produto desativado com sucesso.
</div>

<?php endif; ?>

<div class="tabela-container">

<?php if ($resultado && $resultado->num_rows > 0): ?>

<table>

<thead>

<tr>
<th>Foto</th>
<th>Produto</th>
<th>Categoria</th>
<th>Preço</th>
<th>Estoque</th>
<th>Status</th>
<th>Situação</th>
<th>Ações</th>
</tr>

</thead>

<tbody>

<?php while ($produto = $resultado->fetch_assoc()): ?>

<?php

$estoque = (int)$produto["estoque"];
$minimo = (int)$produto["estoque_minimo"];
$ativo = (int)$produto["ativo"];

if ($ativo === 0) {

    $classeEstoque = "inativo";
    $statusEstoque = "Inativo";

} elseif ($estoque <= 0) {

    $classeEstoque = "zero";
    $statusEstoque = "Sem estoque";

} elseif ($estoque <= $minimo) {

    $classeEstoque = "baixo";
    $statusEstoque = "Estoque baixo";

} else {

    $classeEstoque = "ok";
    $statusEstoque = "Disponível";

}

?>

<tr class="<?php echo $ativo === 0 ? 'linha-inativa' : ''; ?>">

<td>
<?php if (!empty($produto["foto"])): ?>
<img
class="foto-produto"
src="<?php echo htmlspecialchars($produto["foto"]); ?>"
alt="Foto de <?php echo htmlspecialchars($produto["nome"]); ?>"
>
<?php else: ?>
<div class="foto-vazia" title="Produto sem foto">📦</div>
<?php endif; ?>
</td>

<td>
<strong>
<?php echo htmlspecialchars($produto["nome"]); ?>
</strong>
</td>

<td>
<?php echo htmlspecialchars($produto["categoria"]); ?>
</td>

<td>
R$
<?php
echo number_format(
    $produto["preco_venda"],
    2,
    ",",
    "."
);
?>
</td>

<td>
<?php echo $produto["estoque"]; ?>
</td>

<td>

<span class="badge <?php echo $classeEstoque; ?>">
<?php echo $statusEstoque; ?>
</span>

</td>

<td>

<?php if ($ativo === 1): ?>

<span class="badge ok">
Ativo
</span>

<?php else: ?>

<span class="badge inativo">
Inativo
</span>

<?php endif; ?>

</td>

<td>

<div class="acoes">

<a
href="editar_produto.php?id=<?php echo $produto['id']; ?>"
class="btn-editar"
>
Editar
</a>

<?php if ($ativo === 1): ?>

<a
href="desativar_produto.php?id=<?php echo $produto['id']; ?>"
class="btn-desativar"
onclick="return confirm('Deseja realmente desativar este produto?');"
>
Desativar
</a>

<?php endif; ?>

</div>

</td>

</tr>

<?php endwhile; ?>

</tbody>

</table>

<?php else: ?>

<div class="vazio">

<h3>Nenhum produto cadastrado</h3>

<p>
Clique em “Novo Produto” para começar.
</p>

</div>

<?php endif; ?>

</div>

</div>

</body>

</html>
