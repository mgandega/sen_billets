<?php

/**
 * Returns the importmap for this application.
 *
 * - "path" is a path inside the asset mapper system. Use the
 *     "debug:asset-map" command to see the full list of paths.
 *
 * - "entrypoint" (JavaScript only) set to true for any module that will
 *     be used as an "entrypoint" (and passed to the importmap() Twig function).
 *
 * The "importmap:require" command can be used to add new entries to this file.
 */
return [
    'app' => [
        'path' => './assets/app.js',
        'entrypoint' => true,
    ],
    'bootstrap' => [
    'version' => '5.3.2',
    ],
    'bootstrap.min.css' => [
    'path' => './assets/vendor/bootstrap/dist/css/bootstrap.min.css',
    'entrypoint' => true,
    'type' => 'css',
    ],
    'bootstrap.bundle.min.js' => [
    'path' => './assets/vendor/bootstrap/dist/js/bootstrap.bundle.min.js',
    'entrypoint' => true,
],

    'bootstrap-icons/font/bootstrap-icons.css' => [
        'version' => '1.11.1',
        'type' => 'css',
    ],
    '@popperjs/core' => [
        'version' => '2.11.8',
    ],
    '@hotwired/stimulus' => [
        'version' => '3.2.2',
    ],
    '@symfony/stimulus-bridge' => [
        'version' => '3.2.2',
    ],
    '@hotwired/turbo' => [
        'version' => '7.3.0',
    ],
    // '@symfony/ux-turbo' => [
    //     'version' => '2.13.2',
    // ],
    '@symfony/stimulus-bundle' => [
        'path' => './vendor/symfony/stimulus-bundle/assets/dist/loader.js',
    ],
];
