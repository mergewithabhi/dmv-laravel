@foreach ($sponsors as $sponsor)
<div>
    @if($sponsor->logo)
        @if($sponsor->website_url)
            <a href="{{ $sponsor->website_url }}" target="_blank" rel="noopener noreferrer" aria-label="Visit {{ $sponsor->name }}">
                <img src="{{ $sponsor->logo->url('thumb') ?: $sponsor->logo->url() }}" alt="{{ $sponsor->logo->alt_text ?: $sponsor->name }}" loading="lazy" decoding="async">
            </a>
        @else
            <img src="{{ $sponsor->logo->url('thumb') ?: $sponsor->logo->url() }}" alt="{{ $sponsor->logo->alt_text ?: $sponsor->name }}" loading="lazy" decoding="async">
        @endif
    @else
        <strong>{{ $sponsor->name }}</strong>
    @endif
</div>
@endforeach
