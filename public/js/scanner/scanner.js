class QRScanner {
    constructor(videoElementId, validateUrl) {
        this.videoElement = document.getElementById(videoElementId);
        this.validateUrl = validateUrl;
        this.codeReader = new ZXing.BrowserMultiFormatReader();
        this.currentStream = null;
        this.selectedDeviceId = null;
    }

    async init() {
        try {
            const devices = await ZXing.BrowserCodeReader.listVideoInputDevices();
            if (devices.length === 0) {
                this.showResult("Aucune caméra détectée", "danger");
                return;
            }

            // caméra par défaut
            this.selectedDeviceId = devices[0].deviceId;

            this.startScan();
        } catch (error) {
            this.showResult("Erreur d’accès à la caméra : " + error.message, "danger");
        }
    }

    async startScan() {
        try {
            this.videoElement.style.display = "block";

            this.codeReader.decodeFromVideoDevice(this.selectedDeviceId, this.videoElement, (result, err) => {
                if (result) {
                    this.validate(result.getText());
                }
                if (err && !(err instanceof ZXing.NotFoundException)) {
                    console.error(err);
                }
            });
        } catch (error) {
            this.showResult("Impossible de démarrer le scanner : " + error.message, "danger");
        }
    }

    async validate(qrCode) {
        try {
            const response = await fetch(this.validateUrl.replace("PLACEHOLDER", encodeURIComponent(qrCode)), {
                method: "POST",
                headers: { "Content-Type": "application/json" }
            });

            const data = await response.json();

            if (data.valid) {
                this.showResult(
                    `✅ Billet valide<br>
                     🎟️ <b>${data.ticket.ticketType.name}</b><br>
                     👤 ${data.ticket.customerName}<br>
                     📌 ${data.event.title} (${data.event.date})`,
                    "success"
                );
            } else {
                this.showResult("❌ " + (data.message || "Billet invalide"), "danger");
            }
        } catch (error) {
            this.showResult("Erreur de connexion au serveur : " + error.message, "danger");
        }
    }

    async switchCamera() {
        try {
            const devices = await ZXing.BrowserCodeReader.listVideoInputDevices();
            if (devices.length < 2) {
                this.showResult("Pas d'autre caméra disponible", "warning");
                return;
            }

            const currentIndex = devices.findIndex(d => d.deviceId === this.selectedDeviceId);
            const nextIndex = (currentIndex + 1) % devices.length;
            this.selectedDeviceId = devices[nextIndex].deviceId;

            this.codeReader.reset();
            this.startScan();
        } catch (error) {
            this.showResult("Erreur en changeant de caméra : " + error.message, "danger");
        }
    }

    showResult(message, type = "info") {
        const resultDiv = document.getElementById("scan-result");
        resultDiv.className = `alert alert-${type} mt-3`;
        resultDiv.innerHTML = message;
    }

    async validateQRCode(qrCode) {
        if (!qrCode) return this.showError('Code QR vide');

        const encodedQR = encodeURIComponent(qrCode);
        const url = `/scanner/api/validate/${encodedQR}`;

        try {
            // const response = await fetch(url, { method: 'POST' });
            const response = await fetch(`/scanner/api/validate/${encodeURIComponent(qrCode)}`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' }
            });


            if (!response.ok) {
                const text = await response.text().catch(() => '[body non lisible]');
                return this.showError(`Erreur serveur ${response.status} ${response.statusText}`);
            }

            const result = await response.json();

            if (result.valid) {
                this.showSuccess(result);
                this.addToRecentValidations(result);
            } else {
                this.showError(result.message || 'Billet invalide', result);
            }
        } catch (error) {
            console.error('Erreur réseau / CORS:', error);
            this.showError('Erreur de connexion ou problème CORS');
        }
    }

}
