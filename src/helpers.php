<?php

namespace Helpers;

use DiDom\Document;

function getTagContent(Document $document, string $tag, string $attribute = null): ?string
{
    $element = $document->has($tag) ? $document->find($tag)[0] : null;
    if ($element) {
        $content = $attribute ? optional($element)->attr($attribute) : optional($element)->text();
        return mb_substr($content, 0, 255);
    }
    return null;
}
