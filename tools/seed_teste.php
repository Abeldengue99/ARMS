<?php
require 'api/db.php';
$pdo->exec("
    INSERT INTO arms.auth_user (email, password_hash, user_type, is_admin) 
    VALUES ('admin@aksanti.xyz', 'hash', 'AKSANTI', TRUE) 
    ON CONFLICT DO NOTHING;
    
    INSERT INTO arms.user_profile (user_id, full_name, locale) 
    SELECT id, 'Príncipe Aksanti', 'pt-PT' FROM arms.auth_user WHERE email='admin@aksanti.xyz' 
    ON CONFLICT (user_id) DO UPDATE
    SET full_name = EXCLUDED.full_name;
    
    INSERT INTO arms.client (name, primary_email) 
    VALUES ('Aksanti Solutions Lda.', 'geral@aksanti.xyz') 
    ON CONFLICT DO NOTHING;
    
    INSERT INTO arms.client_contact (client_id, email, full_name) 
    SELECT id, 'financas@aksanti.xyz', 'João Financeiro' FROM arms.client WHERE name='Aksanti Solutions Lda.'
    ON CONFLICT DO NOTHING;
");
echo 'Done';
