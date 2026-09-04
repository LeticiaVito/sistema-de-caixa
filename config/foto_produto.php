<?php

const FOTO_PRODUTO_TAMANHO_MAXIMO = 2 * 1024 * 1024;
const FOTO_PRODUTO_DIRETORIO = __DIR__ . "/../uploads/produtos";

function salvarFotoProduto(array $arquivo): ?string
{
    $erroUpload = $arquivo["error"] ?? UPLOAD_ERR_NO_FILE;

    if ($erroUpload === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if ($erroUpload !== UPLOAD_ERR_OK) {
        throw new RuntimeException("Não foi possível enviar a foto.");
    }

    if (($arquivo["size"] ?? 0) <= 0 || $arquivo["size"] > FOTO_PRODUTO_TAMANHO_MAXIMO) {
        throw new RuntimeException("A foto deve ter no máximo 2 MB.");
    }

    $temporario = $arquivo["tmp_name"] ?? "";

    if (!is_uploaded_file($temporario)) {
        throw new RuntimeException("Arquivo de foto inválido.");
    }

    $tiposPermitidos = [
        "image/jpeg" => "jpg",
        "image/png" => "png",
        "image/webp" => "webp"
    ];

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($temporario);

    if (!isset($tiposPermitidos[$mime]) || @getimagesize($temporario) === false) {
        throw new RuntimeException("Use uma imagem JPG, PNG ou WebP válida.");
    }

    if (!is_dir(FOTO_PRODUTO_DIRETORIO) && !mkdir(FOTO_PRODUTO_DIRETORIO, 0755, true)) {
        throw new RuntimeException("Não foi possível preparar a pasta de fotos.");
    }

    $nome = bin2hex(random_bytes(16)) . "." . $tiposPermitidos[$mime];
    $destino = FOTO_PRODUTO_DIRETORIO . DIRECTORY_SEPARATOR . $nome;

    if (!move_uploaded_file($temporario, $destino)) {
        throw new RuntimeException("Não foi possível salvar a foto.");
    }

    return "uploads/produtos/" . $nome;
}

function excluirFotoProduto(?string $caminho): void
{
    if (!$caminho || !preg_match('#^uploads/produtos/[a-f0-9]{32}\.(jpg|png|webp)$#', $caminho)) {
        return;
    }

    $arquivo = FOTO_PRODUTO_DIRETORIO . DIRECTORY_SEPARATOR . basename($caminho);

    if (is_file($arquivo)) {
        unlink($arquivo);
    }
}
