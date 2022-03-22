<?php
return [
    [
        'id' => 'test-html-namespace-Foo-Bar',
        'type' => 'internal',
        'priority' => 'normal',
        'url' => '/1.1/en/test.html#namespace-Foo\\Bar',
        'page_url' => '/1.1/en/test.html',
        'level' => 0,
        'max_level' => 2,
        'position' => 0,
        'max_position' => 3,
        'hierarchy' => [
            'Test',
        ],
        'title' => 'Test',
        'contents' => 'class Foo\\Bar\\Baz Test root section content.',
    ],

    [
        'id' => 'test-html-level-1-subsection-1-title',
        'type' => 'internal',
        'priority' => 'normal',
        'url' => '/1.1/en/test.html#level-1-subsection-1-title',
        'page_url' => '/1.1/en/test.html',
        'level' => 1,
        'max_level' => 2,
        'position' => 1,
        'max_position' => 3,
        'hierarchy' => [
            'Test',
            'Level 1 Subsection 1 Title',
        ],
        'title' => 'Level 1 Subsection 1 Title',
        'contents' => 'Foo\\Bar\\Baz::method(string $argument) $argument - Method argument description. ' .
            'Level 1 subsection 1 content: $foo = new \\Foo\\Bar\\Baz(); $value = $foo->method(\'argument\'); ' .
            'Lorem ipsum, method(\'argument\') dolor sit amet: <method value="argument" /> Admonition content',
    ],

    [
        'id' => 'test-html-level-2-subsection-1-title',
        'type' => 'internal',
        'priority' => 'normal',
        'url' => '/1.1/en/test.html#level-2-subsection-1-title',
        'page_url' => '/1.1/en/test.html',
        'level' => 2,
        'max_level' => 2,
        'position' => 2,
        'max_position' => 3,
        'hierarchy' => [
            'Test',
            'Level 1 Subsection 1 Title',
            'Level 2 Subsection 1 Title',
        ],
        'title' => 'Level 2 Subsection 1 Title',
        'contents' => 'Level 2 subsection 1 content.',
    ],

    [
        'id' => 'test-html-level-1-subsection-2-title',
        'type' => 'internal',
        'priority' => 'normal',
        'url' => '/1.1/en/test.html#level-1-subsection-2-title',
        'page_url' => '/1.1/en/test.html',
        'level' => 1,
        'max_level' => 2,
        'position' => 3,
        'max_position' => 3,
        'hierarchy' => [
            'Test',
            'Level 1 Subsection 2 Title',
        ],
        'title' => 'Level 1 Subsection 2 Title',
        'contents' => 'Level 1 subsection 2 content.',
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
