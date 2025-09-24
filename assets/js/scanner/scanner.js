class QRScanner {
    constructor() {
        this.codeReader = new ZXing.BrowserQRCodeReader();
        this.selectedDeviceId = null;
        this.isScanning = false;
        this.recentValidations = [];

        this.initializeElements();
        this.bindEvents();
        this.loadRecentValidations();
    }

    initializeElements() {
        this.startBtn = document.getElementById('start-scanner');
        this.stopBtn = document.getElementById('stop-scanner');
        this.switchBtn = document.getElementById('switch-camera');
        this.flashBtn = document.getElementById('toggle-flash');
        this.video = document.getElementById('scanner-video');
        this.overlay = document.getElementById('scanner-overlay');
        this.frame = document.getElementById('scanner-frame');
        this.controls = document.getElementById('scanner-controls');
        this.manualInput = document.getElementById('manual-qr');
        this.validateBtn = document.getElementById('validate-manual');
        this.results = document.getElementById('validation-results');
        this.recentContainer = document.getElementById('recent-validations');
        if (this.startBtn) this.startBtn.disabled = false;
    }

    bindEvents() {
        if (this.startBtn) this.startBtn.addEventListener('click', () => this.startScanning());
        if (this.stopBtn) this.stopBtn.addEventListener('click', () => this.stopScanning());
        if (this.switchBtn) this.switchBtn.addEventListener('click', () => this.switchCamera());
        if (this.validateBtn) this.validateBtn.addEventListener('click', () => this.validateManual());
        if (this.manualInput) this.manualInput.addEventListener('keypress', e => { if (e.key === 'Enter') this.validateManual(); });
        if (this.overlay) this.overlay.addEventListener('click', e => { if (e.target === this.overlay) this.startScanning(); });
    }

    async getVideoDevices() {
        try { return await this.codeReader.getVideoInputDevices() || []; }
        catch { return []; }
    }

    async startScanning() {
        if (this.isScanning) return;
        try { await navigator.mediaDevices.getUserMedia({ video: true }); } 
        catch { return this.showError('Accès caméra refusé'); }

        const devices = await this.getVideoDevices();
        if (!devices.length) return this.showError('Aucune caméra détectée');
        this.selectedDeviceId = devices[0].deviceId;

        this.overlay.style.display = 'none';
        this.video.style.display = 'block';
        this.frame.style.display = 'block';
        this.controls.style.display = 'flex';
        this.isScanning = true;

        this.codeReader.reset();
        this.codeReader.decodeFromVideoDevice(this.selectedDeviceId, this.video, (result, err) => {
            if (result) { this.handleScanResult(result.text); this.stopScanning(); }
        });
    }

    stopScanning() {
        try { this.codeReader.reset(); } catch {}
        this.isScanning = false;
        this.video.style.display = 'none';
        this.frame.style.display = 'none';
        this.controls.style.display = 'none';
        this.overlay.style.display = 'flex';
    }

    async switchCamera() {
        if (!this.isScanning) return;
        const devices = await this.getVideoDevices();
        if (devices.length < 2) return;
        const currentIndex = devices.findIndex(d => d.deviceId === this.selectedDeviceId);
        this.selectedDeviceId = devices[(currentIndex + 1) % devices.length].deviceId;
        this.codeReader.reset();
        this.codeReader.decodeFromVideoDevice(this.selectedDeviceId, this.video, (result) => { if (result) { this.handleScanResult(result.text); this.stopScanning(); }});
    }

    handleScanResult(qrCode) { if (!qrCode) return this.showError('QR vide'); this.validateQRCode(qrCode.trim()); }

    validateManual() { const code = this.manualInput.value.trim(); if (!code) return this.showError('Veuillez saisir un code QR'); this.manualInput.value = ''; this.validateQRCode(code); }

    // async validateQRCode(qrCode) {
    //     try {
    //         const response = await fetch('/scanner/api/validate', { method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify({code: qrCode}) });
    //         if (!response.ok) return this.showError(`Erreur serveur ${response.status}`);
    //         const result = await response.json();
    //         if (result.valid) { this.showSuccess(result); this.addToRecentValidations(result); }
    //         else this.showError(result.message || 'Billet invalide', result);
    //     } catch { this.showError('Erreur réseau'); }
    // }
    // async validateQRCode(qrCode) {
    //     if (!qrCode) return this.showError('Code QR vide');

    //     const encodedQR = encodeURIComponent(qrCode);
    //     const url = `/scanner/api/validate/${encodedQR}`;

    //     try {
    //         // const response = await fetch(url, { method: 'POST' });
    //         const response = await fetch('/scanner/api/validate', {
    //         method: 'POST',
    //         headers: { 'Content-Type': 'application/json' },
    //         body: JSON.stringify({ code: qrCode })
    //     });


    //         if (!response.ok) {
    //             const text = await response.text().catch(() => '[body non lisible]');
    //             return this.showError(`Erreur serveur ${response.status} ${response.statusText}`);
    //         }

    //         const result = await response.json();

    //         if (result.valid) {
    //             this.showSuccess(result);
    //             this.addToRecentValidations(result);
    //         } else {
    //             this.showError(result.message || 'Billet invalide', result);
    //         }
    //     } catch (error) {
    //         console.error('Erreur réseau / CORS:', error);
    //         this.showError('Erreur de connexion ou problème CORS');
    //     }
    // }

    async validateQRCode(rawText) {
        // 1) essayer d'extraire le ticket (TICKET-...); sinon fallback au texte entier
        const ticketMatch = String(rawText).match(/TICKET-[0-9a-fA-F\-]{10,}/i);
        const code = ticketMatch ? ticketMatch[0] : rawText;

        if (!code) return this.showError('Code QR vide');

        try {
            const response = await fetch('/scanner/api/validate', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ code })
            });

            if (!response.ok) {
                const text = await response.text().catch(()=>'');
                return this.showError(`Erreuuur serveur ${response.status} ${response.statusText} ${text}`);
            }

            const result = await response.json();
            if (result.valid) { this.showSuccess(result); this.addToRecentValidations(result); }
            else this.showError(result.message || 'Billet invalide', result);

        } catch (err) {
            console.error(err);
            this.showError('Erreur réseau / CORS');
        }
    }


    showSuccess(result) { this.results.innerHTML = `<div class="alert alert-success alert-dismissible fade show" role="alert">
        <div class="d-flex align-items-center"><i class="bi bi-check-circle-fill fs-3 me-3"></i>
        <div class="flex-grow-1"><h5 class="alert-heading mb-1">✅ Billet validé !</h5>
        <p class="mb-1"><strong>${result.ticket?.customerName || 'N/A'}</strong> - ${result.ticket?.ticketType?.name || 'N/A'}</p>
        <small class="text-muted">${result.event?.title || ''} • ${result.ticket?.qrCode || ''}</small></div></div>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>`; this.playSound('success'); }

    showError(message) { this.results.innerHTML = `<div class="alert alert-danger alert-dismissible fade show" role="alert">
        <div class="d-flex align-items-center"><i class="bi bi-x-circle-fill fs-3 me-3"></i>
        <div class="flex-grow-1"><h5 class="alert-heading mb-1">❌ Validation échouée</h5><p class="mb-0">${message}</p></div></div>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>`; this.playSound('error'); }

    addToRecentValidations(result) { this.recentValidations.unshift({ ...result, timestamp: new Date() }); this.recentValidations = this.recentValidations.slice(0,10); this.updateRecentValidationsDisplay(); this.saveRecentValidations(); }

    updateRecentValidationsDisplay() {
        if (!this.recentValidations.length) { this.recentContainer.innerHTML='<p class="text-muted text-center">Aucune validation récente</p>'; return; }
        this.recentContainer.innerHTML = this.recentValidations.map(v=>`<div class="d-flex justify-content-between align-items-center py-2 border-bottom">
            <div><strong>${v.ticket?.customerName||'Inconnu'}</strong><small class="text-muted d-block">${v.ticket?.ticketType?.name||''} • ${v.ticket?.qrCode||''}</small></div>
            <div class="text-end"><span class="badge bg-success">Validé</span><small class="text-muted d-block">${new Date(v.timestamp).toLocaleTimeString()}</small></div>
        </div>`).join('');
    }

    loadRecentValidations() { const saved = localStorage.getItem('recentValidations'); if (saved) { this.recentValidations=JSON.parse(saved).map(v=>({...v,timestamp:new Date(v.timestamp)})); this.updateRecentValidationsDisplay(); } }
    saveRecentValidations() { localStorage.setItem('recentValidations',JSON.stringify(this.recentValidations)); }

    playSound(type) { try { const ctx=new (window.AudioContext||window.webkitAudioContext)(); const osc=ctx.createOscillator(); const gain=ctx.createGain(); osc.connect(gain); gain.connect(ctx.destination);
        if(type==='success'){osc.frequency.setValueAtTime(800,ctx.currentTime);osc.frequency.setValueAtTime(1000,ctx.currentTime+0.1);}
        else{osc.frequency.setValueAtTime(300,ctx.currentTime);osc.frequency.setValueAtTime(200,ctx.currentTime+0.1);}
        gain.gain.setValueAtTime(0.3,ctx.currentTime);gain.gain.exponentialRampToValueAtTime(0.01,ctx.currentTime+0.2);osc.start(ctx.currentTime);osc.stop(ctx.currentTime+0.2);}
        catch(e){}
    }
}

document.addEventListener('DOMContentLoaded',()=>new QRScanner());