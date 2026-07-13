// Estou a criar do zero uma cópia para imitar a tua tabela gigante e intocável da PostgreSQL 'client' com a sua sub-tabela associada 'client_contact'
const mockClientes = [
    // Estou a compilar a grandiosa "Tech Solutions" na lista de empresas, e de seguida ligo-lhes o seu fiel funcionário/contacto direto nas tuas bases
    { id: 1, company_name: 'Tech Solutions Lda', tax_id: '500123456', status: 'ACTIVE', contact_name: 'Carlos Tech', contact_email: 'carlos@tech.ao' },
    // Estou a simular o grande e poderoso cliente que vos paga fortunas pesadas à Aksanti, a nossa conhecida Construtora
    { id: 2, company_name: 'Construtora Ouro', tax_id: '500987654', status: 'ACTIVE', contact_name: 'João Ouro', contact_email: 'diretor@ouro.ao' },
    // Estou a criar e arrastar o caso de um pobre e triste cliente que está com a sua conta cancelada, expulsa ou extinta (O famoso TERMINATED) nas tuas cruéis mãos!
    { id: 3, company_name: 'Marketing Global', tax_id: '500555444', status: 'TERMINATED', contact_name: 'Marta Global', contact_email: 'marta@marketing.ao' }
];
