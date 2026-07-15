<?php
require_once 'db.php';
require_once 'auth.php';

header('Content-Type: application/json; charset=utf-8');

try {
    armsAuthExigirAdmin();

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $stmt = $pdo->prepare("SELECT * FROM arms.tenant_settings WHERE tenant_id = 1");
        $stmt->execute();
        $settings = $stmt->fetch();
        
        echo json_encode(['sucesso' => true, 'dados' => $settings ?: []]);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Read input
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (isset($input['acao']) && $input['acao'] === 'identidade') {
            $nome = trim($input['system_name'] ?? '');
            $cor = trim($input['primary_color'] ?? '');
            
            if (!$nome || !$cor) {
                echo json_encode(['sucesso' => false, 'erro' => 'Nome e Cor são obrigatórios.']);
                exit;
            }

            $logoUrlUpdate = "";
            $params = [$nome, $cor];

            if (!empty($input['logo_base64'])) {
                $base64Str = $input['logo_base64'];
                if (preg_match('/^data:image\/(\w+)(\+xml)?;base64,/', $base64Str, $match)) {
                    $ext = strtolower($match[1]);
                    $ext = $ext === 'svg' ? 'svg' : $ext;
                    
                    if (in_array($ext, ['png', 'jpeg', 'jpg', 'svg'])) {
                        $data = substr($base64Str, strpos($base64Str, ',') + 1);
                        $data = base64_decode($data);
                        if ($data !== false) {
                            $filename = 'img/custom-logo-' . time() . '.' . $ext;
                            $filepath = __DIR__ . '/../' . $filename;
                            
                            if (file_put_contents($filepath, $data)) {
                                $logoUrlUpdate = ", logo_url = ?";
                                $params[] = $filename;
                            }
                        }
                    }
                }
            }

            $query = "UPDATE arms.tenant_settings SET system_name = ?, primary_color = ? $logoUrlUpdate, updated_at = NOW() WHERE tenant_id = 1";
            $stmt = $pdo->prepare($query);
            $stmt->execute($params);
            
            echo json_encode(['sucesso' => true, 'mensagem' => 'Identidade visual atualizada com sucesso.']);
            exit;
        }
        
        if (isset($input['acao']) && $input['acao'] === 'smtp') {
            $host = trim($input['smtp_host'] ?? '');
            $port = (int)($input['smtp_port'] ?? 587);
            $user = trim($input['smtp_user'] ?? '');
            $pass = trim($input['smtp_pass'] ?? '');
            $from = trim($input['smtp_from_email'] ?? '');
            $fromName = trim($input['smtp_from_name'] ?? '');
            
            $stmt = $pdo->prepare("
                UPDATE arms.tenant_settings 
                SET smtp_host = ?, smtp_port = ?, smtp_user = ?, smtp_pass = ?, smtp_from_email = ?, smtp_from_name = ?, updated_at = NOW()
                WHERE tenant_id = 1
            ");
            $stmt->execute([$host, $port ?: null, $user, $pass, $from, $fromName]);
            
            echo json_encode(['sucesso' => true, 'mensagem' => 'Configurações de E-mail atualizadas com sucesso.']);
            exit;
        }
    }
    
} catch (Exception $e) {
    echo json_encode(['sucesso' => false, 'erro' => 'Erro interno.']);
}
