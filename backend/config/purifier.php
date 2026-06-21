<?php

return [

    'encoding'      => 'UTF-8',
    'finalize'      => true,
    'ignore_invalid_tags' => false,
    'cachePath'     => storage_path('app/purifier'),
    'cacheFileMode' => 0755,

    'settings' => [

        'default' => [
            'HTML.Doctype'             => 'HTML 4.01 Transitional',
            'HTML.Allowed'             => 'div,b,strong,i,em,u,a[href|title],ul,ol,li,p[style],br,span[style],img[width|height|alt|src]',
            'CSS.AllowedProperties'    => 'font,font-size,font-weight,font-style,font-family,text-decoration,padding-left,color,background-color,text-align',
            'AutoFormat.AutoParagraph' => true,
            'AutoFormat.RemoveEmpty'   => true,
        ],

        /*
        |--------------------------------------------------------------------------
        | Posts Profile
        |--------------------------------------------------------------------------
        | Used by Admin\PostController to sanitize post body HTML.
        | Allowlist matches the spec Domain 3 requirements.
        | iframe is permitted only for YouTube/Vimeo embed URLs (URI.SafeIframeRegexp).
        |
        | NOTE — h1 is intentionally excluded from the allowlist.
        | The post title is the page-level h1; TipTap will be configured without
        | an h1 button in PR2 so editors never produce a second h1.
        |
        | NOTE — allow + allowfullscreen are HTML5 iframe attributes absent from
        | HTMLPurifier's default HTML4 doctype. They cannot be added via HTML.Allowed
        | alone (that throws an ErrorException). Instead they are registered through
        | a custom HTMLPurifier definition — see Admin\PostController::cleanBody().
        | HTML.DefinitionID + HTML.DefinitionRev are required to activate custom
        | definitions; DefinitionRev must be bumped whenever the definition changes.
        |
        | AutoFormat.RemoveEmpty strips iframes whose src was rejected by
        | URI.SafeIframeRegexp (HTMLPurifier removes the src, leaving <iframe></iframe>).
        | The Predicate ensures the rule only removes elements that are empty due to
        | a missing required attribute; valid YouTube/Vimeo iframes retain their src
        | and are therefore NOT removed.
        */
        'posts' => [
            'HTML.Allowed'             => 'p,br,strong,em,u,s,h2,h3,h4,ul,ol,li,blockquote,a[href|target|rel],img[src|alt|width|height],iframe[src|width|height|frameborder|allowfullscreen|allow],pre,code,span[class]',
            'HTML.SafeIframe'          => true,
            'URI.SafeIframeRegexp'     => '%^https://(www\.youtube\.com/embed/|player\.vimeo\.com/video/)%',
            'AutoFormat.AutoParagraph' => false,
            'Output.TidyFormat'        => false,
            'AutoFormat.RemoveEmpty'   => true,
            'AutoFormat.RemoveEmpty.Predicate' => ['iframe' => [0 => 'src'], 'img' => [0 => 'src'], 'a' => [0 => 'href']],
            'HTML.DefinitionID'        => 'posts-embeds',
            'HTML.DefinitionRev'       => 1,
        ],

    ],

];
