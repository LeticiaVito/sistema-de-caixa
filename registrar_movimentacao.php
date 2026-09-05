<?php

session_start();

if (!isset($_SESSION["usuario_id"])) {
    header("Location: index.php");
    exit;
}

require_once "config/conexao.php";
require_once "config/dinheiro.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {

    header("Location: caixa.php");
    exit;

}

$tipo =
    $_POST["tipo"] ?? "";

$descricao =
    trim($_POST["descricao"] ?? "");

$dinheiroDados = json_decode($_POST["dinheiro_detalhes"] ?? "", true);

try {
    if (!is_array($dinheiroDados)) throw new RuntimeException("Informe as notas e moedas.");
    $quantidadesDinheiro = lerQuantidadesDinheiro($dinheiroDados);
    $valorCentavosTotal = totalDinheiro($quantidadesDinheiro);
} catch (RuntimeException $e) {
    header("Location: caixa.php?erro=dinheiro");
    exit;
}

$valor = $valorCentavosTotal / 100;

$usuarioId =
    (int)$_SESSION["usuario_id"];


$tiposPermitidos = [
    "entrada",
    "saida",
    "sangria"
];


if (
    !in_array(
        $tipo,
        $tiposPermitidos,
        true
    )
    ||
    $descricao === ""
    ||
    $valor <= 0
) {

    header(
        "Location: caixa.php?erro=1"
    );

    exit;

}


/* caixa aberto */

$conn->begin_transaction();

$sqlCaixa = "
    SELECT id
    FROM caixas
    WHERE status = 'aberto'
    ORDER BY id DESC
    LIMIT 1
    FOR UPDATE
";

$resultadoCaixa =
    $conn->query($sqlCaixa);


if (
    !$resultadoCaixa ||
    $resultadoCaixa->num_rows !== 1
) {

    header(
        "Location: caixa.php?erro=1"
    );

    exit;

}


$caixa =
    $resultadoCaixa->fetch_assoc();

$caixaId =
    (int)$caixa["id"];

if ($tipo !== "entrada") {
    $stmtDisponivel = $conn->prepare("SELECT quantidade FROM caixa_denominacoes WHERE caixa_id = ? AND valor_centavos = ? FOR UPDATE");
    foreach ($quantidadesDinheiro as $valorCentavos => $quantidade) {
        if ($quantidade <= 0) continue;
        $stmtDisponivel->bind_param("ii", $caixaId, $valorCentavos);
        $stmtDisponivel->execute();
        $linha = $stmtDisponivel->get_result()->fetch_assoc();
        if (!$linha || (int)$linha["quantidade"] < $quantidade) {
            $conn->rollback();
            header("Location: caixa.php?erro=saldo_notas");
            exit;
        }
    }
}


$sql = "
    INSERT INTO movimentacoes_caixa
    (
        caixa_id,
        usuario_id,
        tipo,
        descricao,
        valor
    )
    VALUES
    (?, ?, ?, ?, ?)
";

$stmt =
    $conn->prepare($sql);

$stmt->bind_param(
    "iissd",
    $caixaId,
    $usuarioId,
    $tipo,
    $descricao,
    $valor
);


if ($stmt->execute()) {

    $movimentacaoId = $conn->insert_id;
    $stmtDetalhe = $conn->prepare("INSERT INTO movimentacao_dinheiro_detalhes (movimentacao_id, valor_centavos, quantidade) VALUES (?, ?, ?)");
    $stmtSaldo = $conn->prepare("INSERT INTO caixa_denominacoes (caixa_id, valor_centavos, quantidade) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE quantidade = quantidade + VALUES(quantidade)");

    foreach ($quantidadesDinheiro as $valorCentavos => $quantidade) {
        if ($quantidade <= 0) continue;
        $stmtDetalhe->bind_param("iii", $movimentacaoId, $valorCentavos, $quantidade);
        if (!$stmtDetalhe->execute()) {
            $conn->rollback();
            header("Location: caixa.php?erro=1");
            exit;
        }
        $ajuste = $tipo === "entrada" ? $quantidade : -$quantidade;
        $stmtSaldo->bind_param("iii", $caixaId, $valorCentavos, $ajuste);
        if (!$stmtSaldo->execute()) {
            $conn->rollback();
            header("Location: caixa.php?erro=1");
            exit;
        }
    }

    $conn->commit();

    header(
        "Location: caixa.php?movimentacao=1"
    );

}else{

    $conn->rollback();

    header(
        "Location: caixa.php?erro=1"
    );

}

exit;
