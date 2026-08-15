<?php
require_once 'db.php';
require_once 'auth.php';
require_once 'acesso-pedidos.php';

function armsCalendarioStatusTexto($status) {
    $mapa = [
        'DRAFT' => 'Rascunho',
        'SENT' => 'Enviado',
        'RECEIVED' => 'Recebido',
        'CLIENT_RESPONDED' => 'Alteração solicitada',
        'ACCEPTED' => 'Aceite',
        'REJECTED' => 'Rejeitado',
        'CLOSED' => 'Fechado',
    ];

    return $mapa[$status] ?? ($status ?: 'Atualizado');
}

function armsCalendarioBool($valor) {
    return $valor === true || $valor === 1 || $valor === '1' || $valor === 't' || $valor === 'true' || $valor === 'TRUE';
}

function armsCalendarioDataValida($valor) {
    return is_string($valor) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor);
}

function armsCalendarioNoIntervalo($dataHora, $inicio, $fim) {
    if (!$dataHora) return false;

    $ts = strtotime($dataHora);
    return $ts !== false && $ts >= strtotime($inicio . ' 00:00:00') && $ts <= strtotime($fim . ' 23:59:59');
}

function armsCalendarioEvento($id, $tipo, $categoria, $titulo, $descricao, $dataHora, array $pedido, $prioridade = 2) {
    $dataHora = str_replace(' ', 'T', (string)$dataHora);

    return [
        'id' => $id,
        'tipo' => $tipo,
        'categoria' => $categoria,
        'titulo' => $titulo,
        'descricao' => $descricao,
        'inicio' => $dataHora,
        'data' => substr($dataHora, 0, 10),
        'hora' => substr($dataHora, 11, 5),
        'referencia' => $pedido['reference'] ?? '',
        'pedido_titulo' => $pedido['title'] ?? '',
        'cliente' => $pedido['client_name'] ?? '',
        'area' => $pedido['area_name'] ?? '',
        'status' => $pedido['status'] ?? '',
        'url' => !empty($pedido['reference']) ? 'pedido-detalhe.html?ref=' . rawurlencode($pedido['reference']) : null,
        'prioridade' => $prioridade,
    ];
}

function armsCalendarioOrdenar($a, $b) {
    $tempoA = strtotime($a['inicio']);
    $tempoB = strtotime($b['inicio']);

    if ($tempoA === $tempoB) {
        return ($a['prioridade'] <=> $b['prioridade']) ?: strcmp($a['titulo'], $b['titulo']);
    }

    return $tempoA <=> $tempoB;
}

function armsCalendarioEventos(PDO $pdo, $inicio, $fim, $tipoFiltro = '') {
    $sessionUserType = $_SESSION['arms_user_type'] ?? 'AKSANTI';
    $eventos = [];

    [$wherePedidos, $paramsPedidos] = armsPedidosWhereSql('r', 'cal_pedidos');
    $paramsPedidos[':inicio_created_ts'] = $inicio . ' 00:00:00';
    $paramsPedidos[':fim_created_ts'] = $fim . ' 23:59:59';
    $paramsPedidos[':inicio_deadline_ts'] = $inicio . ' 00:00:00';
    $paramsPedidos[':fim_deadline_ts'] = $fim . ' 23:59:59';

    $stmtPedidos = $pdo->prepare("
        SELECT
            r.id,
            r.reference,
            r.title,
            r.status,
            to_char(r.created_at, 'YYYY-MM-DD HH24:MI:SS') AS created_at_iso,
            to_char(r.deadline_at, 'YYYY-MM-DD HH24:MI:SS') AS deadline_at_iso,
            (r.deadline_at < NOW() AND r.status IN ('SENT', 'RECEIVED', 'CLIENT_RESPONDED')) AS deadline_expirado,
            a.name AS area_name,
            CASE
                WHEN COALESCE(r.destination_type, 'CLIENT') = 'AKSANTI' AND r.recipient_user_id IS NOT NULL
                    THEN COALESCE(up_recipient.full_name, au_recipient.email, c.name)
                ELSE c.name
            END AS client_name
        FROM arms.request r
        LEFT JOIN arms.area a ON a.id = r.area_id
        LEFT JOIN arms.client c ON c.id = r.client_id
        LEFT JOIN arms.auth_user au_recipient ON r.recipient_user_id = au_recipient.id
        LEFT JOIN arms.user_profile up_recipient ON r.recipient_user_id = up_recipient.user_id
        $wherePedidos
          AND (
              r.created_at BETWEEN :inicio_created_ts AND :fim_created_ts
              OR r.deadline_at BETWEEN :inicio_deadline_ts AND :fim_deadline_ts
          )
        ORDER BY r.deadline_at ASC, r.created_at ASC
    ");
    $stmtPedidos->execute($paramsPedidos);
    $pedidos = $stmtPedidos->fetchAll(PDO::FETCH_ASSOC);

    foreach ($pedidos as $pedido) {
        if ($sessionUserType === 'CLIENT' && $pedido['status'] === 'DRAFT') {
            continue;
        }

        if ($sessionUserType !== 'CLIENT' && armsCalendarioNoIntervalo($pedido['created_at_iso'], $inicio, $fim)) {
            $eventos[] = armsCalendarioEvento(
                'pedido-' . $pedido['id'] . '-created',
                'REQUEST_CREATED',
                'pedido',
                'Pedido criado: ' . $pedido['reference'],
                $pedido['title'] ?: 'Pedido criado no sistema.',
                $pedido['created_at_iso'],
                $pedido,
                4
            );
        }

        if (armsCalendarioNoIntervalo($pedido['deadline_at_iso'], $inicio, $fim)) {
            $expirado = armsCalendarioBool($pedido['deadline_expirado']);
            $eventos[] = armsCalendarioEvento(
                'pedido-' . $pedido['id'] . '-deadline',
                'DEADLINE',
                $expirado ? 'urgente' : 'deadline',
                ($expirado ? 'Prazo expirado: ' : 'Deadline: ') . $pedido['reference'],
                $expirado
                    ? 'O prazo deste pedido já passou e exige atenção imediata.'
                    : 'Data limite definida para resposta ou acompanhamento.',
                $pedido['deadline_at_iso'],
                $pedido,
                $expirado ? 0 : 1
            );
        }
    }

    [$whereAuditoria, $paramsAuditoria] = armsPedidosWhereSql('r', 'cal_audit');
    $paramsAuditoria[':inicio_ts'] = $inicio . ' 00:00:00';
    $paramsAuditoria[':fim_ts'] = $fim . ' 23:59:59';

    $stmtAuditoria = $pdo->prepare("
        SELECT
            ral.id,
            ral.to_status,
            to_char(ral.created_at, 'YYYY-MM-DD HH24:MI:SS') AS created_at_iso,
            r.reference,
            r.title,
            r.status,
            a.name AS area_name,
            CASE
                WHEN COALESCE(r.destination_type, 'CLIENT') = 'AKSANTI' AND r.recipient_user_id IS NOT NULL
                    THEN COALESCE(up_recipient.full_name, au_recipient.email, c.name)
                ELSE c.name
            END AS client_name,
            up.full_name AS actor_name
        FROM arms.request_audit_log ral
        INNER JOIN arms.request r ON r.id = ral.request_id
        LEFT JOIN arms.area a ON a.id = r.area_id
        LEFT JOIN arms.client c ON c.id = r.client_id
        LEFT JOIN arms.auth_user au_recipient ON r.recipient_user_id = au_recipient.id
        LEFT JOIN arms.user_profile up_recipient ON r.recipient_user_id = up_recipient.user_id
        LEFT JOIN arms.user_profile up ON up.user_id = ral.actor_id
        $whereAuditoria
          AND ral.created_at BETWEEN :inicio_ts AND :fim_ts
        ORDER BY ral.created_at ASC
    ");
    $stmtAuditoria->execute($paramsAuditoria);
    $auditorias = $stmtAuditoria->fetchAll(PDO::FETCH_ASSOC);

    foreach ($auditorias as $evento) {
        $status = $evento['to_status'];

        if ($sessionUserType === 'CLIENT' && in_array($status, ['DRAFT', 'SENT'], true)) {
            continue;
        }

        $categoria = 'estado';
        $prioridade = 3;

        if ($status === 'ACCEPTED') {
            $categoria = 'aceite';
            $prioridade = 1;
        } elseif ($status === 'REJECTED') {
            $categoria = 'rejeitado';
            $prioridade = 1;
        } elseif ($status === 'CLIENT_RESPONDED') {
            $categoria = 'alteracao';
            $prioridade = 1;
        } elseif ($status === 'RECEIVED') {
            $categoria = 'recebido';
            $prioridade = 2;
        } elseif ($status === 'SENT') {
            $categoria = 'enviado';
            $prioridade = 2;
        }

        $textoStatus = armsCalendarioStatusTexto($status);
        $descricao = trim(($evento['actor_name'] ?: 'Sistema') . ' registou este movimento no pedido.');

        $eventos[] = armsCalendarioEvento(
            'audit-' . $evento['id'],
            'STATUS_CHANGE',
            $categoria,
            $textoStatus . ': ' . $evento['reference'],
            $descricao,
            $evento['created_at_iso'],
            $evento,
            $prioridade
        );
    }

    if ($tipoFiltro !== '') {
        $eventos = array_values(array_filter($eventos, function ($evento) use ($tipoFiltro) {
            return $evento['categoria'] === $tipoFiltro || $evento['tipo'] === $tipoFiltro;
        }));
    }

    usort($eventos, 'armsCalendarioOrdenar');

    return $eventos;
}

function armsCalendarioIcsEscape($valor) {
    $valor = str_replace(["\\", ";", ",", "\r\n", "\n", "\r"], ["\\\\", "\;", "\,", "\\n", "\\n", "\\n"], (string)$valor);
    return $valor;
}

function armsCalendarioIcsData($dataHora) {
    $dt = new DateTime(str_replace('T', ' ', $dataHora), new DateTimeZone('Africa/Luanda'));
    return $dt->format('Ymd\THis');
}

function armsCalendarioResponderIcs(array $eventos) {
    header('Content-Type: text/calendar; charset=utf-8');
    header('Content-Disposition: attachment; filename="arms-calendario.ics"');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

    $linhas = [
        'BEGIN:VCALENDAR',
        'VERSION:2.0',
        'PRODID:-//Aksanti//ARMS Calendar//PT',
        'CALSCALE:GREGORIAN',
        'METHOD:PUBLISH',
        'X-WR-CALNAME:ARMS - Calendário de Pedidos',
        'X-WR-TIMEZONE:Africa/Luanda',
    ];

    foreach ($eventos as $evento) {
        $inicio = armsCalendarioIcsData($evento['inicio']);
        $fimDt = new DateTime(str_replace('T', ' ', $evento['inicio']), new DateTimeZone('Africa/Luanda'));
        $fimDt->modify('+30 minutes');

        $linhas[] = 'BEGIN:VEVENT';
        $linhas[] = 'UID:' . armsCalendarioIcsEscape($evento['id']) . '@arms.aksanti';
        $linhas[] = 'DTSTAMP:' . gmdate('Ymd\THis\Z');
        $linhas[] = 'DTSTART;TZID=Africa/Luanda:' . $inicio;
        $linhas[] = 'DTEND;TZID=Africa/Luanda:' . $fimDt->format('Ymd\THis');
        $linhas[] = 'SUMMARY:' . armsCalendarioIcsEscape($evento['titulo']);
        $linhas[] = 'DESCRIPTION:' . armsCalendarioIcsEscape($evento['descricao'] . ' Ref: ' . $evento['referencia']);
        $linhas[] = 'CATEGORIES:' . armsCalendarioIcsEscape($evento['categoria']);
        $linhas[] = 'END:VEVENT';
    }

    $linhas[] = 'END:VCALENDAR';
    echo implode("\r\n", $linhas) . "\r\n";
}

$acao = $_GET['acao'] ?? 'listar';

try {
    armsAuthIniciarSessao();
    armsAuthExigirLogin();

    $inicio = armsCalendarioDataValida($_GET['inicio'] ?? '') ? $_GET['inicio'] : date('Y-m-01', strtotime('-1 month'));
    $fim = armsCalendarioDataValida($_GET['fim'] ?? '') ? $_GET['fim'] : date('Y-m-t', strtotime('+3 months'));
    $tipo = trim($_GET['tipo'] ?? '');

    if ($acao === 'ics') {
        $inicio = armsCalendarioDataValida($_GET['inicio'] ?? '') ? $_GET['inicio'] : date('Y-m-d', strtotime('-30 days'));
        $fim = armsCalendarioDataValida($_GET['fim'] ?? '') ? $_GET['fim'] : date('Y-m-d', strtotime('+365 days'));
        armsCalendarioResponderIcs(armsCalendarioEventos($pdo, $inicio, $fim, $tipo));
        exit;
    }

    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

    $eventos = armsCalendarioEventos($pdo, $inicio, $fim, $tipo);
    $resumo = [
        'total' => count($eventos),
        'urgentes' => count(array_filter($eventos, fn($e) => $e['categoria'] === 'urgente')),
        'deadlines' => count(array_filter($eventos, fn($e) => in_array($e['categoria'], ['deadline', 'urgente'], true))),
        'decisoes' => count(array_filter($eventos, fn($e) => in_array($e['categoria'], ['aceite', 'rejeitado', 'alteracao'], true))),
    ];

    echo json_encode([
        'sucesso' => true,
        'intervalo' => ['inicio' => $inicio, 'fim' => $fim],
        'resumo' => $resumo,
        'eventos' => $eventos,
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    if ($acao === 'ics') {
        http_response_code(500);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Erro ao gerar calendário.';
        error_log('[ARMS] Erro no calendário ICS: ' . $e->getMessage());
        exit;
    }

    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['sucesso' => false, 'erro' => 'Erro interno ao carregar calendário.']);
    error_log('[ARMS] Erro em calendario.php: ' . $e->getMessage());
}
?>
