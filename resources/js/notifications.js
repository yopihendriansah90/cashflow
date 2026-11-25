import Swal from "sweetalert2";
import "sweetalert2/dist/sweetalert2.min.css";

function notify(options) {
    const labels = document.querySelector("[data-app-notifications]")?.dataset;
    return Swal.fire({
        theme: document.documentElement.classList.contains("dark") ? "dark" : "light",
        target: document.querySelector("dialog[open]") ?? document.body,
        topLayer: typeof HTMLElement.prototype.showPopover === "function",
        confirmButtonText: labels?.confirm ?? "OK",
        cancelButtonText: labels?.cancel ?? "Batal",
        confirmButtonColor: "var(--color-brand-600)",
        keydownListenerCapture: true,
        ...options,
    });
}

export async function confirmAction(text, confirmButtonText) {
    const result = await notify({
        text, icon: "warning", showCancelButton: true, reverseButtons: true, focusCancel: true,
        ...(confirmButtonText ? { confirmButtonText } : {}),
    });
    return result.isConfirmed;
}

export function showError(text) {
    return notify({ text, icon: "error" });
}

export function initializeNotifications() {
    const flash = document.querySelector("[data-app-notifications]")?.dataset;
    if (flash?.errors) {
        showError(flash.errors);
    } else if (flash?.status) {
        notify({ text: flash.status, icon: "success", toast: true, position: "top-end", timer: 4500, showConfirmButton: false });
    }

    let invalidField;
    document.addEventListener("invalid", (event) => {
        event.preventDefault();
        if (invalidField) return;

        invalidField = event.target;
        setTimeout(async () => {
            const field = invalidField;
            try {
                await showError(field.validationMessage);
                field.focus();
            } finally {
                invalidField = undefined;
            }
        });
    }, true);

    const confirming = new WeakSet();
    const approved = new WeakSet();
    document.addEventListener("submit", async (event) => {
        const form = event.target;
        if (event.defaultPrevented || !form.dataset.confirmAction || approved.has(form)) return;

        event.preventDefault();
        if (confirming.has(form)) return;

        confirming.add(form);
        try {
            if (await confirmAction(form.dataset.confirmAction)) {
                approved.add(form);
                try {
                    form.requestSubmit(event.submitter ?? undefined);
                } finally {
                    approved.delete(form);
                }
            }
        } finally {
            confirming.delete(form);
        }
    });
}
