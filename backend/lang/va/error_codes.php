<?php

return [
    'validation' => [
        'application_id_required' => "L'aplicació és obligatòria.",
        'application_id_invalid' => "L'aplicació seleccionada no és vàlida.",
        'code_required' => 'El codi és obligatori.',
        'code_max' => 'El codi no pot superar els 50 caràcters.',
        'code_unique' => "Ja existix un codi d'error amb eixe valor en esta aplicació.",
        'name_required' => 'El nom és obligatori.',
        'name_max' => 'El nom no pot superar els 200 caràcters.',
        'description_max' => 'La descripció no pot superar els 5000 caràcters.',
        'file_max' => "La ruta de l'arxiu no pot superar els 255 caràcters.",
        'line_integer' => 'La línia ha de ser un número enter.',
        'line_min' => 'La línia ha de ser com a mínim 1.',
    ],
];
