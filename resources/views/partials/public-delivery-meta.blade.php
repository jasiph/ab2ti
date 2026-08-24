@php
    $seo = $page['props']['seo'] ?? [];
@endphp
@if (! empty($seo['canonical_url']))
    <link rel="canonical" href="{{ $seo['canonical_url'] }}">
    <meta name="description" content="{{ $seo['description'] }}">
    <meta name="robots" content="{{ $seo['robots'] }}">

    <meta property="og:type" content="{{ $seo['og_type'] }}">
    <meta property="og:title" content="{{ $seo['og_title'] }}">
    <meta property="og:description" content="{{ $seo['og_description'] }}">
    <meta property="og:url" content="{{ $seo['og_url'] }}">
    <meta property="og:image" content="{{ $seo['og_image'] }}">
    @if (! empty($seo['og_image_width']))
        <meta property="og:image:width" content="{{ $seo['og_image_width'] }}">
    @endif
    @if (! empty($seo['og_image_height']))
        <meta property="og:image:height" content="{{ $seo['og_image_height'] }}">
    @endif
    <meta property="og:image:alt" content="{{ $seo['og_image_alt'] }}">

    <meta name="twitter:card" content="{{ $seo['twitter_card'] }}">
    <meta name="twitter:title" content="{{ $seo['twitter_title'] }}">
    <meta name="twitter:description" content="{{ $seo['twitter_description'] }}">
    <meta name="twitter:image" content="{{ $seo['twitter_image'] }}">
@endif
