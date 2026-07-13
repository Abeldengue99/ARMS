<?php

$replacements = [
    // HTML file bug fixes
    'pedidos.htmláq=' => 'pedidos.html?q=',
    'pedido-detalhe.htmláref=' => 'pedido-detalhe.html?ref=',
    'pedido-detalhe.htmlÃ¡ref=' => 'pedido-detalhe.html?ref=',
    'decisãoá' => 'decisão?',
    
    // Emojis in pedido-detalhe.html
    'ão Requer a Sua Aprovação Formal' => '⚠️ Requer a Sua Aprovação Formal',
    'ão Histórico de Decisóes' => '📋 Histórico de Decisões',
    'ão Timeline' => '📋 Timeline',
    'ão Coment?rios' => '💬 Comentários',
    'ão Anexos' => '📎 Anexos',
    
    // Ternary operators in admin-utilizadores.html
    "é '<span class=\"badge\" style=\"background-color:#1A1A1A; color:var(--aksanti-gold);\">Membro Aksanti</span>'" => "? '<span class=\"badge\" style=\"background-color:#1A1A1A; color:var(--aksanti-gold);\">Membro Aksanti</span>'",
    
    // Question mark corruption (Tipo 1)
    'cabe?alho' => 'cabeçalho',
    'Cabe?alho' => 'Cabeçalho',
    'visóvel' => 'visível',
    'estática' => 'estética',
    'm?veis' => 'móveis',
    'telem?veis' => 'telemóveis',
    'telem?vel' => 'telemóvel',
    'P?r' => 'Pôr',
    'vari?veis' => 'variáveis',
    'peda?os' => 'pedaços',
    'D? ' => 'Dá ',
    'ecr?' => 'ecrã',
    'ecrãs' => 'ecrãs', // prevent double replace
    'saláo' => 'salão',
    'contenãoo' => 'contenção',
    'cart?o' => 'cartão',
    'CART?O' => 'CARTÃO',
    'precisóo' => 'precisão',
    'cabe?a' => 'cabeça',
    'cart?ozinho' => 'cartãozinho',
    'traduãoo' => 'tradução',
    'lángua' => 'língua',
    'inglás' => 'inglês',
    'c?rebro' => 'cérebro',
    'C?rebro' => 'Cérebro',
    'instruãoes' => 'instruções',
    'D?partements' => 'Départements',
    'R?ponse' => 'Réponse',
    'Envoy?' => 'Envoyé',
    'Re?u' => 'Reçu',
    'R?pondu' => 'Répondu',
    'Accept?' => 'Accepté',
    'Rejet?' => 'Rejeté',
    'Ferm?' => 'Fermé',
    'Cr?er' => 'Créer',
    'D?tails' => 'Détails',
    'R?f?rence' => 'Référence',
    'oubli?' => 'oublié',
    'sessóo' => 'sessão',
    'r?cio' => 'rácio',
    'Animaãoes' => 'Animações',
    'opãoes' => 'opções',
    'opãoo' => 'opção',
    'configuraãoes' => 'configurações',
    'm?s' => 'mês',
    'Distribuiãoo' => 'Distribuição',
    'Gr?fico' => 'Gráfico',
    'iniciaãoes' => 'iniciações',
    'Mar?o' => 'Março',
    'Din?mica' => 'Dinâmica',
    'sótio' => 'sítio',
    'c?!' => 'cá!',
    'c?digo' => 'código',
    'C?digo' => 'Código',
    'N?mero' => 'Número',
    'sóo obrigatórios' => 'são obrigatórios',
    'sóo as' => 'são as',
    'sóo leis' => 'são leis',
    'sóo obrigat' => 'são obrigat',
    'ediãoo' => 'edição',
    'conte?do' => 'conteúdo',
    'lágica' => 'lógica',
    'cart?es' => 'cartões',
    'mai?sculas' => 'maiúsculas',
    'Endere?o' => 'Endereço',
    'Seguran?a' => 'Segurança',
    'D?lar' => 'Dólar',
    'D?connecter' => 'Déconnecter',
    '?s' => 'às',
    'injeãoes' => 'injeções',
    'm?gicas' => 'mágicas',
    'DECIS?O' => 'DECISÃO',
    'decisóes' => 'decisões',
    'HIST?RICO' => 'HISTÓRICO',
    'COMENT?RIOS' => 'COMENTÁRIOS',
    'Coment?rios' => 'Comentários',
    'coment?rio' => 'comentário',
    'M?x' => 'Máx',
    'p?es' => 'pões',
    'nóveis' => 'níveis',
    
    // Double encoding (Tipo 2)
    'pÃ¡gina' => 'página',
    'Ã©' => 'é',
    'nÃ£o' => 'não',
    'lÃ¡' => 'lá',
    'botÃ£o' => 'botão',
    'jÃ¡' => 'já',
    'Ã§Ãµes' => 'ções',
    'NotificaÃ§Ãµes' => 'Notificações',
    'ecrÃ£' => 'ecrã',
    'nÃºmeros' => 'números',
    'Ã ' => 'à',
    'Ã rea' => 'Área',
    'TÃ­tulo' => 'Título',
    'DescriÃ§Ã£o' => 'Descrição',
    'ligaÃ§Ã£o' => 'ligação',
    'AdministraÃ§Ã£o' => 'Administração',
    'SessÃ£o' => 'Sessão',
    'visÃ³veis' => 'visíveis',
    'referÃªncia' => 'referência',
    'notificaÃ§Ã£o' => 'notificação',
    'Ã¡' => 'á', // Remaining ones that are not in the query string
];

$files = glob('*.html');

foreach ($files as $file) {
    $content = file_get_contents($file);
    
    foreach ($replacements as $old => $new) {
        $content = str_replace($old, $new, $content);
    }
    
    file_put_contents($file, $content);
}

echo "Replacements done.";
?>
