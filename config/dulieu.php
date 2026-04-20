<?php

/**
 * DulieuConfig — Cấu hình tất cả danh mục dữ liệu trong hệ thống
 *
 * Key = type (lọc trong bảng news)
 * Giá trị = metadata cho view
 */
return [

    // ==============================================================
    // NHÓM: DỊCH VỤ — /dich-vu/{type}
    // ==============================================================
    'dichvuchinh' => [
        'group'  => 'Dịch vụ',
        'route_group' => 'dichvu',
        'title'  => 'Dịch vụ chính',
        'columns'    => ['namevi' => 'Tên dịch vụ'],
        'formFields' => [
            'namevi' => ['label' => 'Tên dịch vụ', 'type' => 'text', 'required' => true,'placeholder' => 'Nhập tên dịch vụ'],
        ],
        'formOptions' => [
            'code' => ['label' => 'Mã dịch vụ', 'type' => 'text','name' => 'options2.code'],
            'ngaygiao'   => ['label' => 'Số ngày giao dự kiến', 'type' => 'number','name' => 'options2.ngaygiao','class' => 'text-center'],
        ],
        'canCreate' => true,
        'canEdit'   => true,
        'canDelete' => true,
    ],

    'dichvuchitiet' => [
        'group'  => 'Dịch vụ',
        'route_group' => 'dichvu',
        'title'  => 'Dịch vụ chi tiết',
        'columns'    => ['namevi' => 'Tên dịch vụ'],
        'formFields' => [
            'namevi' => ['label' => 'Tên dịch vụ', 'type' => 'text', 'required' => true],
            'numb'   => ['label' => 'Số thứ tự', 'type' => 'number'],
        ],
        'canCreate' => true,
        'canEdit'   => true,
        'canDelete' => true,
    ],

    'dichvudikem' => [
        'group'  => 'Dịch vụ',
        'title'  => 'Dịch vụ đi kèm',
        'route_group' => 'dichvu',
        'columns'    => ['namevi' => 'Tên dịch vụ', 'descvi' => 'Mô tả'],
        'formFields' => [
            'namevi' => ['label' => 'Tên dịch vụ', 'type' => 'text', 'required' => true],
            'descvi' => ['label' => 'Mô tả', 'type' => 'textarea'],
            'numb'   => ['label' => 'Số thứ tự', 'type' => 'number'],
        ],
        'canCreate' => true,
        'canEdit'   => true,
        'canDelete' => true,
    ],

    'chinhanh' => [
        'group'  => 'Dịch vụ',
        'route_group' => 'dichvu',
        'title'  => 'Chi nhánh nhận hàng',
        'columns'    => ['namevi' => 'Tên chi nhánh', 'descvi' => 'Địa chỉ'],
        'formFields' => [
            'namevi' => ['label' => 'Tên chi nhánh', 'type' => 'text', 'required' => true],
            'descvi' => ['label' => 'Địa chỉ', 'type' => 'textarea'],
            'numb'   => ['label' => 'Số thứ tự', 'type' => 'number'],
        ],
        'canCreate' => true,
        'canEdit'   => true,
        'canDelete' => true,
    ],

    'tinhtrangdon' => [
        'group'  => 'Dịch vụ',
        'title'  => 'Tình trạng đơn',
        'route_group' => 'dichvu',
        'columns'    => ['namevi' => 'Tên tình trạng'],
        'formFields' => [
            'namevi' => ['label' => 'Tên tình trạng', 'type' => 'text', 'required' => true],
            'numb'   => ['label' => 'Số thứ tự', 'type' => 'number'],
        ],
        'canCreate' => true,
        'canEdit'   => true,
        'canDelete' => true,
    ],

    // ==============================================================
    // NHÓM: ĐƠN VỊ — /don-vi/{type}
    // ==============================================================
    'loaikien' => [
        'group'  => 'Đơn vị',
        'title'  => 'Loại kiện',
        'route_group' => 'donvi',
        'columns'    => ['namevi' => 'Tên loại kiện'],
        'formFields' => [
            'namevi' => ['label' => 'Tên loại kiện', 'type' => 'text', 'required' => true],
            'numb'   => ['label' => 'Số thứ tự', 'type' => 'number'],
        ],
        'canCreate' => true,
        'canEdit'   => true,
        'canDelete' => true,
    ],

    'hanghoa' => [
        'group'  => 'Đơn vị',
        'title'  => 'Hàng hóa (Loại kiện)',
        'route_group' => 'donvi',
        'columns'    => ['namevi' => 'Tên hàng hóa'],
        'formFields' => [
            'namevi' => ['label' => 'Tên hàng hóa', 'type' => 'text', 'required' => true],
            'numb'   => ['label' => 'Số thứ tự', 'type' => 'number'],
        ],
        'canCreate' => true,
        'canEdit'   => true,
        'canDelete' => true,
    ],

    // ==============================================================
    // NHÓM: PHÂN LOẠI — /phan-loai/{type}
    // ==============================================================
    'loaibuugui' => [
        'group'  => 'Phân loại',
        'route_group' => 'phanloai',
        'title'  => 'Loại bưu gửi',
        'columns'    => ['namevi' => 'Tên loại bưu gửi'],
        'formFields' => [
            'namevi' => ['label' => 'Tên loại bưu gửi', 'type' => 'text', 'required' => true],
            'numb'   => ['label' => 'Số thứ tự', 'type' => 'number'],
        ],
        'canCreate' => true,
        'canEdit'   => true,
        'canDelete' => true,
    ],

    'lydoguihang' => [
        'group'  => 'Phân loại',
        'route_group' => 'phanloai',
        'title'  => 'Lý do gửi hàng',
        'columns'    => ['namevi' => 'Tên lý do'],
        'formFields' => [
            'namevi' => ['label' => 'Tên lý do', 'type' => 'text', 'required' => true],
            'numb'   => ['label' => 'Số thứ tự', 'type' => 'number'],
        ],
        'canCreate' => true,
        'canEdit'   => true,
        'canDelete' => true,
    ],

    'hinhthucgui' => [
        'group'  => 'Phân loại',
        'title'  => 'Hình thức gửi hàng',
        'route_group' => 'phanloai',
        'columns'    => ['namevi' => 'Tên hình thức'],
        'formFields' => [
            'namevi' => ['label' => 'Tên hình thức', 'type' => 'text', 'required' => true],
            'numb'   => ['label' => 'Số thứ tự', 'type' => 'number'],
        ],
        'canCreate' => true,
        'canEdit'   => true,
        'canDelete' => true,
    ],

    'deliveryterm' => [
        'group'  => 'Phân loại',
        'title'  => 'Delivery term',
        'route_group' => 'phanloai',
        'columns'    => ['namevi' => 'Tên delivery term'],
        'formFields' => [
            'namevi' => ['label' => 'Tên delivery term', 'type' => 'text', 'required' => true],
            'numb'   => ['label' => 'Số thứ tự', 'type' => 'number'],
        ],
        'canCreate' => true,
        'canEdit'   => true,
        'canDelete' => true,
    ],

    'phuongtien' => [
        'group'  => 'Phân loại',
        'title'  => 'Phương tiện',
        'route_group' => 'phanloai',
        'columns'    => ['namevi' => 'Tên phương tiện'],
        'formFields' => [
            'namevi' => ['label' => 'Tên phương tiện', 'type' => 'text', 'required' => true],
            'numb'   => ['label' => 'Số thứ tự', 'type' => 'number'],
        ],
        'canCreate' => true,
        'canEdit'   => true,
        'canDelete' => true,
    ],

    // ==============================================================
    // NHÓM: ĐẠI LÝ — /doi-tac/{type}
    // ==============================================================
    'daily' => [
        'group'  => 'Đại lý',
        'title'  => 'Danh sách đại lý',
        'route_group' => 'doitac',
        'columns'    => ['namevi' => 'Tên đại lý', 'descvi' => 'Địa chỉ'],
        'formFields' => [
            'namevi' => ['label' => 'Tên đại lý', 'type' => 'text', 'required' => true],
            'descvi' => ['label' => 'Địa chỉ', 'type' => 'textarea'],
            'numb'   => ['label' => 'Số thứ tự', 'type' => 'number'],
        ],
        'canCreate' => true,
        'canEdit'   => true,
        'canDelete' => true,
    ],

    'hangbay' => [
        'group'  => 'Đại lý',
        'title'  => 'Hãng bay',
        'route_group' => 'doitac',
        'columns'    => ['namevi' => 'Tên hãng bay'],
        'formFields' => [
            'namevi' => ['label' => 'Tên hãng bay', 'type' => 'text', 'required' => true],
            'numb'   => ['label' => 'Số thứ tự', 'type' => 'number'],
        ],
        'canCreate' => true,
        'canEdit'   => true,
        'canDelete' => true,
    ],

    'doitacchungchuyen' => [
        'group'  => 'Đại lý',
        'route_group' => 'doitac',
        'title'  => 'Đối tác chung chuyển',
        'columns'    => ['namevi' => 'Tên đối tác', 'descvi' => 'Mô tả'],
        'formFields' => [
            'namevi' => ['label' => 'Tên đối tác', 'type' => 'text', 'required' => true],
            'descvi' => ['label' => 'Mô tả', 'type' => 'textarea'],
            'numb'   => ['label' => 'Số thứ tự', 'type' => 'number'],
        ],
        'canCreate' => true,
        'canEdit'   => true,
        'canDelete' => true,
    ],

    // ==============================================================
    // NHÓM: PHỤ PHÍ — /phu-phi/{type}
    // ==============================================================
    'phuphidonhang' => [
        'group'  => 'Phụ phí',
        'route_group' => 'phuphi',
        'title'  => 'Phụ phí đơn hàng',
        'columns'    => ['namevi' => 'Tên phụ phí', 'descvi' => 'Ghi chú'],
        'formFields' => [
            'namevi' => ['label' => 'Tên phụ phí', 'type' => 'text', 'required' => true],
            'descvi' => ['label' => 'Ghi chú', 'type' => 'textarea'],
            'numb'   => ['label' => 'Số thứ tự', 'type' => 'number'],
        ],
        'canCreate' => true,
        'canEdit'   => true,
        'canDelete' => true,
    ],
];