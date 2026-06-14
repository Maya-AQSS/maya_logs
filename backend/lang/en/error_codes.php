<?php

return [
    'validation' => [
        'application_id_required' => 'The application is required.',
        'application_id_invalid' => 'The selected application is not valid.',
        'code_required' => 'The code is required.',
        'code_max' => 'The code may not be greater than 50 characters.',
        'code_unique' => 'An error code with this value already exists in this application.',
        'name_required' => 'The name is required.',
        'name_max' => 'The name may not be greater than 200 characters.',
        'description_max' => 'The description may not be greater than 5000 characters.',
        'file_max' => 'The file path may not be greater than 255 characters.',
        'line_integer' => 'The line must be an integer.',
        'line_min' => 'The line must be at least 1.',
    ],
];
