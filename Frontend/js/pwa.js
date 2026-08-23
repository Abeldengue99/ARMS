// pwa.js - Lógica de Service Worker, Atualizações e Instalação (Modern UI)

let deferredPrompt;
let newWorker;
let versionMonitorTimer = null;

const ARMS_DEPLOY_MARKER_STORAGE_KEY = 'arms_deploy_marker_seen';
const ARMS_DEPLOY_MARKER_ALERT_KEY = 'arms_deploy_marker_alerted';
const ARMS_VERSION_CHECK_INTERVAL_MS = 5 * 60 * 1000;

// Estilos dinâmicos para os modais de PWA (Atualização e Instalação)
const pwaStyles = `
  .pwa-toast-container {
    position: fixed;
    bottom: 20px;
    right: 20px;
    z-index: 99999;
    display: flex;
    flex-direction: column;
    gap: 15px;
    font-family: 'Inter', system-ui, -apple-system, sans-serif;
  }
  
  .pwa-card {
    background: rgba(26, 26, 46, 0.95);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 215, 0, 0.2);
    border-radius: 12px;
    padding: 20px;
    color: #fff;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
    display: flex;
    align-items: center;
    gap: 15px;
    max-width: 350px;
    transform: translateY(100px);
    opacity: 0;
    transition: all 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275);
  }
  
  .pwa-card.pwa-show {
    transform: translateY(0);
    opacity: 1;
  }
  
  .pwa-icon {
    width: 48px;
    height: 48px;
    background: rgba(255, 215, 0, 0.12);
    border: 1px solid rgba(255, 215, 0, 0.25);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--aksanti-gold, #ffd700);
    font-size: 24px;
    flex-shrink: 0;
    overflow: hidden;
  }
  
  .pwa-favicon-img {
    width: 28px;
    height: 28px;
    object-fit: contain;
  }
  
  .pwa-content h4 {
    margin: 0 0 5px 0;
    font-size: 16px;
    font-weight: 600;
  }
  
  .pwa-content p {
    margin: 0 0 12px 0;
    font-size: 13px;
    color: #aaa;
    line-height: 1.4;
  }
  
  .pwa-actions {
    display: flex;
    gap: 10px;
  }
  
  .pwa-btn {
    padding: 8px 16px;
    border: none;
    border-radius: 6px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
  }
  
  .pwa-btn-primary {
    background: var(--aksanti-gold, #ffd700);
    color: #1a1a2e;
  }
  
  .pwa-btn-primary:hover {
    filter: brightness(1.1);
    transform: translateY(-2px);
  }
  
  .pwa-btn-secondary {
    background: rgba(255, 255, 255, 0.1);
    color: #fff;
  }
  
  .pwa-btn-secondary:hover {
    background: rgba(255, 255, 255, 0.2);
  }
`;

// Injetar estilos
const styleSheet = document.createElement('style');
styleSheet.innerText = pwaStyles;
document.head.appendChild(styleSheet);

// Contentor para os toasts
const toastContainer = document.createElement('div');
toastContainer.className = 'pwa-toast-container';
document.body.appendChild(toastContainer);

// Mostrar o toast de atualização
function showUpdateToast(options = {}) {
  if (toastContainer.querySelector('[data-pwa-toast="update"]')) {
    return;
  }

  const titulo = options.title || 'Nova Versão Disponível';
  const mensagem = options.message || 'Uma atualização elegante e inovadora acabou de chegar. Atualize para continuar a usar a melhor versão do ARMS.';
  const textoBotaoPrimario = options.primaryLabel || 'Atualizar Agora';
  const textoBotaoSecundario = options.secondaryLabel || 'Mais Tarde';
  const acaoPrimaria = typeof options.onPrimary === 'function'
    ? options.onPrimary
    : () => {
        if (newWorker) {
          try {
            sessionStorage.setItem('arms_sw_update_pending_reload', '1');
          } catch (e) {}
          newWorker.postMessage('SKIP_WAITING');
        } else {
          window.location.reload();
        }
      };

  const card = document.createElement('div');
  card.className = 'pwa-card';
  card.dataset.pwaToast = 'update';
  card.innerHTML = `
    <div class="pwa-icon">
      <img src="img/favicon.png" alt="ARMS" class="pwa-favicon-img" onerror="this.src='img/icon-192x192.png'">
    </div>
    <div class="pwa-content">
      <h4>${titulo}</h4>
      <p>${mensagem}</p>
      <div class="pwa-actions">
        <button class="pwa-btn pwa-btn-primary" id="btn-pwa-update">${textoBotaoPrimario}</button>
        <button class="pwa-btn pwa-btn-secondary" id="btn-pwa-dismiss">${textoBotaoSecundario}</button>
      </div>
    </div>
  `;
  
  toastContainer.appendChild(card);
  
  // Animar a entrada
  setTimeout(() => card.classList.add('pwa-show'), 100);
  
  card.querySelector('#btn-pwa-update').addEventListener('click', () => {
    card.classList.remove('pwa-show');
    setTimeout(() => card.remove(), 500);
    acaoPrimaria();
  });
  
  card.querySelector('#btn-pwa-dismiss').addEventListener('click', () => {
    card.classList.remove('pwa-show');
    setTimeout(() => card.remove(), 500);
  });
}

// Mostrar o toast de instalação
function showInstallToast() {
  const card = document.createElement('div');
  card.className = 'pwa-card';
  card.innerHTML = `
    <div class="pwa-icon">
      <img src="img/favicon.png" alt="ARMS" class="pwa-favicon-img" onerror="this.src='img/icon-192x192.png'">
    </div>
    <div class="pwa-content">
      <h4>Instale a App</h4>
      <p>Tenha o ARMS sempre à mão! Instale a nossa aplicação no seu ecrã inicial para um acesso mais rápido.</p>
      <div class="pwa-actions">
        <button class="pwa-btn pwa-btn-primary" id="btn-pwa-install">Instalar</button>
        <button class="pwa-btn pwa-btn-secondary" id="btn-pwa-close">Agora Não</button>
      </div>
    </div>
  `;
  
  toastContainer.appendChild(card);
  
  setTimeout(() => card.classList.add('pwa-show'), 1000); // Aparece após 1 segundo
  
  document.getElementById('btn-pwa-install').addEventListener('click', async () => {
    card.classList.remove('pwa-show');
    setTimeout(() => card.remove(), 500);
    
    if (deferredPrompt) {
      deferredPrompt.prompt();
      const { outcome } = await deferredPrompt.userChoice;
      console.log(`User response to the install prompt: ${outcome}`);
      deferredPrompt = null;
    }
  });
  
  document.getElementById('btn-pwa-close').addEventListener('click', () => {
    card.classList.remove('pwa-show');
    setTimeout(() => card.remove(), 500);
  });
}

// Intercetar prompt de instalação
window.addEventListener('beforeinstallprompt', (e) => {
  e.preventDefault();
  deferredPrompt = e;
  
  // Mostrar o nosso UI de instalação apenas se não tivermos mostrado nesta sessão
  if (!sessionStorage.getItem('pwa_install_prompted')) {
    showInstallToast();
    sessionStorage.setItem('pwa_install_prompted', 'true');
  }
});

async function verificarNovaVersaoPublicada() {
  try {
    const resposta = await fetch(`version.json?t=${Date.now()}`, {
      cache: 'no-store',
      credentials: 'same-origin'
    });

    if (!resposta.ok) return;

    const info = await resposta.json();
    const marcadorServidor = String(info && info.deploy_marker ? info.deploy_marker : '').trim();
    if (!marcadorServidor) return;

    const atualizarMarcadorConhecido = () => {
      try {
        localStorage.setItem(ARMS_DEPLOY_MARKER_STORAGE_KEY, marcadorServidor);
      } catch (e) {}
    };

    let marcadorGuardado = '';
    try {
      marcadorGuardado = localStorage.getItem(ARMS_DEPLOY_MARKER_STORAGE_KEY) || '';
    } catch (e) {}

    if (!marcadorGuardado) {
      atualizarMarcadorConhecido();
      return;
    }

    if (marcadorGuardado === marcadorServidor) {
      return;
    }

    let saltoAceiteSW = false;
    try {
      saltoAceiteSW = sessionStorage.getItem('arms_sw_update_pending_reload') === '1';
      if (saltoAceiteSW) {
        sessionStorage.removeItem('arms_sw_update_pending_reload');
      }
    } catch (e) {}

    atualizarMarcadorConhecido();

    if (saltoAceiteSW) {
      return;
    }

    let marcadorAlertado = '';
    try {
      marcadorAlertado = sessionStorage.getItem(ARMS_DEPLOY_MARKER_ALERT_KEY) || '';
    } catch (e) {}

    if (marcadorAlertado === marcadorServidor) {
      return;
    }

    try {
      sessionStorage.setItem(ARMS_DEPLOY_MARKER_ALERT_KEY, marcadorServidor);
    } catch (e) {}

    showUpdateToast({
      title: 'Nova versão do site',
      message: 'Foi publicada uma nova versão do ARMS. Atualize para carregar o conteúdo mais recente.',
      primaryLabel: 'Atualizar agora',
      secondaryLabel: 'Mais tarde',
      onPrimary: () => {
        window.location.reload();
      }
    });
  } catch (e) {
  }
}

function iniciarMonitorVersaoAplicacao() {
  verificarNovaVersaoPublicada();

  if (versionMonitorTimer) return;
  versionMonitorTimer = window.setInterval(verificarNovaVersaoPublicada, ARMS_VERSION_CHECK_INTERVAL_MS);
}

if (document.readyState === 'complete') {
  iniciarMonitorVersaoAplicacao();
} else {
  window.addEventListener('load', iniciarMonitorVersaoAplicacao);
}

// Registo do Service Worker
if ('serviceWorker' in navigator) {
  window.addEventListener('load', () => {
    navigator.serviceWorker.register('/sw.js', { scope: '/' }).then(reg => {
      
      reg.addEventListener('updatefound', () => {
        newWorker = reg.installing;
        newWorker.addEventListener('statechange', () => {
          if (newWorker.state === 'installed') {
            if (navigator.serviceWorker.controller) {
              // Nova versão disponível!
              showUpdateToast();
            }
          }
        });
      });
      
    }).catch(err => {
      console.error('Service Worker registration failed: ', err);
    });
    
    let refreshing;
    navigator.serviceWorker.addEventListener('controllerchange', () => {
      if (refreshing) return;
      refreshing = true;
      window.location.reload();
    });
  });
}
