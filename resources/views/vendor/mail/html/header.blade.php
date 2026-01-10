@props(['url'])
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block;">
@if(trim($slot) === 'LeanScale')
<img src="{{ asset('images/leanscale-logo.svg') }}" class="logo" alt="LeanScale Logo">
@else
{{ $slot }}
@endif
</a>
</td>
</tr>
