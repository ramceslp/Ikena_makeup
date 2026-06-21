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
        | iframe is permitted only for YouTube embed and Vimeo video URLs.
        */
        'posts' => [
            'HTML.Allowed'             => 'p,br,strong,em,u,s,h2,h3,h4,ul,ol,li,blockquote,a[href|target|rel],img[src|alt|width|height],iframe[src|width|height|frameborder],pre,code,span[class]',
            'HTML.SafeIframe'          => true,
            'URI.SafeIframeRegexp'     => '%^https://(www\.youtube\.com/embed/|player\.vimeo\.com/video/)%',
            'AutoFormat.AutoParagraph' => false,
            'Output.TidyFormat'        => false,
        ],

    ],

];
