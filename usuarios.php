<?php

session_start();

if (!isset($_SESSION["usuario_id"])) {
    header("Location: index.php");
    exit;
}

if (($_SESSION["usuario_tipo"] ?? "") !== "admin") {
    header("Location: dashboard.php");
    exit;
}

require_once "config/conexao.php";

if (empty($_SESSION["csrf_token_usuarios"])) {
    $_SESSION["csrf_token_usuarios"] = bin2hex(random_bytes(32));
}

$mensagem = "";
$erro = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $token = $_POST["csrf_token"] ?? "";

    if (
        !is_string($token) ||
        !hash_equals($_SESSION["csrf_token_usuarios"], $token)
    ) {
        $erro = "A página expirou. Atualize e tente novamente.";
    } else {
        $acao = $_POST["acao"] ?? "";

        if ($acao === "cadastrar") {
            $nome = trim($_POST["nome"] ?? "");
            $email = strtolower(trim($_POST["email"] ?? ""));
            $senha = $_POST["senha"] ?? "";
            $tipo = $_POST["tipo"] ?? "atendente";

            if ($nome === "" || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $erro = "Informe um nome e um e-mail válidos.";
            } elseif (strlen($senha) < 6) {
                $erro = "A senha deve ter pelo menos 6 caracteres.";
            } elseif (!in_array($tipo, ["admin", "atendente"], true)) {
                $erro = "Tipo de usuário inválido.";
            } else {
                $senhaHash = password_hash($senha, PASSWORD_DEFAULT);
                $stmt = $conn->prepare(
                    "INSERT INTO usuarios (nome, email, senha, tipo, ativo)
                     VALUES (?, ?, ?, ?, 1)"
                );
                $stmt->bind_param("ssss", $nome, $email, $senhaHash, $tipo);

                if ($stmt->execute()) {
                    $mensagem = "Usuário cadastrado com sucesso.";
                } elseif ($stmt->errno === 1062) {
                    $erro = "Já existe um usuário com esse e-mail.";
                } else {
                    $erro = "Não foi possível cadastrar o usuário.";
                }
            }
        } elseif ($acao === "alternar_status") {
            $id = (int)($_POST["id"] ?? 0);

            if ($id <= 0) {
                $erro = "Usuário inválido.";
            } elseif ($id === (int)$_SESSION["usuario_id"]) {
                $erro = "Você não pode desativar a própria conta.";
            } else {
                $stmt = $conn->prepare(
                    "UPDATE usuarios SET ativo = IF(ativo = 1, 0, 1) WHERE id = ?"
                );
                $stmt->bind_param("i", $id);
                $stmt->execute();

                if ($stmt->affected_rows === 1) {
                    $mensagem = "Status do usuário atualizado.";
                } else {
                    $erro = "Usuário não encontrado.";
                }
            }
        } elseif ($acao === "redefinir_senha") {
            $id = (int)($_POST["id"] ?? 0);
            $novaSenha = $_POST["nova_senha"] ?? "";

            if ($id <= 0 || strlen($novaSenha) < 6) {
                $erro = "A nova senha deve ter pelo menos 6 caracteres.";
            } else {
                $senhaHash = password_hash($novaSenha, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE usuarios SET senha = ? WHERE id = ?");
                $stmt->bind_param("si", $senhaHash, $id);
                $stmt->execute();

                if ($stmt->affected_rows === 1) {
                    $mensagem = "Senha redefinida com sucesso.";
                } else {
                    $erro = "Usuário não encontrado ou senha já utilizada.";
                }
            }
        } else {
            $erro = "Ação inválida.";
        }
    }
}

$usuarios = $conn->query(
    "SELECT id, nome, email, tipo, ativo, criado_em
     FROM usuarios
     ORDER BY ativo DESC, nome ASC"
);

$csrfToken = htmlspecialchars(
    $_SESSION["csrf_token_usuarios"],
    ENT_QUOTES,
    "UTF-8"
);

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Usuários | Cantina do Tio Fabinho</title>
<style>
*{box-sizing:border-box;margin:0;padding:0;font-family:Arial,Helvetica,sans-serif}
body{background:#f5f5f5;color:#222}
.container{max-width:1320px;margin:0 auto;padding:30px}
.voltar{display:inline-block;margin-bottom:20px;color:#555;text-decoration:none}
.topo{margin-bottom:25px}.topo h1{font-size:28px;margin-bottom:6px}.topo p{color:#777}
.grid{display:grid;grid-template-columns:340px minmax(0,1fr);gap:20px;align-items:start}
.card{background:#fff;padding:22px;border-radius:16px;box-shadow:0 5px 20px rgba(0,0,0,.06)}
.card h2{font-size:18px;margin-bottom:18px}
label{display:block;font-size:13px;font-weight:bold;margin:14px 0 7px}
input,select{width:100%;padding:12px;border:1px solid #ddd;border-radius:9px;background:#fff}
button{border:0;border-radius:9px;padding:10px 13px;font-weight:bold;cursor:pointer}
.btn-principal{width:100%;margin-top:18px;background:#181818;color:#fff;padding:13px}
.tabela{max-width:100%;overflow:hidden}table{width:100%;border-collapse:collapse;table-layout:fixed}
th,td{text-align:left;padding:14px 10px;border-bottom:1px solid #eee;font-size:13px;vertical-align:middle}
th{color:#777;font-size:12px}th:nth-child(1){width:17%}th:nth-child(2){width:25%}th:nth-child(3){width:14%}th:nth-child(4){width:10%}th:nth-child(5){width:34%}
td{overflow-wrap:anywhere}.badge{display:inline-block;padding:5px 9px;border-radius:20px;font-size:11px}
.ativo{background:#e8f7ec;color:#247a3d}.inativo{background:#ffe5e5;color:#b42318}
.acoes-inner{display:flex;flex-direction:column;gap:9px;align-items:stretch}.acoes form{margin:0}
.form-senha{display:grid;grid-template-columns:minmax(100px,1fr) auto;gap:7px;align-items:end}
.form-senha label{margin:0 0 5px;font-size:11px;color:#666}.form-senha input{padding:9px;min-width:0}
.btn-status{background:#eee;color:#333;width:100%}.btn-senha{background:#181818;color:#fff;white-space:nowrap}
button:hover{filter:brightness(.94)}input:focus,select:focus{outline:2px solid #aaa;outline-offset:1px}
.mensagem{padding:13px;border-radius:10px;margin-bottom:20px;font-size:13px}
.sucesso{background:#e8f7ec;color:#247a3d}.erro{background:#ffe5e5;color:#b42318}
.proprio{font-size:11px;color:#777}
@media(max-width:1000px){.grid{grid-template-columns:1fr}}
@media(max-width:720px){
    .container{padding:16px}.card{padding:16px}.tabela{background:transparent;box-shadow:none;padding:0}
    .tabela h2{background:#fff;padding:18px;border-radius:14px;margin-bottom:12px;box-shadow:0 5px 20px rgba(0,0,0,.05)}
    table,tbody,tr,td{display:block;width:100%}thead{display:none}table{table-layout:auto}
    tr{background:#fff;border-radius:14px;padding:8px 16px;margin-bottom:12px;box-shadow:0 5px 20px rgba(0,0,0,.05)}
    td{display:flex;justify-content:space-between;gap:16px;padding:11px 0;text-align:right}
    td::before{content:attr(data-label);color:#777;font-size:12px;font-weight:bold;text-align:left}
    td.acoes{display:block;text-align:left;padding-top:14px}.acoes::before{display:none}
    .acoes-inner{gap:10px}.form-senha{grid-template-columns:minmax(0,1fr) auto}
}
</style>
</head>
<body>
<div class="container">
<a href="dashboard.php" class="voltar">← Voltar ao dashboard</a>
<div class="topo">
<h1>Usuários</h1>
<p>Gerencie quem pode acessar o sistema da cantina.</p>
</div>

<?php if ($mensagem !== ""): ?>
<div class="mensagem sucesso"><?php echo htmlspecialchars($mensagem); ?></div>
<?php endif; ?>
<?php if ($erro !== ""): ?>
<div class="mensagem erro"><?php echo htmlspecialchars($erro); ?></div>
<?php endif; ?>

<div class="grid">
<section class="card">
<h2>Cadastrar usuário</h2>
<form method="POST">
<input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
<input type="hidden" name="acao" value="cadastrar">
<label for="nome">Nome</label>
<input id="nome" name="nome" maxlength="100" required>
<label for="email">E-mail</label>
<input id="email" name="email" type="email" maxlength="100" required>
<label for="senha">Senha inicial</label>
<input id="senha" name="senha" type="password" minlength="6" required>
<label for="tipo">Perfil</label>
<select id="tipo" name="tipo" required>
<option value="atendente">Atendente</option>
<option value="admin">Administrador</option>
</select>
<button class="btn-principal" type="submit">Cadastrar usuário</button>
</form>
</section>

<section class="card tabela">
<h2>Usuários cadastrados</h2>
<table>
<thead><tr><th>Nome</th><th>E-mail</th><th>Perfil</th><th>Status</th><th>Ações</th></tr></thead>
<tbody>
<?php while ($usuario = $usuarios->fetch_assoc()): ?>
<tr>
<td data-label="Nome">
<?php echo htmlspecialchars($usuario["nome"]); ?>
<?php if ((int)$usuario["id"] === (int)$_SESSION["usuario_id"]): ?>
<div class="proprio">Sua conta</div>
<?php endif; ?>
</td>
<td data-label="E-mail"><?php echo htmlspecialchars($usuario["email"]); ?></td>
<td data-label="Perfil"><?php echo $usuario["tipo"] === "admin" ? "Administrador" : "Atendente"; ?></td>
<td data-label="Status"><span class="badge <?php echo $usuario["ativo"] ? "ativo" : "inativo"; ?>">
<?php echo $usuario["ativo"] ? "Ativo" : "Inativo"; ?>
</span></td>
<td class="acoes" data-label="Ações">
<div class="acoes-inner">
<?php if ((int)$usuario["id"] !== (int)$_SESSION["usuario_id"]): ?>
<form method="POST" class="form-status">
<input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
<input type="hidden" name="acao" value="alternar_status">
<input type="hidden" name="id" value="<?php echo (int)$usuario["id"]; ?>">
<button class="btn-status" type="submit">
<?php echo $usuario["ativo"] ? "Desativar" : "Ativar"; ?>
</button>
</form>
<?php endif; ?>
<form method="POST" class="form-senha">
<input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
<input type="hidden" name="acao" value="redefinir_senha">
<input type="hidden" name="id" value="<?php echo (int)$usuario["id"]; ?>">
<div><label>Nova senha</label><input name="nova_senha" type="password" minlength="6" placeholder="Mínimo 6 caracteres" required></div>
<button class="btn-senha" type="submit">Redefinir</button>
</form>
</div>
</td>
</tr>
<?php endwhile; ?>
</tbody>
</table>
</section>
</div>
</div>
</body>
</html>
