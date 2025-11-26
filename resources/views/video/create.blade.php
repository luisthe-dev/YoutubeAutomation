<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Video Creator | AI Automation</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Outfit', 'sans-serif'],
                    },
                    colors: {
                        primary: '#6366f1', // Indigo 500
                        secondary: '#ec4899', // Pink 500
                        dark: {
                            900: '#0f172a', // Slate 900
                            800: '#1e293b', // Slate 800
                            700: '#334155', // Slate 700
                        }
                    }
                }
            }
        }
    </script>
    <style>
        body {
            font-family: 'Outfit', sans-serif;
            background-color: #0f172a;
            color: #f8fafc;
        }
        .glass-panel {
            background: rgba(30, 41, 59, 0.7);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        .input-field {
            background: rgba(15, 23, 42, 0.6);
            border: 1px solid rgba(255, 255, 255, 0.1);
            transition: all 0.3s ease;
        }
        .input-field:focus {
            border-color: #6366f1;
            box-shadow: 0 0 0 2px rgba(99, 102, 241, 0.2);
        }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center p-4 bg-gradient-to-br from-dark-900 via-dark-800 to-black">

    <div class="w-full max-w-4xl glass-panel rounded-2xl shadow-2xl overflow-hidden">
        
        <!-- Header -->
        <div class="p-8 border-b border-white/10 bg-gradient-to-r from-primary/10 to-secondary/10">
            <h1 class="text-3xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-primary to-secondary">
                AI Video Creator
            </h1>
            <p class="text-slate-400 mt-2">Generate automated videos with advanced AI orchestration.</p>
        </div>

        <!-- Success Message -->
        @if(session('success'))
        <div class="p-4 bg-green-500/10 border-l-4 border-green-500 text-green-400 m-8 mb-0 rounded-r">
            <div class="flex items-center">
                <svg class="w-6 h-6 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                {{ session('success') }}
            </div>
        </div>
        @endif

        <!-- Form -->
        <form action="{{ route('video.store') }}" method="POST" class="p-8 space-y-8">
            @csrf

            <!-- Main Inputs -->
            <div class="space-y-6">
                <div>
                    <label for="topic" class="block text-sm font-medium text-slate-300 mb-2">Video Topic</label>
                    <input type="text" name="topic" id="topic" required
                        class="w-full px-4 py-3 rounded-xl input-field text-white placeholder-slate-500 focus:outline-none"
                        placeholder="e.g., The History of Quantum Computing">
                </div>

                <div>
                    <label for="title" class="block text-sm font-medium text-slate-300 mb-2">Custom Title (Optional)</label>
                    <input type="text" name="title" id="title"
                        class="w-full px-4 py-3 rounded-xl input-field text-white placeholder-slate-500 focus:outline-none"
                        placeholder="Leave empty to generate automatically">
                </div>
            </div>

            <!-- Advanced Settings Toggle -->
            <div x-data="{ open: false }">
                <button type="button" onclick="document.getElementById('advanced-settings').classList.toggle('hidden')" 
                    class="flex items-center text-sm text-primary hover:text-primary/80 transition-colors">
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                    Show Advanced Configuration
                </button>

                <div id="advanced-settings" class="hidden mt-6 grid grid-cols-1 md:grid-cols-2 gap-6 p-6 rounded-xl bg-dark-800/50 border border-white/5">
                    
                    <!-- Text Driver -->
                    <div class="space-y-4">
                        <h3 class="text-sm font-semibold text-slate-400 uppercase tracking-wider">Text Generation</h3>
                        <div>
                            <label class="block text-xs text-slate-500 mb-1">Preferred Driver</label>
                            <select name="text_driver" class="w-full px-3 py-2 rounded-lg input-field text-sm text-white focus:outline-none">
                                <option value="">Default (Env)</option>
                                <option value="gemini">Gemini</option>
                                <option value="pollinations">Pollinations</option>
                                <option value="chatgpt">ChatGPT</option>
                                <option value="groq">Groq</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs text-slate-500 mb-1">Fallback Driver</label>
                            <select name="text_fallback" class="w-full px-3 py-2 rounded-lg input-field text-sm text-white focus:outline-none">
                                <option value="">None</option>
                                <option value="gemini">Gemini</option>
                                <option value="pollinations">Pollinations</option>
                                <option value="chatgpt">ChatGPT</option>
                                <option value="groq">Groq</option>
                            </select>
                        </div>
                    </div>

                    <!-- Image Driver -->
                    <div class="space-y-4">
                        <h3 class="text-sm font-semibold text-slate-400 uppercase tracking-wider">Image Generation</h3>
                        <div>
                            <label class="block text-xs text-slate-500 mb-1">Preferred Driver</label>
                            <select name="image_driver" class="w-full px-3 py-2 rounded-lg input-field text-sm text-white focus:outline-none">
                                <option value="">Default (Env)</option>
                                <option value="replicate">Replicate (Flux)</option>
                                <option value="pollinations">Pollinations</option>
                                <option value="gemini">Gemini</option>
                                <option value="openai">DALL-E 3</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs text-slate-500 mb-1">Fallback Driver</label>
                            <select name="image_fallback" class="w-full px-3 py-2 rounded-lg input-field text-sm text-white focus:outline-none">
                                <option value="">None</option>
                                <option value="replicate">Replicate</option>
                                <option value="pollinations">Pollinations</option>
                                <option value="gemini">Gemini</option>
                                <option value="openai">DALL-E 3</option>
                            </select>
                        </div>
                    </div>

                    <!-- Voice Driver -->
                    <div class="space-y-4">
                        <h3 class="text-sm font-semibold text-slate-400 uppercase tracking-wider">Voice Generation</h3>
                        <div>
                            <label class="block text-xs text-slate-500 mb-1">Preferred Driver</label>
                            <select name="voice_driver" class="w-full px-3 py-2 rounded-lg input-field text-sm text-white focus:outline-none">
                                <option value="">Default (Env)</option>
                                <option value="elevenlabs">ElevenLabs</option>
                                <option value="pollinations">Pollinations</option>
                                <option value="groq">Groq</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs text-slate-500 mb-1">Fallback Driver</label>
                            <select name="voice_fallback" class="w-full px-3 py-2 rounded-lg input-field text-sm text-white focus:outline-none">
                                <option value="">None</option>
                                <option value="elevenlabs">ElevenLabs</option>
                                <option value="pollinations">Pollinations</option>
                                <option value="groq">Groq</option>
                            </select>
                        </div>
                    </div>

                    <!-- Options -->
                    <div class="space-y-4">
                        <h3 class="text-sm font-semibold text-slate-400 uppercase tracking-wider">Options</h3>
                        <div class="flex items-center">
                            <input type="checkbox" name="use_backups" id="use_backups" value="1" checked
                                class="w-4 h-4 rounded border-slate-600 text-primary focus:ring-primary bg-dark-700">
                            <label for="use_backups" class="ml-2 text-sm text-slate-300">Enable Fallback Generators</label>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Submit Button -->
            <button type="submit" 
                class="w-full py-4 px-6 rounded-xl bg-gradient-to-r from-primary to-secondary hover:from-primary/90 hover:to-secondary/90 text-white font-bold text-lg shadow-lg shadow-primary/25 transform transition hover:-translate-y-0.5 active:translate-y-0">
                Start Video Generation
            </button>
        </form>
    </div>

</body>
</html>
