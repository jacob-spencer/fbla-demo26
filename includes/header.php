<!DOCTYPE html>
<?php 
if (file_exists(substr(__DIR__, 0, -9).'/maintenance.flag')) {
    header('Location: /maintenance.php');
    exit;
}
?>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? $pageTitle . ' - ' . APP_NAME : APP_NAME; ?></title>
    
    <!-- PWA Components -->
    <link rel="manifest" href="/manifest.json">
    
    <meta name="description" content="<?php echo isset($pageDescription) ? $pageDescription : 'Mainstreet - Discover and connect with local businesses in your area'; ?>">
    
    <!-- Markdown tool for AI chatbot integration -->
    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>

    <!-- Tailwind CSS config -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        background: 'hsl(214, 31%, 8%)',
                        foreground: 'hsl(213, 31%, 91%)',
                        card: 'hsl(214, 30%, 12%)',
                        'card-foreground': 'hsl(213, 31%, 88%)',
                        primary: 'hsl(217, 91%, 60%)',
                        'primary-foreground': 'hsl(0, 0%, 100%)',
                        secondary: 'hsl(228, 94%, 67%)',
                        'secondary-foreground': 'hsl(0, 0%, 100%)',
                        muted: 'hsl(214, 30%, 15%)',
                        'muted-foreground': 'hsl(214, 15%, 65%)',
                        accent: 'hsl(45, 93%, 58%)',
                        'accent-foreground': 'hsl(0, 0%, 100%)',
                        destructive: 'hsl(0, 84%, 60%)',
                        'destructive-foreground': 'hsl(0, 0%, 100%)',
                        border: 'hsl(214, 20%, 20%)',
                        input: 'hsl(214, 25%, 18%)',
                        ring: 'hsl(217, 91%, 60%)'
                    },
                    fontFamily: {
                        sans: ['Inter', 'sans-serif']
                    }
                }
            }
        }
    </script>
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    
    <style>
        body { 
            font-family: 'Inter', sans-serif; 
            background-color: hsl(214, 31%, 8%);
            color: hsl(213, 31%, 91%);
        }
        .hero-gradient {
            background: url('fbla-waves.png');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
        }
        .business-card {
            transition: all 0.2s ease-in-out;
        }
        .business-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px -8px rgba(0, 0, 0, 0.1);
        }
    </style>
</head>


<body class="bg-background text-foreground">

<!-- Styles for the widget -->
<style>
  /* Floating button */
    #ai-chat-button {
        position: fixed;
        right: 20px;
        bottom: 24px;
        width: 56px;
        height: 56px;
        border-radius: 999px;
        background: linear-gradient(0deg, #5b8cff, #3c0cef);
        box-shadow: 0 8px 30px rgba(11, 26, 60, 0.45);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 99999;
        cursor: pointer;
        overflow: visible;
        animation: ai-button-gradient 10s ease infinite;
        transition: .3s ease;
    }
    #chat-icon {
        animation: chat-icon 10s ease infinite;
    }
    @keyframes ai-button-gradient {
        0%{transform: rotate(0deg);}
        50%{transform: rotate(135deg);}
        100%{transform: rotate(0deg);}
    }
    @keyframes chat-icon {
        0%{transform: rotate(0deg);}
        50%{transform: rotate(-135deg);}
        100%{transform: rotate(0deg);}
    }

  #ai-chat-button:focus { outline: 2px solid rgba(255,255,255,0.12); }
    #ai-chat-button:hover {
      width: 64px;
      height: 64px;
    }
  /* Chat modal */
  #ai-chat-modal {
    position: fixed;
    right: 20px;
    bottom: 90px;
    width: 360px;
    max-width: calc(100% - 48px);
    height: 520px;
    max-height: calc(100% - 120px);
    background: linear-gradient(180deg, rgba(18,20,28,0.98), rgba(14,16,22,0.98));
    border-radius: 16px;
    box-shadow: 0 20px 60px rgba(3,6,23,0.6);
    z-index: 99998;
    display: none; /* toggled by JS */
    flex-direction: column;
    overflow: hidden;
    border: 1px solid rgba(255,255,255,0.04);
  }

  #ai-chat-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 12px 14px;
    border-bottom: 1px solid rgba(255,255,255,0.03);
    background: linear-gradient(180deg, rgba(255,255,255,0.01), rgba(255,255,255,0.00));
  }
  #ai-chat-title { font-weight: 600; font-size: 14px; color: white; }

  #ai-chat-body {
    padding: 12px;
    overflow-y: auto;
    flex: 1 1 auto;
    background: transparent;
  }

  .ai-msg {
    display: block;
    margin-bottom: 10px;
    max-width: 86%;
    padding: 10px 12px;
    border-radius: 12px;
    font-size: 13px;
    line-height: 1.35;
  }
  .ai-msg.user {
    margin-left: auto;
    background: rgba(255,255,255,0.06);
    color: #fff;
    border-bottom-right-radius: 6px;
  }
  .ai-msg.bot {
    margin-right: auto;
    background: rgba(255,255,255,0.03);
    color: #e6eefb;
    border-bottom-left-radius: 6px;
  }
  .ai-typing {
    height: 8px;
    width: 36px;
    background: linear-gradient(90deg, rgba(255,255,255,0.12), rgba(255,255,255,0.06));
    border-radius: 8px;
    display:inline-block;
  }

  #ai-chat-footer {
    border-top: 1px solid rgba(255,255,255,0.03);
    padding: 10px;
    display: flex;
    gap: 8px;
    align-items: center;
    background: linear-gradient(180deg, rgba(255,255,255,0.00), rgba(255,255,255,0.01));
  }

  #ai-chat-input {
    flex: 1 1 auto;
    padding: 10px 12px;
    border-radius: 10px;
    background: rgba(255,255,255,0.02);
    color: #fff;
    border: 1px solid rgba(255,255,255,0.03);
    font-size: 14px;
  }
  #ai-chat-send {
    background: linear-gradient(135deg,#7b5cff,#5b8cff);
    color: #fff;
    padding: 10px 12px;
    border-radius: 10px;
    border: none;
    cursor: pointer;
    font-weight: 600;
  }
  #ai-chat-send:disabled { opacity: 0.6; cursor: not-allowed; }

  /* Small screen adjustments */
  @media (max-width: 480px) {
    #ai-chat-modal { right: 12px; left: 12px; bottom: 80px; width: auto; height: 60vh; }
    #ai-chat-button { right: 12px; bottom: 20px; }
  }
</style>

<!-- Floating chat button -->
<div>
    <div id="ai-chat-button" aria-label="Open chat" title="Chat with us" role="button" tabindex="0">
      <svg id="chat-icon" width="22" height="22" viewBox="0 0 24 24" fill="none" aria-hidden>
        <path d="M21 15a2 2 0 0 1-2 2H8l-5 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z" fill="white" />
      </svg>
    </div>
</div>
<!-- Chat modal -->
<div id="ai-chat-modal" role="dialog" aria-modal="true" aria-labelledby="ai-chat-title">
  <div id="ai-chat-header">
    <div style="display:flex;align-items:center;gap:10px">
      <div style="width:36px;height:36px;border-radius:8px;background:linear-gradient(135deg,#5b8cff,#7b5cff);display:flex;align-items:center;justify-content:center">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden>
          <path d="M12 2C6.48 2 2 5.58 2 10c0 1.93.98 3.66 2.64 5.06L4 19l4-1.78C9.11 17.54 10.53 18 12 18c5.52 0 10-3.58 10-8s-4.48-8-10-8z" fill="white"/>
        </svg>
      </div>
      <div id="ai-chat-title">Scout AI</div>
    </div>
    <div style="display:flex;gap:8px;align-items:center">
      <button id="ai-chat-clear" title="Clear conversation" aria-label="Clear conversation" style="background:none;border:none;color:rgba(255,255,255,0.6);cursor:pointer">Clear</button>
      <button id="ai-chat-close" aria-label="Close chat" style="background:none;border:none;color:rgba(255,255,255,0.9);cursor:pointer;font-weight:700">×</button>
    </div>
  </div>

  <div id="ai-chat-body" aria-live="polite" tabindex="0"></div>

  <div id="ai-chat-footer">
    <input id="ai-chat-input" placeholder="Ask about anything..." aria-label="Type your message" />
    <button id="ai-chat-send">Send</button>
  </div>
</div>

<!-- Client script: minimal chat UX and server proxy integration -->
<script>
(function(){
  const btn = document.getElementById('ai-chat-button');
  const modal = document.getElementById('ai-chat-modal');
  const closeBtn = document.getElementById('ai-chat-close');
  const clearBtn = document.getElementById('ai-chat-clear');
  const bodyEl = document.getElementById('ai-chat-body');
  const inputEl = document.getElementById('ai-chat-input');
  const sendBtn = document.getElementById('ai-chat-send');

  // Simple session id for server to tie messages (not secure, just persistence key)
  const sessionId = localStorage.getItem('ai_chat_sid') || ('sid-' + Date.now() + '-' + Math.random().toString(36).slice(2,9));
  localStorage.setItem('ai_chat_sid', sessionId);

  // Load history
  const HISTORY_KEY = 'ai_chat_history_v1';
  function loadHistory(){
    try {
      const raw = localStorage.getItem(HISTORY_KEY);
      return raw ? JSON.parse(raw) : [];
    } catch(e) { return []; }
  }
  function saveHistory(messages){
    localStorage.setItem(HISTORY_KEY, JSON.stringify(messages));
  }

  function appendMessage(text, who){
    const el = document.createElement('div');
    el.className = 'ai-msg ' + (who === 'user' ? 'user' : 'bot');
    el.innerHTML = text;
    bodyEl.appendChild(el);
    bodyEl.scrollTop = bodyEl.scrollHeight;
  }

  function renderHistory(){
    bodyEl.innerHTML = '';
    const history = loadHistory();
    history.forEach(m => appendMessage(m.text, m.role));
  }

  // Typing indicator
  function showTyping() {
    const t = document.createElement('div');
    t.className = 'ai-msg bot';
    t.id = 'ai-typing-indicator';
    t.innerHTML = '<span class="ai-typing" aria-hidden></span> thinking…';
    bodyEl.appendChild(t);
    bodyEl.scrollTop = bodyEl.scrollHeight;
  }
  function hideTyping() {
    const t = document.getElementById('ai-typing-indicator');
    if (t) t.remove();
  }

  // Toggle modal
  function openChat() {
    modal.style.display = 'flex';
    inputEl.focus();
    renderHistory();
  }
  function closeChat() {
    modal.style.display = 'none';
    btn.focus();
  }

  // Send message to backend
  async function sendMessage(text){
    if (!text || text.trim() === '') return;
    const message = text.trim();
    // Persist user message
    const history = loadHistory();
    history.push({ role: 'user', text: message, ts: Date.now() });
    saveHistory(history);
    appendMessage(message, 'user');
    inputEl.value = '';
    sendBtn.disabled = true;
    showTyping();

    try {
        // POST to your server endpoint (Groq/OpenAI)
        const res = await fetch('/api/ai-chat.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ 
              session_id: sessionId, 
              message: message,
              page_url: window.location.href,   // send full URL
              page_path: window.location.pathname  // optional: just path
          })
        });

    
        if (!res.ok) {
            throw new Error('Server error: ' + res.status);
        }
    
        // Expect JSON { reply: "<HTML or Markdown>" } 
        const payload = await res.json();
        let reply = payload && payload.reply ? payload.reply : 'Sorry, something went wrong.';
        hideTyping();
    
        /*
        Should no longer be needed, AI prompt-engineered into returning raw HTML
        // --- Render Markdown to HTML if needed ---
        // If your backend sends Markdown, convert it using marked.js
        if (window.marked) {
            reply = marked.parse(reply);
        }
        */
        
        // Save bot response
        const hist2 = loadHistory();
        hist2.push({ role: 'bot', text: reply, ts: Date.now() });
        saveHistory(hist2);
    
        // Append message as HTML
        const el = document.createElement('div');
        el.className = 'ai-msg bot';
        el.innerHTML = reply;
        bodyEl.appendChild(el);
        bodyEl.scrollTop = bodyEl.scrollHeight;
    
    } 
 catch (err) {
      hideTyping();
      appendMessage('There was an error connecting to the chat service. Try again later.', 'bot');
      console.error('AI chat error:', err);
    } finally {
      sendBtn.disabled = false;
    }
  }

  // UI events
  btn.addEventListener('click', () => {
    if (modal.style.display === 'flex') closeChat(); else openChat();
  });
  btn.addEventListener('keydown', (e) => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); btn.click(); } });

  closeBtn.addEventListener('click', closeChat);
  clearBtn.addEventListener('click', async () => {
    // Clear client-side history
    localStorage.removeItem(HISTORY_KEY);
    renderHistory();

    // Clear server-side memory
    try {
        const res = await fetch('/api/ai-chat.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ session_id: sessionId, clear_memory: true })
        });
        const data = await res.json();
        console.log(data.reply); 
    } catch (err) {
        console.error('Error clearing server memory:', err);
    }

    inputEl.focus();
});


  sendBtn.addEventListener('click', () => sendMessage(inputEl.value));
  inputEl.addEventListener('keydown', (e) => {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault();
      sendBtn.click();
    }
  });

  // initial render (button shows, modal hidden)
  renderHistory();

  // Optional: welcome message once
  const welcomed = localStorage.getItem('ai_chat_welcomed_v1');
  if (!welcomed) {
    const welcome = "Hi! I can help you find businesses, deals, or answer questions about this site. Ask me anything.";
    const hist = loadHistory();
    hist.push({ role: 'bot', text: welcome, ts: Date.now() });
    saveHistory(hist);
    localStorage.setItem('ai_chat_welcomed_v1', '1');
  }
})();
</script>

<!-- --- end: AI Chat widget --- -->
<header class="bg-card/70 backdrop-blur-sm fixed w-full top-0 z-50">
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative h-16 flex items-center justify-between">
    <!-- Logo -->
    <div class="flex items-center space-x-4">
      <a href="/" class="flex items-center space-x-2">
        <div class="w-8 h-8 bg-primary rounded-lg flex items-center justify-center">
          <i data-lucide="map-pin" class="text-primary-foreground w-4 h-4"></i>
        </div>
        <span class="text-xl font-bold text-primary"><?php echo APP_NAME; ?></span>
      </a>
    </div>

    <!-- Center nav -->
    <nav class="hidden md:flex items-center space-x-8 absolute left-1/2 transform -translate-x-1/2">
      <a href="/discover" class="transition-colors text-foreground hover:text-primary">
          <i data-lucide="globe" class="w-4 h-4 mr-2 inline"></i> Discover
          </a>
      <a href="/search" class="transition-colors text-foreground hover:text-primary">
        <i data-lucide="search" class="w-4 h-4 mr-2 inline"></i> Search
      </a>
      <a href="/deals" class="transition-colors text-foreground hover:text-primary">
          <i data-lucide="piggy-bank" class="w-4 h-4 mr-2 inline"></i> Deals
          </a>
      <a href="/map" class="transition-colors text-foreground hover:text-primary">
        <i data-lucide="map" class="w-4 h-4 mr-2 inline"></i> Map
      </a>
      <?php if (getCurrentUser() && getCurrentUser()['is_business_owner']): ?>
      <a href="/my-business" class="transition-colors text-foreground hover:text-primary">
        <i data-lucide="building-2" class="w-4 h-4 mr-2 inline"></i> My Business
      </a>
      <?php endif; ?>
    </nav>

    <!-- Right side -->

      <div class="flex items-center space-x-4">
        <!-- Desktop user links -->
        <div class="hidden md:flex items-center space-x-4">
        <?php if (isLoggedIn()): ?>
            <?php $user = getCurrentUser(); ?>
        
            <!-- Profile dropdown -->
            <div class="relative group">
              <button class="flex items-center space-x-2 focus:outline-none">
                <?php if ($user['profile_image_url']): ?>
                  <img src="<?php echo escapeHtml($user['profile_image_url']); ?>" 
                       alt="Profile" 
                       class="w-8 h-8 rounded-full object-cover border border-border">
                <?php else: ?>
                  <div class="w-8 h-8 bg-primary/20 rounded-full flex items-center justify-center">
                    <i data-lucide="user" class="w-4 h-4 text-primary"></i>
                  </div>
                <?php endif; ?>
              </button>
            
              <!-- Dropdown menu -->
              <div 
                class="absolute right-0 mt-6 w-48 bg-card backdrop-blur-sm border border-border rounded-lg shadow-lg 
                       opacity-0 scale-95 invisible group-hover:visible group-hover:opacity-100 group-hover:scale-100 
                       transition-all duration-150 origin-top-right z-50"
              >
                <ul class="py-2 text-sm text-foreground">
                  <li>
                    <a href="/my-profile" class="block px-4 py-2 hover:bg-muted hover:text-primary transition-colors">
                      <i data-lucide="user" class="w-4 h-4 mr-2 inline"></i> My Profile
                    </a>
                  </li>
                  <li>
                    <a href="/bookmarks" class="block px-4 py-2 text-accent hover:bg-muted hover:text-primary transition-colors">
                      <i data-lucide="bookmark" class="w-4 h-4 mr-2 inline"></i> Bookmarks
                    </a>
                  </li>
                  <li><hr class="border-border my-1"></li>
                  <li>
                    <a href="/logout" class="block px-4 py-2 text-destructive hover:bg-muted hover:text-destructive transition-colors">
                      <i data-lucide="log-out" class="w-4 h-4 mr-2 inline"></i> Sign Out
                    </a>
                  </li>
                </ul>
              </div>
            </div>

         <?php else: ?>
            <div class="relative group">
          <button class="flex items-center space-x-2 focus:outline-none">
              <div class="w-8 h-8 bg-primary/20 rounded-full flex items-center justify-center">
                <i data-lucide="user" class="w-4 h-4 text-primary"></i>
              </div>
          </button>
        
          <!-- Dropdown menu -->
          <div 
            class="absolute right-0 mt-6 w-48 bg-card backdrop-blur-sm border border-border rounded-lg shadow-lg 
                   opacity-0 scale-95 invisible group-hover:visible group-hover:opacity-100 group-hover:scale-100 
                   transition-all duration-150 origin-top-right z-50"
          >
            <ul class="py-2 text-sm text-foreground">
              <li>
                <a href="/auth" class="block px-4 py-2 hover:bg-muted hover:text-primary transition-colors">
                  <i data-lucide="log-in" class="w-4 h-4 mr-2 inline"></i> Log In
                </a>
              </li>
              <li>
                <a href="/auth?register" class="block px-4 py-2 hover:bg-muted hover:text-primary transition-colors">
                  <i data-lucide="user-plus" class="w-4 h-4 mr-2 inline"></i> Sign Up
                </a>
              </li>
            </ul>
          </div>
        </div>
            
          <?php endif; ?>
        </div>

        <!-- Hamburger button (mobile only) -->
        <button id="menu-toggle" class="md:hidden relative w-8 h-8 flex flex-col justify-center items-center">
          <span class="bar w-6 h-0.5 bg-foreground rounded transition-all duration-300 mb-1.5"></span>
          <span class="bar w-6 h-0.5 bg-foreground rounded transition-all duration-300 mb-1.5"></span>
          <span class="bar w-6 h-0.5 bg-foreground rounded transition-all duration-300"></span>
        </button>
      </div>

  </div>
</header>
<!-- Mobile Menu -->
<nav id="mobile-menu" class="fixed top-16 left-0 w-full bg-card/40 backdrop-blur-lg 
    transform -translate-y-full transition-transform duration-300 ease-in-out z-40 md:hidden">
  <ul class="flex flex-col px-4 py-4 space-y-1 text-foreground text-base font-medium">
    <li><a href="/discover" class="block py-3 hover:text-primary">
          <i data-lucide="globe" class="w-4 h-4 mr-2 inline"></i> Discover</a></li>
    <li><a href="/search" class="block py-3 hover:text-primary">
      <i data-lucide="search" class="w-4 h-4 mr-2 inline"></i> Search
    </a></li>
    <li><a href="/deals" class="block py-3 hover:text-primary">
          <i data-lucide="piggy-bank" class="w-4 h-4 mr-2 inline"></i> Deals</a></li>
    <li><a href="/map" class="block py-3 hover:text-primary">
      <i data-lucide="map" class="w-4 h-4 mr-2 inline"></i> Map
    </a></li>

    <?php if (getCurrentUser() && getCurrentUser()['is_business_owner']): ?>
    <li><a href="/my-business" class="block py-3 hover:text-primary">
      <i data-lucide="building-2" class="w-4 h-4 mr-2 inline"></i> My Business
    </a></li>
    <?php endif; ?>

    <?php if (isLoggedIn()): ?>
      <li><a href="/my-profile" class="block py-3 hover:text-primary">
        <i data-lucide="user" class="w-4 h-4 mr-2 inline"></i> My Profile
      </a></li>
      <li><a href="/bookmarks" class="block py-3 text-accent hover:text-primary">
        <i data-lucide="bookmark" class="w-4 h-4 mr-2 inline"></i> Bookmarks
      </a></li>
      <li><a href="/logout" class="block py-3 text-destructive hover:text-destructive">
        <i data-lucide="log-out" class="w-4 h-4 mr-2 inline"></i> Sign Out
      </a></li>
    <?php else: ?>
      <li><a href="/auth" class="block py-3 hover:text-primary">
        <i data-lucide="log-in" class="w-4 h-4 mr-2 inline"></i> Log In
      </a></li>
      <li><a href="/auth?register" class="block py-3 hover:text-primary">
        <i data-lucide="user-plus" class="w-4 h-4 mr-2 inline"></i> Sign Up
      </a></li>
    <?php endif; ?>
  </ul>
</nav>

</body>

<script>
  lucide.createIcons();

  const menuToggle = document.getElementById('menu-toggle');
  const bars = menuToggle.querySelectorAll('.bar');
  const mobileMenu = document.getElementById('mobile-menu');

  let isOpen = false;
  menuToggle.addEventListener('click', () => {
    mobileMenu.classList.toggle('-translate-y-full');
    isOpen = !isOpen;

    // animate bars into X
    if (isOpen) {
      bars[0].classList.add('rotate-45', 'translate-y-2');
      bars[1].classList.add('opacity-0');
      bars[2].classList.add('-rotate-45', '-translate-y-2');
    } else {
      bars[0].classList.remove('rotate-45', 'translate-y-2');
      bars[1].classList.remove('opacity-0');
      bars[2].classList.remove('-rotate-45', '-translate-y-2');
    }
  });
  
  document.addEventListener('DOMContentLoaded', () => {
    new ShaderBackground('shader-canvas', '/assets/purple.jpg');
  });
</script>
