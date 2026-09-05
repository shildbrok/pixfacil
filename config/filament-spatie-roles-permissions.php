<?php



return [

    'preload_roles' => true,

    'preload_permissions' => true,

    'navigation_section_group' => 'filament-spatie-roles-permissions::filament-spatie.section.roles_and_permissions', 

    'team_model' => \App\Models\Team::class,


    'should_register_on_navigation' => [
        'permissions' => true,
        'roles' => true,
    ],

    'guard_names' => [
        'web' => 'web',
        'api' => 'api',
    ],

    'toggleable_guard_names' => [
        'roles' => [
            'isToggledHiddenByDefault' => true,
        ],
        'permissions' => [
            'isToggledHiddenByDefault' => true,
        ],
    ],

    'default_guard_name' => 'web',

    'model_filter_key' => 'return \'%\'.$key;', 

    'user_name_column' => 'name',

    'generator' => [

        'guard_names' => [
            'web',
            'api',
        ],

        'permission_affixes' => [


            'viewAnyPermission' => 'view-any',
            'viewPermission' => 'view',
            'createPermission' => 'create',
            'updatePermission' => 'update',
            'deletePermission' => 'delete',
            'restorePermission' => 'restore',
            'forceDeletePermission' => 'force-delete',


            'replicate',
            'reorder',
        ],


        'permission_name' => 'return $permissionAffix . \' \' . $modelName;',


        'discover_models_through_filament_resources' => false,


        'model_directories' => [
            app_path('Models'),

        ],


        'custom_models' => [

        ],


        'excluded_models' => [

        ],

        'excluded_policy_models' => [
            \App\Models\User::class,
        ],


        'custom_permissions' => [

        ],

        'user_model' => \App\Models\User::class,

        'policies_namespace' => 'App\Policies',
    ],
];
