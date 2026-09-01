<?php

session_start();

if (!isset($_SESSION["usuario_id"])) {
    header("Location: index.php");
    exit;
}

require_once "config/conexao.php";

$nomeUsuario = $_SESSION["usuario_nome"] ?? "Usuário";
$tipoUsuario = $_SESSION["usuario_tipo"] ?? "atendente";


/* =====================================================
   VENDAS DE HOJE
===================================================== */

$sqlVendasHoje = "
    SELECT
        COUNT(*) AS quantidade,
        COALESCE(SUM(total), 0) AS faturamento
    FROM vendas
    WHERE status = 'finalizada'
    AND DATE(data_venda) = CURDATE()
";

$resultadoVendasHoje = $conn->query($sqlVendasHoje);

$vendasHoje = 0;
$faturamentoHoje = 0;

if ($resultadoVendasHoje) {

    $dados = $resultadoVendasHoje->fetch_assoc();

    $vendasHoje = (int)$dados["quantidade"];
    $faturamentoHoje = (float)$dados["faturamento"];

}


/* =====================================================
   PRODUTOS ATIVOS
===================================================== */

$sqlProdutos = "
    SELECT COUNT(*) AS total
    FROM produtos
    WHERE ativo = 1
";

$resultadoProdutos = $conn->query($sqlProdutos);

$totalProdutos = 0;

if ($resultadoProdutos) {

    $dados = $resultadoProdutos->fetch_assoc();

    $totalProdutos = (int)$dados["total"];

}


/* =====================================================
   ESTOQUE BAIXO
===================================================== */

$sqlEstoqueBaixo = "
    SELECT COUNT(*) AS total
    FROM produtos
    WHERE ativo = 1
    AND estoque <= estoque_minimo
";

$resultadoEstoqueBaixo = $conn->query($sqlEstoqueBaixo);

$totalEstoqueBaixo = 0;

if ($resultadoEstoqueBaixo) {

    $dados = $resultadoEstoqueBaixo->fetch_assoc();

    $totalEstoqueBaixo = (int)$dados["total"];

}


/* =====================================================
   ALERTAS DE ESTOQUE
===================================================== */

$sqlAlertas = "
    SELECT
        id,
        nome,
        estoque,
        estoque_minimo
    FROM produtos
    WHERE ativo = 1
    AND estoque <= estoque_minimo
    ORDER BY estoque ASC, nome ASC
    LIMIT 5
";

$resultadoAlertas = $conn->query($sqlAlertas);


/* =====================================================
   PRODUTOS MAIS VENDIDOS
===================================================== */

$sqlMaisVendidos = "
    SELECT
        p.id,
        p.nome,
        SUM(iv.quantidade) AS total_vendido
    FROM itens_venda iv

    INNER JOIN vendas v
        ON v.id = iv.venda_id

    INNER JOIN produtos p
        ON p.id = iv.produto_id

    WHERE v.status = 'finalizada'

    GROUP BY
        p.id,
        p.nome

    ORDER BY total_vendido DESC

    LIMIT 5
";

$resultadoMaisVendidos = $conn->query($sqlMaisVendidos);


/* =====================================================
   PRODUTOS MENOS VENDIDOS
===================================================== */

$sqlMenosVendidos = "
    SELECT
        p.id,
        p.nome,
        COALESCE(
            SUM(
                CASE
                    WHEN v.status = 'finalizada'
                    THEN iv.quantidade
                    ELSE 0
                END
            ),
            0
        ) AS total_vendido

    FROM produtos p

    LEFT JOIN itens_venda iv
        ON iv.produto_id = p.id

    LEFT JOIN vendas v
        ON v.id = iv.venda_id

    WHERE p.ativo = 1

    GROUP BY
        p.id,
        p.nome

    ORDER BY
        total_vendido ASC,
        p.nome ASC

    LIMIT 5
";

$resultadoMenosVendidos = $conn->query($sqlMenosVendidos);


/* =====================================================
   GRÁFICO DOS ÚLTIMOS 7 DIAS
===================================================== */

$sqlGrafico = "
    SELECT
        DATE(data_venda) AS data,
        COUNT(*) AS quantidade
    FROM vendas

    WHERE status = 'finalizada'
    AND data_venda >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)

    GROUP BY DATE(data_venda)

    ORDER BY DATE(data_venda)
";

$resultadoGrafico = $conn->query($sqlGrafico);


/*
Criamos os últimos 7 dias já com zero.
Depois substituímos pelos valores encontrados.
*/

$graficoDados = [];

for ($i = 6; $i >= 0; $i--) {

    $data = date(
        "Y-m-d",
        strtotime("-{$i} days")
    );

    $graficoDados[$data] = 0;

}


if ($resultadoGrafico) {

    while ($linha = $resultadoGrafico->fetch_assoc()) {

        $graficoDados[$linha["data"]] =
            (int)$linha["quantidade"];

    }

}


$labelsGrafico = [];
$valoresGrafico = [];

foreach ($graficoDados as $data => $quantidade) {

    $labelsGrafico[] =
        date(
            "d/m",
            strtotime($data)
        );

    $valoresGrafico[] =
        $quantidade;

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
Dashboard | Cantina do Tio Fabinho
</title>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>


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

.app{
    display:flex;
    min-height:100vh;
}


/* =====================================================
   SIDEBAR
===================================================== */

.sidebar{

    width:250px;

    background:#181818;

    color:white;

    padding:25px 20px;

    display:flex;

    flex-direction:column;

    position:fixed;

    left:0;

    top:0;

    bottom:0;

}

.logo{
    margin-bottom:35px;
}

.logo-icon{

    width:48px;
    height:48px;

    border-radius:14px;

    background:white;

    color:#181818;

    display:flex;

    align-items:center;

    justify-content:center;

    font-size:24px;

    margin-bottom:15px;

}

.logo h2{

    font-size:21px;

    line-height:1.2;

}

.logo span{

    font-weight:300;

    color:#cfcfcf;

}


.menu{

    display:flex;

    flex-direction:column;

    gap:8px;

}

.menu a{

    text-decoration:none;

    color:#cfcfcf;

    padding:13px 14px;

    border-radius:10px;

    transition:.2s;

    font-size:14px;

}

.menu a:hover,
.menu a.ativo{

    background:#2c2c2c;

    color:white;

}


.sidebar-bottom{
    margin-top:auto;
}

.usuario{

    background:#242424;

    padding:14px;

    border-radius:12px;

    margin-bottom:10px;

}

.usuario strong{

    display:block;

    font-size:13px;

    margin-bottom:3px;

}

.usuario span{

    font-size:11px;

    color:#aaa;

    text-transform:uppercase;

}

.logout{

    display:block;

    text-align:center;

    color:#ffb5b5;

    text-decoration:none;

    font-size:13px;

    padding:10px;

    border-radius:9px;

}

.logout:hover{
    background:#2c2c2c;
}


/* =====================================================
   CONTEÚDO
===================================================== */

.main{

    margin-left:250px;

    width:calc(100% - 250px);

    padding:30px;

}


.topo{

    display:flex;

    justify-content:space-between;

    align-items:center;

    margin-bottom:30px;

}

.topo h1{

    font-size:28px;

    margin-bottom:5px;

}

.topo p{

    color:#777;

    font-size:14px;

}

.data{

    background:white;

    padding:12px 16px;

    border-radius:12px;

    box-shadow:0 2px 10px rgba(0,0,0,.05);

    font-size:13px;

}


/* =====================================================
   CARDS
===================================================== */

.cards{

    display:grid;

    grid-template-columns:repeat(4,1fr);

    gap:18px;

    margin-bottom:25px;

}

.card{

    background:white;

    padding:22px;

    border-radius:16px;

    box-shadow:0 5px 20px rgba(0,0,0,.06);

}

.card-topo{

    display:flex;

    justify-content:space-between;

    align-items:center;

    margin-bottom:18px;

}

.icone{

    width:40px;

    height:40px;

    background:#f0f0f0;

    border-radius:11px;

    display:flex;

    align-items:center;

    justify-content:center;

    font-size:19px;

}

.card h3{

    font-size:13px;

    color:#777;

    font-weight:normal;

}

.card .valor{

    font-size:26px;

    font-weight:bold;

    margin-bottom:6px;

}

.card small{

    color:#999;

    font-size:11px;

}


/* =====================================================
   DASHBOARD
===================================================== */

.grid-dashboard{

    display:grid;

    grid-template-columns:2fr 1fr;

    gap:20px;

}

.coluna-direita{

    display:flex;

    flex-direction:column;

    gap:20px;

}

.painel{

    background:white;

    border-radius:16px;

    padding:22px;

    box-shadow:0 5px 20px rgba(0,0,0,.06);

}

.painel h3{

    font-size:17px;

    margin-bottom:5px;

}

.painel-subtitulo{

    color:#888;

    font-size:12px;

    margin-bottom:25px;

}


/* =====================================================
   RANKING
===================================================== */

.ranking{

    display:flex;

    flex-direction:column;

    gap:11px;

}

.ranking-item{

    display:flex;

    align-items:center;

    justify-content:space-between;

    padding:13px;

    background:#f7f7f7;

    border-radius:12px;

}

.ranking-info{

    display:flex;

    align-items:center;

    gap:12px;

}

.ranking-numero{

    width:30px;

    height:30px;

    background:#222;

    color:white;

    border-radius:8px;

    display:flex;

    align-items:center;

    justify-content:center;

    font-size:12px;

    font-weight:bold;

}

.ranking-numero.menos{

    background:#e8e8e8;

    color:#555;

}

.ranking-nome strong{

    display:block;

    font-size:13px;

}

.ranking-nome span{

    font-size:11px;

    color:#888;

}

.ranking-qtd{

    font-size:13px;

    font-weight:bold;

}


/* =====================================================
   ALERTAS
===================================================== */

.alerta{

    padding:14px;

    background:#fff6e5;

    border-radius:12px;

    margin-bottom:10px;

    font-size:13px;

}

.alerta strong{

    display:block;

    margin-bottom:4px;

}

.alerta-sem{

    background:#eef8f0;

}

canvas{

    max-height:300px;

}

.footer{

    margin-top:30px;

    text-align:center;

    color:#aaa;

    font-size:12px;

}


/* =====================================================
   RESPONSIVO
===================================================== */

@media(max-width:1100px){

    .cards{

        grid-template-columns:repeat(2,1fr);

    }

    .grid-dashboard{

        grid-template-columns:1fr;

    }

}


@media(max-width:750px){

    .sidebar{

        width:80px;

    }

    .logo h2,
    .logo span,
    .menu a span,
    .usuario,
    .logout{

        display:none;

    }

    .menu a{

        text-align:center;

        font-size:20px;

    }

    .main{

        margin-left:80px;

        width:calc(100% - 80px);

        padding:20px;

    }

    .cards{

        grid-template-columns:1fr;

    }

    .topo{

        flex-direction:column;

        align-items:flex-start;

        gap:15px;

    }

}

</style>

</head>


<body>


<div class="app">


<aside class="sidebar">


<div class="logo">

<div class="logo-icon">
☕
</div>

<h2>

Cantina do<br>

<span>
Tio Fabinho
</span>

</h2>

</div>


<nav class="menu">


<a
href="dashboard.php"
class="ativo"
>

📊
<span>
Dashboard
</span>

</a>


<a href="nova_venda.php">

🛒
<span>
Nova Venda
</span>

</a>


<a href="produtos.php">

📦
<span>
Produtos
</span>

</a>


<a href="#">

📋
<span>
Estoque
</span>

</a>


<a href="caixa.php">

💰
<span>
Caixa
</span>

</a>


<a href="vendas.php">

🧾
<span>
Vendas
</span>

</a>


<a href="#">

📈
<span>
Relatórios
</span>

</a>


<a href="#">

🚚
<span>
Fornecedores
</span>

</a>


</nav>


<div class="sidebar-bottom">


<div class="usuario">

<strong>

<?php
echo htmlspecialchars($nomeUsuario);
?>

</strong>

<span>

<?php
echo htmlspecialchars($tipoUsuario);
?>

</span>

</div>


<a
href="logout.php"
class="logout"
>

Sair

</a>


</div>


</aside>


<main class="main">


<div class="topo">


<div>

<h1>
Dashboard
</h1>

<p>
Visão geral da Cantina do Tio Fabinho.
</p>

</div>


<div
class="data"
id="dataAtual"
></div>


</div>


<!-- CARDS -->

<section class="cards">


<div class="card">

<div class="card-topo">

<h3>
Vendas de hoje
</h3>

<div class="icone">
🛒
</div>

</div>

<div class="valor">

<?php
echo $vendasHoje;
?>

</div>

<small>
Vendas finalizadas hoje
</small>

</div>


<div class="card">

<div class="card-topo">

<h3>
Faturamento hoje
</h3>

<div class="icone">
💰
</div>

</div>

<div class="valor">

R$

<?php

echo number_format(
    $faturamentoHoje,
    2,
    ",",
    "."
);

?>

</div>

<small>
Valor total vendido hoje
</small>

</div>


<div class="card">

<div class="card-topo">

<h3>
Produtos ativos
</h3>

<div class="icone">
📦
</div>

</div>

<div class="valor">

<?php
echo $totalProdutos;
?>

</div>

<small>
Produtos disponíveis para venda
</small>

</div>


<div class="card">

<div class="card-topo">

<h3>
Estoque baixo
</h3>

<div class="icone">
⚠️
</div>

</div>

<div class="valor">

<?php
echo $totalEstoqueBaixo;
?>

</div>

<small>
Produtos precisando de reposição
</small>

</div>


</section>


<section class="grid-dashboard">


<!-- GRÁFICO -->

<div class="painel">


<h3>
Vendas dos últimos 7 dias
</h3>


<p class="painel-subtitulo">
Quantidade de vendas realizadas por dia.
</p>


<canvas id="graficoVendas"></canvas>


</div>


<!-- COLUNA DIREITA -->

<div class="coluna-direita">


<!-- MAIS VENDIDOS -->

<div class="painel">

<h3>
🔥 Mais vendidos
</h3>

<p class="painel-subtitulo">
Produtos com maior saída.
</p>


<div class="ranking">


<?php

if (
    $resultadoMaisVendidos &&
    $resultadoMaisVendidos->num_rows > 0
):

    $posicao = 1;

    while (
        $produto =
        $resultadoMaisVendidos->fetch_assoc()
    ):

?>


<div class="ranking-item">


<div class="ranking-info">


<div class="ranking-numero">

<?php
echo $posicao;
?>

</div>


<div class="ranking-nome">

<strong>

<?php
echo htmlspecialchars(
    $produto["nome"]
);
?>

</strong>

<span>
Unidades vendidas
</span>

</div>


</div>


<div class="ranking-qtd">

<?php
echo (int)$produto["total_vendido"];
?>

un.

</div>


</div>


<?php

        $posicao++;

    endwhile;

else:

?>


<div class="ranking-item">

<div class="ranking-nome">

<strong>
Nenhuma venda registrada
</strong>

<span>
Os produtos aparecerão aqui.
</span>

</div>

</div>


<?php endif; ?>


</div>

</div>


<!-- MENOS VENDIDOS -->

<div class="painel">

<h3>
📉 Menos vendidos
</h3>

<p class="painel-subtitulo">
Produtos com pouca ou nenhuma saída.
</p>


<div class="ranking">


<?php

if (
    $resultadoMenosVendidos &&
    $resultadoMenosVendidos->num_rows > 0
):

    $posicao = 1;

    while (
        $produto =
        $resultadoMenosVendidos->fetch_assoc()
    ):

?>


<div class="ranking-item">


<div class="ranking-info">


<div class="ranking-numero menos">

<?php
echo $posicao;
?>

</div>


<div class="ranking-nome">

<strong>

<?php
echo htmlspecialchars(
    $produto["nome"]
);
?>

</strong>

<span>
Unidades vendidas
</span>

</div>


</div>


<div class="ranking-qtd">

<?php
echo (int)$produto["total_vendido"];
?>

un.

</div>


</div>


<?php

        $posicao++;

    endwhile;

else:

?>


<div class="ranking-item">

<div class="ranking-nome">

<strong>
Nenhum produto disponível
</strong>

</div>

</div>


<?php endif; ?>


</div>

</div>


<!-- ALERTA ESTOQUE -->

<div class="painel">


<h3>
⚠️ Alertas de estoque
</h3>


<p class="painel-subtitulo">
Produtos que chegaram ao estoque mínimo.
</p>


<?php

if (
    $resultadoAlertas &&
    $resultadoAlertas->num_rows > 0
):

    while (
        $alerta =
        $resultadoAlertas->fetch_assoc()
    ):

?>


<div class="alerta">

<strong>

<?php
echo htmlspecialchars(
    $alerta["nome"]
);
?>

</strong>

Estoque atual:

<?php
echo (int)$alerta["estoque"];
?>

un.

<br>

Mínimo:

<?php
echo (int)$alerta["estoque_minimo"];
?>

un.

</div>


<?php

    endwhile;

else:

?>


<div class="alerta alerta-sem">

<strong>
✅ Estoque em ordem
</strong>

Nenhum produto precisa de reposição.

</div>


<?php endif; ?>


</div>


</div>


</section>


<div class="footer">

Cantina do Tio Fabinho • Sistema de Gestão

</div>


</main>


</div>


<script>


/* DATA ATUAL */

const dataAtual = new Date();


document
.getElementById("dataAtual")
.innerText =

dataAtual.toLocaleDateString(
    "pt-BR",
    {

        weekday:"long",

        day:"2-digit",

        month:"long",

        year:"numeric"

    }
);


/* GRÁFICO */

const labelsGrafico =
<?php
echo json_encode(
    $labelsGrafico
);
?>;


const valoresGrafico =
<?php
echo json_encode(
    $valoresGrafico
);
?>;


const ctx =
document
.getElementById("graficoVendas")
.getContext("2d");


new Chart(
    ctx,
    {

        type:"line",

        data:{

            labels:
                labelsGrafico,

            datasets:[
                {

                    label:
                        "Vendas",

                    data:
                        valoresGrafico,

                    borderWidth:2,

                    tension:.35,

                    fill:false,

                    pointRadius:4,

                    pointHoverRadius:6

                }
            ]

        },

        options:{

            responsive:true,

            maintainAspectRatio:true,

            plugins:{

                legend:{
                    display:false
                }

            },

            scales:{

                y:{

                    beginAtZero:true,

                    ticks:{

                        precision:0

                    }

                }

            }

        }

    }
);


</script>


</body>

</html>