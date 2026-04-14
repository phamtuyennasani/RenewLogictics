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
        'title'  => 'Dịch vụ chính',
        'columns'    => ['namevi' => 'Tên dịch vụ'],
        'formFields' => [
            'namevi' => ['label' => 'Tên dịch vụ', 'type' => 'text', 'required' => true],
            'numb'   => ['label' => 'Số thứ tự', 'type' => 'number'],
        ],
        'formOptions' => [
            'code' => ['label' => 'Mã dịch vụ', 'type' => 'text','name' => 'options2.code'],
            'ngaygiao'   => ['label' => 'Số ngày giao dự kiến', 'type' => 'text','name' => 'options2.ngaygiao','class' => 'text-center'],
        ],
        'canCreate' => true,
        'canEdit'   => true,
        'canDelete' => true,
    ],

    'dichvuchitiet' => [
        'group'  => 'Dịch vụ',
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
    // NHÓM: QUỐC GIA — /place/{type}
    // ==============================================================
    'quocgia' => [
        'group'  => 'Quốc gia',
        'title'  => 'Quốc gia',
        'columns'    => ['namevi' => 'Tên quốc gia'],
        'formFields' => [
            'namevi' => ['label' => 'Tên quốc gia', 'type' => 'text', 'required' => true],
            'numb'   => ['label' => 'Số thứ tự', 'type' => 'number'],
        ],
        'canCreate' => true,
        'canEdit'   => true,
        'canDelete' => true,
    ],

    'tinhthanh' => [
        'group'  => 'Quốc gia',
        'title'  => 'Tỉnh / Thành phố',
        'columns'    => ['namevi' => 'Tên tỉnh / Thành phố'],
        'formFields' => [
            'namevi' => ['label' => 'Tên tỉnh / Thành phố', 'type' => 'text', 'required' => true],
            'numb'   => ['label' => 'Số thứ tự', 'type' => 'number'],
        ],
        'canCreate' => true,
        'canEdit'   => true,
        'canDelete' => true,
    ],

    'phuongxa' => [
        'group'  => 'Quốc gia',
        'title'  => 'Phường / Xã',
        'columns'    => ['namevi' => 'Tên phường / Xã'],
        'formFields' => [
            'namevi' => ['label' => 'Tên phường / Xã', 'type' => 'text', 'required' => true],
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
        'columns'    => ['namevi' => 'Tên hãng bay'],
        'formFields' => [
            'namevi' => ['label' => 'Tên hãng bay', 'type' => 'text', 'required' => true],
            'numb'   => ['label' => 'Số thứ tự', 'type' => 'number'],
        ],
        'canCreate' => true,
        'canEdit'   => true,
        'canDelete' => true,
    ],

    'doitac' => [
        'group'  => 'Đại lý',
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
    'phuphi' => [
        'group'  => 'Phụ phí',
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