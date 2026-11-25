import { rupiah } from "../../rupiah";
import { mountChart, readConfig } from "../helpers";

export default function (element) {
    const days = Object.entries(readConfig(element));
    const reducedMotion = window.matchMedia("(prefers-reduced-motion: reduce)");

    mountChart(element, (dark) => ({
        chart: {
            type: "bar", height: 275, toolbar: { show: false },
            animations: { enabled: !reducedMotion.matches },
            background: "transparent",
        },
        theme: { mode: dark ? "dark" : "light" },
        colors: [
            getComputedStyle(element).getPropertyValue(dark ? "--color-success-400" : "--color-success-600").trim(),
            getComputedStyle(element).getPropertyValue(dark ? "--color-brand-400" : "--color-brand-600").trim(),
        ],
        series: [
            { name: "Pemasukan", data: days.map(([, value]) => Number(value.income)) },
            { name: "Pengeluaran", data: days.map(([, value]) => Number(value.expense)) },
        ],
        dataLabels: { enabled: false },
        xaxis: { categories: days.map(([day]) => day) },
        yaxis: { min: 0, labels: { formatter: (value) => rupiah(Math.round(value).toLocaleString("en-US", { useGrouping: false })) } },
        tooltip: {
            shared: true, intersect: false,
            x: { formatter: (day) => "Tanggal " + day },
            y: { formatter: (_, { seriesIndex, dataPointIndex }) => {
                const [, value] = days[dataPointIndex];
                return rupiah(seriesIndex === 0 ? value.income : value.expense);
            } },
        },
        responsive: [{ breakpoint: 640, options: {
            xaxis: { labels: { formatter: (day) => (Number(day) % 5 === 1 ? day : "") } },
            yaxis: { tickAmount: 4 },
        } }],
    }));
}
