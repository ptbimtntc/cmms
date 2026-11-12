{{--
    Chart.js, loaded once, with defaults matched to the design tokens (theme.css).
    Hex values mirror the tokens because the canvas cannot read CSS variables per draw.
--}}
@once
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
    <script>
        window.reportChartTheme = {
            bar: '#0284c7',      // --color-chart-1
            barStrong: '#075985',// --color-chart-1-strong
            grid: '#e2e8f0',     // --color-border
            axis: '#5b6b80',     // --color-text-muted
            ink: '#1e293b',      // --color-text
            surface: '#ffffff',  // --color-surface
        };
        if (window.Chart) {
            const t = window.reportChartTheme;
            Chart.defaults.font.family = getComputedStyle(document.body).fontFamily;
            Chart.defaults.color = t.axis;
            Chart.defaults.borderColor = t.grid;
            Chart.defaults.plugins.tooltip.backgroundColor = t.ink;
            Chart.defaults.plugins.tooltip.padding = 8;
            Chart.defaults.plugins.tooltip.cornerRadius = 8;
            Chart.defaults.maintainAspectRatio = false;
        }
    </script>
@endonce
