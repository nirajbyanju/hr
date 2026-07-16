<?php

return [
    'password' => env('DEMO_USER_PASSWORD', 'P@ssword'),

    'accounts' => [
        [
            'label' => 'Admin',
            'name' => 'Demo Admin',
            'email' => 'demo.admin@samriddhihr.local',
            'role' => 'Admin',
            'role_slug' => 'admin',
            'employee_code' => 'DEMO-ADMIN',
        ],
        [
            'label' => 'HR Admin',
            'name' => 'Demo HR Admin',
            'email' => 'hr.admin@samriddhihr.local',
            'role' => 'HR Admin',
            'role_slug' => 'hr-admin',
            'employee_code' => 'DEMO-HR',
        ],
        [
            'label' => 'Department Head',
            'name' => 'Demo Department Head',
            'email' => 'department.head@samriddhihr.local',
            'role' => 'Department Head',
            'role_slug' => 'department-head',
            'employee_code' => 'DEMO-DH',
        ],
        [
            'label' => 'Employee',
            'name' => 'Demo Employee',
            'email' => 'employee@samriddhihr.local',
            'role' => 'Employee',
            'role_slug' => 'employee',
            'employee_code' => 'DEMO-EMP',
        ],
    ],
];
