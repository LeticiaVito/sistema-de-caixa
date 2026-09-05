<?php

session_start();

if (!isset($_SESSION["usuario_id"])) {
    header("Location: index.php");
    exit;
}

require_once "config/conexao.php";
require_once "config/dinheiro.php";

$usuarioId = (int)$_SESSION["usuario_id"];


/* ==============================
   BUSCA CAIXA ABERTO
============================== */

$sqlCaixa = "
    SELECT *
    FROM caixas
    WHERE status = 'aberto'
    ORDER BY id DESC
    LIMIT 1
";

$resultadoCaixa = $conn->query($sqlCaixa);

$caixaAberto = null;

if (
    $resultadoCaixa &&
    $resultadoCaixa->num_rows === 1
) {
    $caixaAberto = $resultadoCaixa->fetch_assoc();
}


/* ==============================
   VALORES PADRÃO
============================== */

$movimentacoes = null;

$totalEntradas = 0;
$totalSaidas = 0;
$totalSangrias = 0;

$totalVendasDinheiro = 0;
$totalVendasPix = 0;
$totalVendasCartao = 0;
$totalVendido = 0;

$saldoEsperado = 0;


/* ==============================
   SE EXISTIR CAIXA ABERTO
============================== */

if ($caixaAberto) {

    $caixaId = (int)$caixaAberto["id"];


    /* ==============================
       MOVIMENTAÇÕES
    ============================== */

    $sqlMov = "
        SELECT *
        FROM movimentacoes_caixa
        WHERE caixa_id = ?
        ORDER BY criado_em DESC
    ";

    $stmtMov = $conn->prepare($sqlMov);
    $stmtMov->bind_param("i", $caixaId);
    $stmtMov->execute();

    $movimentacoes = $stmtMov->get_result();


    /* ==============================
       RESUMO DAS MOVIMENTAÇÕES
    ============================== */

    $sqlResumoMov = "
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

    $stmtResumoMov = $conn->prepare($sqlResumoMov);
    $stmtResumoMov->bind_param("i", $caixaId);
    $stmtResumoMov->execute();

    $dadosMov =
        $stmtResumoMov
        ->get_result()
        ->fetch_assoc();

    $totalEntradas = (float)$dadosMov["entradas"];
    $totalSaidas = (float)$dadosMov["saidas"];
    $totalSangrias = (float)$dadosMov["sangrias"];


    /* ==============================
       VENDAS POR FORMA DE PAGAMENTO
    ============================== */

    $sqlVendas = "
        SELECT

            COALESCE(
                SUM(
                    CASE
                        WHEN forma_pagamento = 'dinheiro'
                        THEN total
                        ELSE 0
                    END
                ),
                0
            ) AS dinheiro,

            COALESCE(
                SUM(
                    CASE
                        WHEN forma_pagamento = 'pix'
                        THEN total
                        ELSE 0
                    END
                ),
                0
            ) AS pix,

            COALESCE(
                SUM(
                    CASE
                        WHEN forma_pagamento = 'cartao'
                        THEN total
                        ELSE 0
                    END
                ),
                0
            ) AS cartao,

            COALESCE(
                SUM(total),
                0
            ) AS total

        FROM vendas

        WHERE status = 'finalizada'

        AND caixa_id = ?
    ";

    $stmtVendas = $conn->prepare($sqlVendas);

    $stmtVendas->bind_param(
        "i",
        $caixaId
    );

    $stmtVendas->execute();

    $dadosVendas =
        $stmtVendas
        ->get_result()
        ->fetch_assoc();

    $totalVendasDinheiro =
        (float)$dadosVendas["dinheiro"];

    $totalVendasPix =
        (float)$dadosVendas["pix"];

    $totalVendasCartao =
        (float)$dadosVendas["cartao"];

    $totalVendido =
        (float)$dadosVendas["total"];


    /* ==============================
       SALDO FÍSICO ESPERADO
    ============================== */

    /*
        Só dinheiro entra no caixa físico.

        PIX e cartão entram no faturamento,
        mas não entram no dinheiro em espécie.
    */

    $saldoEsperado =
        (float)$caixaAberto["valor_inicial"]
        + $totalVendasDinheiro
        + $totalEntradas
        - $totalSaidas
        - $totalSangrias;

}

?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Caixa | Cantina do Tio Fabinho</title>

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
    max-width:1250px;
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
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:20px;
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

.status-caixa{
    background:#e8f7ec;
    color:#247a3d;
    padding:9px 13px;
    border-radius:20px;
    font-size:12px;
    font-weight:bold;
}

.mensagem{
    padding:13px 15px;
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

.card{
    background:white;
    padding:22px;
    border-radius:16px;
    box-shadow:0 5px 20px rgba(0,0,0,.06);
    margin-bottom:20px;
}

.caixa-fechado{
    text-align:center;
    padding:50px 20px;
}

.caixa-fechado h2{
    margin-bottom:8px;
}

.caixa-fechado p{
    color:#777;
    margin-bottom:25px;
}

.denominacoes-abertura{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin:20px 0}
.denominacao-campo{background:#f7f7f7;border:1px solid #eee;border-radius:10px;padding:10px}
.denominacao-campo span{display:block;font-size:11px;font-weight:bold;margin-bottom:6px}
.denominacao-campo input{margin:0;padding:10px;background:#fff}
.total-abertura{background:#181818;color:#fff;padding:14px;border-radius:10px;margin-bottom:10px;display:flex;justify-content:space-between}

.campo{
    max-width:400px;
    margin:0 auto 15px;
    text-align:left;
}

label{
    display:block;
    font-size:13px;
    font-weight:bold;
    margin-bottom:7px;
}

input{
    width:100%;
    padding:13px;
    border:1px solid #ddd;
    border-radius:10px;
    outline:none;
}

.btn{
    border:none;
    background:#1b1b1b;
    color:white;
    padding:13px 18px;
    border-radius:10px;
    cursor:pointer;
    text-decoration:none;
    display:inline-block;
    font-weight:bold;
}

.btn:hover{
    background:#333;
}


/* RESUMO PRINCIPAL */

.cards-resumo{
    display:grid;
    grid-template-columns:repeat(4,1fr);
    gap:15px;
    margin-bottom:20px;
}

.resumo{
    background:white;
    padding:20px;
    border-radius:14px;
    box-shadow:0 5px 20px rgba(0,0,0,.05);
}

.resumo span{
    display:block;
    font-size:12px;
    color:#888;
    margin-bottom:7px;
}

.resumo strong{
    display:block;
    font-size:24px;
}

.resumo small{
    display:block;
    color:#aaa;
    margin-top:6px;
    font-size:11px;
}


/* PAGAMENTOS */

.pagamentos{
    display:grid;
    grid-template-columns:repeat(4,1fr);
    gap:15px;
    margin-bottom:20px;
}

.pagamento-card{
    background:white;
    padding:20px;
    border-radius:14px;
    box-shadow:0 5px 20px rgba(0,0,0,.05);
}

.pagamento-topo{
    display:flex;
    justify-content:space-between;
    align-items:center;
    margin-bottom:12px;
}

.pagamento-card span{
    font-size:12px;
    color:#888;
}

.pagamento-card strong{
    display:block;
    font-size:22px;
}

.icone{
    width:38px;
    height:38px;
    border-radius:10px;
    background:#f2f2f2;
    display:flex;
    align-items:center;
    justify-content:center;
}


/* GRID */

.grid{
    display:grid;
    grid-template-columns:1.3fr 1fr;
    gap:20px;
}

.acoes{
    display:grid;
    grid-template-columns:repeat(3,1fr);
    gap:10px;
}

.acao{
    border:1px solid #eee;
    padding:18px;
    border-radius:12px;
}

.acao h3{
    font-size:15px;
    margin-bottom:15px;
}

.acao input{
    margin-bottom:10px;
}

.btn-selecionar{width:100%;border:1px solid #ddd;background:#f7f7f7;padding:10px;border-radius:8px;font-size:11px;font-weight:bold;cursor:pointer;margin-bottom:8px}
.modal-dinheiro{position:fixed;inset:0;background:rgba(0,0,0,.55);display:none;align-items:center;justify-content:center;padding:20px;z-index:100}
.modal-dinheiro.aberto{display:flex}.modal-conteudo{background:#fff;border-radius:16px;padding:22px;width:min(620px,100%);max-height:90vh;overflow:auto}
.modal-topo{display:flex;justify-content:space-between;align-items:center;margin-bottom:16px}.modal-fechar{border:0;background:#eee;border-radius:8px;padding:8px 11px;cursor:pointer}
.modal-denominacoes{display:grid;grid-template-columns:repeat(4,1fr);gap:9px}.modal-nota{border:1px solid #ddd;background:#fff;border-radius:9px;padding:10px 5px;cursor:pointer;font-weight:bold}.modal-nota small{display:block;color:#247a3d;margin-top:3px}
.modal-total{background:#181818;color:#fff;padding:14px;border-radius:10px;margin:15px 0;display:flex;justify-content:space-between}.modal-acoes{display:flex;gap:9px}.modal-acoes button{flex:1;padding:11px;border:0;border-radius:9px;cursor:pointer}.modal-confirmar{background:#181818;color:#fff}

.tabela-container{
    overflow-x:auto;
}

table{
    width:100%;
    border-collapse:collapse;
}

th,
td{
    padding:13px;
    text-align:left;
    border-bottom:1px solid #eee;
    font-size:13px;
}

th{
    color:#777;
}

.badge{
    display:inline-block;
    padding:5px 9px;
    border-radius:20px;
    font-size:11px;
}

.entrada{
    background:#e8f7ec;
    color:#247a3d;
}

.saida{
    background:#ffeaea;
    color:#b42318;
}

.sangria{
    background:#fff3d6;
    color:#9a6700;
}

.saldo-box{
    margin-top:20px;
    padding:20px;
    background:#181818;
    color:white;
    border-radius:14px;
}

.saldo-box span{
    display:block;
    font-size:12px;
    color:#bbb;
    margin-bottom:7px;
}

.saldo-box strong{
    font-size:30px;
}

.saldo-box small{
    display:block;
    margin-top:7px;
    color:#aaa;
    font-size:11px;
}

.fechar{
    margin-top:15px;
    display:block;
    background:#b42318;
    color:white;
    text-align:center;
    padding:14px;
    border-radius:10px;
    text-decoration:none;
    font-weight:bold;
}

.fechar:hover{
    background:#8f1c14;
}

@media(max-width:1000px){

    .cards-resumo,
    .pagamentos{
        grid-template-columns:repeat(2,1fr);
    }

    .grid{
        grid-template-columns:1fr;
    }

}

@media(max-width:750px){

    .acoes{
        grid-template-columns:1fr;
    }

}

@media(max-width:550px){

    .denominacoes-abertura{grid-template-columns:repeat(2,1fr)}

    .cards-resumo,
    .pagamentos{
        grid-template-columns:1fr;
    }

    .topo{
        align-items:flex-start;
        flex-direction:column;
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

<div>

<h1>Caixa</h1>

<p>
Controle financeiro da Cantina do Tio Fabinho.
</p>

</div>

<?php if ($caixaAberto): ?>

<div class="status-caixa">
● Caixa aberto
</div>

<?php endif; ?>

</div>


<?php if (isset($_GET["aberto"])): ?>

<div class="mensagem sucesso">
Caixa aberto com sucesso.
</div>

<?php endif; ?>


<?php if (isset($_GET["movimentacao"])): ?>

<div class="mensagem sucesso">
Movimentação registrada com sucesso.
</div>

<?php endif; ?>


<?php if (isset($_GET["fechado"])): ?>

<div class="mensagem sucesso">
Caixa fechado com sucesso.
</div>

<?php endif; ?>


<?php if (($_GET["erro"] ?? "") === "caixa_fechado"): ?>

<div class="mensagem erro">
Abra o caixa antes de iniciar uma venda.
</div>

<?php elseif (isset($_GET["erro"])): ?>

<div class="mensagem erro">
Não foi possível concluir a operação.
</div>

<?php endif; ?>


<?php if (!$caixaAberto): ?>


<div class="card caixa-fechado">

<h2>Caixa fechado</h2>

<p>
Informe o valor inicial disponível em dinheiro.
</p>

<form
action="abrir_caixa.php"
method="POST"
>

<div class="denominacoes-abertura">
<?php foreach (denominacoesDinheiro() as $valorCentavos => $rotulo): ?>
<label class="denominacao-campo">
<span><?php echo htmlspecialchars($rotulo); ?></span>
<input type="number" name="denominacoes[<?php echo $valorCentavos; ?>]" min="0" value="0" inputmode="numeric" oninput="calcularAbertura()">
</label>
<?php endforeach; ?>
</div>

<div class="total-abertura">Total inicial: <strong id="totalAbertura">R$ 0,00</strong></div>

<button class="btn" type="submit">
Abrir Caixa
</button>

</form>

</div>


<?php else: ?>


<!-- RESUMO -->

<section class="cards-resumo">


<div class="resumo">

<span>
Valor inicial
</span>

<strong>

R$
<?php
echo number_format(
    $caixaAberto["valor_inicial"],
    2,
    ",",
    "."
);
?>

</strong>

<small>
Dinheiro colocado na abertura
</small>

</div>


<div class="resumo">

<span>
Total vendido
</span>

<strong>

R$
<?php
echo number_format(
    $totalVendido,
    2,
    ",",
    "."
);
?>

</strong>

<small>
Todas as formas de pagamento
</small>

</div>


<div class="resumo">

<span>
Entradas adicionais
</span>

<strong>

R$
<?php
echo number_format(
    $totalEntradas,
    2,
    ",",
    "."
);
?>

</strong>

<small>
Valores adicionados manualmente
</small>

</div>


<div class="resumo">

<span>
Saídas + sangrias
</span>

<strong>

R$
<?php
echo number_format(
    $totalSaidas + $totalSangrias,
    2,
    ",",
    "."
);
?>

</strong>

<small>
Valores retirados do caixa
</small>

</div>


</section>


<!-- FORMAS DE PAGAMENTO -->

<section class="pagamentos">


<div class="pagamento-card">

<div class="pagamento-topo">

<div>
<span>Dinheiro</span>
</div>

<div class="icone">
💵
</div>

</div>

<strong>

R$
<?php
echo number_format(
    $totalVendasDinheiro,
    2,
    ",",
    "."
);
?>

</strong>

</div>


<div class="pagamento-card">

<div class="pagamento-topo">

<div>
<span>PIX</span>
</div>

<div class="icone">
📱
</div>

</div>

<strong>

R$
<?php
echo number_format(
    $totalVendasPix,
    2,
    ",",
    "."
);
?>

</strong>

</div>


<div class="pagamento-card">

<div class="pagamento-topo">

<div>
<span>Cartão</span>
</div>

<div class="icone">
💳
</div>

</div>

<strong>

R$
<?php
echo number_format(
    $totalVendasCartao,
    2,
    ",",
    "."
);
?>

</strong>

</div>


<div class="pagamento-card">

<div class="pagamento-topo">

<div>
<span>Total das vendas</span>
</div>

<div class="icone">
🧾
</div>

</div>

<strong>

R$
<?php
echo number_format(
    $totalVendido,
    2,
    ",",
    "."
);
?>

</strong>

</div>


</section>


<div class="grid">


<!-- MOVIMENTAÇÕES -->

<div class="card">

<h2 style="margin-bottom:20px;">
Movimentações do caixa
</h2>


<div class="acoes">


<div class="acao">

<h3>➕ Entrada</h3>

<form
action="registrar_movimentacao.php"
method="POST"
>

<input
type="hidden"
name="tipo"
value="entrada"
>

<input
type="text"
name="descricao"
placeholder="Descrição"
required
>

<input
type="number"
name="valor"
step="0.01"
min="0.01"
placeholder="Valor"
class="valor-movimentacao"
readonly
required
>
<input type="hidden" name="dinheiro_detalhes" class="dinheiro-detalhes">
<button type="button" class="btn-selecionar" onclick="abrirSeletor(this.form)">Selecionar notas e moedas</button>

<button class="btn" type="submit">
Registrar
</button>

</form>

</div>


<div class="acao">

<h3>➖ Saída</h3>

<form
action="registrar_movimentacao.php"
method="POST"
>

<input
type="hidden"
name="tipo"
value="saida"
>

<input
type="text"
name="descricao"
placeholder="Descrição"
required
>

<input
type="number"
name="valor"
step="0.01"
min="0.01"
placeholder="Valor"
class="valor-movimentacao"
readonly
required
>
<input type="hidden" name="dinheiro_detalhes" class="dinheiro-detalhes">
<button type="button" class="btn-selecionar" onclick="abrirSeletor(this.form)">Selecionar notas e moedas</button>

<button class="btn" type="submit">
Registrar
</button>

</form>

</div>


<div class="acao">

<h3>💸 Sangria</h3>

<form
action="registrar_movimentacao.php"
method="POST"
>

<input
type="hidden"
name="tipo"
value="sangria"
>

<input
type="text"
name="descricao"
placeholder="Motivo da sangria"
required
>

<input
type="number"
name="valor"
step="0.01"
min="0.01"
placeholder="Valor"
class="valor-movimentacao"
readonly
required
>
<input type="hidden" name="dinheiro_detalhes" class="dinheiro-detalhes">
<button type="button" class="btn-selecionar" onclick="abrirSeletor(this.form)">Selecionar notas e moedas</button>

<button class="btn" type="submit">
Registrar
</button>

</form>

</div>


</div>


<div class="saldo-box">

<span>
Dinheiro físico esperado no caixa
</span>

<strong>

R$
<?php
echo number_format(
    $saldoEsperado,
    2,
    ",",
    "."
);
?>

</strong>

<small>
Valor inicial + vendas em dinheiro + entradas − saídas − sangrias.
</small>

</div>


<a
href="fechar_caixa.php?id=<?php echo $caixaAberto["id"]; ?>"
class="fechar"
>
Fechar Caixa
</a>


</div>


<!-- HISTÓRICO -->

<div class="card">

<h2 style="margin-bottom:20px;">
Histórico de movimentações
</h2>

<div class="tabela-container">

<?php if (
    $movimentacoes &&
    $movimentacoes->num_rows > 0
): ?>

<table>

<thead>

<tr>
<th>Data</th>
<th>Tipo</th>
<th>Descrição</th>
<th>Valor</th>
</tr>

</thead>

<tbody>

<?php while (
    $mov = $movimentacoes->fetch_assoc()
): ?>

<tr>

<td>

<?php
echo date(
    "d/m/Y H:i",
    strtotime($mov["criado_em"])
);
?>

</td>

<td>

<span class="badge <?php echo $mov["tipo"]; ?>">

<?php

if ($mov["tipo"] === "entrada") {

    echo "Entrada";

} elseif ($mov["tipo"] === "saida") {

    echo "Saída";

} else {

    echo "Sangria";

}

?>

</span>

</td>

<td>

<?php
echo htmlspecialchars(
    $mov["descricao"]
);
?>

</td>

<td>

R$
<?php
echo number_format(
    $mov["valor"],
    2,
    ",",
    "."
);
?>

</td>

</tr>

<?php endwhile; ?>

</tbody>

</table>

<?php else: ?>

<p style="color:#888;font-size:13px;">
Nenhuma movimentação registrada neste caixa.
</p>

<?php endif; ?>

</div>

</div>


</div>


<?php endif; ?>

</div>

<div class="modal-dinheiro" id="modalDinheiro">
<div class="modal-conteudo">
<div class="modal-topo"><div><h2>Notas e moedas</h2><small>Toque nos valores para adicionar.</small></div><button type="button" class="modal-fechar" onclick="fecharSeletor()">✕</button></div>
<div class="modal-denominacoes">
<?php foreach (denominacoesDinheiro() as $valorCentavos => $rotulo): ?>
<button type="button" class="modal-nota" onclick="adicionarMovimento(<?php echo $valorCentavos; ?>)"><?php echo htmlspecialchars($rotulo); ?><small id="mov-qtd-<?php echo $valorCentavos; ?>"></small></button>
<?php endforeach; ?>
</div>
<div class="modal-total"><span>Total selecionado</span><strong id="modalTotal">R$ 0,00</strong></div>
<div class="modal-acoes"><button type="button" onclick="limparMovimento()">Limpar</button><button type="button" class="modal-confirmar" onclick="confirmarMovimento()">Confirmar</button></div>
</div>
</div>

</body>

<script>
function calcularAbertura(){
    let centavos=0;
    document.querySelectorAll('.denominacoes-abertura input').forEach(input=>{
        const valor=parseInt(input.name.match(/\[(\d+)\]/)[1],10);
        centavos+=valor*(parseInt(input.value,10)||0);
    });
    document.getElementById('totalAbertura').textContent=(centavos/100).toLocaleString('pt-BR',{style:'currency',currency:'BRL'});
}

let formularioMovimento=null;let movimentoAtual={};const valoresMovimento=[20000,10000,5000,2000,1000,500,200,100,50,25,10,5,1];
function abrirSeletor(form){formularioMovimento=form;try{movimentoAtual=JSON.parse(form.querySelector('.dinheiro-detalhes').value)||{}}catch(e){movimentoAtual={}};atualizarSeletor();document.getElementById('modalDinheiro').classList.add('aberto')}
function fecharSeletor(){document.getElementById('modalDinheiro').classList.remove('aberto')}
function adicionarMovimento(v){movimentoAtual[v]=(movimentoAtual[v]||0)+1;atualizarSeletor()}
function limparMovimento(){movimentoAtual={};atualizarSeletor()}
function atualizarSeletor(){let total=0;valoresMovimento.forEach(v=>{const q=movimentoAtual[v]||0;total+=v*q;document.getElementById('mov-qtd-'+v).textContent=q?'× '+q:''});document.getElementById('modalTotal').textContent=(total/100).toLocaleString('pt-BR',{style:'currency',currency:'BRL'})}
function confirmarMovimento(){let total=0;valoresMovimento.forEach(v=>total+=v*(movimentoAtual[v]||0));formularioMovimento.querySelector('.valor-movimentacao').value=(total/100).toFixed(2);formularioMovimento.querySelector('.dinheiro-detalhes').value=JSON.stringify(movimentoAtual);fecharSeletor()}
</script>
</html>
