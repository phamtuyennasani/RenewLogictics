import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../domain/ops_order.dart';
import '../domain/ops_order_repository.dart';
import 'ops_order_list_controller.dart';
import 'ops_order_providers.dart';

/// Form tạo pickup từ order (full form theo plan).
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

  // Form fields
  late TextEditingController _companyCtrl;
  late TextEditingController _fullnameCtrl;
  late TextEditingController _phoneCtrl;
  late TextEditingController _emailCtrl;
  late TextEditingController _addressCtrl;
  late TextEditingController _noteCtrl;

  String _country = 'VIETNAM';
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
    } catch (e) {
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
    } catch (e) {
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
    } catch (e) {
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
    if (date == null) return;

    if (!mounted) return;
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
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Vui lòng chọn tỉnh/thành')),
      );
      return;
    }
    if (_selectedWardId == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Vui lòng chọn phường/xã')),
      );
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

      await ref.read(opsOrderRepositoryProvider).createPickup(widget.orderId, data);

      if (!mounted) return;

      // Refresh order list
      ref.read(opsOrderListControllerProvider.notifier).refresh();

      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Đã tạo phiếu pickup thành công')),
      );

      context.pop();
      context.pop(); // Pop back to order list
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Lỗi: ${e.toString()}')),
      );
    } finally {
      if (mounted) setState(() => _isSubmitting = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Tạo phiếu pickup'),
      ),
      body: Form(
        key: _formKey,
        child: ListView(
          padding: const EdgeInsets.all(16),
          children: [
            const Text(
              'Thông tin người gửi',
              style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold),
            ),
            const SizedBox(height: 12),
            TextFormField(
              controller: _companyCtrl,
              decoration: const InputDecoration(
                labelText: 'Công ty *',
                border: OutlineInputBorder(),
              ),
              validator: (v) => v?.trim().isEmpty ?? true ? 'Bắt buộc' : null,
            ),
            const SizedBox(height: 12),
            TextFormField(
              controller: _fullnameCtrl,
              decoration: const InputDecoration(
                labelText: 'Họ tên *',
                border: OutlineInputBorder(),
              ),
              validator: (v) => v?.trim().isEmpty ?? true ? 'Bắt buộc' : null,
            ),
            const SizedBox(height: 12),
            TextFormField(
              controller: _phoneCtrl,
              decoration: const InputDecoration(
                labelText: 'Số điện thoại *',
                border: OutlineInputBorder(),
              ),
              keyboardType: TextInputType.phone,
              validator: (v) => v?.trim().isEmpty ?? true ? 'Bắt buộc' : null,
            ),
            const SizedBox(height: 12),
            TextFormField(
              controller: _emailCtrl,
              decoration: const InputDecoration(
                labelText: 'Email',
                border: OutlineInputBorder(),
              ),
              keyboardType: TextInputType.emailAddress,
            ),
            const SizedBox(height: 12),
            TextFormField(
              controller: _addressCtrl,
              decoration: const InputDecoration(
                labelText: 'Địa chỉ *',
                border: OutlineInputBorder(),
              ),
              maxLines: 2,
              validator: (v) => v?.trim().isEmpty ?? true ? 'Bắt buộc' : null,
            ),
            const SizedBox(height: 12),
            DropdownButtonFormField<int>(
              value: _selectedProvinceId,
              decoration: const InputDecoration(
                labelText: 'Tỉnh/Thành *',
                border: OutlineInputBorder(),
              ),
              items: _provinces.map((p) {
                return DropdownMenuItem(
                  value: p['id'] as int,
                  child: Text(p['name'] as String),
                );
              }).toList(),
              onChanged: (value) {
                setState(() => _selectedProvinceId = value);
                if (value != null) _loadWards(value);
              },
            ),
            const SizedBox(height: 12),
            DropdownButtonFormField<int>(
              value: _selectedWardId,
              decoration: const InputDecoration(
                labelText: 'Phường/Xã *',
                border: OutlineInputBorder(),
              ),
              items: _wards.map((w) {
                return DropdownMenuItem(
                  value: w['id'] as int,
                  child: Text(w['name'] as String),
                );
              }).toList(),
              onChanged: (value) => setState(() => _selectedWardId = value),
            ),
            const SizedBox(height: 24),
            const Text(
              'Thông tin pickup',
              style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold),
            ),
            const SizedBox(height: 12),
            DropdownButtonFormField<int>(
              value: _selectedShipperId,
              decoration: const InputDecoration(
                labelText: 'Shipper (tùy chọn)',
                border: OutlineInputBorder(),
              ),
              items: _shippers.map((s) {
                return DropdownMenuItem(
                  value: s['id'] as int,
                  child: Text(s['name'] as String),
                );
              }).toList(),
              onChanged: (value) => setState(() => _selectedShipperId = value),
            ),
            const SizedBox(height: 12),
            ListTile(
              title: Text(_scheduledAt == null
                  ? 'Ngày hẹn (tùy chọn)'
                  : 'Ngày hẹn: ${_scheduledAt!.day}/${_scheduledAt!.month}/${_scheduledAt!.year} ${_scheduledAt!.hour}:${_scheduledAt!.minute.toString().padLeft(2, '0')}'),
              trailing: const Icon(Icons.calendar_today),
              onTap: _pickScheduledDate,
              shape: RoundedRectangleBorder(
                side: const BorderSide(color: Colors.grey),
                borderRadius: BorderRadius.circular(4),
              ),
            ),
            const SizedBox(height: 12),
            TextFormField(
              controller: _noteCtrl,
              decoration: const InputDecoration(
                labelText: 'Ghi chú',
                border: OutlineInputBorder(),
              ),
              maxLines: 3,
            ),
            const SizedBox(height: 24),
            ElevatedButton(
              onPressed: _isSubmitting ? null : _submit,
              style: ElevatedButton.styleFrom(
                padding: const EdgeInsets.all(16),
              ),
              child: _isSubmitting
                  ? const CircularProgressIndicator()
                  : const Text('Tạo phiếu pickup'),
            ),
          ],
        ),
      ),
    );
  }
}
