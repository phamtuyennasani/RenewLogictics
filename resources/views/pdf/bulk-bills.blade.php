<!doctype html>
<html lang="vi">
<head>
<meta charset="utf-8">
@include('pdf.partials.bill-styles')
</head>
<body>
{{-- $items: mảng bill-data của từng đơn (withCvck=false) --}}
@foreach($items as $item)
    <div class="bulk-page">
        @include('pdf.partials.bill-pages', $item)
    </div>
@endforeach
</body>
</html>
