<?php

declare(strict_types=1);

return [
    'credentials' => [
        'key'    => getenv('AWS_ACCESS_KEY_ID'),
        'secret' => getenv('AWS_SECRET_ACCESS_KEY'),
    ],
    'region'  => getenv('AWS_REGION'),
    'version' => 'latest',
    'bucket'  => getenv('AWS_BUCKET_NAME'),
];
