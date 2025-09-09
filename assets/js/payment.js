// Payment handling for Sen-Billets
class PaymentManager {
    constructor() {
        this.stripe = null;
        this.elements = null;
        this.cardElement = null;
        this.initializePaymentMethods();
    }

    async initializePaymentMethods() {
        // Initialize Stripe if available
        if (window.Stripe && window.STRIPE_PUBLIC_KEY) {
            this.stripe = Stripe(window.STRIPE_PUBLIC_KEY);
            this.setupStripeElements();
        }

        this.bindPaymentMethodEvents();
    }

    setupStripeElements() {
        if (!this.stripe) return;

        this.elements = this.stripe.elements();
        
        // Create card element
        this.cardElement = this.elements.create('card', {
            style: {
                base: {
                    fontSize: '16px',
                    color: '#424770',
                    '::placeholder': {
                        color: '#aab7c4',
                    },
                },
            },
        });

        // Mount card element
        const cardElementContainer = document.getElementById('card-element');
        if (cardElementContainer) {
            this.cardElement.mount('#card-element');
        }
    }

    bindPaymentMethodEvents() {
        // Payment method selection
        const paymentMethodInputs = document.querySelectorAll('input[name="payment_method"]');
        paymentMethodInputs.forEach(input => {
            input.addEventListener('change', (e) => {
                this.handlePaymentMethodChange(e.target.value);
            });
        });

        // Payment form submission
        const paymentForm = document.getElementById('payment-form');
        if (paymentForm) {
            paymentForm.addEventListener('submit', (e) => {
                e.preventDefault();
                this.handlePaymentSubmission();
            });
        }
    }

    handlePaymentMethodChange(method) {
        // Hide all payment forms
        document.querySelectorAll('.payment-form-section').forEach(section => {
            section.style.display = 'none';
        });

        // Show selected payment form
        const selectedForm = document.getElementById(`${method}-form`);
        if (selectedForm) {
            selectedForm.style.display = 'block';
        }

        // Update submit button text
        const submitButton = document.getElementById('submit-payment');
        if (submitButton) {
            const buttonTexts = {
                'stripe': 'Payer par carte',
                'orange_money': 'Payer avec Orange Money',
                'wave': 'Payer avec Wave',
                'bank_transfer': 'Confirmer le virement'
            };
            submitButton.textContent = buttonTexts[method] || 'Confirmer le paiement';
        }
    }

    async handlePaymentSubmission() {
        const selectedMethod = document.querySelector('input[name="payment_method"]:checked')?.value;
        
        if (!selectedMethod) {
            this.showError('Veuillez sélectionner une méthode de paiement');
            return;
        }

        try {
            switch (selectedMethod) {
                case 'stripe':
                    await this.processStripePayment();
                    break;
                case 'orange_money':
                    await this.processOrangeMoneyPayment();
                    break;
                case 'wave':
                    await this.processWavePayment();
                    break;
                case 'bank_transfer':
                    await this.processBankTransfer();
                    break;
                default:
                    throw new Error('Méthode de paiement non supportée');
            }
        } catch (error) {
            this.showError(error.message);
        }
    }

    async processStripePayment() {
        if (!this.stripe || !this.cardElement) {
            throw new Error('Stripe n\'est pas initialisé');
        }

        const { error, paymentMethod } = await this.stripe.createPaymentMethod({
            type: 'card',
            card: this.cardElement,
        });

        if (error) {
            throw new Error(error.message);
        }

        // Send payment method to server
        await this.submitPayment({
            payment_method: 'stripe',
            payment_method_id: paymentMethod.id
        });
    }

    async processOrangeMoneyPayment() {
        const phoneNumber = document.getElementById('orange-phone')?.value;
        
        if (!phoneNumber) {
            throw new Error('Veuillez saisir votre numéro de téléphone');
        }

        await this.submitPayment({
            payment_method: 'orange_money',
            phone_number: phoneNumber
        });
    }

    async processWavePayment() {
        await this.submitPayment({
            payment_method: 'wave'
        });
    }

    async processBankTransfer() {
        await this.submitPayment({
            payment_method: 'bank_transfer'
        });
    }

    async submitPayment(data) {
        const response = await fetch('/payment/process', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(data)
        });

        const result = await response.json();

        if (result.success) {
            if (result.redirect_url) {
                window.location.href = result.redirect_url;
            } else {
                window.location.href = `/payment/success/${result.payment_id}`;
            }
        } else {
            throw new Error(result.error || 'Erreur lors du paiement');
        }
    }

    showError(message) {
        const errorContainer = document.getElementById('payment-errors');
        if (errorContainer) {
            errorContainer.innerHTML = `
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    ${message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            `;
        }

        // Also show toast notification
        if (window.SenBillets) {
            window.SenBillets.showToast(message, 'error');
        }
    }
}

// Initialize payment manager when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    new PaymentManager();
});

export default PaymentManager;