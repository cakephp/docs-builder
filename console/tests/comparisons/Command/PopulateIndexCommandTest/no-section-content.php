<?php
return [
    [
        'id' => 'test-nested-html-nested',
        'type' => 'internal',
        'priority' => 'normal',
        'url' => '/1.1/en/test/nested.html#nested',
        'page_url' => '/1.1/en/test/nested.html',
        'level' => 0,
        'max_level' => 1,
        'position' => 0,
        'max_position' => 1,
        'hierarchy' => [
            'Test',
            'Nested',
        ],
        'title' => 'Nested',
        'contents' => 'Nested root section content.',
    ],

    [
        'id' => 'test-nested-html-level-1-subsection-1-title',
        'type' => 'internal',
        'priority' => 'normal',
        'url' => '/1.1/en/test/nested.html#level-1-subsection-1-title',
        'page_url' => '/1.1/en/test/nested.html',
        'level' => 1,
        'max_level' => 1,
        'position' => 1,
        'max_position' => 1,
        'hierarchy' => [
            'Test',
            'Nested',
            'Level 1 Subsection 1 Title',
        ],
        'title' => 'Level 1 Subsection 1 Title',
        'contents' => 'Level 1 subsection 1 content.',
    ],

    [
        'id' => 'more-html-more',
        'type' => 'internal',
        'priority' => 'normal',
        'url' => '/1.1/en/more.html#more',
        'page_url' => '/1.1/en/more.html',
        'level' => 0,
        'max_level' => 0,
        'position' => 0,
        'max_position' => 0,
        'hierarchy' => [
            'More',
        ],
        'title' => 'More',
        'contents' => 'More root section content.',
    ],

    [
        'id' => 'appendices-html-appendices',
        'type' => 'internal',
        'priority' => 'low',
        'url' => '/1.1/en/appendices.html#appendices',
        'page_url' => '/1.1/en/appendices.html',
        'level' => 0,
        'max_level' => 0,
        'position' => 0,
        'max_position' => 0,
        'hierarchy' => [
            'Appendices',
        ],
        'title' => 'Appendices',
        'contents' => 'Appendices root section content.',
    ],

    [
        'id' => 'appendices-low-priority-html-low-priority',
        'type' => 'internal',
        'priority' => 'low',
        'url' => '/1.1/en/appendices/low-priority.html#low-priority',
        'page_url' => '/1.1/en/appendices/low-priority.html',
        'level' => 0,
        'max_level' => 0,
        'position' => 0,
        'max_position' => 0,
        'hierarchy' => [
            'Appendices',
            'Low Priority',
        ],
        'title' => 'Low Priority',
        'contents' => 'Low priority root section content.',
    ],

    [
        'id' => 'https-example-com-foo',
        'type' => 'external',
        'priority' => 'normal',
        'url' => 'https://example.com/foo',
        'page_url' => 'https://example.com/foo',
        'level' => 0,
        'max_level' => 0,
        'position' => 0,
        'max_position' => 0,
        'hierarchy' => [
            'Foo',
        ],
        'title' => 'Foo',
        'contents' => null,
    ],

    [
        'id' => 'https-example-com-bar',
        'type' => 'external',
        'priority' => 'normal',
        'url' => 'https://example.com/bar',
        'page_url' => 'https://example.com/bar',
        'level' => 0,
        'max_level' => 0,
        'position' => 0,
        'max_position' => 0,
        'hierarchy' => [
            'Bar',
        ],
        'title' => 'Bar',
        'contents' => null,
    ],

    [
        'id' => 'https-example-com-baz',
        'type' => 'external',
        'priority' => 'normal',
        'url' => 'https://example.com/baz',
        'page_url' => 'https://example.com/baz',
        'level' => 0,
        'max_level' => 0,
        'position' => 0,
        'max_position' => 0,
        'hierarchy' => [
            'Baz',
        ],
        'title' => 'Baz',
        'contents' => null,
    ],
];
