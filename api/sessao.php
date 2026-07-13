<?php
/**
 * ARMS - API de Sessão
 * Verifica autenticação, devolve dados atualizados e encerra sessão.
 */
require_once 'db.php';
require_once 'auth.php';
require_once 'utilizador-identidade.php';
require_once 'permissoes.php';
require_once 'seguranca-servico.php';

armsAuthIniciarSessao();

header('Content-Type: application/json; charset=utf-8');

function armsApiBool($valor) {
    return $valor === true || $valor === 1 || $valor === '1' || $valor === 't' || $valor === 'true' || $valor === 'TRUE';
}

$acao = $_GET['acao'] ?? 'verificar';

switch ($acao) {
    case 'verificar':
        if (!empty($_SESSION['arms_logado']) && $_SESSION['arms_logado'] === true) {
            $stmt = $pdo->prepare("
                SELECT
                    au.id,
                    au.email,
                    au.user_type,
                    au.is_admin,
                    au.is_active,
                    up.full_name,
                    up.phone AS cargo,
                    up.locale,
                    cliente.client_id,
                    cliente.client_name,
                    cliente.client_tax_id,
                    cliente.client_location,
                    cliente.client_primary_email,
                    cliente.client_is_active
                FROM arms.auth_user au
                LEFT JOIN arms.user_profile up ON au.id = up.user_id
                LEFT JOIN LATERAL (
                    SELECT
                        cc.client_id,
                        c.name AS client_name,
                        c.tax_id AS client_tax_id,
                        c.location AS client_location,
                        c.primary_email AS client_primary_email,
                        c.is_active AS client_is_active
                    FROM arms.client_contact cc
                    JOIN arms.client c ON c.id = cc.client_id
                    WHERE cc.user_id = au.id
                    ORDER BY cc.created_at DESC
                    LIMIT 1
                ) cliente ON TRUE
                WHERE au.id = :id
            ");
            $stmt->execute(['id' => $_SESSION['arms_user_id']]);
            $user = $stmt->fetch();

            if (!$user || !armsApiBool($user['is_active'])) {
                session_destroy();
                echo json_encode(['sucesso' => false, 'autenticado' => false]);
                exit;
            }

            $nome = $user['full_name'] ?? $user['email'];
            $iniciais = armsIniciaisUtilizador($nome, $user['email']);

            $_SESSION['arms_user_type'] = $user['user_type'];
            $_SESSION['arms_is_admin'] = armsApiBool($user['is_admin']);
            $_SESSION['arms_user_email'] = $user['email'];
            $_SESSION['arms_client_id'] = $user['client_id'];
            $_SESSION['arms_client_name'] = $user['client_name'];
            $permissoes = armsPermissoesDoUtilizador($pdo, $user['id'], armsApiBool($user['is_admin']));
            armsSegurancaAtualizarSessao($pdo, $user['id']);

            $cliente = null;
            if (!empty($user['client_id'])) {
                $cliente = [
                    'id' => $user['client_id'],
                    'nome' => $user['client_name'],
                    'nif' => $user['client_tax_id'],
                    'localizacao' => $user['client_location'],
                    'email' => $user['client_primary_email'],
                    'ativo' => armsApiBool($user['client_is_active'])
                ];
            }

            echo json_encode([
                'sucesso' => true,
                'autenticado' => true,
                'utilizador' => [
                    'id' => $user['id'],
                    'email' => $user['email'],
                    'nome' => $user['full_name'] ?? 'Utilizador',
                    'cargo' => $user['cargo'] ?? '',
                    'tipo' => $user['user_type'],
                    'admin' => armsApiBool($user['is_admin']),
                    'permissoes' => $permissoes,
                    'ativo' => armsApiBool($user['is_active']),
                    'iniciais' => $iniciais,
                    'locale' => $user['locale'] ?? 'pt-PT',
                    'client_id' => $user['client_id'],
                    'cliente' => $cliente
                ]
            ]);
        } else {
            echo json_encode(['sucesso' => true, 'autenticado' => false]);
        }
        break;

    case 'logout':
        armsSegurancaTerminarSessaoAtual($pdo);
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'] ?? '', $params['secure'], $params['httponly']);
        }
        session_destroy();
        echo json_encode(['sucesso' => true, 'mensagem' => 'Sessão terminada.']);
        break;

    default:
        echo json_encode(['sucesso' => false, 'erro' => 'Ação desconhecida.']);
}
?>
