<?php

session_start();

if (!isset($_SESSION["usuario_id"])) {
    header("Location: index.php");
    exit;
}

require_once "config/conexao.php";

$sql = "
    SELECT
        id,
        nome,
        categoria,
        preco_venda,
        estoque
    FROM produtos
    WHERE ativo = 1
    AND estoque > 0
    ORDER BY nome ASC
";

$resultado = $conn->query($sql);

$produtos = [];

if ($resultado) {

    while ($produto = $resultado->fetch_assoc()) {

        $produtos[] = $produto;

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
Nova Venda | Cantina do Tio Fabinho
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
    max-width:1200px;
    margin:0 auto;
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

.grid{
    display:grid;
    grid-template-columns:1.5fr 1fr;
    gap:20px;
}

.card{
    background:white;
    border-radius:16px;
    padding:22px;
    box-shadow:0 5px 20px rgba(0,0,0,.06);
}

.card h2{
    font-size:18px;
    margin-bottom:20px;
}

.busca{
    margin-bottom:18px;
}

.busca input{
    width:100%;
    padding:13px;
    border:1px solid #ddd;
    border-radius:10px;
    outline:none;
}

.produtos{
    display:grid;
    grid-template-columns:repeat(2,1fr);
    gap:12px;
    max-height:620px;
    overflow-y:auto;
}

.produto{
    border:1px solid #eee;
    border-radius:12px;
    padding:15px;
    cursor:pointer;
    transition:.2s;
}

.produto:hover{
    border-color:#222;
    transform:translateY(-1px);
}

.produto h3{
    font-size:14px;
    margin-bottom:6px;
}

.produto .categoria{
    font-size:11px;
    color:#999;
    margin-bottom:10px;
}

.produto .preco{
    font-size:17px;
    font-weight:bold;
}

.produto .estoque{
    font-size:11px;
    color:#777;
    margin-top:5px;
}

.carrinho-vazio{
    text-align:center;
    padding:40px 10px;
    color:#999;
    font-size:13px;
}

.item-carrinho{
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:10px;
    border-bottom:1px solid #eee;
    padding:13px 0;
}

.item-info{
    flex:1;
}

.item-info strong{
    display:block;
    font-size:13px;
    margin-bottom:5px;
}

.item-info span{
    font-size:12px;
    color:#777;
}

.quantidade{
    display:flex;
    align-items:center;
    gap:6px;
}

.quantidade button{
    width:29px;
    height:29px;
    border:none;
    background:#eee;
    border-radius:7px;
    cursor:pointer;
}

.quantidade span{
    min-width:20px;
    text-align:center;
    font-size:13px;
}

.remover{
    border:none;
    background:#ffeaea;
    color:#b42318;
    padding:7px 9px;
    border-radius:7px;
    cursor:pointer;
}

.total{
    border-top:1px solid #eee;
    margin-top:20px;
    padding-top:20px;
    display:flex;
    justify-content:space-between;
    align-items:center;
}

.total span{
    font-size:14px;
    color:#777;
}

.total strong{
    font-size:27px;
}

.pagamento{
    margin-top:22px;
}

.pagamento label{
    display:block;
    font-size:13px;
    font-weight:bold;
    margin-bottom:8px;
}

.pagamento select{
    width:100%;
    padding:13px;
    border:1px solid #ddd;
    border-radius:10px;
    background:white;
}

.btn-finalizar{
    width:100%;
    border:none;
    background:#1b1b1b;
    color:white;
    padding:15px;
    border-radius:10px;
    font-weight:bold;
    cursor:pointer;
    margin-top:20px;
}

.btn-finalizar:hover{
    background:#333;
}

.btn-finalizar:disabled{
    background:#aaa;
    cursor:not-allowed;
}

.mensagem{
    padding:13px;
    border-radius:10px;
    margin-bottom:20px;
    font-size:13px;
}

.sucesso{
    background:#e8f7ec;
    color:#247a3d;
}

.erro{
    background:#ffeaea;
    color:#b42318;
}

@media(max-width:900px){

    .grid{
        grid-template-columns:1fr;
    }

}

@media(max-width:600px){

    .produtos{
        grid-template-columns:1fr;
    }

}

</style>

</head>

<body>

<div class="container">

<a
href="dashboard.php"
class="voltar"
>
← Voltar ao dashboard
</a>

<div class="topo">

<h1>Nova Venda</h1>

<p>
Registre uma nova venda da Cantina do Tio Fabinho.
</p>

</div>


<?php if (isset($_GET["sucesso"])): ?>

<div class="mensagem sucesso">

Venda finalizada com sucesso.

</div>

<?php endif; ?>


<?php if (isset($_GET["erro"])): ?>

<div class="mensagem erro">

Não foi possível finalizar a venda.

</div>

<?php endif; ?>


<div class="grid">


<div class="card">

<h2>Produtos</h2>

<div class="busca">

<input
type="text"
id="buscaProduto"
placeholder="Pesquisar produto..."
onkeyup="filtrarProdutos()"
>

</div>

<div class="produtos" id="listaProdutos">


<?php foreach ($produtos as $produto): ?>

<div
class="produto"
data-nome="<?php echo strtolower(htmlspecialchars($produto["nome"])); ?>"
onclick='adicionarProduto(
    <?php echo (int)$produto["id"]; ?>,
    <?php echo json_encode($produto["nome"]); ?>,
    <?php echo (float)$produto["preco_venda"]; ?>,
    <?php echo (int)$produto["estoque"]; ?>
)'
>

<h3>
<?php echo htmlspecialchars($produto["nome"]); ?>
</h3>

<div class="categoria">
<?php echo htmlspecialchars($produto["categoria"]); ?>
</div>

<div class="preco">

R$
<?php
echo number_format(
    $produto["preco_venda"],
    2,
    ",",
    "."
);
?>

</div>

<div class="estoque">

Estoque:
<?php echo (int)$produto["estoque"]; ?>
un.

</div>

</div>

<?php endforeach; ?>


</div>

</div>


<div class="card">

<h2>Carrinho</h2>

<form
action="finalizar_venda.php"
method="POST"
id="formVenda"
>

<input
type="hidden"
name="itens"
id="itensInput"
>

<div id="carrinho">

<div class="carrinho-vazio">
Nenhum produto adicionado.
</div>

</div>


<div class="total">

<span>Total</span>

<strong id="totalVenda">
R$ 0,00
</strong>

</div>


<div class="pagamento">

    <label>
        Forma de pagamento
    </label>

    <select
        name="forma_pagamento"
        id="formaPagamento"
        onchange="alterarFormaPagamento()"
        required
    >

        <option value="">
            Selecione
        </option>

        <option value="dinheiro">
            Dinheiro
        </option>

        <option value="pix">
            PIX
        </option>

        <option value="cartao">
            Cartão
        </option>

    </select>

</div>


<div
    id="areaDinheiro"
    style="display:none; margin-top:18px;"
>

    <div class="pagamento">

        <label>
            Valor recebido
        </label>

        <input
            type="number"
            id="valorRecebido"
            name="valor_recebido"
            step="0.01"
            min="0"
            placeholder="Ex: 20.00"
            oninput="calcularTroco()"
        >

    </div>


    <div
        style="
            margin-top:15px;
            background:#f7f7f7;
            padding:15px;
            border-radius:10px;
        "
    >

        <span
            style="
                display:block;
                font-size:12px;
                color:#777;
                margin-bottom:5px;
            "
        >
            Troco
        </span>

        <strong
            id="trocoVenda"
            style="font-size:22px;"
        >
            R$ 0,00
        </strong>

    </div>

</div>


<button
type="submit"
class="btn-finalizar"
id="btnFinalizar"
disabled
>
Finalizar Venda
</button>

</form>

</div>

</div>

</div>


<script>

let carrinho = [];


function adicionarProduto(id, nome, preco, estoque){

    const existente =
        carrinho.find(
            item => item.id === id
        );

    if(existente){

        if(existente.quantidade >= estoque){

            alert(
                "Não há mais unidades disponíveis deste produto."
            );

            return;

        }

        existente.quantidade++;

    }else{

        carrinho.push({

            id:id,
            nome:nome,
            preco:preco,
            quantidade:1,
            estoque:estoque

        });

    }

    atualizarCarrinho();

}


function alterarQuantidade(id, valor){

    const item =
        carrinho.find(
            produto => produto.id === id
        );

    if(!item){
        return;
    }

    const novaQuantidade =
        item.quantidade + valor;

    if(novaQuantidade <= 0){

        removerProduto(id);

        return;

    }

    if(novaQuantidade > item.estoque){

        alert(
            "Quantidade maior que o estoque disponível."
        );

        return;

    }

    item.quantidade =
        novaQuantidade;

    atualizarCarrinho();

}


function removerProduto(id){

    carrinho =
        carrinho.filter(
            item => item.id !== id
        );

    atualizarCarrinho();

}


function atualizarCarrinho(){

    const area =
        document.getElementById("carrinho");

    const totalElemento =
        document.getElementById("totalVenda");

    const itensInput =
        document.getElementById("itensInput");

    const btnFinalizar =
        document.getElementById("btnFinalizar");


    if(carrinho.length === 0){

        area.innerHTML = `
            <div class="carrinho-vazio">
                Nenhum produto adicionado.
            </div>
        `;

        totalElemento.innerText =
            "R$ 0,00";

        itensInput.value = "";

        btnFinalizar.disabled = true;

        return;

    }


    let html = "";

    let total = 0;


    carrinho.forEach(item => {

        const subtotal =
            item.preco *
            item.quantidade;

        total += subtotal;


        html += `

            <div class="item-carrinho">

                <div class="item-info">

                    <strong>
                        ${item.nome}
                    </strong>

                    <span>
                        R$ ${item.preco.toFixed(2).replace(".", ",")}
                    </span>

                </div>


                <div class="quantidade">

                    <button
                    type="button"
                    onclick="alterarQuantidade(${item.id}, -1)"
                    >
                    -
                    </button>

                    <span>
                        ${item.quantidade}
                    </span>

                    <button
                    type="button"
                    onclick="alterarQuantidade(${item.id}, 1)"
                    >
                    +
                    </button>

                </div>


                <button
                type="button"
                class="remover"
                onclick="removerProduto(${item.id})"
                >
                ×
                </button>

            </div>

        `;

    });


    area.innerHTML = html;


    totalElemento.innerText =
        total.toLocaleString(
            "pt-BR",
            {
                style:"currency",
                currency:"BRL"
            }
        );


    itensInput.value =
        JSON.stringify(carrinho);


    btnFinalizar.disabled = false;

}


function filtrarProdutos(){

    const busca =
        document
        .getElementById("buscaProduto")
        .value
        .toLowerCase();

    const produtos =
        document.querySelectorAll(
            ".produto"
        );

    produtos.forEach(produto => {

        const nome =
            produto.dataset.nome;

        if(nome.includes(busca)){

            produto.style.display =
                "block";

        }else{

            produto.style.display =
                "none";

        }

    });

}

</script>

</body>

</html>