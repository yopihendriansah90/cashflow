import { confirmAction, showError } from "../notifications";

export default function quickTransaction() {
    return {
        loading: false,

        async open(trigger) {
            if (this.loading || document.querySelector("dialog[open]")) return;

            const { url, noWallet, addWallet, loadError } = trigger.dataset;
            this.loading = true;
            try {
                const response = await fetch(url, { redirect: "error" });
                if (response.status === 409) {
                    const { walletUrl } = await response.json();
                    if (await confirmAction(noWallet, addWallet)) {
                        location.assign(walletUrl);
                    }
                    return;
                }
                if (!response.ok) throw new Error("Could not load the transaction form");

                const html = new DOMParser().parseFromString(await response.text(), "text/html");
                const dialog = html.body.firstElementChild;
                if (!dialog?.matches("[data-quick-transaction-dialog]")) throw new Error("Invalid transaction form");

                if (!document.querySelector("dialog[open]")) {
                    trigger.focus();
                    this.$refs.form.replaceChildren(dialog);
                }
            } catch {
                await showError(loadError);
            } finally {
                this.loading = false;
            }
        },
    };
}

export async function saveQuickTransaction(form) {
    for (const error of form.querySelectorAll("[data-quick-error]")) {
        const field = error.previousElementSibling;
        field?.removeAttribute("aria-invalid");
        field?.removeAttribute("aria-describedby");
        error.remove();
    }

    try {
        const response = await fetch(form.action, {
            method: "POST",
            headers: { Accept: "application/json", "X-Requested-With": "XMLHttpRequest" },
            body: new FormData(form),
            redirect: "error",
        });
        const result = await response.json();
        if (response.status === 201 && result.saved) {
            location.reload();
            return true;
        }
        if (response.status === 422 && result.errors) {
            let firstField;
            for (const [name, messages] of Object.entries(result.errors)) {
                const fields = Array.from(form.elements).filter(field => field.name === name && !field.matches(":disabled"));
                const field = fields.find(field => field.getClientRects().length) ?? fields[0];
                if (!field) continue;

                const error = document.createElement("p");
                error.id = `quick-error-${name}`;
                error.dataset.quickError = "";
                error.className = "mt-1 text-sm text-error-600 dark:text-error-400";
                error.textContent = messages.join(" ");
                field.setAttribute("aria-invalid", "true");
                field.setAttribute("aria-describedby", error.id);
                field.after(error);
                firstField ??= field;
            }
            await showError(Object.values(result.errors).flat().join("\n"));
            firstField?.focus();
            return false;
        }
    } catch {
        // The same message covers failed requests and unexpected responses.
    }
    await showError(form.dataset.saveError);
    return false;
}
