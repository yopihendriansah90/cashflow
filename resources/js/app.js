import { createPopper } from "@popperjs/core";
import Alpine from "alpinejs";
import flatpickr from "flatpickr";
import "flatpickr/dist/flatpickr.min.css";
import "./bootstrap";
import initCharts from "./components/chart";
import registerUi from "./ui";
import cashbookFilters from "./components/cashbook-filters";
import { registerRupiah } from "./components/rupiah";
import quickTransaction, { saveQuickTransaction } from "./components/quick-transaction";
import { confirmAction, initializeNotifications } from "./notifications";

window.Alpine = Alpine;
window.createPopper = createPopper;
window.flatpickr = flatpickr;

// Register Alpine.js components before initializing
Alpine.data("cashbookFilters", cashbookFilters);
Alpine.data("quickTransaction", quickTransaction);
registerRupiah(Alpine);

Alpine.data("dropdown", () => ({
    open: false,
    toggle() {
        this.open = !this.open;
        if (this.open) this.position();
    },
    position() {
        this.$nextTick(() => {
            const button = this.$el;
            const dropdown = this.$refs.dropdown;
            const rect = button.getBoundingClientRect();

            // Apply initial styles to ensure measurement is possible
            dropdown.style.position = "fixed";
            dropdown.style.zIndex = "999";

            const isRtl = document.documentElement.getAttribute("dir") === "rtl";
            if (isRtl) {
                dropdown.style.left = `${rect.left}px`;
                dropdown.style.right = "auto";
            } else {
                dropdown.style.right = `${window.innerWidth - rect.right - 13}px`;
                dropdown.style.left = "auto";
            }

            const dropdownHeight = Math.max(dropdown.offsetHeight, 0);
            const spaceBelow = window.innerHeight - rect.bottom;
            const spaceAbove = rect.top;

            if (spaceBelow < dropdownHeight && spaceAbove > spaceBelow) {
                dropdown.style.top = `${rect.top - dropdownHeight}px`;
            } else {
                dropdown.style.top = `${rect.bottom}px`;
            }
        });
    },
    init() {
        this.$watch("open", (value) => {
            if (value) this.position();
        });
    },
}));

registerUi(Alpine);

// Free ships English only: choosing a language only switches the layout direction (RTL for Arabic).
Alpine.store("direction", {
    languages: [
        { id: "en", name: "English", shortName: "English", flag: "flag-us.svg" },
        { id: "ar", name: "Arabic (Saudi)", shortName: "Arabic", flag: "flag-sa.svg", badge: "RTL" },
        { id: "es", name: "Español", shortName: "Español", flag: "flag-es.svg" },
        { id: "de", name: "Deutsch", shortName: "Deutsch", flag: "flag-de.svg" },
    ],
    locale: localStorage.getItem("dir") === "rtl" ? "ar" : "en",
    get current() {
        return this.languages.find((l) => l.id === this.locale) || this.languages[0];
    },
    select(id) {
        this.locale = id;
        const dir = id === "ar" ? "rtl" : "ltr";
        localStorage.setItem("dir", dir);
        document.documentElement.setAttribute("dir", dir);
    },
});


Alpine.data("cashflowDialog", (open) => ({
    dirty: false,
    pending: false,
    trigger: null,
    viewportListener: null,
    confirming: false,
    init() {
        if (open) this.$nextTick(() => this.show());
    },
    show() {
        this.trigger = this.$refs.trigger || document.activeElement;
        this.$refs.dialog.showModal();
        const dialog = this.$refs.dialog;
        const viewport = window.visualViewport;
        if (dialog.hasAttribute("data-transaction-sheet") && viewport && !this.viewportListener) {
            this.viewportListener = () => {
                const bottom = document.documentElement.clientHeight - viewport.height - viewport.offsetTop;
                dialog.style.setProperty("--transaction-viewport-height", `${viewport.height}px`);
                dialog.style.setProperty("--transaction-viewport-bottom", `${Math.max(0, bottom)}px`);
            };
            viewport.addEventListener("resize", this.viewportListener);
            viewport.addEventListener("scroll", this.viewportListener);
            this.viewportListener();
        }
    },
    destroy() {
        this.stopViewportTracking();
    },
    stopViewportTracking() {
        if (!this.viewportListener) return;
        window.visualViewport.removeEventListener("resize", this.viewportListener);
        window.visualViewport.removeEventListener("scroll", this.viewportListener);
        this.viewportListener = null;
    },
    async close() {
        if (this.pending || this.confirming) return;
        const message = this.$refs.dialog.dataset.discard;
        if (this.dirty && !await this.confirm(message)) return;
        this.$refs.dialog.close();
    },
    async confirm(message) {
        this.confirming = true;
        try {
            return await confirmAction(message);
        } finally {
            this.confirming = false;
        }
    },
    restore() {
        this.stopViewportTracking();
        this.pending = false;
        this.dirty = false;
        for (const form of this.$refs.dialog.querySelectorAll("form")) {
            if (form.elements.length) {
                form.reset();
                for (const field of form.elements) {
                    if (["text", "checkbox", "select-one", "file"].includes(field.type)) {
                        field.dispatchEvent(new Event("change", { bubbles: true }));
                        field.dispatchEvent(new Event("input", { bubbles: true }));
                    }
                }
            }
        }
        this.dirty = false;
        this.trigger?.focus();
    },
    async submit(event) {
        if (this.pending || this.confirming) event.preventDefault();
        if (event.defaultPrevented) return;

        const form = event.target;
        if (form.hasAttribute("data-wallet-starts")) {
            const values = new FormData(form);
            const date = values.get("occurred_on");
            const starts = JSON.parse(form.dataset.walletStarts);
            const early = ["source_wallet_id", "destination_wallet_id"].some((field) => {
                const start = starts[values.get(field)];
                return start && date && date < start;
            });
            const approval = form.elements.namedItem("confirm_early_transaction");
            if (early && approval.value !== "1") {
                event.preventDefault();
                if (!await this.confirm(form.dataset.confirmEarly)) {
                    form.elements.namedItem("occurred_on").focus();
                    return;
                }
                approval.value = "1";
                try {
                    form.requestSubmit(event.submitter ?? undefined);
                } finally {
                    approval.value = "0";
                }
                return;
            }
        }

        this.pending = true;
        if (form.hasAttribute("data-quick-transaction")) {
            event.preventDefault();
            this.pending = await saveQuickTransaction(form);
        }
    },
}));

Alpine.data("cashflowImage", (current, fallback, reset) => ({
    reset,
    candidate: null,
    get preview() {
        return this.reset ? fallback : this.candidate || current;
    },
    select(event) {
        if (this.candidate) URL.revokeObjectURL(this.candidate);
        const file = event.target.files[0];
        this.candidate = file ? URL.createObjectURL(file) : null;
    },
    destroy() {
        if (this.candidate) URL.revokeObjectURL(this.candidate);
    },
}));

Alpine.start();
Alpine.nextTick(initializeNotifications);


// Initialize components on DOM ready
document.addEventListener("DOMContentLoaded", () => {
    // Map imports
    if (document.querySelector("#mapOne")) {
        import("./components/maps/world-map").then((module) => module.initMap());
    }
    // Chart imports
    initCharts();

    // Calendar init
    if (document.querySelector("#calendar")) {
        import("./components/calendar/init").then((module) => module.calendarInit());
    }

    // copy button
    const copyInput = document.getElementById("copy-input");
    if (copyInput) {
        // Select the copy button and input field
        const copyButton = document.getElementById("copy-button");
        const copyText = document.getElementById("copy-text");
        const websiteInput = document.getElementById("website-input");

        // Event listener for the copy button
        copyButton.addEventListener("click", () => {
            // Copy the input value to the clipboard
            navigator.clipboard.writeText(websiteInput.value).then(() => {
                // Change the text to "Copied"
                copyText.textContent = "Copied";

                // Reset the text back to "Copy" after 2 seconds
                setTimeout(() => {
                    copyText.textContent = "Copy";
                }, 2000);
            });
        });
    }
});
