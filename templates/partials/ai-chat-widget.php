<!-- AI Chat Widget -->
<div id="ai-chat-widget" class="ai-chat-widget">
  <button id="ai-chat-toggle" class="ai-chat-toggle" aria-label="Toggle AI Chat">
    <svg class="ai-chat-icon" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
      <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2 2h3a2 2 0 0 1 2 2h13a2 2 0 0 1 2-2M10 20a2 2 0 0 1-2 2m-6-10a2 2 0 0 1 2-2h6a2 2 0 0 1 2 2"></path>
    </svg>
    <span class="ai-chat-label">Ask AI</span>
  </button>
  
  <div id="ai-chat-window" class="ai-chat-window" aria-hidden="true">
    <div class="ai-chat-header">
      <div class="ai-chat-title">
        <svg class="ai-chat-avatar" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2 2h3a2 2 0 0 1 2 2h13a2 2 0 0 1 2-2M10 20a2 2 0 0 1-2 2m-6-10a2 2 0 0 1 2-2h6a2 2 0 0 1 2 2"></path>
        </svg>
        <span>Patriot AI Assistant</span>
      </div>
      <button id="ai-chat-close" class="ai-chat-close" aria-label="Close Chat">×</button>
    </div>
    
    <div id="ai-chat-messages" class="ai-chat-messages">
      <div class="ai-chat-message ai-chat-bot">
        <svg class="ai-chat-avatar" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2 2h3a2 2 0 0 1 2 2h13a2 2 0 0 1 2-2M10 20a2 2 0 0 1-2 2m-6-10a2 2 0 0 1 2-2h6a2 2 0 0 1 2 2"></path>
        </svg>
        <div class="ai-chat-content">
          <p>Hello! I'm the Patriot Pest Control AI assistant. I can help you with:</p>
          <ul>
            <li>Information about our services</li>
            <li>Pricing and quotes</li>
            <li>Service areas and availability</li>
            <li>Scheduling appointments</li>
            <li>General pest control questions</li>
          </ul>
          <p>How can I help you today?</p>
        </div>
      </div>
    </div>
    
    <div class="ai-chat-input-area">
      <form id="ai-chat-form">
        <input 
          type="text" 
          id="ai-chat-input" 
          placeholder="Type your message..." 
          aria-label="Chat message"
          autocomplete="off"
        >
        <button type="submit" class="ai-chat-send" aria-label="Send message">
          <span>Send</span>
        </button>
      </form>
      <div class="ai-chat-disclaimer">
        <small>AI assistant powered by Patriot Pest Control. For urgent matters, please call us directly.</small>
      </div>
    </div>
  </div>
</div>

<style>
/* AI Chat Widget Styles */
.ai-chat-widget {
  position: fixed;
  bottom: 100px;
  right: 20px;
  z-index: 9999;
  font-family: var(--body);
}

.ai-chat-toggle {
  background: var(--orange);
  color: var(--olive-950);
  border: none;
  border-radius: 50px;
  padding: 12px 20px;
  font-size: 16px;
  font-weight: 600;
  cursor: pointer;
  box-shadow: 0 4px 12px rgba(0,0,0,.2);
  display: flex;
  align-items: center;
  gap: 8px;
  transition: transform .2s, box-shadow .2s;
  min-height: 48px;
}

.ai-chat-toggle:hover {
  transform: translateY(-2px);
  box-shadow: 0 6px 16px rgba(0,0,0,.25);
}

.ai-chat-icon {
  font-size: 20px;
}

.ai-chat-label {
  font-size: 14px;
}

.ai-chat-window {
  position: absolute;
  bottom: 70px;
  right: 0;
  width: 350px;
  max-width: calc(100vw - 40px);
  height: 500px;
  max-height: calc(100vh - 120px);
  background: #ffffff;
  border-radius: 12px;
  box-shadow: 0 8px 32px rgba(0,0,0,.3);
  display: none;
  flex-direction: column;
  overflow: hidden;
  opacity: 0;
  transform: translateY(10px);
  transition: opacity .3s, transform .3s;
}

.ai-chat-window.open {
  display: flex;
  opacity: 1;
  transform: translateY(0);
}

.ai-chat-header {
  background: var(--olive-800);
  color: var(--cream);
  padding: 16px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  border-bottom: 1px solid var(--olive-700);
}

.ai-chat-title {
  display: flex;
  align-items: center;
  gap: 10px;
  font-weight: 600;
  font-size: 16px;
}

.ai-chat-avatar {
  width: 24px;
  height: 24px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.ai-chat-close {
  background: none;
  border: none;
  color: var(--cream);
  font-size: 24px;
  cursor: pointer;
  padding: 4px 8px;
  line-height: 1;
  border-radius: 4px;
  transition: background .2s;
}

.ai-chat-close:hover {
  background: rgba(255,255,255,.1);
}

.ai-chat-messages {
  flex: 1;
  overflow-y: auto;
  padding: 16px;
  background: #fbfaf5;
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.ai-chat-message {
  display: flex;
  gap: 10px;
  max-width: 85%;
}

.ai-chat-bot {
  align-self: flex-start;
}

.ai-chat-user {
  align-self: flex-end;
  flex-direction: row-reverse;
}

.ai-chat-content {
  background: white;
  padding: 12px 16px;
  border-radius: 12px;
  box-shadow: 0 1px 3px rgba(0,0,0,.1);
  font-size: 14px;
  line-height: 1.5;
  color: #23281c;
}

.ai-chat-user .ai-chat-content {
  background: var(--orange);
  color: var(--olive-950);
}

.ai-chat-content p {
  margin: 0 0 8px 0;
}

.ai-chat-content p:last-child {
  margin-bottom: 0;
}

.ai-chat-content ul {
  margin: 8px 0;
  padding-left: 20px;
}

.ai-chat-content li {
  margin-bottom: 4px;
}

.ai-chat-input-area {
  padding: 16px;
  border-top: 1px solid var(--olive-700);
  background: white;
}

#ai-chat-form {
  display: flex;
  gap: 8px;
}

#ai-chat-input {
  flex: 1;
  padding: 10px 14px;
  border: 1px solid var(--olive-500);
  border-radius: 20px;
  font-size: 14px;
  font-family: var(--body);
  outline: none;
}

#ai-chat-input:focus {
  border-color: var(--orange);
  box-shadow: 0 0 0 3px rgba(244,119,46,.18);
}

.ai-chat-send {
  background: var(--orange);
  color: var(--olive-950);
  border: none;
  border-radius: 20px;
  padding: 10px 20px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: background .2s;
}

.ai-chat-send:hover {
  background: var(--orange-hot);
}

.ai-chat-send:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.ai-chat-disclaimer {
  margin-top: 8px;
  text-align: center;
}

.ai-chat-disclaimer small {
  color: var(--olive-300);
  font-size: 11px;
}

/* Mobile responsive */
@media (max-width: 768px) {
  .ai-chat-widget {
    bottom: 80px;
    right: 10px;
  }
  
  .ai-chat-window {
    width: calc(100vw - 20px);
    max-width: none;
    right: -10px;
    bottom: 60px;
  }
  
  .ai-chat-toggle {
    padding: 10px 16px;
    font-size: 14px;
  }
  
  .ai-chat-label {
    display: none;
  }
  
  .ai-chat-icon {
    font-size: 18px;
  }
}

/* Animation for message appearance */
@keyframes messageSlideIn {
  from {
    opacity: 0;
    transform: translateY(10px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.ai-chat-message {
  animation: messageSlideIn .3s ease-out;
}

/* Typing indicator */
.ai-chat-typing {
  display: flex;
  gap: 4px;
  padding: 8px 0;
}

.ai-chat-typing-dot {
  width: 8px;
  height: 8px;
  background: var(--olive-500);
  border-radius: 50%;
  animation: typingBounce 1.4s infinite ease-in-out;
}

.ai-chat-typing-dot:nth-child(2) {
  animation-delay: 0.2s;
}

.ai-chat-typing-dot:nth-child(3) {
  animation-delay: 0.4s;
}

@keyframes typingBounce {
  0%, 60%, 100% {
    transform: translateY(0);
  }
  30% {
    transform: translateY(-6px);
  }
}
</style>

<script>
(function() {
  'use strict';
  
  const toggle = document.getElementById('ai-chat-toggle');
  const window = document.getElementById('ai-chat-window');
  const close = document.getElementById('ai-chat-close');
  const form = document.getElementById('ai-chat-form');
  const input = document.getElementById('ai-chat-input');
  const messages = document.getElementById('ai-chat-messages');
  
  // Toggle chat window
  toggle.addEventListener('click', function() {
    window.classList.toggle('open');
    const isOpen = window.classList.contains('open');
    window.setAttribute('aria-hidden', !isOpen);
    if (isOpen) {
      input.focus();
    }
  });
  
  // Close chat window
  close.addEventListener('click', function() {
    window.classList.remove('open');
    window.setAttribute('aria-hidden', 'true');
  });
  
  // Form submission
  form.addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const message = input.value.trim();
    if (!message) return;
    
    // Add user message
    addMessage(message, 'user');
    input.value = '';
    
    // Show typing indicator
    showTyping();
    
    try {
      const response = await fetch('/api/v1/ai/chat', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          message: message,
          context: {
            page: window.location.pathname,
            userAgent: navigator.userAgent
          }
        })
      });
      
      const data = await response.json();
      
      // Remove typing indicator
      removeTyping();
      
      // Add bot response
      addMessage(data.response, 'bot');
    } catch (error) {
      removeTyping();
      addMessage('Sorry, I encountered an error. Please try again or call us directly at (509) 471-5767.', 'bot');
    }
  });
  
  function addMessage(text, type) {
    const messageDiv = document.createElement('div');
    messageDiv.className = 'ai-chat-message ai-chat-' + type;
    
    const avatar = document.createElement('div');
    avatar.className = 'ai-chat-avatar';
    
    if (type === 'bot') {
      avatar.innerHTML = '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2 2h3a2 2 0 0 1 2 2h13a2 2 0 0 1 2-2M10 20a2 2 0 0 1-2 2m-6-10a2 2 0 0 1 2-2h6a2 2 0 0 1 2 2"></path></svg>';
    } else {
      avatar.innerHTML = '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>';
    }
    
    const content = document.createElement('div');
    content.className = 'ai-chat-content';
    content.innerHTML = '<p>' + text + '</p>';
    
    messageDiv.appendChild(avatar);
    messageDiv.appendChild(content);
    messages.appendChild(messageDiv);
    
    // Scroll to bottom
    messages.scrollTop = messages.scrollHeight;
  }
  
  function showTyping() {
    const typingDiv = document.createElement('div');
    typingDiv.className = 'ai-chat-message ai-chat-bot';
    typingDiv.id = 'ai-chat-typing';
    
    const avatar = document.createElement('div');
    avatar.className = 'ai-chat-avatar';
    avatar.innerHTML = '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2 2h3a2 2 0 0 1 2 2h13a2 2 0 0 1 2-2M10 20a2 2 0 0 1-2 2m-6-10a2 2 0 0 1 2-2h6a2 2 0 0 1 2 2"></path></svg>';
    
    const content = document.createElement('div');
    content.className = 'ai-chat-content';
    content.innerHTML = '<div class="ai-chat-typing"><div class="ai-chat-typing-dot"></div><div class="ai-chat-typing-dot"></div><div class="ai-chat-typing-dot"></div></div>';
    
    typingDiv.appendChild(avatar);
    typingDiv.appendChild(content);
    messages.appendChild(typingDiv);
    
    messages.scrollTop = messages.scrollHeight;
  }
  
  function removeTyping() {
    const typing = document.getElementById('ai-chat-typing');
    if (typing) {
      typing.remove();
    }
  }
  
  // Close on escape key
  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape' && window.classList.contains('open')) {
      window.classList.remove('open');
      window.setAttribute('aria-hidden', 'true');
    }
  });
  
  // Add conversation context from page
  const pageContext = {
    page: window.location.pathname,
    title: document.title,
    referrer: document.referrer
  };
  
  // Store context for later use
  window.aiChatContext = pageContext;
})();
</script>