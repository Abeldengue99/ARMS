<?php
require_once 'db.php';
require_once 'auth.php';
require_once 'retencao-servico.php';
require_once 'permissoes.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

try {
    armsExigirPermissao($pdo, 'retencao.gerir', 'Não tem permissão para gerir retenção e auditoria.');

    $acao = $_GET['acao'] ?? 'resumo';
    $executedBy = $_SESSION['arms_user_id'] ?? null;

    if ($acao === 'relatorio') {
        $resultado = armsRetencaoGerarRelatorio($pdo, $executedBy, 'REPORT');
        echo json_encode([
            'sucesso' => true,
            'mensagem' => 'Relatório de retenção gerado com sucesso.',
            'arquivo' => $resultado['arquivo'],
            'dados' => $resultado['relatorio'],
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($acao === 'limpar') {
        $entrada = json_decode(file_get_contents('php://input'), true) ?: [];
        $confirmacao = $entrada['confirmacao'] ?? '';

        if ($confirmacao !== 'LIMPAR_DADOS_EXPIRADOS') {
            echo json_encode([
                'sucesso' => false,
                'erro' => 'Confirmação inválida. Gere o relatório e confirme a limpeza no painel administrativo.',
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $resultado = armsRetencaoExecutarLimpeza($pdo, $executedBy);
        echo json_encode([
            'sucesso' => true,
            'mensagem' => $resultado['mensagem'],
            'arquivo' => $resultado['relatorio_previo'],
            'apagados' => $resultado['apagados'],
            'dados' => armsRetencaoResumo($pdo),
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($acao === 'salvar') {
        $entrada = json_decode(file_get_contents('php://input'), true) ?: [];
        $pdo->beginTransaction();
        $alteracoes = armsRetencaoAtualizarConfiguracoes($pdo, $entrada, $executedBy);
        $dadosAtualizados = armsRetencaoResumo($pdo);
        $pdo->commit();

        echo json_encode([
            'sucesso' => true,
            'mensagem' => 'Definições atualizadas com sucesso.',
            'alteracoes' => $alteracoes,
            'dados' => $dadosAtualizados,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    echo json_encode([
        'sucesso' => true,
        'dados' => armsRetencaoResumo($pdo),
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    http_response_code($e instanceof InvalidArgumentException ? 400 : 500);
    echo json_encode([
        'sucesso' => false,
        'erro' => 'Erro na política de retenção: ' . $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}
