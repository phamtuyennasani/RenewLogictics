Chính sách:
Tiếp theo chúng ta sẽ đến group Chính sách:
Hiện tại chính ta có group chính sách từ menu load từ sideber.
Giờ tôi muốn chúng ta sẽ tạo ra 1 config/polici để quản lý chính sách có dạng:
return [
    'quy-dinh-tao-don' =>[
        'contentvi' => true
        'content_editor' => true,
        'canEdit' => true
    ],
    ....
]

với mỗi type(quy-dinh-tao-don) sẽ sinh ra 1 menu tại group chính sách.
khi bấm vào menu đó, Nếu user là quyền đc Edit sẽ hiện lên giao diện nhập liệu để ADmin nhập chính sách.
Nếu user ko phải quyền Edit thì sẽ là bài giao diện bài viết