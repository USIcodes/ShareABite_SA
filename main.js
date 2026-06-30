document.addEventListener('DOMContentLoaded', function () {
    const sellerTypeInputs = document.querySelectorAll('input[name="userType"]');
    const businessGroup = document.querySelector('.business-group');
    const sellerPayGroup = document.querySelector('.seller-pay-group');
    const donationCheckbox = document.querySelector('#isDonation');
    const priceInput = document.querySelector('#price');
    const uploadInputs = document.querySelectorAll('input[type="file"][accept*="image"]');
    const orderSummary = document.querySelector('.order-summary');

    function toggleBusinessField() {
        const sellerSelected = [...sellerTypeInputs].some(input => input.checked && input.value === 'seller');
        [businessGroup, sellerPayGroup].forEach(group => {
            if (!group) return;
            group.style.display = sellerSelected ? 'block' : 'none';
            const input = group.querySelector('input');
            if (!input) return;
            if (sellerSelected) {
                input.setAttribute('required', 'required');
            } else {
                input.removeAttribute('required');
            }
        });
    }

    if (sellerTypeInputs.length && (businessGroup || sellerPayGroup)) {
        sellerTypeInputs.forEach(input => input.addEventListener('change', toggleBusinessField));
        toggleBusinessField();
    }

    if (donationCheckbox && priceInput) {
        donationCheckbox.addEventListener('change', function () {
            if (this.checked) {
                priceInput.value = '0.00';
                priceInput.setAttribute('readonly', 'readonly');
            } else {
                priceInput.removeAttribute('readonly');
            }
        });
    }

    document.querySelectorAll('form').forEach(form => {
        form.addEventListener('submit', function () {
            if (!form.checkValidity()) return;
            const button = form.querySelector('.loader-button');
            if (!button) return;
            button.disabled = true;
            button.dataset.originalText = button.textContent;
            button.textContent = 'Please wait...';
        });
    });

    uploadInputs.forEach(input => {
        input.addEventListener('change', function () {
            const file = this.files && this.files[0];
            const group = this.closest('.field-group');
            if (!file || !group) return;
            let preview = group.querySelector('.image-preview');
            if (!preview) {
                preview = document.createElement('img');
                preview.className = 'image-preview';
                preview.alt = 'Selected listing image preview';
                this.insertAdjacentElement('afterend', preview);
            }
            preview.src = URL.createObjectURL(file);
        });
    });

    if (orderSummary) {
        const quantityInput = document.querySelector('#quantity');
        const methodInputs = document.querySelectorAll('input[name="pickupMethod"]');
        const unitPrice = Number(orderSummary.dataset.unitPrice || 0);
        const deliveryFee = Number(orderSummary.dataset.deliveryFee || 0);
        const subtotalEl = orderSummary.querySelector('[data-summary-subtotal]');
        const deliveryEl = orderSummary.querySelector('[data-summary-delivery]');
        const totalEl = orderSummary.querySelector('[data-summary-total]');

        function updateSummary() {
            const quantity = Math.max(1, Number(quantityInput?.value || 1));
            const method = [...methodInputs].find(input => input.checked)?.value || 'pickup';
            const subtotal = unitPrice * quantity;
            const delivery = method === 'delivery' ? deliveryFee : 0;
            subtotalEl.textContent = subtotal.toFixed(2);
            deliveryEl.textContent = delivery.toFixed(2);
            totalEl.textContent = (subtotal + delivery).toFixed(2);
        }

        quantityInput?.addEventListener('input', updateSummary);
        methodInputs.forEach(input => input.addEventListener('change', updateSummary));
        updateSummary();
    }
});

function printTicket() {
    window.print();
}
