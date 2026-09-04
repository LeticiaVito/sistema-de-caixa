<?php

function denominacoesDinheiro(): array
{
    return [20000=>'R$ 200',10000=>'R$ 100',5000=>'R$ 50',2000=>'R$ 20',1000=>'R$ 10',500=>'R$ 5',200=>'R$ 2',100=>'R$ 1',50=>'50 centavos',25=>'25 centavos',10=>'10 centavos',5=>'5 centavos',1=>'1 centavo'];
}

function lerQuantidadesDinheiro(array $dados): array
{
    $resultado = [];
    foreach (denominacoesDinheiro() as $valor => $_) {
        $quantidade = filter_var($dados[(string)$valor] ?? 0, FILTER_VALIDATE_INT);
        if ($quantidade === false || $quantidade < 0 || $quantidade > 9999) {
            throw new RuntimeException('Quantidade de notas ou moedas inválida.');
        }
        $resultado[$valor] = $quantidade;
    }
    return $resultado;
}

function totalDinheiro(array $quantidades): int
{
    $total = 0;
    foreach ($quantidades as $valor => $quantidade) {
        $total += (int)$valor * (int)$quantidade;
    }
    return $total;
}

function separarTroco(int $troco, array $disponivel): ?array
{
    $valores = array_keys(denominacoesDinheiro());
    $memo = [];

    $buscar = function (int $indice, int $restante) use (&$buscar, &$memo, $valores, $disponivel): ?array {
        if ($restante === 0) return [];
        if ($indice >= count($valores) || $restante < 0) return null;
        $chave = $indice . ':' . $restante;
        if (array_key_exists($chave, $memo)) return $memo[$chave];
        $valor = $valores[$indice];
        $maximo = min(intdiv($restante, $valor), (int)($disponivel[$valor] ?? 0));
        for ($quantidade = $maximo; $quantidade >= 0; $quantidade--) {
            $continua = $buscar($indice + 1, $restante - ($quantidade * $valor));
            if ($continua !== null) return $memo[$chave] = [$valor => $quantidade] + $continua;
        }
        return $memo[$chave] = null;
    };

    $encontrado = $buscar(0, $troco);
    if ($encontrado === null) return null;
    return $encontrado + array_fill_keys($valores, 0);
}
