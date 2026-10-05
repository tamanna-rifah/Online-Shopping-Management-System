<!-- chat_widget.php - AI Shopping Assistant Widget -->
<style>
/* Reset for chat widget */
#ai-chat-widget * {
    box-sizing: border-box;
    font-family: 'Inter', system-ui, -apple-system, sans-serif;
}

#ai-chat-widget {
    position: fixed;
    bottom: 30px;
    right: 30px;
    z-index: 9999;
}

/* Chat Button */
.chat-widget-btn {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    background: linear-gradient(135deg, #6a11cb, #2575fc);
    color: white;
    border: none;
    cursor: pointer;
    box-shadow: 0 10px 25px rgba(37, 117, 252, 0.4);
    display: flex;
    align-items: center;
    justify-content: center;
    transition: transform 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275), box-shadow 0.3s ease;
}

.chat-widget-btn:hover {
    transform: scale(1.1) rotate(5deg);
    box-shadow: 0 15px 35px rgba(37, 117, 252, 0.5);
}

.chat-widget-btn svg {
    width: 30px;
    height: 30px;
    fill: currentColor;
    transition: transform 0.3s ease;
}

/* Chat Window */
.chat-widget-window {
    position: absolute;
    bottom: 80px;
    right: 0;
    width: 350px;
    height: 500px;
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(15px);
    border: 1px solid rgba(255, 255, 255, 0.5);
    border-radius: 20px;
    box-shadow: 0 15px 40px rgba(0, 0, 0, 0.15);
    display: flex;
    flex-direction: column;
    overflow: hidden;
    opacity: 0;
    visibility: hidden;
    transform: translateY(20px) scale(0.95);
    transition: opacity 0.3s ease, transform 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275), visibility 0.3s;
    transform-origin: bottom right;
}

.chat-widget-window.active {
    opacity: 1;
    visibility: visible;
    transform: translateY(0) scale(1);
}

/* Chat Header */
.chat-widget-header {
    background: linear-gradient(135deg, #6a11cb, #2575fc);
    color: white;
    padding: 20px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    box-shadow: 0 4px 15px rgba(0,0,0,0.05);
}

.chat-widget-header h3 {
    margin: 0;
    font-size: 16px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 10px;
}

.chat-widget-header h3 span {
    display: inline-block;
    width: 8px;
    height: 8px;
    background-color: #10b981;
    border-radius: 50%;
    box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.25);
}

.close-chat-btn {
    background: none;
    border: none;
    color: white;
    cursor: pointer;
    font-size: 24px;
    line-height: 1;
    opacity: 0.8;
    transition: opacity 0.2s;
}

.close-chat-btn:hover {
    opacity: 1;
}

/* Chat Messages Area */
.chat-widget-messages {
    flex: 1;
    padding: 20px;
    overflow-y: auto;
    display: flex;
    flex-direction: column;
    gap: 15px;
    background: #f8fafc;
}

/* Scrollbar styling */
.chat-widget-messages::-webkit-scrollbar {
    width: 6px;
}
.chat-widget-messages::-webkit-scrollbar-track {
    background: transparent;
}
.chat-widget-messages::-webkit-scrollbar-thumb {
    background: rgba(0,0,0,0.1);
    border-radius: 10px;
}

.chat-message {
    max-width: 85%;
    padding: 12px 16px;
    border-radius: 18px;
    font-size: 14px;
    line-height: 1.5;
    animation: fadeIn 0.3s ease;
    word-wrap: break-word;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

.chat-message.bot {
    background: white;
    color: #1f2937;
    align-self: flex-start;
    border-bottom-left-radius: 4px;
    box-shadow: 0 4px 10px rgba(0,0,0,0.04);
    border: 1px solid #f1f5f9;
}

.chat-message.user {
    background: linear-gradient(135deg, #6a11cb, #2575fc);
    color: white;
    align-self: flex-end;
    border-bottom-right-radius: 4px;
    box-shadow: 0 4px 12px rgba(37, 117, 252, 0.2);
}

/* Chat Input Area */
.chat-widget-input-area {
    padding: 15px;
    background: white;
    border-top: 1px solid #e2e8f0;
    display: flex;
    gap: 10px;
}

.chat-widget-input-area input {
    flex: 1;
    padding: 12px 16px;
    border: 1px solid #e2e8f0;
    border-radius: 20px;
    outline: none;
    font-size: 14px;
    transition: border-color 0.2s, box-shadow 0.2s;
    background: #f8fafc;
}

.chat-widget-input-area input:focus {
    border-color: #2575fc;
    box-shadow: 0 0 0 3px rgba(37, 117, 252, 0.1);
    background: white;
}

.chat-widget-input-area button {
    width: 45px;
    height: 45px;
    border-radius: 50%;
    background: #2575fc;
    color: white;
    border: none;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: background 0.2s, transform 0.2s;
}

.chat-widget-input-area button:hover {
    background: #1d4ed8;
    transform: scale(1.05);
}

.chat-widget-input-area button svg {
    width: 18px;
    height: 18px;
    fill: currentColor;
    margin-left: 2px;
}

.chat-widget-input-area button:disabled {
    background: #94a3b8;
    cursor: not-allowed;
    transform: none;
}

/* Typing Indicator */
.typing-indicator {
    display: none;
    align-self: flex-start;
    background: white;
    padding: 12px 16px;
    border-radius: 18px;
    border-bottom-left-radius: 4px;
    box-shadow: 0 4px 10px rgba(0,0,0,0.04);
    border: 1px solid #f1f5f9;
}

.typing-indicator span {
    display: inline-block;
    width: 6px;
    height: 6px;
    background-color: #94a3b8;
    border-radius: 50%;
    margin: 0 2px;
    animation: typing 1.4s infinite ease-in-out both;
}

.typing-indicator span:nth-child(1) { animation-delay: -0.32s; }
.typing-indicator span:nth-child(2) { animation-delay: -0.16s; }

@keyframes typing {
    0%, 80%, 100% { transform: scale(0); }
    40% { transform: scale(1); }
}

@media (max-width: 480px) {
    .chat-widget-window {
        width: calc(100vw - 40px);
        right: -10px;
        bottom: 70px;
        height: 60vh;
    }
}
</style>

<div id="ai-chat-widget">
    <!-- Chat Window -->
    <div class="chat-widget-window" id="chat-window">
        <div class="chat-widget-header">
            <h3><span></span> AI Assistant</h3>
            <div style="display: flex; gap: 10px; align-items: center;">
                <select id="ai-model-select" style="background: rgba(255,255,255,0.2); border: 1px solid rgba(255,255,255,0.4); color: white; border-radius: 12px; padding: 4px 8px; font-size: 12px; outline: none; cursor: pointer;">
                    <option value="gemini" style="color: black;">Gemini</option>
                    <option value="openai" style="color: black;">OpenAI (OSS)</option>
                </select>
                <button class="close-chat-btn" id="close-chat">&times;</button>
            </div>
        </div>
        
        <div class="chat-widget-messages" id="chat-messages">
            <div class="chat-message bot">
                Hi there! 👋 I'm your AI shopping assistant. How can I help you find the perfect dress today?
            </div>
            <div class="typing-indicator" id="typing-indicator">
                <span></span><span></span><span></span>
            </div>
        </div>
        
        <div class="chat-widget-input-area">
            <input type="text" id="chat-input" placeholder="Type your message..." autocomplete="off" />
            <button id="send-chat">
                <svg viewBox="0 0 24 24"><path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/></svg>
            </button>
        </div>
    </div>

    <!-- Floating Button -->
    <button class="chat-widget-btn" id="chat-toggle">
        <svg viewBox="0 0 24 24"><path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm0 14H6l-2 2V4h16v12z"/></svg>
    </button>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const chatToggle = document.getElementById('chat-toggle');
    const closeChat = document.getElementById('close-chat');
    const chatWindow = document.getElementById('chat-window');
    const chatMessages = document.getElementById('chat-messages');
    const chatInput = document.getElementById('chat-input');
    const sendChat = document.getElementById('send-chat');
    const typingIndicator = document.getElementById('typing-indicator');

    // Toggle Chat Window
    function toggleChat() {
        chatWindow.classList.toggle('active');
        if (chatWindow.classList.contains('active')) {
            setTimeout(() => chatInput.focus(), 300);
            scrollToBottom();
        }
    }

    chatToggle.addEventListener('click', toggleChat);
    closeChat.addEventListener('click', toggleChat);

    function scrollToBottom() {
        chatMessages.scrollTop = chatMessages.scrollHeight;
    }

    function addMessage(text, sender) {
        const msgDiv = document.createElement('div');
        msgDiv.classList.add('chat-message', sender);
        
        // Basic Markdown-like formatting (bold)
        let formattedText = text.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
        // Newlines to br
        formattedText = formattedText.replace(/\n/g, '<br>');
        
        msgDiv.innerHTML = formattedText;
        
        // Insert before typing indicator
        chatMessages.insertBefore(msgDiv, typingIndicator);
        scrollToBottom();
    }

    async function sendMessage() {
        const text = chatInput.value.trim();
        if (!text) return;

        // Add user message
        addMessage(text, 'user');
        chatInput.value = '';
        
        // Show typing indicator
        typingIndicator.style.display = 'block';
        chatInput.disabled = true;
        sendChat.disabled = true;
        scrollToBottom();

        try {
            const selectedModel = document.getElementById('ai-model-select').value;
            const response = await fetch('ai_chat_handler.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ message: text, model: selectedModel })
            });

            const data = await response.json();
            
            typingIndicator.style.display = 'none';
            chatInput.disabled = false;
            sendChat.disabled = false;
            
            if (data.reply) {
                addMessage(data.reply, 'bot');
            } else if (data.error) {
                addMessage("Oops! " + data.error, 'bot');
            } else {
                addMessage("Sorry, I encountered an unexpected error.", 'bot');
            }
            
        } catch (error) {
            typingIndicator.style.display = 'none';
            chatInput.disabled = false;
            sendChat.disabled = false;
            addMessage("Network error. Please try again.", 'bot');
        }
        
        setTimeout(() => chatInput.focus(), 50);
    }

    sendChat.addEventListener('click', sendMessage);
    
    chatInput.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            sendMessage();
        }
    });
});
</script>
