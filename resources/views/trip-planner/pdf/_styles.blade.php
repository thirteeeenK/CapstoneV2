{{-- Shared print/PDF styles. dompdf has no flexbox or grid, so every layout is built from tables. --}}
<style>
    @page { margin: 16mm 15mm 18mm; }
    * { box-sizing: border-box; }
    body { font-family: 'DejaVu Sans', sans-serif; color: #1f2f38; font-size: 9.5px; line-height: 1.5; margin: 0; }
    h1, h2, h3, p { margin: 0; }
    table { border-collapse: collapse; width: 100%; }
    td, th { vertical-align: top; }
    .muted { color: #5c707b; } .small { font-size: 8px; } .strong { font-weight: bold; } .right { text-align: right; } .nowrap { white-space: nowrap; }
    .mono { font-family: 'DejaVu Sans Mono', monospace; }
    .warn { color: #8a4b00; }
    .spacer { height: 12px; }

    /* Masthead */
    .masthead td { vertical-align: middle; }
    .masthead .logo { width: 34px; height: 34px; }
    .masthead .wordmark { font-size: 16px; font-weight: bold; color: #074e6d; letter-spacing: 0.5px; }
    .masthead .tagline { font-size: 8px; color: #5c707b; letter-spacing: 1px; text-transform: uppercase; }
    .masthead .doc-title { font-size: 18px; font-weight: bold; color: #063f58; text-align: right; }
    .masthead .doc-meta { font-size: 8px; color: #5c707b; text-align: right; }
    .rule { height: 3px; background: #0a78a8; margin: 8px 0 12px; }

    /* Reference panel */
    .ref-panel { border: 2px solid #063f58; }
    .ref-panel td { padding: 10px 14px; vertical-align: middle; }
    .ref-panel .ref-label { font-size: 8px; font-weight: bold; color: #5c707b; text-transform: uppercase; letter-spacing: 1.5px; }
    .ref-panel .ref-code { font-family: 'DejaVu Sans Mono', monospace; font-size: 26px; font-weight: bold; letter-spacing: 3px; color: #063f58; line-height: 1.2; }
    .ref-panel .ref-side { background: #f0f9ff; border-left: 1px solid #b8e3f6; width: 38%; text-align: right; }
    .ref-panel .ref-amount { font-size: 16px; font-weight: bold; color: #063f58; }

    /* Status chips */
    .chip { display: inline-block; padding: 2px 7px; border-radius: 8px; font-size: 7.5px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.6px; border: 1px solid; }
    .chip-green { background: #e8f7ef; color: #146c43; border-color: #a7dcbf; }
    .chip-blue { background: #f0f9ff; color: #074e6d; border-color: #b8e3f6; }
    .chip-amber { background: #fff6e5; color: #8a4b00; border-color: #f3d19c; }
    .chip-red { background: #fdecec; color: #9b1c1c; border-color: #f2b8b8; }
    .chip-slate { background: #f1f4f6; color: #45555f; border-color: #d3dbe0; }

    /* Sections */
    .section { margin-top: 16px; }
    .section-title { font-size: 10px; font-weight: bold; text-transform: uppercase; letter-spacing: 1.2px; color: #074e6d; border-bottom: 1.5px solid #0a78a8; padding-bottom: 3px; margin-bottom: 7px; page-break-after: avoid; }
    .section-note { color: #5c707b; font-size: 8.5px; margin-bottom: 6px; }

    /* Fact cards (two-column) */
    .facts td.col { width: 49%; padding: 0; }
    .facts td.gap { width: 2%; padding: 0; }
    .card { border: 1px solid #d7e3e6; background: #fbfcfd; padding: 8px 10px; }
    .card .card-title { font-size: 8px; font-weight: bold; text-transform: uppercase; letter-spacing: 1px; color: #5c707b; margin-bottom: 4px; }
    .kv td { padding: 1.5px 0; }
    .kv td.k { color: #5c707b; width: 38%; }
    .kv td.v { font-weight: bold; }

    /* Data tables */
    .data { page-break-inside: auto; }
    .data th { background: #063f58; color: #ffffff; font-size: 7.5px; text-transform: uppercase; letter-spacing: 0.7px; text-align: left; padding: 5px 6px; }
    .data td { padding: 6px; border-bottom: 1px solid #e1e9ec; }
    .data tr { page-break-inside: avoid; }
    .data tr.alt td { background: #f7fafb; }
    .data .amount { text-align: right; white-space: nowrap; }

    /* Totals */
    .totals-wrap td.totals-cell { width: 55%; }
    .totals td { padding: 3px 6px; }
    .totals td.amount { text-align: right; white-space: nowrap; }
    .totals tr.grand td { border-top: 2px solid #063f58; font-size: 11px; font-weight: bold; padding-top: 5px; }
    .totals tr.paid td { color: #146c43; font-weight: bold; }
    .totals tr.balance td { background: #fff6e5; color: #8a4b00; font-weight: bold; }
    .totals tr.settled td { background: #e8f7ef; color: #146c43; font-weight: bold; }

    /* Notice boxes */
    .notice { border-left: 3px solid #0a78a8; background: #f0f9ff; padding: 7px 10px; margin-top: 8px; page-break-inside: avoid; }
    .notice-amber { border-left-color: #d98a1c; background: #fff6e5; }
    .notice ul { margin: 3px 0 0 14px; padding: 0; } .notice li { margin: 1.5px 0; }

    /* Itinerary */
    .page-break { page-break-before: always; }
    .day { margin-top: 14px; page-break-inside: avoid; }
    .day-band td { background: #063f58; color: #ffffff; padding: 6px 9px; vertical-align: middle; }
    .day-band .day-no { font-size: 11px; font-weight: bold; }
    .day-band .day-meta { text-align: right; font-size: 8px; color: #dff1fb; }
    .day-sub { background: #f0f9ff; border: 1px solid #b8e3f6; border-top: 0; padding: 5px 9px; font-size: 8.5px; }
    .schedule td { padding: 7px 6px; border-bottom: 1px solid #e1e9ec; }
    .schedule th { background: #f0f9ff; color: #074e6d; font-size: 7.5px; text-transform: uppercase; letter-spacing: 0.7px; text-align: left; padding: 4px 6px; border-bottom: 1px solid #b8e3f6; }
    .schedule tr { page-break-inside: avoid; }
    .schedule .time { width: 15%; font-weight: bold; color: #063f58; white-space: nowrap; }
    .schedule .stop-name { font-size: 10px; font-weight: bold; }
    .schedule .why { margin-top: 3px; color: #45555f; font-size: 8.5px; }
    .schedule .event td { background: #fbfcfd; color: #45555f; font-style: italic; padding: 5px 6px; }
    .km { font-weight: bold; color: #063f58; }
    .directions-block { margin-top: 8px; page-break-inside: avoid; }
    .directions-block ol { margin: 3px 0 0 16px; padding: 0; } .directions-block li { margin: 1px 0; }
    .subhead { font-size: 8px; font-weight: bold; text-transform: uppercase; letter-spacing: 1px; color: #5c707b; margin: 12px 0 4px; }
    .keep { page-break-inside: avoid; }
    .report p { margin-bottom: 6px; text-align: justify; }

    /* Footer */
    .page-footer { position: fixed; bottom: -12mm; left: 0; right: 0; height: 9mm; border-top: 1px solid #d7e3e6; padding-top: 3px; font-size: 7.5px; color: #5c707b; }
    .doc-footer { margin-top: 18px; padding-top: 6px; border-top: 1px solid #d7e3e6; font-size: 7.5px; color: #5c707b; }

    /* Browser print view: a centred A4-like sheet with a print button. */
    .print-controls { text-align: center; margin: 16px 0 0; }
    .print-controls button { padding: 10px 18px; background: #085e85; border: 0; border-radius: 6px; color: #ffffff; font-weight: bold; cursor: pointer; font-family: inherit; font-size: 13px; }
    body.web { background: #e7edf0; }
    body.web .sheet { max-width: 794px; margin: 18px auto 32px; background: #ffffff; padding: 44px 52px; box-shadow: 0 10px 30px rgba(6, 63, 88, 0.15); zoom: 1.25; }
    @media print {
        body.web { background: #ffffff; }
        body.web .sheet { max-width: none; margin: 0; padding: 0; box-shadow: none; zoom: 1; }
        .print-controls { display: none; }
    }
</style>
