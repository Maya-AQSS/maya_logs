<?php

// IDs correspond to maya_authorization ApplicationSeeder insertion order (fresh DB).
// 1 = PortalCEED (maya-dashboard)
// 2 = DocuCEED   (maya-dms)
// 3 = AutoriCEED (maya-authorization)
// 4 = TraCEED    (maya-logs)
return [
    ['id' => 1, 'name' => 'PortalCEED', 'slug' => 'maya-dashboard'],
    ['id' => 2, 'name' => 'DocuCEED', 'slug' => 'maya-dms'],
    ['id' => 3, 'name' => 'AutoriCEED', 'slug' => 'maya-authorization'],
    ['id' => 4, 'name' => 'TraCEED', 'slug' => 'maya-logs'],
    ['id' => 5, 'name' => 'AudiCEED', 'slug' => 'maya-audit'],
];
