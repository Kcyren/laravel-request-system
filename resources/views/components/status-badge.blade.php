@props(['status', 'testid' => null])
{{-- Text stays lowercase in the markup (smoke tests match on it); CSS capitalises it. --}}
<span class="badge badge-{{ $status }}"@if ($testid) data-testid="{{ $testid }}"@endif>{{ $status }}</span>
