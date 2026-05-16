{{-- ============================================================ --}}
{{-- resources/views/chatbot/index.blade.php                     --}}
{{-- Interface chatbot IA — HomeBase                             --}}
{{-- ============================================================ --}}

@extends('layouts.app')

@section('title', 'Chatbot IA')
@section('page-title', '🤖 Chatbot IA — Mistral')

@section('content')
<div class="flex gap-6 h-[calc(100vh-8rem)]" x-data="chatbotApp()">

    {{-- ══════════════════════════════════════════════════════ --}}
    {{-- PANNEAU GAUCHE : Liste des sessions                    --}}
    {{-- ══════════════════════════════════════════════════════ --}}
    <aside class="w-72 flex-shrink-0 flex flex-col bg-white dark:bg-gray-900 rounded-2xl border border-gray-200 dark:border-gray-800 overflow-hidden">

        {{-- Header sessions --}}
        <div class="p-4 border-b border-gray-200 dark:border-gray-800">
            <button @click="newSession()"
                    class="w-full flex items-center justify-center gap-2 px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl font-medium text-sm transition-colors">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                </svg>
                Nouvelle conversation
            </button>
        </div>

        {{-- Stats utilisation API --}}
        <div class="px-4 py-3 bg-indigo-50 dark:bg-indigo-900/20 border-b border-indigo-100 dark:border-indigo-800">
            <p class="text-xs font-medium text-indigo-700 dark:text-indigo-400 mb-1">Quota API aujourd'hui</p>
            <div class="flex items-center gap-2">
                <div class="flex-1 bg-indigo-200 dark:bg-indigo-800 rounded-full h-1.5">
                    <div class="bg-indigo-600 h-1.5 rounded-full transition-all"
                         :style="`width: ${Math.min((dayUsage / {{ config('mistral.rate_limit.per_day') }}) * 100, 100)}%`">
                    </div>
                </div>
                <span class="text-xs text-indigo-600 dark:text-indigo-400 font-mono" x-text="`${dayUsage}/{{ config('mistral.rate_limit.per_day') }}`"></span>
            </div>
        </div>

        {{-- Liste des sessions --}}
        <div class="flex-1 overflow-y-auto p-2 space-y-1">
            <template x-for="session in sessions" :key="session.id">
                <div class="group relative">
                    <button @click="loadSession(session.id)"
                            class="w-full text-left px-3 py-2.5 rounded-xl text-sm transition-all"
                            :class="currentSessionId === session.id
                                ? 'bg-indigo-100 dark:bg-indigo-900/40 text-indigo-700 dark:text-indigo-300'
                                : 'text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800'">
                        <p class="font-medium truncate" x-text="session.title"></p>
                        <p class="text-xs opacity-60 mt-0.5" x-text="session.created_at"></p>
                    </button>
                    {{-- Bouton suppression (visible au hover) --}}
                    <button @click.stop="deleteSession(session.id)"
                            class="absolute right-2 top-2 opacity-0 group-hover:opacity-100 p-1 text-gray-400 hover:text-red-500 transition-all">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                        </svg>
                    </button>
                </div>
            </template>

            {{-- État vide --}}
            <template x-if="sessions.length === 0">
                <div class="text-center py-8 text-gray-400 dark:text-gray-600">
                    <p class="text-sm">Aucune conversation</p>
                    <p class="text-xs mt-1">Créez votre première session</p>
                </div>
            </template>
        </div>

        {{-- Sélecteur de modèle --}}
        <div class="p-4 border-t border-gray-200 dark:border-gray-800">
            <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1.5">Modèle IA</label>
            <select x-model="selectedModel"
                    class="w-full text-sm bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg px-3 py-2 text-gray-700 dark:text-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                <option value="mistral-small-latest">Mistral Small (rapide, économique)</option>
                <option value="mistral-medium-latest">Mistral Medium</option>
                <option value="mistral-large-latest">Mistral Large (puissant)</option>
            </select>
        </div>
    </aside>

    {{-- ══════════════════════════════════════════════════════ --}}
    {{-- ZONE DE CHAT PRINCIPALE                                --}}
    {{-- ══════════════════════════════════════════════════════ --}}
    <div class="flex-1 flex flex-col bg-white dark:bg-gray-900 rounded-2xl border border-gray-200 dark:border-gray-800 overflow-hidden">

        {{-- Header chat --}}
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 dark:border-gray-800">
            <div>
                <h2 class="font-semibold text-gray-900 dark:text-white" x-text="currentTitle || 'Nouvelle conversation'"></h2>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                    Tokens utilisés : <span class="font-mono" x-text="totalTokens.toLocaleString('fr-FR')"></span>
                </p>
            </div>
            <div class="flex items-center gap-2">
                {{-- Indicateur de chargement --}}
                <div x-show="isTyping" class="flex items-center gap-2 text-sm text-indigo-600 dark:text-indigo-400">
                    <div class="flex gap-1">
                        <span class="w-1.5 h-1.5 bg-indigo-600 rounded-full animate-bounce" style="animation-delay: 0ms"></span>
                        <span class="w-1.5 h-1.5 bg-indigo-600 rounded-full animate-bounce" style="animation-delay: 150ms"></span>
                        <span class="w-1.5 h-1.5 bg-indigo-600 rounded-full animate-bounce" style="animation-delay: 300ms"></span>
                    </div>
                    <span>Mistral réfléchit…</span>
                </div>
            </div>
        </div>

        {{-- Messages --}}
        <div class="flex-1 overflow-y-auto p-6 space-y-4" id="messages-container" x-ref="messagesContainer">

            {{-- Message de bienvenue (si aucune session) --}}
            <template x-if="messages.length === 0 && !currentSessionId">
                <div class="flex flex-col items-center justify-center h-full text-center py-12">
                    <div class="w-16 h-16 bg-indigo-100 dark:bg-indigo-900/30 rounded-2xl flex items-center justify-center mb-4">
                        <span class="text-3xl">🤖</span>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">Bonjour, je suis HomeBase AI</h3>
                    <p class="text-gray-500 dark:text-gray-400 max-w-sm text-sm leading-relaxed">
                        Propulsé par Mistral AI. Posez-moi vos questions, demandez-moi d'analyser vos tâches, d'améliorer vos notes ou de vous aider dans vos projets.
                    </p>
                    {{-- Suggestions rapides --}}
                    <div class="mt-6 grid grid-cols-2 gap-3 w-full max-w-md">
                        <template x-for="suggestion in quickSuggestions" :key="suggestion">
                            <button @click="sendQuickSuggestion(suggestion)"
                                    class="px-4 py-3 bg-gray-50 dark:bg-gray-800 hover:bg-indigo-50 dark:hover:bg-indigo-900/20 border border-gray-200 dark:border-gray-700 hover:border-indigo-300 rounded-xl text-sm text-gray-600 dark:text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400 transition-all text-left"
                                    x-text="suggestion">
                            </button>
                        </template>
                    </div>
                </div>
            </template>

            {{-- Messages de la conversation --}}
            <template x-for="msg in messages" :key="msg.id || msg.tempId">
                <div class="flex gap-3" :class="msg.role === 'user' ? 'flex-row-reverse' : 'flex-row'">

                    {{-- Avatar --}}
                    <div class="flex-shrink-0 w-8 h-8 rounded-full flex items-center justify-center text-sm font-bold"
                         :class="msg.role === 'user'
                             ? 'bg-indigo-600 text-white'
                             : 'bg-gray-200 dark:bg-gray-700 text-gray-600 dark:text-gray-300'">
                        <span x-text="msg.role === 'user' ? '{{ auth()->user()?->initials ?? 'U' }}' : '🤖'"></span>
                    </div>

                    {{-- Bulle de message --}}
                    <div class="max-w-[75%] space-y-1">
                        <div class="px-4 py-3 rounded-2xl text-sm leading-relaxed"
                             :class="msg.role === 'user'
                                 ? 'bg-indigo-600 text-white rounded-tr-sm'
                                 : 'bg-gray-100 dark:bg-gray-800 text-gray-900 dark:text-white rounded-tl-sm'">

                            {{-- Afficher le markdown de la réponse IA --}}
                            <div x-html="msg.role === 'assistant' ? formatMarkdown(msg.content) : escapeHtml(msg.content)"></div>
                        </div>

                        {{-- Méta-infos --}}
                        <div class="flex items-center gap-2 text-xs text-gray-400"
                             :class="msg.role === 'user' ? 'justify-end' : 'justify-start'">
                            <span x-text="msg.time"></span>
                            <template x-if="msg.tokens">
                                <span x-text="`${msg.tokens} tokens`" class="font-mono"></span>
                            </template>
                        </div>
                    </div>
                </div>
            </template>
        </div>

        {{-- Zone de saisie --}}
        <div class="border-t border-gray-200 dark:border-gray-800 p-4">
            <form @submit.prevent="sendMessage()" class="flex gap-3">
                <div class="flex-1 relative">
                    <textarea
                        x-model="inputMessage"
                        @keydown.enter.prevent="if (!$event.shiftKey) sendMessage()"
                        :disabled="isTyping || !currentSessionId"
                        rows="1"
                        placeholder="Écrivez votre message… (Entrée pour envoyer, Shift+Entrée pour nouvelle ligne)"
                        class="w-full px-4 py-3 bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl text-sm text-gray-900 dark:text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent resize-none transition-colors disabled:opacity-50"
                        style="max-height: 120px; overflow-y: auto;"
                        x-ref="textarea"
                        @input="autoResize($el)">
                    </textarea>
                </div>
                <button type="submit"
                        :disabled="!inputMessage.trim() || isTyping || !currentSessionId"
                        class="flex-shrink-0 w-12 h-12 bg-indigo-600 hover:bg-indigo-700 disabled:bg-gray-300 dark:disabled:bg-gray-700 text-white rounded-xl flex items-center justify-center transition-colors">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
                    </svg>
                </button>
            </form>
            <p class="text-xs text-gray-400 dark:text-gray-600 mt-2 text-center">
                Les réponses sont générées par Mistral AI et peuvent contenir des erreurs. Vérifiez les informations importantes.
            </p>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
/**
 * ============================================================
 * Chatbot Alpine.js App
 * Gère toute la logique du chatbot côté client.
 * ============================================================
 */
function chatbotApp() {
    return {
        // ── État ─────────────────────────────────────────────
        sessions:         @json($sessions ?? []),
        messages:         [],
        currentSessionId: null,
        currentTitle:     '',
        totalTokens:      0,
        inputMessage:     '',
        isTyping:         false,
        selectedModel:    'mistral-small-latest',
        dayUsage:         {{ $usageStats['day_usage'] ?? 0 }},

        // Suggestions rapides
        quickSuggestions: [
            "Aide-moi à planifier ma journée",
            "Résume les bonnes pratiques de code propre",
            "Propose un plan d'entraînement sportif",
            "Explique le machine learning simplement",
        ],

        // ── Initialisation ────────────────────────────────────
        init() {
            // Charger la dernière session automatiquement
            if (this.sessions.length > 0) {
                this.loadSession(this.sessions[0].id);
            }
        },

        // ── Créer une nouvelle session ────────────────────────
        async newSession() {
            try {
                const res = await axios.post('/chatbot/session', {
                    title: 'Nouvelle conversation'
                });

                if (res.data.success) {
                    const session = {
                        id:         res.data.session_id,
                        title:      res.data.title,
                        created_at: 'À l\'instant',
                    };
                    this.sessions.unshift(session);
                    this.messages         = [];
                    this.currentSessionId = session.id;
                    this.currentTitle     = session.title;
                    this.totalTokens      = 0;

                    // Focus sur le champ de saisie
                    this.$nextTick(() => this.$refs.textarea?.focus());
                }
            } catch (err) {
                console.error('Erreur création session:', err);
                this.showError('Impossible de créer une session');
            }
        },

        // ── Charger une session existante ─────────────────────
        async loadSession(sessionId) {
            try {
                const res = await axios.get(`/chatbot/session/${sessionId}`);

                if (res.data.success) {
                    this.currentSessionId = sessionId;
                    this.currentTitle     = res.data.session.title;
                    this.totalTokens      = res.data.session.total_tokens;
                    this.messages         = res.data.messages;

                    // Scroller vers le bas
                    this.$nextTick(() => this.scrollToBottom());
                }
            } catch (err) {
                console.error('Erreur chargement session:', err);
            }
        },

        // ── Envoyer un message ────────────────────────────────
        async sendMessage() {
            const message = this.inputMessage.trim();
            if (!message || this.isTyping || !this.currentSessionId) return;

            // Ajouter le message utilisateur immédiatement (optimistic UI)
            const tempId = Date.now();
            this.messages.push({
                tempId,
                role:    'user',
                content: message,
                time:    new Date().toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' }),
            });

            this.inputMessage = '';
            this.isTyping     = true;
            this.$nextTick(() => {
                this.scrollToBottom();
                this.autoResize(this.$refs.textarea);
            });

            try {
                const res = await axios.post('/chatbot/message', {
                    session_id: this.currentSessionId,
                    message:    message,
                    model:      this.selectedModel,
                });

                if (res.data.success) {
                    // Ajouter la réponse IA
                    this.messages.push(res.data.message);

                    // Mettre à jour le titre et les tokens
                    this.currentTitle = res.data.session.title;
                    this.totalTokens  = res.data.session.total_tokens;
                    this.dayUsage     = Math.min(this.dayUsage + 1, {{ config('mistral.rate_limit.per_day') }});

                    // Mettre à jour la session dans la liste
                    const idx = this.sessions.findIndex(s => s.id === this.currentSessionId);
                    if (idx !== -1) this.sessions[idx].title = this.currentTitle;

                    this.$nextTick(() => this.scrollToBottom());
                }

            } catch (err) {
                // Supprimer le message temporaire
                this.messages = this.messages.filter(m => m.tempId !== tempId);

                const status = err.response?.status;
                if (status === 429) {
                    this.showError('⏱ Limite de requêtes atteinte. Attendez quelques secondes.');
                } else if (status === 503) {
                    this.showError('🔌 L\'IA est temporairement indisponible. Réessayez dans quelques secondes.');
                } else {
                    this.showError('❌ Erreur lors de l\'envoi du message.');
                }
                console.error('Erreur envoi message:', err);
            } finally {
                this.isTyping = false;
            }
        },

        // ── Supprimer une session ─────────────────────────────
        async deleteSession(sessionId) {
            if (!confirm('Supprimer cette conversation ?')) return;

            try {
                await axios.delete(`/chatbot/session/${sessionId}`);
                this.sessions = this.sessions.filter(s => s.id !== sessionId);

                if (this.currentSessionId === sessionId) {
                    this.currentSessionId = null;
                    this.messages         = [];
                    this.currentTitle     = '';
                }
            } catch (err) {
                console.error('Erreur suppression:', err);
            }
        },

        // ── Suggestion rapide ─────────────────────────────────
        async sendQuickSuggestion(text) {
            if (!this.currentSessionId) {
                await this.newSession();
            }
            this.inputMessage = text;
            await this.sendMessage();
        },

        // ── Helpers UI ────────────────────────────────────────
        scrollToBottom() {
            const container = this.$refs.messagesContainer;
            if (container) {
                container.scrollTop = container.scrollHeight;
            }
        },

        autoResize(el) {
            if (!el) return;
            el.style.height = 'auto';
            el.style.height = Math.min(el.scrollHeight, 120) + 'px';
        },

        showError(msg) {
            // Afficher une notification d'erreur temporaire
            const notif = document.createElement('div');
            notif.className = 'fixed bottom-6 right-6 z-50 px-5 py-3 bg-red-600 text-white rounded-xl text-sm shadow-lg';
            notif.textContent = msg;
            document.body.appendChild(notif);
            setTimeout(() => notif.remove(), 4000);
        },

        // Formater le Markdown basique pour les réponses IA
        formatMarkdown(text) {
            return text
                .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
                .replace(/```(\w*)\n([\s\S]*?)```/g, '<pre class="bg-gray-900 text-green-400 rounded-lg p-3 my-2 text-xs overflow-x-auto"><code>$2</code></pre>')
                .replace(/`([^`]+)`/g, '<code class="bg-gray-200 dark:bg-gray-700 px-1.5 py-0.5 rounded text-xs font-mono">$1</code>')
                .replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>')
                .replace(/\*([^*]+)\*/g, '<em>$1</em>')
                .replace(/^### (.+)$/gm, '<h3 class="font-semibold mt-3 mb-1">$1</h3>')
                .replace(/^## (.+)$/gm, '<h2 class="font-bold mt-4 mb-2">$1</h2>')
                .replace(/^- (.+)$/gm, '<li class="ml-4 list-disc">$1</li>')
                .replace(/^\d+\. (.+)$/gm, '<li class="ml-4 list-decimal">$1</li>')
                .replace(/\n/g, '<br>');
        },

        escapeHtml(text) {
            return text
                .replace(/&/g, '&amp;').replace(/</g, '&lt;')
                .replace(/>/g, '&gt;').replace(/\n/g, '<br>');
        },
    };
}
</script>
@endpush
