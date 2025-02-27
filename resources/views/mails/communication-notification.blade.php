@component('mail::message')

{!! $html !!}

@if($uuid)
<div style="font-size: 10px;color:white;opacity:0;">ce-uuid-start{{$uuid}}ce-uuid-stop</div>
@endif

@endcomponent
