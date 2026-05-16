import axios from 'axios';

document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('chat-form');
    const input = document.getElementById('chat-input');
    const submitBtn = document.getElementById('chat-submit');
    const spinner = document.getElementById('loading-spinner');
    const messagesContainer = document.getElementById('chat-messages');
    const chatTitle = document.getElementById('chat-title');
    const chatStats = document.getElementById('chat-stats');
    const clearBtn = document.getElementById('clear-history');

    if (!form || !input) return;

    // Scroll vers le bas au chargement
    messagesContainer.scrollTop = messagesContainer.scrollHeight;

    // Gestion de l'envoi
    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const message = input.value.trim();
        if (!message) return;

        setLoading(true);
        appendMessage(message, 'user');
        input.value = '';

        try {
            const { data } = await axios.post('/chatbot/message', {
                session_id: window.chatbotConfig?.sessionId,
                message: message
            });

            if (data.success) {
                appendMessage(data.message.content, 'assistant');
                updateSessionInfo(data.session);
            } else {
                appendMessage(data.message || 'Erreur inattendue.', 'assistant', true);
            }
        } catch (error) {
            console.error('Chat error:', error);
            const msg = error.response?.data?.message || 'Impossible de contacter le serveur.';
            appendMessage(msg, 'assistant', true);
        } finally {
            setLoading(false);
            input.focus();
        }
    });

    // Effacer l'historique
    if (clearBtn && window.chatbotConfig?.sessionId) {
        clearBtn.addEventListener('click', async () => {
            if (!confirm('Supprimer toute cette conversation ?')) return;
            try {
                await axios.delete(`/chatbot/session/${window.chatbotConfig.sessionId}`);
                location.reload();
            } catch (error) {
                alert('Erreur lors de la suppression.');
            }
        });
    }

    function setLoading(loading) {
        submitBtn.disabled = loading;
        input.disabled = loading;
        spinner.classList.toggle('hidden', !loading);
    }

    function appendMessage(text, role, isError = false) {
        const div = document.createElement('div');
        div.className = `flex ${role === 'user' ? 'justify-end' : 'justify-start'}`;
        
        const bubble = document.createElement('div');
        bubble.className = `max-w-[85%] p-3 rounded-2xl text-sm ${
            role === 'user' 
                ? 'bg-indigo-600 text-white rounded-tr-none' 
                : `bg-gray-100 text-gray-800 rounded-tl-none ${isError ? 'bg-red-100 text-red-700' : ''}`
        }`;
        
        bubble.innerHTML = `<div>${text.replace(/\n/g, '<br>')}</div><div class="text-xs opacity-70 mt-1">${new Date().toLocaleTimeString('fr-FR', {hour: '2-digit', minute:'2-digit'})}</div>`;
        
        div.appendChild(bubble);
        messagesContainer.appendChild(div);
        messagesContainer.scrollTop = messagesContainer.scrollHeight;
    }

    function updateSessionInfo(session) {
        if (chatTitle) chatTitle.textContent = session.title;
        if (chatStats) chatStats.textContent = `${session.total_tokens} tokens utilisés`;
        if (window.chatbotConfig) window.chatbotConfig.sessionId = session.id;
    }
});