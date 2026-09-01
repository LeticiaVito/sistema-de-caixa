<?php

session_start();

if (!isset($_SESSION["usuario_id"])) {
    header("Location: index.php");
    exit;
}

require_once "config/conexao.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: caixa.php");
    exit;
}

$valorInicial = (float)($_POST["valor_inicial"] ?? 0);
$usuarioId = (int)$_SESSION["usuario_id"];


/* Não deixa abrir dois caixas ao mesmo tempo */
$sqlVerifica = "
    SELECT id
    FROM caixas
    WHERE status = 'aberto'
    LIMIT 1
";

$resultado = $conn->query($sqlVerifica);

if ($resultado && $resultado->num_rows > 0) {
    header("Location: caixa.php?erro=1");
    exit;
}


/* Abrir caixa */
$sql = "
    INSERT INTO caixas
    (
        usuario_id,
        valor_inicial,
        status
    )
    VALUES
    (?, ?, 'aberto')
";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    die("Erro ao preparar abertura do caixa: " . $conn->error);
}

$stmt->bind_param(
    "id",
    $usuarioId,
    $valorInicial
);

if ($stmt->execute()) {

    header("Location: caixa.php?aberto=1");
    exit;

} else {

    die("Erro ao abrir caixa: " . $stmt->error);

}