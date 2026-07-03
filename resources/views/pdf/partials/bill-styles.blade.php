{{-- Styles dùng chung cho PDF bill (single + bulk) --}}
<style>
    @page { margin: 8mm; }
    * { box-sizing: border-box; }
    body { font-family: "DejaVu Sans", sans-serif; font-size: 8.5pt; color: #111827; margin: 0; }
    table { width: 100%; border-collapse: collapse; }
    td, th { vertical-align: top; }
    .b { border: 1px solid #111827; }
    .bb { border-bottom: 1px solid #111827; }
    .br { border-right: 1px solid #111827; }
    .sb { border-bottom: 1px solid #d1d5db; }
    .sr { border-right: 1px solid #d1d5db; }
    .cell { padding: 4px 6px; }
    .head { font-weight: bold; padding: 4px 6px; }
    .muted { color: #6b7280; }
    .page-break { page-break-before: always; }
    /* Bulk: mỗi đơn bắt đầu trang mới (đơn đầu tiên không cần break). */
    .bulk-page { page-break-before: always; }
    .bulk-page:first-child { page-break-before: avoid; }
</style>
