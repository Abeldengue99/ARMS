// pwa.js - Lógica de Service Worker, Atualizações e Instalação (Modern UI)

let deferredPrompt;
let newWorker;

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
    background: rgba(255, 215, 0, 0.1);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--aksanti-gold, #ffd700);
    font-size: 24px;
    flex-shrink: 0;
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
function showUpdateToast() {
  const card = document.createElement('div');
  card.className = 'pwa-card';
  card.innerHTML = `
    <div class="pwa-icon">
      <i class="fas fa-sync-alt"></i>
    </div>
    <div class="pwa-content">
      <h4>Nova Versão Disponível</h4>
      <p>Uma atualização elegante e inovadora acabou de chegar. Atualize para continuar a usar a melhor versão do ARMS.</p>
      <div class="pwa-actions">
        <button class="pwa-btn pwa-btn-primary" id="btn-pwa-update">Atualizar Agora</button>
        <button class="pwa-btn pwa-btn-secondary" id="btn-pwa-dismiss">Mais Tarde</button>
      </div>
    </div>
  `;
  
  toastContainer.appendChild(card);
  
  // Animar a entrada
  setTimeout(() => card.classList.add('pwa-show'), 100);
  
  document.getElementById('btn-pwa-update').addEventListener('click', () => {
    if (newWorker) {
      newWorker.postMessage('SKIP_WAITING');
    }
  });
  
  document.getElementById('btn-pwa-dismiss').addEventListener('click', () => {
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
      <i class="fas fa-mobile-alt"></i>
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

// Registo do Service Worker
if ('serviceWorker' in navigator) {
  window.addEventListener('load', () => {
    navigator.serviceWorker.register('js/sw.js', { scope: './' }).then(reg => {
      
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
