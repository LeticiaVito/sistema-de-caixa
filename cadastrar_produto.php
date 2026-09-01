<?php

session_start();

if (!isset($_SESSION["usuario_id"])) {
    header("Location: index.php");
    exit;
}

require_once "config/conexao.php";

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

        $sql = "INSERT INTO produtos
        (
            nome,
            categoria,
            preco_custo,
            preco_venda,
            estoque,
            estoque_minimo,
            validade,
            codigo
        )
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $conn->prepare($sql);

        $stmt->bind_param(
            "ssddiiss",
            $nome,
            $categoria,
            $precoCusto,
            $precoVenda,
            $estoque,
            $estoqueMinimo,
            $validade,
            $codigo
        );

        if ($stmt->execute()) {

            $mensagem = "Produto cadastrado com sucesso!";

        } else {

            $erro = "Erro ao cadastrar produto.";

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


<form method="POST">

<div class="grid">

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

</body>

</html>