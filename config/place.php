<?php 

return[
    'countries' => [
        'group'  => 'Place',
        'title'  => 'Danh sách quốc gia',
        'route_group' => 'place',
        'columns'    => ['name' => 'Tên quốc gia'],
        'formFields' => [
            'name' => ['label' => 'Tên quốc gia', 'type' => 'text', 'required' => true,'class' => 'flex-1'],
        ],
        'canCreate' => true,
        'canEdit'   => true,
        'canDelete' => true,
    ],
    'state' => [
        'group'  => 'Place',
        'title'  => 'Danh sách tỉnh / bang',
        'route_group' => 'place',
        'columns'    => ['name' => 'Tên tỉnh / bang', 'country_id' => 'Quốc gia'],
        'formFields' => [
            'name' => ['label' => 'Tên tỉnh / bang', 'type' => 'text', 'required' => true,'class' => 'flex-1'],
            'country_id' => ['label' => 'Quốc gia', 'type' => 'select', 'required' => true,'class' => 'w-70'],
        ],
        'canCreate' => true,
        'canEdit'   => true,
        'canDelete' => true,
    ],
    'cities' => [
        'group'  => 'Place',
        'title'  => 'Danh sách thành phố',
        'route_group' => 'place',
        'columns'    => ['name' => 'Tên thành phố', 'state_id' => 'Tỉnh / bang', 'country_id' => 'Quốc gia'],
        'formFields' => [
            'name' => ['label' => 'Tên thành phố', 'type' => 'text', 'required' => true,'class' => 'flex-1'],
            'state_id' => ['label' => 'Tỉnh / bang', 'type' => 'select', 'required' => true,'class' => 'w-70'],
            'country_id' => ['label' => 'Quốc gia', 'type' => 'select', 'required' => true,'class' => 'w-70'],
        ],
        'canCreate' => true,
        'canEdit'   => true,
        'canDelete' => true,
    ],
];