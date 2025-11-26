<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Video Progress | AI Automation</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Outfit', 'sans-serif'],
                        mono: ['Fira Code', 'monospace'],
                    },
                    colors: {
                        primary: '#6366f1',
                        secondary: '#ec4899',
                        dark: {
                            900: '#0f172a',
                            800: '#1e293b',
                            700: '#334155',
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
        .terminal {
            background: #0f172a;
            border: 1px solid rgba(255, 255, 255, 0.1);
            font-family: 'Fira Code', monospace;
        }
    </style>
</head>
<body class="min-h-screen flex flex-col items-center justify-center p-4 bg-gradient-to-br from-dark-900 via-dark-800 to-black">

    <div class="w-full max-w-4xl glass-panel rounded-2xl shadow-2xl overflow-hidden mb-8">
        <div class="p-8 border-b border-white/10 flex justify-between items-center">
            <div>
                <h1 class="text-2xl font-bold text-white">Generation Progress</h1>
                <p class="text-slate-400 text-sm mt-1">Job ID: <span class="font-mono text-primary">{{ $jobId }}</span></p>
            </div>
            <a href="{{ route('video.create') }}" class="text-sm text-slate-400 hover:text-white transition-colors">
                &larr; Create New
            </a>
        </div>

        <div class="p-8" x-data="progressTracker('{{ $jobId }}')">
            <!-- Status Indicator -->
            <div class="flex items-center mb-6 space-x-3">
                <div class="relative flex h-3 w-3">
                  <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75"></span>
                  <span class="relative inline-flex rounded-full h-3 w-3 bg-green-500"></span>
                </div>
                <span class="text-green-400 font-medium tracking-wide text-sm uppercase">Processing</span>
            </div>

            <!-- Terminal Output -->
            <div class="terminal rounded-xl p-6 h-96 overflow-y-auto shadow-inner" id="terminal-window">
                <template x-for="log in logs" :key="log.timestamp + log.message">
                    <div class="mb-2 text-sm">
                        <span class="text-slate-500" x-text="formatTime(log.timestamp)"></span>
                        <span :class="{
                            'text-green-400': log.level === 'info',
                            'text-red-400': log.level === 'error',
                            'text-yellow-400': log.level === 'warning'
                        }" class="ml-2" x-text="log.message"></span>
                    </div>
                </template>
                <div x-show="logs.length === 0" class="text-slate-600 italic">Waiting for logs...</div>
            </div>
        </div>
    </div>

    <script>
        function progressTracker(jobId) {
            return {
                logs: [],
                init() {
                    this.poll();
                    setInterval(() => this.poll(), 2000); // Poll every 2 seconds
                },
                poll() {
                    fetch(`/video/progress/${jobId}`)
                        .then(response => response.json())
                        .then(data => {
                            this.logs = data.logs;
                            // Auto-scroll to bottom
                            this.$nextTick(() => {
                                const terminal = document.getElementById('terminal-window');
                                terminal.scrollTop = terminal.scrollHeight;
                            });
                        });
                },
                formatTime(isoString) {
                    const date = new Date(isoString);
                    return date.toLocaleTimeString();
                }
            }
        }
    </script>
</body>
</html>
