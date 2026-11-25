import { showError } from "../notifications";

export default function cashbookFilters() {
    let pending;
    let applied;
    let desktop;
    let onViewportChange;
    const choices = {};

    return {
        loading: false,
        status: "",
        mode: "",

        init() {
            applied = new FormData(this.$el.querySelector("form[data-cashbook-filters]"));
            desktop = window.matchMedia("(min-width: 48rem)");
            onViewportChange = () => {
                this.restoreMode();
                this.resetTableScroll();
            };
            this.restoreMode();
            desktop.addEventListener("change", onViewportChange);
        },

        destroy() {
            pending?.abort();
            desktop?.removeEventListener("change", onViewportChange);
        },

        restoreMode() {
            const key = desktop.matches ? "desktop" : "mobile";
            let stored = choices[key];
            if (!stored) {
                try {
                    stored = localStorage.getItem(`cashbook-mode-${key}`);
                } catch {
                    // A browser can restrict access to storage.
                }
            }
            this.mode = ["cards", "table"].includes(stored) ? stored : desktop.matches ? "table" : "cards";
        },

        chooseMode(mode) {
            const key = desktop.matches ? "desktop" : "mobile";
            choices[key] = this.mode = mode;
            try {
                localStorage.setItem(`cashbook-mode-${key}`, mode);
            } catch {
                // Keep the choice for the current page when storage is unavailable.
            }
            this.resetTableScroll();
        },

        resetTableScroll() {
            if (desktop.matches || this.mode !== "table") return;

            this.$nextTick(() => {
                const table = this.$el.querySelector("[data-cashbook-scroll]");
                if (table) table.scrollLeft = 0;
            });
        },

        filter() {
            const form = this.$refs.filters;
            if (!form.elements.namedItem("bulan").validity.valid) return;

            const url = new URL(form.action);
            this.setFilters(url, new FormData(form));
            this.load(url);
        },

        paginate(event) {
            const link = event.target.closest("a[rel='prev'], a[rel='next']");
            if (!link || event.defaultPrevented || event.button !== 0 ||
                event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;

            event.preventDefault();
            this.load(new URL(link.href), true);
        },

        setFilters(url, values) {
            for (const key of ["bulan", "dompet"]) {
                const value = values.get(key);
                if (value) url.searchParams.set(key, value);
                else url.searchParams.delete(key);
            }
        },

        restoreFilters() {
            for (const key of ["bulan", "dompet"]) {
                this.$refs.filters.elements.namedItem(key).value = applied.get(key) ?? "";
            }
        },

        async load(url, fromPagination = false) {
            pending?.abort();
            const controller = new AbortController();
            pending = controller;
            this.loading = true;
            this.status = this.$el.dataset.loading;

            try {
                const response = await fetch(url, {
                    headers: { "X-Cashbook-Results": "1" },
                    signal: controller.signal,
                });
                if (!response.ok) throw new Error("Cashbook request failed");

                const html = new DOMParser().parseFromString(await response.text(), "text/html");
                const fragment = html.body.firstElementChild;
                if (!fragment?.matches("[data-cashbook-results]") || fragment.nextElementSibling) {
                    throw new Error("Invalid cashbook response");
                }
                if (controller.signal.aborted) return;

                const displayed = new URL(response.url);
                history.replaceState(history.state, "", displayed);
                applied = displayed.searchParams;
                this.restoreFilters();
                this.$refs.results.replaceChildren(fragment);
                this.resetTableScroll();

                const form = this.$el.querySelector("[data-cashbook-transaction-form]");
                if (form) {
                    const action = new URL(form.action);
                    this.setFilters(action, applied);
                    form.action = action.href;
                }
                if (fromPagination) this.$refs.results.focus();
                this.status = "";
            } catch {
                if (!controller.signal.aborted) {
                    this.restoreFilters();
                    this.status = "";
                    showError(this.$el.dataset.error);
                }
            } finally {
                if (pending === controller) {
                    this.loading = false;
                    pending = undefined;
                }
            }
        },
    };
}
