<?php

session_start();

if (!isset($_SESSION["usuario_id"])) {
    header("Location: index.php");
    exit;
}

require_once "config/conexao.php";

if (empty($_SESSION["csrf_token_produtos"])) {
    $_SESSION["csrf_token_produtos"] = bin2hex(random_bytes(32));
}

$mostrarArquivados = isset($_GET["arquivados"]);
$statusListado = $mostrarArquivados ? 0 : 1;
$stmtProdutos = $conn->prepare("SELECT * FROM produtos WHERE ativo = ? ORDER BY id DESC");
$stmtProdutos->bind_param("i", $statusListado);
$stmtProdutos->execute();
$resultado = $stmtProdutos->get_result();

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

.acoes form{margin:0}
.acoes-topo{display:flex;gap:10px;align-items:center}
.btn-secundario{background:white;color:#222;border:1px solid #ddd}
.btn-restaurar{border:none;background:#e8f7ec;color:#247a3d;padding:8px 12px;border-radius:8px;font-size:12px;cursor:pointer}
.btn-excluir{border:none;background:#b42318;color:white;padding:8px 12px;border-radius:8px;font-size:12px;cursor:pointer}
.btn-excluir:hover{background:#8f1c14}
.mensagem.erro{background:#ffe5e5;color:#b42318}

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

<div class="acoes-topo">
<a class="btn btn-secundario" href="produtos.php<?php echo $mostrarArquivados ? '' : '?arquivados=1'; ?>"><?php echo $mostrarArquivados ? 'Ver produtos ativos' : 'Mostrar arquivados'; ?></a>
<a class="btn" href="cadastrar_produto.php">+ Novo Produto</a>
</div>

</div>

<?php if (isset($_GET["arquivado"])): ?>

<div class="mensagem">
Produto arquivado com sucesso.
</div>

<?php endif; ?>

<?php if (isset($_GET["restaurado"])): ?>
<div class="mensagem">Produto restaurado com sucesso.</div>
<?php endif; ?>

<?php if (isset($_GET["excluido"])): ?>
<div class="mensagem">Produto excluído permanentemente.</div>
<?php endif; ?>

<?php if (($_GET["erro"] ?? "") === "historico"): ?>
<div class="mensagem erro">Esse produto possui vendas registradas e não pode ser excluído. Arquive-o para preservar o histórico.</div>
<?php elseif (isset($_GET["erro"])): ?>
<div class="mensagem erro">Não foi possível excluir o produto.</div>
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

<form method="POST" action="desativar_produto.php" onsubmit="return confirm('<?php echo $ativo ? 'Arquivar este produto?' : 'Restaurar este produto?'; ?>');">
<input type="hidden" name="id" value="<?php echo (int)$produto["id"]; ?>">
<input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION["csrf_token_produtos"], ENT_QUOTES, "UTF-8"); ?>">
<input type="hidden" name="acao" value="<?php echo $ativo ? 'arquivar' : 'restaurar'; ?>">
<button type="submit" class="<?php echo $ativo ? 'btn-desativar' : 'btn-restaurar'; ?>"><?php echo $ativo ? 'Arquivar' : 'Restaurar'; ?></button>
</form>

<?php if (($_SESSION["usuario_tipo"] ?? "") === "admin"): ?>
<form method="POST" action="excluir_produto.php" onsubmit="return confirm('Excluir este produto permanentemente? Essa ação não poderá ser desfeita.');">
<input type="hidden" name="id" value="<?php echo (int)$produto["id"]; ?>">
<input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION["csrf_token_produtos"], ENT_QUOTES, "UTF-8"); ?>">
<button type="submit" class="btn-excluir">Excluir</button>
</form>
<?php endif; ?>

</div>

</td>

</tr>

<?php endwhile; ?>

</tbody>

</table>

<?php else: ?>

<div class="vazio">

<h3><?php echo $mostrarArquivados ? 'Nenhum produto arquivado' : 'Nenhum produto ativo'; ?></h3>

<p>
<?php echo $mostrarArquivados ? 'Os produtos arquivados aparecerão aqui.' : 'Clique em “Novo Produto” para começar.'; ?>
</p>

</div>

<?php endif; ?>

</div>

</div>

</body>

</html>
