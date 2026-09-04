<?php

session_start();

if (!isset($_SESSION["usuario_id"])) {
    header("Location: index.php");
    exit;
}

require_once "config/conexao.php";
require_once "config/dinheiro.php";


if ($_SERVER["REQUEST_METHOD"] !== "POST") {

    header("Location: nova_venda.php");
    exit;

}


/* ==============================
   DADOS RECEBIDOS
============================== */

$itensJson =
    $_POST["itens"] ?? "";

$formaPagamento =
    $_POST["forma_pagamento"] ?? "";

$dinheiroJson = $_POST["dinheiro_detalhes"] ?? "";

$valorRecebido =
    isset($_POST["valor_recebido"])
    && $_POST["valor_recebido"] !== ""
        ? (float)$_POST["valor_recebido"]
        : null;

$usuarioId =
    (int)$_SESSION["usuario_id"];


/* ==============================
   FORMAS DE PAGAMENTO PERMITIDAS
============================== */

$formasPermitidas = [
    "dinheiro",
    "pix",
    "cartao"
];


if (
    $itensJson === "" ||
    !in_array(
        $formaPagamento,
        $formasPermitidas,
        true
    )
) {

    header(
        "Location: nova_venda.php?erro=1"
    );

    exit;

}


/* ==============================
   CONVERTE O CARRINHO
============================== */

$itens =
    json_decode(
        $itensJson,
        true
    );


if (
    !is_array($itens) ||
    count($itens) === 0
) {

    header(
        "Location: nova_venda.php?erro=1"
    );

    exit;

}


/* ==============================
   INICIA TRANSAÇÃO
============================== */

$conn->begin_transaction();


try {

    /* A venda precisa pertencer a um caixa aberto. O bloqueio evita que o
       caixa seja fechado enquanto a venda estiver sendo processada. */
    $sqlCaixa = "
        SELECT id
        FROM caixas
        WHERE status = 'aberto'
        ORDER BY id DESC
        LIMIT 1
        FOR UPDATE
    ";

    $resultadoCaixa = $conn->query($sqlCaixa);

    if (!$resultadoCaixa || $resultadoCaixa->num_rows !== 1) {
        throw new Exception("Não existe caixa aberto.");
    }

    $caixaId = (int)$resultadoCaixa->fetch_assoc()["id"];


    $itensVenda = [];

    $totalVenda = 0;


/* ==============================
   CONFERE PRODUTOS
============================== */

    foreach ($itens as $item) {


        $produtoId =
            (int)($item["id"] ?? 0);

        $quantidade =
            (int)($item["quantidade"] ?? 0);


        if (
            $produtoId <= 0 ||
            $quantidade <= 0
        ) {

            throw new Exception(
                "Item inválido."
            );

        }


        $sqlProduto = "
            SELECT
                id,
                nome,
                preco_venda,
                estoque,
                ativo
            FROM produtos
            WHERE id = ?
            FOR UPDATE
        ";


        $stmtProduto =
            $conn->prepare(
                $sqlProduto
            );


        if (!$stmtProduto) {

            throw new Exception(
                "Erro ao consultar produto."
            );

        }


        $stmtProduto->bind_param(
            "i",
            $produtoId
        );


        $stmtProduto->execute();


        $resultadoProduto =
            $stmtProduto->get_result();


        if (
            $resultadoProduto->num_rows !== 1
        ) {

            throw new Exception(
                "Produto não encontrado."
            );

        }


        $produto =
            $resultadoProduto->fetch_assoc();


        if (
            (int)$produto["ativo"] !== 1
        ) {

            throw new Exception(
                "Produto inativo."
            );

        }


        if (
            (int)$produto["estoque"] <
            $quantidade
        ) {

            throw new Exception(
                "Estoque insuficiente para " .
                $produto["nome"]
            );

        }


        /*
            IMPORTANTE:
            o preço usado é sempre o do banco,
            nunca o preço enviado pelo JavaScript.
        */

        $preco =
            (float)$produto["preco_venda"];


        $subtotal =
            $preco *
            $quantidade;


        $totalVenda +=
            $subtotal;


        $itensVenda[] = [

            "produto_id" =>
                $produtoId,

            "quantidade" =>
                $quantidade,

            "preco" =>
                $preco,

            "subtotal" =>
                $subtotal

        ];

    }


/* ==============================
   VALOR RECEBIDO E TROCO
============================== */

    $troco = null;


    if ($formaPagamento === "dinheiro") {

        $dadosDinheiro = json_decode($dinheiroJson, true);
        if (!is_array($dadosDinheiro)) {
            throw new Exception("Informe as notas e moedas recebidas.");
        }
        $recebidoDetalhado = lerQuantidadesDinheiro($dadosDinheiro);
        $recebidoCentavos = totalDinheiro($recebidoDetalhado);
        $totalCentavos = (int)round($totalVenda * 100);


        if (
            $recebidoCentavos < $totalCentavos
        ) {

            throw new Exception(
                "Valor recebido insuficiente."
            );

        }


        $valorRecebido = $recebidoCentavos / 100;
        $trocoCentavos = $recebidoCentavos - $totalCentavos;
        $disponivel = [];
        $resultadoDenominacoes = $conn->query("SELECT valor_centavos, quantidade FROM caixa_denominacoes WHERE caixa_id = $caixaId FOR UPDATE");
        while ($linha = $resultadoDenominacoes->fetch_assoc()) {
            $disponivel[(int)$linha["valor_centavos"]] = (int)$linha["quantidade"];
        }
        foreach ($recebidoDetalhado as $valor => $quantidade) {
            $disponivel[$valor] = ($disponivel[$valor] ?? 0) + $quantidade;
        }
        $trocoDetalhado = separarTroco($trocoCentavos, $disponivel);
        if ($trocoDetalhado === null) {
            throw new Exception("O caixa não possui notas ou moedas suficientes para esse troco.");
        }
        $troco = $trocoCentavos / 100;


    } else {


        /*
            PIX e cartão não possuem
            valor recebido nem troco.
        */

        $valorRecebido = null;
        $troco = null;

    }


/* ==============================
   SALVA A VENDA
============================== */

    $sqlVenda = "
        INSERT INTO vendas
        (
            caixa_id,
            usuario_id,
            total,
            forma_pagamento,
            valor_recebido,
            troco,
            status
        )
        VALUES
        (?, ?, ?, ?, ?, ?, 'finalizada')
    ";


    $stmtVenda =
        $conn->prepare(
            $sqlVenda
        );


    if (!$stmtVenda) {

        throw new Exception(
            "Erro ao preparar venda."
        );

    }


    $stmtVenda->bind_param(
        "iidsdd",
        $caixaId,
        $usuarioId,
        $totalVenda,
        $formaPagamento,
        $valorRecebido,
        $troco
    );


    if (!$stmtVenda->execute()) {

        throw new Exception(
            "Erro ao salvar venda."
        );

    }


    $vendaId =
        $conn->insert_id;

    if ($formaPagamento === "dinheiro") {
        $stmtDetalhe = $conn->prepare("INSERT INTO venda_dinheiro_detalhes (venda_id, tipo, valor_centavos, quantidade) VALUES (?, ?, ?, ?)");
        $stmtSaldo = $conn->prepare("INSERT INTO caixa_denominacoes (caixa_id, valor_centavos, quantidade) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE quantidade = quantidade + VALUES(quantidade)");
        foreach (["recebido" => $recebidoDetalhado, "troco" => $trocoDetalhado] as $tipo => $quantidades) {
            foreach ($quantidades as $valor => $quantidade) {
                if ($quantidade <= 0) continue;
                $stmtDetalhe->bind_param("isii", $vendaId, $tipo, $valor, $quantidade);
                if (!$stmtDetalhe->execute()) throw new Exception("Erro ao registrar dinheiro da venda.");
                $ajuste = $tipo === "recebido" ? $quantidade : -$quantidade;
                $stmtSaldo->bind_param("iii", $caixaId, $valor, $ajuste);
                if (!$stmtSaldo->execute()) throw new Exception("Erro ao atualizar dinheiro do caixa.");
            }
        }
    }


/* ==============================
   SALVA ITENS E BAIXA ESTOQUE
============================== */

    foreach ($itensVenda as $item) {


        $sqlItem = "
            INSERT INTO itens_venda
            (
                venda_id,
                produto_id,
                quantidade,
                preco_unitario,
                subtotal
            )
            VALUES
            (?, ?, ?, ?, ?)
        ";


        $stmtItem =
            $conn->prepare(
                $sqlItem
            );


        if (!$stmtItem) {

            throw new Exception(
                "Erro ao preparar item."
            );

        }


        $stmtItem->bind_param(
            "iiidd",
            $vendaId,
            $item["produto_id"],
            $item["quantidade"],
            $item["preco"],
            $item["subtotal"]
        );


        if (!$stmtItem->execute()) {

            throw new Exception(
                "Erro ao salvar item da venda."
            );

        }


        /*
            Baixa o estoque apenas se
            ainda houver quantidade suficiente.
        */

        $sqlEstoque = "
            UPDATE produtos
            SET estoque = estoque - ?
            WHERE id = ?
            AND estoque >= ?
        ";


        $stmtEstoque =
            $conn->prepare(
                $sqlEstoque
            );


        if (!$stmtEstoque) {

            throw new Exception(
                "Erro ao preparar atualização do estoque."
            );

        }


        $stmtEstoque->bind_param(
            "iii",
            $item["quantidade"],
            $item["produto_id"],
            $item["quantidade"]
        );


        $stmtEstoque->execute();


        if (
            $stmtEstoque->affected_rows !== 1
        ) {

            throw new Exception(
                "Não foi possível atualizar o estoque."
            );

        }

    }


/* ==============================
   CONFIRMA TUDO
============================== */

    $conn->commit();


    header(
        "Location: nova_venda.php?sucesso=1"
    );

    exit;


} catch (Exception $e) {


/* ==============================
   SE ALGO DER ERRADO,
   DESFAZ TUDO
============================== */

    $conn->rollback();


    header(
        "Location: nova_venda.php?erro=1"
    );

    exit;

}
