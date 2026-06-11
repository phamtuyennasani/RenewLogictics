import 'package:flutter/material.dart';

import '../../../../core/utils/date_formatters.dart';
import '../../../../shared/widgets/status_chip.dart';
import '../../domain/pickup.dart';

/// Card hiển thị một pickup ở danh sách.
class PickupCard extends StatelessWidget {
  const PickupCard({super.key, required this.pickup, required this.onTap});

  final Pickup pickup;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final customer = pickup.customer;

    return Card(
      margin: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: onTap,
        child: Padding(
          padding: const EdgeInsets.all(14),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Expanded(
                    child: Text(
                      pickup.maPickup,
                      style: theme.textTheme.titleMedium
                          ?.copyWith(fontWeight: FontWeight.w600),
                    ),
                  ),
                  StatusChip(badge: pickup.status),
                ],
              ),
              const SizedBox(height: 8),
              Text(
                customer.displayName,
                style: theme.textTheme.bodyLarge,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
              ),
              if (customer.address != null && customer.address!.isNotEmpty) ...[
                const SizedBox(height: 2),
                Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Icon(Icons.location_on_outlined,
                        size: 16, color: theme.hintColor),
                    const SizedBox(width: 4),
                    Expanded(
                      child: Text(
                        customer.address!,
                        style: theme.textTheme.bodySmall
                            ?.copyWith(color: theme.hintColor),
                        maxLines: 2,
                        overflow: TextOverflow.ellipsis,
                      ),
                    ),
                  ],
                ),
              ],
              const SizedBox(height: 10),
              Wrap(
                spacing: 12,
                runSpacing: 4,
                children: [
                  _Meta(
                    icon: Icons.inventory_2_outlined,
                    label: '${pickup.packageCount ?? 0} kiện',
                  ),
                  if (pickup.weightKg != null)
                    _Meta(
                      icon: Icons.scale_outlined,
                      label: DateFormatters.weight(pickup.weightKg),
                    ),
                  if (pickup.ordersCount != null)
                    _Meta(
                      icon: Icons.receipt_long_outlined,
                      label: '${pickup.ordersCount} đơn',
                    ),
                  if (pickup.scheduledAt != null)
                    _Meta(
                      icon: Icons.schedule_outlined,
                      label: DateFormatters.dateTime(pickup.scheduledAt),
                    ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _Meta extends StatelessWidget {
  const _Meta({required this.icon, required this.label});

  final IconData icon;
  final String label;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        Icon(icon, size: 15, color: theme.hintColor),
        const SizedBox(width: 4),
        Text(label, style: theme.textTheme.bodySmall),
      ],
    );
  }
}
