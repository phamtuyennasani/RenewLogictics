import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

/// Shell điều hướng cho module OPS: bottom navigation với nút quét QR nổi giữa.
///
/// Dùng [StatefulNavigationShell] của go_router (indexedStack) để mỗi tab giữ
/// state riêng. 5 branch theo thứ tự:
///   0 Đơn hàng · 1 Pickup · 2 Quét QR (nút nổi giữa) · 3 Thông báo · 4 Tài khoản
class OpsShellScreen extends StatelessWidget {
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
  Widget build(BuildContext context) {
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
    return SizedBox(
      width: 64,
      height: 64,
      child: FloatingActionButton(
        onPressed: onTap,
        elevation: active ? 6 : 4,
        backgroundColor: active
            ? theme.colorScheme.primary
            : Color.alphaBlend(
                Colors.black.withValues(alpha: 0.06),
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

/// Thanh điều hướng dưới có notch cho FAB ở giữa.
class _OpsBottomBar extends StatelessWidget {
  const _OpsBottomBar({required this.currentIndex, required this.onTap});

  final int currentIndex;
  final ValueChanged<int> onTap;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return BottomAppBar(
      height: 70,
      padding: EdgeInsets.zero,
      color: theme.colorScheme.surface,
      surfaceTintColor: theme.colorScheme.surface,
      elevation: 0,
      shape: const CircularNotchedRectangle(),
      notchMargin: 9,
      child: DecoratedBox(
        decoration: BoxDecoration(
          border: Border(
            top: BorderSide(color: theme.colorScheme.outlineVariant),
          ),
          boxShadow: [
            BoxShadow(
              color: const Color(0xFF0F172A).withValues(alpha: 0.08),
              blurRadius: 20,
              offset: const Offset(0, -8),
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
            const SizedBox(width: 70),
            Expanded(
              child: _NavItem(
                icon: Icons.notifications_none_outlined,
                activeIcon: Icons.notifications,
                label: 'Thông báo',
                selected: currentIndex == 3,
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
  });

  final IconData icon;
  final IconData activeIcon;
  final String label;
  final bool selected;
  final VoidCallback onTap;

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
            AnimatedContainer(
              duration: const Duration(milliseconds: 180),
              width: 34,
              height: 28,
              decoration: BoxDecoration(
                color: selected
                    ? theme.colorScheme.primary.withValues(alpha: 0.1)
                    : Colors.transparent,
                borderRadius: BorderRadius.circular(999),
              ),
              child: Icon(selected ? activeIcon : icon, size: 21, color: color),
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
