<?php
/**
 * ARMS — API para Editar Cliente
 * Atualiza dados de um cliente existente no PostgreSQL
 */
require_once 'db.php';
require_once 'auth.php';
require_once 'permissoes.php';

header('Content-Type: application/json; charset=utf-8');
armsExigirPermissao($pdo, 'clientes.gerir', 'Não tem permissão para editar clientes.');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['sucesso' => false, 'erro' => 'Método não permitido.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!is_array($input)) {
    echo json_encode(['sucesso' => false, 'erro' => 'Dados inválidos.']);
    exit;
}

$id = trim($input['id'] ?? '');
$nome = trim($input['nome'] ?? '');
$email = strtolower(trim($input['email'] ?? ''));
$nif = trim($input['nif'] ?? '');
$localizacao = trim($input['localizacao'] ?? '');
$contactoNome = trim($input['contacto_nome'] ?? '');

// Validar campos obrigatórios (Empresa)
if ($id === '') {
    echo json_encode(['sucesso' => false, 'erro' => 'ID do cliente é obrigatório.']);
    exit;
}

if ($nome === '' || $email === '' || $nif === '' || $localizacao === '') {
    echo json_encode(['sucesso' => false, 'erro' => 'Nome, Email, NIF e Localização da empresa são obrigatórios.']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['sucesso' => false, 'erro' => 'Informe um e-mail válido para a empresa.']);
    exit;
}

// Validar representantes
$representantes = $input['representantes'] ?? [];
if (!is_array($representantes) || count($representantes) === 0) {
    echo json_encode(['sucesso' => false, 'erro' => 'É obrigatório registar pelo menos um representante.']);
    exit;
}

$primeiroRep = $representantes[0];
$repNome = trim($primeiroRep['nome'] ?? '');
$repEmail = trim($primeiroRep['email'] ?? '');
$repTelefone = trim($primeiroRep['telefone'] ?? '');

if ($repNome === '' || $repEmail === '' || $repTelefone === '') {
    echo json_encode(['sucesso' => false, 'erro' => 'O Representante principal deve ter Nome, Email e Contacto preenchidos.']);
    exit;
}

try {
    $stmtEmailDuplicado = $pdo->prepare("
        SELECT name
        FROM arms.client
        WHERE id <> CAST(:id AS uuid)
          AND LOWER(BTRIM(primary_email::text)) = LOWER(:email)
        LIMIT 1
    ");
    $stmtEmailDuplicado->execute([
        'id' => $id,
        'email' => $email
    ]);
    $clienteEmailDuplicado = $stmtEmailDuplicado->fetch();

    if ($clienteEmailDuplicado) {
        echo json_encode([
            'sucesso' => false,
            'erro' => 'Já existe outro cliente com este e-mail principal: ' . $clienteEmailDuplicado['name']
        ]);
        exit;
    }

    if ($nif !== '') {
        $stmtNifDuplicado = $pdo->prepare("
            SELECT name
            FROM arms.client
            WHERE id <> CAST(:id AS uuid)
              AND LOWER(BTRIM(tax_id::text)) = LOWER(:nif)
            LIMIT 1
        ");
        $stmtNifDuplicado->execute([
            'id' => $id,
            'nif' => $nif
        ]);
        $clienteNifDuplicado = $stmtNifDuplicado->fetch();

        if ($clienteNifDuplicado) {
            echo json_encode([
                'sucesso' => false,
                'erro' => 'Já existe outro cliente com este NIF: ' . $clienteNifDuplicado['name']
            ]);
            exit;
        }
    }

    $pdo->beginTransaction();

    // 1. Atualizar dados do cliente
    $stmt = $pdo->prepare("
        UPDATE arms.client 
        SET name = :nome, 
            primary_email = :email, 
            tax_id = :nif, 
            location = :localizacao,
            is_active = :ativo
        WHERE id = :id
        RETURNING id, name, primary_email, tax_id, location, is_active
    ");

    $stmt->execute([
        'id'           => $id,
        'nome'         => $nome,
        'email'        => $email,
        'nif'          => $nif !== '' ? $nif : null,
        'localizacao'  => $localizacao !== '' ? $localizacao : null,
        'ativo'        => isset($input['ativo']) ? ($input['ativo'] ? 'true' : 'false') : 'true'
    ]);

    $clienteAtualizado = $stmt->fetch();

    if (!$clienteAtualizado) {
        $pdo->rollBack();
        echo json_encode(['sucesso' => false, 'erro' => 'Cliente não encontrado.']);
        exit;
    }

    // 2. Atualizar representantes (Apaga os antigos e insere os novos para manter sincronia)
    $stmtDel = $pdo->prepare("DELETE FROM arms.client_contact WHERE client_id = :client_id");
    $stmtDel->execute(['client_id' => $id]);

    if (count($representantes) > 0) {
        $stmtContacto = $pdo->prepare("
            INSERT INTO arms.client_contact (client_id, email, full_name, phone)
            VALUES (:client_id, :email, :full_name, :phone)
            ON CONFLICT (client_id, email) DO UPDATE
            SET full_name = EXCLUDED.full_name,
                phone = EXCLUDED.phone
        ");
        
        foreach ($representantes as $rep) {
            $repNome = trim($rep['nome'] ?? '');
            $repEmail = strtolower(trim($rep['email'] ?? ''));
            $repTelefone = trim($rep['telefone'] ?? '');
            
            if ($repEmail === '') {
                $repEmail = 'no-reply-' . uniqid() . '@aksanti.local';
            }
            
            if ($repNome !== '' || $repTelefone !== '') {
                $stmtContacto->execute([
                    'client_id' => $id,
                    'email'     => $repEmail,
                    'full_name' => $repNome,
                    'phone'     => $repTelefone !== '' ? $repTelefone : null
                ]);
            }
        }
    }

    $pdo->commit();

    echo json_encode([
        'sucesso'  => true,
        'mensagem' => 'Cliente atualizado com sucesso!',
        'cliente'  => $clienteAtualizado
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode([
        'sucesso' => false,
        'erro'    => $e->getMessage()
    ]);
}
?>
