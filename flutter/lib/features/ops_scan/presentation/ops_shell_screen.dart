import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../shared/widgets/unread_badge.dart';
import '../../notifications/presentation/notifications_controller.dart';

/// Shell điều hướng cho module OPS: bottom navigation với nút quét QR nổi giữa.
///
/// Dùng [StatefulNavigationShell] của go_router (indexedStack) để mỗi tab giữ
/// state riêng. 5 branch theo thứ tự:
///   0 Đơn hàng · 1 Pickup · 2 Quét QR (nút nổi giữa) · 3 Thông báo · 4 Tài khoản
class OpsShellScreen extends ConsumerWidget {
  const OpsShellScreen({super.key, required this.navigationShell});

  final StatefulNavigationShell navigationShell;

  void _goBranch(int index) {
    navigationShell.goBranch(
      index,
      // Nhấn lại tab đang mở → về gốc branch (giống Instagram/YouTube).
      initialLocation: index == navigationShell.currentIndex,
    );
  }

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final unreadCount = ref.watch(
      notificationsControllerProvider.select((s) => s.unreadCount),
    );
    return Scaffold(
      extendBody: true,
      body: navigationShell,
      floatingActionButton: _ScanFab(
        active: navigationShell.currentIndex == 2,
        onTap: () => _goBranch(2),
      ),
      floatingActionButtonLocation: FloatingActionButtonLocation.centerDocked,
      bottomNavigationBar: _OpsBottomBar(
        currentIndex: navigationShell.currentIndex,
        unreadCount: unreadCount,
        onTap: _goBranch,
      ),
    );
  }
}

/// Nút quét QR nổi ở giữa (FAB tròn).
class _ScanFab extends StatelessWidget {
  const _ScanFab({required this.active, required this.onTap});

  final bool active;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Container(
      width: 66,
      height: 66,
      decoration: BoxDecoration(
        shape: BoxShape.circle,
        border: Border.all(
          color: Colors.white.withValues(alpha: 0.82),
          width: 3,
        ),
        boxShadow: [
          BoxShadow(
            color: theme.colorScheme.primary.withValues(
              alpha: active ? 0.28 : 0.2,
            ),
            blurRadius: 26,
            offset: const Offset(0, 14),
          ),
        ],
      ),
      child: FloatingActionButton(
        onPressed: onTap,
        elevation: 0,
        backgroundColor: active
            ? theme.colorScheme.primary
            : Color.alphaBlend(
                Colors.black.withValues(alpha: 0.05),
                theme.colorScheme.primary,
              ),
        shape: const CircleBorder(),
        child: Icon(
          Icons.qr_code_scanner,
          size: 30,
          color: theme.colorScheme.onPrimary,
        ),
      ),
    );
  }
}

/// Thanh điều hướng dưới dạng surface nổi, chừa nhịp cho FAB quét ở giữa.
class _OpsBottomBar extends StatelessWidget {
  const _OpsBottomBar({
    required this.currentIndex,
    required this.unreadCount,
    required this.onTap,
  });

  final int currentIndex;
  final int unreadCount;
  final ValueChanged<int> onTap;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return SafeArea(
      top: false,
      child: Padding(
        padding: const EdgeInsets.fromLTRB(10, 0, 10, 10),
        child: Container(
          height: 72,
          decoration: BoxDecoration(
            color: theme.colorScheme.surface.withValues(alpha: 0.96),
            borderRadius: BorderRadius.circular(26),
            border: Border.all(color: Colors.white.withValues(alpha: 0.78)),
            boxShadow: [
              BoxShadow(
                color: theme.colorScheme.primary.withValues(alpha: 0.1),
                blurRadius: 28,
                offset: const Offset(0, 14),
              ),
              BoxShadow(
                color: const Color(0xFF10201E).withValues(alpha: 0.05),
                blurRadius: 12,
                offset: const Offset(0, 4),
              ),
            ],
          ),
          child: Row(
            children: [
              Expanded(
                child: _NavItem(
                  icon: Icons.inventory_2_outlined,
                  activeIcon: Icons.inventory_2,
                  label: 'Đơn hàng',
                  selected: currentIndex == 0,
                  onTap: () => onTap(0),
                ),
              ),
              Expanded(
                child: _NavItem(
                  icon: Icons.local_shipping_outlined,
                  activeIcon: Icons.local_shipping,
                  label: 'Pickup',
                  selected: currentIndex == 1,
                  onTap: () => onTap(1),
                ),
              ),
              // Khoảng trống cho FAB ở giữa.
              const SizedBox(width: 76),
              Expanded(
                child: _NavItem(
                  icon: Icons.notifications_none_outlined,
                  activeIcon: Icons.notifications,
                  label: 'Thông báo',
                  selected: currentIndex == 3,
                  badgeCount: unreadCount,
                  onTap: () => onTap(3),
                ),
              ),
              Expanded(
                child: _NavItem(
                  icon: Icons.person_outline,
                  activeIcon: Icons.person,
                  label: 'Tài khoản',
                  selected: currentIndex == 4,
                  onTap: () => onTap(4),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _NavItem extends StatelessWidget {
  const _NavItem({
    required this.icon,
    required this.activeIcon,
    required this.label,
    required this.selected,
    required this.onTap,
    this.badgeCount = 0,
  });

  final IconData icon;
  final IconData activeIcon;
  final String label;
  final bool selected;
  final VoidCallback onTap;
  final int badgeCount;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final color = selected
        ? theme.colorScheme.primary
        : theme.colorScheme.onSurfaceVariant;

    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(12),
      child: Padding(
        padding: const EdgeInsets.symmetric(vertical: 7, horizontal: 2),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            UnreadBadge(
              count: badgeCount,
              child: AnimatedContainer(
                duration: const Duration(milliseconds: 220),
                curve: Curves.easeOutCubic,
                width: 36,
                height: 30,
                decoration: BoxDecoration(
                  color: selected
                      ? theme.colorScheme.primaryContainer.withValues(
                          alpha: 0.72,
                        )
                      : Colors.transparent,
                  borderRadius: BorderRadius.circular(13),
                ),
                child: Icon(
                  selected ? activeIcon : icon,
                  size: 21,
                  color: color,
                ),
              ),
            ),
            const SizedBox(height: 3),
            Text(
              label,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: theme.textTheme.labelSmall?.copyWith(
                color: color,
                fontWeight: selected ? FontWeight.w700 : FontWeight.w500,
              ),
            ),
          ],
        ),
      ),
    );
  }
}
