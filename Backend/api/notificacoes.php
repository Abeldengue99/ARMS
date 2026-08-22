<?php
require_once 'db.php';
require_once 'auth.php';
require_once 'configuracoes-servico.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

function armsNotifBool($valor) {
    return $valor === true || $valor === 1 || $valor === '1' || $valor === 't' || $valor === 'true' || $valor === 'TRUE';
}

function armsNotifStatusTexto($status) {
    $mapa = [
        'DRAFT' => 'Rascunho',
        'SENT' => 'Enviado',
        'RECEIVED' => 'Recebido',
        'CLIENT_RESPONDED' => 'Alteração solicitada',
        'ACCEPTED' => 'Aceite',
        'REJECTED' => 'Rejeitado',
        'CLOSED' => 'Fechado',
        'PENDING' => 'Alteração solicitada',
    ];

    return $mapa[$status] ?? ($status ?: 'Atualizado');
}

function armsNotifTipoVisual($tipo, $payload = []) {
    $decision = strtoupper((string)($payload['decision'] ?? ''));
    $acao = strtolower((string)($payload['acao'] ?? ''));

    if ($tipo === 'RESPONSE' && $decision === 'ACCEPTED') {
        return ['categoria' => 'aceite', 'icone' => 'check', 'etiqueta' => 'Aceite'];
    }

    if ($tipo === 'RESPONSE' && $decision === 'REJECTED') {
        return ['categoria' => 'rejeitado', 'icone' => 'x', 'etiqueta' => 'Rejeitado'];
    }

    if ($tipo === 'RESPONSE' && $decision === 'PENDING') {
        return ['categoria' => 'alteracao', 'icone' => 'edit', 'etiqueta' => 'Alteração'];
    }

    $mapa = [
        'NEW_REQUEST' => ['categoria' => 'pedido', 'icone' => 'file', 'etiqueta' => 'Novo pedido'],
        'STATUS_CHANGE' => ['categoria' => 'estado', 'icone' => 'refresh', 'etiqueta' => 'Estado'],
        'DEADLINE' => ['categoria' => (($payload['automacao_tipo'] ?? '') === 'deadline_warning' ? 'prazo' : 'urgente'), 'icone' => 'clock', 'etiqueta' => (($payload['automacao_tipo'] ?? '') === 'deadline_warning' ? 'Prazo' : 'Urgente')],
        'COMMENT' => ['categoria' => 'comentario', 'icone' => $acao === 'edited' ? 'edit' : 'message', 'etiqueta' => $acao === 'edited' ? 'Comentário editado' : 'Comentário'],
        'ATTACHMENT' => ['categoria' => 'documento', 'icone' => $acao === 'downloaded' ? 'download' : 'paperclip', 'etiqueta' => $acao === 'updated' ? 'Documento atualizado' : ($acao === 'downloaded' ? 'Documento baixado' : 'Documento')],
        'SYSTEM' => ['categoria' => 'sistema', 'icone' => 'bell', 'etiqueta' => 'Sistema'],
    ];

    return $mapa[$tipo] ?? ['categoria' => 'sistema', 'icone' => 'bell', 'etiqueta' => 'Sistema'];
}

function armsNotifMensagem(array $n, array $payload) {
    $ref = $n['pedido_ref'] ?: ($payload['pedido_ref'] ?? '');
    $tipo = $n['type'];

    switch ($tipo) {
        case 'NEW_REQUEST':
            $criadorNome = $payload['created_by_name'] ?? 'Alguém';
            return [
                'titulo' => $ref ? "Novo pedido $ref" : 'Novo pedido recebido',
                'descricao' => "O utilizador $criadorNome enviou um novo pedido.",
            ];

        case 'STATUS_CHANGE':
            $de = armsNotifStatusTexto($payload['from_status'] ?? '');
            $para = armsNotifStatusTexto($payload['to_status'] ?? '');
            $titulo = $ref ? "Pedido $ref atualizado" : 'Pedido atualizado';
            $descricao = !empty($payload['from_status'])
                ? "O estado mudou de $de para $para."
                : "O estado atual é $para.";
            return compact('titulo', 'descricao');

        case 'DEADLINE':
            $deadline = $payload['deadline'] ?? '';
            if (($payload['automacao_tipo'] ?? '') === 'deadline_warning') {
                return [
                    'titulo' => $ref ? "Prazo a terminar no pedido $ref" : 'Prazo a terminar',
                    'descricao' => $deadline
                        ? "A data limite termina em $deadline. Acompanhe o pedido antes do vencimento."
                        : 'A data limite deste pedido está próxima.',
                ];
            }

            return [
                'titulo' => $ref ? "Prazo vencido no pedido $ref" : 'Pedido urgente',
                'descricao' => $deadline
                    ? "A data limite terminou em $deadline. É necessária resposta urgente."
                    : 'A data limite terminou. É necessária resposta urgente.',
            ];

        case 'RESPONSE':
            $decision = strtoupper((string)($payload['decision'] ?? ''));
            if ($decision === 'ACCEPTED') {
                return [
                    'titulo' => $ref ? "Pedido $ref aceite" : 'Pedido aceite',
                    'descricao' => 'A resposta formal foi registada como aceite.',
                ];
            }
            if ($decision === 'REJECTED') {
                return [
                    'titulo' => $ref ? "Pedido $ref rejeitado" : 'Pedido rejeitado',
                    'descricao' => 'A resposta formal foi registada como rejeitada.',
                ];
            }
            if ($decision === 'PENDING') {
                return [
                    'titulo' => $ref ? "Alteração solicitada no pedido $ref" : 'Alteração solicitada',
                    'descricao' => 'Foi solicitada uma alteração antes da decisão final.',
                ];
            }
            return [
                'titulo' => $ref ? "Resposta no pedido $ref" : 'Resposta recebida',
                'descricao' => 'Foi registada uma resposta formal.',
            ];

        case 'COMMENT':
            if (strtolower((string)($payload['acao'] ?? '')) === 'edited') {
                return [
                    'titulo' => $ref ? "Comentário editado no pedido $ref" : 'Comentário editado',
                    'descricao' => 'Uma mensagem foi atualizada e a versão anterior ficou guardada para auditoria.',
                ];
            }

            return [
                'titulo' => $ref ? "Novo comentário no pedido $ref" : 'Novo comentário',
                'descricao' => 'Há uma nova mensagem no histórico do pedido.',
            ];

        case 'ATTACHMENT':
            $fileName = trim((string)($payload['file_name'] ?? ''));
            if (strtolower((string)($payload['acao'] ?? '')) === 'updated') {
                return [
                    'titulo' => $ref ? "Documento atualizado no pedido $ref" : 'Documento atualizado',
                    'descricao' => $fileName
                        ? "O ficheiro $fileName foi substituído e a versão anterior ficou guardada para auditoria."
                        : 'Um ficheiro foi substituído e a versão anterior ficou guardada para auditoria.',
                ];
            }

            if (strtolower((string)($payload['acao'] ?? '')) === 'downloaded') {
                $nomeBaixador = $payload['downloaded_by_name'] ?? 'outro utilizador';
                return [
                    'titulo' => $ref ? "Documento baixado no pedido $ref" : 'Documento baixado',
                    'descricao' => $fileName
                        ? "O ficheiro $fileName foi baixado por $nomeBaixador."
                        : "Um ficheiro que anexaste foi baixado por $nomeBaixador.",
                ];
            }

            return [
                'titulo' => $ref ? "Novo documento no pedido $ref" : 'Novo documento',
                'descricao' => $fileName
                    ? "O ficheiro $fileName foi enviado para o pedido."
                    : 'Foi enviado um novo documento para o pedido.',
            ];

        default:
            $mensagem = trim((string)($payload['message'] ?? 'Notificação do sistema'));
            return [
                'titulo' => $ref ? "$mensagem" : 'Notificação do sistema',
                'descricao' => $ref ? "Relacionado com o pedido $ref." : $mensagem,
            ];
    }
}

function armsNotifPreparar(array $n) {
    $payload = json_decode($n['payload'] ?? '{}', true);
    if (!is_array($payload)) {
        $payload = [];
    }

    $visual = armsNotifTipoVisual($n['type'], $payload);
    $texto = armsNotifMensagem($n, $payload);
    $ref = $n['pedido_ref'] ?: ($payload['pedido_ref'] ?? '');

    $n['is_read'] = armsNotifBool($n['is_read']);
    $n['payload'] = $payload;
    $n['titulo'] = $texto['titulo'];
    $n['message'] = $texto['titulo'];
    $n['descricao'] = $texto['descricao'];
    $n['categoria'] = $visual['categoria'];
    $n['icone'] = $visual['icone'];
    $n['etiqueta'] = $visual['etiqueta'];
    $n['pedido_ref'] = $ref;
    $n['target_url'] = $ref ? 'pedido-detalhe.html?ref=' . rawurlencode($ref) : null;

    return $n;
}

$acao = $_GET['acao'] ?? 'listar';

try {
    armsAuthIniciarSessao();

    if (empty($_SESSION['arms_logado']) || empty($_SESSION['arms_user_id'])) {
        echo json_encode(['sucesso' => false, 'erro' => 'Não autenticado.', 'nao_lidas' => 0]);
        exit;
    }

    $userId = $_SESSION['arms_user_id'];
    $userType = $_SESSION['arms_user_type'] ?? 'AKSANTI';
    $clientId = $_SESSION['arms_client_id'] ?? null;

    if ($userType === 'CLIENT' && $clientId) {
        $horasAlertaDeadline = armsConfiguracaoInteiro($pdo, 'automation_deadline_warning_hours', 72, 1, 168);

        $stmtAlertaDeadline = $pdo->prepare("
            INSERT INTO arms.notification (recipient_id, request_id, type, channel, payload)
            SELECT
                :uid::uuid,
                r.id,
                'DEADLINE',
                'IN_APP',
                jsonb_build_object(
                    'pedido_ref', r.reference,
                    'message', 'Prazo de pedido a terminar',
                    'deadline', to_char(r.deadline_at, 'DD/MM/YYYY HH24:MI'),
                    'automacao_tipo', 'deadline_warning'
                )
            FROM arms.request r
            WHERE r.client_id = :client_id::uuid
              AND r.status IN ('SENT', 'RECEIVED')
              AND r.deadline_at >= NOW()
              AND r.deadline_at <= NOW() + ((:horas)::int * INTERVAL '1 hour')
              AND NOT EXISTS (
                  SELECT 1
                  FROM arms.notification n
                  WHERE n.recipient_id = :uid::uuid
                    AND n.request_id = r.id
                    AND n.type = 'DEADLINE'
                    AND n.channel = 'IN_APP'
                    AND COALESCE(n.payload->>'automacao_tipo', '') = 'deadline_warning'
              )
        ");
        $stmtAlertaDeadline->execute([
            ':uid' => $userId,
            ':client_id' => $clientId,
            ':horas' => $horasAlertaDeadline
        ]);

        $stmtUrgentes = $pdo->prepare("
            INSERT INTO arms.notification (recipient_id, request_id, type, channel, payload)
            SELECT
                :uid::uuid,
                r.id,
                'DEADLINE',
                'IN_APP',
                jsonb_build_object(
                    'pedido_ref', r.reference,
                    'message', 'Pedido urgente para responder',
                    'deadline', to_char(r.deadline_at, 'DD/MM/YYYY HH24:MI'),
                    'automacao_tipo', 'deadline_overdue'
                )
            FROM arms.request r
            WHERE r.client_id = :client_id::uuid
              AND r.status IN ('SENT', 'RECEIVED')
              AND r.deadline_at < NOW()
              AND NOT EXISTS (
                  SELECT 1
                  FROM arms.notification n
                  WHERE n.recipient_id = :uid::uuid
                    AND n.request_id = r.id
                    AND n.type = 'DEADLINE'
                    AND n.channel = 'IN_APP'
                    AND COALESCE(n.payload->>'automacao_tipo', 'deadline_overdue') = 'deadline_overdue'
              )
        ");
        $stmtUrgentes->execute([
            ':uid' => $userId,
            ':client_id' => $clientId
        ]);
    }

    // Limpeza de notificações antigas para otimização de espaço
    // As notificações lidas ou com mais de 30 dias são eliminadas permanentemente
    try {
        $stmtLimpeza = $pdo->prepare("
            DELETE FROM arms.notification 
            WHERE recipient_id = :uid 
              AND (is_read = TRUE OR created_at < NOW() - INTERVAL '30 days')
        ");
        $stmtLimpeza->execute([':uid' => $userId]);
    } catch (Exception $e) {
        // Ignora erro de limpeza para não bloquear a funcionalidade principal
    }

    switch ($acao) {
        case 'contar':
            $stmt = $pdo->prepare("
                SELECT
                    COUNT(*)::int AS total,
                    MAX(created_at) AS ultima_criada
                FROM arms.notification
                WHERE recipient_id = :uid
                  AND is_read = FALSE
                  AND channel = 'IN_APP'
            ");
            $stmt->execute([':uid' => $userId]);
            $row = $stmt->fetch() ?: ['total' => 0, 'ultima_criada' => null];

            echo json_encode([
                'sucesso' => true,
                'nao_lidas' => (int)$row['total'],
                'ultima_criada' => $row['ultima_criada'],
            ]);
            break;

        case 'marcar_lida':
            $input = json_decode(file_get_contents('php://input'), true) ?: [];
            $notifId = $input['id'] ?? null;
            $destino = null;

            if ($notifId) {
                $stmt = $pdo->prepare("
                    UPDATE arms.notification
                    SET is_read = TRUE, read_at = NOW()
                    WHERE id = :id AND recipient_id = :uid
                    RETURNING id, request_id
                ");
                $stmt->execute([':id' => $notifId, ':uid' => $userId]);
                $updated = $stmt->fetch();

                if ($updated && !empty($updated['request_id'])) {
                    $stmtReq = $pdo->prepare("SELECT reference FROM arms.request WHERE id = :id");
                    $stmtReq->execute([':id' => $updated['request_id']]);
                    $ref = $stmtReq->fetchColumn();
                    $destino = $ref ? 'pedido-detalhe.html?ref=' . rawurlencode($ref) : null;
                }
            }

            echo json_encode(['sucesso' => true, 'target_url' => $destino]);
            break;

        case 'marcar_todas':
            $stmt = $pdo->prepare("
                UPDATE arms.notification
                SET is_read = TRUE, read_at = NOW()
                WHERE recipient_id = :uid AND is_read = FALSE
            ");
            $stmt->execute([':uid' => $userId]);
            echo json_encode(['sucesso' => true, 'marcadas' => $stmt->rowCount()]);
            break;

        case 'listar':
        default:
            $stmt = $pdo->prepare("
                SELECT
                    n.id,
                    n.type,
                    n.is_read,
                    n.payload,
                    n.request_id,
                    to_char(n.created_at, 'DD/MM/YYYY, HH24:MI') as data_formatada,
                    n.created_at,
                    r.reference as pedido_ref
                FROM arms.notification n
                LEFT JOIN arms.request r ON n.request_id = r.id
                WHERE n.recipient_id = :uid AND n.channel = 'IN_APP'
                ORDER BY n.created_at DESC
                LIMIT 50
            ");
            $stmt->execute([':uid' => $userId]);
            $notificacoes = array_map('armsNotifPreparar', $stmt->fetchAll());

            $stmtCount = $pdo->prepare("
                SELECT COUNT(*) FROM arms.notification
                WHERE recipient_id = :uid AND is_read = FALSE AND channel = 'IN_APP'
            ");
            $stmtCount->execute([':uid' => $userId]);

            echo json_encode([
                'sucesso' => true,
                'dados' => $notificacoes,
                'nao_lidas' => (int)$stmtCount->fetchColumn(),
                'total' => count($notificacoes),
            ], JSON_UNESCAPED_UNICODE);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['sucesso' => false, 'erro' => 'Erro interno ao carregar notificações.']);
    error_log('[ARMS] Erro em notificacoes.php: ' . $e->getMessage());
}
?>
