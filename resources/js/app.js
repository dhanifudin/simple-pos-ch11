import Alpine from 'alpinejs';

const CART_STORAGE_KEY = 'pos-cart';

// Shared confirm/action dialog used site-wide instead of native confirm() —
// a global store (not a per-component x-data) so any button anywhere, including
// inside a table-row loop, can open the matching <x-modal id="..."> without needing
// a local Alpine scope wrapping it.
Alpine.store('modal', {
    current: null,
    open(id) {
        this.current = id;
    },
    close() {
        this.current = null;
    },
});

Alpine.data('posCart', (taxPercent = 0) => ({
    items: {},
    amountPaid: null,
    discountInput: null,
    taxPercent,
    scanInput: '',
    scanError: '',

    // The product grid is server-paginated/searched (see TransactionController::create),
    // so picking a product navigates to a new page and would otherwise wipe Alpine's
    // in-memory state. Persisting to sessionStorage lets the cart survive that navigation
    // without needing a server-side cart (out of scope for this course project).
    init() {
        try {
            const saved = sessionStorage.getItem(CART_STORAGE_KEY);
            if (saved) this.items = JSON.parse(saved);
        } catch (e) {
            this.items = {};
        }
    },

    persist() {
        try {
            sessionStorage.setItem(CART_STORAGE_KEY, JSON.stringify(this.items));
        } catch (e) {
            // storage unavailable (private mode, quota) — cart just won't survive navigation
        }
    },

    get list() {
        return Object.values(this.items);
    },

    get itemsSubtotal() {
        return this.list.reduce((sum, item) => sum + item.price * item.qty, 0);
    },

    // Clamped so a discount can never exceed the subtotal (no negative pre-tax amount).
    get discount() {
        return Math.min(this.discountInput ?? 0, this.itemsSubtotal);
    },

    get taxAmount() {
        return Math.round((this.itemsSubtotal - this.discount) * this.taxPercent / 100);
    },

    get total() {
        return this.itemsSubtotal - this.discount + this.taxAmount;
    },

    get change() {
        if (this.amountPaid === null) return 0;
        return Math.max(0, this.amountPaid - this.total);
    },

    get canCheckout() {
        return this.list.length > 0 && this.amountPaid !== null && this.amountPaid >= this.total;
    },

    // Suggested cash-tendered amounts: exact total ("Uang Pas"), then the total rounded
    // up to the next few common Rupiah bill denominations, deduplicated.
    quickAmounts() {
        const total = this.total;
        if (total <= 0) return [];
        const roundUp = (n, step) => Math.ceil(n / step) * step;
        const amounts = [total, roundUp(total, 5000), roundUp(total, 10000), roundUp(total, 20000), roundUp(total, 50000), roundUp(total, 100000)];
        return [...new Set(amounts)].sort((a, b) => a - b);
    },

    // Live-formats "Uang Diterima" with thousand separators as the cashier types.
    // The visible field is plain text (a real <input type=number> rejects the "."
    // grouping characters), driven as an Alpine-controlled input; the raw integer
    // goes to the server via a separate hidden field (see pos/create.blade.php).
    onAmountInput(e) {
        const digits = e.target.value.replace(/\D/g, '');
        this.amountPaid = digits === '' ? null : parseInt(digits, 10);
    },

    // Same formatted-input pattern as onAmountInput, for the discount field.
    onDiscountInput(e) {
        const digits = e.target.value.replace(/\D/g, '');
        this.discountInput = digits === '' ? null : parseInt(digits, 10);
    },

    // Quick discount buttons: a percentage of the current subtotal, rounded to a
    // whole Rupiah — same "quick suggestion" pattern as quickAmounts() for cash.
    quickDiscounts() {
        const subtotal = this.itemsSubtotal;
        if (subtotal <= 0) return [];
        return [5, 10, 15].map((pct) => ({ pct, amount: Math.round(subtotal * pct / 100) }));
    },

    // Confirmation happens via the shared <x-modal id="clear-cart"> before this runs.
    clearCart() {
        this.items = {};
        this.amountPaid = null;
        this.discountInput = null;
        this.persist();
    },

    // Barcode scanners emulate keyboard entry + Enter, so a plain text input plus
    // @keydown.enter is enough to "scan" — no browser barcode API needed. Looked up
    // via fetch (not a form submit) so the cart stays live, no page reload.
    async scan() {
        const sku = this.scanInput.trim();
        if (!sku) return;

        try {
            const res = await fetch(`/pos/lookup?sku=${encodeURIComponent(sku)}`, {
                headers: { Accept: 'application/json' },
            });

            if (!res.ok) {
                this.scanError = 'SKU tidak ditemukan.';
                this.scanInput = '';
                return;
            }

            const product = await res.json();
            this.add(product);
            this.scanInput = '';
            this.scanError = '';
        } catch (e) {
            this.scanError = 'Gagal memindai, coba lagi.';
        }
    },

    add(product) {
        const existing = this.items[product.id];
        if (!existing) {
            if (product.stock < 1) return;
            this.items[product.id] = { ...product, qty: 1 };
        } else if (existing.qty < existing.stock) {
            existing.qty++;
        }
        this.persist();
    },

    changeQty(id, delta) {
        const item = this.items[id];
        if (!item) return;
        item.qty += delta;
        if (item.qty <= 0) delete this.items[id];
        else if (item.qty > item.stock) item.qty = item.stock;
        this.persist();
    },

    checkout() {
        sessionStorage.removeItem(CART_STORAGE_KEY);
    },

    formatRupiah(n) {
        return 'Rp ' + Number(n).toLocaleString('id-ID');
    },
}));

window.Alpine = Alpine;
Alpine.start();
