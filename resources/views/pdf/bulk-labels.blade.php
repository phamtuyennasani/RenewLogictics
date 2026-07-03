<!doctype html>
<html lang="vi">
<head>
<meta charset="utf-8">
@include('pdf.partials.label-styles')
</head>
<body>
{{-- $items: mảng label-data của từng đơn (OrderPdfRenderer::labelData) --}}
@foreach($items as $item)
    @include('pdf.partials.label-pages', $item)
@endforeach
</body>
</html>
