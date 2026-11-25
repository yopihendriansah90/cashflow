const grouped = /^-?(?:[0-9]+|[0-9]{1,3}(?:\.[0-9]{3})+)$/;

export function rupiah(value) {
    return "Rp" + String(value).replace(/\B(?=(\d{3})+(?!\d))/g, ".");
}

export function transferTotal(amount, fee) {
    const valid = value => grouped.test(value) && !value.startsWith("-");
    if (!valid(amount) || (fee !== "" && !valid(fee))) return "—";

    return rupiah(BigInt(amount.replaceAll(".", "")) + BigInt(fee.replaceAll(".", "") || "0"));
}

export function registerRupiah(Alpine) {
    Alpine.magic("transferTotal", () => transferTotal);
    Alpine.directive("rupiah", (field, _, { cleanup }) => {
        let previous = field.value;

        function format(event) {
            if (event?.isComposing) return;
            const value = field.value;
            const position = field.selectionStart;
            const digitsAfter = value.slice(position).replace(/\D/g, "").length;
            const digitEdit = event && grouped.test(previous) && /^-?[0-9.]+$/.test(value)
                && (event.inputType?.startsWith("delete") || (event.inputType === "insertText" && /^[0-9]$/.test(event.data)));
            if (grouped.test(value) || digitEdit) {
                field.value = rupiah(value.replaceAll(".", "")).slice(2);
                let caret = field.value.length;
                for (let digits = digitsAfter; digits > 0 && caret > 0;) {
                    if (/\d/.test(field.value[--caret])) digits--;
                }
                if (event) field.setSelectionRange(caret, caret);
            }
            previous = field.value;
        }

        function deleteSeparator(event) {
            const start = field.selectionStart;
            if (start !== field.selectionEnd || !grouped.test(field.value)) return;
            if (event.inputType === "deleteContentBackward" && field.value[start - 1] === ".") {
                field.setSelectionRange(Math.max(0, start - 2), start);
            }
            if (event.inputType === "deleteContentForward" && field.value[start] === ".") {
                field.setSelectionRange(start, start + 2);
            }
        }

        format();
        field.defaultValue = field.value;
        field.addEventListener("beforeinput", deleteSeparator);
        field.addEventListener("input", format, true);
        cleanup(() => {
            field.removeEventListener("beforeinput", deleteSeparator);
            field.removeEventListener("input", format, true);
        });
    });
}
