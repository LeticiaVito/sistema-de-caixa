<?php

session_start();

if (!isset($_SESSION["usuario_id"])) {
    header("Location: index.php");
    exit;
}

require_once "config/conexao.php";
require_once "config/foto_produto.php";

$id = isset($_GET["id"]) ? (int)$_GET["id"] : 0;

if ($id <= 0) {
    header("Location: produtos.php");
    exit;
}

/* Buscar produto */
$sql = "SELECT * FROM produtos WHERE id = ? LIMIT 1";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();

$resultado = $stmt->get_result();

if ($resultado->num_rows !== 1) {
    header("Location: produtos.php");
    exit;
}

$produto = $resultado->fetch_assoc();

$mensagem = "";
$erro = "";


/* Atualizar produto */
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $nome = trim($_POST["nome"] ?? "");
    $categoria = trim($_POST["categoria"] ?? "");

    $precoCusto = (float)($_POST["preco_custo"] ?? 0);
    $precoVenda = (float)($_POST["preco_venda"] ?? 0);

    $estoque = (int)($_POST["estoque"] ?? 0);
    $estoqueMinimo = (int)($_POST["estoque_minimo"] ?? 5);

    $validade = $_POST["validade"] ?? null;

    $codigo = trim($_POST["codigo"] ?? "");

    $ativo = isset($_POST["ativo"])
        ? (int)$_POST["ativo"]
        : 1;


    if ($validade === "") {
        $validade = null;
    }

    if ($codigo === "") {
        $codigo = null;
    }


    if ($nome === "" || $categoria === "") {

        $erro = "Preencha os campos obrigatórios.";

    } elseif ($precoVenda < 0) {

        $erro = "O preço de venda não pode ser negativo.";

    } elseif ($estoque < 0) {

        $erro = "O estoque não pode ser negativo.";

    } else {

        $fotoAnterior = $produto["foto"] ?? null;
        $fotoNova = null;

        try {
            $fotoNova = salvarFotoProduto($_FILES["foto"] ?? []);
            $removerFoto = isset($_POST["remover_foto"]);
            $fotoFinal = $fotoNova ?? ($removerFoto ? null : $fotoAnterior);

        $sqlUpdate = "UPDATE produtos
                      SET nome = ?,
                          categoria = ?,
                          preco_custo = ?,
                          preco_venda = ?,
                          estoque = ?,
                          estoque_minimo = ?,
                          validade = ?,
                          codigo = ?,
                          foto = ?,
                          ativo = ?
                      WHERE id = ?";

        $stmtUpdate = $conn->prepare($sqlUpdate);

        if (!$stmtUpdate) {

            excluirFotoProduto($fotoNova);
            $erro = "Erro ao preparar atualização.";

        } else {

            $stmtUpdate->bind_param(
                "ssddiisssii",
                $nome,
                $categoria,
                $precoCusto,
                $precoVenda,
                $estoque,
                $estoqueMinimo,
                $validade,
                $codigo,
                $fotoFinal,
                $ativo,
                $id
            );


            if ($stmtUpdate->execute()) {

                if ($fotoAnterior !== $fotoFinal) {
                    excluirFotoProduto($fotoAnterior);
                }

                $mensagem = "Produto atualizado com sucesso!";

                $produto["nome"] = $nome;
                $produto["categoria"] = $categoria;
                $produto["preco_custo"] = $precoCusto;
                $produto["preco_venda"] = $precoVenda;
                $produto["estoque"] = $estoque;
                $produto["estoque_minimo"] = $estoqueMinimo;
                $produto["validade"] = $validade;
                $produto["codigo"] = $codigo;
                $produto["foto"] = $fotoFinal;
                $produto["ativo"] = $ativo;

            } else {

                excluirFotoProduto($fotoNova);

                if ($stmtUpdate->errno == 1062) {

                    $erro = "Já existe outro produto com esse código.";

                } else {

                    $erro = "Erro ao atualizar produto.";

                }

            }

        }

        } catch (RuntimeException $e) {
            excluirFotoProduto($fotoNova);
            $erro = $e->getMessage();
        }

    }

}

?>

<!DOCTYPE html>

<html lang="pt-BR">

<head>

<meta charset="UTF-8">

<meta
name="viewport"
content="width=device-width, initial-scale=1.0"
>

<title>
Editar Produto | Cantina do Tio Fabinho
</title>


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
    max-width:850px;
    margin:40px auto;
    padding:20px;
}

.voltar{
    display:inline-block;
    margin-bottom:20px;
    color:#555;
    text-decoration:none;
    font-size:14px;
}

.voltar:hover{
    color:#111;
}

.card{
    background:white;
    padding:30px;
    border-radius:18px;
    box-shadow:0 5px 20px rgba(0,0,0,.06);
}

h1{
    margin-bottom:7px;
    font-size:28px;
}

.sub{
    color:#777;
    margin-bottom:30px;
    font-size:14px;
}

.grid{
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:18px;
}

.campo{
    margin-bottom:18px;
}

.campo.full{
    grid-column:1 / -1;
}

label{
    display:block;
    font-size:13px;
    font-weight:bold;
    margin-bottom:7px;
    color:#444;
}

input,
select{
    width:100%;
    padding:13px;
    border:1px solid #ddd;
    border-radius:10px;
    outline:none;
    background:#fafafa;
    font-size:14px;
    transition:.2s;
}

input:focus,
select:focus{
    border-color:#222;
    background:white;
    box-shadow:0 0 0 3px rgba(0,0,0,.04);
}

.foto-area{display:flex;align-items:center;gap:18px;padding:16px;border:1px dashed #ccc;border-radius:12px;background:#fafafa}
.foto-preview{width:120px;height:120px;object-fit:cover;border-radius:12px;background:#eee}
.foto-controles{flex:1}.foto-ajuda{display:block;margin-top:7px;color:#777;font-size:11px}
.remover-foto{display:flex;align-items:center;gap:8px;margin-top:12px;font-size:12px;font-weight:normal}
.remover-foto input{width:auto}

.btn{
    width:100%;
    padding:14px;
    border:none;
    border-radius:10px;
    background:#1b1b1b;
    color:white;
    font-weight:bold;
    cursor:pointer;
    margin-top:10px;
    font-size:14px;
    transition:.2s;
}

.btn:hover{
    background:#333;
    transform:translateY(-1px);
}

.sucesso{
    background:#e8f7ec;
    color:#247a3d;
    padding:12px 14px;
    border-radius:10px;
    margin-bottom:20px;
    font-size:13px;
}

.erro{
    background:#ffeaea;
    color:#b42318;
    padding:12px 14px;
    border-radius:10px;
    margin-bottom:20px;
    font-size:13px;
}

.status-info{
    padding:14px;
    border-radius:10px;
    background:#f6f6f6;
    margin-bottom:18px;
    font-size:13px;
    color:#666;
}

@media(max-width:700px){

    .grid{
        grid-template-columns:1fr;
    }

    .campo.full{
        grid-column:auto;
    }

    .card{
        padding:22px;
    }

}

</style>

</head>


<body>

<div class="container">

<a
class="voltar"
href="produtos.php"
>
← Voltar para produtos
</a>


<div class="card">


<h1>
Editar Produto
</h1>

<p class="sub">
Altere as informações do produto selecionado.
</p>


<?php if ($mensagem): ?>

<div class="sucesso">

<?php echo htmlspecialchars($mensagem); ?>

</div>

<?php endif; ?>


<?php if ($erro): ?>

<div class="erro">

<?php echo htmlspecialchars($erro); ?>

</div>

<?php endif; ?>


<form method="POST" enctype="multipart/form-data">


<div class="grid">

<div class="campo full">
<label for="foto">Foto do produto</label>
<div class="foto-area">
<img
id="fotoPreview"
class="foto-preview"
src="<?php echo htmlspecialchars($produto["foto"] ?: "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='120' height='120'%3E%3Crect width='100%25' height='100%25' fill='%23eeeeee'/%3E%3Ctext x='50%25' y='52%25' text-anchor='middle' fill='%23888' font-size='12'%3ESem foto%3C/text%3E%3C/svg%3E"); ?>"
alt="Foto do produto"
>
<div class="foto-controles">
<input id="foto" type="file" name="foto" accept="image/jpeg,image/png,image/webp">
<span class="foto-ajuda">JPG, PNG ou WebP, com no máximo 2 MB.</span>
<?php if (!empty($produto["foto"])): ?>
<label class="remover-foto"><input type="checkbox" name="remover_foto" value="1"> Remover foto atual</label>
<?php endif; ?>
</div>
</div>
</div>


<div class="campo full">

<label>
Nome do produto *
</label>

<input
type="text"
name="nome"
value="<?php echo htmlspecialchars($produto["nome"]); ?>"
required
>

</div>


<div class="campo">

<label>
Categoria *
</label>

<select
name="categoria"
required
>

<option
value="Bebidas"
<?php echo $produto["categoria"] === "Bebidas" ? "selected" : ""; ?>
>
Bebidas
</option>

<option
value="Salgados"
<?php echo $produto["categoria"] === "Salgados" ? "selected" : ""; ?>
>
Salgados
</option>

<option
value="Doces"
<?php echo $produto["categoria"] === "Doces" ? "selected" : ""; ?>
>
Doces
</option>

<option
value="Lanches"
<?php echo $produto["categoria"] === "Lanches" ? "selected" : ""; ?>
>
Lanches
</option>

<option
value="Outros"
<?php echo $produto["categoria"] === "Outros" ? "selected" : ""; ?>
>
Outros
</option>

</select>

</div>


<div class="campo">

<label>
Código do produto
</label>

<input
type="text"
name="codigo"
value="<?php echo htmlspecialchars($produto["codigo"] ?? ""); ?>"
placeholder="Ex: BEB001"
>

</div>


<div class="campo">

<label>
Preço de custo
</label>

<input
type="number"
name="preco_custo"
step="0.01"
min="0"
value="<?php echo htmlspecialchars($produto["preco_custo"]); ?>"
>

</div>


<div class="campo">

<label>
Preço de venda *
</label>

<input
type="number"
name="preco_venda"
step="0.01"
min="0"
value="<?php echo htmlspecialchars($produto["preco_venda"]); ?>"
required
>

</div>


<div class="campo">

<label>
Quantidade em estoque
</label>

<input
type="number"
name="estoque"
min="0"
value="<?php echo htmlspecialchars($produto["estoque"]); ?>"
>

</div>


<div class="campo">

<label>
Estoque mínimo
</label>

<input
type="number"
name="estoque_minimo"
min="0"
value="<?php echo htmlspecialchars($produto["estoque_minimo"]); ?>"
>

</div>


<div class="campo full">

<label>
Validade
</label>

<input
type="date"
name="validade"
value="<?php echo htmlspecialchars($produto["validade"] ?? ""); ?>"
>

</div>


<div class="campo full">

<label>
Situação do produto
</label>

<select name="ativo">

<option
value="1"
<?php echo (int)$produto["ativo"] === 1 ? "selected" : ""; ?>
>
Ativo
</option>

<option
value="0"
<?php echo (int)$produto["ativo"] === 0 ? "selected" : ""; ?>
>
Inativo
</option>

</select>

</div>


</div>


<div class="status-info">

<?php if ((int)$produto["ativo"] === 1): ?>

Este produto está atualmente
<strong>ativo</strong>
e poderá aparecer nas vendas.

<?php else: ?>

Este produto está atualmente
<strong>inativo</strong>.
Altere para “Ativo” para disponibilizá-lo novamente.

<?php endif; ?>

</div>


<button
class="btn"
type="submit"
>
Salvar Alterações
</button>


</form>


</div>

</div>

<script>
document.getElementById("foto").addEventListener("change", function () {
    if (this.files[0]) {
        document.getElementById("fotoPreview").src = URL.createObjectURL(this.files[0]);
    }
});
</script>

</body>

</html>
