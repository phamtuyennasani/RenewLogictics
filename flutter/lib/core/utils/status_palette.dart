import 'package:flutter/material.dart';

/// Map status `value` (từ PickupStatusEnum / OrderStatusEnum) → màu Material.
///
/// Backend trả Tailwind class trong `color` (vd `bg-amber-100 text-amber-700`)
/// không dùng được trực tiếp ở Flutter, nên app tự map theo `value`.
class StatusPalette {
  const StatusPalette._();

  /// Cặp màu (nền, chữ) cho chip trạng thái.
  static ({Color bg, Color fg}) of(String value) {
    switch (value) {
      // ----- Pickup status -----
      case 'moi_tao_pickup':
        return (bg: const Color(0xFFF5F5F5), fg: const Color(0xFF404040));
      case 'da_xac_nhan':
        return (bg: const Color(0xFFDBEAFE), fg: const Color(0xFF1D4ED8));
      case 'pickup_dang_lay':
        return (bg: const Color(0xFFFEF3C7), fg: const Color(0xFFB45309));
      case 'pickup_da_lay':
        return (bg: const Color(0xFFD1FAE5), fg: const Color(0xFF047857));
      case 'da_huy':
      case 'huy':
        return (bg: const Color(0xFFFEE2E2), fg: const Color(0xFFB91C1C));

      // ----- Order status (OPS) -----
      case 'da_nhan_hang':
        return (bg: const Color(0xFFCFFAFE), fg: const Color(0xFF0E7490));
      case 'moi_tao':
        return (bg: const Color(0xFFF5F5F5), fg: const Color(0xFF404040));

      default:
        return (bg: const Color(0xFFF1F5F9), fg: const Color(0xFF475569));
    }
  }
}
