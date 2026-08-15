<?php
require_once __DIR__ . '/../api/db.php';
require_once __DIR__ . '/../api/retencao-servico.php';

try {
    $resultado = armsRetencaoGerarRelatorio($pdo, null, 'SCHEDULED_REPORT');
    $resumo = $resultado['relatorio']['resumo'] ?? [];

    echo 'Relatório de retenção gerado: ' . $resultado['arquivo'] . PHP_EOL;
    echo 'Candidatos para limpeza automática: ' . (int)($resumo['total_limpeza_automatica'] ?? 0) . PHP_EOL;
    echo 'Candidatos para revisão manual: ' . (int)($resumo['total_revisao_manual'] ?? 0) . PHP_EOL;
    echo 'A eliminação deve ser autorizada no painel administrativo antes de apagar dados.' . PHP_EOL;
} catch (Throwable $e) {
    fwrite(STDERR, 'Erro ao gerar relatório de retenção: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
