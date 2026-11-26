<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><title>{{ $campaignSubject }}</title></head>
<body>
<main>
    <h1>{{ $campaignSubject }}</h1>
    <p>{!! nl2br(e($body), false) !!}</p>
    @if($destinationUrl)
        <p><a href="{{ $destinationUrl }}">{{ $callToAction ?: 'Learn more' }}</a></p>
    @endif
    <p><a href="{{ $unsubscribeUrl }}">Unsubscribe</a></p>
    <img alt="" width="1" height="1" src="{{ $openPixelUrl }}">
</main>
</body>
</html>
