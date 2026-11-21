@use('Illuminate\View\ComponentAttributeBag')
@use('SiteBuilder\Components\Definitions\Gallery')
@use('SiteBuilder\Core\Content\JsonObject')
@use('SiteBuilder\Core\Content\Node')
@use('SiteBuilder\Core\Rendering\PropertyResolver')
@php
    $gallery = new Node('album-gallery', 'standard.gallery', props: new JsonObject([
        'label' => $snapshot['title'],
        'heading' => ($showHeading ?? true) ? $snapshot['title'] : null,
        'items' => $snapshot['items'],
        'columns' => $snapshot['columns'],
        'lightbox' => $snapshot['lightbox'],
    ]));
    $galleryProps = app(PropertyResolver::class)->resolve($gallery, new Gallery, $context->locale);
@endphp
@include('site-builder-components::blocks.gallery', ['props' => $galleryProps, 'attributes' => $attributes ?? new ComponentAttributeBag])
