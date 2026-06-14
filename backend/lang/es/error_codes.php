<?php

return [
    'validation' => [
        'application_id_required' => 'La aplicación es obligatoria.',
        'application_id_invalid' => 'La aplicación seleccionada no es válida.',
        'code_required' => 'El código es obligatorio.',
        'code_max' => 'El código no puede superar los 50 caracteres.',
        'code_unique' => 'Ya existe un código de error con ese valor en esta aplicación.',
        'name_required' => 'El nombre es obligatorio.',
        'name_max' => 'El nombre no puede superar los 200 caracteres.',
        'description_max' => 'La descripción no puede superar los 5000 caracteres.',
        'file_max' => 'La ruta del archivo no puede superar los 255 caracteres.',
        'line_integer' => 'La línea debe ser un número entero.',
        'line_min' => 'La línea debe ser como mínimo 1.',
    ],
];
