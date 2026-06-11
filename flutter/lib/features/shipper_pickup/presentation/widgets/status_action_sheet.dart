import 'package:flutter/material.dart';

import '../../../../core/models/status_badge.dart';
import '../../../../core/utils/status_palette.dart';

/// Kết quả người dùng chọn trong bottom sheet đổi trạng thái.
class StatusActionChoice {
  const StatusActionChoice({required this.status, this.reason});

  /// `value` của transition đã chọn (nằm trong allowed_transitions).
  final String status;

  /// Lý do — chỉ có khi hủy (`da_huy`).
  final String? reason;
}

/// Bottom sheet đổi trạng thái pickup.
///
/// QUAN TRỌNG: chỉ render các nút từ [transitions] (allowed_transitions do API
/// trả). KHÔNG hardcode FSM. Khi chọn hủy (`da_huy`) thì bắt buộc nhập lý do.
class StatusActionSheet extends StatefulWidget {
  const StatusActionSheet({
    super.key,
    required this.transitions,
    this.isSubmitting = false,
  });

  final List<StatusBadge> transitions;
  final bool isSubmitting;

  /// Mở sheet, trả [StatusActionChoice] khi xác nhận, null nếu đóng.
  static Future<StatusActionChoice?> show(
    BuildContext context, {
    required List<StatusBadge> transitions,
  }) {
    return showModalBottomSheet<StatusActionChoice>(
      context: context,
      isScrollControlled: true,
      showDragHandle: true,
      builder: (_) => StatusActionSheet(transitions: transitions),
    );
  }

  @override
  State<StatusActionSheet> createState() => _StatusActionSheetState();
}

class _StatusActionSheetState extends State<StatusActionSheet> {
  /// Transition đang chờ nhập lý do (chỉ với hủy). Null = chưa chọn.
  StatusBadge? _pendingCancel;
  final _reasonController = TextEditingController();
  String? _reasonError;

  @override
  void dispose() {
    _reasonController.dispose();
    super.dispose();
  }

  bool _isCancel(StatusBadge t) => t.value == 'da_huy' || t.value == 'huy';

  void _onTransitionTap(StatusBadge t) {
    if (_isCancel(t)) {
      // Chuyển sang bước nhập lý do thay vì gửi ngay.
      setState(() => _pendingCancel = t);
      return;
    }
    Navigator.of(context).pop(StatusActionChoice(status: t.value));
  }

  void _confirmCancel() {
    final reason = _reasonController.text.trim();
    if (reason.isEmpty) {
      setState(() => _reasonError = 'Vui lòng nhập lý do hủy.');
      return;
    }
    Navigator.of(
      context,
    ).pop(StatusActionChoice(status: _pendingCancel!.value, reason: reason));
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final bottomInset = MediaQuery.of(context).viewInsets.bottom;

    return Padding(
      padding: EdgeInsets.fromLTRB(20, 4, 20, 18 + bottomInset),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          if (_pendingCancel == null)
            ..._buildTransitionList(theme)
          else
            ..._buildCancelReason(theme),
        ],
      ),
    );
  }

  List<Widget> _buildTransitionList(ThemeData theme) {
    return [
      Text('Cập nhật trạng thái', style: theme.textTheme.titleMedium),
      const SizedBox(height: 4),
      Text(
        'Chọn trạng thái tiếp theo cho phiếu lấy hàng.',
        style: theme.textTheme.bodySmall?.copyWith(color: theme.hintColor),
      ),
      const SizedBox(height: 16),
      for (final t in widget.transitions) ...[
        _TransitionButton(
          badge: t,
          danger: _isCancel(t),
          onTap: () => _onTransitionTap(t),
        ),
        const SizedBox(height: 10),
      ],
    ];
  }

  List<Widget> _buildCancelReason(ThemeData theme) {
    return [
      Row(
        children: [
          IconButton(
            onPressed: () => setState(() {
              _pendingCancel = null;
              _reasonError = null;
            }),
            icon: const Icon(Icons.arrow_back),
            tooltip: 'Quay lại',
          ),
          const SizedBox(width: 4),
          Expanded(
            child: Text(
              'Lý do hủy',
              style: theme.textTheme.titleMedium?.copyWith(
                fontWeight: FontWeight.w700,
              ),
            ),
          ),
        ],
      ),
      const SizedBox(height: 8),
      TextField(
        controller: _reasonController,
        autofocus: true,
        minLines: 2,
        maxLines: 4,
        textInputAction: TextInputAction.done,
        decoration: InputDecoration(
          hintText: 'Nhập lý do hủy phiếu lấy hàng',
          errorText: _reasonError,
          border: const OutlineInputBorder(),
        ),
        onChanged: (_) {
          if (_reasonError != null) setState(() => _reasonError = null);
        },
        onSubmitted: (_) => _confirmCancel(),
      ),
      const SizedBox(height: 16),
      FilledButton(
        style: FilledButton.styleFrom(
          backgroundColor: theme.colorScheme.error,
          foregroundColor: theme.colorScheme.onError,
        ),
        onPressed: _confirmCancel,
        child: const Text('Xác nhận hủy'),
      ),
    ];
  }
}

class _TransitionButton extends StatelessWidget {
  const _TransitionButton({
    required this.badge,
    required this.onTap,
    this.danger = false,
  });

  final StatusBadge badge;
  final VoidCallback onTap;
  final bool danger;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final palette = StatusPalette.of(badge.value);
    final label = badge.label.isEmpty ? badge.value : badge.label;
    final icon = danger ? Icons.cancel_outlined : Icons.arrow_forward_rounded;
    final color = danger ? theme.colorScheme.error : palette.fg;

    return Material(
      color: Colors.transparent,
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(8),
        child: Ink(
          padding: const EdgeInsets.all(12),
          decoration: BoxDecoration(
            color: color.withValues(alpha: danger ? 0.06 : 0.08),
            borderRadius: BorderRadius.circular(8),
            border: Border.all(color: color.withValues(alpha: 0.18)),
          ),
          child: Row(
            children: [
              Container(
                width: 36,
                height: 36,
                decoration: BoxDecoration(
                  color: color.withValues(alpha: 0.12),
                  borderRadius: BorderRadius.circular(8),
                ),
                child: Icon(icon, color: color, size: 20),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Text(
                  danger && label == 'Hủy' ? 'Hủy pickup' : label,
                  style: theme.textTheme.bodyLarge?.copyWith(
                    color: color,
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ),
              Icon(Icons.chevron_right_rounded, color: color),
            ],
          ),
        ),
      ),
    );
  }
}
