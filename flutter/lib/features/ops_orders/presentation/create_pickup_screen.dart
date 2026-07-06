import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/utils/date_formatters.dart';
import '../../../shared/widgets/app_surfaces.dart';
import '../../../shared/widgets/detail_widgets.dart';
import '../domain/ops_order.dart';
import 'ops_order_list_controller.dart';
import 'ops_order_providers.dart';

class CreatePickupScreen extends ConsumerStatefulWidget {
  const CreatePickupScreen({
    super.key,
    required this.orderId,
    required this.orderDetail,
  });

  final int orderId;
  final OpsOrderDetail orderDetail;

  @override
  ConsumerState<CreatePickupScreen> createState() => _CreatePickupScreenState();
}

class _CreatePickupScreenState extends ConsumerState<CreatePickupScreen> {
  final _formKey = GlobalKey<FormState>();

  late TextEditingController _companyCtrl;
  late TextEditingController _fullnameCtrl;
  late TextEditingController _phoneCtrl;
  late TextEditingController _emailCtrl;
  late TextEditingController _addressCtrl;
  late TextEditingController _noteCtrl;

  final String _country = 'VIETNAM';
  int? _selectedProvinceId;
  int? _selectedWardId;
  int? _selectedShipperId;
  DateTime? _scheduledAt;

  List<Map<String, dynamic>> _provinces = [];
  List<Map<String, dynamic>> _wards = [];
  List<Map<String, dynamic>> _shippers = [];

  bool _isLoadingProvinces = false;
  bool _isLoadingWards = false;
  bool _isLoadingShippers = false;
  bool _isSubmitting = false;

  @override
  void initState() {
    super.initState();
    final sender = widget.orderDetail.order.sender;
    _companyCtrl = TextEditingController(text: sender.company);
    _fullnameCtrl = TextEditingController(text: sender.fullname);
    _phoneCtrl = TextEditingController(text: sender.phone);
    _emailCtrl = TextEditingController(text: sender.email);
    _addressCtrl = TextEditingController(text: sender.address);
    _noteCtrl = TextEditingController();

    _loadProvinces();
    _loadShippers();
  }

  @override
  void dispose() {
    _companyCtrl.dispose();
    _fullnameCtrl.dispose();
    _phoneCtrl.dispose();
    _emailCtrl.dispose();
    _addressCtrl.dispose();
    _noteCtrl.dispose();
    super.dispose();
  }

  Future<void> _loadProvinces() async {
    setState(() => _isLoadingProvinces = true);
    try {
      final provinces = await ref.read(opsCommonApiProvider).provinces();
      setState(() {
        _provinces = provinces;
        _isLoadingProvinces = false;
      });
    } catch (_) {
      setState(() => _isLoadingProvinces = false);
    }
  }

  Future<void> _loadWards(int provinceId) async {
    setState(() {
      _isLoadingWards = true;
      _wards = [];
      _selectedWardId = null;
    });
    try {
      final wards = await ref.read(opsCommonApiProvider).wards(provinceId);
      setState(() {
        _wards = wards;
        _isLoadingWards = false;
      });
    } catch (_) {
      setState(() => _isLoadingWards = false);
    }
  }

  Future<void> _loadShippers() async {
    setState(() => _isLoadingShippers = true);
    try {
      final shippers = await ref.read(opsCommonApiProvider).shippers();
      setState(() {
        _shippers = shippers;
        _isLoadingShippers = false;
      });
    } catch (_) {
      setState(() => _isLoadingShippers = false);
    }
  }

  Future<void> _pickScheduledDate() async {
    final date = await showDatePicker(
      context: context,
      initialDate: DateTime.now(),
      firstDate: DateTime.now(),
      lastDate: DateTime.now().add(const Duration(days: 365)),
    );
    if (date == null || !mounted) return;

    final time = await showTimePicker(
      context: context,
      initialTime: TimeOfDay.now(),
    );
    if (time == null) return;

    setState(() {
      _scheduledAt = DateTime(
        date.year,
        date.month,
        date.day,
        time.hour,
        time.minute,
      );
    });
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) return;
    if (_selectedProvinceId == null) {
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(const SnackBar(content: Text('Vui lòng chọn tỉnh/thành')));
      return;
    }
    if (_selectedWardId == null) {
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(const SnackBar(content: Text('Vui lòng chọn phường/xã')));
      return;
    }

    setState(() => _isSubmitting = true);

    try {
      final data = {
        'company': _companyCtrl.text.trim(),
        'fullname': _fullnameCtrl.text.trim(),
        'phone': _phoneCtrl.text.trim(),
        'email': _emailCtrl.text.trim().isEmpty ? null : _emailCtrl.text.trim(),
        'country': _country,
        'address': _addressCtrl.text.trim(),
        'id_city': _selectedProvinceId,
        'id_ward': _selectedWardId,
        'shipper_id': _selectedShipperId,
        'scheduled_at': _scheduledAt?.toIso8601String(),
        'note': _noteCtrl.text.trim().isEmpty ? null : _noteCtrl.text.trim(),
      };

      await ref
          .read(opsOrderRepositoryProvider)
          .createPickup(widget.orderId, data);

      if (!mounted) return;
      ref.read(opsOrderListControllerProvider.notifier).refresh();

      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Đã tạo phiếu pickup thành công')),
      );

      context.pop();
      context.pop();
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text('Lỗi: ${e.toString()}')));
    } finally {
      if (mounted) setState(() => _isSubmitting = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final order = widget.orderDetail.order;

    return Scaffold(
      appBar: AppBar(title: const Text('Tạo phiếu pickup')),
      body: AppPage(
        child: Form(
          key: _formKey,
          child: ListView(
            padding: EdgeInsets.fromLTRB(
              16,
              10,
              16,
              112 + MediaQuery.of(context).padding.bottom,
            ),
            children: [
              AppHeroPanel(
                trailingIcon: Icons.add_location_alt_outlined,
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'Tạo pickup từ đơn hàng',
                      style: theme.textTheme.bodyMedium?.copyWith(
                        color: Colors.white.withValues(alpha: 0.78),
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                    const SizedBox(height: 6),
                    Text(
                      order.idBill,
                      style: theme.textTheme.headlineSmall?.copyWith(
                        color: Colors.white,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    if (order.trackingCode?.trim().isNotEmpty == true) ...[
                      const SizedBox(height: 4),
                      Text(
                        order.trackingCode!,
                        style: theme.textTheme.bodyMedium?.copyWith(
                          color: Colors.white.withValues(alpha: 0.84),
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                    ],
                    const SizedBox(height: 12),
                    Wrap(
                      spacing: 8,
                      runSpacing: 8,
                      children: [
                        MetaChip(
                          icon: Icons.inventory_2_outlined,
                          label:
                              '${order.packageCount ?? widget.orderDetail.packages.length} kiện',
                          color: Colors.white,
                        ),
                        if (order.weightKg != null)
                          MetaChip(
                            icon: Icons.scale_outlined,
                            label: DateFormatters.weight(order.weightKg),
                            color: Colors.white,
                          ),
                      ],
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 14),
              SectionCard(
                icon: Icons.business_outlined,
                title: 'Người gửi',
                child: Column(
                  children: [
                    _TextField(
                      controller: _companyCtrl,
                      label: 'Công ty *',
                      icon: Icons.apartment_outlined,
                      validator: (v) =>
                          v?.trim().isEmpty ?? true ? 'Bắt buộc' : null,
                    ),
                    const SizedBox(height: 12),
                    _TextField(
                      controller: _fullnameCtrl,
                      label: 'Họ tên *',
                      icon: Icons.person_outline,
                      validator: (v) =>
                          v?.trim().isEmpty ?? true ? 'Bắt buộc' : null,
                    ),
                    const SizedBox(height: 12),
                    _TextField(
                      controller: _phoneCtrl,
                      label: 'Số điện thoại *',
                      icon: Icons.phone_outlined,
                      keyboardType: TextInputType.phone,
                      validator: (v) =>
                          v?.trim().isEmpty ?? true ? 'Bắt buộc' : null,
                    ),
                    const SizedBox(height: 12),
                    _TextField(
                      controller: _emailCtrl,
                      label: 'Email',
                      icon: Icons.email_outlined,
                      keyboardType: TextInputType.emailAddress,
                    ),
                    const SizedBox(height: 12),
                    _TextField(
                      controller: _addressCtrl,
                      label: 'Địa chỉ *',
                      icon: Icons.location_on_outlined,
                      maxLines: 2,
                      validator: (v) =>
                          v?.trim().isEmpty ?? true ? 'Bắt buộc' : null,
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 14),
              SectionCard(
                icon: Icons.map_outlined,
                title: 'Địa bàn lấy hàng',
                child: Column(
                  children: [
                    DropdownButtonFormField<int>(
                      initialValue: _selectedProvinceId,
                      decoration: InputDecoration(
                        labelText: 'Tỉnh/Thành *',
                        prefixIcon: const Icon(Icons.location_city_outlined),
                        suffixIcon: _isLoadingProvinces
                            ? const _FieldSpinner()
                            : null,
                      ),
                      items: _provinces.map((p) {
                        return DropdownMenuItem(
                          value: p['id'] as int,
                          child: Text(p['name'] as String),
                        );
                      }).toList(),
                      onChanged: _isLoadingProvinces
                          ? null
                          : (value) {
                              setState(() => _selectedProvinceId = value);
                              if (value != null) _loadWards(value);
                            },
                    ),
                    const SizedBox(height: 12),
                    DropdownButtonFormField<int>(
                      initialValue: _selectedWardId,
                      decoration: InputDecoration(
                        labelText: 'Phường/Xã *',
                        prefixIcon: const Icon(Icons.place_outlined),
                        suffixIcon: _isLoadingWards
                            ? const _FieldSpinner()
                            : null,
                      ),
                      items: _wards.map((w) {
                        return DropdownMenuItem(
                          value: w['id'] as int,
                          child: Text(w['name'] as String),
                        );
                      }).toList(),
                      onChanged: _isLoadingWards
                          ? null
                          : (value) => setState(() => _selectedWardId = value),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 14),
              SectionCard(
                icon: Icons.delivery_dining_outlined,
                title: 'Điều phối',
                child: Column(
                  children: [
                    DropdownButtonFormField<int>(
                      initialValue: _selectedShipperId,
                      decoration: InputDecoration(
                        labelText: 'Shipper (tùy chọn)',
                        prefixIcon: const Icon(
                          Icons.person_pin_circle_outlined,
                        ),
                        suffixIcon: _isLoadingShippers
                            ? const _FieldSpinner()
                            : null,
                      ),
                      items: _shippers.map((s) {
                        return DropdownMenuItem(
                          value: s['id'] as int,
                          child: Text(s['name'] as String),
                        );
                      }).toList(),
                      onChanged: _isLoadingShippers
                          ? null
                          : (value) =>
                                setState(() => _selectedShipperId = value),
                    ),
                    const SizedBox(height: 12),
                    AppSurface(
                      padding: EdgeInsets.zero,
                      onTap: _pickScheduledDate,
                      child: ListTile(
                        leading: const Icon(Icons.calendar_today_outlined),
                        title: Text(
                          _scheduledAt == null
                              ? 'Ngày hẹn (tùy chọn)'
                              : DateFormatters.dateTime(_scheduledAt),
                        ),
                        trailing: const Icon(Icons.chevron_right_rounded),
                      ),
                    ),
                    const SizedBox(height: 12),
                    _TextField(
                      controller: _noteCtrl,
                      label: 'Ghi chú',
                      icon: Icons.sticky_note_2_outlined,
                      maxLines: 3,
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
      bottomNavigationBar: DecoratedBox(
        decoration: BoxDecoration(
          color: theme.colorScheme.surface,
          border: Border(
            top: BorderSide(color: theme.colorScheme.outlineVariant),
          ),
          boxShadow: [
            BoxShadow(
              color: const Color(0xFF10201E).withValues(alpha: 0.06),
              blurRadius: 22,
              offset: const Offset(0, -8),
            ),
          ],
        ),
        child: SafeArea(
          child: Padding(
            padding: const EdgeInsets.fromLTRB(16, 10, 16, 12),
            child: FilledButton.icon(
              onPressed: _isSubmitting ? null : _submit,
              icon: _isSubmitting
                  ? const SizedBox(
                      width: 18,
                      height: 18,
                      child: CircularProgressIndicator(strokeWidth: 2),
                    )
                  : const Icon(Icons.add_box_outlined),
              label: Text(
                _isSubmitting ? 'Đang tạo phiếu...' : 'Tạo phiếu pickup',
              ),
              style: FilledButton.styleFrom(
                minimumSize: const Size.fromHeight(50),
              ),
            ),
          ),
        ),
      ),
    );
  }
}

class _TextField extends StatelessWidget {
  const _TextField({
    required this.controller,
    required this.label,
    required this.icon,
    this.keyboardType,
    this.maxLines = 1,
    this.validator,
  });

  final TextEditingController controller;
  final String label;
  final IconData icon;
  final TextInputType? keyboardType;
  final int maxLines;
  final FormFieldValidator<String>? validator;

  @override
  Widget build(BuildContext context) {
    return TextFormField(
      controller: controller,
      keyboardType: keyboardType,
      maxLines: maxLines,
      decoration: InputDecoration(labelText: label, prefixIcon: Icon(icon)),
      validator: validator,
    );
  }
}

class _FieldSpinner extends StatelessWidget {
  const _FieldSpinner();

  @override
  Widget build(BuildContext context) {
    return const Padding(
      padding: EdgeInsets.all(12),
      child: SizedBox(
        width: 16,
        height: 16,
        child: CircularProgressIndicator(strokeWidth: 2),
      ),
    );
  }
}
