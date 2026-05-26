<?php

declare(strict_types=1);

require_once __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/lib/repository.php';

api_require_method(['GET']);

$repository = new ApiRepository(api_db());
$satellites = $repository->satellites();

api_legacy_json_response(array_map(
    static function (array $satellite): array {
        return [
            'id' => (int) $satellite['id'],
            'name' => $satellite['name'],
            'html_element_name' => $satellite['html_element_name'],
            'website' => $satellite['website'],
        ];
    },
    $satellites
));
