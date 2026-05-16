<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Assistant IA') }}
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm rounded-lg overflow-hidden flex flex-col h-[80vh]">
                
                <!-- Header du chat -->
                <div class="px-4 py-3 border-b border-gray-200 flex justify-between items-center bg-gray-50">
                    <div>
                        <h3 class="font-semibold text-gray-800" id="chat-title">Nouvelle conversation</h3>
                        <p class="text-xs text-gray-500" id="chat-stats">0 tokens utilisés</p>
                    </div>
                    <button id="clear-history" class="text-xs text-red-600 hover:text-red-800 underline">
                        Effacer l'historique
                    </button>
                </div>

                <!-- Zone de messages -->
                <div id="chat-messages" class="flex-1 overflow-y-auto p-4 space-y-4">
                    @forelse($messages ?? [] as $msg)
                        <div class="flex {{ $msg->role === 'user' ? 'justify-end' : 'justify-start' }}">
                            <div class="max-w-[85%] p-3 rounded-2xl text-sm {{ 
                                $msg->role === 'user' 
                                    ? 'bg-indigo-600 text-white rounded-tr-none' 
                                    : 'bg-gray-100 text-gray-800 rounded-tl-none' 
                            }}">
                                {{ $msg->content }}
                                <div class="text-xs opacity-70 mt-1">{{ $msg->created_at?->format('H:i') }}</div>
                            </div>
                        </div>
                    @empty
                        <div class="flex justify-center items-center h-full text-gray-400 text-sm italic">
                            Posez votre première question à l'assistant IA.
                        </div>
                    @endforelse
                </div>

                <!-- Formulaire d'envoi -->
                <form id="chat-form" class="p-4 border-t border-gray-200 flex gap-2 bg-gray-50">
                    <input type="text" id="chat-input" name="message" 
                           placeholder="Écrivez votre message..." required
                           class="flex-1 px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 disabled:bg-gray-100">
                    <button type="submit" id="chat-submit" 
                            class="px-5 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition disabled:opacity-50 flex items-center gap-2">
                        <span>Envoyer</span>
                        <svg id="loading-spinner" class="hidden animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                    </button>
                </form>
            </div>
        </div>
    </div>

    @push('scripts')
    @vite('resources/js/chatbot.js')
    <script>
        // Variables globales pour le JS
        window.chatbotConfig = {
            sessionId: {{ $currentSessionId ?? 'null' }},
            usageStats: @json($usageStats ?? [])
        };
    </script>
    @endpush
</x-app-layout>