class QRScanner {
    constructor(videoId, apiUrl) {
        this.video = document.getElementById(videoId);
        this.apiUrl = apiUrl;
        this.codeReader = new ZXing.BrowserMultiFormatReader();
        this.selectedDeviceId = null;
        this.isScanning = false;
        this.flashOn = false;
        this.recentValidations = [];
    }

    async start() {
        try {
            const devices = await ZXing.BrowserMultiFormatReader.listVideoInputDevices();
            if (devices.length === 0) {
                this.showError("Aucune caméra détectée");
                return;
            }

            this.selectedDeviceId = devices[0].deviceId;
            this.video.style.display = "block";
            document.getElementById("scanner-overlay").style.display = "none";
            document.getElementById("scanner-frame").style.display = "block";
            document.getElementById("scanner-controls").style.display = "flex";

            this.isScanning = true;
            this.decodeStream();
        } catch (e) {
            this.showError("Erreur d'accès à la caméra : " + e.message);
        }
    }

    stop() {
        this.codeReader.reset();
        this.isScanning = false;
        this.video.style.display = "none";
        document.getElementById("scanner-overlay").style.display = "flex";
        document.getElementById("scanner-frame").style.display = "none";
        document.getElementById("scanner-controls").style.display = "none";
    }

    async switchCamera() {
        try {
            const devices = await ZXing.BrowserMultiFormatReader.listVideoInputDevices();
            if (devices.length < 2) {
                this.showError("Pas d'autre caméra disponible");
                return;
            }
            const currentIndex = devices.findIndex(d => d.deviceId === this.selectedDeviceId);
            this.selectedDeviceId = devices[(currentIndex + 1) % devices.length].deviceId;
            this.codeReader.reset();
            this.decodeStream();
        } catch (e) {
            this.showError("Erreur changement caméra : " + e.message);
        }
    }

    toggleFlash() {
        this.flashOn = !this.flashOn;
        const track = this.video.srcObject?.getVideoTracks()[0];
        if (track && track.getCapabilities().torch) {
            track.applyConstraints({ advanced: [{ torch: this.flashOn }] });
        }
    }

    decodeStream() {
        this.codeReader.decodeFromVideoDevice(this.selectedDeviceId, this.video, (result, err) => {
            if (result) this.validate(result.getText());
            if (err && !(err instanceof ZXing.NotFoundException)) console.error(err);
        });
    }

    async validate(qrCode) {
        try {
            const res = await fetch(this.apiUrl.replace("PLACEHOLDER", encodeURIComponent(qrCode)), {
                method: "POST",
                headers: { "Content-Type": "application/json" }
            });
            const data = await res.json();
            if (data.valid) this.showSuccess(data);
            else this.showError(data.message || "Billet invalide");
        } catch (e) {
            this.showError("Erreur de connexion : " + e.message);
        }
    }

    showSuccess(data) {
        const ticket = data.ticket;
        const event = data.event;
        const container = document.getElementById("validation-results");
        container.innerHTML = `
            <div class="alert alert-success">
                <strong>✅ Billet validé :</strong> ${ticket.customerName} • ${ticket.ticketType.name} • ${event.title}
            </div>
        `;
        this.addRecent(data);
    }

    showError(message) {
        document.getElementById("validation-results").innerHTML = `<div class="alert alert-danger">${message}</div>`;
    }

    addRecent(data) {
        this.recentValidations.unshift(data);
        this.recentValidations = this.recentValidations.slice(0, 10);
        const container = document.getElementById("recent-validations");
        container.innerHTML = this.recentValidations.map(r => {
            return `<div class="d-flex justify-content-between py-2 border-bottom">
                <div>${r.ticket.customerName} • ${r.ticket.ticketType.name}</div>
                <div class="text-muted">${r.event.title}</div>
            </div>`;
        }).join("");
    }
}
