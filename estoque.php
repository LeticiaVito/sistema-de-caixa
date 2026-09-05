<?php

session_start();

if (!isset($_SESSION["usuario_id"])) {
    header("Location: index.php");
    exit;
}

require_once "config/conexao.php";

if (empty($_SESSION["csrf_token_estoque"])) {
    $_SESSION["csrf_token_estoque"] = bin2hex(random_bytes(32));
}

$mensagem = "";
$erro = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $token = $_POST["csrf_token"] ?? "";
    $produtoId = (int)($_POST["produto_id"] ?? 0);
    $tipo = $_POST["tipo"] ?? "";
    $quantidade = filter_var($_POST["quantidade"] ?? null, FILTER_VALIDATE_INT);
    $motivo = trim($_POST["motivo"] ?? "");
    $tipos = ["entrada", "perda", "consumo", "ajuste"];

    if (!is_string($token) || !hash_equals($_SESSION["csrf_token_estoque"], $token)) {
        $erro = "A página expirou. Atualize e tente novamente.";
    } elseif ($produtoId <= 0 || !in_array($tipo, $tipos, true) || $quantidade === false || $quantidade < 0 || $motivo === "") {
        $erro = "Preencha corretamente todos os campos.";
    } elseif ($tipo !== "ajuste" && $quantidade === 0) {
        $erro = "A quantidade deve ser maior que zero.";
    } else {
        $conn->begin_transaction();
        try {
            $stmtProduto = $conn->prepare("SELECT estoque FROM produtos WHERE id = ? AND ativo = 1 FOR UPDATE");
            $stmtProduto->bind_param("i", $produtoId);
            $stmtProduto->execute();
            $produto = $stmtProduto->get_result()->fetch_assoc();
            if (!$produto) throw new RuntimeException("Produto não encontrado ou arquivado.");

            $estoqueAnterior = (int)$produto["estoque"];
            if ($tipo === "entrada") {
                $estoqueNovo = $estoqueAnterior + $quantidade;
            } elseif ($tipo === "ajuste") {
                $estoqueNovo = $quantidade;
            } else {
                if ($quantidade > $estoqueAnterior) throw new RuntimeException("A retirada é maior que o estoque disponível.");
                $estoqueNovo = $estoqueAnterior - $quantidade;
            }

            $stmtAtualizar = $conn->prepare("UPDATE produtos SET estoque = ? WHERE id = ?");
            $stmtAtualizar->bind_param("ii", $estoqueNovo, $produtoId);
            if (!$stmtAtualizar->execute()) throw new RuntimeException("Não foi possível atualizar o estoque.");

            $usuarioId = (int)$_SESSION["usuario_id"];
            $stmtHistorico = $conn->prepare("INSERT INTO estoque_movimentacoes (produto_id,usuario_id,tipo,quantidade,estoque_anterior,estoque_novo,motivo) VALUES (?,?,?,?,?,?,?)");
            $stmtHistorico->bind_param("iisiiis", $produtoId, $usuarioId, $tipo, $quantidade, $estoqueAnterior, $estoqueNovo, $motivo);
            if (!$stmtHistorico->execute()) throw new RuntimeException("Não foi possível registrar o histórico.");

            $conn->commit();
            $mensagem = "Movimentação registrada com sucesso.";
        } catch (Throwable $e) {
            $conn->rollback();
            $erro = $e->getMessage();
        }
    }
}

$produtos = $conn->query("SELECT id,nome,estoque FROM produtos WHERE ativo = 1 ORDER BY nome");
$historico = $conn->query("SELECT em.*, p.nome AS produto_nome, u.nome AS usuario_nome FROM estoque_movimentacoes em INNER JOIN produtos p ON p.id=em.produto_id INNER JOIN usuarios u ON u.id=em.usuario_id ORDER BY em.id DESC LIMIT 100");
$alertas = $conn->query("SELECT id,nome,estoque,estoque_minimo,validade FROM produtos WHERE ativo=1 AND (estoque<=estoque_minimo OR (validade IS NOT NULL AND validade<=DATE_ADD(CURDATE(),INTERVAL 30 DAY))) ORDER BY estoque ASC, validade ASC");

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Estoque | Cantina do Tio Fabinho</title>
<style>
*{box-sizing:border-box;margin:0;padding:0;font-family:Arial,Helvetica,sans-serif}body{background:#f5f5f5;color:#222}.container{max-width:1250px;margin:auto;padding:30px}.voltar{display:inline-block;margin-bottom:20px;color:#555;text-decoration:none}.topo{margin-bottom:24px}.topo h1{font-size:28px;margin-bottom:5px}.topo p{color:#777}.grid{display:grid;grid-template-columns:360px minmax(0,1fr);gap:20px;align-items:start}.card{background:#fff;border-radius:16px;padding:22px;box-shadow:0 5px 20px rgba(0,0,0,.06);margin-bottom:20px}.card h2{font-size:18px;margin-bottom:16px}label{display:block;font-size:12px;font-weight:bold;margin:13px 0 6px}input,select{width:100%;padding:12px;border:1px solid #ddd;border-radius:9px;background:#fff}button{width:100%;margin-top:17px;padding:13px;border:0;border-radius:9px;background:#181818;color:#fff;font-weight:bold;cursor:pointer}.ajuda{font-size:11px;color:#777;margin-top:7px}.mensagem{padding:13px;border-radius:10px;margin-bottom:18px}.sucesso{background:#e8f7ec;color:#247a3d}.erro{background:#ffe5e5;color:#b42318}.alerta{padding:12px;background:#fff3d6;color:#8a6100;border-radius:10px;margin-bottom:9px;font-size:13px}.alerta strong{display:block;margin-bottom:3px}.tabela{overflow-x:auto}table{width:100%;border-collapse:collapse;min-width:760px}th,td{text-align:left;padding:12px;border-bottom:1px solid #eee;font-size:12px}th{color:#777}.badge{padding:5px 8px;border-radius:15px;background:#eee}.entrada{background:#e8f7ec;color:#247a3d}.perda{background:#ffe5e5;color:#b42318}.consumo{background:#fff3d6;color:#8a6100}.ajuste{background:#e8efff;color:#2856a5}@media(max-width:900px){.grid{grid-template-columns:1fr}}@media(max-width:600px){.container{padding:16px}.card{padding:16px}}
</style>
</head>
<body><div class="container">
<a class="voltar" href="dashboard.php">← Voltar ao dashboard</a>
<div class="topo"><h1>Controle de estoque</h1><p>Registre entradas, perdas, consumo e ajustes de inventário.</p></div>
<?php if($mensagem):?><div class="mensagem sucesso"><?php echo htmlspecialchars($mensagem);?></div><?php endif;?>
<?php if($erro):?><div class="mensagem erro"><?php echo htmlspecialchars($erro);?></div><?php endif;?>
<div class="grid"><div>
<section class="card"><h2>Nova movimentação</h2><form method="POST">
<input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token_estoque'],ENT_QUOTES,'UTF-8');?>">
<label>Produto</label><select name="produto_id" required><option value="">Selecione</option><?php while($p=$produtos->fetch_assoc()):?><option value="<?php echo $p['id'];?>"><?php echo htmlspecialchars($p['nome']);?> — <?php echo $p['estoque'];?> un.</option><?php endwhile;?></select>
<label>Tipo</label><select name="tipo" id="tipoMovimento" onchange="ajustarAjuda()" required><option value="entrada">Entrada de mercadoria</option><option value="perda">Perda ou vencimento</option><option value="consumo">Consumo interno</option><option value="ajuste">Ajuste de inventário</option></select>
<label id="rotuloQuantidade">Quantidade</label><input type="number" name="quantidade" min="0" required><div class="ajuda" id="ajudaQuantidade">Quantidade que será adicionada ao estoque.</div>
<label>Motivo ou observação</label><input name="motivo" maxlength="255" placeholder="Ex.: Reposição do fornecedor" required>
<button type="submit">Registrar movimentação</button></form></section>
<section class="card"><h2>Alertas</h2><?php if($alertas->num_rows):while($a=$alertas->fetch_assoc()):?><div class="alerta"><strong><?php echo htmlspecialchars($a['nome']);?></strong><?php if((int)$a['estoque']<=(int)$a['estoque_minimo']):?>Estoque: <?php echo $a['estoque'];?> (mínimo <?php echo $a['estoque_minimo'];?>).<?php endif;?> <?php if($a['validade']):?>Validade: <?php echo date('d/m/Y',strtotime($a['validade']));?>.<?php endif;?></div><?php endwhile;else:?><p class="ajuda">Nenhum alerta no momento.</p><?php endif;?></section>
</div><section class="card"><h2>Histórico recente</h2><div class="tabela"><table><thead><tr><th>Data</th><th>Produto</th><th>Tipo</th><th>Quantidade</th><th>Antes → Depois</th><th>Responsável</th><th>Motivo</th></tr></thead><tbody><?php while($m=$historico->fetch_assoc()):?><tr><td><?php echo date('d/m/Y H:i',strtotime($m['criado_em']));?></td><td><?php echo htmlspecialchars($m['produto_nome']);?></td><td><span class="badge <?php echo $m['tipo'];?>"><?php echo ucfirst($m['tipo']);?></span></td><td><?php echo $m['quantidade'];?></td><td><?php echo $m['estoque_anterior'];?> → <?php echo $m['estoque_novo'];?></td><td><?php echo htmlspecialchars($m['usuario_nome']);?></td><td><?php echo htmlspecialchars($m['motivo']);?></td></tr><?php endwhile;?></tbody></table></div></section></div></div>
<script>function ajustarAjuda(){const t=document.getElementById('tipoMovimento').value;document.getElementById('rotuloQuantidade').textContent=t==='ajuste'?'Nova quantidade em estoque':'Quantidade';document.getElementById('ajudaQuantidade').textContent=t==='entrada'?'Quantidade que será adicionada ao estoque.':t==='ajuste'?'Informe a quantidade total contada no inventário.':'Quantidade que será retirada do estoque.'}ajustarAjuda();</script>
</body></html>
