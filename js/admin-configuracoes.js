document.addEventListener('DOMContentLoaded', () => {
    try {
        const ud = JSON.parse(localStorage.getItem('arms_utilizador_dados') || '{}');
        if (ud.admin !== true && ud.is_admin !== true && String(ud.is_admin) !== '1') {
            window.location.href = 'dashboard.html';
            return;
        }
    } catch (e) {
        window.location.href = 'index.html';
    }

    carregarConfiguracoes();

    document.getElementById('config-color-picker').addEventListener('input', (e) => {
        document.getElementById('config-primary-color').value = e.target.value;
    });
    
    document.getElementById('config-primary-color').addEventListener('input', (e) => {
        document.getElementById('config-color-picker').value = e.target.value;
    });

    window.logoUploadBase64 = null;
    const boxUpload = document.getElementById('box-upload-logo');
    const fileInput = document.getElementById('config-logo-upload');
    const previewImg = document.getElementById('preview-logo-img');
    const btnRemover = document.getElementById('btn-remover-logo');
    const defaultLogoSrc = previewImg.src;

    boxUpload.addEventListener('click', () => fileInput.click());
    
    boxUpload.addEventListener('dragover', (e) => {
        e.preventDefault();
        boxUpload.style.borderColor = '#3b82f6';
        boxUpload.style.background = '#eff6ff';
    });

    boxUpload.addEventListener('dragleave', () => {
        boxUpload.style.borderColor = '#cbd5e1';
        boxUpload.style.background = '#f8fafc';
    });

    boxUpload.addEventListener('drop', (e) => {
        e.preventDefault();
        boxUpload.style.borderColor = '#cbd5e1';
        boxUpload.style.background = '#f8fafc';
        if (e.dataTransfer.files && e.dataTransfer.files.length > 0) {
            fileInput.files = e.dataTransfer.files;
            fileInput.dispatchEvent(new Event('change'));
        }
    });

    fileInput.addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (!file) return;

        if (file.size > 2 * 1024 * 1024) {
            ArmsNotificacoes.mostrar('erro', 'O ficheiro excede o tamanho máximo de 2MB.');
            fileInput.value = '';
            return;
        }

        const reader = new FileReader();
        reader.onload = function(event) {
            window.logoUploadBase64 = event.target.result;
            previewImg.src = event.target.result;
            btnRemover.style.display = 'inline-block';
        };
        reader.readAsDataURL(file);
    });

    btnRemover.addEventListener('click', () => {
        window.logoUploadBase64 = null;
        fileInput.value = '';
        previewImg.src = window.defaultLogoSrcBase || defaultLogoSrc;
        btnRemover.style.display = 'none';
    });
});

function carregarConfiguracoes() {
    fetch('api/configuracoes-plataforma.php')
        .then(r => r.json())
        .then(data => {
            if (data.sucesso && data.dados) {
                const d = data.dados;
                document.getElementById('config-system-name').value = d.system_name || '';
                document.getElementById('config-primary-color').value = d.primary_color || '#d97706';
                document.getElementById('config-color-picker').value = d.primary_color || '#d97706';
                
                document.getElementById('config-smtp-host').value = d.smtp_host || '';
                document.getElementById('config-smtp-port').value = d.smtp_port || '';
                document.getElementById('config-smtp-user').value = d.smtp_user || '';
                document.getElementById('config-smtp-from-name').value = d.smtp_from_name || '';
                document.getElementById('config-smtp-from-email').value = d.smtp_from_email || '';

                if (d.logo_url) {
                    const img = document.getElementById('preview-logo-img');
                    img.src = d.logo_url;
                    window.defaultLogoSrcBase = d.logo_url;
                }
            }
        });
}

window.salvarConfiguracoes = function() {
    const payloadIdentidade = {
        acao: 'identidade',
        system_name: document.getElementById('config-system-name').value,
        primary_color: document.getElementById('config-primary-color').value
    };

    if (window.logoUploadBase64) {
        payloadIdentidade.logo_base64 = window.logoUploadBase64;
    }

    const payloadSMTP = {
        acao: 'smtp',
        smtp_host: document.getElementById('config-smtp-host').value,
        smtp_port: document.getElementById('config-smtp-port').value,
        smtp_user: document.getElementById('config-smtp-user').value,
        smtp_pass: document.getElementById('config-smtp-pass').value,
        smtp_from_name: document.getElementById('config-smtp-from-name').value,
        smtp_from_email: document.getElementById('config-smtp-from-email').value
    };

    Promise.all([
        fetch('api/configuracoes-plataforma.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payloadIdentidade)
        }).then(r => r.json()),
        fetch('api/configuracoes-plataforma.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payloadSMTP)
        }).then(r => r.json())
    ]).then(([resID, resSMTP]) => {
        if (resID.sucesso && resSMTP.sucesso) {
            document.documentElement.style.setProperty('--aksanti-gold', payloadIdentidade.primary_color);
            ArmsNotificacoes.mostrar('sucesso', 'Definições guardadas com sucesso! A recarregar o sistema...');
            
            // Reload page so all sidebars and variables pick up the new logo and colors fully
            setTimeout(() => {
                window.location.reload();
            }, 1200);
        } else {
            let errorMsg = '';
            if (!resID.sucesso) errorMsg += 'Erro na Identidade: ' + resID.erro + ' ';
            if (!resSMTP.sucesso) errorMsg += 'Erro no SMTP: ' + resSMTP.erro;
            ArmsNotificacoes.mostrar('erro', errorMsg);
        }
    }).catch(err => {
        ArmsNotificacoes.mostrar('erro', 'Ocorreu um erro ao guardar as definições.');
    });
};
