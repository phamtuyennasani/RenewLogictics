@extends('layouts.app-with-sidebar')

@section('content')
    {{-- Dashboard Page --}}
    <div class="max-w-7xl mx-auto">

        {{-- Page Header --}}
        <div class="mb-8">
            <h1 class="text-2xl font-bold text-neutral-900">Dashboard</h1>
            <p class="text-neutral-500 mt-1">
                Xin chào, {{ Auth::user()->fullname ?? Auth::user()->username }}! Chào mừng bạn quay trở lại.
            </p>
        </div>

        {{-- Stats Cards --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">

            {{-- Total Orders --}}
            <div class="bg-white rounded-2xl p-6 border border-neutral-200 hover:shadow-md transition-shadow">
                <div class="flex items-center justify-between mb-4">
                    <div class="w-12 h-12 rounded-xl flex items-center justify-center"
                         style="background: linear-gradient(135deg, {{ config('theme.primary.hex', '#3b82f6') }}, {{ config('theme.accent.hex', '#0ea5e9') }});">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                    </div>
                    <span class="text-xs font-medium text-emerald-600 bg-emerald-50 px-2 py-1 rounded-full">+12%</span>
                </div>
                <h3 class="text-3xl font-bold text-neutral-900 mb-1">0</h3>
                <p class="text-sm text-neutral-500">Tổng đơn hàng</p>
            </div>

            {{-- Pending Orders --}}
            <div class="bg-white rounded-2xl p-6 border border-neutral-200 hover:shadow-md transition-shadow">
                <div class="flex items-center justify-between mb-4">
                    <div class="w-12 h-12 rounded-xl bg-amber-100 flex items-center justify-center">
                        <svg class="w-6 h-6 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    <span class="text-xs font-medium text-amber-600 bg-amber-50 px-2 py-1 rounded-full">Chờ xử lý</span>
                </div>
                <h3 class="text-3xl font-bold text-neutral-900 mb-1">0</h3>
                <p class="text-sm text-neutral-500">Đơn chờ duyệt</p>
            </div>

            {{-- Delivered --}}
            <div class="bg-white rounded-2xl p-6 border border-neutral-200 hover:shadow-md transition-shadow">
                <div class="flex items-center justify-between mb-4">
                    <div class="w-12 h-12 rounded-xl bg-emerald-100 flex items-center justify-center">
                        <svg class="w-6 h-6 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    <span class="text-xs font-medium text-emerald-600 bg-emerald-50 px-2 py-1 rounded-full">Hoàn thành</span>
                </div>
                <h3 class="text-3xl font-bold text-neutral-900 mb-1">0</h3>
                <p class="text-sm text-neutral-500">Đơn đã giao</p>
            </div>

            {{-- Revenue --}}
            <div class="bg-white rounded-2xl p-6 border border-neutral-200 hover:shadow-md transition-shadow">
                <div class="flex items-center justify-between mb-4">
                    <div class="w-12 h-12 rounded-xl bg-cyan-100 flex items-center justify-center">
                        <svg class="w-6 h-6 text-cyan-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    <span class="text-xs font-medium text-cyan-600 bg-cyan-50 px-2 py-1 rounded-full">Tháng này</span>
                </div>
                <h3 class="text-3xl font-bold text-neutral-900 mb-1">0đ</h3>
                <p class="text-sm text-neutral-500">Doanh thu</p>
            </div>

        </div>

        {{-- Quick Actions + Recent Orders --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            {{-- Quick Actions --}}
            <div class="lg:col-span-1 bg-white rounded-2xl border border-neutral-200 p-6">
                <h3 class="text-base font-semibold text-neutral-900 mb-4">Tác vụ nhanh</h3>
                <div class="space-y-3">

                    @can('orders.create')
                    <a href="{{ route('orders.create') }}"
                       class="flex items-center gap-3 p-3 rounded-xl bg-neutral-50 hover:bg-[var(--color-primary-50)] hover:border-[var(--color-primary-200)] border border-transparent transition-all group">
                        <div class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0"
                             style="background: linear-gradient(135deg, {{ config('theme.primary.hex', '#3b82f6') }}, {{ config('theme.accent.hex', '#0ea5e9') }});">
                            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                            </svg>
                        </div>
                        <div>
                            <p class="text-sm font-semibold text-neutral-900 group-hover:text-[var(--color-primary-700)]">Tạo đơn mới</p>
                            <p class="text-xs text-neutral-400">Nhanh chóng &amp; dễ dàng</p>
                        </div>
                    </a>
                    @endcan

                    @can('pickups.index')
                    <a href="{{ route('pickups.index') }}"
                       class="flex items-center gap-3 p-3 rounded-xl bg-neutral-50 hover:bg-[var(--color-primary-50)] hover:border-[var(--color-primary-200)] border border-transparent transition-all group">
                        <div class="w-10 h-10 rounded-xl bg-neutral-200 flex items-center justify-center shrink-0">
                            <svg class="w-5 h-5 text-neutral-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                            </svg>
                        </div>
                        <div>
                            <p class="text-sm font-semibold text-neutral-900 group-hover:text-[var(--color-primary-700)]">Quản lý Pickup</p>
                            <p class="text-xs text-neutral-400">Theo dõi lấy hàng</p>
                        </div>
                    </a>
                    @endcan

                    @can('orders.index')
                    <a href="{{ route('orders.index') }}"
                       class="flex items-center gap-3 p-3 rounded-xl bg-neutral-50 hover:bg-[var(--color-primary-50)] hover:border-[var(--color-primary-200)] border border-transparent transition-all group">
                        <div class="w-10 h-10 rounded-xl bg-neutral-200 flex items-center justify-center shrink-0">
                            <svg class="w-5 h-5 text-neutral-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                            </svg>
                        </div>
                        <div>
                            <p class="text-sm font-semibold text-neutral-900 group-hover:text-[var(--color-primary-700)]">Danh sách đơn hàng</p>
                            <p class="text-xs text-neutral-400">Xem &amp; quản lý đơn</p>
                        </div>
                    </a>
                    @endcan

                    @can('thongke')
                    <a href="{{ route('thongke') }}"
                       class="flex items-center gap-3 p-3 rounded-xl bg-neutral-50 hover:bg-[var(--color-primary-50)] hover:border-[var(--color-primary-200)] border border-transparent transition-all group">
                        <div class="w-10 h-10 rounded-xl bg-neutral-200 flex items-center justify-center shrink-0">
                            <svg class="w-5 h-5 text-neutral-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                            </svg>
                        </div>
                        <div>
                            <p class="text-sm font-semibold text-neutral-900 group-hover:text-[var(--color-primary-700)]">Xem thống kê</p>
                            <p class="text-xs text-neutral-400">Báo cáo chi tiết</p>
                        </div>
                    </a>
                    @endcan

                </div>
            </div>

            {{-- Recent Orders --}}
            <div class="lg:col-span-2 bg-white rounded-2xl border border-neutral-200 p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-base font-semibold text-neutral-900">Đơn hàng gần đây</h3>
                    <a href="{{ route('orders.index') }}"
                       class="text-sm font-medium hover:opacity-80 transition-opacity"
                       style="color: {{ config('theme.primary.hex', '#3b82f6') }};">
                        Xem tất cả →
                    </a>
                </div>

                <div class="text-center py-16">
                    <div class="w-16 h-16 rounded-2xl bg-neutral-100 flex items-center justify-center mx-auto mb-4">
                        <svg class="w-8 h-8 text-neutral-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                    </div>
                    <p class="text-neutral-500 font-medium">Chưa có đơn hàng nào</p>
                    <p class="text-neutral-400 text-sm mt-1">Danh sách đơn hàng sẽ xuất hiện tại đây</p>
                    @can('orders.create')
                    <a href="{{ route('orders.create') }}"
                       class="inline-flex items-center gap-2 mt-5 px-5 py-2.5 rounded-xl text-white font-medium text-sm transition-all hover:opacity-90"
                       style="background: linear-gradient(135deg, {{ config('theme.primary.hex', '#3b82f6') }}, {{ config('theme.accent.hex', '#0ea5e9') }}); box-shadow: 0 4px 14px 0 rgba(59, 130, 246, 0.35);">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                        </svg>
                        Tạo đơn đầu tiên
                    </a>
                    @endcan
                </div>
            </div>
        </div>
    </div>
@endsection
