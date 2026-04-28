# Các vấn đề đã sửa trong implementation

## 1. Route name mismatch
- **Vấn đề**: `redirect()->route('order.view', $order->id)` không khớp với route định nghĩa
- **Sửa**: Đổi thành `redirect()->route('orders.show', $order->id)` theo route đã định nghĩa trong web.php

## 2. Static model không tồn tại
- **Vấn đề**: Gọi `\App\Models\Static::getContent()` nhưng model này chưa có
- **Sửa**: Tạm thời hardcode nội dung placeholder, sau này có thể tích hợp với model Static nếu cần

## 3. Livewire wire:model.live không hoạt động với nested components
- **Vấn đề**: Syntax `wire:model.live="sender"` trên tag `<livewire:...>` không hợp lệ
- **Sửa**: Bỏ wire:model, để parent component truyền data qua props và lắng nghe events

## 4. SenderSection thiếu prop $idCtv
- **Vấn đề**: View kiểm tra `@if($idCtv)` nhưng component không có property này
- **Sửa**: Thêm `public ?int $idCtv = null;` và nhận từ mount()

## 5. Điều kiện hiển thị saved senders
- **Vấn đề**: Hiển thị dropdown ngay cả khi chưa có dữ liệu
- **Sửa**: Thêm điều kiện `!empty($listSavedSenders)` để chỉ hiện khi có data

## Cách hoạt động đúng của Livewire nested components

### Parent → Child communication (truyền data xuống)
```php
// Parent
public array $sender = [...];

// View
<livewire:sender-section :sender="$sender" />

// Child
public array $sender = [];
```

### Child → Parent communication (gửi data lên)
```php
// Child
$this->dispatch('senderUpdated', sender: $this->sender);

// Parent
protected $listeners = ['senderUpdated' => 'handleSenderUpdated'];

public function handleSenderUpdated(array $sender): void
{
    $this->sender = $sender;
}
```

## Các bước tiếp theo để hoàn thiện

1. **Implement two-way binding giữa parent và child components**
   - Sử dụng events để sync state
   - Hoặc dùng `#[Modelable]` với `wire:model` đúng cách

2. **Test các luồng nghiệp vụ**
   - ADMIN/CS chọn Sale → load CTV
   - CTV tự động gán id_ctv
   - Tính cân nặng khi thay đổi DIM
   - Load saved contacts

3. **Xử lý validation errors**
   - Hiển thị lỗi từng field
   - Scroll to first error
   - Highlight invalid fields

4. **Thêm loading states**
   - Loading khi submit
   - Loading khi load saved contacts
   - Disable form khi đang submit

5. **Tích hợp với hệ thống cũ**
   - Đảm bảo tương thích với Order model hiện tại
   - Test với database thật
   - Migration data nếu cần
