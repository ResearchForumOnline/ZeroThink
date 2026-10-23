window.Zero = {
    config: { engine: 'groq', task: 'chat', model: 'openai/gpt-oss-120b', zero_mode: true, use_web: false },
    fileContext: "", 
    imageBase64: "", // For Vision

    setMode: function(e, el) { this.config.engine = e; document.querySelectorAll('.model-pill.model').forEach(b => b.classList.remove('active')); if(el) el.classList.add('active'); },
    toggleZeroThink: function(el) { this.config.zero_mode = !this.config.zero_mode; el.classList.toggle('active'); },
    toggleSearch: function(el) { this.config.use_web = !this.config.use_web; el.classList.toggle('active'); document.getElementById('btn-search').style.boxShadow = this.config.use_web ? "0 0 10px #00ff9d" : "none"; },

    // --- VOICE ---
    toggleMic: function() {
        const btn = document.getElementById('mic-btn');
        if (!('webkitSpeechRecognition' in window)) return alert("Browser does not support Voice.");
        
        const recognition = new webkitSpeechRecognition();
        recognition.lang = 'en-US';
        recognition.interimResults = false;
        recognition.maxAlternatives = 1;

        recognition.start();
        btn.classList.add('recording');

        recognition.onresult = (event) => {
            document.getElementById('zero-input').value = event.results[0][0].transcript;
            btn.classList.remove('recording');
            this.send(); // Auto send
        };
        recognition.onerror = () => { btn.classList.remove('recording'); };
    },

    speak: function(text) {
        if ('speechSynthesis' in window) {
            window.speechSynthesis.cancel();
            const u = new SpeechSynthesisUtterance(text.replace(/<[^>]*>/g, '')); // Strip HTML
            u.pitch = 0.9; u.rate = 1.1; // Tech voice
            window.speechSynthesis.speak(u);
        }
    },

    // --- UPLOAD ---
    handleUpload: async function(input) {
        if (!input.files[0]) return;
        const file = input.files[0];
        const formData = new FormData();
        formData.append('file', file);
        
        const history = document.getElementById('chat-history');
        history.innerHTML += `<div class="zt-message"><i class="fas fa-spinner fa-spin"></i> Uploading ${file.name}...</div>`;

        const res = await fetch('/api_upload.php', { method: 'POST', body: formData });
        const d = await res.json();
        
        if (d.status === 'success') {
            if (d.type === 'text') {
                this.fileContext = d.content;
                history.lastElementChild.innerHTML = `✅ <strong>${d.filename}</strong> Ingested.`;
            } else if (d.type === 'image') {
                this.imageBase64 = d.url; // Store base64 for API
                history.lastElementChild.innerHTML = `<img src="${d.url}" style="max-width:200px; border-radius:10px;"> <br> ✅ Image Ready for Vision Analysis`;
            }
        } else {
            history.lastElementChild.innerHTML = `❌ Error: ${d.message}`;
        }
    },

    // --- SEND ---
    send: async function() {
        const input = document.getElementById('zero-input');
        const prompt = input.value.trim();
        if (!prompt && !this.fileContext && !this.imageBase64) return;

        const history = document.getElementById('chat-history');
        history.innerHTML += `<div class="user-message">${prompt || '[File Upload]'}</div>`;
        input.value = '';
        history.scrollTop = history.scrollHeight;

        const aiDiv = document.createElement('div');
        aiDiv.className = 'zt-message';
        aiDiv.innerHTML = '<i class="fas fa-circle-notch fa-spin" style="color:#00ffff"></i> Processing...';
        history.appendChild(aiDiv);

        const keys = {
            groq: document.getElementById('k-groq').value,
            openai: document.getElementById('k-openai').value,
            xai: document.getElementById('k-xai').value,
            gemini: document.getElementById('k-gemini').value,
            nvidia: document.getElementById('k-nvidia').value,
            serper: document.getElementById('k-serper').value
        };

        const payload = {
            prompt: prompt,
            file_context: this.fileContext,
            image: this.imageBase64, // Send Image
            engine: this.config.engine,
            groq_model: document.getElementById('groq-model').value,
            zero_mode: this.config.zero_mode,
            use_web: this.config.use_web,
            key: keys[this.config.engine] || '',
            serper: keys.serper,
            persona: document.getElementById('k-persona').value,
            csrf_token: window.ZT_SESSION_CSRF_TOKEN || ''
        };

        try {
            const res = await fetch('/api_agent.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': window.ZT_SESSION_CSRF_TOKEN || ''
                },
                body: JSON.stringify(payload)
            });
            const data = await res.json();
            
            let html = "";
            if (data.thought) {
                const uid = 'think-' + Date.now();
                const safeThought = String(data.thought)
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#039;')
                    .replace(/\n/g, '<br>')
                    .replace(/\[(FRAME|EVIDENCE|COUNTERPOINT|UNCERTAINTY|IMPACT|DECISION|NEXT STEP)\]/g, '<strong style="color:#7ee7d2;">[$1]</strong>')
                    .replace(/\[(SYNTHESIS|CHOICE)\]/g, '<strong style="color:#a855f7;">[$1]</strong>');
                html += `<div class="thinking-wrapper"><div class="think-toggle" onclick="document.getElementById('${uid}').classList.toggle('open')"><i class="fas fa-list-check"></i> ZeroThink Reasoning Brief</div><div id="${uid}" class="thinking-box">${safeThought}</div></div>`;
            }
            const txt = data.reply || "Error.";
            html += (typeof marked !== 'undefined') ? marked.parse(txt) : txt;
            
            // Add Speak Button
            html += `<div class="speak-btn" onclick="window.Zero.speak(this.parentNode.innerText)"><i class="fas fa-volume-up"></i> Listen</div>`;
            
            aiDiv.innerHTML = html;
            
            this.fileContext = ""; // Reset One-time Use
            this.imageBase64 = "";

        } catch (e) { aiDiv.innerHTML = `<span style="color:red">ERROR: ${e.message}</span>`; }
        history.scrollTop = history.scrollHeight;
    },
    
    // --- VAULT (Load/Save/Wipe) ---
    loadVault: async function() { /* Previous Code */ },
    saveVault: async function() { /* Previous Code */ },
    wipeVault: async function() { /* Previous Code */ }
};
// Re-bind enter key
document.getElementById('zero-input').addEventListener('keypress', function (e) { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); window.Zero.send(); } });
