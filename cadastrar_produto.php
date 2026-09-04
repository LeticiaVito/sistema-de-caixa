<?php

session_start();

if (!isset($_SESSION["usuario_id"])) {
    header("Location: index.php");
    exit;
}

require_once "config/conexao.php";
require_once "config/foto_produto.php";

$mensagem = "";
$erro = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $nome = trim($_POST["nome"] ?? "");
    $categoria = trim($_POST["categoria"] ?? "");

    $precoCusto = $_POST["preco_custo"] ?? 0;
    $precoVenda = $_POST["preco_venda"] ?? 0;

    $estoque = $_POST["estoque"] ?? 0;
    $estoqueMinimo = $_POST["estoque_minimo"] ?? 5;

    $validade = $_POST["validade"] ?? null;
    $codigo = trim($_POST["codigo"] ?? "");

    if ($validade === "") {
        $validade = null;
    }

    if ($codigo === "") {
        $codigo = null;
    }

    if ($nome === "" || $categoria === "") {

        $erro = "Preencha os campos obrigatórios.";

    } else {

        $foto = null;

        try {
            $foto = salvarFotoProduto($_FILES["foto"] ?? []);

        $sql = "INSERT INTO produtos
        (
            nome,
            categoria,
            preco_custo,
            preco_venda,
            estoque,
            estoque_minimo,
            validade,
            codigo,
            foto
        )
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $conn->prepare($sql);

        if (!$stmt) {
            excluirFotoProduto($foto);
            throw new RuntimeException("Não foi possível preparar o cadastro.");
        }

        $stmt->bind_param(
            "ssddiisss",
            $nome,
            $categoria,
            $precoCusto,
            $precoVenda,
            $estoque,
            $estoqueMinimo,
            $validade,
            $codigo,
            $foto
        );

        if ($stmt->execute()) {

            $mensagem = "Produto cadastrado com sucesso!";

        } else {

            excluirFotoProduto($foto);
            $erro = "Erro ao cadastrar produto.";

        }

        } catch (RuntimeException $e) {
            excluirFotoProduto($foto);
            $erro = $e->getMessage();
        }

    }

}

?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>

<meta charset="UTF-8">

<title>Cadastrar Produto | Cantina do Tio Fabinho</title>

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
    max-width:850px;
    margin:40px auto;
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
    margin-bottom:7px;
}

.sub{
    color:#777;
    margin-bottom:30px;
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
}

input:focus,
select:focus{
    border-color:#222;
    background:white;
}

.foto-area{
    display:flex;
    align-items:center;
    gap:18px;
    padding:16px;
    border:1px dashed #ccc;
    border-radius:12px;
    background:#fafafa;
}

.foto-preview{
    width:110px;
    height:110px;
    object-fit:cover;
    border-radius:12px;
    background:#eee;
    display:none;
}

.foto-controles{flex:1}.foto-ajuda{display:block;margin-top:7px;color:#777;font-size:11px}

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
}

.btn:hover{
    background:#333;
}

.sucesso{
    background:#e8f7ec;
    color:#247a3d;
    padding:12px;
    border-radius:10px;
    margin-bottom:20px;
}

.erro{
    background:#ffeaea;
    color:#b42318;
    padding:12px;
    border-radius:10px;
    margin-bottom:20px;
}

@media(max-width:700px){

    .grid{
        grid-template-columns:1fr;
    }

}

</style>

</head>

<body>

<div class="container">

<a class="voltar" href="produtos.php">
← Voltar para produtos
</a>

<div class="card">

<h1>Novo Produto</h1>

<p class="sub">
Cadastre um novo item no estoque da cantina.
</p>

<?php if ($mensagem): ?>

<div class="sucesso">
<?php echo $mensagem; ?>
</div>

<?php endif; ?>

<?php if ($erro): ?>

<div class="erro">
<?php echo $erro; ?>
</div>

<?php endif; ?>


<form method="POST" enctype="multipart/form-data">

<div class="grid">

<div class="campo full">
<label for="foto">Foto do produto</label>
<div class="foto-area">
<img id="fotoPreview" class="foto-preview" alt="Pré-visualização da foto">
<div class="foto-controles">
<input id="foto" type="file" name="foto" accept="image/jpeg,image/png,image/webp">
<span class="foto-ajuda">JPG, PNG ou WebP, com no máximo 2 MB.</span>
</div>
</div>
</div>

<div class="campo full">

<label>Nome do produto *</label>

<input
type="text"
name="nome"
placeholder="Ex: Coca-Cola 350ml"
required
>

</div>


<div class="campo">

<label>Categoria *</label>

<select name="categoria" required>

<option value="">
Selecione
</option>

<option value="Bebidas">
Bebidas
</option>

<option value="Salgados">
Salgados
</option>

<option value="Doces">
Doces
</option>

<option value="Lanches">
Lanches
</option>

<option value="Outros">
Outros
</option>

</select>

</div>


<div class="campo">

<label>Código do produto</label>

<input
type="text"
name="codigo"
placeholder="Ex: BEB001"
>

</div>


<div class="campo">

<label>Preço de custo</label>

<input
type="number"
name="preco_custo"
step="0.01"
min="0"
placeholder="0,00"
>

</div>


<div class="campo">

<label>Preço de venda *</label>

<input
type="number"
name="preco_venda"
step="0.01"
min="0"
required
>

</div>


<div class="campo">

<label>Quantidade em estoque</label>

<input
type="number"
name="estoque"
min="0"
value="0"
>

</div>


<div class="campo">

<label>Estoque mínimo</label>

<input
type="number"
name="estoque_minimo"
min="0"
value="5"
>

</div>


<div class="campo full">

<label>Validade</label>

<input
type="date"
name="validade"
>

</div>

</div>

<button class="btn" type="submit">
Cadastrar Produto
</button>

</form>

</div>

</div>

<script>
document.getElementById("foto").addEventListener("change", function () {
    const preview = document.getElementById("fotoPreview");
    const arquivo = this.files[0];

    if (!arquivo) {
        preview.removeAttribute("src");
        preview.style.display = "none";
        return;
    }

    preview.src = URL.createObjectURL(arquivo);
    preview.style.display = "block";
});
</script>

</body>

</html>
