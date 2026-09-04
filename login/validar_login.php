<?php

session_start();

require_once __DIR__ . "/../config/conexao.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: ../index.php");
    exit;
}

$email = trim($_POST["email"] ?? "");
$senha = $_POST["senha"] ?? "";

if ($email === "" || $senha === "") {
    header("Location: ../index.php?erro=login");
    exit;
}

$sql = "SELECT id, nome, email, senha, tipo, ativo
        FROM usuarios
        WHERE email = ?
        LIMIT 1";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    die("Erro ao preparar consulta: " . $conn->error);
}

$stmt->bind_param("s", $email);
$stmt->execute();

$resultado = $stmt->get_result();

if ($resultado->num_rows !== 1) {
    header("Location: ../index.php?erro=login");
    exit;
}

$usuario = $resultado->fetch_assoc();

if ((int)$usuario["ativo"] !== 1) {
    header("Location: ../index.php?erro=inativo");
    exit;
}

if (!password_verify($senha, $usuario["senha"])) {
    header("Location: ../index.php?erro=login");
    exit;
}

$_SESSION["usuario_id"] = $usuario["id"];
$_SESSION["usuario_nome"] = $usuario["nome"];
$_SESSION["usuario_email"] = $usuario["email"];
$_SESSION["usuario_tipo"] = $usuario["tipo"];

header("Location: ../dashboard.php");
exit;
