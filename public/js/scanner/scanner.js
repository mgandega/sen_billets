class QRScanner {
    constructor(videoElementId, validateUrlTemplate) {
        this.videoElement = document.getElementById(videoElementId);
        this.validateUrlTemplate = validateUrlTemplate;
        this.codeReader = new ZXing.BrowserMultiFormatReader();
        this.currentStream = null;
        this.selectedDeviceId = null;
    }

    async init() {
        try {
            const devices = await ZXing.BrowserCodeReader.listVideoInputDevices();
            if (!devices.length) {
                this.showResult("Aucune caméra détectée", "danger");
                return;
            }

            // caméra par défaut
            this.selectedDeviceId = devices[0].deviceId;
            this.startScan();
        } catch (err) {
            this.showResult("Erreur d’accès à la caméra : " + err.message, "danger");
        }
    }

    async startScan() {
        try {
            this.videoElement.style.display = "block";

            this.codeReader.decodeFromVideoDevice(
                this.selectedDeviceId,
                this.videoElement,
                (result, err) => {
                    if (result) {
                        // Remplace PLACEHOLDER dans l'URL par le QR scanné
                        // const validateUrl = this.validateUrlTemplate.replace("PLACEHOLDER", encodeURIComponent(result.getText()));
                        const validateUrl = "{{ path('scanner_api_validate', {'qrCode': 'PLACEHOLDER'}) }}".replace('PLACEHOLDER', encodeURIComponent(result.getText()));

                        this.validate(validateUrl);
                    }

                    if (err && !(err instanceof ZXing.NotFoundException)) {
                        console.error(err);
                    }
                }
            );
        } catch (err) {
            this.showResult("Impossible de démarrer le scanner : " + err.message, "danger");
        }
    }

    async validate(url) {
        try {
            const response = await fetch(url, {
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
        } catch (err) {
            this.showResult("Erreur de connexion au serveur : " + err.message, "danger");
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
        } catch (err) {
            this.showResult("Erreur en changeant de caméra : " + err.message, "danger");
        }
    }

    showResult(message, type = "info") {
        const resultDiv = document.getElementById("scan-result");
        resultDiv.className = `alert alert-${type} mt-3`;
        resultDiv.innerHTML = message;
    }
}

